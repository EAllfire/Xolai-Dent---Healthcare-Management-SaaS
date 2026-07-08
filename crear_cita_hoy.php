<?php
/**
 * crear_cita_hoy.php
 * Script seguro para crear una cita en la BD para la fecha de hoy.
 * Uso (opcional): enviar por POST o GET alguno de estos campos:
 * - paciente_id
 * - nombre, apellido, telefono, correo (si paciente_id no existe, se creará)
 * - servicio_id
 * - modalidad_id
 * - hora_inicio (formato HH:MM)
 * - duracion_minutos (default 30)
 * - estado_id (default 1 = reservado)
 *
 * El script valida solapamientos en la misma modalidad y retorna JSON con el resultado.
 */

require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // Helpers para obtener parámetros
    $get = function($k, $default = null) {
        if (isset($_POST[$k])) return $_POST[$k];
        if (isset($_GET[$k])) return $_GET[$k];
        return $default;
    };

    $paciente_id = $get('paciente_id');
    $nombre = trim($get('nombre', 'Paciente')); 
    $apellido = trim($get('apellido', 'Prueba'));
    $telefono = trim($get('telefono', '0000000000'));
    $correo = trim($get('correo', ''));
    $servicio_id = $get('servicio_id');
    $modalidad_id = $get('modalidad_id');
    $hora_inicio = $get('hora_inicio'); // HH:MM
    $duracion = intval($get('duracion_minutos', 30));
    $estado_id = intval($get('estado_id', 1)); // por defecto 'reservado'

    $fecha = date('Y-m-d'); // hoy

    // 1) Si no nos dieron servicio, obtener uno por defecto
    if (empty($servicio_id)) {
        $r = $conn->query("SELECT id, modalidad_id FROM portal_servicios ORDER BY id LIMIT 1");
        if (!$r || $r->num_rows === 0) throw new Exception('No existen servicios en la base de datos.');
        $row = $r->fetch_assoc();
        $servicio_id = intval($row['id']);
        if (empty($modalidad_id) && !empty($row['modalidad_id'])) $modalidad_id = intval($row['modalidad_id']);
    }

    // 2) Si no nos dieron modalidad, intentar obtener de servicio o escoger una disponible
    if (empty($modalidad_id)) {
        if (!empty($servicio_id)) {
            $stmt = $conn->prepare("SELECT modalidad_id FROM portal_servicios WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $servicio_id);
            $stmt->execute();
            $stmt->bind_result($mid);
            if ($stmt->fetch()) {
                $modalidad_id = intval($mid);
            }
            $stmt->close();
        }
    }
    if (empty($modalidad_id)) {
        $r = $conn->query("SELECT id FROM agenda_modalidades ORDER BY id LIMIT 1");
        if ($r && $r->num_rows > 0) {
            $modalidad_id = intval($r->fetch_assoc()['id']);
        } else {
            throw new Exception('No existen modalidades en la base de datos.');
        }
    }

    // 3) Si no nos dieron hora, calcular próximo slot 30-minutos redondeado
    if (empty($hora_inicio)) {
        $now = new DateTime();
        $mins = intval($now->format('i'));
        $add = ($mins % 30 === 0) ? 0 : (30 - ($mins % 30));
        if ($add > 0) $now->modify("+{$add} minutes");
        $hora_inicio = $now->format('H:i');
    } else {
        // validate format
        $d = DateTime::createFromFormat('H:i', $hora_inicio);
        if (!$d) throw new Exception('Formato de hora_inicio inválido. Usa HH:MM.');
        $hora_inicio = $d->format('H:i');
    }

    // 4) calcular hora_fin
    $dt = DateTime::createFromFormat('H:i', $hora_inicio);
    if (!$dt) throw new Exception('Hora inicio inválida.');
    $dt->modify("+{$duracion} minutes");
    $hora_fin = $dt->format('H:i');

    // --- NUEVO PASO: OBTENER EL usuario_id DEL SERVICIO O MODALIDAD ---
    $usuario_propietario_id = null;
    if (!empty($servicio_id)) {
        $stmt_owner = $conn->prepare("SELECT usuario_id FROM portal_servicios WHERE id = ?");
        $stmt_owner->bind_param("i", $servicio_id);
        $stmt_owner->execute();
        $stmt_owner->bind_result($owner_id);
        if ($stmt_owner->fetch()) {
            $usuario_propietario_id = $owner_id;
        }
        $stmt_owner->close();
    }
    if (empty($usuario_propietario_id) && !empty($modalidad_id)) {
        $stmt_owner = $conn->prepare("SELECT usuario_id FROM agenda_modalidades WHERE id = ?");
        $stmt_owner->bind_param("i", $modalidad_id);
        $stmt_owner->execute();
        $stmt_owner->bind_result($owner_id);
        if ($stmt_owner->fetch()) {
            $usuario_propietario_id = $owner_id;
        }
        $stmt_owner->close();
    }
    // Como es un script de prueba, si no se encuentra un dueño, se asigna al admin por defecto.
    if (empty($usuario_propietario_id)) $usuario_propietario_id = 1;

    // 5) asegurar paciente: si no existe paciente_id buscar por telefono o crear
    if (empty($paciente_id)) {
        $paciente_id = null;
        if (!empty($telefono)) {
            $stmt = $conn->prepare("SELECT id FROM portal_pacientes WHERE telefono = ? LIMIT 1");
            $stmt->bind_param('s', $telefono);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($pid);
                $stmt->fetch();
                $paciente_id = intval($pid);
            }
            $stmt->close();
        }
        if (empty($paciente_id)) {
            // crear paciente mínimo
            // CORRECCIÓN: Usar columnas 'alergias' y 'tipo_id' y añadir 'usuario_id'
            $stmt = $conn->prepare("INSERT INTO portal_pacientes (usuario_id, nombre, apellido, telefono, correo, alergias, tipo_id, origen) VALUES (?, ?, ?, ?, ?, ?, 1, 'manual')");
            $diag = 'Registro automático para prueba';
            $stmt->bind_param('isssss', $usuario_propietario_id, $nombre, $apellido, $telefono, $correo, $diag);
            if (!$stmt->execute()) throw new Exception('Error al crear paciente: ' . $stmt->error);
            $paciente_id = $conn->insert_id;
            $stmt->close();
        }
    }

    // 6) Verificar solapamientos en la misma modalidad
    $sqlCheck = "SELECT COUNT(*) as total FROM agenda_citas WHERE fecha = ? AND modalidad_id = ? AND ((hora_inicio < ? AND hora_fin > ?) OR (hora_inicio >= ? AND hora_inicio < ?))";
    $stmt = $conn->prepare($sqlCheck);
    $stmt->bind_param('sisss', $fecha, $modalidad_id, $hora_fin, $hora_inicio, $hora_inicio, $hora_fin);
    $stmt->execute();
    $stmt->bind_result($overlap_count);
    $stmt->fetch();
    $stmt->close();

    if (intval($overlap_count) > 0) {
        echo json_encode(['success' => false, 'error' => 'Existe una cita solapada en la misma modalidad en ese horario.', 'overlaps' => intval($overlap_count), 'fecha'=>$fecha, 'hora_inicio'=>$hora_inicio, 'hora_fin'=>$hora_fin, 'modalidad_id'=>$modalidad_id]);
        exit;
    }

    // 7) Insertar la cita
    // CORRECCIÓN: Añadir 'usuario_id' a la inserción de la cita
    $stmt = $conn->prepare("INSERT INTO agenda_citas (usuario_id, fecha, hora_inicio, hora_fin, paciente_id, profesional_id, servicio_id, modalidad_id, estado_id, nota_paciente, nota_interna, tipo) VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, '', 'manual')");
    if (!$stmt) throw new Exception('Error preparando insert cita: ' . $conn->error);
    $nota = 'Creada por crear_cita_hoy.php';
    $prof = null; // no asignamos profesional
    $stmt->bind_param('isssiiiss', $usuario_propietario_id, $fecha, $hora_inicio, $hora_fin, $paciente_id, $servicio_id, $modalidad_id, $estado_id, $nota);
    if (!$stmt->execute()) {
        throw new Exception('Error al insertar cita: ' . $stmt->error);
    }
    $cita_id = $conn->insert_id;
    $stmt->close();

    echo json_encode(['success' => true, 'cita_id' => $cita_id, 'fecha'=>$fecha, 'hora_inicio'=>$hora_inicio, 'hora_fin'=>$hora_fin, 'paciente_id'=>$paciente_id, 'servicio_id'=>$servicio_id, 'modalidad_id'=>$modalidad_id]);
    exit;

} catch (Exception $ex) {
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
    exit;
}

?>