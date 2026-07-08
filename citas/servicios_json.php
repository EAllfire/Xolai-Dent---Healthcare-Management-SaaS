<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (ob_get_length()) ob_clean();
error_reporting(0);

try {
    $usuario_id_real = $_SESSION['usuario_id'] ?? 0;
    // Identificar al propietario principal (Padre)
    $id_propietario = $_SESSION['id_padre'] ?? $usuario_id_real;
    $usuario_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';

    $params = [];
    $types = "";
    $especialidad_id_medico = null;

    // Obtener especialidad del médico si se está filtrando por un médico específico
    $medico_id_param = $_GET['medico_id'] ?? null;
    if ($medico_id_param) {
        $stmt_medico_esp = $conn->prepare("SELECT especialidad_id FROM agenda_usuarios WHERE id = ?");
        $stmt_medico_esp->bind_param("i", $medico_id_param);
        $stmt_medico_esp->execute();
        $stmt_medico_esp->bind_result($especialidad_id_medico);
        $stmt_medico_esp->fetch();
        $stmt_medico_esp->close();
    }

    // Construcción de cláusulas WHERE dinámicas
    $whereClauses = [];

    // 1. Filtro de Propiedad: Estricto al Padre o sus colaboradores (Eliminamos servicios sin dueño o con ID vacío)
    $whereClauses[] = "((s.usuario_id = ? OR s.usuario_id IN (SELECT id FROM agenda_usuarios WHERE id_padre = ?)) AND s.usuario_id > 0)";
    $params[] = $id_propietario;
    $params[] = $id_propietario;
    $types .= "ii";

    // 2. Filtro de Especialidad: Inteligente y Excluyente
    if ($medico_id_param && is_numeric($medico_id_param)) {
        if ($especialidad_id_medico && $especialidad_id_medico > 0) {
            // Médico con especialidad: ve la suya O servicios generales (oculta otras especialidades)
            $whereClauses[] = "(s.especialidad_id = ? OR s.especialidad_id IS NULL OR s.especialidad_id = 0)";
            $params[] = $especialidad_id_medico;
            $types .= "i";
        } else {
            // Médico sin especialidad (General): ve ÚNICAMENTE los servicios generales
            $whereClauses[] = "(s.especialidad_id IS NULL OR s.especialidad_id = 0)";
        }
    }

    $whereSQL = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";

    $sql = "SELECT s.id, s.nombre, s.descripcion, s.precio, s.duracion_minutos, s.modalidad_id, m.nombre as modalidad_nombre, s.especialidad_id, ae.nombre as especialidad_nombre, 
            (SELECT GROUP_CONCAT(u2.nombre SEPARATOR ', ') FROM agenda_usuarios u2 WHERE u2.especialidad_id = s.especialidad_id AND u2.tipo IN ('medico', 'dentista')) as medico_nombre 
            FROM portal_servicios s
            LEFT JOIN agenda_modalidades m ON s.modalidad_id = m.id
            LEFT JOIN agenda_especialidades ae ON s.especialidad_id = ae.id
            $whereSQL";

    $sql .= " ORDER BY s.nombre ASC";
            

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $servicios = [];
    while ($row = $result->fetch_assoc()) {
        $servicios[] = [
            'id' => $row['id'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'precio' => $row['precio'],
            'duracion_minutos' => $row['duracion_minutos'],
            'modalidad_id' => $row['modalidad_id'],
            'especialidad_id' => $row['especialidad_id'],
            'especialidad_nombre' => $row['especialidad_nombre'],
            'modalidad_nombre' => $row['modalidad_nombre'],
            'medico_nombre' => $row['medico_nombre']
        ];
    }
    echo json_encode($servicios);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>