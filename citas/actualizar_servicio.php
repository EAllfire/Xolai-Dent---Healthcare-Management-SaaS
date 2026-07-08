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
    // Obtener datos del formulario
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = (float)($_POST['precio'] ?? 0);
    $especialidad_id = (int)($_POST['especialidad_id'] ?? 0);
    $duracion_minutos = (int)($_POST['duracion_minutos'] ?? 30);
    $usuario_id = $_SESSION['usuario_id'] ?? 0;
    
    // Validar datos requeridos
    if ($id <= 0) {
        throw new Exception("ID de servicio no válido");
    }
    
    if (empty($nombre)) {
        throw new Exception("El nombre del servicio es requerido");
    }
    
    if ($precio < 0) {
        throw new Exception("El precio no puede ser negativo");
    }
    
    if ($duracion_minutos < 5 || $duracion_minutos > 180) {
        throw new Exception("La duración debe estar entre 5 y 180 minutos");
    }
    
    // Verificar que el servicio existe y pertenece al usuario
     $stmt_check = $conn->prepare("SELECT id FROM portal_servicios WHERE id = ? AND usuario_id = ?");
    $stmt_check->bind_param("ii", $id, $usuario_id);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    if ($stmt_check->num_rows === 0) {
        throw new Exception("El servicio no existe o no tiene permiso para editarlo");
    }
    $stmt_check->close();
    
    $sql = "UPDATE portal_servicios SET             
                nombre = ?, 
                descripcion = ?, 
                precio = ?, 
                especialidad_id = ?,
                modalidad_id = NULL,
                duracion_minutos = ? 
            WHERE id = ? AND usuario_id = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    $especialidad_value = $especialidad_id > 0 ? $especialidad_id : null;
    $stmt->bind_param("ssdiiii", $nombre, $descripcion, $precio, $especialidad_value, $duracion_minutos, $id, $usuario_id);
    
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