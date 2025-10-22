<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
header('Content-Type: application/json');
if (!puedeRealizar('gestionar_usuarios')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Acceso denegado']); exit; }
$data = json_decode(file_get_contents('php://input'),true);
$nombre = trim($data['nombre'] ?? '');
if (!$nombre) { echo json_encode(['success'=>false,'error'=>'Nombre requerido']); exit; }
$stmt = $conn->prepare("INSERT INTO agenda_modalidades (nombre, descripcion) VALUES (?, '')");
$stmt->bind_param('s',$nombre);
if ($stmt->execute()) { echo json_encode(['success'=>true,'id'=>$conn->insert_id]); } else { echo json_encode(['success'=>false,'error'=>$stmt->error]); }
$stmt->close();
?>