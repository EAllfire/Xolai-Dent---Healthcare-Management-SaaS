<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Mexico_City');
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/auth.php";
header('Content-Type: application/json; charset=utf-8');

// Obtener el ID del usuario de la sesión para filtrar las citas.
// Use permission helper to scope visible citas
$usuario_id_real = $_SESSION['usuario_id'] ?? null;
if (!$usuario_id_real) { echo json_encode([]); exit; }

$allowed = obtenerIdsPermitidos();

$sql = "SELECT 
    c.id, c.fecha, c.hora_inicio, c.hora_fin, c.modalidad_id, c.estado_id, e.nombre AS estado, c.nota_interna,
    CONCAT(COALESCE(p.nombre, ''), ' ', COALESCE(p.apellido_paterno, ''), ' ', COALESCE(p.apellido_materno, '')) AS paciente_full,
    c.paciente_nombre_text AS paciente_nombre_text,
    p.telefono AS paciente_telefono,
    c.telefono_celular AS telefono_celular,
    p.alergias AS diagnostico,
    s.nombre AS servicio,
    c.servicio_text AS servicio_text,
    atp.nombre AS tipo_paciente,
    u.nombre AS doctor_nombre
  FROM agenda_citas c
  LEFT JOIN portal_pacientes p ON c.paciente_id = p.id
  LEFT JOIN portal_servicios s ON c.servicio_id = s.id
  LEFT JOIN agenda_estado_cita e ON c.estado_id = e.id
  LEFT JOIN agenda_tipos_paciente atp ON p.tipo_id = atp.id
  LEFT JOIN agenda_usuarios u ON c.profesional_id = u.id
  WHERE c.estado_id != 7";
// Append owner-scoping based on $allowed
if ($allowed === null) {
    // no extra filter
} elseif (is_array($allowed) && in_array('PARENT_ONLY', $allowed)) {
    $parent = $_SESSION['id_padre'] ?? null;
    if ($parent) $sql .= " AND c.usuario_id = " . intval($parent);
    else { echo json_encode([]); exit; }
} elseif (is_array($allowed) && in_array('SELF_AND_CHILDREN', $allowed)) {
    $self = $_SESSION['usuario_id'] ?? 0;
    $sql .= " AND (c.usuario_id = " . intval($self) . " OR c.usuario_id IN (SELECT id FROM agenda_usuarios WHERE id_padre = " . intval($self) . "))";
} elseif (is_array($allowed) && count($allowed) > 0) {
    $ids = implode(',', array_map('intval', $allowed));
    $sql .= " AND c.usuario_id IN ($ids)";
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    error_log("SQL Prepare Error in citas_json.php: " . $conn->error);
    echo json_encode(['error' => 'Error al preparar la consulta de citas.']);
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

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
  
    $paciente_full = trim((string)($row['paciente_full'] ?? ''));
    $paciente_nombre_text = trim((string)($row['paciente_nombre_text'] ?? ''));
    $paciente_nombre = $paciente_full ?: $paciente_nombre_text;
    $servicio = trim((string)($row['servicio'] ?? ''));
    $servicio_text = trim((string)($row['servicio_text'] ?? ''));
    $servicio_nombre = $servicio ?: $servicio_text;
    $telefono = trim((string)($row['paciente_telefono'] ?? '')) ?: trim((string)($row['telefono_celular'] ?? ''));

  $evento = [
    'id' => $row['id'],
    'start' => $row['fecha']."T".$hora_inicio,
    'end' => $row['fecha']."T".$hora_fin,
    // El recurso debe ser la MODALIDAD para que coincida con las columnas del calendario
    'resourceId' => $row['modalidad_id'],
    'color' => $color,
    'extendedProps' => [
        'estado' => $row['estado'],
        'estado_id' => $row['estado_id'], // <-- Añadido aquí
        'telefono' => $telefono,
        'telefono_celular' => $row['telefono_celular'],
        'diagnostico' => $row['diagnostico'],
        'servicio' => $servicio_nombre,
        'servicio_text' => $row['servicio_text'],
        'tipo_paciente' => $row['tipo_paciente'] ?? 'No especificado',
        'motivo' => $row['nota_interna'] ?? '',
        'paciente_full' => $paciente_full,
        'paciente_nombre_text' => $paciente_nombre_text,
        'doctor_nombre' => $row['doctor_nombre'] ?? 'No asignado'
    ]
  ];

  if ($estado_lower === 'bloqueado') {
      $evento['title'] = !empty($row['nota_interna']) ? $row['nota_interna'] : 'Espacio Bloqueado';
  } else {
      $evento['title'] = ($paciente_nombre ?: 'Paciente no encontrado') . " (" . ($servicio_nombre ?: 'Servicio no encontrado') . ")";
  }

  $eventos[] = $evento;
}

echo json_encode($eventos);
// no closing PHP tag to avoid accidental trailing whitespace