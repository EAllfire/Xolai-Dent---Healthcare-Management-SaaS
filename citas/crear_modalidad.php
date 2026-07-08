<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
if (!puedeRealizar('gestionar_modalidades')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Acceso denegado']); exit; }
$data = json_decode(file_get_contents('php://input'),true);
$nombre = trim($data['nombre'] ?? '');
// Si viene un usuario_id del select, lo usamos (para asociar a un médico específico), sino usamos al dueño de la clínica
$id_propietario = $_SESSION['id_padre'] ?? $_SESSION['usuario_id'];
$usuario_id_asignado = !empty($data['usuario_id']) ? (int)$data['usuario_id'] : $id_propietario;

if (!$nombre) { echo json_encode(['success'=>false,'error'=>'Nombre requerido']); exit; }
$stmt = $conn->prepare("INSERT INTO agenda_modalidades (nombre, usuario_id) VALUES (?, ?)");
$stmt->bind_param('si',$nombre, $usuario_id_asignado);
if ($stmt->execute()) { echo json_encode(['success'=>true,'id'=>$conn->insert_id]); } else { echo json_encode(['success'=>false,'error'=>$stmt->error]); }
$stmt->close();
?>