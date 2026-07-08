<?php
session_start();
require_once 'includes/auth_check.php';

// Obtener información del usuario logueado
$user_nombre = $_SESSION['user_nombre'] ?? 'Usuario';
$user_tipo = $_SESSION['user_tipo'] ?? 'lectura';

// Verificar permisos
$puede_crear_citas = in_array($user_tipo, ['admin', 'caja']);
$puede_gestionar_usuarios = ($user_tipo === 'admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Angeles - Sistema de Citas</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fullcalendar/core@5.11.3/main.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fullcalendar/resource-timeline@5.11.3/main.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #000000;
            color: #e5e7eb;
            overflow-x: hidden;
        }

        /* Header Styles */
        .main-header {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            height: 80px;
            border-bottom: 1px solid rgba(41, 121, 255, 0.1);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            flex-direction: column;
        }
        
        .logo-section img {
            height: 50px;
            width: auto;
            filter: drop-shadow(1px 1px 2px rgba(0,0,0,0.1));
        }
        
        .logo-text {
            font-size: 14px;
            font-weight: 600;
            color: #ffffff;
            margin-top: 2px;
            letter-spacing: 0.5px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.05);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            color: #e5e7eb;
            font-size: 14px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .header-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-header {
            background: rgba(41, 121, 255, 0.1);
            color: #e5e7eb;
            border: 1px solid rgba(41, 121, 255, 0.2);
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-header:hover {
            background: rgba(41, 121, 255, 0.2);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.2);
        }

        /* Main Content */
        .main-content {
            margin-top: 80px;
            display: flex;
            height: calc(100vh - 80px);
        }

        /* Sidebar */
        .sidebar {
            width: 350px;
            background: #050505;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #2979ff #0a0a0a;
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: #050505;
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: #333;
            border-radius: 3px;
        }
        
        .filter-card {
            background: #0a0a0a;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.2s;
        }
        
        .filter-card:hover {
            box-shadow: 0 0 15px rgba(41, 121, 255, 0.1);
            transform: translateY(-1px);
        }
        
        .filter-card h5 {
            color: #e0e0e0;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            background: #000000;
            border: 1px solid #333;
            color: #e5e7eb;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            font-size: 13px;
            transition: all 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            background: #000000;
            color: #fff;
            border-color: #2979ff;
            box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2);
            outline: none;
        }

        /* Calendar Area */
        .calendar-area {
            flex: 1;
            padding: 1.5rem;
            background: #000000;
        }
        
        .calendar-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #0a0a0a;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .calendar-title {
            color: #ffffff;
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        /* FullCalendar Customizations */
        .fc {
            font-family: 'Inter', sans-serif;
            color: #e5e7eb;
        }
        
        .fc-button-primary {
            background: #111 !important;
            border-color: #333 !important;
            color: white !important;
            border-radius: 6px !important;
            font-weight: 500 !important;
            padding: 0.4rem 0.8rem !important;
        }
        
        .fc-button-primary:hover {
            background: #2979ff !important;
            border-color: #2979ff !important;
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.4);
        }
        
        .fc-event {
            border-radius: 4px !important;
            border: none !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.5) !important;
            font-size: 12px !important;
        }
        
        .fc-event:hover {
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.3) !important;
            transform: translateY(-1px);
        }
        
        .fc-header-toolbar {
            margin-bottom: 1.5rem !important;
        }
        
        .fc-today-button, .fc-prev-button, .fc-next-button {
            background: #111 !important;
            border-color: #333 !important;
            color: #e5e7eb !important;
        }
        
        .fc-today-button:hover, .fc-prev-button:hover, .fc-next-button:hover {
            background: #2979ff !important;
            border-color: #2979ff !important;
            color: #fff !important;
        }
        
        /* Dark mode for calendar grid */
        .fc-theme-standard td, .fc-theme-standard th {
            border-color: #333 !important;
        }
        
        .fc-timegrid-slot {
            background: #000000 !important;
        }
        
        .fc-col-header-cell {
            background: #0a0a0a !important;
            color: #e5e7eb !important;
        }

        /* Mini Calendars */
        .mini-calendar {
            background: transparent;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: none;
        }
        
        .mini-calendar .flatpickr-calendar {
            position: static !important;
            width: 100% !important;
            box-shadow: none !important;
            border: none !important;
            background: transparent !important;
            color: #e5e7eb !important;
        }
        
        .flatpickr-calendar {
            background: #0a0a0a !important;
            border: 1px solid #333 !important;
        }
        
        .flatpickr-day {
            color: #e5e7eb !important;
        }
        
        .flatpickr-day:hover {
            background: rgba(41, 121, 255, 0.2) !important;
        }
        
        .flatpickr-day.selected {
            background: #2979ff !important;
            border-color: #2979ff !important;
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.4) !important;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .btn-action {
            background: #111;
            color: white;
            border: 1px solid #333;
            border-radius: 6px;
            padding: 0.6rem 1.2rem;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-action:hover {
            background: #2979ff;
            border-color: #2979ff;
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.4);
            transform: translateY(-1px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                order: 2;
            }
            
            .calendar-area {
                order: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="logo-section">
            <img src="images/logo.png" alt="Hospital Angeles">
            <div class="logo-text">IMAGENOLOGÍA</div>
        </div>
        
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($user_nombre); ?></span>
            <span class="badge badge-secondary"><?php echo ucfirst($user_tipo); ?></span>
        </div>
        
        <div class="header-buttons">
            <?php if ($puede_gestionar_usuarios): ?>
                <a href="admin_usuarios.php" class="btn-header">
                    <i class="fas fa-users-cog"></i> Admin
                </a>
                <a href="#" onclick="abrirCatalogo()" class="btn-header">
                    <i class="fas fa-list"></i> Catálogo
                </a>
            <?php endif; ?>
            <a href="login.php?logout=1" class="btn-header">
                <i class="fas fa-sign-out-alt"></i> Salir
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Action Buttons -->
            <?php if ($puede_crear_citas): ?>
            <div class="action-buttons">
                <button type="button" class="btn-action" onclick="abrirModalAgendar()">
                    <i class="fas fa-plus"></i> Nueva Cita
                </button>
            </div>
            <?php endif; ?>
            
            <!-- Filtros -->
            <div class="filter-card">
                <h5>Modalidad</h5>
                <select id="profesional-select" class="form-control">
                    <option value="">Todos</option>
                </select>
            </div>
            
            <div class="filter-card">
                <h5>Estado</h5>
                <select id="estado-select" class="form-control">
                    <option value="">Todos</option>
                </select>
            </div>
            
            <!-- Mini Calendarios -->
            <div class="filter-card">
                <h5>Mes Actual</h5>
                <div id="calendar1" class="mini-calendar"></div>
            </div>
            
            <div class="filter-card">
                <h5>Próximo Mes</h5>
                <div id="calendar2" class="mini-calendar"></div>
            </div>
        </div>

        <!-- Calendar Area -->
        <div class="calendar-area">
            <div class="calendar-header">
                <h3 class="calendar-title">Agenda de Citas - Hospital Angeles</h3>
            </div>
            <div id="calendar"></div>
        </div>
    </div>

    <!-- Modal Agendar Cita -->
    <?php if ($puede_crear_citas): ?>
    <div class="modal fade" id="modalAgendar" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agendar Nueva Cita</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formAgendar">
                        <div class="row">
                            <!-- Fecha y Horario -->
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Fecha y Horario</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label>Fecha</label>
                                            <input type="date" class="form-control" id="fecha" name="fecha" required>
                                        </div>
                                        <div class="row">
                                            <div class="col-6">
                                                <label>Hora inicio</label>
                                                <input type="time" class="form-control" id="hora-inicio" name="hora_inicio" required>
                                            </div>
                                            <div class="col-6">
                                                <label>Hora fin</label>
                                                <input type="time" class="form-control" id="hora-fin" name="hora_fin" readonly 
                                                       style="background-color: #f8f9fa;">
                                                <div class="form-check mt-2">
                                                    <input type="checkbox" class="form-check-input" id="permitir-editar-hora">
                                                    <label class="form-check-label" for="permitir-editar-hora">
                                                        Permitir edición manual
                                                    </label>
                                                </div>
                                                <small id="hora-fin-help" class="form-text text-muted">
                                                    Se calcula automáticamente según el servicio
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Información del Paciente -->
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Información del Paciente</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label>Buscar paciente</label>
                                            <input type="text" class="form-control" id="paciente-search" 
                                                   placeholder="Escriba nombre o teléfono...">
                                            <div id="pacientes-dropdown" class="list-group mt-1" style="display: none; position: absolute; z-index: 1000; width: calc(100% - 30px);"></div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnMostrarRegistro">
                                                Registrar Nuevo Paciente
                                            </button>
                                        </div>

                                        <div id="seccionRegistroPaciente" style="display: none;">
                                            <div class="border-top pt-3">
                                                <h6>Registrar Nuevo Paciente</h6>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="nuevoPacienteNombre">Nombre:</label>
                                                            <input type="text" class="form-control" id="nuevoPacienteNombre">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="nuevoPacienteApellido">Apellido:</label>
                                                            <input type="text" class="form-control" id="nuevoPacienteApellido">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="nuevoPacienteTelefono">Teléfono:</label>
                                                            <input type="tel" class="form-control" id="nuevoPacienteTelefono">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="nuevoPacienteEmail">Email:</label>
                                                            <input type="email" class="form-control" id="nuevoPacienteEmail">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="nuevoPacienteTipo">Tipo de seguro/institución:</label>
                                                            <select class="form-control" id="nuevoPacienteTipo">
                                                                <option value="PARTICULAR">PARTICULAR</option>
                                                                <option value="IMSS">IMSS</option>
                                                                <option value="PENSIONES">PENSIONES</option>
                                                                <option value="ISSSTE">ISSSTE</option>
                                                                <option value="SEGURO POPULAR">SEGURO POPULAR</option>
                                                                <option value="PEMEX">PEMEX</option>
                                                                <option value="SEDENA">SEDENA</option>
                                                                <option value="SEMAR">SEMAR</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="nuevoPacienteOrigen">Origen:</label>
                                                            <select class="form-control" id="nuevoPacienteOrigen">
                                                                <option value="Consulta Externa">Consulta Externa</option>
                                                                <option value="Hospitalización">Hospitalización</option>
                                                                <option value="Urgencias">Urgencias</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="nuevoPacienteDiagnostico">Diagnóstico:</label>
                                                    <textarea class="form-control" id="nuevoPacienteDiagnostico" rows="2"></textarea>
                                                </div>
                                                <div class="text-right">
                                                    <button type="button" class="btn btn-secondary btn-sm" id="btnCancelarPaciente">Cancelar</button>
                                                    <button type="button" class="btn btn-primary btn-sm" id="btnGuardarPaciente">Guardar Paciente</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Modalidad y Servicio -->
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Modalidad y Servicio</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label>Modalidad</label>
                                            <select class="form-control" id="modalidad-select" name="modalidad_id" required>
                                                <option value="">Seleccione modalidad...</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Servicio</label>
                                            <select class="form-control" id="servicio-select" name="servicio_id" required>
                                                <option value="">Primero seleccione una modalidad</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Estado y Notas -->
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Estado de la Cita</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label>Estado</label>
                                            <select class="form-control" id="estado-cita" name="estado_id" required>
                                                <option value="1">Reservado</option>
                                                <option value="2">Confirmado</option>
                                                <option value="5">Pendiente</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Tipo</label>
                                            <select class="form-control" id="tipo-cita" name="tipo" required>
                                                <option value="cita">Cita</option>
                                                <option value="bloqueo">Bloqueo</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Información Adicional</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label>Nota interna</label>
                                            <textarea class="form-control" id="nota-interna" name="nota_interna" rows="2" 
                                                      placeholder="Notas para uso interno..."></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Nota para paciente</label>
                                            <textarea class="form-control" id="nota-paciente" name="nota_paciente" rows="2" 
                                                      placeholder="Instrucciones para el paciente..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" id="paciente-id" name="paciente_id">
                        <input type="hidden" id="profesional-id" name="profesional_id" value="1">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="guardarCita">Guardar Cita</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- JavaScript Dependencies -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/interaction@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/resource@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/resource-timeline@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/es@5.11.3/index.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js"></script>

    <script>

    // Styles for resource thumbnails (rounded square) — more specific to override FullCalendar defaults
    var style = document.createElement('style');
    style.innerHTML = `
        /* container for our custom resource content */
        .fc .fc-resource-custom { text-align:center; padding:6px 4px; box-sizing:border-box; }
        .fc .resource-thumb-wrap { text-align:center; padding-bottom:6px; }
    .fc .resource-thumb { width:48px; height:48px; object-fit:cover; border-radius:8px !important; border:1px solid rgba(0,0,0,0.08); display:inline-block; }
    .fc .resource-thumb-below { display:block; margin:6px auto 0; }
        .fc .resource-title { text-align:center; font-size:13px; padding-top:4px; white-space:normal; line-height:1.1; }
        /* Ensure resource header area can grow to accommodate thumbnail */
        .fc .fc-resource { white-space:normal !important; height:auto !important; min-height:64px !important; }
        .fc .fc-resource .fc-scrollgrid-section { overflow: visible !important; }
        .fc .fc-col-header-cell { height: auto !important; min-height:64px !important; }
    `;
    document.head.appendChild(style);

        // Variables globales
        let calendar;
        let pacientesList = [];
        let serviciosDisponibles = [];
        
        // Inicializar cuando la página carga
        $(document).ready(function() {
            initializeCalendar();
            loadFilters();
            loadPatients();
            setupEventListeners();
            setupMiniCalendars();
        });

        // Inicializar FullCalendar
        function initializeCalendar() {
            const calendarEl = document.getElementById('calendar');
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'resourceTimeGridWeek',
                locale: 'es',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'resourceTimeGridDay,resourceTimeGridWeek,dayGridMonth'
                },
                resources: 'citas/recursos_json.php',
                events: 'citas/citas_json.php',
                editable: <?php echo $puede_crear_citas ? 'true' : 'false'; ?>,
                selectable: <?php echo $puede_crear_citas ? 'true' : 'false'; ?>,
                selectMirror: true,
                dayMaxEvents: true,
                weekends: true,
                slotMinTime: "06:00:00",
                slotMaxTime: "22:00:00",
                slotDuration: "00:30:00",
                height: 'auto',
                contentHeight: 600,
                aspectRatio: 1.8,
                // Mostrar imagen encima del nombre de cada recurso/modalidad
                resourceLabelContent: function(arg) {
                    var img = arg.resource.extendedProps.imagen || arg.resource.imagen || '';
                    var container = document.createElement('div');
                    container.className = 'fc-resource-custom';
                    var titleDiv = document.createElement('div');
                    titleDiv.className = 'resource-title';
                    titleDiv.textContent = arg.resource.title || arg.resource.label || '';
                    container.appendChild(titleDiv);
                    if (img) {
                        var wrap = document.createElement('div'); wrap.className = 'resource-thumb-wrap';
                        var imgel = document.createElement('img'); imgel.className = 'resource-thumb resource-thumb-below';
                        imgel.src = img;
                        imgel.alt = arg.resource.title || '';
                        // hide broken images
                        imgel.onerror = function(){ this.style.display = 'none'; };
                        wrap.appendChild(imgel);
                        container.appendChild(wrap);
                    }
                    return { domNodes: [container] };
                },
                
                eventDidMount: function(info) {
                    // Añadir tooltip a cada evento
                    const tooltip = createTooltip(info.event);
                    $(info.el).attr('title', '').tooltip({
                        title: tooltip,
                        html: true,
                        placement: 'top',
                        trigger: 'hover'
                    });
                },
                
                select: function(info) {
                    <?php if ($puede_crear_citas): ?>
                    // Pre-llenar modal con fecha y hora seleccionada
                    document.getElementById('fecha').value = info.startStr.split('T')[0];
                    document.getElementById('hora-inicio').value = info.startStr.split('T')[1]?.slice(0,5) || '';
                    
                    // Buscar modalidad_id basado en el resourceId
                    const modalidadSelect = document.getElementById('modalidad-select');
                    for (let option of modalidadSelect.options) {
                        if (option.dataset.resourceId == info.resource.id) {
                            modalidadSelect.value = option.value;
                            cargarServiciosPorModalidad();
                            break;
                        }
                    }
                    
                    $('#modalAgendar').modal('show');
                    <?php endif; ?>
                },
                
                eventClick: function(info) {
                    // Manejar click en evento existente
                    console.log('Clicked event:', info.event);
                }
            });
            
            calendar.render();

            // After render, try to inject images into resource headers (robust fallback)
            setTimeout(function(){
                try { injectResourceImages(); } catch(e){ console.warn('injectResourceImages error', e); }
            }, 250);

            // Also re-apply when window resizes or when view changes
            window.addEventListener('resize', function(){ setTimeout(injectResourceImages, 300); });
            // Observe calendar mutations (FullCalendar may re-render headers)
            try {
                var calEl = calendarEl;
                var mo = new MutationObserver(function(m){
                    // debounce
                    if (window._injectTimeout) clearTimeout(window._injectTimeout);
                    window._injectTimeout = setTimeout(function(){ injectResourceImages(); }, 150);
                });
                mo.observe(calEl, { childList:true, subtree:true });
            } catch(e){ console.warn('MutationObserver not available', e); }
        }

        // Crear tooltip para eventos
        function createTooltip(event) {
            const props = event.extendedProps;
            return `
                <div class="tooltip-content">
                    <strong>${event.title}</strong><br>
                    <i class="fas fa-clock"></i> <strong>Hora:</strong> ${event.start.toLocaleTimeString('es-ES', {hour: '2-digit', minute:'2-digit'})} - 
                    ${event.end ? event.end.toLocaleTimeString('es-ES', {hour: '2-digit', minute:'2-digit'}) : 'N/A'}<br>
                    ${props.servicio ? `<i class="fas fa-stethoscope"></i> <strong>Servicio:</strong> ${props.servicio}<br>` : ''}
                    ${props.tipo_paciente ? `<i class="fas fa-hospital"></i> <strong>Tipo:</strong> ${props.tipo_paciente}<br>` : ''}
                    ${props.telefono ? `<i class="fas fa-phone"></i> <strong>Teléfono:</strong> ${props.telefono}<br>` : ''}
                    ${props.diagnostico ? `<i class="fas fa-notes-medical"></i> <strong>Diagnóstico:</strong> ${props.diagnostico}` : ''}
                </div>
            `;
        }

        // Cargar filtros
        function loadFilters() {
            // Cargar modalidades
            fetch('citas/recursos_json.php')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('profesional-select');
                    data.forEach(modalidad => {
                        const option = document.createElement('option');
                        option.value = modalidad.id;
                        option.textContent = modalidad.title;
                        option.dataset.resourceId = modalidad.id;
                        select.appendChild(option);
                        
                        // También agregar al modal de agendar
                        const modalSelect = document.getElementById('modalidad-select');
                        if (modalSelect) {
                            const modalOption = document.createElement('option');
                            modalOption.value = modalidad.id;
                            modalOption.textContent = modalidad.title;
                            modalOption.dataset.resourceId = modalidad.id;
                            modalSelect.appendChild(modalOption);
                        }
                    });
                });

            // Cargar estados
            const estados = [
                {id: 1, nombre: 'Reservado'},
                {id: 2, nombre: 'Confirmado'},
                {id: 3, nombre: 'Asistió'},
                {id: 4, nombre: 'No asistió'},
                {id: 5, nombre: 'Pendiente'},
                {id: 6, nombre: 'En espera'}
            ];
            
            const estadoSelect = document.getElementById('estado-select');
            estados.forEach(estado => {
                const option = document.createElement('option');
                option.value = estado.id;
                option.textContent = estado.nombre;
                estadoSelect.appendChild(option);
            });
        }

        function injectResourceImages(){
            if (!calendar) return;
            // calendar.getResources() is available in FullCalendar v5
            var resources = [];
            try { resources = calendar.getResources(); } catch(e){ resources = []; }
            console.debug('injectResourceImages: resources count', resources.length);
            var attempts = 0;
            var maxAttempts = 6;

            function doInject(){
                attempts++;
                var injected = 0;
                resources.forEach(function(res){
                var img = (res.extendedProps && (res.extendedProps.imagen || res.imagen)) || '';
                if (!img) return;
                var id = res.id;
                // prefer element with data-resource-id
                var el = document.querySelector('[data-resource-id="'+id+'"]');
                var usedSelector = null;
                var tried = [];
                var selectors = [
                    '[data-resource-id="'+id+'"]',
                    '.fc-col-header-cell[data-resource-id="'+id+'"]',
                    '.fc-resource[data-resource-id="'+id+'"]',
                    '.fc-resource-area [data-resource-id="'+id+'"]',
                    '.fc-scroller [data-resource-id="'+id+'"]'
                ];
                for (var s=0; s<selectors.length && !el; s++){
                    tried.push(selectors[s]);
                    el = document.querySelector(selectors[s]);
                }
                // as last resort, try to match by title text inside header/resource nodes
                if (!el) {
                    var nodes = document.querySelectorAll('.fc-col-header-cell, .fc-resource, .fc-resource-area, .fc-scroller');
                    nodes.forEach(function(n){ if (!el && n.textContent && n.textContent.trim().indexOf(res.title) !== -1) el = n; });
                }
                if (!el) {
                    console.debug('injectResourceImages: no element found for resource', id, 'title', res.title, 'tried', tried);
                    return;
                }
                // avoid duplicates
                if (el.querySelector('.fc-resource-img')) { injected++; return; }
                var imgEl = document.createElement('img');
                imgEl.src = img;
                imgEl.className = 'fc-resource-img';
                imgEl.style.width = '40px'; imgEl.style.height = '40px'; imgEl.style.objectFit = 'cover'; imgEl.style.borderRadius = '6px'; imgEl.style.display = 'block'; imgEl.style.margin = '6px auto 0';
                imgEl.onerror = function(){ this.style.display = 'none'; };
                // try to insert after title-like element if exists
                var titleCandidate = el.querySelector('.resource-title, .fc-col-header-cell-cushion, .fc-col-header-cell-main, .fc-resource-title');
                if (titleCandidate && titleCandidate.parentNode) {
                    titleCandidate.parentNode.insertBefore(imgEl, titleCandidate.nextSibling);
                } else {
                    el.appendChild(imgEl);
                }
                injected++;
            });
                console.debug('injectResourceImages attempt', attempts, 'injected', injected);
                if (injected === 0 && attempts < maxAttempts) {
                    setTimeout(doInject, 400 * attempts);
                }
            }
            doInject();
            }

        // Cargar pacientes
        function loadPatients() {
            fetch('citas/pacientes_json.php')
                .then(response => response.json())
                .then(data => {
                    pacientesList = data;
                })
                .catch(error => console.error('Error loading patients:', error));
        }

        // Cargar servicios por modalidad
        function cargarServiciosPorModalidad() {
            const modalidadSelect = document.getElementById('modalidad-select');
            const servicioSelect = document.getElementById('servicio-select');
            const modalidadId = modalidadSelect.value;
            
            // Limpiar servicios
            servicioSelect.innerHTML = '<option value="">Seleccione un servicio...</option>';
            
            if (!modalidadId) {
                servicioSelect.innerHTML = '<option value="">Primero seleccione una modalidad</option>';
                return;
            }
            
            fetch(`citas/servicios_por_modalidad.php?modalidad_id=${modalidadId}`)
                .then(response => response.json())
                .then(data => {
                    serviciosDisponibles = data;
                    data.forEach(servicio => {
                        const option = document.createElement('option');
                        option.value = servicio.id;
                        option.textContent = servicio.duracion_minutos ? 
                            `${servicio.nombre} (${servicio.duracion_minutos} min)` : 
                            servicio.nombre;
                        option.dataset.duracion = servicio.duracion_minutos || 0;
                        servicioSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    servicioSelect.innerHTML = '<option value="">Error al cargar servicios</option>';
                });
        }

        // Calcular hora fin basada en duración del servicio
        function calcularHoraFin() {
            const permitirEditar = document.getElementById('permitir-editar-hora').checked;
            if (permitirEditar) return; // No calcular si está en modo manual
            
            const horaInicioInput = document.getElementById('hora-inicio');
            const horaFinInput = document.getElementById('hora-fin');
            const servicioSelect = document.getElementById('servicio-select');
            
            const horaInicio = horaInicioInput.value;
            const servicioOption = servicioSelect.selectedOptions[0];
            
            if (horaInicio && servicioOption && servicioOption.dataset.duracion) {
                const duracionMinutos = parseInt(servicioOption.dataset.duracion);
                
                // Convertir hora inicio a minutos
                const [horas, minutos] = horaInicio.split(':').map(num => parseInt(num));
                const totalMinutos = (horas * 60) + minutos + duracionMinutos;
                
                // Convertir de vuelta a formato HH:MM
                const nuevasHoras = Math.floor(totalMinutos / 60);
                const nuevosMinutos = totalMinutos % 60;
                
                const horaFin = `${nuevasHoras.toString().padStart(2, '0')}:${nuevosMinutos.toString().padStart(2, '0')}`;
                horaFinInput.value = horaFin;
            }
        }

        // Configurar event listeners
        function setupEventListeners() {
            // Filtros
            $('#profesional-select, #estado-select').on('change', function() {
                // Refrescar calendar con filtros
                calendar.refetchEvents();
            });

            <?php if ($puede_crear_citas): ?>
            // Modal de agendar
            $('#modalidad-select').on('change', cargarServiciosPorModalidad);
            $('#servicio-select, #hora-inicio').on('change', calcularHoraFin);
            
            // Checkbox para editar hora fin
            $('#permitir-editar-hora').on('change', function() {
                toggleEditarHoraFin();
            });

            // Búsqueda de pacientes
            $('#paciente-search').on('input', function() {
                const query = $(this).val().toLowerCase();
                if (query.length >= 2) {
                    const filtered = pacientesList.filter(p => 
                        p.nombre_completo.toLowerCase().includes(query) || 
                        p.telefono.includes(query)
                    );
                    renderPacientesDropdown(filtered);
                } else {
                    $('#pacientes-dropdown').hide();
                }
            });

            // Registro de pacientes
            $('#btnMostrarRegistro').click(function() {
                $('#seccionRegistroPaciente').show();
                $(this).hide();
            });

            $('#btnCancelarPaciente').click(function() {
                $('#seccionRegistroPaciente').hide();
                $('#btnMostrarRegistro').show();
                limpiarFormularioPaciente();
            });

            $('#btnGuardarPaciente').click(function() {
                guardarNuevoPaciente();
            });

            // Guardar cita
            $('#guardarCita').click(function() {
                guardarCita();
            });
            <?php endif; ?>
        }

        // Toggle editar hora fin
        function toggleEditarHoraFin() {
            const checkbox = document.getElementById('permitir-editar-hora');
            const horaFinInput = document.getElementById('hora-fin');
            const helpText = document.getElementById('hora-fin-help');
            
            if (checkbox.checked) {
                // Modo manual
                horaFinInput.readOnly = false;
                horaFinInput.style.backgroundColor = 'white';
                helpText.innerHTML = 'Puede editar manualmente para ajustar el tiempo si es necesario';
                helpText.className = 'form-text text-success';
            } else {
                // Modo automático
                horaFinInput.readOnly = true;
                horaFinInput.style.backgroundColor = '#f8f9fa';
                helpText.innerHTML = 'Se calcula automáticamente según el servicio';
                helpText.className = 'form-text text-muted';
                // Recalcular automáticamente
                calcularHoraFin();
            }
        }

        // Render dropdown de pacientes
        function renderPacientesDropdown(pacientes) {
            const dropdown = $('#pacientes-dropdown');
            if (pacientes.length === 0) {
                dropdown.hide();
                return;
            }
            
            let html = '';
            pacientes.forEach(paciente => {
                html += `
                    <a href="#" class="list-group-item list-group-item-action" 
                       onclick="seleccionarPaciente(${paciente.id}, '${paciente.nombre_completo}')">
                        <div><strong>${paciente.nombre_completo}</strong></div>
                        <small class="text-muted">${paciente.telefono}</small>
                    </a>
                `;
            });
            
            dropdown.html(html).show();
        }

        // Seleccionar paciente
        function seleccionarPaciente(id, nombre) {
            document.getElementById('paciente-id').value = id;
            document.getElementById('paciente-search').value = nombre;
            $('#pacientes-dropdown').hide();
        }

        // Limpiar formulario de paciente
        function limpiarFormularioPaciente() {
            $('#nuevoPacienteNombre, #nuevoPacienteApellido, #nuevoPacienteTelefono, #nuevoPacienteEmail, #nuevoPacienteDiagnostico').val('');
            $('#nuevoPacienteTipo').val('PARTICULAR');
            $('#nuevoPacienteOrigen').val('Consulta Externa');
        }

        // Guardar nuevo paciente
        function guardarNuevoPaciente() {
            const pacienteData = {
                nombre: $('#nuevoPacienteNombre').val(),
                apellido: $('#nuevoPacienteApellido').val(),
                telefono: $('#nuevoPacienteTelefono').val(),
                email: $('#nuevoPacienteEmail').val(),
                tipo: $('#nuevoPacienteTipo').val(),
                origen: $('#nuevoPacienteOrigen').val(),
                diagnostico: $('#nuevoPacienteDiagnostico').val()
            };
            
            if (!pacienteData.nombre || !pacienteData.apellido) {
                alert('Nombre y apellido son obligatorios');
                return;
            }
            
            fetch('citas/guardar_paciente.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(pacienteData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Usar el nuevo paciente
                    document.getElementById('paciente-id').value = data.paciente_id;
                    document.getElementById('paciente-search').value = `${pacienteData.nombre} ${pacienteData.apellido}`;
                    
                    // Ocultar formulario
                    $('#seccionRegistroPaciente').hide();
                    $('#btnMostrarRegistro').show();
                    limpiarFormularioPaciente();
                    
                    // Recargar lista de pacientes
                    loadPatients();
                    
                    alert('Paciente registrado exitosamente');
                } else {
                    alert('Error al registrar paciente: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al registrar paciente');
            });
        }

        // Guardar cita
        function guardarCita() {
            const form = document.getElementById('formAgendar');
            const formData = new FormData(form);
            
            // Validaciones básicas
            if (!formData.get('paciente_id')) {
                alert('Debe seleccionar un paciente');
                return;
            }
            
            if (!formData.get('fecha') || !formData.get('hora_inicio') || !formData.get('hora_fin')) {
                alert('Fecha y horas son obligatorias');
                return;
            }
            
            if (!formData.get('modalidad_id') || !formData.get('servicio_id')) {
                alert('Modalidad y servicio son obligatorios');
                return;
            }
            
            // Convertir FormData a objeto
            const citaData = {};
            for (let [key, value] of formData.entries()) {
                citaData[key] = value;
            }
            
            fetch('citas/guardar_cita.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(citaData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#modalAgendar').modal('hide');
                    calendar.refetchEvents();
                    limpiarModalAgendar();
                    alert('Cita guardada exitosamente');
                } else {
                    alert('Error al guardar cita: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al guardar cita');
            });
        }

        // Limpiar modal agendar
        function limpiarModalAgendar() {
            document.getElementById('formAgendar').reset();
            document.getElementById('paciente-id').value = '';
            document.getElementById('paciente-search').value = '';
            document.getElementById('servicio-select').innerHTML = '<option value="">Primero seleccione una modalidad</option>';
            document.getElementById('permitir-editar-hora').checked = false;
            toggleEditarHoraFin();
            $('#pacientes-dropdown').hide();
            $('#seccionRegistroPaciente').hide();
            $('#btnMostrarRegistro').show();
        }

        // Configurar mini calendarios
        function setupMiniCalendars() {
            const today = new Date();
            const nextMonth = new Date(today.getFullYear(), today.getMonth() + 1, 1);
            
            flatpickr("#calendar1", {
                inline: true,
                locale: "es",
                defaultDate: today,
                static: true,
                disableMobile: true,
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length > 0) {
                        calendar.gotoDate(selectedDates[0]);
                    }
                }
            });
            
            flatpickr("#calendar2", {
                inline: true,
                locale: "es", 
                defaultDate: nextMonth,
                static: true,
                disableMobile: true,
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length > 0) {
                        calendar.gotoDate(selectedDates[0]);
                    }
                }
            });
        }

        // Funciones para botones del header
        function abrirModalAgendar() {
            $('#modalAgendar').modal('show');
        }
        
        function abrirCatalogo() {
            window.location.href = 'catalogo_servicios.php';
        }

        // Limpiar modal cuando se abre
        $('#modalAgendar').on('shown.bs.modal', function() {
            limpiarModalAgendar();
        });

        // Limpiar modal cuando se cierra
        $('#modalAgendar').on('hidden.bs.modal', function() {
            limpiarModalAgendar();
        });
    </script>
</body>
</html>