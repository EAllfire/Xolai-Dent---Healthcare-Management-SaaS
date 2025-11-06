<?php
require_once("includes/db.php");
header('Content-Type: application/json; charset=utf-8');

// Use 'alergias' from the database and map it to 'diagnostico' for the frontend
$searchTerm = isset($_GET['term']) ? $_GET['term'] : '';

$sql = "SELECT id, nombre, apellido, telefono, correo, tipo, origen, alergias, comentarios FROM portal_pacientes";

if (!empty($searchTerm)) {
    $searchTerm = strtolower($conn->real_escape_string($searchTerm));
    // Search in both nombre and apellido, case-insensitively
    $sql .= " WHERE LOWER(nombre) LIKE '%" . $searchTerm . "%' OR LOWER(apellido) LIKE '%" . $searchTerm . "%'";
}

$sql .= " ORDER BY nombre, apellido LIMIT 25"; // Limit results for performance
$result = $conn->query($sql);

// Check for query errors
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
        'apellido' => $row['apellido'],
        'telefono' => $row['telefono'],
        'correo' => $row['correo'],
        'tipo' => $row['tipo'],
        'origen' => $row['origen'],
        'diagnostico' => $row['alergias'], // Map 'alergias' to 'diagnostico'
        'comentarios' => $row['comentarios'],
    ];
}

echo json_encode($pacientes);
// no closing PHP tag