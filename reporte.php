<?php
session_start();
require_once 'includes/db.php';

// Simple API endpoints inside the same file for AJAX
$action = $_GET['action'] ?? '';

if (!empty($action)) {
    header('Content-Type: application/json');
    
    $usuario_id_real = $_SESSION['usuario_id'] ?? 0;
    $id_propietario = $_SESSION['id_padre'] ?? $usuario_id_real;
    $usuario_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';

    // Todo usuario que no sea Superadmin debe estar filtrado por su ID de propietario (Clínica)
    $aplicar_filtro = ($usuario_tipo !== 'superadmin');

    if (!$usuario_id_real) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    try {
        if ($action === 'stats') {
            // --- Lógica de Rango de Fechas Mejorada ---
            $period = $_GET['period'] ?? 'week';
            $is_custom = ($period === 'custom');
            $where_clause = "fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()"; // Default: week

            switch ($period) {
                case 'today':
                    $where_clause = "fecha = CURDATE()";
                    break;
                case 'week':
                    $where_clause = "fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
                    break;
                case 'month':
                    // Usamos el primer día del mes actual para mayor precisión
                    $where_clause = "fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND fecha <= CURDATE()";
                    break;
                case '3months':
                    $where_clause = "fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 3 MONTH) AND CURDATE()";
                    break;
                case '6months':
                    $where_clause = "fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND CURDATE()";
                    break;
                case 'thisyear':
                    $where_clause = "YEAR(fecha) = YEAR(CURDATE())";
                    break;
                case 'custom':
                    $unit = $_GET['unit'] ?? 'day';
                    $value = (int)($_GET['value'] ?? 1);
                    // Validar unidad para seguridad
                    $allowed_units = ['day', 'week', 'month', 'year'];
                    $unit = in_array($unit, $allowed_units) ? $unit : 'day';
                    $where_clause = "fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL {$value} {$unit}) AND CURDATE()";
                    break;
                case 'specific_day':
                    $day = $_GET['day'] ?? date('Y-m-d');
                    $where_clause = "fecha = '{$day}'";
                    break;
                case 'specific_month':
                    $month = $_GET['month'] ?? date('Y-m');
                    $where_clause = "DATE_FORMAT(fecha, '%Y-%m') = '{$month}'";
                    break;
                case 'specific_week':
                    $week = $_GET['week'] ?? date('Y-\WW');
                    $year = substr($week, 0, 4);
                    $week_num = substr($week, 6, 2);
                    $start_of_week = date('Y-m-d', strtotime("{$year}-W{$week_num}-1")); // 1 for Monday
                    $end_of_week = date('Y-m-d', strtotime("{$year}-W{$week_num}-7"));   // 7 for Sunday
                    $where_clause = "fecha BETWEEN '{$start_of_week}' AND '{$end_of_week}'";
                    break;
            }

            // Lógica para Super Admin: ver todo
            $sql = "SELECT COUNT(*) as total FROM agenda_citas WHERE " . $where_clause;
            if ($aplicar_filtro) {
                $sql .= " AND usuario_id = " . (int)$id_propietario;
            }

            $res = $conn->query($sql);
            $total = $res ? $res->fetch_assoc()['total'] : 0;

            // --- INICIO: Lógica para contar WPP ---
            $wpp_enviados = 0;
            $log_path = __DIR__ . "/includes/correo_logs/correo_log.txt"; // Ajustar si es necesario
            if (file_exists($log_path) && is_readable($log_path)) {
                // Usamos una forma eficiente de leer el archivo para no cargarlo todo en memoria
                $handle = fopen($log_path, "r");
                if ($handle) {
                    // Convertir el período a fechas de inicio y fin para la comparación
                    $start_date = new DateTime();
                    $end_date = new DateTime();
                    if ($period === 'today') { $start_date->setTimestamp(strtotime('today')); $end_date->setTimestamp(strtotime('tomorrow')-1); }
                    else if ($period === 'week') { $start_date->setTimestamp(strtotime('-6 days')); $end_date->setTimestamp(strtotime('tomorrow')-1); }
                    else if ($period === 'month') { $start_date = new DateTime('first day of this month'); $end_date = new DateTime('now'); }
                    else if ($period === '3months') { $start_date->modify('-3 months'); $end_date = new DateTime('now'); }
                    else if ($period === '6months') { $start_date->modify('-6 months'); $end_date = new DateTime('now'); }
                    else if ($period === 'thisyear') { $start_date = new DateTime('first day of January this year'); $end_date = new DateTime('now'); }
                    else if ($is_custom) {
                        $unit = $_GET['unit'] ?? 'day';
                        $value = (int)($_GET['value'] ?? 1);
                        $allowed_units = ['day', 'week', 'month', 'year'];
                        $unit = in_array($unit, $allowed_units) ? $unit : 'day';
                        $start_date->modify("-{$value} {$unit}");
                        $end_date = new DateTime('now');
                    } else if ($period === 'specific_day') {
                        $day = $_GET['day'] ?? date('Y-m-d');
                        $start_date = new DateTime($day);
                        $end_date = new DateTime($day);
                    } else if ($period === 'specific_month') {
                        $month = $_GET['month'] ?? date('Y-m');
                        $start_date = new DateTime("first day of {$month}");
                        $end_date = new DateTime("last day of {$month}");
                    } else if ($period === 'specific_week') {
                        $week = $_GET['week'] ?? date('Y-\WW');
                        $year = substr($week, 0, 4);
                        $week_num = substr($week, 6, 2);
                        $start_date = new DateTime();
                        $start_date->setISODate($year, $week_num, 1); // Monday
                        $end_date = (clone $start_date)->modify('+6 days'); // Sunday
                    }
                    // Normalizar fechas para no incluir la hora
                    $start_date->setTime(0,0,0);
                    $end_date->setTime(23,59,59);
                    
                    while (($line = fgets($handle)) !== false) {
                        // Extraer la fecha del log y comparar con el rango del reporte
                        if (preg_match('/^\[(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
                            try {
                            $log_date = new DateTime($matches[1]);
                            if ($log_date >= $start_date && $log_date <= $end_date) {
                                if (strpos($line, '[WPP] RESPUESTA:') !== false && strpos($line, '"message_status":"accepted"') !== false) {
                                    $wpp_enviados++;
                                }
                            }
                            } catch (Exception $e) { continue; }
                        }
                    }
                    fclose($handle);
                }
            }
            // --- FIN: Lógica para contar WPP ---

            // --- INICIO: Cálculo del Factor de Ocupación (Promedio de citas por día) ---
            $start_date_dt = new DateTime();
            $end_date_dt = new DateTime();

            if ($is_custom) {
                $unit = $_GET['unit'] ?? 'day';
                $value = (int)($_GET['value'] ?? 1);
                $allowed_units = ['day', 'week', 'month', 'year'];
                $unit = in_array($unit, $allowed_units) ? $unit : 'day';
                $start_date_dt->modify("-{$value} {$unit}");
            } else {
                if ($period === 'today') { $start_date_dt->setTimestamp(strtotime('today')); }
                else if ($period === 'week') { $start_date_dt->setTimestamp(strtotime('-6 days')); }
                else if ($period === 'month') { $start_date_dt = new DateTime('first day of this month'); }
                else if ($period === '3months') { $start_date_dt->modify('-3 months'); }
                else if ($period === '6months') { $start_date_dt->modify('-6 months'); }
                else if ($period === 'thisyear') { $start_date_dt = new DateTime('first day of January this year'); }
            }

            if ($period === 'specific_day') {
                $day = $_GET['day'] ?? date('Y-m-d');
                $start_date_dt = new DateTime($day);
            } else if ($period === 'specific_month') {
                $month = $_GET['month'] ?? date('Y-m');
                $start_date_dt = new DateTime("first day of {$month}");
                $end_date_dt = new DateTime("last day of {$month}");
            } else if ($period === 'specific_week') {
                $week = $_GET['week'] ?? date('Y-\WW');
                $year = substr($week, 0, 4);
                $week_num = substr($week, 6, 2);
                $start_date_dt = new DateTime();
                $start_date_dt->setISODate($year, $week_num, 1);
                $end_date_dt = (clone $start_date_dt)->modify('+6 days');
            }

            // Aseguramos que la hora no afecte el conteo de días
            $dias_en_periodo = $end_date_dt->diff($start_date_dt)->days + 1;

            $factor = ($dias_en_periodo > 0 && $total > 0) ? round($total / $dias_en_periodo, 2) : 0;
            $factor_diff = 0; // El cálculo de diferencia con período anterior se omite por simplicidad
            // --- FIN: Cálculo del Factor de Ocupación ---

            $sql2 = "SELECT COUNT(DISTINCT paciente_id) as nuevos FROM agenda_citas WHERE " . $where_clause;
            if ($aplicar_filtro) {
                $sql2 .= " AND usuario_id = ?";
            }
            $stmt2 = $conn->prepare($sql2);
            if ($aplicar_filtro) $stmt2->bind_param("i", $id_propietario);
            $stmt2->execute();
            $stmt2->bind_result($nuevos);
            $stmt2->fetch();
            $stmt2->close();
            $nuevos_diff = -8.53;

            // Obtener el total recaudado real para el reporte
            $sql_pagos = "SELECT SUM(COALESCE(s.precio, 0)) as total 
                          FROM agenda_citas c 
                          JOIN portal_servicios s ON c.servicio_id = s.id 
                          WHERE c.estado_pago = 'completado' AND " . $where_clause;
            if ($aplicar_filtro) {
                $sql_pagos .= " AND c.usuario_id = " . (int)$id_propietario;
            }
            $res_pagos = $conn->query($sql_pagos);
            $row_pagos = $res_pagos ? $res_pagos->fetch_assoc() : null;
            $pagos_online = (float)($row_pagos['total'] ?? 0);

            // --- INICIO: Lógica para contar citas canceladas ---
            $sql_canceladas = "SELECT COUNT(*) as canceladas FROM agenda_citas WHERE estado_id = 7 AND " . $where_clause;
            if ($aplicar_filtro) {
                $sql_canceladas .= " AND usuario_id = ?";
            }
            $stmt_canceladas = $conn->prepare($sql_canceladas);
            if ($aplicar_filtro) $stmt_canceladas->bind_param("i", $id_propietario);
            $stmt_canceladas->execute();
            $stmt_canceladas->bind_result($total_canceladas);
            $stmt_canceladas->fetch();
            $stmt_canceladas->close();
            // --- FIN: Lógica para contar citas canceladas ---

            echo json_encode([
                'success' => true,
                'total' => $total,
                'factor' => $factor,
                'factor_diff' => $factor_diff,
                'nuevos' => $nuevos,
                'nuevos_diff' => $nuevos_diff,
                'pagos_online' => $pagos_online,
                'wpp_enviados' => $wpp_enviados, // Devolvemos el nuevo valor
                'canceladas' => $total_canceladas
            ]);
            exit;
        }

        if ($action === 'states_by_count') {
            $sql = "SELECT ec.nombre, COUNT(*) as cantidad FROM agenda_citas c LEFT JOIN agenda_estado_cita ec ON c.estado_id = ec.id WHERE c.fecha = CURDATE()";
            if ($aplicar_filtro) {
                $sql .= " AND c.usuario_id = ?";
            }
            $sql .= " GROUP BY ec.nombre";
            $stmt = $conn->prepare($sql);
            if ($aplicar_filtro) $stmt->bind_param("i", $id_propietario);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res === false) throw new Exception("Query failed: " . $conn->error);
            $out = [];
            while ($r = $res->fetch_assoc()) {
                $out[] = $r;
            }
            echo json_encode(['success'=>true,'data'=>$out]);
            exit;
        }

        if ($action === 'reservas_today') {
            // --- Lógica de Rango de Fechas Mejorada ---
            $period = $_GET['period'] ?? 'today';
            $where_clause = "c.fecha = CURDATE()"; // Default: today

            switch ($period) {
                case 'today':
                    $where_clause = "c.fecha = CURDATE()";
                    break;
                case 'week':
                    $where_clause = "c.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
                    break;
                case 'month':
                    $where_clause = "c.fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND c.fecha <= CURDATE()";
                    break;
                case '3months':
                    $where_clause = "c.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 3 MONTH) AND CURDATE()";
                    break;
                case '6months':
                    $where_clause = "c.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND CURDATE()";
                    break;
                case 'thisyear':
                    $where_clause = "YEAR(c.fecha) = YEAR(CURDATE())";
                    break;
                case 'custom':
                    $unit = $_GET['unit'] ?? 'day';
                    $value = (int)($_GET['value'] ?? 1);
                    $allowed_units = ['day', 'week', 'month', 'year'];
                    $unit = in_array($unit, $allowed_units) ? $unit : 'day';
                    $where_clause = "c.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL {$value} {$unit}) AND CURDATE()";
                    break;
                case 'specific_day':
                    $day = $_GET['day'] ?? date('Y-m-d');
                    $where_clause = "c.fecha = '{$day}'";
                    break;
                case 'specific_month':
                    $month = $_GET['month'] ?? date('Y-m');
                    $where_clause = "DATE_FORMAT(c.fecha, '%Y-%m') = '{$month}'";
                    break;
                case 'specific_week':
                    $week = $_GET['week'] ?? date('Y-\WW');
                    $year = substr($week, 0, 4);
                    $week_num = substr($week, 6, 2);
                    $start_of_week = date('Y-m-d', strtotime("{$year}-W{$week_num}-1"));
                    $end_of_week = date('Y-m-d', strtotime("{$year}-W{$week_num}-7"));
                    $where_clause = "c.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL {$value} {$unit}) AND CURDATE()";
                    break;
            }

            $modalidad = $_GET['modalidad'] ?? 'all';
            $sql = "SELECT c.fecha, c.hora_inicio, c.hora_fin, p.nombre, p.telefono, p.alergias as diagnostico, atp.nombre as tipo_paciente, p.origen, c.nota_paciente, s.nombre as servicio, ec.nombre as estado
                    FROM agenda_citas c
                    LEFT JOIN portal_pacientes p ON c.paciente_id = p.id
                    LEFT JOIN portal_servicios s ON c.servicio_id = s.id
                    LEFT JOIN agenda_estado_cita ec ON c.estado_id = ec.id
                    LEFT JOIN agenda_tipos_paciente atp ON p.tipo_id = atp.id 
                    WHERE {$where_clause} AND c.estado_id != 9";
            
            $params = [];
            $types = '';

            if ($aplicar_filtro) {
                $sql .= " AND c.usuario_id = ?";
                $params[] = $id_propietario;
                $types .= 'i';
            }

            if ($modalidad !== 'all' && !empty($modalidad) && is_numeric($modalidad)) {
                $sql .= " AND c.modalidad_id = ?";
                $params[] = $modalidad;
                $types .= 'i';
            }
            $sql .= " ORDER BY c.fecha, c.hora_inicio";

            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $stmt->store_result();
            
            $data = [];
            $row = [];
            $meta = $stmt->result_metadata();
            $bind_params = [];
            while ($field = $meta->fetch_field()) {
                $bind_params[] = &$row[$field->name];
            }
            call_user_func_array([$stmt, 'bind_result'], $bind_params);

            while ($stmt->fetch()) {
                $c = [];
                foreach ($row as $key => $val) {
                    $c[$key] = $val;
                }
                $data[] = $c;
            }

            // --- INICIO: Lógica para añadir el color ---
            // Mapa de colores basado en el nombre del estado (igual que en otros archivos)
            $colores_map = [
                'reservado' => '#2196F3',
                'confirmado' => '#FF9800',
                'asistió' => '#E91E63',
                'no asistió' => '#FF7F50',
                'pendiente' => '#F44336',
                'en espera' => '#4CAF50',
                'cancelada' => '#797a79ff'
            ];

            foreach ($data as &$row) {
                $estado_lower = strtolower($row['estado'] ?? '');
                $row['hex_color'] = $colores_map[$estado_lower] ?? '#6c757d'; // Gris por defecto
            }
            // --- FIN: Lógica para añadir el color ---
            $stmt->close();
            
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        }

        if ($action === 'origen_stats') {
            // --- Lógica de Rango de Fechas Mejorada ---
            $period = $_GET['period'] ?? 'week';
            $where_clause = "c.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()"; // Default: week

            switch ($period) {
                case 'today':
                    $where_clause = "c.fecha = CURDATE()";
                    break;
                case 'week':
                    $where_clause = "c.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
                    break;
                case 'month':
                    $where_clause = "c.fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND c.fecha <= CURDATE()";
                    break;
                case '3months':
                    $where_clause = "c.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 3 MONTH) AND CURDATE()";
                    break;
                case '6months':
                    $where_clause = "c.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND CURDATE()";
                    break;
                case 'thisyear':
                    $where_clause = "YEAR(c.fecha) = YEAR(CURDATE())";
                    break;
                case 'custom':
                    $unit = $_GET['unit'] ?? 'day';
                    $value = (int)($_GET['value'] ?? 1);
                    $allowed_units = ['day', 'week', 'month', 'year'];
                    $unit = in_array($unit, $allowed_units) ? $unit : 'day';
                    $where_clause = "c.fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL {$value} {$unit}) AND CURDATE()";
                    break;
                case 'specific_day':
                    $day = $_GET['day'] ?? date('Y-m-d');
                    $where_clause = "c.fecha = '{$day}'";
                    break;
                case 'specific_month':
                    $month = $_GET['month'] ?? date('Y-m');
                    $where_clause = "DATE_FORMAT(c.fecha, '%Y-%m') = '{$month}'";
                    break;
                case 'specific_week':
                    $week = $_GET['week'] ?? date('Y-\WW');
                    $year = substr($week, 0, 4);
                    $week_num = substr($week, 6, 2);
                    $start_of_week = date('Y-m-d', strtotime("{$year}-W{$week_num}-1"));
                    $end_of_week = date('Y-m-d', strtotime("{$year}-W{$week_num}-7"));
                    $where_clause = "c.fecha BETWEEN '{$start_of_week}' AND '{$end_of_week}'";
                    break;
            }

            // Contar citas con origen 'web' (En línea)
            $sql_web = "SELECT COUNT(c.id) FROM agenda_citas c JOIN portal_pacientes p ON c.paciente_id = p.id WHERE p.origen = 'web' AND " . $where_clause;
            if ($aplicar_filtro) $sql_web .= " AND c.usuario_id = ?";
            $stmt_web = $conn->prepare($sql_web);
            if ($aplicar_filtro) $stmt_web->bind_param("i", $id_propietario);
            $stmt_web->execute();
            $stmt_web->bind_result($total_web);
            $stmt_web->fetch();
            $stmt_web->close();

            // Contar el resto de citas (Caja/Interno)
            $sql_caja = "SELECT COUNT(c.id) FROM agenda_citas c JOIN portal_pacientes p ON c.paciente_id = p.id WHERE (p.origen != 'web' OR p.origen IS NULL) AND " . $where_clause;
            if ($aplicar_filtro) $sql_caja .= " AND c.usuario_id = ?";
            $stmt_caja = $conn->prepare($sql_caja);
            if ($aplicar_filtro) $stmt_caja->bind_param("i", $id_propietario);
            $stmt_caja->execute();
            $stmt_caja->bind_result($total_caja);
            $stmt_caja->fetch();
            $stmt_caja->close();

            echo json_encode(['success' => true, 'data' => [$total_web, $total_caja]]);
            exit;
        }

        if ($action === 'ocupacion_stats') {
            $period = $_GET['period'] ?? 'week';
            $where_clause = "fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
            $date_format = '%a'; // Formato por defecto para semana (Lun, Mar, etc.)

            switch ($period) {
                case 'today':
                    $where_clause = "fecha = CURDATE()";
                    $date_format = '%H:00'; // Por hora
                    break;
                case 'week':
                    $where_clause = "fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
                    $date_format = '%Y-%m-%d'; // Por día
                    break;
                case 'month':
                    $where_clause = "fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND fecha <= CURDATE()";
                    $date_format = '%d'; // Por día del mes
                    break;
                case '3months':
                case '6months':
                    $where_clause = "fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL " . ($period === '3months' ? '3' : '6') . " MONTH) AND CURDATE()";
                    $date_format = '%Y-%m'; // Por mes
                    break;
                case 'thisyear':
                    $where_clause = "YEAR(fecha) = YEAR(CURDATE())";
                    $date_format = '%b'; // Por mes abreviado (Ene, Feb)
                    break;
                case 'custom':
                    $unit = $_GET['unit'] ?? 'day';
                    $value = (int)($_GET['value'] ?? 1);
                    $allowed_units = ['day', 'week', 'month', 'year'];
                    $unit = in_array($unit, $allowed_units) ? $unit : 'day';
                    $where_clause = "fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL {$value} {$unit}) AND CURDATE()";
                    $date_format = '%Y-%m-%d'; // Para rangos personalizados, mostrar por día es lo más claro
                    break;
                case 'specific_day':
                    $where_clause = "fecha = '{$_GET['day']}'";
                    $date_format = '%H:00'; // Por hora
                    break;
                case 'specific_month':
                    $where_clause = "DATE_FORMAT(fecha, '%Y-%m') = '{$_GET['month']}'";
                    $date_format = '%Y-%m-%d'; // Por día
                    break;
                case 'specific_week':
                    $week = $_GET['week'] ?? date('Y-\WW');
                    $year = substr($week, 0, 4);
                    $week_num = substr($week, 6, 2);
                    $where_clause = "fecha BETWEEN '".date('Y-m-d', strtotime("{$year}-W{$week_num}-1"))."' AND '".date('Y-m-d', strtotime("{$year}-W{$week_num}-7"))."'";
                    $date_format = '%Y-%m-%d'; // Por día
                    break;
            }

            // CORRECCIÓN: Consulta simplificada para un gráfico de barras simple.
            $sql = "SELECT DATE_FORMAT(fecha, '{$date_format}') as dia, COUNT(*) as cantidad 
                    FROM agenda_citas 
                    WHERE {$where_clause}";
            
            if ($aplicar_filtro) {
                $sql .= " AND usuario_id = ?";
            }
            
            $sql .= " GROUP BY dia ORDER BY fecha";
            
            $stmt = $conn->prepare($sql);
            if ($aplicar_filtro) $stmt->bind_param("i", $id_propietario);
            $stmt->execute();
            $res = $stmt->get_result();
            $labels = [];
            $data = [];
            while ($r = $res->fetch_assoc()) {
                $labels[] = $r['dia'];
                $data[] = (int)$r['cantidad'];
            }

            echo json_encode(['success' => true, 'labels' => $labels, 'data' => $data]);
            exit;
        }
        
        if ($action === 'citas_canceladas') {
            // Obtener últimas 10 citas canceladas (estado_id = 7)
            // Se puede ajustar el límite o filtrar por fecha si se desea
            $sql = "SELECT c.id, c.fecha, c.hora_inicio, p.nombre, p.apellido, s.nombre as servicio
                    FROM agenda_citas c
                    LEFT JOIN portal_pacientes p ON c.paciente_id = p.id
                    LEFT JOIN portal_servicios s ON c.servicio_id = s.id
                    WHERE c.estado_id = 7";
            
            if ($aplicar_filtro) {
                $sql .= " AND c.usuario_id = ?";
            }
            
            $sql .= " ORDER BY c.fecha DESC, c.hora_inicio DESC LIMIT 10";
            
            $stmt = $conn->prepare($sql);
            if ($aplicar_filtro) $stmt->bind_param("i", $id_propietario);
            $stmt->execute();
            $res = $stmt->get_result();
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        }

        if ($action === 'pendientes_pago') {
            // Obtener citas que NO están pagadas y NO están canceladas
            // Se asume que estado_pago 'completado' es pagado.
            // Filtramos por los últimos 30 días y futuras para mantener la lista relevante.
            $sql = "SELECT c.id, c.fecha, c.hora_inicio, p.nombre, p.apellido, s.nombre as servicio, 
                           COALESCE(s.precio, 0) as precio
                    FROM agenda_citas c
                    LEFT JOIN portal_pacientes p ON c.paciente_id = p.id
                    LEFT JOIN portal_servicios s ON c.servicio_id = s.id
                    WHERE (c.estado_pago != 'completado' OR c.estado_pago IS NULL)
                    AND c.estado_id != 7
                    AND c.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            
            if ($aplicar_filtro) {
                $sql .= " AND c.usuario_id = ?";
            }
            $sql .= " ORDER BY c.fecha ASC, c.hora_inicio ASC LIMIT 10";
            
            $stmt = $conn->prepare($sql);
            if ($aplicar_filtro) $stmt->bind_param("i", $id_propietario);
            $stmt->execute();
            $res = $stmt->get_result();
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        }

        throw new Exception("Acción no válida: " . htmlspecialchars($action));

    } catch (Throwable $t) {
        http_response_code(500);
        error_log("Error en API de reporte.php: " . $t->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error interno del servidor.', 'details' => $t->getMessage()]);
        exit;
    }
}

// HTML Rendering part
header('Content-Type: text/html; charset=utf-8');
require_once 'includes/auth.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id']) || !puedeRealizar('acceder_reportes')) {
    header('Location: login.php');
    exit;
}

// Obtener información del usuario desde la sesión
$user_id = $_SESSION['usuario_id'];
$user_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$user_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';

// Permisos
$puede_crear_citas = in_array($user_tipo, ['admin', 'caja']);
$puede_gestionar_usuarios = ($user_tipo === 'admin');

?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php $show_calendar = true; $show_back = false; $show_admin_tools = $puede_gestionar_usuarios; $show_mobile_menu = false; include __DIR__ . '/includes/header.php'; ?>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #000000;
            color: #e5e7eb;
            padding-top: 100px;
        }
        
        /* Header Styles Unificados */
        .main-header {
            background: rgba(5, 5, 5, 0.95);
            backdrop-filter: blur(10px);
            color: white;
            height: 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            border-bottom: 1px solid rgba(41, 121, 255, 0.1);
        }
        
        .header-left, .header-center, .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .header-logo img { height: 45px; width: auto; filter: drop-shadow(1px 1px 2px rgba(0,0,0,0.1)); }
        .header-title { font-size: 24px; font-weight: 700; color: white; letter-spacing: 1px; }
        
        .btn-header {
            color: #e5e7eb;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-header:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }

        /* Contenido Reporte */
        .panel-grid { display:flex; gap:20px; align-items:stretch; flex-wrap:wrap; }
        
        .card-stat { 
            background: #0a0a0a; 
            padding: 24px; 
            border-radius: 16px; 
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); 
            border: 1px solid rgba(255, 255, 255, 0.05); 
            margin-bottom: 20px; 
        }
        
        .panel-grid .card-stat { flex:1 1 0; min-width:220px }
        
        .small-muted { color:#9ca3af; font-size:12px; margin-bottom:8px }
        .stat-number { font-size:32px; font-weight:700; color: #ffffff; }
        
        .card-stat h6 { margin:0 0 8px 0; font-size:14px; color:#e5e7eb }
        .chart-box { min-height:160px }
        
        /* Inputs oscuros */
        .form-control { 
            background: #000; 
            border: 1px solid #333; 
            color: #e5e7eb; 
            border-radius: 8px; 
        }
        .form-control:focus { 
            background: #000; 
            color: #fff; 
            border-color: #2979ff; 
            box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2); 
        }
        
        .btn-secondary {
            background: #1f2937;
            border-color: #374151;
            color: #e5e7eb;
        }
        .btn-secondary:hover {
            background: #374151;
            border-color: #4b5563;
        }
        
        .bg-light { background-color: #0a0a0a !important; border: 1px solid rgba(255,255,255,0.05) !important; color: #e5e7eb; }

        @media (max-width:768px) { .panel-grid { flex-direction:column; } }
    </style>

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Reporte</h3>
            <div class="d-flex align-items-center">
                <select id="periodSelect" class="form-control" style="width:200px; margin-right: 15px;">
                    <option value="">-- Periodo Rápido --</option>
                    <option value="today">Hoy</option>
                    <option value="week" selected>Esta semana</option>
                    <option value="month">Este mes</option>
                    <option value="3months">Últimos 3 meses</option>
                    <option value="6months">Semestre</option>
                    <option value="thisyear">Este año</option>
                </select>
                <div class="form-inline">
                    <input type="number" id="customPeriodValue" class="form-control mr-2" style="width: 80px;" placeholder="Cant." min="1">
                    <select id="customPeriodUnit" class="form-control mr-2" style="width: 120px;">
                        <option value="day">Días</option>
                        <option value="week">Semanas</option>
                        <option value="month">Meses</option>
                        <option value="year">Años</option>
                    </select>
                    <button id="applyCustomPeriod" class="btn btn-secondary">Aplicar</button>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center mb-3 p-2 bg-light border rounded">
            <strong class="mr-3">Ver por fecha específica:</strong>
            <div class="form-inline mr-3">
                <label for="specificDate" class="mr-2">Día:</label>
                <input type="date" id="specificDate" class="form-control">
            </div>
            <div class="form-inline mr-3">
                <label for="specificMonth" class="mr-2">Mes:</label>
                <input type="month" id="specificMonth" class="form-control">
            </div>
            <div class="form-inline">
                <label for="specificWeek" class="mr-2">Semana:</label>
                <input type="week" id="specificWeek" class="form-control">
            </div>
        </div>

        <div class="panel-grid mb-3">
            <div class="card-stat">
                <div class="small-muted">TOTAL DE RESERVAS</div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="stat-number" id="totalReservas">--</div>
                    <a href="#" id="verDetalles">Ver detalles</a>
                </div>
                <div id="detalleColapsado" style="display:none;margin-top:10px;"></div>
            </div>
            <div class="card-stat">
                <div class="small-muted">FACTOR DE OCUPACIÓN</div>
                <div class="stat-number" id="factorOcupacion">--</div>
                <div class="small-muted">Promedio de citas por día</div>
            </div>
            <div class="card-stat">
                <div class="small-muted">NUEVOS CLIENTES</div>
                <div class="stat-number" id="nuevosClientes">--</div>
                <div class="small-muted">Con respecto al período anterior</div>
            </div>
            <div class="card-stat">
                <div class="small-muted">CITAS CANCELADAS</div>
                <div class="stat-number" id="citasCanceladas">--</div>
                <div class="small-muted">En el período seleccionado</div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card-stat mb-3">
                    <h6>Pagos en línea</h6>
                    <div class="stat-number" id="pagosOnline">--</div>
                </div>
                <div class="card-stat mb-3">
                    <h6>Recordatorios por WhatsApp</h6>
                    <div>Enviados: <span class="stat-number" id="wppEnviados">--</span></div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="card-stat mb-3">
                    <h6>Factor de ocupación (por día)</h6>
                    <div class="chart-box"><canvas id="ocupacionChart"></canvas></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-stat mb-3">
                    <h6>Origen de las reservas</h6>
                    <div class="chart-box"><canvas id="origenChart"></canvas></div>
                </div>
            </div>
        </div>

        <div class="card-stat mt-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5>Detalle de reservas</h5>
                <select id="modalidadFiltro" class="form-control" style="width:260px;"></select>
            </div>
            <div id="detalleHoy" style="margin-top:12px;max-height:400px;overflow:auto;"></div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function parseJsonOrError(response){
            if (!response.ok) {
                if (response.status === 403) return Promise.reject(new Error('403: Acceso denegado — ¿sesión expirada? Por favor inicia sesión.'));
                return response.text().then(function(t){
                    var preview = (t||'').toString().slice(0,200);
                    return Promise.reject(new Error('HTTP '+response.status+' — respuesta: '+ preview));
                });
            }
            var ct = (response.headers.get('content-type')||'').toLowerCase();
            if (ct.indexOf('application/json') !== -1) return response.json();
            return response.text().then(function(t){
                var preview = (t||'').toString().slice(0,200);
                return Promise.reject(new Error('Respuesta no JSON del servidor: '+ preview));
            });
        }

        function fetchAllStats(params) {
            fetchStats(params);
            fetchOrigenStats(params);
            fetchOcupacionStats(params);
            loadDetalleHoy(params);
        }

        function fetchStats(params) {
            var queryString = new URLSearchParams(params).toString();
            if (!queryString) {
                var period = document.getElementById('periodSelect').value;
                if (!period) return; // No hacer nada si no hay periodo seleccionado
                queryString = 'period=' + period;
            }
            fetch('reporte.php?action=stats&' + queryString)
                .then(parseJsonOrError).then(j => {
                    if (j.success) {
                        document.getElementById('totalReservas').textContent = j.total;
                        document.getElementById('factorOcupacion').textContent = j.factor;
                        document.getElementById('nuevosClientes').textContent = j.nuevos;
                        document.getElementById('pagosOnline').textContent = j.pagos_online;
                        document.getElementById('wppEnviados').textContent = j.wpp_enviados || 0; // Actualizamos el contador de WPP
                        document.getElementById('citasCanceladas').textContent = j.canceladas || 0;
                    }
                }).catch(function(err){ console.warn('fetchStats error:', err); });
        }

        document.getElementById('verDetalles').addEventListener('click', function(e){
            e.preventDefault();
            var cont = document.getElementById('detalleColapsado');
            if (cont.style.display === 'none') {
                fetch('reporte.php?action=states_by_count').then(parseJsonOrError).then(j=>{
                    if (j.success) {
                        cont.innerHTML = '';
                        j.data.forEach(function(it){
                            var div = document.createElement('div');
                            div.innerHTML = '<span class="circle-dot" style="background:#2196F3"></span> '+it.nombre+' <strong>'+it.cantidad+'</strong>';
                            cont.appendChild(div);
                        });
                        cont.style.display = 'block';
                    }
                }).catch(function(err){ console.warn('states_by_count error:', err); cont.innerHTML = '<div class="alert alert-danger">'+(err.message || 'Error')+'</div>'; });
            } else { cont.style.display = 'none'; }
        });

        function fetchOrigenStats(params) {
            var queryString = new URLSearchParams(params).toString();
            if (!queryString) {
                const period = document.getElementById('periodSelect').value;
                if (!period) return;
                params = { period: period };
                queryString = new URLSearchParams(params).toString();
            }
            if (!params.period) {
                return;
            }
            fetch('reporte.php?action=origen_stats&' + queryString)
                .then(parseJsonOrError)
                .then(j => {
                    if (j.success && oriChart) {
                        oriChart.data.datasets[0].data = j.data;
                        oriChart.update();
                    }
                })
                .catch(err => console.warn('fetchOrigenStats error:', err));
        }

        function fetchOcupacionStats(params) {
            var queryString = new URLSearchParams(params).toString();
            if (!queryString) {
                var period = document.getElementById('periodSelect').value;
                if (!period) return;
                queryString = 'period=' + period;
            }
            fetch('reporte.php?action=ocupacion_stats&' + queryString)
                .then(parseJsonOrError)
                .then(j => {
                    if (j.success && ocupChart && j.data && j.labels) {
                        // CORRECCIÓN: Lógica simplificada para un gráfico de barras simple.
                        ocupChart.data.labels = j.labels;
                        ocupChart.data.datasets[0].data = j.data;
                        ocupChart.data.datasets[0].backgroundColor = '#4CAF50'; // Color verde
                        ocupChart.data.datasets[0].label = 'Reservas';
                        ocupChart.update();
                    }
                })
                .catch(err => console.warn('fetchOcupacionStats error:', err));
        }

        document.getElementById('periodSelect').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('customPeriodValue').value = '';
                document.getElementById('specificDate').value = '';
                document.getElementById('specificMonth').value = '';
                document.getElementById('specificWeek').value = '';
                fetchAllStats({ period: this.value });
            }
        });

        document.getElementById('applyCustomPeriod').addEventListener('click', function() {
            const value = document.getElementById('customPeriodValue').value;
            const unit = document.getElementById('customPeriodUnit').value;
            if (value && unit) {
                document.getElementById('periodSelect').value = '';
                document.getElementById('specificDate').value = '';
                document.getElementById('specificMonth').value = '';
                document.getElementById('specificWeek').value = '';
                fetchAllStats({ period: 'custom', value: value, unit: unit });
            }
        });

        document.getElementById('specificDate').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('periodSelect').value = '';
                document.getElementById('customPeriodValue').value = '';
                document.getElementById('specificMonth').value = '';
                document.getElementById('specificWeek').value = '';
                fetchAllStats({ period: 'specific_day', day: this.value });
            }
        });

        document.getElementById('specificMonth').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('periodSelect').value = '';
                document.getElementById('customPeriodValue').value = '';
                document.getElementById('specificDate').value = '';
                document.getElementById('specificWeek').value = '';
                fetchAllStats({ period: 'specific_month', month: this.value });
            }
        });

        document.getElementById('specificWeek').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('periodSelect').value = '';
                document.getElementById('customPeriodValue').value = '';
                document.getElementById('specificDate').value = '';
                document.getElementById('specificMonth').value = '';
                fetchAllStats({ period: 'specific_week', week: this.value });
            }
        });

        // Carga inicial de datos
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializa los listeners de los filtros de modalidad
            document.getElementById('modalidadFiltro').addEventListener('change', () => fetchAllStats(getActivePeriodParams()));

            fetchAllStats({ period: 'week' });
        });

        // carga de modalidades para filtro
        fetch('citas/modalidades_json.php').then(parseJsonOrError).then(data=>{
            var sel = document.getElementById('modalidadFiltro');
            sel.innerHTML = '<option value="all" selected>Todas las modalidades</option>';
            data.forEach(function(m){
                var o = document.createElement('option'); o.value = m.id; o.textContent = m.title || m.nombre || m.name || m.id; sel.appendChild(o);
            });
            // La carga inicial de loadDetalleHoy se hace dentro de fetchAllStats
        });

        function getActivePeriodParams() {
            const period = document.getElementById('periodSelect').value;
            if (period) return { period: period };

            const customValue = document.getElementById('customPeriodValue').value;
            if (customValue) return { period: 'custom', value: customValue, unit: document.getElementById('customPeriodUnit').value };

            const specificDay = document.getElementById('specificDate').value;
            if (specificDay) return { period: 'specific_day', day: specificDay };
            
            const specificMonth = document.getElementById('specificMonth').value;
            if (specificMonth) return { period: 'specific_month', month: specificMonth };

            const specificWeek = document.getElementById('specificWeek').value;
            if (specificWeek) return { period: 'specific_week', week: specificWeek };

            return { period: 'week' }; // Fallback
        }

        function loadDetalleHoy(params) {
            var mod = document.getElementById('modalidadFiltro').value;
            var queryString = new URLSearchParams(params).toString();
            if (!queryString) {
                return; // No hacer nada si no hay parámetros
            }

            if (!params.period) return;

            fetch(`reporte.php?action=reservas_today&modalidad=${encodeURIComponent(mod)}&${queryString}`)
                .then(parseJsonOrError).then(j=>{
                    if (j.success) {
                        var out = document.getElementById('detalleHoy');
                        out.innerHTML = '';
                        if (!j.data || j.data.length === 0) {
                            out.innerHTML = '<div class="text-muted p-3">No hay reservas para el período y filtros seleccionados.</div>';
                            return;
                        }
                        j.data.forEach(function(row){
                            var div = document.createElement('div');
                            // Añadimos una barra de color a la izquierda
                            div.style.cssText = 'padding: 8px 8px 8px 16px; border-bottom: 1px solid #eee; position: relative;';
                            
                            var colorBar = document.createElement('div');
                            colorBar.style.cssText = `position: absolute; left: 0; top: 0; bottom: 0; width: 6px; background-color: ${row.hex_color || '#ccc'}; border-radius: 3px 0 0 3px;`;
                            div.appendChild(colorBar);
                            
                            // Se usa innerHTML para añadir el contenido restante, después de la barra de color.
                            div.innerHTML += `<strong>${row.fecha} ${row.hora_inicio} - ${row.hora_fin}</strong> <br>` +
                                            '<strong>'+(row.nombre || 'N/A')+'</strong> ('+(row.telefono || 'N/A')+')<br>' +
                                            (row.diagnostico || 'N/A') + ' - ' + (row.tipo_paciente || 'N/A') + ' - ' + (row.origen || 'N/A') + '<br>' +
                                            '<em>'+(row.servicio || 'N/A')+'</em> <span style="float:right">'+(row.estado || 'N/A')+'</span>';
                            out.appendChild(div);
                        });
                    }
                }).catch(function(err){ console.warn('reservas_today error:', err); document.getElementById('detalleHoy').innerHTML='<div class="alert alert-danger">'+(err.message||'Error')+'</div>'; });
        }

        // Charts: placeholder data
        var ocupCtx = document.getElementById('ocupacionChart').getContext('2d');
        var ocupChart = new Chart(ocupCtx, {
            type: 'bar',
            data: {
                labels: [], // Se llenará dinámicamente
                datasets: [{ label: 'Reservas', backgroundColor: '#4CAF50', data: [] }]
            },
            options: { 
                responsive:true, 
                maintainAspectRatio:false,
                scales: { x: { stacked: false }, y: { stacked: false, beginAtZero: true } }
            }
        });

        var oriCtx = document.getElementById('origenChart').getContext('2d');
        var oriChart = new Chart(oriCtx, {
            type: 'doughnut',
            data: { labels:['En línea','Caja / Interno'], datasets:[{ data:[0,0], backgroundColor:['#2196F3','#4CAF50'] }] },
            options: { responsive:true, maintainAspectRatio:false }
        });

    </script>
</body>
</html>