<?php
header('Content-Type: application/json');
require_once("includes/db.php");

$fecha = $_POST['fecha'] ?? '';
$hora_inicio = $_POST['hora_inicio'] ?? '';
$hora_fin = $_POST['hora_fin'] ?? '';
$paciente_id = $_POST['paciente_id'] ?? null;
$profesional_id = $_POST['profesional_id'] ?? 1; // Por defecto profesional 1
$servicio_id = $_POST['servicio_id'] ?? null;
$modalidad_id = $_POST['modalidad_id'] ?? null;
$estado_id = $_POST['estado_id'] ?? 1; // Estado "reservado" por defecto
$tipo = $_POST['tipo'] ?? 'normal';
$nota_interna = $_POST['nota_interna'] ?? '';
$nota_paciente = $_POST['nota_paciente'] ?? '';

$response = [];

try {
    if ($fecha && $hora_inicio && $paciente_id && $modalidad_id) { // Requerir solo los campos esenciales

        // --- LÓGICA DE HORA_FIN CORREGIDA ---
        // Solo calcular la hora_fin si el campo viene explícitamente vacío.
        // Si el usuario envía un valor (incluso para un bloqueo sin servicio), se respeta.
        if (empty($hora_fin)) {
            if (!empty($servicio_id)) {
                $stmt_duracion = $conn->prepare("SELECT duracion_minutos FROM portal_servicios WHERE id = ?");
                $stmt_duracion->bind_param("i", $servicio_id);
                $stmt_duracion->execute();
                $stmt_duracion->bind_result($duracion);
                $stmt_duracion->fetch(); $stmt_duracion->close();
                $minutos_a_sumar = ($duracion > 0) ? intval($duracion) : 30; // Default 30 si el servicio no tiene duración
                $hora_fin = date('H:i:s', strtotime($hora_inicio) + $minutos_a_sumar * 60);
            } else {
                // Si no hay ni hora_fin ni servicio, se usa un default de 30 min.
                $hora_fin = date('H:i:s', strtotime($hora_inicio) + 1800);
            }
        }
        
        // Escapar valores para consulta directa
        $fecha = $conn->real_escape_string($fecha);
        $hora_inicio = $conn->real_escape_string($hora_inicio);
        $hora_fin = $conn->real_escape_string($hora_fin);
        $nota_paciente = $conn->real_escape_string($nota_paciente);
        $nota_interna = $conn->real_escape_string($nota_interna);
        $tipo = $conn->real_escape_string($tipo);
        
        $paciente_id = intval($paciente_id);
        $servicio_id = !empty($servicio_id) ? intval($servicio_id) : null;
        $modalidad_id = intval($modalidad_id);
        $estado_id = intval($estado_id);

        // Modalities that don't require a specific professional
        $modalidades_sin_profesional = [3, 4, 5, 6, 7, 8]; 
        if (in_array($modalidad_id, $modalidades_sin_profesional)) {
            $profesional_id = null;
        } else {
            $profesional_id = intval($profesional_id);
        }
        
        // Verificar empalme de citas
        $sqlEmpalme = "SELECT COUNT(*) as total FROM agenda_citas 
                       WHERE fecha = '$fecha' AND modalidad_id = $modalidad_id 
                       AND ((hora_inicio < '$hora_fin' AND hora_fin > '$hora_inicio') 
                       OR (hora_inicio < '$hora_inicio' AND hora_fin > '$hora_fin') 
                       OR (hora_inicio >= '$hora_inicio' AND hora_inicio < '$hora_fin'))";
        
        $resultEmpalme = $conn->query($sqlEmpalme);
        
        if (!$resultEmpalme) {
            throw new Exception("Error en consulta empalme: " . $conn->error);
        }
        
        $rowEmpalme = $resultEmpalme->fetch_assoc();
        
        if ($rowEmpalme['total'] > 0) {
            $response = ["success" => false, "error" => "Ya existe una cita en ese horario para la modalidad seleccionada."];
        } else {
            // Insertar nueva cita
            $profesional_id_sql = is_null($profesional_id) ? "NULL" : $profesional_id;
            $servicio_id_sql = is_null($servicio_id) ? "NULL" : $servicio_id;
            $sqlInsert = "INSERT INTO agenda_citas (fecha, paciente_id, profesional_id, servicio_id, estado_id, nota_paciente, nota_interna, hora_inicio, hora_fin, modalidad_id, tipo) 
                         VALUES ('$fecha', $paciente_id, $profesional_id_sql, $servicio_id_sql, $estado_id, '$nota_paciente', '$nota_interna', '$hora_inicio', '$hora_fin', $modalidad_id, '$tipo')";
            
            if ($conn->query($sqlInsert)) {
                $id = $conn->insert_id;
                $response = ["success" => true, "id" => $id];
            } else {
                $debug_info = " | Modalidad ID: " . $modalidad_id . " | Profesional ID (before): " . ($_POST['profesional_id'] ?? 'not set') . " | Profesional ID (after): " . (is_null($profesional_id) ? 'NULL' : $profesional_id) . " | Profesional ID (SQL): " . $profesional_id_sql;
                $response = ["success" => false, "error" => "Error al insertar: " . $conn->error . $debug_info];
            }
        }
    } else {
        $response = ["success" => false, "error" => "Faltan datos obligatorios: fecha, hora_inicio, paciente_id, modalidad_id."];
    }
} catch (Exception $e) {
    $response = ["success" => false, "error" => $e->getMessage()];
}

echo json_encode($response);
?>