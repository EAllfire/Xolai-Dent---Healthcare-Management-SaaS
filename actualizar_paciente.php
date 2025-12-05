<?php
// --- MODO DEBUG ---
// Cambia a `true` para ver los logs en pantalla. En producción, debe ser `false`.
define('DEBUG_MODE', true);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("../includes/db.php"); // This path is correct if 'citas' is inside 'Agenda'
header('Content-Type: application/json; charset=utf-8');
require_once('../debug_helper.php'); // CORRECCIÓN: El helper está en la carpeta 'agenda', un nivel arriba.

$input = json_decode(file_get_contents('php://input'), true);

$id = $input['id'] ?? null;
$nombre = $input['nombre'] ?? '';
$apellido = $input['apellido'] ?? '';
$telefono = $input['telefono'] ?? '';
$correo = $input['correo'] ?? '';
$diagnostico = $input['diagnostico'] ?? '';
$tipo_id = $input['estado_id'] ?? null; // El formulario envía 'estado_id', que corresponde a 'tipo_id' en la BD.
$origen = $input['origen'] ?? 'externo';
$comentarios = $input['comentarios'] ?? '';
$fecha_nacimiento = !empty($input['fecha_nacimiento']) ? $input['fecha_nacimiento'] : null; // CORRECCIÓN: Usar null si está vacío.

if (!$id) {
    echo json_encode(["success" => false, "error" => "ID de paciente no proporcionado."]);
    exit;
}

if ($nombre && $apellido) {
    $sql = "UPDATE portal_pacientes SET 
                nombre = ?, 
                apellido = ?, 
                telefono = ?,
                correo = ?,
                alergias = ?, /* CORRECCIÓN: El nombre de la columna es 'alergias' */
                tipo_id = ?,
                origen = ?, 
                comentarios = ?,
                fecha_nacimiento = ?
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        log_message("Error al preparar la consulta: " . $conn->error);
        echo json_encode(["success" => false, "error" => "Error interno del servidor (prepare)."]);
        print_debug_buffer();
        exit;
    }

    $stmt->bind_param("sssssisssi", $nombre, $apellido, $telefono, $correo, $diagnostico, $tipo_id, $origen, $comentarios, $fecha_nacimiento, $id);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "id" => $id]);
    } else {
        log_message("Error al ejecutar la consulta: " . $stmt->error);
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["success" => false, "error" => "Nombre y apellido son requeridos."]);
}

// Imprime los logs si el modo debug está activado.
print_debug_buffer();
?>