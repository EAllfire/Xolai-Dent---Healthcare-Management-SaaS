<?php
// Evitar que errores de PHP se impriman en la salida y rompan el JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

try {
    require_once 'includes/db.php';

    if (!isset($_SESSION['usuario_id'])) {
        throw new Exception('No autorizado');
    }

    $cita_id = $_POST['cita_id'] ?? null;
    $paciente_id = $_POST['paciente_id'] ?? null;
    $idx = $_POST['idx'] ?? null;
    $estado_pago = $_POST['estado_pago'] ?? null;
    $metodo = $_POST['metodo'] ?? 'Efectivo';
    $doctor = $_POST['doctor'] ?? null; // ID o Nombre dependiendo del contexto

    if ((!$cita_id && $cita_id !== 'dental') || !$estado_pago) {
        throw new Exception('Datos incompletos');
    }

    $valid_states = ['pendiente', 'completado', 'cancelado'];
    if (!in_array($estado_pago, $valid_states)) {
        throw new Exception('Estado inválido');
    }

    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    if ($cita_id === 'dental') {
        // Actualización de registro en JSON del expediente
        $stmt = $conn->prepare("SELECT registro_pagos_json FROM agenda_expediente_dentista WHERE paciente_id = ?");
        $stmt->bind_param("i", $paciente_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row) throw new Exception('Expediente no encontrado');

        $pagos = json_decode($row['registro_pagos_json'], true);
        if (!isset($pagos[$idx])) throw new Exception('Registro de pago no encontrado en el índice especificado');

        // Actualizar datos
        $pagos[$idx]['metodo'] = $metodo;
        if ($doctor) $pagos[$idx]['doctor_nombre'] = $doctor; // En dental es texto
        
        if ($estado_pago === 'completado') {
            $total = (float)($pagos[$idx]['base'] ?? 0) + (float)($pagos[$idx]['ajuste'] ?? 0);
            $pagos[$idx]['pago'] = $total;
            $pagos[$idx]['fecha'] = date('Y-m-d'); // Actualizar a fecha de hoy al pagar
        } else {
            $pagos[$idx]['pago'] = 0;
        }

        $new_json = json_encode($pagos);
        $upd = $conn->prepare("UPDATE agenda_expediente_dentista SET registro_pagos_json = ? WHERE paciente_id = ?");
        $upd->bind_param("si", $new_json, $paciente_id);
        if (!$upd->execute()) throw new Exception("Error al guardar en expediente: " . $upd->error);
        $upd->close();

        echo json_encode(['success' => true]);
    } else {
        // Actualización de cita normal
        $sql = "UPDATE agenda_citas SET estado_pago = ?";
        if ($doctor) $sql .= ", profesional_id = ?";
        $sql .= " WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        if ($doctor) {
            $stmt->bind_param("sii", $estado_pago, $doctor, $cita_id);
        } else {
            $stmt->bind_param("si", $estado_pago, $cita_id);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Error al actualizar cita: " . $stmt->error);
        }
        $stmt->close();
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
