<?php
file_put_contents('debug_entry_point.log', 'Script ejecutado en: ' . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
header('Content-Type: application/json');
if (!puedeRealizar('gestionar_usuarios')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Acceso denegado']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
$nombre = trim($data['nombre'] ?? '');
if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }
if ($nombre === '') { echo json_encode(['success'=>false,'error'=>'Nombre requerido']); exit; }

$stmt = $conn->prepare("UPDATE agenda_modalidades SET nombre = ? WHERE id = ?");
if (!$stmt) { echo json_encode(['success'=>false,'error'=>'Error prepare: '.$conn->error]); exit; }
$stmt->bind_param('si', $nombre, $id);
if ($stmt->execute()) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'error'=>$stmt->error]);
}
$stmt->close();

?>
= ?");
if (!$stmt) { echo json_encode(['success'=>false,'error'=>'Error prepare: '.$conn->error]); exit; }
$stmt->bind_param('si', $nombre, $id);
if ($stmt->execute()) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'error'=>$stmt->error]);
}
$stmt->close();

?>
