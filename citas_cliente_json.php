<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Registrar una función de apagado para capturar errores fatales
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        // Limpiar cualquier salida anterior
        if (ob_get_length()) {
            ob_clean();
        }
        
        // Asegurarse de que no se envíen más cabeceras
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        
        // Loguear el error real en el servidor
        error_log("Error fatal capturado en citas_cliente_json.php: " . $error['message'] . " en " . $error['file'] . ":" . $error['line']);
        
        // Enviar una respuesta JSON genérica al cliente
        echo json_encode([
            'success' => false,
            'error' => 'Ocurrió un error crítico en el servidor. Revise los logs.',
            'debug_info' => 'Error fatal capturado por shutdown function. Es probable que falte el driver `mysqlnd`.'
        ]);
    }
});

// Iniciar búfer de salida para controlar la respuesta
ob_start();

try {
    header('Content-Type: application/json');
    require_once("includes/db.php");
    require_once("includes/auth.php");

    // Verificar si la conexión a la BD falló
    if ($conn->connect_error) {
        throw new Exception("Error de conexión a la base de datos: " . $conn->connect_error);
    }

    $cliente_id = $_GET['cliente_id'] ?? null;

    // Verificar acceso del usuario actual al paciente según owner-scope
    $allowed = obtenerIdsPermitidos();
    $stmt_check = $conn->prepare("SELECT usuario_id FROM portal_pacientes WHERE id = ?");
    $stmt_check->bind_param('i', $cliente_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    $patientRow = $res_check->fetch_assoc();
    $stmt_check->close();
    if (!$patientRow) {
        http_response_code(404);
        throw new Exception('Paciente no encontrado.');
    }
    $patient_owner = $patientRow['usuario_id'];

    if ($allowed !== null) {
        if (is_array($allowed) && in_array('PARENT_ONLY', $allowed)) {
            $parent = $_SESSION['id_padre'] ?? null;
            if (!$parent || $patient_owner != $parent) {
                http_response_code(403); throw new Exception('Acceso denegado.');
            }
        } elseif (is_array($allowed) && in_array('SELF_AND_CHILDREN', $allowed)) {
            $self = $_SESSION['usuario_id'] ?? 0;
            // comprobar self o hijo
            if (!($patient_owner == $self)) {
                // check if patient's owner is a child of self
                $stmt_ch = $conn->prepare("SELECT COUNT(*) as cnt FROM agenda_usuarios WHERE id = ? AND id_padre = ?");
                $stmt_ch->bind_param('ii', $patient_owner, $self);
                $stmt_ch->execute(); $res_ch = $stmt_ch->get_result(); $rch = $res_ch->fetch_assoc(); $stmt_ch->close();
                if ((int)$rch['cnt'] === 0) { http_response_code(403); throw new Exception('Acceso denegado.'); }
            }
        } elseif (is_array($allowed) && count($allowed)>0) {
            if (!in_array((int)$patient_owner, array_map('intval', $allowed))) { http_response_code(403); throw new Exception('Acceso denegado.'); }
        }
    }

    if (empty($cliente_id)) {
        http_response_code(400); // Bad Request
        throw new Exception('ID de cliente no proporcionado.');
    }

    $cliente_id = intval($cliente_id);

    $sql = "SELECT 
                c.id, 
                c.fecha, 
                DATE_FORMAT(c.hora_inicio, '%h:%i %p') AS hora_inicio, 
                DATE_FORMAT(c.hora_fin, '%h:%i %p') AS hora_fin, 
                s.nombre AS servicio_nombre, 
                m.nombre AS modalidad_nombre,
                e.nombre AS estado_nombre
            FROM agenda_citas c
            JOIN portal_servicios s ON c.servicio_id = s.id
            JOIN agenda_modalidades m ON c.modalidad_id = m.id
            JOIN agenda_estado_cita e ON c.estado_id = e.id
            WHERE c.paciente_id = ?
            ORDER BY c.fecha DESC, c.hora_inicio DESC";

    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        throw new Exception('La preparación de la consulta falló: ' . $conn->error);
    }

    $stmt->bind_param("i", $cliente_id);
    
    if (!$stmt->execute()) {
        throw new Exception('La ejecución de la consulta falló: ' . $stmt->error);
    }
    
    // Usar bind_result para compatibilidad con entornos sin mysqlnd
    $stmt->bind_result($id, $fecha, $hora_inicio, $hora_fin, $servicio_nombre, $modalidad_nombre, $estado_nombre);

    $citas = [];
    while ($stmt->fetch()) {
        $citas[] = [
            'id' => $id,
            'fecha' => $fecha,
            'hora_inicio' => $hora_inicio,
            'hora_fin' => $hora_fin,
            'servicio_nombre' => $servicio_nombre,
            'modalidad_nombre' => $modalidad_nombre,
            'estado_nombre' => $estado_nombre
        ];
    }
    
    $stmt->close();
    $conn->close();

    ob_end_clean(); // Limpiar búfer y enviar solo la respuesta JSON
    echo json_encode($citas);

} catch (Throwable $t) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    
    error_log("Error en try-catch en citas_cliente_json.php: " . $t->getMessage());
    
    if (!headers_sent()) {
        http_response_code(500);
    }
    
    echo json_encode([
        'success' => false, 
        'error' => 'Error interno del servidor al procesar la solicitud.', 
        'details' => $t->getMessage()
    ]);
}
?>