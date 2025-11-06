<?php
header('Content-Type: application/json');

// Limpiar cualquier output previo
ob_start();
ob_clean();

try {
    require_once("includes/db.php");
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "Error conexión DB"]);
    exit;
}

$cita_id = $_POST['cita_id'] ?? '';
$nuevo_estado = $_POST['estado'] ?? '';

$response = [];

try {
    if ($cita_id && $nuevo_estado) {
        // Mapear nombres de estados a IDs
        $estados_map = [
            'reservado' => 1,
            'confirmado' => 2,
            'asistió' => 3,
            'no asistió' => 4,
            'pendiente' => 5,
            'en espera' => 6
        ];
        
        if (!isset($estados_map[$nuevo_estado])) {
            throw new Exception("Estado no válido: " . $nuevo_estado);
        }
        
        $estado_id = $estados_map[$nuevo_estado];
        
        // Actualizar el estado de la cita
        $stmt = $conn->prepare("UPDATE agenda_citas SET estado_id = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Error en prepare: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $estado_id, $cita_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Obtener el color correspondiente al nuevo estado
                $colores_map = [
                    'reservado' => '#2196F3',
                    'confirmado' => '#FF9800', 
                    'asistió' => '#E91E63',
                    'no asistió' => '#FF7F50',
                    'pendiente' => '#F44336',
                    'en espera' => '#4CAF50'
                ];
                
                $response = [
                    "success" => true, 
                    "message" => "Estado actualizado correctamente",
                    "nuevo_estado" => $nuevo_estado,
                    "nuevo_color" => $colores_map[$nuevo_estado]
                ];
            } else {
                $response = ["success" => false, "error" => "No se encontró la cita o no se realizaron cambios"];
            }
        } else {
            throw new Exception("Error al ejecutar query: " . $stmt->error);
        }
        
        $stmt->close();
    } else {
        $response = ["success" => false, "error" => "Faltan parámetros requeridos"];
    }
} catch (Exception $e) {
    $response = ["success" => false, "error" => $e->getMessage()];
}

echo json_encode($response);
?>
