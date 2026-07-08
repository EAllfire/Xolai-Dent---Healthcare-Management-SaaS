<?php
// Archivo: citas/actualizar_paciente.php
// Ruta completa sugerida: /Applications/MAMP/htdocs/Agenda/citas/actualizar_paciente.php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Ajuste de ruta para incluir db.php correctamente desde la carpeta 'citas'
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// Leer el cuerpo de la solicitud (JSON)
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

// Si no es JSON, intentar leer POST normal (por si acaso)
if (!$input) {
    $input = $_POST;
}

// Obtener datos
$id = $input['id'] ?? null;
$nombre = $input['nombre'] ?? '';
$apellido_paterno = $input['apellido_paterno'] ?? '';
$apellido_materno = $input['apellido_materno'] ?? '';
$telefono = $input['telefono'] ?? '';
$correo = $input['correo'] ?? '';
$alergias = $input['alergias'] ?? '';
$estado_id = !empty($input['estado_id']) ? (int)$input['estado_id'] : null; // Se mapea a 'tipo_id'
$origen = $input['origen'] ?? '';
$comentarios = $input['comentarios'] ?? '';
$fecha_nacimiento = $input['fecha_nacimiento'] ?? null;
$tel_emergencia = $input['tel_emergencia'] ?? '';
$motivo_consulta = $input['motivo_consulta'] ?? '';
$medicamentos = $input['medicamentos'] ?? '';
$direccion = $input['direccion'] ?? '';
$rfc = $input['rfc'] ?? '';
$recomendado_por_id = !empty($input['recomendado_por_id']) ? (int)$input['recomendado_por_id'] : null;

// Validación básica
if (!$id || !is_numeric($id)) {
    echo json_encode(["success" => false, "error" => "ID de paciente inválido o no proporcionado."]);
    exit;
}

if (empty($nombre) || empty($apellido_paterno) || empty($telefono)) {
    echo json_encode(["success" => false, "error" => "Nombre, Apellido Paterno y Teléfono son obligatorios."]);
    exit;
}

try {
    // Preparar la consulta UPDATE
    $sql = "UPDATE portal_pacientes SET 
            nombre = ?, 
            apellido_paterno = ?, 
            apellido_materno = ?, 
            telefono = ?, 
            correo = ?, 
            alergias = ?, 
            tipo_id = ?, 
            origen = ?, 
            comentarios = ?, 
            fecha_nacimiento = ?,
            tel_emergencia = ?,
            motivo_consulta = ?,
            medicamentos = ?,
            direccion = ?,
            rfc = ?,
            recomendado_por_id = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Error en la preparación de la consulta: " . $conn->error);
    }

    $stmt->bind_param("ssssssissssssssii", 
        $nombre, 
        $apellido_paterno, 
        $apellido_materno, 
        $telefono, 
        $correo, 
        $alergias, 
        $estado_id, 
        $origen, 
        $comentarios, 
        $fecha_nacimiento,
        $tel_emergencia,
        $motivo_consulta,
        $medicamentos,
        $direccion,
        $rfc,
        $recomendado_por_id,
        $id
    );

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true, 
            "message" => "Paciente actualizado correctamente",
            "id" => $id
        ]);
    } else {
        throw new Exception("Error al ejecutar la actualización: " . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>
