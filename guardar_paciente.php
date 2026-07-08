<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start();
require_once("includes/db.php");
header('Content-Type: application/json');

// Lógica de jerarquía: si tiene padre, el dueño de los datos es el padre
$usuario_id = $_SESSION['id_padre'] ?? $_SESSION['usuario_id'] ?? null;

$paciente_id_input = $_POST['id'] ?? null;
$nombre = $_POST['nombre'] ?? '';
$apellido_paterno = $_POST['apellido_paterno'] ?? '';
$apellido_materno = $_POST['apellido_materno'] ?? '';
$telefono = $_POST['telefono'] ?? '';
$correo = $_POST['correo'] ?? '';
$alergias = $_POST['alergias'] ?? $_POST['diagnostico'] ?? '';
$origen = $_POST['origen'] ?? '';
$comentarios = $_POST['comentarios'] ?? '';
$fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
$tel_emergencia = $_POST['tel_emergencia'] ?? '';
$motivo_consulta = $_POST['motivo_consulta'] ?? '';
$medicamentos = $_POST['medicamentos'] ?? '';
$direccion = $_POST['direccion'] ?? '';
$rfc = $_POST['rfc'] ?? '';


if (empty($usuario_id)) {
    echo json_encode(["success" => false, "error" => "No se pudo identificar al usuario. Inicie sesión de nuevo."]);
    exit;
}

if ($nombre && $apellido_paterno && $telefono) {
    $tipo_id = $_POST['tipo_id'] ?? null;
    $apellido_legacy = trim($apellido_paterno . ' ' . $apellido_materno);
    $stmt = $conn->prepare("INSERT INTO portal_pacientes (usuario_id, nombre, apellido_paterno, apellido_materno, apellido, telefono, correo, alergias, tipo_id, origen, comentarios, fecha_nacimiento, tel_emergencia, motivo_consulta, medicamentos, direccion, rfc) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(["success" => false, "error" => $conn->error]);
        exit;
    }
    $stmt->bind_param("issssssisssssssss", $usuario_id, $nombre, $apellido_paterno, $apellido_materno, $apellido_legacy, $telefono, $correo, $alergias, $tipo_id, $origen, $comentarios, $fecha_nacimiento, $tel_emergencia, $motivo_consulta, $medicamentos, $direccion, $rfc);
    if ($stmt->execute()) {
        $paciente_id = $conn->insert_id;
        echo json_encode(["success" => true, "id" => $paciente_id, "nombre" => $nombre]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["success" => false, "error" => "Nombre, Apellido Paterno y Teléfono son requeridos"]);
}
?>