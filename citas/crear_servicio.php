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
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = (float)($_POST['precio'] ?? 0);
    $especialidad_id = (int)($_POST['especialidad_id'] ?? 0);
    $duracion_minutos = (int)($_POST['duracion_minutos'] ?? 30);
    
    $usuario_id_real = $_SESSION['usuario_id'] ?? 0;
    $id_propietario = $_SESSION['id_padre'] ?? $usuario_id_real;

    // Validar datos requeridos
    if (empty($nombre)) {
        throw new Exception("El nombre del servicio es requerido");
    }
    
    if ($precio < 0) {
        throw new Exception("El precio no puede ser negativo");
    }
    
    if ($duracion_minutos < 5 || $duracion_minutos > 180) {
        throw new Exception("La duración debe estar entre 5 y 180 minutos");
    }
    
    // Insertar servicio
    $sql = "INSERT INTO portal_servicios (nombre, descripcion, precio, especialidad_id, modalidad_id, duracion_minutos, usuario_id) VALUES (?, ?, ?, ?, NULL, ?, ?)";
    $types = "ssdiis";
    $params = [$nombre, $descripcion, $precio, $especialidad_id > 0 ? $especialidad_id : null, $duracion_minutos, $id_propietario];
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al crear el servicio: " . $conn->error);
    }
    
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Servicio creado correctamente']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>