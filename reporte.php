<?php
session_start();
require_once 'includes/db.php';
// header variables for this view
$show_calendar = true;
$show_back = false;
// header include will be added into the body after head

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
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

// Simple API endpoints inside the same file for AJAX
$action = $_GET['action'] ?? '';
header('Content-Type: application/json');
if ($action === 'stats') {
    // total reservas today/week (example: week)
    $period = $_GET['period'] ?? 'week';
    if ($period === 'week') {
        // week: count reservations in the last 7 days
        $sql = "SELECT COUNT(*) as total FROM agenda_citas WHERE fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
        $res = $conn->query($sql);
        $total = 0;
        if ($res) { $row = $res->fetch_assoc(); $total = intval($row['total']); }

        // factor ocupacion: placeholder calc (appointments / possible slots) - return dummy
        $factor = 40.06;
        $factor_diff = -12.59; // percent change

        // nuevos clientes (count of distinct patients created in period)
        $sql2 = "SELECT COUNT(DISTINCT paciente_id) as nuevos FROM agenda_citas WHERE fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
        $r2 = $conn->query($sql2);
        $nuevos = $r2 ? intval($r2->fetch_assoc()['nuevos']) : 0;
        $nuevos_diff = -8.53;

        // pagos en linea: placeholder 0
        $pagos_online = 0;

        echo json_encode(['success'=>true,'total'=>$total,'factor'=>$factor,'factor_diff'=>$factor_diff,'nuevos'=>$nuevos,'nuevos_diff'=>$nuevos_diff,'pagos_online'=>$pagos_online]);
        exit;
    }
}

if ($action === 'states_by_count') {
    // return counts grouped by estado
    $sql = "SELECT ec.nombre, COUNT(*) as cantidad FROM agenda_citas c LEFT JOIN agenda_estado_cita ec ON c.estado_id = ec.id WHERE c.fecha = CURDATE() GROUP BY ec.nombre";
    $res = $conn->query($sql);
    $out = [];
    while ($r = $res->fetch_assoc()) {
        $out[] = $r;
    }
    echo json_encode(['success'=>true,'data'=>$out]);
    exit;
}

if ($action === 'reservas_today') {
    $modalidad = $_GET['modalidad'] ?? 'all';
    $sql = "SELECT c.hora_inicio, c.hora_fin, p.nombre, p.telefono, p.diagnostico, p.tipo, p.origen, c.nota_paciente, s.nombre as servicio, ec.nombre as estado
            FROM agenda_citas c
            LEFT JOIN portal_pacientes p ON c.paciente_id = p.id
            LEFT JOIN portal_servicios s ON c.servicio_id = s.id
            LEFT JOIN agenda_estado_cita ec ON c.estado_id = ec.id
            WHERE c.fecha = CURDATE()";
    if ($modalidad !== 'all') {
        $sql .= " AND c.modalidad_id = " . intval($modalidad);
    }
    $sql .= " ORDER BY c.hora_inicio";
    $res = $conn->query($sql);
    $out = [];
    while ($r = $res->fetch_assoc()) {
        $out[] = $r;
    }
    echo json_encode(['success'=>true,'data'=>$out]);
    exit;
}

// If not API, render HTML page
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <link rel="stylesheet" href="css/header.css">
    </head>
    <body>
        <?php $show_calendar = true; $show_back = false; $show_admin_tools = $puede_gestionar_usuarios; $show_mobile_menu = false; include __DIR__ . '/includes/header.php'; ?>

        <div style="height:12px"></div>

    <style>
        /* Report stats as boxed cards (recuadros) */
        .panel-grid { display:flex; gap:16px; align-items:stretch; flex-wrap:wrap; }
        /* make .card-stat a reusable boxed card anywhere on the page */
        .card-stat { background:#fff; padding:16px 18px; border-radius:10px; box-shadow:0 6px 18px rgba(15,23,42,0.06); margin-bottom:12px }
        .panel-grid .card-stat { flex:1 1 0; min-width:220px }
        .small-muted { color:#6b7280; font-size:12px; margin-bottom:8px }
        .stat-number { font-size:28px; font-weight:700 }
        .card-stat h6 { margin:0 0 8px 0; font-size:14px; color:#111827 }
        .chart-box { min-height:160px }
        @media (max-width:768px) { .panel-grid { flex-direction:column; } }
    </style>

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Reporte</h3>
            <select id="periodSelect" class="form-control" style="width:200px;">
                <option value="week">Esta semana</option>
                <option value="month">Este mes</option>
            </select>
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
                <div class="d-flex align-items-center">
                    <div style="font-size:28px;font-weight:700;" id="factorOcupacion">--%</div>
                    <div style="margin-left:10px;color:#999;">(<span id="factorDiff">--%</span>)</div>
                </div>
                <div class="small-muted">Con respecto al período anterior</div>
            </div>
            <div class="card-stat">
                <div class="small-muted">NUEVOS CLIENTES</div>
                <div class="stat-number" id="nuevosClientes">--</div>
                <div class="small-muted">Con respecto al período anterior</div>
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
                    <div>Enviados: <span class="stat-number" id="wppEnviados">0</span></div>
                    <div>Confirmados: <span class="stat-number" id="wppConfirmados">0</span></div>
                    <button id="btnActualizarWpp" class="btn btn-sm btn-link">Actualizar</button>
                </div>
                <div class="card-stat mb-3">
                    <h6>Recordatorios por Email</h6>
                    <div>Enviados: <span class="stat-number" id="emailEnviados">0</span></div>
                    <div>Confirmados: <span class="stat-number" id="emailConfirmados">0</span></div>
                    <button id="btnActualizarEmail" class="btn btn-sm btn-link">Actualizar</button>
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
                <h5>Ver detalle de reservas de hoy</h5>
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

        function fetchStats() {
            fetch('reporte.php?action=stats&period=' + document.getElementById('periodSelect').value)
                .then(parseJsonOrError).then(j => {
                    if (j.success) {
                        document.getElementById('totalReservas').textContent = j.total;
                        document.getElementById('factorOcupacion').textContent = j.factor + '%';
                        document.getElementById('factorDiff').textContent = (j.factor_diff>0?'+':'')+j.factor_diff+'%';
                        document.getElementById('nuevosClientes').textContent = j.nuevos;
                        document.getElementById('pagosOnline').textContent = j.pagos_online;
                    }
                }).catch(function(err){ console.warn('fetchStats error:', err); });
        }
        fetchStats();

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

        // carga de modalidades para filtro
        fetch('citas/modalidades_json.php').then(parseJsonOrError).then(data=>{
            var sel = document.getElementById('modalidadFiltro');
            sel.innerHTML = '<option value="all">Todas</option>';
            data.forEach(function(m){
                var o = document.createElement('option'); o.value = m.id; o.textContent = m.title || m.nombre || m.name || m.id; sel.appendChild(o);
            });
        }).catch(function(err){ console.warn('modalidades load error:', err); var sel=document.getElementById('modalidadFiltro'); sel.innerHTML='<option value="all">Todas</option>'; });

        function loadDetalleHoy() {
            var mod = document.getElementById('modalidadFiltro').value;
            fetch('reporte.php?action=reservas_today&modalidad='+encodeURIComponent(mod))
                .then(parseJsonOrError).then(j=>{
                    if (j.success) {
                        var out = document.getElementById('detalleHoy');
                        out.innerHTML = '';
                        j.data.forEach(function(row){
                            var div = document.createElement('div');
                            div.style.padding='8px'; div.style.borderBottom='1px solid #eee';
                            div.innerHTML = '<strong>'+row.hora_inicio+' - '+row.hora_fin+'</strong> <br>' +
                                            '<strong>'+row.nombre+'</strong> ('+row.telefono+')<br>' +
                                            row.diagnostico + ' - ' + row.tipo + ' - ' + row.origen + '<br>' +
                                            '<em>'+row.servicio+'</em> <span style="float:right">'+row.estado+'</span>';
                            out.appendChild(div);
                        });
                    }
                }).catch(function(err){ console.warn('reservas_today error:', err); document.getElementById('detalleHoy').innerHTML='<div class="alert alert-danger">'+(err.message||'Error')+'</div>'; });
        }
        document.getElementById('modalidadFiltro').addEventListener('change', loadDetalleHoy);
        loadDetalleHoy();

        // Charts: placeholder data
        var ocupCtx = document.getElementById('ocupacionChart').getContext('2d');
        var ocupChart = new Chart(ocupCtx, {
            type: 'bar',
            data: {
                labels: ['Lun','Mar','Mie','Jue','Vie','Sab','Dom'],
                datasets: [{ label: 'Reservas', backgroundColor: '#4caf50', data: [10,30,45,50,20,5,3] }]
            },
            options: { responsive:true, maintainAspectRatio:false }
        });

        var oriCtx = document.getElementById('origenChart').getContext('2d');
        var oriChart = new Chart(oriCtx, {
            type: 'doughnut',
            data: { labels:['Reservas en línea','Reservas desde la agenda'], datasets:[{ data:[0,100], backgroundColor:['#2196F3','#9E9E9E'] }] },
            options: { responsive:true, maintainAspectRatio:false }
        });

    </script>
</body>
</html>