<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

// 1. Validar que se reciba un ID de paciente numérico
$paciente_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : 0;

if ($paciente_id === false || $paciente_id <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'ID de paciente no válido.']);
    exit;
}

try {
    // 2. Preparar y ejecutar la consulta para evitar inyección SQL
    // Se usan los campos correctos: nombre, apellido, correo, telefono, fecha_nacimiento
    $stmt = $conn->prepare("SELECT nombre, apellido, telefono, correo, fecha_nacimiento FROM portal_pacientes WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // 3. Devolver los datos o un error si no se encuentra
    if ($paciente = $result->fetch_assoc()) {
        // Combinar nombre y apellido para el campo 'nombre' del formulario
        // y mapear 'correo' a 'email' para que coincida con el JavaScript
        $response_data = [
            'nombre' => trim(($paciente['nombre'] ?? '') . ' ' . ($paciente['apellido'] ?? '')),
            'telefono' => $paciente['telefono'],
            'email' => $paciente['correo'], // Mapeo de correo a email
            'fecha_nacimiento' => $paciente['fecha_nacimiento']
        ];
        echo json_encode($response_data);
        echo json_encode($paciente);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Paciente no encontrado.']);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    error_log("Error en paciente_por_id.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor al buscar el paciente.']);
}
?>