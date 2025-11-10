<?php
require_once("includes/db.php");
header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT c.id, c.fecha, c.hora_inicio, c.hora_fin, c.modalidad_id, c.estado_id, e.nombre AS estado,
    p.nombre AS paciente, p.tipo AS tipo_paciente, p.telefono, p.alergias AS diagnostico,
    s.nombre AS servicio, s.id AS servicio_id, s.precio
  FROM agenda_citas c
  LEFT JOIN portal_pacientes p ON c.paciente_id = p.id
  LEFT JOIN portal_servicios s ON c.servicio_id = s.id
  LEFT JOIN agenda_estado_cita e ON c.estado_id = e.id";

$result = $conn->query($sql);

$eventos = [];

while ($row = $result->fetch_assoc()) {
    $hora_inicio = $row['hora_inicio'] ?? '';
    $hora_fin = $row['hora_fin'] ?? '';
    // --- RED DE SEGURIDAD ---
    // Si hora_fin está vacía, calcular 30 minutos por defecto para evitar errores de renderizado.
    if (empty($hora_fin) && !empty($hora_inicio)) {
        $hora_fin = date('H:i:s', strtotime($hora_inicio) + 1800); // 1800 segundos = 30 minutos
    }

    $color = '#2196F3'; // Color por defecto (azul)

    if (isset($row['estado'])) {
        $colores_map = [
            'reservado' => '#2196F3',     // Azul
            'confirmado' => '#FF9800',   // Naranja
            'asistió' => '#E91E63',      // Rosa
            'no asistió' => '#FF7F50',   // Coral
            'pendiente' => '#F44336',    // Rojo
            'en espera' => '#4CAF50'     // Verde
        ];
        
        $estado_lower = strtolower($row['estado']);
        if (isset($colores_map[$estado_lower])) {
            $color = $colores_map[$estado_lower];
        }
    }

    $eventos[] = [
        'id' => $row['id'],
        'title' => ($row['paciente'] ?? 'Paciente no encontrado') . " (" . ($row['servicio'] ?? 'Servicio no encontrado') . ")",
        'start' => $row['fecha'] . "T" . $hora_inicio,
        'end' => $row['fecha'] . "T" . $hora_fin,
        'resourceId' => $row['modalidad_id'],
        'color' => $color,
        'extendedProps' => [
            'estado' => $row['estado'] ?? 'desconocido',
            'estado_id' => $row['estado_id'] ?? '',
            'telefono' => $row['telefono'] ?? '',
            'diagnostico' => $row['diagnostico'] ?? '',
            'tipo_paciente' => $row['tipo_paciente'] ?? '',
            'pago' => (isset($row['precio']) && $row['precio'] > 0) ? '$' . number_format($row['precio'], 2) : 'No especificado'
        ]
    ];
}

echo json_encode($eventos);
?>