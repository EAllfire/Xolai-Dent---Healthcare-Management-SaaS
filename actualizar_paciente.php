<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("includes/db.php");
header('Content-Type: application/json');

// Inicializar variables
$id = null;
$nombre = '';
$apellido = '';
$telefono = '';
$correo = '';
$diagnostico = '';
$tipo = '';
$origen = '';
$comentarios = '';

// Revisar si el contenido es JSON
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    $nombre = $input['nombre'] ?? '';
    $apellido = $input['apellido'] ?? '';
    $telefono = $input['telefono'] ?? '';
    $correo = $input['correo'] ?? '';
    $diagnostico = $input['diagnostico'] ?? '';
    $tipo = $input['tipo'] ?? '';
    $origen = $input['origen'] ?? '';
    $comentarios = $input['comentarios'] ?? '';
} else { // Asumir que es FormData (x-www-form-urlencoded)
    $id = $_POST['id'] ?? null;
    $nombre = $_POST['nombre'] ?? '';
    $apellido = $_POST['apellido'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $diagnostico = $_POST['diagnostico'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    $origen = $_POST['origen'] ?? '';
    $comentarios = $_POST['comentarios'] ?? '';
}

if ($id && $nombre && $apellido) {
    // MAPEO: diagnostico (local) -> alergias (remoto)
    $stmt = $conn->prepare("UPDATE portal_pacientes SET nombre = ?, apellido = ?, telefono = ?, correo = ?, alergias = ?, tipo = ?, origen = ?, comentarios = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(["success" => false, "error" => "Error en prepare: " . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("ssssssssi", $nombre, $apellido, $telefono, $correo, $diagnostico, $tipo, $origen, $comentarios, $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "id" => $id, "message" => "Paciente actualizado"]);
        } else {
            echo json_encode(["success" => true, "id" => $id, "message" => "No se realizaron cambios"]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Error al ejecutar: " . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["success" => false, "error" => "ID, nombre y apellido son requeridos"]);
}
?>