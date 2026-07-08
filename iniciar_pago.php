<?php
session_start();
require_once('includes/db.php');
require_once('includes/GestorPagos.php');
require_once('includes/auth.php');

header('Content-Type: application/json');

$cita_id = $_POST['cita_id'] ?? null;
$proveedor = $_POST['proveedor'] ?? 'simulador';
$metodo_pago = $_POST['metodo_pago'] ?? 'tarjeta';

try {
    if (!$cita_id) {
        throw new Exception('ID de cita requerido');
    }

    // Verificar que la cita existe, su propietario y que necesita pago
    $stmt = $conn->prepare("SELECT id, estado_pago, usuario_id FROM agenda_citas WHERE id = ?");
    $stmt->bind_param("i", $cita_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Cita no encontrada');
    }

    $cita = $result->fetch_assoc();
    
    if ($cita['estado_pago'] === 'completado') {
        throw new Exception('Esta cita ya ha sido pagada');
    }

    // Validar owner-scope mediante helper
    $allowed = obtenerIdsPermitidos();
    if ($allowed !== null) {
        $c_owner = $cita['usuario_id'] ?? null;
        if (is_array($allowed) && in_array('PARENT_ONLY', $allowed)) {
            $parent = $_SESSION['id_padre'] ?? null;
            if (!$parent || $c_owner != $parent) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Acceso denegado']); exit; }
        } elseif (is_array($allowed) && in_array('SELF_AND_CHILDREN', $allowed)) {
            $self = $_SESSION['usuario_id'] ?? 0;
            if ($c_owner != $self) {
                $stmt_ch = $conn->prepare("SELECT COUNT(*) as cnt FROM agenda_usuarios WHERE id = ? AND id_padre = ?");
                $stmt_ch->bind_param('ii', $c_owner, $self);
                $stmt_ch->execute(); $res_ch = $stmt_ch->get_result(); $rch = $res_ch->fetch_assoc(); $stmt_ch->close();
                if ((int)$rch['cnt'] === 0) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Acceso denegado']); exit; }
            }
        } elseif (is_array($allowed) && count($allowed)>0) {
            if (!in_array((int)$c_owner, array_map('intval', $allowed))) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Acceso denegado']); exit; }
        }
    }

    // Crear pago usando el gestor
    $gestorPagos = new GestorPagos($conn);
    $resultado = $gestorPagos->crearPago($cita_id, $proveedor, $metodo_pago);

    if ($resultado['success']) {
        echo json_encode($resultado);
    } else {
        throw new Exception($resultado['error'] ?? 'Error creando pago');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>