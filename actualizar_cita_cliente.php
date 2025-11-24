<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

header('Content-Type: application/json');

ob_start();
ob_clean();

try {
    require_once("includes/db.php");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cita_id = $_POST['cita_id'] ?? '';
        $fecha = $_POST['fecha'] ?? '';
        $hora_inicio = $_POST['hora_inicio'] ?? '';
        $estado_id = $_POST['estado_id'] ?? '1'; // Default to '1' (Programada) for client updates
        
        if (empty($cita_id) || empty($fecha) || empty($hora_inicio)) {
            echo json_encode(['success' => false, 'error' => "Faltan datos obligatorios (cita_id, fecha, hora_inicio)."]);
            exit;
        }
        
        if (!is_numeric($cita_id) || !is_numeric($estado_id)) {
            echo json_encode(['success' => false, 'error' => 'ID de cita y estado deben ser numéricos.']);
            exit;
        }
        
        $cita_id = intval($cita_id);
        $estado_id = intval($estado_id);

        // 1. Verificar que la cita existe y obtener modalidad_id, servicio_id y paciente_id
        $stmt_check = $conn->prepare("SELECT modalidad_id, servicio_id, paciente_id FROM agenda_citas WHERE id = ?");
        if ($stmt_check === false) {
            throw new Error('Prepare failed (select cita details): ' . $conn->error);
        }
        $stmt_check->bind_param("i", $cita_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Cita no encontrada.']);
            $stmt_check->close();
            exit;
        }
        
        $stmt_check->bind_result($modalidad_id, $servicio_id, $paciente_id);
        $stmt_check->fetch();
        $stmt_check->close();

        // TODO: Add client authentication here.
        // For example, check if $_SESSION['cliente_id'] matches $paciente_id
        // if (!isset($_SESSION['cliente_id']) || $_SESSION['cliente_id'] != $paciente_id) {
        //     echo json_encode(['success' => false, 'error' => 'No autorizado para modificar esta cita.']);
        //     exit;
        // }

        // 2. Obtener la duración del servicio para calcular hora_fin
        $stmt_duration = $conn->prepare("SELECT duracion_minutos FROM agenda_servicios WHERE id = ?");
        if ($stmt_duration === false) {
            throw new Error('Prepare failed (select service duration): ' . $conn->error);
        }
        $stmt_duration->bind_param("i", $servicio_id);
        $stmt_duration->execute();
        $stmt_duration->bind_result($duracion_minutos);
        $stmt_duration->fetch();
        $stmt_duration->close();

        if (empty($duracion_minutos)) {
            echo json_encode(['success' => false, 'error' => 'No se pudo obtener la duración del servicio.']);
            exit;
        }

        // Calcular hora_fin
        $hora_fin = date('H:i:s', strtotime($hora_inicio . ' +' . $duracion_minutos . ' minutes'));

        // 3. Verificar solapamiento de horarios
        $stmt_overlap = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM agenda_citas 
            WHERE fecha = ? 
            AND id != ?
            AND modalidad_id = ?
            AND hora_inicio < ? AND hora_fin > ?
        ");
        if ($stmt_overlap === false) {
            throw new Error('Prepare failed (overlap check): ' . $conn->error);
        }
        $stmt_overlap->bind_param("siiss", $fecha, $cita_id, $modalidad_id, $hora_fin, $hora_inicio);
        $stmt_overlap->execute();
        $stmt_overlap->bind_result($total_solapamientos);
        $stmt_overlap->fetch();
        $stmt_overlap->close();
        
        if ($total_solapamientos > 0) {
            echo json_encode(['success' => false, 'error' => 'Ya existe una cita en ese horario para la misma modalidad.']);
            exit;
        }
        
        // 4. Actualizar la cita
        $stmt_update = $conn->prepare("
            UPDATE agenda_citas 
            SET fecha = ?, hora_inicio = ?, hora_fin = ?, estado_id = ?
            WHERE id = ?
        ");
        if ($stmt_update === false) {
            throw new Error('Prepare failed (update): ' . $conn->error);
        }
        $stmt_update->bind_param("sssii", $fecha, $hora_inicio, $hora_fin, $estado_id, $cita_id);
        
        if ($stmt_update->execute()) {
            echo json_encode(['success' => true, 'message' => 'Cita actualizada correctamente.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al actualizar la cita: ' . $stmt_update->error]);
        }
        $stmt_update->close();
        
    } else {
        echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    }
    
} catch (Throwable $t) {
    error_log("Error fatal en actualizar_cita_cliente.php: " . $t->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor. Por favor, contacte al administrador.', 'details' => $t->getMessage()]);
}