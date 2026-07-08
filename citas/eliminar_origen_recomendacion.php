<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!puedeRealizar('gestionar_origenes_recomendacion')) { // Nuevo permiso
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id'] ?? 0);
$usuario_id_creador = $_SESSION['id_padre'] ?? $_SESSION['usuario_id'];

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de origen no válido.']);
    exit;
}
if (empty($usuario_id_creador)) {
    echo json_encode(['success' => false, 'error' => 'No se pudo identificar al usuario creador.']);
    exit;
}

try {
    // Antes de eliminar, verificar si hay pacientes asociados a este origen.
    // Si el origen es un string (ej. "Facebook"), debemos buscar por ese string.
    // Si el origen es un ID numérico, debemos buscar por ese ID.
    // Dado que estamos guardando el nombre del origen en portal_pacientes.origen,
    // necesitamos obtener el nombre del origen a eliminar.
    $stmt_get_name = $conn->prepare("SELECT nombre FROM agenda_origenes_recomendacion WHERE id = ? AND usuario_id = ?");
    $stmt_get_name->bind_param("ii", $id, $usuario_id_creador);
    $stmt_get_name->execute();
    $stmt_get_name->bind_result($origen_nombre_a_eliminar);
    if (!$stmt_get_name->fetch()) {
        $stmt_get_name->close();
        throw new Exception("Origen de recomendación no encontrado o no pertenece a su clínica.");
    }
    $stmt_get_name->close();

    $stmt_check_pacientes = $conn->prepare("SELECT COUNT(*) FROM portal_pacientes WHERE origen = ? AND usuario_id = ?");
    $stmt_check_pacientes->bind_param("si", $origen_nombre_a_eliminar, $usuario_id_creador);
    $stmt_check_pacientes->execute();
    $stmt_check_pacientes->bind_result($pacientes_asociados);
    $stmt_check_pacientes->fetch();
    $stmt_check_pacientes->close();

    if ($pacientes_asociados > 0) {
        throw new Exception("No se puede eliminar el origen '{$origen_nombre_a_eliminar}' porque tiene {$pacientes_asociados} paciente(s) asociado(s).");
    }

    $stmt = $conn->prepare("DELETE FROM agenda_origenes_recomendacion WHERE id = ? AND usuario_id = ?");
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    $stmt->bind_param("ii", $id, $usuario_id_creador);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se encontró el origen o no se pudo eliminar.']);
        }
    } else {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en eliminar_origen_recomendacion.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
