<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!puedeRealizar('gestionar_servicios')) {
    http_response_code(403);
    echo json_encode(['error' => 'No tiene permisos para realizar esta acción.']);
    exit;
}

$nombre = $_POST['nombre'] ?? '';
$descripcion = $_POST['descripcion'] ?? '';
$precio = $_POST['precio'] ?? 0;
$servicios = $_POST['servicios'] ?? [];

if (empty($nombre) || !is_numeric($precio)) {
    http_response_code(400);
    echo json_encode(['error' => 'El nombre y el precio son obligatorios.']);
    exit;
}

// Use the mysqli connection $conn from db.php
$conn->begin_transaction();

try {
    // 1. Insertar el paquete
    $sql_paquete = "INSERT INTO agenda_paquetes (nombre, descripcion, precio) VALUES (?, ?, ?)";
    $stmt_paquete = $conn->prepare($sql_paquete);
    if (!$stmt_paquete) {
        throw new Exception("Error al preparar la consulta del paquete: " . $conn->error);
    }
    $stmt_paquete->bind_param("ssd", $nombre, $descripcion, $precio);
    $stmt_paquete->execute();
    
    $paquete_id = $conn->insert_id;
    $stmt_paquete->close();

    // 2. Asociar los servicios
    if (!empty($servicios) && is_array($servicios)) {
        $sql_servicios = "INSERT INTO agenda_paquete_servicios (paquete_id, servicio_id) VALUES (?, ?)";
        $stmt_servicios = $conn->prepare($sql_servicios);
        if (!$stmt_servicios) {
            throw new Exception("Error al preparar la consulta de servicios: " . $conn->error);
        }
        foreach ($servicios as $servicio_id) {
            $stmt_servicios->bind_param("ii", $paquete_id, $servicio_id);
            $stmt_servicios->execute();
        }
        $stmt_servicios->close();
    }

    $conn->commit();

    echo json_encode(['success' => true, 'id' => $paquete_id]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    error_log("Error en guardar_paquete.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error al guardar el paquete: ' . $e->getMessage()]);
}

?>