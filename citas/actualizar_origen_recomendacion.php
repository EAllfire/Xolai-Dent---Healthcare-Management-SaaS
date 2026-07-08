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
$id = (int)($data['id'] ?? 0);
$nombre = trim($data['nombre'] ?? '');
$usuario_id_creador = $_SESSION['id_padre'] ?? $_SESSION['usuario_id'];

if ($id <= 0 || empty($nombre)) {
    echo json_encode(['success' => false, 'error' => 'ID y nombre del origen son requeridos.']);
    exit;
}
if (empty($usuario_id_creador)) {
    echo json_encode(['success' => false, 'error' => 'No se pudo identificar al usuario creador.']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE agenda_origenes_recomendacion SET nombre = ? WHERE id = ? AND usuario_id = ?");
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    $stmt->bind_param("sii", $nombre, $id, $usuario_id_creador);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se encontró el origen o no se realizaron cambios.']);
        }
    } else {
        if ($conn->errno == 1062) { // Error de entrada duplicada
            echo json_encode(['success' => false, 'error' => 'Ya existe otro origen de recomendación con ese nombre.']);
        } else {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en actualizar_origen_recomendacion.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
