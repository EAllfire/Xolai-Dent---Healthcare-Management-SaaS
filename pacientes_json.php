<?php
require_once("includes/db.php");
header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT id, CONCAT(nombre, ' ', apellido) as nombre FROM portal_pacientes ORDER BY nombre";
$result = $conn->query($sql);

$pacientes = [];
while ($row = $result->fetch_assoc()) {
    $pacientes[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre']
    ];
}

echo json_encode($pacientes);
// no closing PHP tag