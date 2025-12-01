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

    // Verificar si la conexión a la BD falló
    if ($conn->connect_error) {
        throw new Exception("Error de conexión a la base de datos: " . $conn->connect_error);
    }

    $cliente_id = $_GET['cliente_id'] ?? null;

    if (empty($cliente_id)) {
        http_response_code(400); // Bad Request
        throw new Exception('ID de cliente no proporcionado.');
    }

    $cliente_id = intval($cliente_id);

    $sql = "SELECT 
                c.id, 
                c.fecha, 
                DATE_FORMAT(c.hora_inicio, '%H:%i') AS hora_inicio, 
                DATE_FORMAT(c.hora_fin, '%H:%i') AS hora_fin, 
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