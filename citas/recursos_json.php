<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
header('Content-Type: application/json; charset=utf-8');

// Limpiar buffer para evitar que errores PHP rompan el JSON
error_reporting(0); 

// Obtener el ID del usuario de la sesión.
$usuario_id_real = $_SESSION['usuario_id'] ?? 0;
$id_propietario = $_SESSION['id_padre'] ?? $usuario_id_real;
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';

if (!$usuario_id_real) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id, nombre, usuario_id, imagen 
        FROM agenda_modalidades 
        WHERE usuario_id = ? OR usuario_id IN (SELECT id FROM agenda_usuarios WHERE id_padre = ?)
        ORDER BY nombre ASC";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id_propietario, $id_propietario);
$stmt->execute();
$result = $stmt->get_result();

$recursos = [];
while ($row = $result->fetch_assoc()) {
    $color = '#1275a0'; // Color base para consultorios
    if (strpos($row['nombre'], 'Laboratorio') !== false) $color = '#388e3c';
    elseif (strpos($row['nombre'], 'Rayos') !== false) $color = '#1d4ed8';
    
    $recursos[] = [
        'id' => $row['id'],
        'title' => $row['nombre'],
        'eventColor' => $color,
        'usuario_id' => $row['usuario_id'],
        'imagen' => $row['imagen'] ? '/' . ltrim($row['imagen'], '/') : ''
    ];
}

echo json_encode($recursos);
?>