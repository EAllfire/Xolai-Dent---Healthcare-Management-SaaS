<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

header('Content-Type: application/json');

ob_start();
ob_clean();

try {
    require_once("includes/db.php");

    $cliente_id = $_GET['cliente_id'] ?? null;

    if (empty($cliente_id)) {
        echo json_encode(['success' => false, 'error' => 'ID de cliente no proporcionado.']);
        exit;
    }

    $cliente_id = intval($cliente_id);

    $stmt = $conn->prepare(
        "SELECT 
            c.id, 
            c.fecha, 
            c.hora_inicio, 
            c.hora_fin, 
            s.nombre AS servicio_nombre, 
            m.nombre AS modalidad_nombre,
            e.nombre AS estado_nombre
         FROM agenda_citas c
         JOIN agenda_servicios s ON c.servicio_id = s.id
         JOIN agenda_modalidades m ON c.modalidad_id = m.id
         JOIN agenda_estados e ON c.estado_id = e.id
         WHERE c.paciente_id = ?
         ORDER BY c.fecha DESC, c.hora_inicio DESC"
    );
    if ($stmt === false) {
        throw new Error('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $citas = [];
    while ($row = $result->fetch_assoc()) {
        $citas[] = $row;
    }

    echo json_encode($citas);

    $stmt->close();
    
} catch (Throwable $t) {
    error_log("Error fatal en citas_cliente_json.php: " . $t->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor. Por favor, contacte al administrador.', 'details' => $t->getMessage()]);
}
?>