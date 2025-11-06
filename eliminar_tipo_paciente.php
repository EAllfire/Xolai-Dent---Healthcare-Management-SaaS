<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

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
    $stmt = $conn->prepare("DELETE FROM agenda_tipos_paciente WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se encontró ningún tipo de paciente con ese ID.']);
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