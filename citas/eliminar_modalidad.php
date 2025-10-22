<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
header('Content-Type: application/json');
if (!puedeRealizar('gestionar_usuarios')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Acceso denegado']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }

// Optionally: check foreign key references (citas table). We'll attempt to delete only if there are no citas for this modalidad.
$check = $conn->prepare("SELECT COUNT(*) as total FROM citas WHERE modalidad_id = ?");
if ($check) {
    $check->bind_param('i', $id);
    $check->execute();
    $check->bind_result($total);
    $check->fetch();
    $check->close();
    if ($total > 0) {
        $msg = 'No se puede eliminar: existen citas asociadas (count=' . intval($total) . ')';
        echo json_encode(['success'=>false,'error'=>$msg]);
        exit;
    }
}

$stmt = $conn->prepare("DELETE FROM agenda_modalidades WHERE id = ?");
if (!$stmt) { echo json_encode(['success'=>false,'error'=>'Error prepare: '.$conn->error]); exit; }
$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'error'=>$stmt->error]);
}
$stmt->close();

?>
