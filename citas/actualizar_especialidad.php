<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Verificar que solo los admins puedan acceder
$user_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';
$es_dentista_principal = ($user_tipo === 'dentista' && empty($_SESSION['id_padre']));

if (!puedeRealizar('gestionar_especialidades') && !$es_dentista_principal) {
    echo json_encode(['success' => false, 'error' => 'Acceso no autorizado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos.']);
    exit;
}

$id = $data['id'] ?? null;
$nombre = $data['nombre'] ?? null;

if (empty($id) || empty($nombre)) {
    echo json_encode(['success' => false, 'error' => 'El ID y el nombre son obligatorios.']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE agenda_especialidades SET nombre = ? WHERE id = ?");
    $stmt->bind_param('si', $nombre, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar en la base de datos: ' . $stmt->error]);
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}
$conn->close();
?>