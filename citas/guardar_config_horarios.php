<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';

// Permitir si tiene permiso de sistema O si es médico explícitamente
if (!puedeRealizar('configurar_sistema') && $usuario_tipo !== 'medico') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tiene permisos para realizar esta acción.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos.']);
    exit;
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("INSERT INTO agenda_configuracion (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");

    foreach ($data as $key => $value) {
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
    }

    $stmt->close();
    $conn->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
}
?>