<?php
// --- INICIO: MANEJO DE ERRORES Y LOGS ---
ini_set('display_errors', 0); // No mostrar errores en producción
error_reporting(E_ALL);
ini_set('log_errors', 1);
// Define un archivo de log específico para este endpoint
$log_file = __DIR__ . '/_paquetes_json_errors.log';
ini_set('error_log', $log_file);

set_exception_handler(function ($exception) {
    error_log("Excepción no capturada: " . $exception->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['error' => 'Error interno del servidor. Revise el log de errores.']);
});
// --- FIN: MANEJO DE ERRORES Y LOGS ---

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $medico_id = isset($_GET['medico_id']) ? (int)$_GET['medico_id'] : 0;
    
    // 1. Obtener todos los paquetes
    $sql_paquetes = "SELECT id, nombre, descripcion, precio FROM agenda_paquetes";
    
    // Filtrar por médico si se proporciona (y si la tabla tiene la columna usuario_id)
    // NOTA: Asegúrate de tener la columna usuario_id en agenda_paquetes
    if ($medico_id > 0) {
        $sql_paquetes .= " WHERE usuario_id = $medico_id"; 
    }
    $sql_paquetes .= " ORDER BY nombre";
    
    $result_paquetes = $conn->query($sql_paquetes);

    if (!$result_paquetes) {
        throw new Exception("Error en la consulta de paquetes: " . $conn->error);
    }

    $paquetes = [];
    while ($row = $result_paquetes->fetch_assoc()) {
        $paquetes[] = $row;
    }
    $result_paquetes->close();

    // 2. Para cada paquete, obtener sus servicios asociados
    $sql_servicios = "SELECT s.id, s.nombre
                      FROM agenda_paquete_servicios aps
                      JOIN portal_servicios s ON aps.servicio_id = s.id
                      WHERE aps.paquete_id = ?";
    $stmt_servicios = $conn->prepare($sql_servicios);
    
    if (!$stmt_servicios) {
        throw new Exception("Error al preparar la consulta de servicios: " . $conn->error);
    }

    foreach ($paquetes as $key => $paquete) {
        $stmt_servicios->bind_param("i", $paquete['id']);
        $stmt_servicios->execute();
        
        // Usar bind_result para compatibilidad universal (evita dependencia de mysqlnd)
        $stmt_servicios->store_result();
        $stmt_servicios->bind_result($servicio_id, $servicio_nombre);
        
        $servicios = [];
        while ($stmt_servicios->fetch()) {
            $servicios[] = ['id' => $servicio_id, 'nombre' => $servicio_nombre];
        }
        $paquetes[$key]['servicios'] = $servicios;
    }
    
    $stmt_servicios->close();

    echo json_encode($paquetes);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en paquetes_json.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error interno en el servidor: ' . $e->getMessage()]);
}

// No es necesario cerrar $conn aquí si otros scripts lo necesitan
?>
