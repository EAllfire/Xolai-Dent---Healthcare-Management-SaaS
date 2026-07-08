<?php
ini_set('display_errors', 0); // Evitar que advertencias rompan el JSON
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start();
require_once("../includes/db.php");
header('Content-Type: application/json');

// Check if the database connection was successful
if ($conn->connect_error) {
    throw new Exception("Database connection failed: " . $conn->connect_error);
}

try {
    // Obtener el ID del usuario que está creando el paciente desde la sesión.
    $usuario_id_real = $_SESSION['usuario_id'] ?? null;
    $usuario_creador_id = $_SESSION['id_padre'] ?? $usuario_id_real;
    if (!$usuario_creador_id) throw new Exception("Sesión expirada. Por favor inicie sesión.");
    
    $user_tipo = $_SESSION['usuario_tipo'] ?? '';
    $es_padre = empty($_SESSION['id_padre']); // Si es null, el usuario es el titular/padre

    $nombre = trim($_POST['nombre'] ?? '');
    $apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
    $apellido_materno = trim($_POST['apellido_materno'] ?? '');
    $telefono = $_POST['telefono'] ?? '';
    $correo = $_POST['correo'] ?? '';
    
    // Separar los campos para que no se mezclen
    $diagnostico_input = $_POST['diagnostico'] ?? ''; 
    $alergias = $_POST['alergias'] ?? '';

    // El formulario de catálogo envía 'tipo_id'
    $tipo_id = !empty($_POST['tipo_id']) ? (int)$_POST['tipo_id'] : null;
    $origen = $_POST['origen'] ?? '';
    $comentarios = $_POST['comentarios'] ?? '';
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $tel_emergencia = $_POST['tel_emergencia'] ?? '';
    $motivo_consulta = !empty($_POST['motivo_consulta']) ? $_POST['motivo_consulta'] : $diagnostico_input;
    $medicamentos = $_POST['medicamentos'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $rfc = $_POST['rfc'] ?? '';
    $recomendado_por_id = !empty($_POST['recomendado_por_id']) ? (int)$_POST['recomendado_por_id'] : null;

    if ($nombre && $apellido_paterno && $telefono) {
        // Solo admin o dentista padre pueden asignar el médico que recomendó
        if (!($user_tipo === 'admin' || $user_tipo === 'superadmin' || ($user_tipo === 'dentista' && $es_padre))) {
            $recomendado_por_id = null;
        }

        // Se eliminó la columna 'apellido' (legacy) para usar solo los campos desglosados
        $stmt = $conn->prepare("INSERT INTO portal_pacientes (usuario_id, nombre, apellido_paterno, apellido_materno, telefono, correo, alergias, tipo_id, origen, comentarios, fecha_nacimiento, tel_emergencia, motivo_consulta, medicamentos, direccion, rfc, recomendado_por_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }

        // Se requieren 17 tipos para 17 variables: issssssissssssssi
        $stmt->bind_param("issssssissssssssi", 
            $usuario_creador_id,
            $nombre, $apellido_paterno, $apellido_materno, $telefono, $correo, $alergias, 
            $tipo_id, // Esta variable ya es `null` o `int`
            $origen, $comentarios, $fecha_nacimiento,
            $tel_emergencia, $motivo_consulta, $medicamentos, $direccion, $rfc,
            $recomendado_por_id
        );

        if ($stmt->execute()) {
            $paciente_id = $conn->insert_id;
            echo json_encode(["success" => true, "id" => $paciente_id, "nombre" => $nombre, "apellido_paterno" => $apellido_paterno]);
        } else {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }
        $stmt->close();
    } else {
        throw new Exception("Nombre, Apellido Paterno y Teléfono son requeridos");
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