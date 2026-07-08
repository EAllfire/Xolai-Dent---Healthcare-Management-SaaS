<?php
session_start();
require_once("includes/db.php");
require_once("includes/auth.php");

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Obtener información del usuario desde la sesión
$user_id = $_SESSION['usuario_id'];
$user_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$user_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';

// Definir permisos basados en el tipo de usuario
$es_admin = ($user_tipo === 'admin');
$puede_ver_admin = ($user_tipo === 'superadmin') || in_array($user_tipo, ['admin', 'medico', 'dentista']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xolai</title>
    <link rel="icon" type="image/png" href="images/logo2.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* CSS Reset and Base Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", sans-serif; /* Added Roboto for broader compatibility with a clean sans-serif look */
            background-color: #000000;
            color: #e5e7eb;
            margin: 0;
            padding-top: 0; /* Changed for transparent header */
        }
        .background-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-image: url('images/fondo2.png');
            background-size: cover;
            background-position: bottom center;
            opacity: 0.30; /* Opacidad completa para que la imagen se vea tal cual */
            z-index: -1; /* Detrás de todo el contenido */
            pointer-events: none; /* Para que no interfiera con los clics */
        }
        a { text-decoration: none; color: inherit; }

        /* Header Styles */
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
            border-radius: 10px; /* More rounded */
            transition: all 0.2s ease;
        }
        .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.12); /* Slightly more pronounced hover */
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        .btn-header {
            color: #e5e7eb;
            background: rgba(255, 255, 255, 0.08); /* More subtle background */
            border: 1px solid rgba(255, 255, 255, 0.1); /* More subtle border */
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 10px; /* More rounded */
            transition: all 0.2s ease;
        }
        .btn-header:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.25);
        }

        /* Dashboard Body Styles */
        .dashboard-container {
            display: grid;
            grid-template-columns: 1fr 1.5fr 1fr;
            gap: 50px; /* Even more increased gap for more space */
            padding: 60px; /* Even more increased padding */
            max-width: 1600px;
            margin: 0 auto;
        }

        .main-content-header {
            text-align: center;
            margin-bottom: 40px;
            padding-top: 120px; /* Space below transparent header (80px) + original padding (40px) */
        }
        .main-content-header h1 {
            font-size: 3rem;
            font-weight: 800;
            color: #f0f6fc;
            margin-bottom: 15px;
            letter-spacing: -0.05em;
        }
        .main-content-header p {
            font-size: 1.2rem;
            color: #8b949e;
            max-width: 800px;
            margin: 0 auto;
        }
        .dashboard-card {
            background: transparent;
            border-radius: 24px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }
        .dashboard-card:hover {
            background: rgba(255, 255, 255, 0.03);
        }
        .dashboard-card h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 15px;
            border-bottom: 1px solid #282828; /* Darker border for contrast */
            padding-bottom: 15px;
        }
        .dashboard-card p {
            color: #a0a0a0;
            font-size: 1rem;
            line-height: 1.6;
        }

        /* Column 1: Novedades */
        .novedades-card {
            justify-content: space-between;
        }
        .image-placeholder {
            background: rgba(41, 121, 255, 0.1); /* Accent color background */
            border-radius: 16px; /* More rounded */
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem; /* Slightly smaller icon */
            color: #2979ff;
            margin: 20px 0;
            min-height: 200px;
        }
        .card-link {
            color: #2979ff;
            font-weight: 600;
            align-self: flex-start;
            margin-top: auto;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s ease;
        }
        .card-link:hover {
            color: #58a6ff;
        }
        .card-link i {
            transition: transform 0.2s ease;
        }
        .card-link:hover i {
            transform: translateX(5px);
        }

        /* Column 2: Reporte */
        .kpi-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        .kpi-item {
            background: #0a0a0a; /* Darker KPI boxes */
            padding: 20px;
            border-radius: 16px; /* More rounded */
            border: 1px solid #222;
            transition: all 0.3s ease-in-out;
        }
        .kpi-item:hover {
            transform: translateY(-4px);
            border-color: #444;
        }
        .kpi-label {
            display: block;
            font-size: 0.9rem;
            color: #a0a0a0;
            margin-bottom: 5px;
        }
        .kpi-value {
            display: block;
            font-size: 2.2rem;
            font-weight: 800; /* Bolder KPI values */
            color: #fff;
        }
        .chart-container {
            position: relative;
            height: 100%;
            min-height: 250px;
        }

        /* Column 3: Citas de Hoy */
        .citas-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            overflow-y: auto;
            max-height: 400px; /* Limit height and allow scroll */
        }
        .cita-item {
            background: #0a0a0a;
            padding: 15px;
            border-radius: 12px; /* More rounded */
            border-left: 4px solid #2979ff;
            border-top: 1px solid #222;
            border-right: 1px solid #222;
            border-bottom: 1px solid #222;
            transition: all 0.3s ease-in-out;
        }
        .cita-item:hover {
            transform: translateX(5px);
            border-color: #58a6ff;
            background: #111;
        }
        /* Variantes de items de cita */
        .cita-item.cancelada {
            border-left-color: #ef4444; /* Rojo */
        }
        .cita-item.pendiente {
            border-left-color: #f59e0b; /* Amarillo/Naranja */
        }

        .cita-item-time {
            font-weight: 600;
            color: #fff;
            margin-bottom: 5px;
        }
        .cita-item-paciente {
            color: #a0a0a0;
        }
        .loading-placeholder {
            text-align: center;
            padding: 50px 0;
            color: #555;
        }
        .no-citas {
            text-align: center;
            padding: 50px 0;
            color: #555;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .main-header { padding: 0 20px; }
            .header-center { display: none; } /* Hide nav links on mobile */
            .dashboard-container { padding: 20px; }
            .main-content-header h1 {
                font-size: 2.5rem;
            }
            .main-content-header p {
                font-size: 1rem;
            }
            .dashboard-card { padding: 15px; }
            .image-placeholder { font-size: 3rem; min-height: 150px; }
        }

        /* Estilos del Dropdown de Configuración */
        .settings-container {
            position: relative;
            display: inline-block;
            margin-right: 10px;
        }
        .settings-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            font-size: 1.2rem;
            color: #e5e7eb;
            padding: 6px 10px;
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .settings-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            transform: rotate(90deg);
        }
        .custom-dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: #0a0a0a;
            min-width: 200px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            border-radius: 12px;
            z-index: 1100;
            overflow: hidden;
            margin-top: 10px;
            border: 1px solid #333;
            text-align: left;
        }
        .custom-dropdown-menu.show {
            display: block;
        }
        .custom-dropdown-menu a {
            color: #e5e7eb;
            padding: 12px 20px;
            text-decoration: none;
            display: block;
            font-size: 14px;
            transition: all 0.2s;
            border-bottom: 1px solid #1a1a1a;
        }
        .custom-dropdown-menu a:last-child {
            border-bottom: none;
        }
        .custom-dropdown-menu a:hover {
            background-color: rgba(41, 121, 255, 0.1);
            color: #2979ff;
        }
        .custom-dropdown-menu a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            color: #2979ff;
        }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    <header class="main-header">
        <div class="header-left">
            <img src="images/Xolai.png" alt="Xolai Logo" class="header-logo-img">
            <span class="header-title">Xolai</span>
        </div>
        <nav class="header-center">
            <a href="index.php" class="nav-link">Agenda</a>
            <a href="catalogo_pacientes.php" class="nav-link">Pacientes</a>
            <a href="pagos.php" class="nav-link">Pagos</a>
            <?php if ($puede_ver_admin): ?>
                <a href="panel_admin.php" class="nav-link">Administración</a>
            <?php endif; ?>
        </nav>
        <div class="header-right">
            <div class="user-info">
                <span><?php echo htmlspecialchars($user_nombre); ?></span>
                <i class="fas fa-user-circle"></i>
            </div>
      
      <!-- Settings Dropdown -->
      <?php if ($puede_ver_admin): ?>
      <div class="settings-container">
        <button onclick="toggleSettingsDropdown()" class="settings-btn" title="Configuración">
            <i class="fas fa-cog"></i>
        </button>
        <div id="ajustesDropdown" class="custom-dropdown-menu">
            <a href="catalogo_servicios.php"><i class="fas fa-stethoscope"></i> Servicios</a>
            <a href="admin_modalidades.php"><i class="fas fa-layer-group"></i> Modalidades</a>
            <?php if ($user_tipo === 'dentista'): ?>
                <a href="admin_usuarios.php"><i class="fas fa-users"></i> Gestionar Equipo</a>
            <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

            <div class="header-buttons">
                <a href="logout.php" class="btn-header">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <div class="main-content-header">
        <h1>Bienvenido a Xolai</h1>
        <p>Tu plataforma integral para la gestión médica. Accede a tus herramientas y reportes clave de un vistazo.</p>
    </div>

    <main class="dashboard-container">
        <!-- Column 1: Novedades -->
        <a href="novedades.php" class="dashboard-card novedades-card">
            <div>
                <h2>Novedades y Ofertas</h2>
                <p>Descubre nuestros nuevos paquetes y promociones especiales para el cuidado integral de la salud.</p>
            </div>
            <div class="image-placeholder">
                <i class="fas fa-bullhorn"></i> <!-- Changed icon for "Novedades" -->
            </div>
            <span class="card-link">Ver más <i class="fas fa-arrow-right"></i></span>
        </a>

        <!-- Column 2: Reporte -->
        <div class="dashboard-card reporte-card">
            <h2>Reporte Semanal</h2>
            <div class="kpi-container">
                <a href="reporte.php" class="kpi-item">
                        <span class="kpi-label">Citas Totales</span>
                        <span class="kpi-value" id="kpi-total-citas">--</span>
                </a>
                <a href="reporte.php" class="kpi-item">
                        <span class="kpi-label">Nuevos Pacientes</span>
                        <span class="kpi-value" id="kpi-nuevos-pacientes">--</span>
                </a>
            </div>
            <div class="chart-container">
                <a href="reporte.php" style="display: block; height: 100%;">
                    <canvas id="reporte-chart"></canvas>
                </a>
            </div>
        </div>

        <!-- Column 4: Pendientes de Pago -->
        <div class="dashboard-card pagos-card">
            <h2>Pendientes de Pago</h2>
            <div id="pendientes-pago-list" class="citas-list">
                <div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>
            </div>
        </div>

        <!-- Column 5: Citas Canceladas -->
        <div class="dashboard-card canceladas-card">
            <h2>Citas Canceladas</h2>
            <div id="citas-canceladas-list" class="citas-list">
                <div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>
            </div>
        </div>

        <!-- Column 3: Citas de Hoy -->
        <div class="dashboard-card citas-card">
            <h2>Citas de Hoy</h2>
            <div id="citas-hoy-list" class="citas-list">
                <div class="loading-placeholder">
                    <i class="fas fa-spinner fa-spin"></i> Cargando citas...
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            fetchReporteSemanal();
            fetchCitasDeHoy();
            fetchPendientesPago();
            fetchCitasCanceladas();
        });

        async function fetchReporteSemanal() {
            try {
                // Fetch KPIs
                const statsResponse = await fetch('reporte.php?action=stats&period=week');
                const statsData = await statsResponse.json();
                if (statsData.success) {
                    document.getElementById('kpi-total-citas').textContent = statsData.total || 0;
                    document.getElementById('kpi-nuevos-pacientes').textContent = statsData.nuevos || 0;
                }

                // Fetch Chart data
                const chartResponse = await fetch('reporte.php?action=ocupacion_stats&period=week');
                const chartData = await chartResponse.json();
                if (chartData.success) {
                    renderChart(chartData.labels, chartData.data);
                }
            } catch (error) {
                console.error("Error fetching weekly report:", error);
            }
        }

        async function fetchCitasDeHoy() {
            const listContainer = document.getElementById('citas-hoy-list');
            try {
                // Usamos reporte.php porque devuelve más datos formateados y el color del estado
                const response = await fetch('reporte.php?action=reservas_today&period=today');
                const result = await response.json();
                renderCitas(result.data || []);
            } catch (error) {
                console.error("Error fetching today's appointments:", error);
                listContainer.innerHTML = '<div class="no-citas">Error al cargar citas.</div>';
            }
        }

        async function fetchPendientesPago() {
            const listContainer = document.getElementById('pendientes-pago-list');
            try {
                const response = await fetch('reporte.php?action=pendientes_pago');
                const result = await response.json();
                
                listContainer.innerHTML = '';
                if (!result.success || !result.data || result.data.length === 0) {
                    listContainer.innerHTML = '<div class="no-citas"><i class="fas fa-check-circle"></i><p>Todo pagado.</p></div>';
                    return;
                }

                result.data.forEach(cita => {
                    const nombre = (cita.nombre || '') + ' ' + (cita.apellido || '');
                    const precio = cita.precio ? `$${parseFloat(cita.precio).toFixed(2)}` : 'N/A';
                    
                    const item = document.createElement('div');
                    item.className = 'cita-item pendiente';
                    item.innerHTML = `
                        <div class="cita-item-time">${cita.fecha} - ${cita.hora_inicio.substring(0,5)}</div>
                        <div class="cita-item-paciente">${nombre.trim() || 'Paciente desconocido'}</div>
                        <div style="display:flex; justify-content:space-between; margin-top:4px;">
                            <small style="color: #888;">${cita.servicio || 'Servicio no esp.'}</small>
                            <small style="color: #f59e0b; font-weight:bold;">${precio}</small>
                        </div>
                    `;
                    listContainer.appendChild(item);
                });
            } catch (error) {
                console.error("Error fetching pending payments:", error);
                listContainer.innerHTML = '<div class="no-citas">Error al cargar.</div>';
            }
        }

        async function fetchCitasCanceladas() {
            const listContainer = document.getElementById('citas-canceladas-list');
            try {
                const response = await fetch('reporte.php?action=citas_canceladas');
                const result = await response.json();
                
                listContainer.innerHTML = '';
                if (!result.success || !result.data || result.data.length === 0) {
                    listContainer.innerHTML = '<div class="no-citas"><p>Sin cancelaciones recientes.</p></div>';
                    return;
                }

                result.data.forEach(cita => {
                    const nombre = (cita.nombre || '') + ' ' + (cita.apellido || '');
                    const item = document.createElement('div');
                    item.className = 'cita-item cancelada';
                    item.innerHTML = `
                        <div class="cita-item-time">${cita.fecha} - ${cita.hora_inicio.substring(0,5)}</div>
                        <div class="cita-item-paciente">${nombre.trim() || 'Paciente desconocido'}</div>
                        <small style="color: #666;">${cita.servicio || 'Servicio no especificado'}</small>
                    `;
                    listContainer.appendChild(item);
                });
            } catch (error) {
                console.error("Error fetching canceled citations:", error);
                listContainer.innerHTML = '<div class="no-citas">Error al cargar.</div>';
            }
        }

        function renderCitas(citas) {
            const listContainer = document.getElementById('citas-hoy-list');
            listContainer.innerHTML = ''; // Clear loading
            if (!citas || citas.length === 0) {
                listContainer.innerHTML = '<div class="no-citas"><i class="fas fa-calendar-check"></i><p>No hay citas para hoy.</p></div>';
                return;
            }

            citas.forEach(cita => {
                const citaLink = document.createElement('a');
                citaLink.href = 'index.php'; // Link to the main agenda
                const citaItem = document.createElement('div');
                citaItem.className = 'cita-item';
                
                // Aplicar el color del estado al borde izquierdo si está disponible
                if (cita.hex_color) {
                    citaItem.style.borderLeftColor = cita.hex_color;
                }
                
                // Construir nombres y datos (ajustando a la estructura que devuelve reporte.php)
                const nombrePaciente = cita.nombre || cita.paciente_nombre_completo || 'Paciente no especificado';
                const nombreServicio = cita.servicio || cita.servicio_nombre || 'Servicio no especificado';
                
                citaItem.innerHTML = `
                    <div class="cita-item-time">${cita.hora_inicio.substring(0,5)}</div>
                    <div class="cita-item-paciente">${nombrePaciente}</div>
                    <small style="color: #666;">${nombreServicio}</small>
                `;
                citaLink.appendChild(citaItem);
                listContainer.appendChild(citaLink);
            });
        }

        function renderChart(labels, data) {
            const ctx = document.getElementById('reporte-chart').getContext('2d');
            
            const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);
            gradient.addColorStop(0, 'rgba(41, 121, 255, 0.5)');
            gradient.addColorStop(1, 'rgba(41, 121, 255, 0)');

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Citas por Día',
                        data: data,
                        backgroundColor: gradient,
                        borderColor: '#2979ff',
                        borderWidth: 2,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#2979ff',
                        tension: 0.4,
                        fill: true,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#222' },
                            ticks: { color: '#888', stepSize: 1 }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#888' }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#000',
                            titleColor: '#fff',
                            bodyColor: '#ccc',
                            borderColor: '#333',
                            borderWidth: 1
                        }
                    }
                }
            });
        }

        function toggleSettingsDropdown() {
            document.getElementById("ajustesDropdown").classList.toggle("show");
        }

        // Cerrar el dropdown si se hace click fuera
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