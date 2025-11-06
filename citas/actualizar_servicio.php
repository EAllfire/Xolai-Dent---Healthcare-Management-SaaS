<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Verificar que el usuario sea admin
if (!puedeRealizar('gestionar_usuarios')) {
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
    // Obtener datos del formulario
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = (float)($_POST['precio'] ?? 0);
    $modalidad_id = (int)($_POST['modalidad_id'] ?? 0);
    $duracion_minutos = (int)($_POST['duracion_minutos'] ?? 30);
    
    // Validar datos requeridos
    if ($id <= 0) {
        throw new Exception("ID de servicio no válido");
    }
    
    if (empty($nombre)) {
        throw new Exception("El nombre del servicio es requerido");
    }
    
    if ($precio <= 0) {
        throw new Exception("El precio debe ser mayor a 0");
    }
    
    if ($duracion_minutos < 5 || $duracion_minutos > 180) {
        throw new Exception("La duración debe estar entre 5 y 180 minutos");
    }
    
    // Verificar que el servicio existe (compatible con sistemas sin mysqlnd)
    $stmt_check = $conn->prepare("SELECT id FROM portal_servicios WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $stmt_check->store_result(); // Almacenar el resultado
    
    if ($stmt_check->num_rows === 0) {
        throw new Exception("El servicio no existe");
    }
    $stmt_check->close();
    
    // Actualizar servicio - MAPEO: modalidad_id -> modalidad, duracion_minutos -> duracion
    $sql = "UPDATE portal_servicios SET 
                nombre = ?, 
                descripcion = ?, 
                precio = ?, 
                modalidad = ?,
                duracion = ?
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    $modalidad_value = $modalidad_id > 0 ? $modalidad_id : null;
    $stmt->bind_param("ssdiii", $nombre, $descripcion, $precio, $modalidad_value, $duracion_minutos, $id);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar el servicio: " . $conn->error);
    }
    
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Servicio actualizado correctamente']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>