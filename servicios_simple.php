<?php
require_once("includes/db.php");
header('Content-Type: application/json');

$modalidad_id = $_GET['modalidad_id'] ?? '';

if (!$modalidad_id || !is_numeric($modalidad_id)) {
    echo json_encode([]);
    exit;
}

// Primero intentar con portal_servicios
try {
    $sql = "SELECT id, nombre, duracion AS duracion_minutos FROM portal_servicios WHERE modalidad = ? ORDER BY nombre";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error en prepare: " . $conn->error);
    }
    
    $stmt->bind_param("i", $modalidad_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $servicios = [];
    while ($row = $result->fetch_assoc()) {
        $servicios[] = [
            'id' => $row['id'],
            'nombre' => $row['nombre'],
            'duracion_minutos' => intval($row['duracion_minutos'])
        ];
    }
    
    echo json_encode($servicios);
    
} catch (Exception $e) {
    // Si falla, intentar con agenda_ventas_servicios (tabla alternativa)
    try {
        $sql = "SELECT id, nombre, 30 AS duracion_minutos FROM agenda_ventas_servicios WHERE modalidad_id = ? ORDER BY nombre";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $modalidad_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $servicios = [];
        while ($row = $result->fetch_assoc()) {
            $servicios[] = [
                'id' => $row['id'],
                'nombre' => $row['nombre'],
                'duracion_minutos' => 30  // Duración por defecto
            ];
        }
        
        echo json_encode($servicios);
        
    } catch (Exception $e2) {
        echo json_encode(['error' => $e2->getMessage()]);
    }
}
?>