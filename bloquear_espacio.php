<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db.php';

$data = json_decode(file_get_contents('php://input'), true);

// --- Validación de Datos ---
$fecha = $data['fecha'] ?? null;
$hora_inicio = $data['hora_inicio'] ?? null;
$hora_fin = $data['hora_fin'] ?? null;
$modalidad_id = $data['modalidad_id'] ?? null;

if (!$fecha || !$hora_inicio || !$hora_fin || !$modalidad_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Faltan datos requeridos (fecha, horas, modalidad).']);
    exit;
}

// --- Lógica de Bloqueo ---
try {
    $motivo = $data['motivo'] ?? 'Bloqueo manual de horario.';

    // El estado_id = 9 se asume que es "Bloqueado".
    // Si no existe, hay que crearlo en la tabla `agenda_estado_cita`.
    // `id`: 9, `nombre`: 'Bloqueado', `color`: '#888888'
    $estado_bloqueado_id = 9; 

    // No se asigna paciente_id ni servicio_id para los bloqueos.
    $stmt = $conn->prepare(
        "INSERT INTO agenda_citas (fecha, hora_inicio, hora_fin, modalidad_id, estado_id, nota_interna) 
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param("sssiis", $fecha, $hora_inicio, $hora_fin, $modalidad_id, $estado_bloqueado_id, $motivo);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
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