<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

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
    $stmt = $conn->prepare("SELECT nombre, apellido_paterno, apellido_materno, apellido, telefono, correo, fecha_nacimiento, usuario_id, tel_emergencia, rfc, direccion, motivo_consulta, alergias, medicamentos FROM portal_pacientes WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // 3. Devolver los datos o un error si no se encuentra
    if ($paciente = $result->fetch_assoc()) {
        // Verificar permiso de acceso al paciente
        $allowed = obtenerIdsPermitidos();
        if ($allowed === null) {
            // ok
        } elseif (is_array($allowed) && in_array('PARENT_ONLY', $allowed)) {
            $parent = $_SESSION['id_padre'] ?? null;
            if (!$parent || intval($paciente['usuario_id']) !== intval($parent)) {
                http_response_code(403); echo json_encode(['error'=>'Acceso denegado']); exit;
            }
        } elseif (is_array($allowed) && in_array('SELF_AND_CHILDREN', $allowed)) {
            $self = $_SESSION['usuario_id'] ?? 0;
            // permitir si usuario_id == self o usuario_id in (children)
            $owner = intval($paciente['usuario_id']);
            if ($owner !== intval($self)) {
                // comprobar hijos
                $stmtC = $conn->prepare("SELECT COUNT(*) as c FROM agenda_usuarios WHERE id = ? AND id_padre = ?");
                $stmtC->bind_param('ii', $owner, $self);
                $stmtC->execute(); $r = $stmtC->get_result()->fetch_assoc();
                $stmtC->close();
                if (intval($r['c']) === 0) { http_response_code(403); echo json_encode(['error'=>'Acceso denegado']); exit; }
            }
        } elseif (is_array($allowed) && count($allowed) > 0) {
            $owner = intval($paciente['usuario_id']);
            $allowed_ints = array_map('intval', $allowed);
            if (!in_array($owner, $allowed_ints)) { http_response_code(403); echo json_encode(['error'=>'Acceso denegado']); exit; }
        }
        // Combinar nombre y apellido para el campo 'nombre' del formulario
        // y mapear 'correo' a 'email' para que coincida con el JavaScript
        $ap_p = $paciente['apellido_paterno'];
        $ap_m = $paciente['apellido_materno'];
        if (empty($ap_p) && !empty($paciente['apellido'])) { $ap_p = $paciente['apellido']; }

        $response_data = [
            'nombre' => $paciente['nombre'],
            'apellido_paterno' => $ap_p,
            'apellido_materno' => $ap_m,
            'telefono' => $paciente['telefono'],
            'email' => $paciente['correo'], // Mapeo de correo a email
            'fecha_nacimiento' => $paciente['fecha_nacimiento'],
            'tel_emergencia' => $paciente['tel_emergencia'],
            'rfc' => $paciente['rfc'],
            'direccion' => $paciente['direccion'],
            'motivo_consulta' => $paciente['motivo_consulta'],
            'alergias' => $paciente['alergias'],
            'medicamentos' => $paciente['medicamentos']
        ];
        echo json_encode($response_data);
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