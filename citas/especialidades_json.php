<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

try {
    $usuario_id_real = $_SESSION['usuario_id'] ?? 0;
    $id_propietario = $_SESSION['id_padre'] ?? $usuario_id_real;
    $usuario_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';

    $whereSQL = "";
    $params = [];
    $types = "";

    // Filter specialties by the current user's clinic (parent_id)
    if ($usuario_tipo !== 'superadmin') {
        $filter_id = !empty($id_propietario) ? $id_propietario : $usuario_id_real;
        $whereSQL = "WHERE ae.usuario_id = ?";
        $params[] = $filter_id;
        $types = "i";
    }

    $sql = "SELECT ae.id, ae.nombre FROM agenda_especialidades ae $whereSQL ORDER BY ae.nombre ASC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $especialidades = [];
    while ($row = $result->fetch_assoc()) {
        $especialidades[] = ['id' => (int)$row['id'], 'nombre' => $row['nombre']];
    }
    echo json_encode($especialidades);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>