<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
header('Content-Type: application/json');

// Obtener el ID del médico para filtrar (opcional)
$medico_id = isset($_GET['medico_id']) ? (int)$_GET['medico_id'] : 0;
$allowed_ids = obtenerIdsPermitidos();

try {
    $sql = "SELECT s.id, s.nombre, s.descripcion, s.precio, s.duracion_minutos, s.modalidad_id, m.nombre as modalidad_nombre, u.nombre as medico_nombre 
            FROM portal_servicios s
            LEFT JOIN agenda_modalidades m ON s.modalidad_id = m.id
            LEFT JOIN agenda_usuarios u ON s.usuario_id = u.id";
    
    $params = [];
    $types = "";
    $especialidad_id_medico = null;

    if ($medico_id > 0) {
        // Obtener especialidad del médico
        $stmt_medico_esp = $conn->prepare("SELECT especialidad_id FROM agenda_usuarios WHERE id = ?");
        $stmt_medico_esp->bind_param("i", $medico_id);
        $stmt_medico_esp->execute();
        $stmt_medico_esp->bind_result($especialidad_id_medico);
        $stmt_medico_esp->fetch();
        $stmt_medico_esp->close();

        $sql .= " WHERE s.usuario_id = ?"; // Filter by services owned by this doctor
        $params[] = $medico_id;
        $types .= "i";
    } elseif ($allowed_ids !== null) {
        if (in_array('PARENT_ONLY', $allowed_ids)) {
            $id_p = $_SESSION['id_padre'] ?? 0;
            $sql .= " WHERE (s.usuario_id = ? OR s.usuario_id IN (SELECT id FROM agenda_usuarios WHERE id_padre = ?))"; // Services owned by parent or its children
            $params[] = $id_p; $params[] = $id_p; $types = "ii";
        } else {
            $ids_str = implode(',', array_map('intval', $allowed_ids));
            $sql .= " WHERE s.usuario_id IN ($ids_str)";
        }
    }

    // Aplicar filtro por especialidad del médico o servicios generales
    if ($especialidad_id_medico !== null) {
        $sql .= ($params ? " AND " : " WHERE ") . "(s.especialidad_id = ? OR s.especialidad_id IS NULL)";
        $params[] = $especialidad_id_medico;
        $types .= "i";
    }

    $sql .= " ORDER BY s.nombre ASC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) { // Only bind if there are parameters
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $servicios = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode($servicios);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>