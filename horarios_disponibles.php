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

try {
    if (!$conn) {
        throw new Exception("No se pudo establecer la conexión a la base de datos.");
    }

    // 1. Obtener la duración del servicio que se quiere agendar
    $duracion_servicio_actual = 30; // Duración por defecto en minutos
    $stmt_duracion = $conn->prepare("SELECT duracion FROM portal_servicios WHERE id = ?");
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

    // 2. Obtener todos los horarios OCUPADOS para esa fecha y modalidad.
    // Se incluyen citas activas (estado != 7) y bloqueos.
    // La consulta ahora busca cualquier cita que INTERSECTE con el día,
    // incluso si su hora de fin se extiende más allá de la medianoche (aunque es poco común).
    $sql = "SELECT TIME_FORMAT(hora_inicio, '%H:%i') as inicio, TIME_FORMAT(hora_fin, '%H:%i') as fin
            FROM agenda_citas 
            WHERE fecha = ? AND modalidad_id = ? AND estado_id != 7
            ORDER BY hora_inicio ASC";
    $stmt_citas = $conn->prepare($sql);
    if ($stmt_citas === false) {
        throw new Exception("Error al preparar la consulta de citas: " . $conn->error);
    }
    $stmt_citas->bind_param("si", $fecha, $modalidad_id);
    $stmt_citas->execute();
    $stmt_citas->store_result();
    $stmt_citas->bind_result($inicio, $fin);
    $citas_ocupadas = [];
    while ($stmt_citas->fetch()) {
        $citas_ocupadas[] = ['inicio' => $inicio, 'fin' => $fin];
    }
    $stmt_citas->close();


    // 3. Generar todos los posibles horarios y luego filtrarlos
    $intervalo_minutos = 15; // Revisar disponibilidad cada 15 minutos
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