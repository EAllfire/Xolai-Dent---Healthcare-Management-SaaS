<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

// Obtener información del usuario desde la sesión para filtrar
// Use permission helper to determine allowed owner scope
$usuario_id_real = $_SESSION['usuario_id'] ?? null;
$searchTerm = isset($_GET['term']) ? $_GET['term'] : '';

$allowed = obtenerIdsPermitidos();

$sql = "SELECT p.id, p.nombre, p.apellido_paterno, p.apellido_materno, p.telefono, p.correo, p.origen, p.alergias, p.comentarios, p.fecha_nacimiento, p.tipo_id, p.recomendado_por_id, atp.nombre as tipo_paciente_nombre, u_rec.nombre as recomendado_por_nombre, p.motivo_consulta
        FROM portal_pacientes p
        LEFT JOIN agenda_tipos_paciente atp ON p.tipo_id = atp.id
        LEFT JOIN agenda_usuarios u_rec ON p.recomendado_por_id = u_rec.id";

$whereClauses = [];
$params = [];
$types = '';

if ($allowed === null) {
    // no extra filter
} elseif (is_array($allowed) && in_array('PARENT_ONLY', $allowed)) {
    $parent = $_SESSION['id_padre'] ?? null;
    if ($parent) {
        $whereClauses[] = "p.usuario_id = ?";
        $params[] = $parent; $types .= 'i';
    } else {
        echo json_encode([]); exit;
    }
} elseif (is_array($allowed) && in_array('SELF_AND_CHILDREN', $allowed)) {
    // show patients of self and children
    $self = $_SESSION['usuario_id'] ?? 0;
    $whereClauses[] = "(p.usuario_id = ? OR p.usuario_id IN (SELECT id FROM agenda_usuarios WHERE id_padre = ?))";
    $params[] = $self; $params[] = $self; $types .= 'ii';
} elseif (is_array($allowed) && count($allowed) > 0) {
    // explicit list of user IDs
    $ids = implode(',', array_map('intval', $allowed));
    $whereClauses[] = "p.usuario_id IN ($ids)";
}

if (!empty($searchTerm)) {
    $whereClauses[] = "LOWER(CONCAT(p.nombre, ' ', p.apellido_paterno, ' ', p.apellido_materno)) LIKE ?";
    $params[] = "%" . strtolower($searchTerm) . "%";
    $types .= 's';
}

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(' AND ', $whereClauses);
}

$sql .= " ORDER BY p.nombre, p.apellido LIMIT 100";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al preparar la consulta: ' . $conn->error]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$pacientes = [];
while ($row = $result->fetch_assoc()) {
    $paciente = $row;
    $paciente['nombre'] = trim($row['nombre'] . ' ' . ($row['apellido_paterno'] ?? '') . ' ' . ($row['apellido_materno'] ?? ''));
    $paciente['nombre_solo'] = $row['nombre'];
    $paciente['apellido_paterno'] = $row['apellido_paterno'] ?? '';
    $paciente['apellido_materno'] = $row['apellido_materno'] ?? '';
    // Mapeo correcto: el 'diagnostico' del frontend ahora lee del motivo de consulta real
    $paciente['diagnostico'] = $row['motivo_consulta'] ?? '';
    $paciente['alergias'] = $row['alergias'] ?? '';
    $pacientes[] = $paciente;
}

echo json_encode($pacientes);
?>