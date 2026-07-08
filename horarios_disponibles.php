<?php
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('log_errors', 1);
ini_set('error_log', 'php-error.log');

require_once(__DIR__ . "/includes/db.php");

$fecha = $_GET['fecha'] ?? '';
$modalidad_id = isset($_GET['modalidad_id']) ? intval($_GET['modalidad_id']) : 0;
$servicio_id = isset($_GET['servicio_id']) ? intval($_GET['servicio_id']) : 0;
$usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;

try {
    if (!$conn) {
        throw new Exception("No se pudo establecer la conexión a la base de datos.");
    }

    // Intento de recuperación: Si falta modalidad_id pero tenemos servicio_id, buscarlo en la BD
    if ($modalidad_id <= 0 && $servicio_id > 0) {
        $stmt_mod = $conn->prepare("SELECT modalidad_id FROM portal_servicios WHERE id = ?");
        if ($stmt_mod) {
            $stmt_mod->bind_param("i", $servicio_id);
            $stmt_mod->execute();
            $stmt_mod->bind_result($db_mod_id);
            if ($stmt_mod->fetch()) {
                $modalidad_id = (int)$db_mod_id;
            }
            $stmt_mod->close();
        }
    }

    // Validaciones (ahora que ya intentamos recuperar la modalidad)
    if (empty($fecha) || $modalidad_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Fecha y modalidad son requeridos.']);
        exit;
    }

    if ($servicio_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'El servicio es requerido para calcular la disponibilidad.']);
        exit;
    }

    // --- INICIO: Cargar configuración de horarios ---
    $config_result = $conn->query("SELECT config_key, config_value FROM agenda_configuracion");
    $config = [];
    if ($config_result) {
        while($row = $config_result->fetch_assoc()) {
            $config[$row['config_key']] = $row['config_value'];
        }
    }
    $intervalo_minutos = (int)($config['slot_interval'] ?? 15);
    $blocked_times_json = $config['blocked_times'] ?? '[]';
    $tramos_bloqueados = json_decode($blocked_times_json, true);
    if (!is_array($tramos_bloqueados)) $tramos_bloqueados = [];
    // --- FIN: Cargar configuración de horarios ---

    // 1. Obtener la duración del servicio que se quiere agendar
    $duracion_servicio_actual = 30; // Duración por defecto en minutos
    $stmt_duracion = $conn->prepare("SELECT duracion_minutos FROM portal_servicios WHERE id = ?");
    if ($stmt_duracion) {
        $stmt_duracion->bind_param("i", $servicio_id);
        $stmt_duracion->execute();
        $stmt_duracion->bind_result($duracion_db);
        if ($stmt_duracion->fetch()) {
            $duracion_servicio_actual = (int)$duracion_db;
        }
        $stmt_duracion->close();
    } else {
        throw new Exception("Error al preparar la consulta de duración: " . $conn->error);
    }

    // 2. Obtener horarios ocupados (citas y bloqueos).
    // Ahora validamos tanto la modalidad (consultorio) como el profesional específico (doctor).
    $sql = "SELECT TIME_FORMAT(hora_inicio, '%H:%i') as inicio, TIME_FORMAT(hora_fin, '%H:%i') as fin
            FROM agenda_citas 
            WHERE fecha = ? AND estado_id != 7 
            AND (modalidad_id = ? OR profesional_id = ?)";
    
    // Si se proporciona usuario_id, filtrar por él para cargar disponibilidad específica
    if ($usuario_id > 0) {
        $sql .= " AND usuario_id = ?";
        $sql .= " ORDER BY hora_inicio ASC";
        $stmt_citas = $conn->prepare($sql);
        if (!$stmt_citas) {
            throw new Exception("Error al preparar consulta de disponibilidad: " . $conn->error);
        }
        $stmt_citas->bind_param("siii", $fecha, $modalidad_id, $usuario_id, $usuario_id);
    } else {
        $sql .= " ORDER BY hora_inicio ASC";
        $stmt_citas = $conn->prepare($sql);
        if (!$stmt_citas) {
            throw new Exception("Error al preparar consulta de disponibilidad: " . $conn->error);
        }
        $stmt_citas->bind_param("si", $fecha, $modalidad_id);
    }
    
    $stmt_citas->execute();
    $stmt_citas->store_result();
    $stmt_citas->bind_result($inicio, $fin);
    $citas_ocupadas = [];
    while ($stmt_citas->fetch()) {
        $citas_ocupadas[] = ['inicio' => $inicio, 'fin' => $fin];
    }
    $stmt_citas->close();


    // 3. Generar todos los posibles horarios y luego filtrarlos
    $tz = new DateTimeZone('America/Mexico_City');

    $ahora = new DateTime('now', $tz);
    $fecha_seleccionada = new DateTime($fecha, $tz);
    $es_hoy = $ahora->format('Y-m-d') === $fecha_seleccionada->format('Y-m-d');

    $fecha_dt = new DateTime($fecha);
    $dia_semana = (int)$fecha_dt->format('w'); // 0 para Domingo

    // Por defecto, el horario es de 8am a 6pm.
    // Se puede ajustar por día si es necesario.
    $hora_inicio_jornada = '07:00';
    $hora_fin_jornada = '22:00'; // Ampliado para dar margen a bloqueos tardíos

    if ($dia_semana === 0) { // Domingo
        // Los domingos están habilitados con el mismo horario.
        // Si se necesita un horario especial para domingos (ej. no laborable), se modifica aquí.
        // Ejemplo: $hora_inicio_jornada = '09:00'; $hora_fin_jornada = '14:00';
    }
    
    $inicio_jornada = new DateTime($fecha . ' ' . $hora_inicio_jornada, $tz);
    $fin_jornada = new DateTime($fecha . ' ' . $hora_fin_jornada, $tz);
    $horarios_disponibles = [];

    // Determinar el punto de partida para la iteración
    $slot_actual = clone $inicio_jornada;

    // Si es hoy, ajustar la hora de inicio al siguiente intervalo de tiempo disponible
    if ($es_hoy && $ahora > $slot_actual) {
        $slot_actual = $ahora;
        
        // Redondear la hora actual al siguiente intervalo de 15 minutos
        $minutos = (int)$slot_actual->format('i');
        $segundos = (int)$slot_actual->format('s');

        // Si hay segundos, pasar al siguiente minuto para asegurar que el slot sea futuro
        if ($segundos > 0) {
            $slot_actual->modify('+1 minute');
        }

        $minutos = (int)$slot_actual->format('i');
        $resto = $minutos % $intervalo_minutos;
        
        if ($resto !== 0) {
            $minutos_a_sumar = $intervalo_minutos - $resto;
            $slot_actual->modify("+$minutos_a_sumar minutes");
        }
        
        // Resetear los segundos a cero para normalizar la hora del slot
        $slot_actual->setTime($slot_actual->format('H'), $slot_actual->format('i'), 0);
    }

    while ($slot_actual < $fin_jornada) {
        $slot_fin = (clone $slot_actual)->modify('+' . $duracion_servicio_actual . ' minutes');

        // Si el final del servicio excede la jornada laboral, no habrá más slots disponibles
        if ($slot_fin > $fin_jornada) {
            break;
        }

        // --- INICIO: Verificación de tramos bloqueados por configuración ---
        $slot_disponible_por_bloqueo = true;
        $slot_fin_bloqueo = (clone $slot_actual)->modify('+' . $duracion_servicio_actual . ' minutes');
        foreach ($tramos_bloqueados as $tramo) {
            $bloqueo_inicio = new DateTime($fecha . ' ' . $tramo['inicio'], $tz);
            $bloqueo_fin = new DateTime($fecha . ' ' . $tramo['fin'], $tz);

            // Comprobar si el slot se superpone con el tramo bloqueado
            if ($slot_actual < $bloqueo_fin && $slot_fin_bloqueo > $bloqueo_inicio) {
                // Si hay un bloqueo, avanza el tiempo hasta el final del bloqueo
                $slot_actual = clone $bloqueo_fin;
                $slot_disponible_por_bloqueo = false;
                break; // Salir del bucle de tramos y re-evaluar el nuevo slot_actual
            }
        }
        if (!$slot_disponible_por_bloqueo) {
            continue; // Saltar este slot y pasar al siguiente
        }
        // --- FIN: Verificación de tramos bloqueados ---

        $es_disponible = true;
        foreach ($citas_ocupadas as $cita) {
            $cita_inicio = new DateTime($fecha . ' ' . $cita['inicio'], $tz);
            $cita_fin = new DateTime($fecha . ' ' . $cita['fin'], $tz);

            // Comprobar si el slot actual se superpone con una cita existente
            if ($slot_actual < $cita_fin && $slot_fin > $cita_inicio) {
                $es_disponible = false;
                break;
            }
        }

        if ($es_disponible) {
            $horarios_disponibles[] = $slot_actual->format('H:i');
        }
        
        // Avanzar al siguiente slot
        $slot_actual->modify('+' . $intervalo_minutos . ' minutes');
    }

    echo json_encode($horarios_disponibles);

} catch (Exception $e) {
    $error_message = "Error en horarios_disponibles.php: " . $e->getMessage();
    error_log($error_message);
    http_response_code(500);
    echo json_encode(['error' => 'Ocurrió un error interno al procesar su solicitud.']);
}
?>