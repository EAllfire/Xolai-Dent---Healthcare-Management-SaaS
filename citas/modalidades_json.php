<?php
session_start();
require_once '../includes/db.php';

try {
    // Verificar si la columna 'imagen' existe
    $colCheck = $conn->query("SHOW COLUMNS FROM agenda_modalidades LIKE 'imagen'");
    $hasImagen = ($colCheck && $colCheck->num_rows > 0);

    if ($hasImagen) {
        $sql = "SELECT id, nombre, COALESCE(imagen, '') as imagen FROM agenda_modalidades ORDER BY nombre ASC";
    } else {
        $sql = "SELECT id, nombre, '' as imagen FROM agenda_modalidades ORDER BY nombre ASC";
    }

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }

    $modalidades = [];
    while ($row = $result->fetch_assoc()) {
        $img = $row['imagen'] ?? '';
        // Normalizar ruta: si no es URL completa, devolver ruta relativa desde la raíz
        if ($img && strpos($img, '://') === false) {
            $img = '/' . ltrim($img, '/');
        }
        $modalidades[] = [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre'],
            'imagen' => $img
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($modalidades);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('modalidades_json error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>