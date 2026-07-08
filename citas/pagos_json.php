<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$usuario_id_real = $_SESSION['usuario_id'];
$id_propietario = $_SESSION['id_padre'] ?? $usuario_id_real;
$mes_filtro = $_GET['mes'] ?? ''; // Formato YYYY-MM
$estado_filtro = $_GET['estado'] ?? 'todos'; // todos, pendiente, completado

$response = [
    'pagos' => [],
    'stats' => ['recaudado' => 0, 'pendiente' => 0]
];

try {
    // 1. Obtener datos de pacientes y sus expedientes dentales
    // Filtramos por el propietario de la clínica (Dueño o Padre)
    $sql = "SELECT p.id as paciente_id, p.nombre, p.apellido_paterno, p.apellido_materno, p.telefono, 
                   d.registro_pagos_json, d.realized_treatments_json
            FROM portal_pacientes p
            LEFT JOIN agenda_expediente_dentista d ON p.id = d.paciente_id
            WHERE p.usuario_id = ? OR p.usuario_id IN (SELECT id FROM agenda_usuarios WHERE id_padre = ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id_propietario, $id_propietario);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $nombre_completo = trim($row['nombre'] . ' ' . $row['apellido_paterno'] . ' ' . $row['apellido_materno']);
        $abonos = json_decode($row['registro_pagos_json'] ?? '[]', true);
        $realizados = json_decode($row['realized_treatments_json'] ?? '[]', true);
        
        // Construir string de tratamientos registrados para este paciente
        $tratamientos_nombres = [];
        if (is_array($realizados)) {
            foreach ($realizados as $r) {
                $tratamientos_nombres[] = $r['servicio_nombre'] ?? 'Tratamiento';
            }
        }
        $tratamientos_str = implode(', ', array_unique($tratamientos_nombres)) ?: 'Sin tratamientos registrados';

        // A. PROCESAR ABONOS (Pagos realizados)
        if (is_array($abonos)) {
            foreach ($abonos as $p) {
                $fecha_pago = $p['fecha'] ?? '---';
                $monto = (float)($p['pago'] ?? 0);
                $es_bloqueado = (isset($p['bloqueado']) && $p['bloqueado'] == 1);

                // Filtro por mes
                if ($mes_filtro !== '' && strpos($fecha_pago, $mes_filtro) !== 0 && $fecha_pago !== '---') continue;
                
                // Filtro por estado (Abonos bloqueados se consideran 'completado')
                if ($estado_filtro === 'completado' && !$es_bloqueado) continue;
                if ($estado_filtro === 'pendiente' && $es_bloqueado) continue; // Los abonos bloqueados no son pendientes

                $response['pagos'][] = [
                    'paciente' => $nombre_completo,
                    'servicio' => $tratamientos_str,
                    'fecha_pago' => $fecha_pago,
                    'fecha_tratamiento' => $fecha_pago, // Para abonos, la fecha de pago es la más relevante
                    'telefono' => $row['telefono'] ?? '---',
                    'metodo' => $p['metodo'] ?? 'Múltiple',
                    'precio' => $monto,
                    'estado_pago' => $es_bloqueado ? 'completado' : 'pendiente',
                    'bloqueado' => $es_bloqueado ? 1 : 0
                ];

                if ($es_bloqueado) $response['stats']['recaudado'] += $monto;
            }
        }

        // B. PROCESAR DEUDA (Tratamientos realizados vs Abonos)
        $deuda_total_paciente = 0;
        if (is_array($realizados)) {
            foreach ($realizados as $r) $deuda_total_paciente += (float)($r['total'] ?? 0);
        }
        
        $total_pagado_paciente = 0;
        if (is_array($abonos)) {
            foreach ($abonos as $p) $total_pagado_paciente += (float)($p['pago'] ?? 0);
        }

        $pendiente_paciente = $deuda_total_paciente - $total_pagado_paciente;

        if ($pendiente_paciente > 0.01) { // Evitar errores de redondeo
            if ($estado_filtro === 'todos' || $estado_filtro === 'pendiente') {
                // Usar la fecha del primer tratamiento realizado para la antigüedad
                $fecha_t = '---';
                if (is_array($realizados) && !empty($realizados)) {
                    // Buscar la fecha de aplicación más antigua entre los tratamientos realizados
                    $earliest_date = null;
                    foreach ($realizados as $r_item) {
                        if (isset($r_item['fecha_aplicacion']) && $r_item['fecha_aplicacion'] !== '---') {
                            if ($earliest_date === null || $r_item['fecha_aplicacion'] < $earliest_date) $earliest_date = $r_item['fecha_aplicacion'];
                        }
                    }
                    $fecha_t = $earliest_date ?? '---';
                }

                $response['pagos'][] = [
                    'paciente' => $nombre_completo,
                    'servicio' => $tratamientos_str,
                    'fecha_pago' => '---',
                    'fecha_tratamiento' => $fecha_t,
                    'telefono' => $row['telefono'] ?? '---',
                    'metodo' => '---',
                    'precio' => $pendiente_paciente,
                    'estado_pago' => 'pendiente',
                    'bloqueado' => 0
                ];
                $response['stats']['pendiente'] += $pendiente_paciente;
            }
        }
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}