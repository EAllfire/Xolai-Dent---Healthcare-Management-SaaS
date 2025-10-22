<?php
header('Content-Type: application/json');
require_once("includes/db.php");

$fecha = $_POST['fecha'] ?? '';
$hora_inicio = $_POST['hora_inicio'] ?? '';
$hora_fin = $_POST['hora_fin'] ?? '';
$paciente_id = $_POST['paciente_id'] ?? null;
$profesional_id = $_POST['profesional_id'] ?? 1; // Por defecto profesional 1
$servicio_id = $_POST['servicio_id'] ?? null;
$modalidad_id = $_POST['modalidad_id'] ?? null;
$estado_id = $_POST['estado_id'] ?? 1; // Estado "reservado" por defecto
$tipo = $_POST['tipo'] ?? 'normal';
$nota_interna = $_POST['nota_interna'] ?? '';
$nota_paciente = $_POST['nota_paciente'] ?? '';

$response = [];

try {
    if ($fecha && $hora_inicio && $hora_fin && $paciente_id && $servicio_id && $modalidad_id) {
        
        // Escapar valores para consulta directa
        $fecha = $conn->real_escape_string($fecha);
        $hora_inicio = $conn->real_escape_string($hora_inicio);
        $hora_fin = $conn->real_escape_string($hora_fin);
        $nota_paciente = $conn->real_escape_string($nota_paciente);
        $nota_interna = $conn->real_escape_string($nota_interna);
        $tipo = $conn->real_escape_string($tipo);
        
        $paciente_id = intval($paciente_id);
        $profesional_id = intval($profesional_id);
        $servicio_id = intval($servicio_id);
        $modalidad_id = intval($modalidad_id);
        $estado_id = intval($estado_id);
        
        // Verificar empalme de citas
        $sqlEmpalme = "SELECT COUNT(*) as total FROM agenda_citas 
                       WHERE fecha = '$fecha' AND modalidad_id = $modalidad_id 
                       AND ((hora_inicio < '$hora_fin' AND hora_fin > '$hora_inicio') 
                       OR (hora_inicio < '$hora_inicio' AND hora_fin > '$hora_fin') 
                       OR (hora_inicio >= '$hora_inicio' AND hora_inicio < '$hora_fin'))";
        
        $resultEmpalme = $conn->query($sqlEmpalme);
        
        if (!$resultEmpalme) {
            throw new Exception("Error en consulta empalme: " . $conn->error);
        }
        
        $rowEmpalme = $resultEmpalme->fetch_assoc();
        
        if ($rowEmpalme['total'] > 0) {
            $response = ["success" => false, "error" => "Ya existe una cita en ese horario para la modalidad seleccionada."];
        } else {
            // Insertar nueva cita
            $sqlInsert = "INSERT INTO agenda_citas (fecha, paciente_id, profesional_id, servicio_id, estado_id, nota_paciente, nota_interna, hora_inicio, hora_fin, modalidad_id, tipo) 
                         VALUES ('$fecha', $paciente_id, $profesional_id, $servicio_id, $estado_id, '$nota_paciente', '$nota_interna', '$hora_inicio', '$hora_fin', $modalidad_id, '$tipo')";
            
            if ($conn->query($sqlInsert)) {
                $id = $conn->insert_id;
                $response = ["success" => true, "id" => $id];
            } else {
                $response = ["success" => false, "error" => "Error al insertar: " . $conn->error];
            }
        }
    } else {
        $response = ["success" => false, "error" => "Faltan datos obligatorios: fecha, hora_inicio, hora_fin, paciente_id, servicio_id, modalidad_id"];
    }
} catch (Exception $e) {
    $response = ["success" => false, "error" => $e->getMessage()];
}

echo json_encode($response);
?>