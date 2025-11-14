<?php
header('Content-Type: application/json');
require_once 'includes/db.php';

// Verificar que solo los admins puedan acceder
session_start();
if (!isset($_SESSION['usuario_tipo']) || !in_array($_SESSION['usuario_tipo'], ['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso no autorizado.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos.']);
    exit;
}

$ids = $data['ids'] ?? null;
$descripcion = $data['descripcion'] ?? '';

if (!is_array($ids) || empty($ids)) {
    echo json_encode(['success' => false, 'error' => 'No se proporcionaron IDs de servicios.']);
    exit;
}

// Validar que todos los IDs sean numéricos para seguridad
$validated_ids = array_filter($ids, 'is_numeric');
if (count($validated_ids) !== count($ids)) {
    echo json_encode(['success' => false, 'error' => 'Se detectaron IDs no válidos.']);
    exit;
}

// Construir la consulta con placeholders para los IDs
$placeholders = implode(',', array_fill(0, count($validated_ids), '?'));
$types = str_repeat('i', count($validated_ids));
$sql = "UPDATE portal_servicios SET descripcion = ? WHERE id IN ($placeholders)";

$stmt = $conn->prepare($sql);

// El primer tipo es 's' para la descripción, seguido de 'i' para cada ID
$stmt->bind_param('s' . $types, $descripcion, ...$validated_ids);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'affected_rows' => $stmt->affected_rows]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al actualizar los servicios: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>