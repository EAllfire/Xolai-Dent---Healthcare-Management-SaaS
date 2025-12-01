<?php
require_once __DIR__ . "/includes/db.php";
header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT c.id, c.fecha, c.hora_inicio, c.hora_fin, c.modalidad_id, c.estado_id, e.nombre AS estado,
    p.nombre AS paciente, p.tipo AS tipo_paciente, p.telefono, p.alergias AS diagnostico,
    s.nombre AS servicio, s.id AS servicio_id, s.precio
  FROM agenda_citas c
  LEFT JOIN portal_pacientes p ON c.paciente_id = p.id
  LEFT JOIN portal_servicios s ON c.servicio_id = s.id
  LEFT JOIN agenda_estado_cita e ON c.estado_id = e.id";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al preparar la consulta: ' . $conn->error]);
    exit;
}

$stmt->execute();

$eventos = [];

// Vincular variables a las columnas del resultado
$row = [];
$meta = $stmt->result_metadata();
$bind_params = [];
while ($field = $meta->fetch_field()) {
    $bind_params[] = &$row[$field->name];
}
call_user_func_array([$stmt, 'bind_result'], $bind_params);

while ($stmt->fetch()) {    
    // Crear una copia del array para no sobreescribir las referencias.
    // Esto es crucial cuando se usa bind_result en un bucle.
    $current_row = array_map(function($x) { return $x; }, $row);

    $hora_inicio = $current_row['hora_inicio'] ?? '';
    $hora_fin = $current_row['hora_fin'] ?? '';
    if (empty($hora_fin) && !empty($hora_inicio)) {
        $hora_fin = date('H:i:s', strtotime($hora_inicio) . ' + 30 minutes');
    }

    $color = '#2196F3'; // Color por defecto (azul)

    if (isset($current_row['estado'])) {
        $colores_map = [
            'reservado' => '#2196F3',     // Azul
            'confirmado' => '#FF9800',   // Naranja
            'asistió' => '#E91E63',      // Rosa
            'no asistió' => '#FF7F50',   // Coral
            'pendiente' => '#F44336',    // Rojo
            'en espera' => '#4CAF50',     // Verde
            'cancelada' => '#797a79ff' //Gris
        ];
        
        $estado_lower = strtolower($current_row['estado']);
        if (isset($colores_map[$estado_lower])) {
            $color = $colores_map[$estado_lower];
        }
    }

    $eventos[] = [
        'id' => $current_row['id'],
        'title' => ($current_row['paciente'] ?? 'Paciente no encontrado') . " (" . ($current_row['servicio'] ?? 'Servicio no encontrado') . ")",
        'start' => $current_row['fecha'] . "T" . $hora_inicio,
        'end' => $current_row['fecha'] . "T" . $hora_fin,
        'resourceId' => $current_row['modalidad_id'],
        'color' => $color,
        'extendedProps' => [
            'estado' => $current_row['estado'] ?? 'desconocido',
            'estado_id' => $current_row['estado_id'] ?? '',
            'telefono' => $current_row['telefono'] ?? '',
            'diagnostico' => $current_row['diagnostico'] ?? '',
            'tipo_paciente' => $current_row['tipo_paciente'] ?? '',
            'pago' => (isset($current_row['precio']) && $current_row['precio'] > 0) ? '$' . number_format($current_row['precio'], 2) : 'No especificado'
        ]
    ];
}

$stmt->close();
echo json_encode($eventos);
?>