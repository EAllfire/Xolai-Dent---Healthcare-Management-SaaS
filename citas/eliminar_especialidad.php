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

if (empty($id)) {
    echo json_encode(['success' => false, 'error' => 'El ID es obligatorio.']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM agenda_especialidades WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se encontró ninguna especialidad con ese ID.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al eliminar de la base de datos: ' . $stmt->error]);
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}
$conn->close();
?>