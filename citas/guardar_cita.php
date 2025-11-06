<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once '../includes/db.php';

$fecha = $_POST['fecha'] ?? '';
$hora_inicio = $_POST['hora_inicio'] ?? '';
$hora_fin = $_POST['hora_fin'] ?? '';
$paciente_id = $_POST['paciente_id'] ?? null;
$profesional_id = $_POST['profesional_id'] ?? null;
$servicio_id = $_POST['servicio_id'] ?? null;
$modalidad_id = $_POST['modalidad_id'] ?? null;

// Modalities that don't require a specific professional
$modalidades_sin_profesional = [3, 4, 5, 6, 7, 8]; 
if (in_array($modalidad_id, $modalidades_sin_profesional)) {
    $profesional_id = null;
} else {
    $profesional_id = is_numeric($profesional_id) ? intval($profesional_id) : null;
}

$estado_id = $_POST['estado_id'] ?? null; // <-- CAMBIO IMPORTANTE!
$tipo = $_POST['tipo'] ?? '';
$nota_interna = $_POST['nota_interna'] ?? '';
$nota_paciente = $_POST['nota_paciente'] ?? '';

error_log('guardar_cita.php datos recibidos: ' . json_encode($_POST));

$response = [];
try {
    if ($fecha && $hora_inicio && $hora_fin && $paciente_id && $profesional_id && $servicio_id && $modalidad_id && $estado_id) {
        // Validar empalme de citas en la misma modalidad y fecha
        $sqlEmpalme = "SELECT COUNT(*) as total FROM agenda_citas WHERE fecha = ? AND modalidad_id = ? AND ((hora_inicio < ? AND hora_fin > ?) OR (hora_inicio < ? AND hora_fin > ?) OR (hora_inicio >= ? AND hora_inicio < ?))";
        $stmtEmpalme = $conn->prepare($sqlEmpalme);
        if (!$stmtEmpalme) {
            throw new Exception("Error en prepare empalme: " . $conn->error);
        }
        $stmtEmpalme->bind_param(
            "sissssss",
            $fecha,
            $modalidad_id,
            $hora_fin,
            $hora_inicio,
            $hora_inicio,
            $hora_fin,
            $hora_inicio,
            $hora_fin
        );
        $stmtEmpalme->execute();
        $resultEmpalme = $stmtEmpalme->get_result();
        $rowEmpalme = $resultEmpalme->fetch_assoc();
        $stmtEmpalme->close();
        if ($rowEmpalme['total'] > 0) {
            $response = ["success" => false, "error" => "Ya existe una cita en ese horario para la modalidad seleccionada."];
        } else {
            $stmt = $conn->prepare("INSERT INTO agenda_citas (fecha, paciente_id, profesional_id, servicio_id, estado_id, nota_paciente, nota_interna, hora_inicio, hora_fin, modalidad_id, tipo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Error en prepare: " . $conn->error);
            }
            $stmt->bind_param(
                "siiiissssis",
                $fecha,
                $paciente_id,
                $profesional_id,
                $servicio_id,
                $estado_id,
                $nota_paciente,
                $nota_interna,
                $hora_inicio,
                $hora_fin,
                $modalidad_id,
                $tipo
            );
            if ($stmt->execute()) {
                $id = $stmt->insert_id;
                $response = ["success" => true, "id" => $id];
            } else {
                $response = ["success" => false, "error" => $stmt->error];
            }
            $stmt->close();
        }
    } else {
        $response = ["success" => false, "error" => "Faltan datos obligatorios."];
    }
} catch (Exception $e) {
    $response = ["success" => false, "error" => $e->getMessage()];
}

echo json_encode($response);
?>