<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Verificar que el usuario sea admin
if (!puedeRealizar('gestionar_servicios')) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    // Leer datos JSON del cuerpo de la petición
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    
    // Validar ID
    if ($id <= 0) {
        throw new Exception("ID de servicio no válido");
    }
    
    // Verificar que el servicio existe (compatible con sistemas sin mysqlnd)
    $stmt_check = $conn->prepare("SELECT nombre FROM portal_servicios WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    if ($stmt_check->num_rows === 0) {
        throw new Exception("El servicio no existe");
    }
    
    $stmt_check->bind_result($nombre_servicio);
    $stmt_check->fetch();
    $servicio = ['nombre' => $nombre_servicio];
    $stmt_check->close();

    // Verificar si hay citas asociadas al servicio (compatible con sistemas sin mysqlnd)
    $stmt_citas = $conn->prepare("SELECT COUNT(*) as total FROM agenda_citas WHERE servicio_id = ?");
    $stmt_citas->bind_param("i", $id);
    $stmt_citas->execute();
    $stmt_citas->bind_result($total_citas);
    $stmt_citas->fetch();
    $citas = ['total' => $total_citas];
    $stmt_citas->close();
    
    if ($citas['total'] > 0) {
        throw new Exception("No se puede eliminar el servicio '{$servicio['nombre']}' porque tiene {$citas['total']} cita(s) asociada(s).");
    }
    
    // Eliminar servicio
    $stmt_delete = $conn->prepare("DELETE FROM portal_servicios WHERE id = ?");
    $stmt_delete->bind_param("i", $id);
    if (!$stmt_delete->execute()) {
        throw new Exception("Error al eliminar el servicio: " . $conn->error);
    }
    $stmt_delete->close();
    
    echo json_encode(['success' => true, 'message' => 'Servicio eliminado correctamente']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>