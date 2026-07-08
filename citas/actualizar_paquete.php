<?php
header('Content-Type: application/json');
session_start(); // <-- AÑADIDO: Iniciar sesión para verificar permisos
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

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

// The frontend sends FormData, so we use $_POST
$id = $_POST['id'] ?? null;
$nombre = $_POST['nombre'] ?? null;
$descripcion = $_POST['descripcion'] ?? null;
$precio = $_POST['precio'] ?? null;
$servicios = $_POST['servicios'] ?? []; // Array de IDs de servicios

if (empty($id) || empty($nombre) || !isset($precio)) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan datos requeridos (id, nombre, precio).', 'post' => $_POST]);
    exit;
}

$conn->begin_transaction();

try {
    // 1. Actualizar agenda_paquetes
    $sql_update_paquete = "UPDATE agenda_paquetes SET nombre = ?, descripcion = ?, precio = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update_paquete);
    if(!$stmt_update) { throw new Exception("Prepare failed for package update: " . $conn->error); }
    $stmt_update->bind_param("ssdi", $nombre, $descripcion, $precio, $id);
    $stmt_update->execute();
    $stmt_update->close();

    // 2. Eliminar las asociaciones de servicios existentes
    $sql_delete_servicios = "DELETE FROM agenda_paquete_servicios WHERE paquete_id = ?";
    $stmt_delete = $conn->prepare($sql_delete_servicios);
    if(!$stmt_delete) { throw new Exception("Prepare failed for services deletion: " . $conn->error); }
    $stmt_delete->bind_param("i", $id);
    $stmt_delete->execute();
    $stmt_delete->close();

    // 3. Insertar las nuevas asociaciones de servicios
    if (!empty($servicios) && is_array($servicios)) {
        $sql_insert_servicios = "INSERT INTO agenda_paquete_servicios (paquete_id, servicio_id) VALUES (?, ?)";
        $stmt_insert = $conn->prepare($sql_insert_servicios);
        if(!$stmt_insert) { throw new Exception("Prepare failed for services insertion: " . $conn->error); }
        
        foreach ($servicios as $servicio_id) {
            if (!empty($servicio_id)) {
                $stmt_insert->bind_param("ii", $id, $servicio_id);
                $stmt_insert->execute();
            }
        }
        $stmt_insert->close();
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    error_log("Error en actualizar_paquete.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error en la base de datos al actualizar el paquete: ' . $e->getMessage()]);
}
?>
