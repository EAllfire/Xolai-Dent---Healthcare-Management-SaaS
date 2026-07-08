<?php
header('Content-Type: application/json; charset=utf-8');

// --- MANEJO DE ERRORES ROBUSTO ---
ini_set('display_errors', 0); // No mostrar errores en la salida directa
error_reporting(E_ALL);
set_exception_handler(function ($exception) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Excepción no capturada: ' . $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);
    exit;
});

try {
    require_once __DIR__ . "/includes/db.php";
    require_once __DIR__ . "/includes/auth.php";
    session_start();

    $usuario_id_real = $_SESSION['usuario_id'] ?? 0;
    $usuario_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';
    $allowed = obtenerIdsPermitidos();

    $periodo = $_GET['periodo'] ?? 'all';
    $where_conditions = [];
    $params = [];
    $types = '';

    if ($periodo === 'today') {
        $where_conditions[] = "c.fecha = CURDATE()";
    } elseif ($periodo === 'week') {
        $where_conditions[] = "YEARWEEK(c.fecha, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($periodo === 'custom_week' && isset($_GET['fecha'])) {
        $fecha_seleccionada = $_GET['fecha'];
        if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $fecha_seleccionada)) {
            $where_conditions[] = "YEARWEEK(c.fecha, 1) = YEARWEEK(?, 1)";
            $params[] = $fecha_seleccionada;
            $types .= 's';
        }
    }
    // Si es 'all', no se añade cláusula WHERE y se obtienen todas las citas.

    // Construir la cláusula WHERE solo si hay condiciones
    $whereSQL = "";
    if (!empty($where_conditions)) {
        $whereSQL = "WHERE " . implode(' AND ', $where_conditions);
    }

    // Aplicar filtro por propietario/clínica según helper
    if ($allowed === null) {
        // no extra filter
    } elseif (is_array($allowed) && in_array('PARENT_ONLY', $allowed)) {
        $parent = $_SESSION['id_padre'] ?? null;
        if ($parent) {
            $whereSQL = ($whereSQL ? $whereSQL . ' AND ' : 'WHERE ') . "c.usuario_id = " . intval($parent);
        } else {
            // fallback: no results
            echo json_encode([]); exit;
        }
    } elseif (is_array($allowed) && in_array('SELF_AND_CHILDREN', $allowed)) {
        $self = $_SESSION['usuario_id'] ?? 0;
        $whereSQL = ($whereSQL ? $whereSQL . ' AND ' : 'WHERE ') . "(c.usuario_id = " . intval($self) . " OR c.usuario_id IN (SELECT id FROM agenda_usuarios WHERE id_padre = " . intval($self) . "))";
    } elseif (is_array($allowed) && count($allowed) > 0) {
        $ids = implode(',', array_map('intval', $allowed));
        $whereSQL = ($whereSQL ? $whereSQL . ' AND ' : 'WHERE ') . "c.usuario_id IN ($ids)";
    }

    // Consulta SQL ajustada y más segura, similar a la de citas_json.php
    $sql = "
        SELECT 
            c.id, 
            c.fecha, 
            c.hora_inicio, 
            c.hora_fin,
            c.tipo,
            c.url_identificacion,
            c.url_orden_medica,
            p.nombre AS paciente_nombre,
            p.apellido_paterno,
            p.apellido_materno,
            p.apellido AS paciente_apellido,
            p.origen AS paciente_origen,
            s.nombre AS servicio_nombre,
            m.nombre AS modalidad_nombre,
            e.nombre AS estado_nombre,
            u.nombre AS medico_nombre,
            u_rec.nombre AS recomendado_nombre
        FROM 
            agenda_citas c
        LEFT JOIN 
            portal_pacientes p ON c.paciente_id = p.id
        LEFT JOIN 
            portal_servicios s ON c.servicio_id = s.id
        LEFT JOIN 
            agenda_modalidades m ON c.modalidad_id = m.id
        LEFT JOIN 
            agenda_estado_cita e ON c.estado_id = e.id
        LEFT JOIN
            agenda_usuarios u ON c.profesional_id = u.id
        LEFT JOIN
            agenda_usuarios u_rec ON p.recomendado_por_id = u_rec.id
        $whereSQL
        ORDER BY 
            c.fecha DESC, c.hora_inicio DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        throw new Exception('Error en la consulta a la base de datos: ' . $conn->error);
    }

    $citas = [];
    while ($row = $result->fetch_assoc()) {
        // Asignar color basado en el nombre del estado, igual que en citas_json.php
        $colores_map = [
            'reservado' => '#2196F3',
            'confirmado' => '#FF9800',
            'asistió' => '#E91E63',
            'no asistió' => '#FF7F50',
            'pendiente' => '#F44336',
            'en espera' => '#4CAF50',
            'cancelada' => '#797a79ff'
        ];
        $estado_lower = strtolower($row['estado_nombre'] ?? '');
        $row['estado_color'] = $colores_map[$estado_lower] ?? '#6c757d'; // Gris por defecto

        // Crear nombre completo del paciente
        $ap_p = $row['apellido_paterno'] ?: ($row['paciente_apellido'] ?? '');
        $ap_m = $row['apellido_materno'] ?? '';
        $row['paciente_nombre_completo'] = trim(($row['paciente_nombre'] ?? '') . ' ' . $ap_p . ' ' . $ap_m);
        
        $citas[] = $row;
    }

    echo json_encode($citas);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en el servidor: ' . $e->getMessage()]);
}
?>