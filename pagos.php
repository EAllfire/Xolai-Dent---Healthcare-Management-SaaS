<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Verificar permisos
if (!puedeRealizar('acceder_reportes') && !in_array($_SESSION['usuario_tipo'], ['admin', 'superadmin', 'dentista'])) { 
    header('Location: index.php');
    exit;
}

$usuario = obtenerUsuarioActual();
$user_nombre = $usuario['nombre'] ?? 'Usuario';
$user_tipo = $usuario['tipo'] ?? 'usuario';
$es_admin = ($user_tipo === 'admin');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pagos - Hospital Angeles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #000000;
            color: #e5e7eb;
            padding-top: 100px;
        }
        
        /* Header Styles */
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
        .header-center { flex: 2; justify-content: center; }
        
        .header-logo img { height: 45px; width: auto; }
        .header-title { font-size: 24px; font-weight: 700; color: white; letter-spacing: 1px; }
        
        .nav-link {
            color: #a0a0a0;
            font-weight: 500;
            font-size: 15px;
            padding: 8px 16px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        .nav-link:hover, .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.12);
        }
        
        .user-info { display: flex; align-items: center; gap: 10px; font-weight: 500; }
        
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

        .container-custom { max-width: 1400px; margin: 0 auto; padding: 0 15px; }
        
        .page-title { font-size: 2rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; }

        .actions-bar {
            background: #0a0a0a;
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-container {
            background: #0a0a0a;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            overflow-x: auto;
        }

        .table { color: #e5e7eb; margin-bottom: 0; }
        .table thead th {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #9ca3af;
            border-top: none;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        .table td {
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }
        .table-hover tbody tr:hover { background-color: rgba(41, 121, 255, 0.05); color: #fff; }

        .badge-pago { padding: 6px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .badge-pendiente { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-completado { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }

        .form-control { background: #000; border: 1px solid #333; color: #e5e7eb; border-radius: 8px; }
        .form-control:focus { background: #000; color: #fff; border-color: #2979ff; box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2); }
        
        .btn-action {
            padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; border: none; cursor: pointer; transition: all 0.2s;
        }
        .btn-pagar { background: #10b981; color: white; }
        .btn-pagar:hover { background: #059669; }
        .btn-revertir { background: #ef4444; color: white; }
        .btn-revertir:hover { background: #dc2626; }

        /* Totales cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 2rem; }
        .stat-card { background: #0a0a0a; border: 1px solid #333; padding: 20px; border-radius: 12px; }
        .stat-label { color: #9ca3af; font-size: 13px; text-transform: uppercase; margin-bottom: 5px; }
        .stat-value { font-size: 24px; font-weight: 700; color: #fff; }
        .stat-value.green { color: #34d399; }
        .stat-value.yellow { color: #fbbf24; }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <div class="header-logo">
                <img src="images/Xolai.png" alt="Logo">
                <span class="header-title" style="margin-left: 10px;">Xolai</span>
            </div>
        </div>
        
        <nav class="header-center">
            <a href="home.php" class="nav-link">Inicio</a>
            <a href="index.php" class="nav-link">Agenda</a>
            <a href="catalogo_pacientes.php" class="nav-link">Pacientes</a>
            <a href="pagos.php" class="nav-link active">Pagos</a>
            <?php if ($es_admin): ?>
                <a href="panel_admin.php" class="nav-link">Administración</a>
            <?php endif; ?>
        </nav>
        
        <div class="header-right">
            <div class="user-info">
                <span><?php echo htmlspecialchars($user_nombre); ?></span>
                <i class="fas fa-user-circle"></i>
            </div>
            <a href="logout.php" class="btn-header">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </header>

    <div class="container-custom">
        <h1 class="page-title">Control de Pagos</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Recaudado (Mes)</div>
                <div class="stat-value green" id="total-recaudado">$0.00</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pendiente por Cobrar</div>
                <div class="stat-value yellow" id="total-pendiente">$0.00</div>
            </div>
        </div>
        
        <div class="actions-bar">
            <div class="d-flex align-items-center gap-3">
                <select id="filtro-estado" class="form-control" style="width: 150px;">
                    <option value="todos">Todos</option>
                    <option value="pendiente" selected>Pendientes</option>
                    <option value="completado">Pagados</option>
                </select>
                <input type="month" id="filtro-mes" class="form-control" value="<?php echo date('Y-m'); ?>">
            </div>
            <div class="input-group" style="max-width: 400px;">
                <input type="text" id="searchInput" class="form-control" placeholder="Buscar paciente...">
                <div class="input-group-append">
                    <span class="input-group-text bg-dark border-dark text-white"><i class="fas fa-search"></i></span>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <div id="loading" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Cargando pagos...</div>
            <div class="table-responsive">
                <table class="table table-hover" id="pagos-table" style="display: none;">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Tratamientos Registrados</th>
                        <th>Fecha de Pago</th>
                        <th>Fecha de Tratamiento</th>
                        <th>Celular</th>
                        <th>Método</th>
                        <th>Monto</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="pagos-tbody"></tbody>
            </table>
            </div>
            <div id="empty-state" class="text-center p-5" style="display: none;">
                <i class="fas fa-money-bill-wave fa-3x mb-3 text-muted"></i>
                <h4>No se encontraron registros</h4>
                <p class="text-muted">Ajusta los filtros para ver más resultados.</p>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let pagosData = [];
        let recursosData = [];

        $(document).ready(function() {
            // Cargar primero los médicos para los dropdowns
            fetch('citas/lista_doctores_json.php')
                .then(r => r.json())
                .then(data => {
                    recursosData = data;
                    cargarPagos();
                });

            $('#filtro-estado, #filtro-mes').on('change', cargarPagos);
            $('#searchInput').on('keyup', filtrarLocalmente);
        });

        function cargarPagos() {
            $('#loading').show();
            $('#pagos-table').hide();
            $('#empty-state').hide();

            const estado = $('#filtro-estado').val();
            const mes = $('#filtro-mes').val();

            // Usamos reporte.php?action=pendientes_pago como base pero necesitamos algo más completo
            // O mejor, creamos una consulta específica en este mismo archivo o uno nuevo si es necesario.
            // Para mantenerlo limpio, usaremos un endpoint simple aquí abajo via fetch.
            
            fetch(`citas/pagos_json.php?mes=${mes}&estado=${estado}`)
                .then(response => response.json())
                .then(data => {
                    pagosData = data.pagos || [];
                    renderizarTabla(pagosData);
                    actualizarStats(data.stats);
                })
                .catch(error => {
                    console.error('Error:', error);
                    $('#loading').html('<p class="text-danger">Error al cargar los movimientos financieros.</p>');
                });
        }

        function actualizarStats(stats) {
            if (!stats) return;
            $('#total-recaudado').text(parseFloat(stats.recaudado || 0).toLocaleString('es-MX', {style: 'currency', currency: 'MXN'}));
            $('#total-pendiente').text(parseFloat(stats.pendiente || 0).toLocaleString('es-MX', {style: 'currency', currency: 'MXN'}));
        }

        function getAntiquityColor(fechaTratamiento) {
            if (!fechaTratamiento || fechaTratamiento === '---') return '';
            const start = new Date(fechaTratamiento + 'T00:00:00');
            const today = new Date();
            today.setHours(0,0,0,0);
            const diffTime = today - start;
            const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays <= 14) return '#10b981'; // Verde (< 2 semanas)
            if (diffDays <= 30) return '#fbbf24'; // Amarillo (entre 2 semanas y 1 mes)
            if (diffDays <= 60) return '#f97316'; // Naranja (entre 1 y 2 meses)
            return '#ef4444'; // Rojo (> 2 meses)
        }

        function filtrarLocalmente() {
            const term = $('#searchInput').val().toLowerCase();
            const filtrados = pagosData.filter(p => 
                p.paciente.toLowerCase().includes(term) || 
                (p.servicio && p.servicio.toLowerCase().includes(term))
            );
            renderizarTabla(filtrados);
        }

        function renderizarTabla(data) {
            const tbody = $('#pagos-tbody');
            tbody.empty();
            $('#loading').hide();
            if (data.length === 0) {
                $('#empty-state').show();
                return;
            }
            $('#pagos-table').show();

            data.forEach(pago => {
                const esPagado = pago.estado_pago === 'completado';
                const badgeClass = esPagado ? 'badge-completado' : 'badge-pendiente';
                const badgeText = esPagado ? 'Pagado' : 'Pendiente';
                const colorPendiente = !esPagado ? getAntiquityColor(pago.fecha_tratamiento) : '';

                let montoHtml = parseFloat(pago.precio || 0).toLocaleString('es-MX', {style: 'currency', currency: 'MXN'});
                let statusStyle = !esPagado && colorPendiente ? `style="background: ${colorPendiente}; color: #000; border: none; font-weight: 800;"` : '';

                const tr = `
                    <tr>
                        <td><strong>${pago.paciente}</strong></td>
                        <td><small class="text-muted">${pago.servicio || 'Sin tratamiento'}</small></td>
                        <td>${pago.fecha_pago || '---'}</td>
                        <td>${pago.fecha_tratamiento || '---'}</td>
                        <td>${pago.telefono || '---'}</td>
                        <td><span class="badge badge-dark" style="background:#111; border:1px solid #333;">${pago.metodo || '---'}</span></td>
                        <td class="font-weight-bold text-white">${montoHtml}</td>
                        <td><span class="badge-pago ${badgeClass}" ${statusStyle}>${badgeText}</span></td>
                    </tr>
                `;
                tbody.append(tr);
            });
        }
    </script>
</body>
</html>
