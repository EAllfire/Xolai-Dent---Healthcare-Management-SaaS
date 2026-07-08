<?php

require_once("../../includes/db.php");
header('Content-Type: application/json');

// Consulta simplificada para obtener modalidades
$sql = "SELECT id, nombre as title FROM agenda_modalidades 
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

$resources = [];
while ($row = $result->fetch_assoc()) {
  // Asignar colores por categoría
  $color = '#1976d2'; // Por defecto
  if (strpos($row['title'], 'Laboratorios') !== false) {
      $color = '#388e3c'; // Verde para laboratorios
  } elseif (strpos($row['title'], 'Radiografía') !== false) {
      $color = '#1976d2'; // Azul para radiografía
  } elseif (strpos($row['title'], 'Resonancia') !== false) {
      $color = '#7b1fa2'; // Púrpura para resonancia
  } elseif (strpos($row['title'], 'Tomografía') !== false) {
      $color = '#5d4037'; // Marrón para tomografía
  } elseif (strpos($row['title'], 'Mastografía') !== false) {
      $color = '#e91e63'; // Rosa para mastografía
  } elseif (strpos($row['title'], 'Sonografía') !== false) {
      $color = '#00796b'; // Teal para sonografía
  }

  $resources[] = [
    'id' => $row['id'],
    'title' => $row['title'],
    'eventColor' => $color
  ];
}

echo json_encode($resources);
?>