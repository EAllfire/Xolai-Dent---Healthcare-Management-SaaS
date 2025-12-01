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

    // --- FILTRO DE PERÍODO ---
    $periodo = $_GET['periodo'] ?? 'all';
    $where_clause = '';

    if ($periodo === 'today') {
        // Filtra por la fecha actual
        $where_clause = " WHERE c.fecha = CURDATE()";
    } elseif ($periodo === 'week') {
        // Filtra por la semana actual (de lunes a domingo)
        // YEARWEEK(fecha, 1) considera que la semana empieza en lunes
        $where_clause = " WHERE YEARWEEK(c.fecha, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($periodo === 'custom_week' && isset($_GET['fecha'])) {
        // Filtra por la semana de la fecha proporcionada
        $fecha_seleccionada = $_GET['fecha'];
        // Validar que la fecha tenga el formato correcto para seguridad
        if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $fecha_seleccionada)) {
            $where_clause = " WHERE YEARWEEK(c.fecha, 1) = YEARWEEK('{$conn->real_escape_string($fecha_seleccionada)}', 1)";
        }
    }
    // Si es 'all', no se añade cláusula WHERE y se obtienen todas las citas.

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
            p.apellido AS paciente_apellido,
            s.nombre AS servicio_nombre,
            m.nombre AS modalidad_nombre,
            e.nombre AS estado_nombre
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
        {$where_clause}
        ORDER BY 
            c.fecha DESC, c.hora_inicio DESC
    ";

    $result = $conn->query($sql);

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
        $row['paciente_nombre_completo'] = trim(($row['paciente_nombre'] ?? '') . ' ' . ($row['paciente_apellido'] ?? ''));
        
        $citas[] = $row;
    }

    echo json_encode($citas);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en el servidor: ' . $e->getMessage()]);
}
?>