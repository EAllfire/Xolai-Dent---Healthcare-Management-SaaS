<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!puedeRealizar('gestionar_modalidades')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
$usuario_id = $_SESSION['usuario_id'];

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de modalidad inválido']);
    exit;
}

// Opcional: obtener la ruta del archivo para eliminarlo del servidor
$stmt_get = $conn->prepare("SELECT imagen FROM agenda_modalidades WHERE id = ? AND usuario_id = ?");
if ($stmt_get) {
    $stmt_get->bind_param('ii', $id, $usuario_id);
    $stmt_get->execute();
    $stmt_get->bind_result($imagen_path);
    if ($stmt_get->fetch() && !empty($imagen_path)) {
        // Construir la ruta completa del archivo en el servidor
        $file_to_delete = __DIR__ . '/../' . ltrim($imagen_path, '/');
        if (file_exists($file_to_delete)) {
            @unlink($file_to_delete); // Intentar eliminar el archivo, suprimir errores si falla
        }
    }
    $stmt_get->close();
}


// Actualizar la base de datos para quitar la referencia a la imagen
$stmt = $conn->prepare("UPDATE agenda_modalidades SET imagen = NULL WHERE id = ? AND usuario_id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta: ' . $conn->error]);
    exit;
}

$stmt->bind_param('ii', $id, $usuario_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al actualizar la base de datos: ' . $stmt->error]);
}
$stmt->close();
?>