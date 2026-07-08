<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$user_tipo = $_SESSION['usuario_tipo'] ?? '';
$id_padre = intval($_SESSION['id_padre'] ?? 0);
$es_dentista_principal = ($user_tipo === 'dentista' && $id_padre === 0);
$es_admin_derivado = ($user_tipo === 'admin' && $id_padre > 0);

$puede_gestionar_bloqueos = in_array($user_tipo, ['superadmin', 'admin', 'medico', 'dentista'])
    || $es_dentista_principal
    || $es_admin_derivado
    || puedeRealizar('crear_citas');

if (!$puede_gestionar_bloqueos) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tiene permisos para crear bloqueos.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// --- Validación de Datos ---
$fecha = $data['fecha'] ?? null;
$fecha_fin = $data['fecha_fin'] ?? $fecha; // Si no se envía fin, es el mismo día
$hora_inicio = $data['hora_inicio'] ?? null;
$hora_fin = $data['hora_fin'] ?? null;
$modalidad_id = !empty($data['modalidad_id']) ? intval($data['modalidad_id']) : null;
$profesional_id = !empty($data['profesional_id']) ? intval($data['profesional_id']) : null;

if (!$fecha || !$hora_inicio || !$hora_fin || (!$modalidad_id && !$profesional_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Faltan datos requeridos (fecha, horas y consultorio o doctor).']);
    exit;
}

// --- Lógica de Bloqueo ---
try {
    $motivo = $data['motivo'] ?? 'Bloqueo manual de horario.';

    $estado_bloqueado_id = 9;
    $usuario_id = $_SESSION['usuario_id'] ?? 1;
    $paciente_id = 1;
    $servicio_id = 1;
    $tipo = 'bloqueo';

    $stmt = $conn->prepare(
        "INSERT INTO agenda_citas (usuario_id, fecha, hora_inicio, hora_fin, paciente_id, profesional_id, servicio_id, modalidad_id, estado_id, nota_interna, tipo) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $begin = new DateTime($fecha);
    $end = new DateTime($fecha_fin);
    $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($begin, $interval, $end);

    $inserted_ids = [];

    foreach ($daterange as $date) {
        $fecha_actual = $date->format("Y-m-d");
        $stmt->bind_param(
            "isssiiiisss",
            $usuario_id,
            $fecha_actual,
            $hora_inicio,
            $hora_fin,
            $paciente_id,
            $profesional_id,
            $servicio_id,
            $modalidad_id,
            $estado_bloqueado_id,
            $motivo,
            $tipo
        );

        if ($stmt->execute()) {
            $inserted_ids[] = $conn->insert_id;
        }
    }

    if (count($inserted_ids) > 0) {
        echo json_encode(['success' => true, 'ids' => $inserted_ids]);
    } else {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>