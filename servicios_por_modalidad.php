<?php
session_start();
require_once("includes/db.php");
require_once("includes/auth.php");
header('Content-Type: application/json');

$servicios = []; // Initialize $servicios to an empty array

try {
    $modalidad_id = isset($_GET['modalidad_id']) ? intval($_GET['modalidad_id']) : 0;
    $allowed_ids = obtenerIdsPermitidos();
    $especialidad_id_medico = null;
    
    $where_sql = "s.modalidad_id = ?";
    $params = [$modalidad_id];
    $types = "i";

    // Get the user_id (doctor_id) associated with the modality
    $stmt_mod_user = $conn->prepare("SELECT usuario_id FROM agenda_modalidades WHERE id = ?");
    $stmt_mod_user->bind_param("i", $modalidad_id);
    $stmt_mod_user->execute();
    $stmt_mod_user->bind_result($modality_user_id);
    $stmt_mod_user->fetch();
    $stmt_mod_user->close();

    if ($modality_user_id) {
        $stmt_medico_esp = $conn->prepare("SELECT especialidad_id FROM agenda_usuarios WHERE id = ?");
        $stmt_medico_esp->bind_param("i", $modality_user_id);
        $stmt_medico_esp->execute();
        $stmt_medico_esp->bind_result($especialidad_id_medico);
        $stmt_medico_esp->fetch();
        $stmt_medico_esp->close();
    }

    if ($allowed_ids !== null) {
        // This part is already handled by the main `servicios_publicos.php` or `citas/servicios_json.php` logic
        // For client-facing, we assume the modality_id implies the correct owner scope.
    }

    // Consulta para obtener servicios basado en modalidad_id -> modalidad (remoto)
    $sql = "SELECT s.id, s.nombre, s.duracion_minutos FROM portal_servicios s WHERE $where_sql";

    // Apply specialty filter
    if ($especialidad_id_medico !== null) {
        $sql .= " AND (s.especialidad_id = ? OR s.especialidad_id IS NULL)";
        $params[] = $especialidad_id_medico;
        $types .= "i";
    }

    $sql .= " ORDER BY s.nombre ASC";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $servicios[] = [
            'id' => $row['id'],
            'nombre' => $row['nombre'],
            'duracion_minutos' => $row['duracion_minutos']
        ];
    }
    echo json_encode($servicios);

} catch (Exception $e) {
    // Log the error for debugging purposes
    error_log("Error en servicios_por_modalidad.php: " . $e->getMessage());
    // Return a JSON error response
    echo json_encode(['error' => $e->getMessage()]);
    // Ensure no other output is sent
    http_response_code(500);
}
?>