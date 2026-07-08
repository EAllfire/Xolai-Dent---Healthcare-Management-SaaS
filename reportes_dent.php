<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

$user_tipo = $_SESSION['usuario_tipo'] ?? '';
$user_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$es_dentista_principal = ($user_tipo === 'dentista' && empty($_SESSION['id_padre']));
$puede_ver_admin = in_array($user_tipo, ['admin', 'medico', 'dentista']);

$usuario_id_real = $_SESSION['usuario_id'] ?? 0;
$id_propietario = $_SESSION['id_padre'] ?? $usuario_id_real;

// Únicamente pueden entrar el dentista principal (padre), caja y admin
if (!isset($_SESSION['usuario_id']) || !($user_tipo === 'admin' || $user_tipo === 'caja' || $user_tipo === 'superadmin' || $es_dentista_principal)) {
    header('Location: index.php');
    exit;
}

$filtro = $_GET['filtro'] ?? 'mes'; // hoy, semana, mes
$fecha_inicio = date('Y-m-01');
$fecha_fin = date('Y-m-t');

if ($filtro === 'hoy') {
    $fecha_inicio = $fecha_fin = date('Y-m-d');
} elseif ($filtro === 'semana') {
    $fecha_inicio = date('Y-m-d', strtotime('monday this week'));
    $fecha_fin = date('Y-m-d', strtotime('sunday this week'));
}

$stats_doctores = [];
// 0. Inicializar lista de todos los médicos del equipo (Padre + Colaboradores) para que aparezcan en la tabla
$res_team = $conn->query("SELECT nombre FROM agenda_usuarios WHERE (id = " . (int)$id_propietario . " OR id_padre = " . (int)$id_propietario . ") AND tipo IN ('dentista', 'medico', 'admin') ORDER BY nombre ASC");
while($t = $res_team->fetch_assoc()) {
    $stats_doctores[$t['nombre']] = ['ingreso' => 0, 'total_gen' => 0, 'liquidado' => 0, 'por_liquidar' => 0];
}

// 1. Obtener todos los servicios para el mapeo de nombres
$servicios_map = [];
$res_serv = $conn->query("SELECT id, nombre FROM portal_servicios");
while ($s = $res_serv->fetch_assoc()) { $servicios_map[$s['id']] = $s['nombre']; }

// 2. Obtener movimientos de pagos desde el JSON de expedientes, agrupados por paciente
$sql = "SELECT d.registro_pagos_json, d.presupuesto_json, d.realized_treatments_json, p.nombre, p.apellido_paterno, p.apellido_materno, p.apellido, u.nombre as clinic_owner_name 
        FROM agenda_expediente_dentista d
        JOIN portal_pacientes p ON d.paciente_id = p.id
        JOIN agenda_usuarios u ON p.usuario_id = u.id
        WHERE (p.usuario_id = " . (int)$id_propietario . " OR p.usuario_id IN (SELECT id FROM agenda_usuarios WHERE id_padre = " . (int)$id_propietario . "))";
$res = $conn->query($sql);

$movimientos_abonos = []; // Lista de abonos para la tabla principal
$stats_metodos = [
    'Efectivo' => ['ingreso' => 0, 'egreso' => 0, 'total_gen' => 0],
    'Tarjeta Débito' => ['ingreso' => 0, 'egreso' => 0, 'total_gen' => 0],
    'Tarjeta Crédito' => ['ingreso' => 0, 'egreso' => 0, 'total_gen' => 0],
    'Efec Dolls' => ['ingreso' => 0, 'egreso' => 0, 'total_gen' => 0],
    'Transferencia' => ['ingreso' => 0, 'egreso' => 0, 'total_gen' => 0],
];
while ($row = $res->fetch_assoc()) {
    // 2.1 Procesar Tratamientos Realizados (Deuda y Trabajo del Médico)
    // Ahora usamos realized_treatments_json que es el origen de verdad para lo que se cobra
    $deuda_total = 0;
    $tratamientos_paciente_all = [];
    $realized = json_decode($row['realized_treatments_json'] ?? '[]', true);
    
    if (is_array($realized)) {
        foreach ($realized as $item) {
            $monto_neto = (float)($item['total'] ?? 0);
            $fecha_aplicacion = $item['fecha_aplicacion'] ?? '';

            if (!empty($item['servicio_nombre'])) {
                $tratamientos_paciente_all[] = $item['servicio_nombre'];
            }
            
            // Ganancia del doctor basada en la fecha de aplicación dentro del rango
            if ($fecha_aplicacion >= $fecha_inicio && $fecha_aplicacion <= $fecha_fin) {
                $doc_name = !empty($item['doctor_nombre']) ? $item['doctor_nombre'] : $row['clinic_owner_name'];
                if (!isset($stats_doctores[$doc_name])) {
                    $stats_doctores[$doc_name] = ['ingreso' => 0, 'total_gen' => 0, 'liquidated' => 0, 'por_liquidar' => 0];
                }
                $stats_doctores[$doc_name]['ingreso'] += $monto_neto;
            }
            
            // La deuda total del paciente se sigue calculando sobre todos sus realizados para el saldo pendiente
            $deuda_total += $monto_neto;
        }
    }
    $tratamientos_str = !empty($tratamientos_paciente_all) ? implode(', ', array_unique($tratamientos_paciente_all)) : 'Sin tratamientos realizados';

    // 2.2 Procesar Abonos
    $pagos = json_decode($row['registro_pagos_json'], true);
    if (!is_array($pagos)) continue;

    // Calcular pagado histórico para el pendiente
    $total_pagado_historico = 0;
    foreach($pagos as $p) {
        $total_pagado_historico += (float)($p['pago'] ?? 0);
    }

    $ap_p = $row['apellido_paterno'] ?: $row['apellido'];
    $ap_m = $row['apellido_materno'] ?: '';
    $paciente_nombre_completo = trim($row['nombre'] . ' ' . $ap_p . ' ' . $ap_m);

    foreach ($pagos as $p) {
        $fecha_pago = $p['fecha'];
        if ($fecha_pago >= $fecha_inicio && $fecha_pago <= $fecha_fin) {
            $pago_recibido = (float)($p['pago'] ?? 0);
            if ($pago_recibido <= 0) continue;

            // Determinar médico del abono
            $doctor = !empty($p['doctor_nombre']) ? $p['doctor_nombre'] : $row['clinic_owner_name'];
            
            // Liquidación (egreso)
            $fue_liquidado = isset($p['liquidado_medico']) && $p['liquidado_medico'] == true;
            $monto_egreso = $fue_liquidado ? (float)($p['monto_liquidado'] ?? 0) : 0;

            // Agregar a la lista de movimientos para la tabla
            $movimientos_abonos[] = [
                'fecha' => $fecha_pago,
                'paciente' => $paciente_nombre_completo,
                'tratamientos' => $tratamientos_str,
                'total_presupuesto' => $deuda_total,
                'ingreso' => $pago_recibido,
                'pendiente' => $deuda_total - $total_pagado_historico,
                'egreso' => $monto_egreso
            ];

            // Acumulado global para el resumen por filas
            // Sumar montos individuales si es el nuevo modelo, o el campo 'pago' si es el viejo
            if (isset($p['monto_efectivo'])) {
                $stats_metodos['Efectivo']['ingreso'] += (float)$p['monto_efectivo'];
                $stats_metodos['Tarjeta Débito']['ingreso'] += (float)($p['monto_tarjeta_debito'] ?? 0);
                $stats_metodos['Tarjeta Crédito']['ingreso'] += (float)($p['monto_tarjeta_credito'] ?? 0);
                $tc = (float)($p['monto_tipo_cambio'] ?? 20);
                $stats_metodos['Efec Dolls']['ingreso'] += ((float)($p['monto_dlls'] ?? 0) * $tc);
                $stats_metodos['Transferencia']['ingreso'] += (float)($p['monto_transferencia'] ?? 0);
            } else {
                $stats_metodos['Efectivo']['ingreso'] += $pago_recibido;
            }

            $stats_metodos['Efectivo']['total_gen'] += $pago_recibido;

            // Los egresos se acumulan según el método con el que se pagó al médico
            if ($fue_liquidado) {
                $metodo_liq = $p['metodo_liquidacion'] ?? 'Efectivo';
                $m_liq = strtolower($metodo_liq);
                $target_key_liq = 'Efectivo';
                if (strpos($m_liq, 'efectivo') !== false) $target_key_liq = 'Efectivo';
                elseif (strpos($m_liq, 'débito') !== false || strpos($m_liq, 'deb') !== false) $target_key_liq = 'Tarjeta Débito';
                elseif (strpos($m_liq, 'crédito') !== false || strpos($m_liq, 'cred') !== false) $target_key_liq = 'Tarjeta Crédito';
                elseif (strpos($m_liq, 'dolls') !== false || strpos($m_liq, 'dlls') !== false) $target_key_liq = 'Efec Dolls';
                elseif (strpos($m_liq, 'transferencia') !== false) $target_key_liq = 'Transferencia';
                
                $stats_metodos[$target_key_liq]['egreso'] += $monto_egreso;
            }

            // Los egresos (liquidaciones ya hechas) se restan de lo que el doctor ha "realizado"
            $doctor_pago = !empty($p['doctor_nombre']) ? $p['doctor_nombre'] : $row['clinic_owner_name'];
            if ($fue_liquidado && isset($stats_doctores[$doctor_pago])) {
                $stats_doctores[$doctor_pago]['liquidado'] += $monto_egreso;
            }
        }
    }
}

// 3. Recalcular "Por Liquidar" para los doctores
foreach ($stats_doctores as $nombre => &$s) {
    // Lo que falta pagar es: (Trabajo Realizado) - (Ya pagado a través de abonos liquidados)
    // Nota: El cálculo de la comisión (%) se hace en el frontend de la tabla del reporte
    $s['por_liquidar'] = max(0, $s['ingreso'] - ($s['liquidado'] / 0.5)); // Asumiendo 50% para el cálculo base, se ajusta con el input del reporte
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes Financieros - DENT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #000; color: #e5e7eb; font-family: 'Inter', sans-serif; padding-top: 0; margin: 0; }

        /* Header Styles - Xolai Style */
        .main-header {
            background: rgba(10, 10, 10, 0.5);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            color: white;
            height: 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 40px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            mask-image: linear-gradient(to bottom, black 70%, transparent 100%);
            -webkit-mask-image: linear-gradient(to bottom, black 70%, transparent 100%);
        }
        
        .header-left, .header-center, .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .header-left { flex: 1; justify-content: flex-start; }
        .header-center { flex: 2; justify-content: center; }
        .header-right { flex: 1; justify-content: flex-end; }

        .header-logo-img {
            height: 45px;
            width: auto;
        }
        
        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: white;
            letter-spacing: 1px;
        }
        
        .nav-link {
            color: #a0a0a0;
            font-weight: 500;
            font-size: 15px;
            padding: 8px 16px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.12);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

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

        .settings-container { position: relative; display: inline-block; margin-right: 10px; }
        .settings-btn { background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1); cursor: pointer; font-size: 1.2rem; color: #e5e7eb; padding: 6px 10px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .custom-dropdown-menu { display: none; position: absolute; right: 0; top: 100%; background-color: #0a0a0a; min-width: 200px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); border-radius: 12px; z-index: 1100; margin-top: 10px; border: 1px solid #333; }
        .custom-dropdown-menu.show { display: block; }
        .custom-dropdown-menu a { color: #e5e7eb; padding: 12px 20px; text-decoration: none; display: block; font-size: 14px; border-bottom: 1px solid #1a1a1a; }
        .custom-dropdown-menu a:hover { background-color: rgba(41, 121, 255, 0.1); color: #2979ff; }

        .report-card { background: #0a0a0a; border: 1px solid #222; border-radius: 16px; padding: 25px; margin-bottom: 30px; }
        .header-title { color: #fff; font-weight: 700; letter-spacing: -0.5px; }
        .table { color: #e5e7eb; font-size: 13px; }
        .table thead th { border-top: none; border-bottom: 1px solid #333; color: #9ca3af; text-transform: uppercase; font-size: 11px; }
        .table td { border-bottom: 1px solid #111; vertical-align: middle; }
        .total-row { background: rgba(41, 121, 255, 0.05); font-weight: 700; color: #fff; }
        .btn-filter { background: #111; border: 1px solid #333; color: #9ca3af; padding: 6px 15px; border-radius: 8px; font-size: 13px; }
        .btn-filter.active { background: #2979ff; color: #fff; border-color: #2979ff; }
        .text-income { color: #10b981; }
        .text-pending { color: #f59e0b; }
        .text-liquidated { color: #6b7280; text-decoration: line-through; }
        .input-pct { background: #000; border: 1px solid #333; color: #2979ff; width: 60px; text-align: center; border-radius: 4px; font-weight: bold; }
        .ganancia-row { color: #2979ff; font-weight: 600; }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <img src="images/Xolai.png" alt="Xolai Logo" class="header-logo-img">
            <span class="header-title">Xolai</span>
        </div>
        <nav class="header-center">
            <a href="home.php" class="nav-link">Inicio</a>
            <a href="index.php" class="nav-link">Agenda</a>
            <a href="catalogo_pacientes.php" class="nav-link">Pacientes</a>
            <a href="pagos.php" class="nav-link">Pagos</a>
            <a href="panel_admin.php" class="nav-link active">Administración</a>
        </nav>
        <div class="header-right">
            <div class="user-info">
                <span><?php echo htmlspecialchars($user_nombre); ?></span>
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="settings-container">
                <button onclick="toggleSettingsDropdown()" class="settings-btn"><i class="fas fa-cog"></i></button>
                <div id="ajustesDropdown" class="custom-dropdown-menu">
                    <a href="catalogo_servicios.php"><i class="fas fa-stethoscope"></i> Servicios</a>
                    <a href="admin_modalidades.php"><i class="fas fa-layer-group"></i> Modalidades</a>
                </div>
            </div>
            <a href="logout.php" class="btn-header"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </header>
    <div class="container-fluid px-5" style="padding-top: 120px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="header-title mb-0">Movimientos Dentales</h2>
                <a href="panel_admin.php" class="btn btn-sm btn-outline-secondary mt-2"><i class="fas fa-arrow-left"></i> Volver a Administración</a>
                <p class="text-muted small">Periodo: <?php echo $fecha_inicio; ?> al <?php echo $fecha_fin; ?></p>
            </div>
            <div class="btn-group">
                <a href="?filtro=hoy" class="btn btn-filter <?php echo $filtro == 'hoy' ? 'active' : ''; ?>">Hoy</a>
                <a href="?filtro=semana" class="btn btn-filter <?php echo $filtro == 'semana' ? 'active' : ''; ?>">Semana</a>
                <a href="?filtro=mes" class="btn btn-filter <?php echo $filtro == 'mes' ? 'active' : ''; ?>">Mes</a>
            </div>
        </div>
<?php usort($movimientos_abonos, function($a, $b) { return strcmp($b['fecha'], $a['fecha']); }); ?>
        <!-- Tabla 1: Desglose de Abonos Realizados -->
        <div class="report-card">
            <h5 class="mb-4"><i class="fas fa-money-bill-wave mr-2 text-primary"></i> Desglose de Abonos en el Periodo</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                              <th>Fecha</th>
                            <th>Paciente</th>
                            <th>Tratamientos (Presupuesto)</th>
                            <th class="text-right">Deuda Total ($)</th>
                            <th class="text-right">Abono Recibido ($)</th>
                            <th class="text-right">Pendiente ($)</th>
                          
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                          $sum_ingreso = 0;
                        foreach ($movimientos_abonos as $mov): 
                            $sum_ingreso += $mov['ingreso'];
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($mov['fecha'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($mov['paciente']); ?></strong></td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($mov['tratamientos']); ?></small></td>
                            <td class="text-right">$<?php echo number_format($mov['total_presupuesto'], 2); ?></td>
                            <td class="text-right text-income font-weight-bold">$<?php echo number_format($mov['ingreso'], 2); ?></td>
                            <td class="text-right text-warning">$<?php echo number_format($mov['pendiente'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                          <td colspan="4" class="text-right text-uppercase">Total Ingresos en Periodo</td>
                            <td class="text-right text-income">$<?php echo number_format($sum_ingreso, 2); ?></td>
                            <td></td>  
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Tabla 3: Ganancia por Médicos -->
        <div class="report-card">
            <h5 class="mb-4"><i class="fas fa-user-md mr-2 text-primary"></i> Cálculo de Ganancias por Doctor</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th class="text-right">Cobrado Total</th>
                            <th class="text-right">Ya Pagado (Egreso)</th>
                            <th class="text-right">Por Liquidar</th>
                            <th class="text-center" style="width: 150px;">% Ganancia</th>
                            <th class="text-right text-primary">Total a Pagar</th>
                            <th class="text-center">Método Pago</th>
                            <th class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="doctor-earnings-body">
                        <?php foreach ($stats_doctores as $nombre => $val): ?>
                        <tr data-doctor="<?php echo htmlspecialchars($nombre); ?>" data-pendiente="<?php echo $val['por_liquidar']; ?>">
                            <td><strong><?php echo $nombre; ?></strong></td>
                            <td class="text-right">$<?php echo number_format($val['ingreso'], 2); ?></td>
                            <td class="text-right text-danger">$<?php echo number_format($val['liquidado'], 2); ?></td>
                            <td class="text-right text-warning">$<?php echo number_format($val['por_liquidar'], 2); ?></td>
                            <td class="text-center"><input type="number" class="input-pct" value="50" oninput="recalcularGanancia(this)"> %</td>
                            <td class="text-right ganancia-row">$<?php echo number_format($val['por_liquidar'] * 0.5, 2); ?></td>
                            <td class="text-center">
                                <select class="form-control p-metodo-liquidacion" style="font-size: 12px; height: 32px; padding: 2px 5px; width: 130px;">
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Tarjeta Débito">Tarjeta Débito</option>
                                    <option value="Tarjeta Crédito">Tarjeta Crédito</option>
                                    <option value="Efec Dolls">Efec Dolls</option>
                                    <option value="Transferencia">Transferencia</option>
                                </select>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-success" onclick="liquidarPagoMedico(this)" <?php echo $val['por_liquidar'] <= 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-hand-holding-usd"></i> Liquidar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
            function recalcularGanancia(input) {
                const row = input.closest('tr');
                const pendiente = parseFloat(row.dataset.pendiente);
                const pct = parseFloat(input.value) || 0;
                const ganancia = pendiente * (pct / 100);
                row.querySelector('.ganancia-row').textContent = '$' + ganancia.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }

            async function liquidarPagoMedico(btn) {
                const row = btn.closest('tr');
                const doctor = row.dataset.doctor;
                const pct = row.querySelector('.input-pct').value;
                const metodo = row.querySelector('.p-metodo-liquidacion').value;
                const fInicio = "<?php echo $fecha_inicio; ?>";
                const fFin = "<?php echo $fecha_fin; ?>";

                if (!confirm(`¿Confirmar pago al Dr. ${doctor} vía ${metodo} al ${pct}%?`)) return;

                btn.disabled = true;
                try {
                    const resp = await fetch('citas/liquidar_ganancia_medico.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            doctor: doctor,
                            porcentaje: pct,
                            metodo_pago: metodo,
                            fecha_inicio: fInicio,
                            fecha_fin: fFin
                        })
                    });
                    const res = await resp.json();
                    if (res.success) {
                        alert('Liquidación registrada con éxito.');
                        location.reload();
                    } else {
                        alert('Error: ' + res.error);
                        btn.disabled = false;
                    }
                } catch(e) { alert('Error de conexión'); btn.disabled = false; }
            }
        </script>

        <!-- Tabla 2: Análisis Unificado por Métodos de Pago -->
        <div class="report-card">
            <h5 class="mb-4"><i class="fas fa-chart-pie mr-2 text-primary"></i> Resumen Consolidado por Métodos de Pago</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha / Forma de Pago</th>
                            <th class="text-right">Ingreso ($)</th>
                            <th class="text-right">Egreso ($)</th>
                            <th class="text-right">Total (Saldo) ($)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $gr_ingreso = 0; $gr_egreso = 0;
                        foreach ($stats_metodos as $nombre => $val): 
                            $saldo = ($val['ingreso'] ?? 0) - ($val['egreso'] ?? 0);
                            $gr_ingreso += $val['ingreso'];
                            $gr_egreso += ($val['egreso'] ?? 0);
                        ?>
                        <tr>
                            <td><?php echo $nombre; ?></td>
                            <td class="text-right text-income">$<?php echo number_format($val['ingreso'], 2); ?></td>
                            <td class="text-right text-muted">$<?php echo number_format($val['egreso'], 2); ?></td>
                            <td class="text-right font-weight-bold">$<?php echo number_format($saldo, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td class="text-uppercase">TOTALES</td>
                            <td class="text-right text-income">$<?php echo number_format($gr_ingreso, 2); ?></td>
                            <td class="text-right">$<?php echo number_format($gr_egreso, 2); ?></td>
                            <td class="text-right">$<?php echo number_format($gr_ingreso - $gr_egreso, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <script>
        function toggleSettingsDropdown() {
            document.getElementById("ajustesDropdown").classList.toggle("show");
        }
        window.onclick = function(event) {
            if (!event.target.matches('.settings-btn') && !event.target.closest('.settings-btn')) {
                var dropdowns = document.getElementsByClassName("custom-dropdown-menu");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
    </script>
</body>
</html>