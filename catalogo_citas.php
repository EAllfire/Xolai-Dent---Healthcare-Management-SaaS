<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Solo usuarios con permisos pueden ver esta página
if (!puedeRealizar('ver_citas')) {
    header('Location: index.php');
    exit;
}

$usuario = obtenerUsuarioActual();
$user_nombre = $usuario['nombre'] ?? 'Usuario';
$user_tipo = $usuario['tipo'] ?? 'usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Citas - Hospital Angeles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Flatpickr CSS para el selector de semana -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding-top: 100px;
        }
        .main-header {
            background: #1275a0;
            color: white;
            height: 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        .header-left, .header-right { display: flex; align-items: center; gap: 15px; }
        .logo-section { position: absolute; left: 50%; transform: translateX(-50%); }
        .header-logo img { max-height: 60px; }
        .logo-text { margin: 0; font-size: 24px; font-weight: bold; }
        .btn-header { color: white; text-decoration: none; font-weight: bold; padding: 0.5rem 1rem; font-size: 13px; }
        .container-custom { max-width: 1600px; margin: 2rem auto; }
        .page-title { font-size: 2rem; font-weight: 600; margin-bottom: 2rem; text-align: center; }
        .table-container {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        .actions-bar { 
            margin-bottom: 1.5rem; 
            display: flex; 
            flex-wrap: wrap; /* Para que se ajuste en pantallas pequeñas */
            gap: 1rem;
            justify-content: space-between; 
            align-items: center; 
        }
        .btn-group .btn { font-weight: 500; }
        .btn-group .btn.active { background-color: #1275a0; color: white; }
        .doc-link {
            display: inline-block;
            margin-right: 10px;
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
    </style>
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <div class="header-logo"><img src="https://angelescuauhtemoc.com/wp-content/uploads/2020/09/logo-50-300x187.png" alt="Hospital Angeles"></div>
        </div>
        <div class="logo-section"><div class="logo-text">IMAGENOLOGÍA</div></div>
        <div class="header-right">
            <a href="index.php" class="btn-header"><i class="fas fa-calendar"></i> Calendario</a>
            <a href="panel_admin.php" class="btn-header"><i class="fas fa-cog"></i> Panel de Administración</a>
            <a href="logout.php" class="btn-header"><i class="fas fa-sign-out-alt"></i> Salir</a>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container-custom">
        <h1 class="page-title">Catálogo General de Citas</h1>
        
        <div class="actions-bar">
            <div class="d-flex align-items-center gap-3">
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
                            <th>Servicio</th>
                            <th>Modalidad</th>
                            <th>Origen</th>
                            <th>Documentos</th>
                            <th>Estado</th>
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
                // Determinar el origen de la cita
                const origen = (cita.tipo === 'individual' || cita.tipo === 'paquete') 
                    ? '<span class="origen-badge origen-online">En Línea</span>' 
                    : '<span class="origen-badge origen-interno">Caja/Interno</span>';

                // Generar enlaces a los documentos
                let docsHtml = '';
                if (cita.url_identificacion) {
                    docsHtml += `<a href="${cita.url_identificacion}" target="_blank" class="doc-link doc-ine" title="Ver Identificación"><i class="fas fa-id-card"></i> INE</a>`;
                }
                if (cita.url_orden_medica) {
                    docsHtml += `<a href="${cita.url_orden_medica}" target="_blank" class="doc-link doc-orden" title="Ver Orden Médica"><i class="fas fa-file-medical"></i> Orden</a>`;
                }
                if (!docsHtml) {
                    docsHtml = '<small class="text-muted">N/A</small>';
                }

                const tr = `
                    <tr>
                        <td>${cita.id}</td>
                        <td>${cita.fecha} <br><small>${cita.hora_inicio}</small></td>
                        <td>${cita.paciente_nombre_completo || 'N/A'}</td>
                        <td>${cita.servicio_nombre || 'N/A'}</td>
                        <td>${cita.modalidad_nombre || 'N/A'}</td>
                        <td>${origen}</td>
                        <td>${docsHtml}</td>
                        <td><span class="badge" style="background-color:${cita.estado_color || '#6c757d'}; color:white;">${cita.estado_nombre || 'Desconocido'}</span></td>
                    </tr>
                `;
                tbody.append(tr);
            });
        }
    </script>
</body>
</html>