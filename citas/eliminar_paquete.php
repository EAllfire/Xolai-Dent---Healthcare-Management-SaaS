<?php
header('Content-Type: application/json');
session_start(); // <-- AÑADIDO: Iniciar sesión para verificar permisos
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Solo los admins pueden eliminar paquetes
if (!puedeRealizar('gestionar_servicios')) {
    http_response_code(403);
    echo json_encode(['error' => 'No tiene permisos para realizar esta acción.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el ID del paquete.']);
    exit;
}

$id = $data['id'];

try {
    // La tabla `agenda_paquete_servicios` debería tener ON DELETE CASCADE,
    // así que solo necesitamos eliminar de la tabla principal.
    $sql = "DELETE FROM agenda_paquetes WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'El paquete no fue encontrado o no se pudo eliminar.']);
    }
    
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en eliminar_paquete.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error en la base de datos al eliminar el paquete: ' . $e->getMessage()]);
}
?>
