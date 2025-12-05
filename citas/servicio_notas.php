<?php
require_once '../includes/db.php';
header('Content-Type: application/json');

$servicio_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($servicio_id <= 0) {
    echo json_encode(['notas' => '']);
    exit;
}

$stmt = $conn->prepare("SELECT notas FROM portal_servicios WHERE id = ?");
$stmt->bind_param("i", $servicio_id);
$stmt->execute();
$stmt->bind_result($notas);

if ($stmt->fetch()) {
    echo json_encode(['notas' => $notas ?? '']);
} else {
    echo json_encode(['notas' => '']);
}

$stmt->close();
?>