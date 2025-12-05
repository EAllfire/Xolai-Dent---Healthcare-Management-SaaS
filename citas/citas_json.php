<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("../includes/db.php");
header('Content-Type: application/json; charset=utf-8');

$sql = "SELECT
    c.id, c.fecha, c.hora_inicio, c.hora_fin, c.modalidad_id, c.estado_id, e.nombre AS estado, c.nota_interna,
    p.nombre AS paciente, p.telefono, p.alergias AS diagnostico,
    s.nombre AS servicio,
    atp.nombre AS tipo_paciente
  FROM agenda_citas c
  LEFT JOIN portal_pacientes p ON c.paciente_id = p.id
  LEFT JOIN portal_servicios s ON c.servicio_id = s.id
  LEFT JOIN agenda_estado_cita e ON c.estado_id = e.id
  LEFT JOIN agenda_tipos_paciente atp ON p.tipo_id = atp.id
  WHERE c.estado_id != 7"; // Excluir citas canceladas


$result = $conn->query($sql);

if ($result === false) {
    http_response_code(500);
    // Use error_log for server-side logging for security
    error_log("SQL Error in citas_json.php: " . $conn->error);
    // Provide a generic error to the client
    echo json_encode(['error' => 'Ocurrió un error al consultar la base de datos.']);
    exit;
}

$eventos = [];

while ($row = $result->fetch_assoc()) {
  $hora_inicio = $row['hora_inicio'] ?? '';
  $hora_fin = $row['hora_fin'] ?? '';
  if (!$hora_fin && $hora_inicio) {
    $hora = strtotime($hora_inicio);
    // Default duration: 30 minutes, this should be defined by the service
    $hora_fin = date('H:i:s', $hora + 1800);
  }

  // Lógica para asignar colores según el estado de la cita
  $color = '#2196F3'; // Color por defecto (azul para 'reservado' o desconocido)
  $estado_lower = isset($row['estado']) ? strtolower($row['estado']) : '';
  
  $colores_map = [
      'reservado'  => '#2196F3', // Azul
      'confirmado' => '#FF9800', // Naranja
      'asistió'    => '#E91E63', // Rosa
      'no asistió' => '#FF7F50', // Coral
      'pendiente'  => '#F44336', // Rojo
      'en espera'  => '#4CAF50', // Verde
      'cancelada'  => 'rgba(189, 195, 199, 0.4)', // Gris muy claro y semitransparente
      'bloqueado'  => '#a9a9a9'  // Gris oscuro estándar (DarkGray)
  ];

  if (isset($colores_map[$estado_lower])) {
      $color = $colores_map[$estado_lower];
  }
  
  $evento = [
    'id' => $row['id'],
    'start' => $row['fecha']."T".$hora_inicio,
    'end' => $row['fecha']."T".$hora_fin,
    'resourceId' => $row['modalidad_id'],
    'color' => $color,
    'extendedProps' => [
        'estado' => $row['estado'],
        'estado_id' => $row['estado_id'], // <-- Añadido aquí
        'telefono' => $row['telefono'],
        'diagnostico' => $row['diagnostico'],
        'servicio' => $row['servicio'],
        'tipo_paciente' => $row['tipo_paciente'] ?? 'No especificado',
        'motivo' => $row['nota_interna'] ?? ''
    ]
  ];

  if ($estado_lower === 'bloqueado') {
      $evento['title'] = !empty($row['nota_interna']) ? $row['nota_interna'] : 'Espacio Bloqueado';
  } else {
      $evento['title'] = ($row['paciente'] ?? 'Paciente no encontrado') . " (" . ($row['servicio'] ?? 'Servicio no encontrado') . ")";
  }

  $eventos[] = $evento;
}

echo json_encode($eventos);
// no closing PHP tag to avoid accidental trailing whitespace