<?php
require_once("../includes/db.php");
header('Content-Type: application/json; charset=utf-8');

// Include imagen column (may be NULL) so frontend can render thumbnails
$sql = "SELECT id, nombre, COALESCE(imagen, '') AS imagen FROM agenda_modalidades 
        ORDER BY 
        CASE 
            WHEN nombre LIKE '%Radiografía%' THEN 1
            WHEN nombre LIKE '%Resonancia%' THEN 2
            WHEN nombre LIKE '%Tomografía%' THEN 3
            WHEN nombre LIKE '%Mastografía%' THEN 4
            WHEN nombre LIKE '%Sonografía%' THEN 5
            WHEN nombre LIKE '%Laboratorios%' THEN 6
            ELSE 9
        END, nombre";
        
$result = $conn->query($sql);

$recursos = [];

while ($row = $result->fetch_assoc()) {
    // Asignar colores por categoría
    $color = '#1976d2'; // Por defecto
    if (strpos($row['nombre'], 'Laboratorios') !== false) {
        $color = '#388e3c'; // Verde para laboratorios
    } elseif (strpos($row['nombre'], 'Radiografía') !== false) {
        $color = '#1976d2'; // Azul para radiografía
    } elseif (strpos($row['nombre'], 'Resonancia') !== false) {
        $color = '#7b1fa2'; // Púrpura para resonancia
    } elseif (strpos($row['nombre'], 'Tomografía') !== false) {
        $color = '#5d4037'; // Marrón para tomografía
    } elseif (strpos($row['nombre'], 'Mastografía') !== false) {
        $color = '#e91e63'; // Rosa para mastografía
    } elseif (strpos($row['nombre'], 'Sonografía') !== false) {
        $color = '#00796b'; // Teal para sonografía
    }

    $img = '';
    if (isset($row['imagen'])) $img = $row['imagen'];
    // normalize image path and prefix base path so it works when app is in a subdirectory
    if ($img && strpos($img, '://') === false) {
        // Determine application base path. If this script is inside /citas, prefer the parent directory
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if (basename($scriptDir) === 'citas') {
            $appBase = rtrim(dirname($scriptDir), '/');
        } else {
            $appBase = $scriptDir;
        }
        if ($appBase === '' || $appBase === '/') {
            $img = '/' . ltrim($img, '/');
        } else {
            $img = $appBase . '/' . ltrim($img, '/');
        }
    }
    $recursos[] = [
        'id' => trim($row['id']),
        'title' => trim($row['nombre']),
        'eventColor' => $color,
        'imagen' => $img
    ];
}

echo json_encode($recursos);
// no closing PHP tag to avoid accidental trailing whitespace