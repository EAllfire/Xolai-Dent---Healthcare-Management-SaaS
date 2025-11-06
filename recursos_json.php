<?php
require_once("includes/db.php");

$sql = "SELECT id, nombre FROM agenda_modalidades 
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



    } elseif (strpos($row['nombre'], 'Tomografía') !== false) {
        $color = '#5d4037'; // Marrón para tomografía
    } elseif (strpos($row['nombre'], 'Mastografía') !== false) {
        $color = '#e91e63'; // Rosa para mastografía
    } elseif (strpos($row['nombre'], 'Sonografía') !== false) {
        $color = '#00796b'; // Teal para sonografía
    }

    $recursos[] = [
        'id' => $row['id'],
        'title' => $row['nombre'],
        'eventColor' => $color
    ];
}

echo json_encode($recursos);
?>