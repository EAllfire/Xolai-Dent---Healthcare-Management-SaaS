<?php
require_once("includes/db.php");
header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT * FROM agenda_estado_cita ORDER BY id";
$result = $conn->query($sql);

$estados = [];
while ($row = $result->fetch_assoc()) {
    $estados[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre']
    ];
}

echo json_encode($estados);
// no closing PHP tag