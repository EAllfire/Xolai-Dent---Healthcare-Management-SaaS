<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
header('Content-Type: application/json; charset=utf-8');

$usuario_id_real = $_SESSION['usuario_id'] ?? 0;
$usuario_tipo = $_SESSION['usuario_tipo'] ?? '';

// Identificar al propietario de la red (Padre). Si el usuario actual tiene padre, el dueño es el padre.
$id_padre_session = isset($_SESSION['id_padre']) ? (int)$_SESSION['id_padre'] : 0;
$id_propietario = ($id_padre_session > 0) ? $id_padre_session : (int)$usuario_id_real;

if (!$usuario_id_real) {
    echo json_encode([]);
    exit;
}

$fecha = isset($_GET['fecha']) ? trim($_GET['fecha']) : '';
$hora_inicio = isset($_GET['hora_inicio']) ? trim($_GET['hora_inicio']) : '';
$hora_fin = isset($_GET['hora_fin']) ? trim($_GET['hora_fin']) : '';
$useTimeFilter = $fecha !== '' && $hora_inicio !== '' && $hora_fin !== '';

$is_superadmin = $usuario_tipo === 'superadmin';
$is_root_admin = $usuario_tipo === 'admin' && $id_padre_session === 0;

// Si es superadmin o admin raíz, ve a todos los profesionales del sistema
if ($is_superadmin || $is_root_admin) {
    $sql = "SELECT DISTINCT u.id, u.nombre FROM agenda_usuarios u";
    if ($useTimeFilter) {
        $sql .= " LEFT JOIN agenda_citas b ON u.id = b.profesional_id 
                AND (b.estado_id = 9 OR b.tipo = 'bloqueo')
                AND b.fecha = ?
                AND b.hora_inicio < ? AND b.hora_fin > ?";
    }
    $sql .= " WHERE u.tipo IN ('dentista', 'medico', 'dentista_externo') 
                AND u.tipo NOT IN ('recepcion', 'caja', 'admin')";
    if ($useTimeFilter) {
        $sql .= " AND b.id IS NULL";
    }
    $sql .= " ORDER BY u.nombre ASC";
    $stmt = $conn->prepare($sql);
    if ($useTimeFilter) {
        $stmt->bind_param('sss', $fecha, $hora_fin, $hora_inicio);
    }
} else {
    // Si es administrado derivado, dentista o médico, ve a su equipo (dueño + hijos)
    $sql = "SELECT DISTINCT u.id, u.nombre FROM agenda_usuarios u";
    if ($useTimeFilter) {
        $sql .= " LEFT JOIN agenda_citas b ON u.id = b.profesional_id 
                AND (b.estado_id = 9 OR b.tipo = 'bloqueo')
                AND b.fecha = ?
                AND b.hora_inicio < ? AND b.hora_fin > ?";
    }
    $sql .= " WHERE (u.id = ? OR u.id_padre = ?) 
                AND u.tipo IN ('dentista', 'medico', 'dentista_externo') 
                AND u.tipo NOT IN ('recepcion', 'caja', 'admin')";
    if ($useTimeFilter) {
        $sql .= " AND b.id IS NULL";
    }
    $sql .= " ORDER BY u.nombre ASC";

    $stmt = $conn->prepare($sql);
    if ($useTimeFilter) {
        $stmt->bind_param('sssii', $fecha, $hora_fin, $hora_inicio, $id_propietario, $id_propietario);
    } else {
        $stmt->bind_param('ii', $id_propietario, $id_propietario);
    }
}

$stmt->execute();
$result = $stmt->get_result();

$doctores = [];
while ($row = $result->fetch_assoc()) {
    $doctores[] = $row;
}
echo json_encode($doctores);