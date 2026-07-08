<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php'; // Para obtenerIdsPermitidos

header('Content-Type: application/json; charset=utf-8');

try {
    $usuario_id_real = $_SESSION['usuario_id'] ?? 0;
    $id_propietario = $_SESSION['id_padre'] ?? $usuario_id_real;
    $usuario_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';

    if (!$usuario_id_real) {
        echo json_encode(['error' => 'Sesión no válida.']);
        exit;
    }

    // Solo el usuario padre (o admin/superadmin) puede gestionar sus propios orígenes.
    // Los colaboradores verán los orígenes de su padre.
    $filter_user_id = $id_propietario;

    $sql = "SELECT id, nombre FROM agenda_origenes_recomendacion WHERE usuario_id = ? ORDER BY nombre ASC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $filter_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $origenes = [];
    while ($row = $result->fetch_assoc()) {
        $origenes[] = ['id' => $row['id'], 'nombre' => $row['nombre']];
    }

    echo json_encode($origenes);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en origenes_recomendacion_json.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>
