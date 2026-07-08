<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

$user_tipo = $_SESSION['usuario_tipo'] ?? '';
$puede_gestionar_bloqueos = in_array($user_tipo, ['superadmin', 'admin', 'medico', 'dentista']);
if (!$puede_gestionar_bloqueos) {
    echo json_encode(['success' => false, 'error' => 'No tiene permisos para eliminar bloqueos.']);
    exit;
}

// Leer JSON con array de IDs
$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'error' => 'No se seleccionaron bloqueos válidos.']);
    exit;
}

// Convertir todo a enteros por seguridad
$ids = array_map('intval', $ids);
$ids_str = implode(',', $ids);

// Eliminar solo si son bloqueos (estado 9 o tipo bloqueo) para seguridad extra
$sql = "DELETE FROM agenda_citas WHERE id IN ($ids_str) AND (estado_id = 9 OR tipo = 'bloqueo')";

if ($conn->query($sql)) {
    echo json_encode(['success' => true, 'deleted_count' => $conn->affected_rows]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $conn->error]);
}
?>