<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("includes/db.php");
header('Content-Type: application/json');

$nombre = $_POST['nombre'] ?? '';
$apellido = $_POST['apellido'] ?? '';
$telefono = $_POST['telefono'] ?? '';
$correo = $_POST['correo'] ?? '';
$diagnostico = $_POST['diagnostico'] ?? '';
$estado_id = $_POST['estado_id'] ?? null;
$origen = $_POST['origen'] ?? '';
$comentarios = $_POST['comentarios'] ?? '';
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';

error_log('guardar_paciente.php datos recibidos: ' . json_encode($_POST));

if ($nombre && $apellido) {
    // MAPEO: diagnostico (local) -> alergias (remoto) para compatibilidad con portal_pacientes
    $stmt = $conn->prepare("INSERT INTO portal_pacientes (nombre, apellido, telefono, correo, alergias, estado_id, origen, comentarios, fecha_nacimiento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(["success" => false, "error" => $conn->error]);
        exit;
    }
    $stmt->bind_param("sssssisss", $nombre, $apellido, $telefono, $correo, $diagnostico, $estado_id, $origen, $comentarios, $fecha_nacimiento);
    if ($stmt->execute()) {
        $paciente_id = $conn->insert_id;
        echo json_encode(["success" => true, "id" => $paciente_id, "nombre" => $nombre, "apellido" => $apellido]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["success" => false, "error" => "Nombre y apellido requeridos"]);
}
?>