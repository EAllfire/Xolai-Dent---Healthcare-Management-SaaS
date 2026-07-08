<?php
session_start();
require_once("includes/db.php");
require_once("includes/auth.php");
header('Content-Type: application/json; charset=utf-8');

// Use 'alergias' from the database and map it to 'diagnostico' for the frontend
// Obtener el ID del usuario de la sesión.

$searchTerm = isset($_GET['term']) ? $_GET['term'] : '';
$allowed = obtenerIdsPermitidos();
$params = [];
$types = "";

$whereSQL = "1=1"; // Condición base verdadera para facilitar concatenación

if ($allowed === null) {
    // no restriction
} elseif (is_array($allowed) && in_array('PARENT_ONLY', $allowed)) {
    $parent = $_SESSION['id_padre'] ?? null;
    if ($parent) {
        $whereSQL .= " AND p.usuario_id = ?";
        $params[] = $parent; $types .= "i";
    } else {
        echo json_encode([]); exit;
    }
} elseif (is_array($allowed) && in_array('SELF_AND_CHILDREN', $allowed)) {
    $self = $_SESSION['usuario_id'] ?? 0;
    $whereSQL .= " AND (p.usuario_id = ? OR p.usuario_id IN (SELECT id FROM agenda_usuarios WHERE id_padre = ?))";
    $params[] = $self; $params[] = $self; $types .= "ii";
} elseif (is_array($allowed) && count($allowed) > 0) {
    $ids = implode(',', array_map('intval', $allowed));
    $whereSQL .= " AND p.usuario_id IN ($ids)";
}

$sql = "SELECT 
            p.id, p.nombre, p.apellido_paterno, p.apellido_materno, p.apellido, p.telefono, p.correo, p.origen, 
            p.alergias, p.comentarios, p.fecha_nacimiento, p.tipo_id, p.tel_emergencia, p.rfc, p.direccion, p.motivo_consulta, p.medicamentos,
            tp.nombre as tipo_nombre,
            u.nombre as medico_nombre
        FROM portal_pacientes p
        LEFT JOIN agenda_tipos_paciente tp ON p.tipo_id = tp.id
        LEFT JOIN agenda_usuarios u ON p.usuario_id = u.id
        WHERE $whereSQL";

if (!empty($searchTerm)) {
    $sql .= " AND LOWER(CONCAT(p.nombre, ' ', p.apellido_paterno, ' ', p.apellido_materno)) LIKE ?";
    $searchTermParam = "%" . strtolower($searchTerm) . "%";
    $params[] = $searchTermParam;
    $types .= "s";
}

$sql .= " ORDER BY p.nombre, p.apellido_paterno, p.apellido_materno LIMIT 100"; // Limit results for performance
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Check for query errors
if (!$result) {
    http_response_code(500);
    // Add more debug info to the error
    echo json_encode(['error' => 'Database query failed', 'sql_error' => $conn->error, 'sql_query' => $sql]);
    exit;
}

$pacientes = [];
while ($row = $result->fetch_assoc()) {
    // Fallback: si no hay apellido_paterno pero hay apellido (viejo), usar el viejo.
    $ap_p = $row['apellido_paterno'];
    $ap_m = $row['apellido_materno'];
    if (empty($ap_p) && !empty($row['apellido'])) { $ap_p = $row['apellido']; }

    $pacientes[] = [
        'id' => $row['id'],
        'nombre' => trim($row['nombre'] . ' ' . $ap_p . ' ' . $ap_m),
        'nombre_solo' => $row['nombre'],
        'apellido_paterno' => $ap_p,
        'apellido_materno' => $ap_m,
        'telefono' => $row['telefono'],
        'correo' => $row['correo'],
        'tipo' => $row['tipo_nombre'], // Use the joined type name
        'tipo_id' => $row['tipo_id'], // Pass tipo_id to the frontend
        'origen' => $row['origen'],
        'diagnostico' => $row['alergias'], // Map 'alergias' to 'diagnostico'
        'comentarios' => $row['comentarios'],
        'fecha_nacimiento' => $row['fecha_nacimiento'],
        'medico_nombre' => $row['medico_nombre'],
        'tel_emergencia' => $row['tel_emergencia'],
        'rfc' => $row['rfc'],
        'direccion' => $row['direccion'],
        'motivo_consulta' => $row['motivo_consulta'],
        'medicamentos' => $row['medicamentos'],
    ];
}

echo json_encode($pacientes);
// no closing PHP tag