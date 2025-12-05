<?php
require_once '../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

$searchTerm = isset($_GET['term']) ? $_GET['term'] : '';

$sql = "SELECT p.id, p.nombre, p.apellido, p.telefono, p.correo, p.origen, p.alergias, p.comentarios, p.fecha_nacimiento, p.tipo_id, atp.nombre as tipo_paciente_nombre
        FROM portal_pacientes p
        LEFT JOIN agenda_tipos_paciente atp ON p.tipo_id = atp.id";

if (!empty($searchTerm)) {
    $searchTerm = strtolower($conn->real_escape_string($searchTerm));
    $sql .= " WHERE LOWER(CONCAT(p.nombre, ' ', p.apellido)) LIKE '%" . $searchTerm . "%' ";
}

$sql .= " ORDER BY p.nombre, p.apellido LIMIT 25";
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
        'nombre' => trim($row['nombre'] . ' ' . ($row['apellido'] ?? '')),
        'nombre_solo' => $row['nombre'],
        'apellido' => $row['apellido'] ?: '',
        'telefono' => $row['telefono'] ?: '',
        'correo' => $row['correo'] ?: '',
        'tipo' => $row['tipo_paciente_nombre'] ?: 'No especificado',
        'estado_id' => $row['tipo_id'], // Corregido: usar tipo_id y mapearlo a estado_id para el frontend
        'origen' => $row['origen'] ?: 'externo',
        'diagnostico' => $row['alergias'] ?: '',
        'comentarios' => $row['comentarios'] ?: '',
        'fecha_nacimiento' => $row['fecha_nacimiento'] ?: '',
    ];
}
echo json_encode($pacientes);
?>