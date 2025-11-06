<?php
require_once("includes/db.php");

$sql = "SELECT c.id, c.fecha, c.hora_inicio, c.hora_fin, c.modalidad_id, c.estado_id, e.nombre AS estado,
    p.nombre AS paciente, p.tipo AS tipo_paciente, p.telefono, p.alergias AS diagnostico,
    s.nombre AS servicio, s.id AS servicio_id, s.precio
  FROM agenda_citas c
  JOIN portal_pacientes p ON c.paciente_id = p.id
  JOIN portal_servicios s ON c.servicio_id = s.id
  JOIN agenda_estado_cita e ON c.estado_id = e.id";

$result = $conn->query($sql);

$eventos = [];

while ($row = $result->fetch_assoc()) {
  $hora_inicio = $row['hora_inicio'] ?? '';
  $hora_fin = $row['hora_fin'] ?? '';
  if (!$hora_fin && $hora_inicio) {
    $hora = strtotime($hora_inicio);
    $hora_fin = date('H:i:s', $hora + 3600);
  }
    $color = null;
    if (isset($row['estado'])) {
      // Mapear estados a colores (mismo mapeo que actualizar_estado.php)
      $colores_map = [
          'reservado' => '#2196F3',    // Azul
          'confirmado' => '#FF9800',   // Naranja
          'asistió' => '#E91E63',      // Rosa
          'no asistió' => '#FF7F50',   // Coral
          'pendiente' => '#F44336',    // Rojo
          'en espera' => '#4CAF50'     // Verde
      ];
      
      $color = $colores_map[$row['estado']] ?? '#2196F3'; // Por defecto azul
    }
    $eventos[] = [
      'id' => $row['id'],
      'title' => $row['paciente']." (".$row['servicio'].")",
      'start' => $row['fecha']."T".$hora_inicio,
      'end' => $row['fecha']."T".$hora_fin,
      'resourceId' => $row['modalidad_id'],
      'color' => $color,
      'estado' => $row['estado'] ?? '',
      'estado_id' => $row['estado_id'] ?? '',
      'telefono' => $row['telefono'] ?? '',
      'diagnostico' => $row['diagnostico'] ?? '',
      'tipo_paciente' => $row['tipo_paciente'] ?? '',
      'pago' => (isset($row['precio']) && $row['precio'] > 0) ? '$' . number_format($row['precio'], 2) : 'No especificado'
    ];
}

echo json_encode($eventos);
?>