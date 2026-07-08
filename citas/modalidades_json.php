<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

try {
    $usuario_id_real = $_SESSION['usuario_id'] ?? 0;
    $id_propietario = $_SESSION['id_padre'] ?? $usuario_id_real;
    $usuario_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';

    // Verificar si la columna 'imagen' existe
    $colCheck = $conn->query("SHOW COLUMNS FROM agenda_modalidades LIKE 'imagen'");
    $hasImagen = ($colCheck && $colCheck->num_rows > 0);
    
    $imgField = $hasImagen ? "COALESCE(imagen, '') as imagen" : "'' as imagen";
    
    $whereSQL = "";
    $params = [];
    $types = "";

    $allowed = obtenerIdsPermitidos();
    error_log("Allowed IDs: " . json_encode($allowed) . " for user " . $usuario_id_real . " tipo " . $usuario_tipo . " padre " . $id_propietario);
    error_log("Allowed IDs: " . json_encode($allowed) . " for user " . $usuario_id_real . " tipo " . $usuario_tipo . " padre " . $id_propietario);
    if ($allowed === null) {
        // no restriction
    } elseif (is_array($allowed) && in_array('PARENT_ONLY', $allowed)) {
        $parent = $_SESSION['id_padre'] ?? null;
        if ($parent) {
            $whereSQL = "WHERE (m.usuario_id = ? OR m.usuario_id IN (SELECT id FROM agenda_usuarios WHERE id_padre = ?))";
            $params[] = $parent; $params[] = $parent; $types = "ii";
        } else {
            echo json_encode([]); exit;
        }
    } elseif (is_array($allowed) && in_array('SELF_AND_CHILDREN', $allowed)) {
        $self = $_SESSION['usuario_id'] ?? 0;
        $whereSQL = "WHERE (m.usuario_id = ? OR m.usuario_id IN (SELECT id FROM agenda_usuarios WHERE id_padre = ?))";
        $params[] = $self; $params[] = $self; $types = "ii";
    } elseif (is_array($allowed) && count($allowed) > 0) {
        $ids = implode(',', array_map('intval', $allowed));
        $whereSQL = "WHERE m.usuario_id IN ($ids)";
    }

    $sql = "SELECT m.id, m.nombre, $imgField, u.nombre as medico_nombre, m.usuario_id, u.especialidad_id 
            FROM agenda_modalidades m 
            LEFT JOIN agenda_usuarios u ON m.usuario_id = u.id 
            $whereSQL 
            ORDER BY m.nombre ASC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }

    $modalidades = [];
    while ($row = $result->fetch_assoc()) {
        $img = $row['imagen'] ?? '';
        // Normalizar ruta: si no es URL completa, devolver ruta relativa desde la raíz
        if ($img && strpos($img, '://') === false) {
            $img = '/' . ltrim($img, '/');
        }
        $modalidades[] = [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre'],
            'imagen' => $img,
            'medico_nombre' => $row['medico_nombre'],
            'usuario_id' => $row['usuario_id'],
            'especialidad_id' => $row['especialidad_id'] // Add specialty ID
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($modalidades);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('modalidades_json error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>