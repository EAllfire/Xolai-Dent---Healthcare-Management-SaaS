<?php
require_once '../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT id, nombre, apellido, telefono, correo, tipo, origen, alergias, comentarios FROM portal_pacientes ORDER BY nombre, apellido";
$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed: ' . $conn->error]);
    exit;
}

$pacientes = [];
while ($row = $result->fetch_assoc()) {
    $pacientes[] = [
        'id' => $row['id'],
        'nombre' => trim($row['nombre'] . ' ' . $row['apellido']),
        'nombre_solo' => $row['nombre'],
        'apellido' => $row['apellido'] ?: '',
        'telefono' => $row['telefono'] ?: '',
        'correo' => $row['correo'] ?: '',
        'tipo' => $row['tipo'] ?: 'adulto',
        'origen' => $row['origen'] ?: 'externo',
        'diagnostico' => $row['alergias'] ?: '',
        'comentarios' => $row['comentarios'] ?: '',
    ];
}
echo json_encode($pacientes);
?>