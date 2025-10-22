<?php
require_once("includes/db.php");
header('Content-Type: application/json');

$modalidad_id = $_GET['modalidad_id'] ?? '';

if (!$modalidad_id || !is_numeric($modalidad_id)) {
    echo json_encode([]);
    exit;
}

// Consulta directa sin prepared statements
$modalidad_id = intval($modalidad_id);
$sql = "SELECT id, nombre, duracion AS duracion_minutos FROM portal_servicios WHERE modalidad = $modalidad_id ORDER BY nombre";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['error' => 'Error en consulta: ' . $conn->error]);
    exit;
}

$servicios = [];
while ($row = $result->fetch_assoc()) {
    $servicios[] = [
        'id' => intval($row['id']),
        'nombre' => $row['nombre'],
        'duracion_minutos' => intval($row['duracion_minutos'] ?? 30)
    ];
}

echo json_encode($servicios);
?>