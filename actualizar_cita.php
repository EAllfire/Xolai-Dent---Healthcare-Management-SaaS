<?php
header('Content-Type: application/json');

try {
    require_once("includes/db.php");
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "Error conexión DB: " . $e->getMessage()]);
    exit;
}

$response = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cita_id = $_POST['cita_id'] ?? '';
        $fecha = $_POST['fecha'] ?? '';
        $hora_inicio = $_POST['hora_inicio'] ?? '';
        $hora_fin = $_POST['hora_fin'] ?? '';
        $estado_id = $_POST['estado_id'] ?? '';
        $nota_paciente = $_POST['nota_paciente'] ?? '';
        $nota_interna = $_POST['nota_interna'] ?? '';
        
        // Validaciones básicas
        if (empty($cita_id) || empty($fecha) || empty($hora_inicio) || empty($hora_fin)) {
            echo json_encode(['success' => false, 'error' => "Faltan datos - cita_id: '$cita_id', fecha: '$fecha', hora_inicio: '$hora_inicio', hora_fin: '$hora_fin'"]);
            exit;
        }
        
        if (!is_numeric($cita_id)) {
            echo json_encode(['success' => false, 'error' => 'cita_id debe ser numérico']);
            exit;
        }
        
        // Escapar valores
        $cita_id = intval($cita_id);
        $fecha = $conn->real_escape_string($fecha);
        $hora_inicio = $conn->real_escape_string($hora_inicio);
        $hora_fin = $conn->real_escape_string($hora_fin);
        $nota_paciente = $conn->real_escape_string($nota_paciente);
        $nota_interna = $conn->real_escape_string($nota_interna);
        
        // Construir consulta de actualización
        $sql = "UPDATE agenda_citas SET 
                fecha = '$fecha', 
                hora_inicio = '$hora_inicio', 
                hora_fin = '$hora_fin'";
        
        if ($estado_id && is_numeric($estado_id)) {
            $estado_id = intval($estado_id);
            $sql .= ", estado_id = $estado_id";
        }
        
        if ($nota_paciente !== '') {
            $sql .= ", nota_paciente = '$nota_paciente'";
        }
        
        if ($nota_interna !== '') {
            $sql .= ", nota_interna = '$nota_interna'";
        }
        
        $sql .= " WHERE id = $cita_id";
        
        if ($conn->query($sql)) {
            $response = ['success' => true, 'message' => 'Cita actualizada correctamente'];
        } else {
            $response = ['success' => false, 'error' => 'Error SQL: ' . $conn->error];
        }
    } else {
        $response = ['success' => false, 'error' => 'Método no permitido: ' . $_SERVER['REQUEST_METHOD']];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'error' => 'Excepción: ' . $e->getMessage()];
}

echo json_encode($response);
?>
        if (!$conn) {
            // En modo demo sin base de datos
            echo json_encode(['success' => true, 'message' => 'Cita actualizada (modo demo)']);
            exit;
        }
        
        // Verificar que la cita existe
        $stmt = $conn->prepare("SELECT id FROM agenda_citas WHERE id = ?");
        $stmt->bind_param("i", $cita_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Cita no encontrada']);
            exit;
        }
        
        // Verificar solapamiento de horarios (excluyendo la cita actual)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM agenda_citas 
            WHERE fecha = ? 
            AND id != ?
            AND modalidad_id = (SELECT modalidad_id FROM agenda_citas WHERE id = ?)
            AND (
                (hora_inicio < ? AND hora_fin > ?) OR
                (hora_inicio < ? AND hora_fin > ?) OR
                (hora_inicio >= ? AND hora_fin <= ?)
            )
        ");
        $stmt->bind_param("siisssss", $fecha, $cita_id, $cita_id, $hora_fin, $hora_inicio, $hora_inicio, $hora_fin, $hora_inicio, $hora_fin);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['total'] > 0) {
            echo json_encode(['success' => false, 'error' => 'Ya existe una cita en ese horario para la misma modalidad']);
            exit;
        }
        
        // Actualizar la cita
        $stmt = $conn->prepare("
            UPDATE agenda_citas 
            SET fecha = ?, hora_inicio = ?, hora_fin = ?, estado_id = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssii", $fecha, $hora_inicio, $hora_fin, $estado_id, $cita_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Cita actualizada correctamente']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al actualizar la cita']);
        }
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    }
    
} catch (Exception $e) {
    error_log("Error en actualizar_cita.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor']);
}
?>