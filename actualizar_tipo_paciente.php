<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos.']);
    exit;
}

$id = $data['id'] ?? null;
$nombre = $data['nombre'] ?? null;
$descripcion = $data['descripcion'] ?? '';
$limite = $data['limite_citas_diarias'] ?? 10000;

if (empty($id) || empty($nombre)) {
    echo json_encode(['success' => false, 'error' => 'El ID y el nombre son obligatorios.']);
    exit;
}

if (!is_numeric($limite)) {
    echo json_encode(['success' => false, 'error' => 'El límite de citas debe ser un número.']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE agenda_tipos_paciente SET nombre = ?, descripcion = ?, limite_citas_diarias = ? WHERE id = ?");
    $stmt->bind_param('ssii', $nombre, $descripcion, $limite, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        if ($conn->errno == 1062) {
            echo json_encode(['success' => false, 'error' => 'Ya existe otro tipo de paciente con ese nombre.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al actualizar en la base de datos: ' . $stmt->error]);
        }
    }

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}

$conn->close();
?>