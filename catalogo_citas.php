<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Solo usuarios con permisos (admin, caja) pueden ver esta página
if (!puedeRealizar('ver_catalogo_citas')) {
    header('Location: index.php');
    exit;
}

$usuario = obtenerUsuarioActual();
$user_nombre = $usuario['nombre'] ?? 'Usuario';
$user_tipo = $usuario['tipo'] ?? 'usuario';
$puede_ver_admin = in_array($user_tipo, ['admin', 'medico', 'dentista']);

// Configuración del header
$show_calendar = true;
$show_back = true;
$show_admin_tools = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Flatpickr CSS para el selector de semana -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #000000;
            color: #e5e7eb;
            padding-top: 0;
        }
        
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

        .container-custom { max-width: 1600px; margin: 0 auto; padding: 120px 15px 0 15px; }
        
        .page-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.5);
        }

        .table-container {
            background: #0a0a0a;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            overflow-x: auto;
        }onararr xe i
            flex-wrap: wrap; /* Para que se ajuste en pantallas pequeñas */
            gap: 1rem;
            align-items: ce
        .btn-group .btn.active { background-color: #2979ff; color: white; border-color: #2979ff; }
        
        .            display: inline-block;

            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            text-decoration: none;
        }
        .doc-link:hover { text-decoration: none; color: white; opacity: 0.8; }
        .doc-ine { background-color: #3b82f6; }
        .doc-orden { background-color: #10b981; }
        .origen-badge { padding: 5px 10px; border-radius: 5px; color: white; font-weight: bold; }
        .origen-online { background-color: #8b5cf6; }
        .origen-interno { background-color: #6b7280; }

        /* Estilos de Tabla Oscura */
        .table {
            color: #e5e7eb;
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #9ca3af;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            border-top: none;
        }

        .table td {
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding: 1rem;
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(41, 121, 255, 0.05);
            color: #e5e7eb;
        }

        /* Inputs y Buscador */
        .form-control {
            background: #000000;
            border: 1px solid #333;
            color: #e5e7eb;
            border-radius: 8px;
        }

        .form-control:focus {
            background: #000000;
            color: #fff;
            border-color: #2979ff;
            box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2);
        }

        .input-group-text {
            background: #111;
            border-color: #333;
            color: #9ca3af;
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
            <a href="panel_admin.php" class="nav-link">Administración</a>
        </nav>
        <div class="header-right">
            <div class="user-info">
                <span><?php echo htmlspecialchars($user_nombre); ?></span>
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="settings-container">
                <button onclick="toggleSettingsDropdown()" class="settings-btn"><i class="fas fa-cog"></i></button>
                <div id="ajustesDropdown" class="custom-dropdown-menu">
                    <a href="catalogo_servicios.php"><i class="fas fa-stethoscope"></i> Tratamientos</a>
                    <a href="admin_modalidades.php"><i class="fas fa-layer-group"></i> Consultorios</a>
                </div>
            </div>
            <a href="logout.php" class="btn-header"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container-custom">
        <h1 class="page-title">Catálogo General de Citas</h1>
        
        <div class="actions-bar">
            <div class="d-flex align-items-center gap-3">
                <a href="panel_admin.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-secondary filter-btn active" data-period="all">Todas</button>
                    <button type="button" class="btn btn-outline-secondary filter-btn" data-period="today">Hoy</button>
                    <button type="button" class="btn btn-outline-secondary filter-btn" data-period="week">Esta Semana</button>
                </div>
                <div class="input-group" style="max-width: 250px;">
                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-calendar-week"></i></span></div>
                    <input type="text" id="weekPicker" class="form-control" placeholder="Seleccionar semana...">
                </div>
            </div>
            <div class="input-group" style="max-width: 500px;">
                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                <input type="text" id="searchInput" class="form-control" placeholder="Buscar por paciente, servicio, modalidad o fecha (YYYY-MM-DD)...">
            </div>
            <span id="total-citas" class="text-muted">Cargando...</span>
        </div>
        
        <div class="table-container">
            <div id="loading" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Cargando citas...</div>
            <div class="table-responsive">
                <table class="table table-hover" id="citas-table" style="display: none;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha y Hora</th>
                            <th>Paciente</th>
                            <th>Tratamiento</th>
                            <th>Recomendación</th>
                            <th>Doctor</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="citas-tbody"></tbody>
                </table>
            </div>
            <div id="empty-state" class="text-center p-5" style="display: none;">
                <h4>No se encontraron citas</h4>
                <p>Intenta con otro término de búsqueda o revisa más tarde.</p>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
    <script>
        let todasLasCitas = [];

        $(document).ready(function() {
            // Cargar todas las citas por defecto
            cargarCitas('all');

            // Buscador
            $('#searchInput').on('keyup', function() {
                const searchTerm = $(this).val().toLowerCase();
                const citasFiltradas = todasLasCitas.filter(cita => {
                    return (cita.paciente_nombre_completo?.toLowerCase().includes(searchTerm)) ||
                           (cita.servicio_nombre?.toLowerCase().includes(searchTerm)) ||
                           (cita.modalidad_nombre?.toLowerCase().includes(searchTerm)) ||
                           (cita.fecha?.toLowerCase().includes(searchTerm));
                });
                renderizarCitas(citasFiltradas);
            });

            // Botones de filtro de período
            $('.filter-btn').on('click', function() {
                $('.filter-btn').removeClass('active');
                $(this).addClass('active');
                const period = $(this).data('period');
                cargarCitas(period);
                // Limpiar el selector de semana si se usa un botón
                if (flatpickr.instances.weekPicker) {
                    flatpickr.instances.weekPicker[0].clear();
                }
            });

            // Inicializar Flatpickr como selector de semana
            flatpickr("#weekPicker", {
                locale: "es",
                weekNumbers: true,
                altInput: true,
                altFormat: "j F, Y",
                dateFormat: "Y-m-d",
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length > 0) {
                        $('.filter-btn').removeClass('active');
                        cargarCitas('custom_week', dateStr);
                    }
                }
            });
        });

        function cargarCitas(period = 'all', date = null) {
            $('#loading').show();
            $('#citas-table').hide();
            
            let url = `citas_catalogo_json.php?periodo=${period}`;
            if (period === 'custom_week' && date) {
                url += `&fecha=${date}`;
            }
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    todasLasCitas = data;
                    renderizarCitas(todasLasCitas);
                })
                .catch(error => {
                    console.error('Error al cargar citas:', error);
                    $('#loading').html(`<div class="alert alert-danger">Error al cargar las citas: ${error.message}</div>`);
                });
        }

        function renderizarCitas(citas) {
            const tbody = $('#citas-tbody');
            tbody.empty();
            $('#loading').hide();

            if (citas.length === 0) {
                $('#citas-table').hide();
                $('#empty-state').show();
                $('#total-citas').text('0 citas encontradas');
                return;
            }

            $('#empty-state').hide();
            $('#citas-table').show();
            $('#total-citas').text(`${citas.length} citas encontradas`);

            citas.forEach(cita => {
                // Formatear recomendación (origen del paciente)
                let recomendacion = 'Otro';
                if (cita.recomendado_nombre) recomendacion = 'Doctor: ' + cita.recomendado_nombre;
                else if (cita.paciente_origen) {
                    recomendacion = cita.paciente_origen.startsWith('DOCTOR:') 
                        ? cita.paciente_origen.replace('DOCTOR:', 'Doctor: ') 
                        : cita.paciente_origen;
                }

                const tr = `
                    <tr>
                        <td>${cita.id}</td>
                        <td>${cita.fecha} <br><small>${cita.hora_inicio}</small></td>
                        <td>${cita.paciente_nombre_completo || 'N/A'}</td>
                        <td>${cita.servicio_nombre || 'N/A'}</td>
                        <td><small>${recomendacion}</small></td>
                        <td><small>${cita.medico_nombre || 'N/A'}</small></td>
                        <td><span class="badge" style="background-color:${cita.estado_color || '#6c757d'}; color:white;">${cita.estado_nombre || 'Desconocido'}</span></td>
                        <td class="text-right">
                            <button class="btn btn-sm btn-outline-success mr-1" onclick="editarCita(${cita.id})" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="eliminarCita(${cita.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `;
                tbody.append(tr);
            });
        }

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

        function editarCita(id) {
            // Redirigir a la agenda principal pasando el ID para abrir el modal de edición
            window.location.href = `index.php?editar_cita=${id}`;
        }

        function eliminarCita(id) {
            if (confirm('¿Está seguro de eliminar esta cita de forma permanente?')) {
                fetch(`eliminar_cita.php?cita_id=${id}`, {
                    method: 'GET'
                })
                .then(r => r.json())
                .then(resp => {
                    if (resp.success) {
                        alert('Cita eliminada correctamente.');
                        // Recargar la lista conservando el periodo seleccionado
                        const period = $('.filter-btn.active').data('period') || 'all';
                        cargarCitas(period);
                    } else {
                        alert('Error al eliminar: ' + (resp.error || 'Desconocido'));
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    alert('Error de conexión al intentar eliminar la cita.');
                });
            }
        }
    </script>
</body>
</html>