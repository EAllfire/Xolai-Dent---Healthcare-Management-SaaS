<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos.']);
    exit;
}

$nombre = $data['nombre'] ?? null;
$descripcion = $data['descripcion'] ?? '';
$limite = $data['limite_citas_diarias'] ?? 10000;

if (empty($nombre)) {
    echo json_encode(['success' => false, 'error' => 'El nombre es obligatorio.']);
    exit;
}

if (!is_numeric($limite)) {
    echo json_encode(['success' => false, 'error' => 'El límite de citas debe ser un número.']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO agenda_tipos_paciente (nombre, descripcion, limite_citas_diarias) VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $nombre, $descripcion, $limite);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        // Check for duplicate entry
        if ($conn->errno == 1062) {
            echo json_encode(['success' => false, 'error' => 'Ya existe un tipo de paciente con ese nombre.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al guardar en la base de datos: ' . $stmt->error]);
        }
    }
    
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}

$conn->close();
?>