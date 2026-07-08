<?php
require_once 'includes/db.php';
header('Content-Type: application/json');

$paciente_id = isset($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;

if ($paciente_id === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de paciente no proporcionado.']);
    exit;
}

try {
    $response = [];

    // 0. Obtener Datos Personales del Paciente
    $stmt_dp = $conn->prepare("SELECT nombre, apellido_paterno, apellido_materno, fecha_nacimiento, curp, telefono, correo, direccion, alergias, motivo_consulta, medicamentos, tel_emergencia, rfc, origen, recomendado_por_id, comentarios FROM portal_pacientes WHERE id = ?");
    $stmt_dp->bind_param("i", $paciente_id);
    $stmt_dp->execute();
    $result_dp = $stmt_dp->get_result();
    $response['datos_personales'] = $result_dp->fetch_assoc();
    $stmt_dp->close();

    // 1. Obtener Historia Clínica
    $stmt = $conn->prepare("SELECT * FROM agenda_expediente_clinico WHERE paciente_id = ?");
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['historia_clinica'] = $result->fetch_assoc();
    $stmt->close();

    // 2. Obtener Signos Vitales
    $stmt = $conn->prepare("SELECT * FROM agenda_signos_vitales WHERE paciente_id = ? ORDER BY fecha_toma DESC LIMIT 20");
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['signos_vitales'] = [];
    while ($row = $result->fetch_assoc()) {
        $response['signos_vitales'][] = $row;
    }
    $stmt->close();

    // 3. Obtener Notas de Evolución
    $stmt = $conn->prepare("
        SELECT n.*, u.nombre as usuario_nombre 
        FROM agenda_notas_evolucion n
        LEFT JOIN agenda_usuarios u ON n.usuario_id = u.id
        WHERE n.paciente_id = ? 
        ORDER BY n.fecha_nota DESC LIMIT 20
    ");
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['notas_evolucion'] = [];
    while ($row = $result->fetch_assoc()) {
        $response['notas_evolucion'][] = $row;
    }
    $stmt->close();

    // 4. Obtener Documentos
    $stmt = $conn->prepare("SELECT * FROM agenda_documentos_paciente WHERE paciente_id = ? ORDER BY fecha_carga DESC");
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['documentos'] = [];
    while ($row = $result->fetch_assoc()) {
        // CORRECCIÓN: Construir una ruta absoluta desde la raíz del sitio web.
        // Esto es más robusto que usar rutas relativas con '../'.
        $row['ruta_archivo'] = '/' . ltrim($row['ruta_archivo'], '/');
        $response['documentos'][] = $row;
    }
    $stmt->close();


    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en get_expediente.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor.']);
}

$conn->close();
?>