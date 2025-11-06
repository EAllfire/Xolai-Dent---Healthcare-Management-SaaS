<?php
require_once("../../includes/db.php");
header('Content-Type: application/json');

// Tu consulta SQL debe devolver los campos necesarios
$sql = "SELECT c.id, c.fecha, c.hora_inicio, c.hora_fin, c.modalidad_id, c.estado_id, e.nombre AS estado,
    p.nombre AS paciente, p.tipo AS tipo_paciente, p.telefono, p.alergias AS diagnostico, 
    s.nombre AS servicio, s.id AS servicio_id, s.precio
  FROM agenda_citas c
  JOIN portal_pacientes p ON c.paciente_id = p.id
  JOIN portal_servicios s ON c.servicio_id = s.id
  JOIN agenda_estado_cita e ON c.estado_id = e.id";
$sql = "SELECT c.id, c.fecha, c.hora_inicio, c.hora_fin, c.modalidad_id, c.estado_id, e.nombre AS estado,
    p.nombre AS paciente, p.tipo AS tipo_paciente, p.telefono, p.diagnostico, 
    s.nombre AS servicio, s.id AS servicio_id, s.precio
  FROM citas c
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
  // Colores por estado


  //QUITAR ALGUNOS COLORES QUE NO SE NECESITAN
  
  $color = null;
  switch (strtolower($row['estado'])) {
    case 'reservado':    $color = '#2196F3'; break;  // Azul
    case 'confirmado':   $color = '#FF9800'; break;  // Naranja
    case 'asistió':      $color = '#E91E63'; break;  // Rosa
    case 'no asistió':   $color = '#FF7F50'; break;  // Coral
    case 'pendiente':    $color = '#F44336'; break;  // Rojo
    case 'en espera':    $color = '#4CAF50'; break;  // Verde 
    default:             $color = '#9E9E9E'; break;  // Gris para otros/indefinidos
  }
  $eventos[] = [
    'id' => $row['id'],
    'title' => $row['paciente']." (".$row['servicio'].")",
    'start' => $row['fecha']."T".$hora_inicio,
    'end' => $row['fecha']."T".$hora_fin,
    'resourceId' => $row['modalidad_id'], // El id de la modalidad
    'estado_id' => isset($row['estado_id']) ? $row['estado_id'] : null,
    'estado' => $row['estado'],
    'color' => $color,
    'extendedProps' => [
      'tipo_paciente' => $row['tipo_paciente'] ?? '',
      'telefono' => $row['telefono'] ?? '',
      'diagnostico' => $row['diagnostico'] ?? '',
      'pago' => $row['precio'] ?? 'No especificado'
    ]
  ];
}

echo json_encode($eventos);
?>