<?php
session_start();
require_once("includes/db.php");
require_once("includes/auth.php");
header('Content-Type: application/json');

$allowed_ids = obtenerIdsPermitidos();
$where_sql = "1=1";
if ($allowed_ids !== null) {
    if (in_array('PARENT_ONLY', $allowed_ids)) {
        $parent_id = (int)($_SESSION['id_padre'] ?? 0);
        $where_sql = "(usuario_id = $parent_id OR usuario_id IN (SELECT id FROM agenda_usuarios WHERE id_padre = $parent_id))";
    } elseif (in_array('SELF_AND_CHILDREN', $allowed_ids)) {
        $self_id = (int)$_SESSION['usuario_id'];
        $where_sql = "(usuario_id = $self_id OR usuario_id IN (SELECT id FROM agenda_usuarios WHERE id_padre = $self_id))";
    } else {
        $ids_str = implode(',', array_map('intval', $allowed_ids));
        $where_sql = "usuario_id IN ($ids_str)";
    }
}

$sql = "SELECT am.id, COALESCE(au.nombre, am.nombre) as nombre, am.usuario_id, au.especialidad_id FROM agenda_modalidades am LEFT JOIN agenda_usuarios au ON am.usuario_id = au.id
        WHERE $where_sql
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
        
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

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
        'eventColor' => $color,
        'usuario_id' => $row['usuario_id'], // Add user_id
        'especialidad_id' => $row['especialidad_id'] // Add specialty_id
    ];
}

echo json_encode($recursos);
?>