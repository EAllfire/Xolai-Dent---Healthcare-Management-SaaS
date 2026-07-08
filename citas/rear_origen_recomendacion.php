<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!puedeRealizar('gestionar_origenes_recomendacion')) { // Nuevo permiso
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$nombre = trim($data['nombre'] ?? '');
$usuario_id_creador = $_SESSION['id_padre'] ?? $_SESSION['usuario_id']; // Asociar al padre o al propio usuario

if (empty($nombre)) {
    echo json_encode(['success' => false, 'error' => 'El nombre del origen es requerido.']);
    exit;
}
if (empty($usuario_id_creador)) {
    echo json_encode(['success' => false, 'error' => 'No se pudo identificar al usuario creador.']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO agenda_origenes_recomendacion (nombre, usuario_id) VALUES (?, ?)");
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    $stmt->bind_param("si", $nombre, $usuario_id_creador);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        if ($conn->errno == 1062) { // Error de entrada duplicada
            echo json_encode(['success' => false, 'error' => 'Ya existe un origen de recomendación con ese nombre.']);
        } else {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en crear_origen_recomendacion.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
