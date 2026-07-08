<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
if (!puedeRealizar('gestionar_modalidades')) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Acceso denegado']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
$usuario_id_real = $_SESSION['usuario_id'] ?? 0;
$id_propietario = $_SESSION['id_padre'] ?? $usuario_id_real;
if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido']); exit; }

// Optionally: check foreign key references (citas table). We'll attempt to delete only if there are no citas for this modalidad.
$check = $conn->prepare("SELECT COUNT(*) as total FROM agenda_citas WHERE modalidad_id = ?");
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

$stmt = $conn->prepare("DELETE FROM agenda_modalidades WHERE id = ? AND usuario_id = ?");
if (!$stmt) { echo json_encode(['success'=>false,'error'=>'Error prepare: '.$conn->error]); exit; }
$stmt->bind_param('ii', $id, $id_propietario);
if ($stmt->execute()) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'error'=>$stmt->error]);
}
$stmt->close();

?>
