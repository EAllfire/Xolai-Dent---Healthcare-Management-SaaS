<?php
require_once("includes/db.php");
header('Content-Type: application/json');

$servicios = []; // Initialize $servicios to an empty array

try {
    $modalidad_id = isset($_GET['modalidad_id']) ? intval($_GET['modalidad_id']) : 0;

    // Consulta para obtener servicios basado en modalidad_id -> modalidad (remoto)
    $sql = "SELECT id, nombre, duracion AS duracion_minutos FROM portal_servicios WHERE modalidad = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $modalidad_id);
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