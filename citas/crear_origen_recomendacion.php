<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!puedeRealizar('gestionar_origenes_recomendacion')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$nombre = trim($data['nombre'] ?? '');

// El dueño de los orígenes siempre debe ser el ID del "Padre" o el propio ID si es cuenta principal.
$usuario_id_real = $_SESSION['usuario_id'] ?? 0;
$usuario_id_creador = (!empty($_SESSION['id_padre']) && (int)$_SESSION['id_padre'] > 0) ? $_SESSION['id_padre'] : $usuario_id_real;

if (empty($nombre)) {
    echo json_encode(['success' => false, 'error' => 'El nombre del origen es requerido.']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO agenda_origenes_recomendacion (nombre, usuario_id) VALUES (?, ?)");
    if (!$stmt) throw new Exception($conn->error);
    
    $stmt->bind_param("si", $nombre, $usuario_id_creador);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        if ($conn->errno == 1062) {
            echo json_encode(['success' => false, 'error' => 'Ya existe este origen.']);
        } else {
            throw new Exception($stmt->error);
        }
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}