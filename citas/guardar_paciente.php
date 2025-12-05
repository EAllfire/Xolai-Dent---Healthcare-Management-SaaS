<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("../includes/db.php");
header('Content-Type: application/json');

try {
    $nombre = $_POST['nombre'] ?? '';
    $apellido = $_POST['apellido'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $diagnostico = $_POST['diagnostico'] ?? ''; // Mapeado a 'alergias' en la BD
    // Asegurarse de que el tipo_id sea un entero o null, no una cadena vacía.
    $tipo_id = !empty($_POST['estado_id']) ? (int)$_POST['estado_id'] : null;
    $origen = $_POST['origen'] ?? '';
    $comentarios = $_POST['comentarios'] ?? '';
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;

    error_log('guardar_paciente.php datos recibidos: ' . json_encode($_POST));

    if ($nombre && $apellido) {
        // MAPEO: diagnostico (local) -> alergias (remoto) para compatibilidad con portal_pacientes
        // Se añade fecha_nacimiento y se corrige el campo 'tipo' por 'tipo_id'
        $stmt = $conn->prepare("INSERT INTO portal_pacientes (nombre, apellido, telefono, correo, alergias, tipo_id, origen, comentarios, fecha_nacimiento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }

        // Corrección: bind_param no maneja bien `null` directamente en todas las versiones.
        // Es más seguro asignar los parámetros a variables y luego pasarlas por referencia.
        $stmt->bind_param("sssssisss", 
            $nombre, $apellido, $telefono, $correo, $diagnostico, 
            $tipo_id, // Esta variable ya es `null` o `int`
            $origen, $comentarios, 
            $fecha_nacimiento
        );

        if ($stmt->execute()) {
            $paciente_id = $conn->insert_id;
            echo json_encode(["success" => true, "id" => $paciente_id, "nombre" => $nombre, "apellido" => $apellido]);
        } else {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }
        $stmt->close();
    } else {
        throw new Exception("Nombre y apellido requeridos");
    }
} catch (Exception $e) {
    // Enviar respuesta JSON con el error
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}

// Cerrar la conexión
$conn->close();
?>