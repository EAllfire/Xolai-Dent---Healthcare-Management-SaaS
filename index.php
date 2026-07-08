<?php
session_start();
require_once("includes/db.php");

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
require_once("includes/auth.php");
$es_admin_o_principal = ($user_tipo === 'admin' || $user_tipo === 'superadmin' || ($user_tipo === 'dentista' && empty($_SESSION['id_padre'])));

$puede_crear_citas = puedeRealizar('crear_citas') || $es_admin_o_principal;
$puede_editar_citas = puedeRealizar('editar_citas') || $es_admin_o_principal;
$puede_eliminar_citas = puedeRealizar('eliminar_citas') || $es_admin_o_principal;
$puede_ver_administracion = in_array($user_tipo, ['admin', 'medico', 'dentista', 'superadmin']);
$id_padre = intval($_SESSION['id_padre'] ?? 0);
$es_dentista_principal = ($user_tipo === 'dentista' && $id_padre === 0);
$es_admin_derivado = ($user_tipo === 'admin' && $id_padre > 0);
$puede_configurar_horarios = in_array($user_tipo, ['admin', 'medico', 'dentista', 'superadmin']); // Nuevo permiso

// Cargar lista de doctores/medicos disponibles para bloqueo de agenda
$doctores = [];
if ($user_tipo === 'superadmin') {
    $stmt = $conn->prepare("SELECT id, nombre, tipo FROM agenda_usuarios WHERE tipo IN ('medico','dentista') ORDER BY nombre ASC");
    $stmt->execute();
} else {
    $owner_id = $id_padre > 0 ? $id_padre : $user_id;
    $stmt = $conn->prepare("SELECT id, nombre, tipo FROM agenda_usuarios WHERE tipo IN ('medico','dentista') AND (id = ? OR id_padre = ?) ORDER BY nombre ASC");
    $stmt->bind_param("ii", $owner_id, $owner_id);
    $stmt->execute();
}
if ($stmt) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $doctores[] = $row;
    }
    $stmt->close();
}

// --- Cargar Configuración de Horarios (Solo Admin) ---
$admin_slot_interval = '30';
$admin_blocked_times = '[]';

// Cargar configuración para TODOS (para visualización correcta)
$res_conf = $conn->query("SELECT config_key, config_value FROM agenda_configuracion");
if ($res_conf) {
    while ($r_conf = $res_conf->fetch_assoc()) {
        if ($r_conf['config_key'] == 'slot_interval') $admin_slot_interval = $r_conf['config_value'];
        if ($r_conf['config_key'] == 'blocked_times') $admin_blocked_times = $r_conf['config_value'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xolai</title>
    <link rel="icon" type="image/png" href="images/logo2.png">
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- FULLCALENDAR CSS LOCAL -->
    <link href="fullcalendar-php-app/assets/css/core.css" rel="stylesheet">
    <link href="fullcalendar-php-app/assets/css/timegrid.css" rel="stylesheet">
    <link href="fullcalendar-php-app/assets/css/resource-timegrid.css" rel="stylesheet">
    <!-- Flatpickr CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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
            opacity: 0.30;
            z-index: -1;
            pointer-events: none;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #000000;
            color: #e5e7eb;
            overflow-x: hidden;
            padding-top: 0;
        }

        /* Header Styles */
        .main-header {
            background: rgba(10, 10, 10, 0.5); /* Un fondo ligeramente más oscuro para mejorar el contraste del blur */
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
            /* Esta máscara crea el efecto de difuminado en el borde inferior, eliminando la línea dura */
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

        
        .header-logo img {
            height: 45px;
            width: auto;
            filter: drop-shadow(1px 1px 2px rgba(0,0,0,0.1)) brightness(1.1);
        }
        
        .header-title {
           
            font-size: 24px;
            font-weight: 700;
            color: white;
            letter-spacing: 1px
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

    /* Variable-controlled slot height for responsive spacing */
    :root { --fc-slot-height: 40px; }

    /* Apply variable to FullCalendar timegrid slots and axis labels */
    .fc .fc-timegrid-slot,
    .fc .fc-timegrid-slot-lane,
    .fc .fc-timegrid-slot .fc-timegrid-slot-lane {
      height: var(--fc-slot-height) !important;
      min-height: var(--fc-slot-height) !important;
      line-height: var(--fc-slot-height) !important;
    }

    .fc .fc-timegrid-axis .fc-timegrid-slot-label {
      height: var(--fc-slot-height) !important;
      line-height: var(--fc-slot-height) !important;
      display: flex;
      align-items: center;
      justify-content: center;
        }
    /* Hover highlight for timegrid cells */
    .fc .fc-timegrid-slot-lane:hover,
    .fc .fc-timegrid-slot:hover {
      background-color: rgba(41, 121, 255, 0.1) !important; /* neon blue tint */
      cursor: pointer;
    }

    /* Small tooltip that shows the time on hover */
    .fc-timecell-tooltip {
      position: fixed;
      z-index: 12000;
      background: #0a0a0a;
      border: 1px solid rgba(41, 121, 255, 0.3);
      box-shadow: 0 6px 18px rgba(0,0,0,0.5);
      padding: 6px 8px;
      font-size: 12px;
      color: #e5e7eb;
      border-radius: 6px;
      pointer-events: none;
      display: none;
    }
        
        .user-type {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .header-buttons {
            display: flex;
            gap: 0.5rem;
        }

    /* Layout for main calendar + sidebar (mini calendars) */
    .calendar-layout { display:flex; gap:12px; align-items:flex-start; }
    .calendar-main { flex: 1 1 auto; min-width: 300px; }
    .calendar-side { width: 320px; flex: 0 0 320px; }
    .resizer { width: 8px; cursor: col-resize; background: transparent; }
    .resizer:hover { background: rgba(0,0,0,0.05); }

    /* Make mini calendars smaller */
    .mini-calendar { max-width: 280px; font-size: 12px; }

        
        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #e5e7eb;
            cursor: pointer;
        }
        
        /* Responsive Header */
        @media (max-width: 768px) {
            
            .mobile-menu-btn {
                display: block;
            }
            
            .header-center { display: none } 
        }
        
        @media (max-width: 480px) {
           
         
      
            
            .user-info span:not(.fas) {
                display: none;
            }
            
            
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
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-header:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.25);
            box-shadow: none;
        }

        /* Main Content */
        .main-content {
            margin-top: 0;
            display: flex; /* Asegura que los elementos internos se distribuyan en fila */
            height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 380px;
            background: #000000;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            padding: 100px 1.5rem 1.5rem 1.5rem;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #2979ff #0a0a0a;
            transition: transform 0.3s ease-in-out;
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: #000000;
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: #333;
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.6);
        }
        
        /* Modern Select Styles */
        select, .form-control select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background: #0a0a0a;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 10px 40px 10px 12px;
            font-size: 14px;
            color: #e5e7eb;
            transition: all 0.2s ease;
            cursor: pointer;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.5);
        }
        
        select:hover {
            border-color: #2979ff;
            box-shadow: 0 0 5px rgba(41, 121, 255, 0.2);
            background-color: #000000;
        }
        
        select:focus {
            outline: none;
            border-color: #2979ff;
            box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2);
            background-color: #000000;
        }
        
        select:disabled {
            background-color: #1f242f;
            color: #6b7280;
            cursor: not-allowed;
        }
        
        /* Form Control Override */
        .form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background: #0a0a0a;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 10px 40px 10px 12px;
            font-size: 14px;
            color: #e5e7eb;
            transition: all 0.2s ease;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.5);
        }
        
        .form-control:hover {
            border-color: #2979ff;
            box-shadow: 0 0 5px rgba(41, 121, 255, 0.2);
            background-color: #000000;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #2979ff;
            box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2);
            background-color: #000000;
        }
        
        /* Calendar Area */
        .calendar-area {
            flex: 1;
            padding: 100px 2rem 2rem 2rem;
            background: #000000;
            overflow-y: auto;
        }
        
        /* Responsive Sidebar */
        @media (max-width: 1024px) {
            .sidebar {
                width: 280px;
                padding: 1rem;
            }
            
            .calendar-area {
                padding: 1.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-top: 0;
                height: 100vh;
            }
            
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                width: 300px;
                z-index: 1040;
                transform: translateX(-100%);
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .calendar-area {
                width: 100%;
                padding: 1rem;
            }
            
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 1039;
                display: none;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                margin-top: 0;
                height: 100vh;
            }
            
            .sidebar {
                top: 0;
                height: 100vh;
                width: 280px;
                padding: 100px 0.75rem 1rem 0.75rem;
            }
            
            .calendar-area {
                padding: 100px 0.75rem 0.75rem 0.75rem;
            }
            
            /* Mini calendarios en mobile */
            .mini-calendar {
                max-width: 240px !important;
            }
            
            .mini-calendar .flatpickr-calendar {
                max-width: 240px !important;
            }
            
            .mini-calendar .flatpickr-calendar .flatpickr-day {
                width: 30px !important;
                max-width: 30px !important;
                height: 30px !important;
                line-height: 30px !important;
                font-size: 11px !important;
            }
        }

        .filter-card {
            background: #0a0a0a;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
            /* backdrop-filter: blur(10px); removed for solid dark look */
        }
        
        /* Contenedor unificado para todos los controles */
        .unified-controls {
            background: #0a0a0a;
            border-radius: 16px;
            padding: 1.5rem 0.75rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
            backdrop-filter: blur(10px);
        }
        
        /* Estilos para el Resizer */
        .sidebar-resizer {
            width: 8px;
            cursor: ew-resize;
            background-color: #0a0a0a; /* Color sutil para la línea */
            flex-shrink: 0; /* Evita que se encoja */
            transition: background-color 0.2s ease;
        }
        .sidebar-resizer:hover { background-color: #2979ff; }

        /* Estilos para la Leyenda de Colores */
        .leyenda-estados {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding-top: 10px;
        }
        
        .leyenda-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .leyenda-color {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            flex-shrink: 0;
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .leyenda-texto {
            font-size: 14px;
            color: #e5e7eb;
        }
        .control-section {
            margin-bottom: 2rem;
            position: relative;
        }
        
        .control-section:last-child {
            margin-bottom: 0;
        }
        
        .control-section::after {
            content: '';
            position: absolute;
            bottom: -1rem;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, rgba(41, 121, 255, 0.2) 20%, rgba(41, 121, 255, 0.2) 80%, transparent 100%);
        }
        
        .control-section:last-child::after {
            display: none;
        } 
        
        .control-title {
            color: #2979ff;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-shadow: 0 0 8px rgba(41, 121, 255, 0.3);
        }
        
        .control-title i {
            font-size: 16px;
            color: #2979ff;
        }
        
        .filter-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
            border-left: 3px solid #2979ff;
            padding-left: 0.75rem;
        }
        
        .form-control, .form-select {
            border: 1px solid #333333;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            font-size: 13px;
            background: #000000;
            color: #e5e7eb;
            transition: all 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #1f2937;
            box-shadow: 0 0 0 2px rgba(31, 41, 55, 0.1);
            outline: none;
        }

        /* Calendar Area */
        .calendar-area {
            flex: 1;
            padding: 100px 2rem 2rem 2rem;
            background: #000000;
        }

        /* Mini Calendarios */
        .mini-calendar {
            background: transparent;
            border-radius: 0;
            padding: 0;
            box-shadow: none;
            margin: 0;
            width: 100%;
        }
        
        .mini-calendar .flatpickr-calendar {
            position: static !important;
            width: 100% !important;
            box-shadow: none !important;
            border: none !important;
            background: transparent !important;
            backdrop-filter: none;
            margin: 0 !important;
        }

        .mini-calendar .flatpickr-calendar .flatpickr-months {
            background: transparent !important;
            border-radius: 0;
            padding: 8px 0;
            margin-bottom: 8px;
        }
        
        .mini-calendar .flatpickr-monthDropdown-months {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background: #000000 !important;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 8px center !important;
            background-size: 12px !important;
            border: 1px solid #333 !important;
            border-radius: 6px !important;
            padding: 6px 25px 6px 10px !important;
            font-size: 13px !important;
            color: #ffffff !important;
            transition: all 0.2s ease !important;
            cursor: pointer !important;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.5) !important;
        }
        
        .mini-calendar .flatpickr-monthDropdown-months:hover {
            border-color: #2979ff !important;
            box-shadow: 0 0 5px rgba(41, 121, 255, 0.2) !important;
            background-color: #000000 !important;
        }
        
        .mini-calendar .flatpickr-monthDropdown-months:focus {
            outline: none !important;
            border-color: #2979ff !important;
            box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2) !important;
        }
        
        /* Estilo moderno para el input de años en Flatpickr */
        .mini-calendar .numInput.flatpickr-year {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background: #000000 !important;
            border: 1px solid #333 !important;
            border-radius: 6px !important;
            padding: 6px 8px !important;
            font-size: 13px !important;
            color: #e5e7eb !important;
            transition: all 0.2s ease !important;
            width: 60px !important;
            text-align: center !important;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.5) !important;
        }
        
        .mini-calendar .numInput.flatpickr-year:hover {
            border-color: #2979ff !important;
            box-shadow: 0 0 5px rgba(41, 121, 255, 0.2) !important;
            background-color: #000000 !important;
        }
        
        .mini-calendar .numInput.flatpickr-year:focus {
            outline: none !important;
            border-color: #2979ff !important;
            box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2) !important;
        }

        .mini-calendar .flatpickr-calendar .flatpickr-weekdays {
            background: transparent !important;
            margin-bottom: 4px;
        }
        
        .mini-calendar .flatpickr-calendar .flatpickr-weekday {
            background: transparent !important;
            color: #9ca3af !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            padding: 4px 0 !important;
        }

        .mini-calendar .flatpickr-calendar .flatpickr-days {
            width: 100% !important;
            background: transparent !important;
        }

        .mini-calendar .flatpickr-calendar .flatpickr-day {
            width: 14.28% !important;
            height: 35px !important;
            line-height: 35px !important;
            margin: 1px !important;
            background: transparent !important;
            color: #ffffff !important;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 400;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .mini-calendar .flatpickr-calendar .flatpickr-day:hover {
            background: rgba(41, 121, 255, 0.2) !important;
            color: #fff !important;
            transform: scale(1.1);
        }

        .mini-calendar .flatpickr-calendar .flatpickr-day.today {
            background: rgba(41, 121, 255, 0.1) !important;
            color: #2979ff !important;
            border: 1px solid #2979ff !important;
            font-weight: 600;
        }

        .mini-calendar .flatpickr-calendar .flatpickr-day.selected {
            background: #2979ff !important;
            color: white !important;
            font-weight: 600 !important;
            box-shadow: 0 0 8px rgba(41, 121, 255, 0.5) !important;
        }

        .mini-calendar .flatpickr-calendar .flatpickr-day.selected:hover {
            background: #2962ff !important;
            transform: scale(1.1);
        }
        
        .mini-calendar .flatpickr-calendar .flatpickr-day.nextMonthDay,
        .mini-calendar .flatpickr-calendar .flatpickr-day.prevMonthDay {
            color: #333 !important;
        }
        
        .mini-calendar .flatpickr-calendar .flatpickr-day.disabled {
            color: #333 !important;
            cursor: not-allowed !important;
        }
        
        /* Estilos generales Flatpickr oscuros */
        .mini-calendar .flatpickr-innerContainer {
            background: transparent !important;
        }
        
        .mini-calendar .flatpickr-rContainer {
            background: transparent !important;
        }
        
        .mini-calendar input.flatpickr-input {
            background: #000000 !important;
            color: #e5e7eb !important;
        }
        
        /* Fuerza Flatpickr general a oscuro */
        .flatpickr-calendar {
            background: #0a0a0a !important;
            color: #e5e7eb !important;
            border: 1px solid #333 !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important;
        }
        .flatpickr-calendar .flatpickr-month {
            background: #0a0a0a !important;
        }
        .flatpickr-calendar .flatpickr-next,
        .flatpickr-calendar .flatpickr-prev {
            color: #e5e7eb !important;
        }
        .flatpickr-calendar .flatpickr-day {
            background: transparent !important;
            color: #ffffff !important;
        }
        .flatpickr-calendar .flatpickr-day:hover {
            background: rgba(41, 121, 255, 0.2) !important;
            color: #ffffff !important;
        }
        .flatpickr-monthDropdown-months,
        .flatpickr-weekday,
        .numInput.flatpickr-year {
            color: #ffffff !important;
        }
        .flatpickr-calendar .flatpickr-weekday {
            color: #9ca3af !important;
        }
        
        /* Ajustar títulos de los mini calendarios */
        .filter-card h5 {
            color: #e0e0e0;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Línea de tiempo actual personalizada */
        .fc-timegrid-now-indicator-line {
            border-color: #ff0055 !important;
            border-width: 2px !important;
            box-shadow: 0 0 8px rgba(255, 0, 85, 0.6) !important;
        }
        
        .fc-timegrid-now-indicator-arrow {
            border-left-color: #ff0055 !important;
            border-right-color: #ff0055 !important;
        }
        
        /* Mejorar visibilidad de la línea de tiempo */
        .fc-timegrid-slot {
      position: relative;
      padding: 0 !important;
        }
        
        .fc-timegrid-now-indicator-container {
            z-index: 100 !important;
        }

        /* FullCalendar Customizations */
        .fc {
            font-family: 'Inter', sans-serif;
            background: #0a0a0a !important;
        }
        
        #calendar {
            background: #0a0a0a !important;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .fc-view-harness {
            background: #0a0a0a !important;
        }
        
        .fc-scrollgrid {
            background: #0a0a0a !important;
            border-color: rgba(255, 255, 255, 0.05) !important;
        }
        
        /* Estilos para recursos y modalidades */
        .fc-resource-area {
            background: #0a0a0a !important;
            border-right: 1px solid rgba(255, 255, 255, 0.05) !important;
        }
        
        .fc-resource-area-header {
            background: #0a0a0a !important;
            font-weight: 600 !important;
            color: #9ca3af !important;
        }
        
        /* Mejorar detección de clics en eventos */
        .fc-event {
            cursor: pointer !important;
            pointer-events: auto !important; /* Mantener para que sea clickeable */
        }
        
        .fc-event-main {
            pointer-events: auto !important;
        }
        
        .fc-event-title {
            pointer-events: none !important;
        }
        
        .fc-event-time {
            pointer-events: none !important;
        }
        
        /* Asegurar que los eventos sean clickeables en todas las vistas */
        .fc-timegrid-event {
            pointer-events: auto !important;
            cursor: pointer !important;
        }
        
        .fc-timegrid-event .fc-event-main {
            pointer-events: auto !important;
        }
        
        /* Reducir z-index del indicador now para que no interfiera */
        .fc-timegrid-now-indicator-line,
        .fc-timegrid-now-indicator-container {
            z-index: 50 !important;
            pointer-events: none !important;
        }
        
        .fc-datagrid-cell {
            padding: 8px 12px !important;
            vertical-align: middle !important;
            background: #0a0a0a !important;
            color: #e5e7eb !important;
        }
        
        .fc-resource {
            font-size: 13px !important;
            font-weight: 500 !important;
            color: #e5e7eb !important;
            line-height: 1.3 !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            white-space: normal !important;
            max-width: 230px !important;
            hyphens: auto !important;
            padding: 4px 0 !important;
        }
        
        .fc-col-header-cell, 
        .fc-resource-cell {
            min-width: 250px !important;
            padding: 8px 12px !important;
            vertical-align: top !important;
            background: #0a0a0a !important;
            border-color: rgba(255, 255, 255, 0.05) !important;
            color: #e5e7eb !important;
        }
        
        /* Responsive para recursos */
        @media (max-width: 768px) {
            .fc-col-header-cell, 
            .fc-resource-cell {
                min-width: 180px !important;
                padding: 4px 8px !important;
            }
            
            .fc-resource {
                font-size: 12px !important;
                max-width: 165px !important;
            }
        }
        
        @media (max-width: 480px) {
            .fc-col-header-cell, 
            .fc-resource-cell {
                min-width: 140px !important;
                padding: 4px 6px !important;
            }
            
            .fc-resource {
                font-size: 11px !important;
                max-width: 125px !important;
                line-height: 1.2 !important;
            }
        }
        
        .fc-button-primary {
            background: #111 !important;
            border-color: #666666 !important;
            color: white !important;
            border-radius: 6px !important;
            font-weight: 500 !important;
            padding: 0.4rem 0.8rem !important;
        }
        
        .fc-button-primary:hover {
            background: #666666 !important;
            border-color: #1d4ed8 !important;
        }
        
        .fc-event {
            border-radius: 12px !important;
            border: 1px solid #222;
            background-color: #0a0a0a;
            box-shadow: none !important;
            font-size: 12px !important;
            transition: all 0.3s ease-in-out !important;
        }
        
        .fc-event:hover {
            transform: translateX(5px) !important;
            background: #111 !important;
            border-color: #58a6ff !important;
        }
        
        /* Asegurar fondo oscuro en todas las áreas del calendario */
        .fc-timegrid-slot,
        .fc-timegrid-col,
        .fc-scrollgrid-sync-table,
        .fc-resource-timeline,
        .fc-daygrid-day,
        .fc-theme-standard .fc-scrollgrid-section,
        .fc-theme-standard td, 
        .fc-theme-standard th {
            background: #0a0a0a !important;
            border-color: rgba(255, 255, 255, 0.05) !important;
            color: #e5e7eb !important;
        }
        
        .fc .fc-col-header-cell {
            background: #0a0a0a !important;
        }
        
        .fc-timegrid-axis {
            background: #0a0a0a !important;
            color: #9ca3af !important;
        }
        
        .fc-today-button, .fc-prev-button, .fc-next-button {
            background: #111 !important;
            border-color: #374151 !important;
            color: #e5e7eb !important;
        }
        
        .fc-today-button:hover, .fc-prev-button:hover, .fc-next-button:hover {
            background: #2d3548 !important;
            border-color: #333333 !important;
            color: #555555 !important;
        }

        /* Tooltip Styles */
        .fc-custom-tooltip {
            position: absolute;
            background: #0a0a0a;
            border: 1px solid #333;
            color: #e5e7eb;
            box-shadow: 0 2px 8px rgba(0,0,0,0.13);
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 15px;
            pointer-events: auto;
            z-index: 99999;
            max-width: 280px;
        }

        /* Estado Puntos */
        .estado-puntos {
            display: flex;
            gap: 8px;
            margin: 8px 0;
            align-items: center;
        }
        
        .estado-punto {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
        }
        
        .estado-punto:hover {
            transform: scale(1.3);
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }
        
        .estado-punto:active {
            transform: scale(1.1);
        }
        
        .estado-punto.clickeable {
            opacity: 0.6;
        }
        
        .estado-punto.clickeable:hover {
            opacity: 1;
        }
        
        .estado-punto.activo {
            opacity: 1 !important;
        }
        
        .estado-punto-tooltip {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 100001;
        }
        
        .estado-punto:hover .estado-punto-tooltip {
            opacity: 1;
        }

        /* Context Menu */
        .context-menu { 
            position: absolute; 
            background: #0a0a0a;
            border: 1px solid rgba(41, 121, 255, 0.3);
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            z-index: 9999; 
            padding: 8px 0; 
            border-radius: 12px;
            min-width: 140px; 
            display: none;
            font-family: Inter, sans-serif;
        }
        
        .context-menu button { 
            width: 100%; 
            background: none; 
            border: none; 
            padding: 12px 16px; 
            text-align: left; 
            cursor: pointer; 
            font-size: 14px;
            color: #e5e7eb;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .context-menu button:hover { 
            background: rgba(41, 121, 255, 0.1);
            color: #2979ff;
        }
        
        .context-menu button:disabled {
            color: #6b7280;
            cursor: not-allowed;
            background: transparent;
        }
        
        .context-menu button:first-child {
            border-radius: 12px 12px 0 0;
        }
        
        .context-menu button:last-child {
            border-radius: 0 0 12px 12px;
        }
        
        /* Estilos para los puntos de estado en el tooltip */
        .estado-puntos {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            margin: 8px 0;
        }
        
        .estado-punto {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            display: inline-block;
        }
        
        .estado-punto.clickeable:hover {
            transform: scale(1.2);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .estado-punto.activo {
            transform: scale(1.1);
            box-shadow: 0 0 0 3px rgba(180, 180, 180, 0.3);
        }
        
        .estado-punto-tooltip {
            position: absolute;
            bottom: 25px;
            left: 50%;
            transform: translateX(-50%);
            background: #1f2937;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 10000;
        }
        
        .estado-punto:hover .estado-punto-tooltip {
            opacity: 1;
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

        /* Injected CSS for resource thumbnails */
        .fc .fc-resource .fc-resource-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            display: block;
            margin: 0 auto 6px;
        }
        .fc .fc-resource {
            min-height: 64px !important;
            text-align: center;
        }

        /* Add a dropdown for time range selection in the top-right corner */
        .time-range-selector {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        
        .time-range-selector label {
            margin-right: 5px;
            font-size: 14px;
            color: #e5e7eb;
        }
        
        .time-range-selector select {
            padding: 8px 12px;
            border: 1px solid #333;
            border-radius: 6px;
            font-size: 14px;
            color: #e5e7eb;
            background: #000000;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .time-range-selector select:hover {
            border-color: #2979ff;
            box-shadow: 0 0 5px rgba(41, 121, 255, 0.2);
        }
        
        .time-range-selector select:focus {
            outline: none;
            border-color: #2979ff;
            box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2);
        }

        /* Animación para el spinner de carga */
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spinner-icon {
            display: inline-block;
            animation: spin 1s linear infinite;
        }

        /* Bootstrap Dark Mode Overrides */
        .modal-content {
            background: #0a0a0a;
            border: 1px solid #333;
            color: #e5e7eb;
            box-shadow: 0 10px 40px rgba(0,0,0,0.7);
        }
        
        .modal-header {
            background: #111;
            border-bottom: 1px solid #333;
            color: #e5e7eb;
        }
        
        .modal-title {
            color: #e5e7eb;
        }
        
        .modal-body {
            color: #e5e7eb;
            background: #0a0a0a;
        }
        
        .modal-footer {
            background: #111;
            border-top: 1px solid #333;
        }
        
        .close {
            color: #e5e7eb;
            opacity: 0.8;
        }
        
        .close:hover {
            color: #fff;
            opacity: 1;
        }
        
        .btn-primary {
            background: #2979ff;
            border-color: #2979ff;
        }
        
        .btn-primary:hover {
            background: #2962ff;
            border-color: #2962ff;
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.4);
        }
        
        .btn-secondary {
            background: #111;
            border-color: #444;
        }
        
        .btn-secondary:hover {
            background: #444;
            border-color: #555;
        }
        
        .btn-secondary.close:focus,
        .btn-close:focus {
            outline: none;
            box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.25);
        }
        
        .form-group label {
            color: #e5e7eb;
            font-weight: 500;
        }
        
        .form-text {
            color: #9ca3af;
        }
        
        .badge {
            background: rgba(41, 121, 255, 0.2);
            color: #2979ff;
            border: 1px solid rgba(41, 121, 255, 0.3);
        }
        
        .alert-info {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #bfdbfe;
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fcd34d;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }
        
        .input-group-text {
            background: #111;
            border-color: #333;
            color: #e5e7eb;
        }
        
        .dropdown-menu {
            background: #111;
            border: 1px solid #333;
        }
        
        .dropdown-item {
            color: #e5e7eb;
        }
        
        .dropdown-item:hover, .dropdown-item:focus {
            background: rgba(41, 121, 255, 0.1);
            color: #2979ff;
        }
        
        .dropdown-divider {
            border-color: rgba(255, 255, 255, 0.15);
        }
        
        .table {
            color: #e5e7eb;
            border-color: #333;
        }
        
        .table-dark {
            background: #0a0a0a;
            border-color: #333;
        }
        
        .table-hover tbody tr:hover {
            background: rgba(41, 121, 255, 0.05);
        }
        
        .pagination .page-link {
            background: #111;
            border-color: #333;
            color: #e5e7eb;
        }
        
        .pagination .page-link:hover {
            background: #000;
            border-color: #444;
            color: #2979ff;
        }
        
        .pagination .page-item.active .page-link {
            background: #2979ff;
            border-color: #2979ff;
        }
        
        hr {
            border-color: #333;
        }
        
        .popover {
            background: #0a0a0a;
            border: 1px solid #333;
        }
        
        .popover-header {
            background: #111;
            border-bottom: 1px solid #333;
            color: #e5e7eb;
        }
        
        .popover-body {
            color: #e5e7eb;
        }
        
        .card {
            background: #0a0a0a;
            border: 1px solid #333;
            color: #e5e7eb;
        }
        
        .card-header {
            background: #111;
            border-bottom: 1px solid #333;
            color: #e5e7eb;
        }
        
        .list-group-item {
            background: #0a0a0a;
            border-color: #333;
            color: #e5e7eb;
        }
        
        .list-group-item:hover {
            background: rgba(41, 121, 255, 0.05);
        }
        
        .progress {
            background: #222;
        }
        
        .flex-fill.text-center {
            color: #e5e7eb;
        }

        /* Notificaciones Toast */
        #notification-container {
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 1060;
            width: 300px;
        }
        .toast-message {
            background-color: #1f2937;
            color: #e5e7eb;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            border-left: 5px solid #6b7280;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.4s cubic-bezier(0.215, 0.610, 0.355, 1);
        }
        .toast-message.show {
            opacity: 1;
            transform: translateX(0);
        }
        .toast-message.success { border-left-color: #10b981; }
        .toast-message.error { border-left-color: #ef4444; }
        .toast-message.info { border-left-color: #3b82f6; }
        .toast-message.warning { border-left-color: #f59e0b; }
    </style>
</head>
<body>
  <!-- Header -->
  <header class="main-header">
    <div class="header-left">
      <button class="mobile-menu-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
      </button>
            
      <div class="header-logo">
         <img src="images/Xolai.png" alt="Xolai Logo" class="header-logo-img">
            <span class="header-title">Xolai</span>
      </div>
    </div>
    
    <nav class="header-center">
        <a href="home.php" class="nav-link">Inicio</a>
        <a href="index.php" class="nav-link">Agenda</a>
        <a href="catalogo_pacientes.php" class="nav-link">Pacientes</a>
        <a href="pagos.php" class="nav-link">Pagos</a>
        <?php if ($puede_ver_administracion): ?>
            <a href="panel_admin.php" class="nav-link">Administración</a>
        <?php endif; ?>
    </nav>
        
    <div class="header-right">
      <div class="user-info">
        <span><?php echo htmlspecialchars($user_nombre); ?></span>
        <i class="fas fa-user-circle"></i>
      </div>
      <div class="header-buttons">
        <a href="logout.php" class="btn-header">
            <i class="fas fa-sign-out-alt"></i>
        </a>
      </div>
    </div>
  </header>

  <!-- Sidebar Overlay for Mobile -->
  <div class="sidebar-overlay" onclick="closeSidebar()"></div>

  <div id="notification-container"></div>

  <!-- Modal Imprimir -->
  <div class="modal fade" id="modalImprimir" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Imprimir calendario</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="im_modalidad">Consulotrio</label>
            <select id="im_modalidad" class="form-control">
              <option value="all">Todas</option>
            </select>
          </div>
          <div class="form-group">
            <label for="im_fecha">Fecha</label>
            <input id="im_fecha" class="form-control" type="date" value="<?= date('Y-m-d') ?>">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" onclick="imprimirSeleccion()">Imprimir / Descargar PDF</button>
        </div>
      </div>
    </div>
  </div>
    
    <div class="main-content">
        <!-- Sidebar -->
        <div class="sidebar">            
            <!-- Controles Unificados -->
            <div class="unified-controls">
                <!-- Acciones Rápidas -->
                <div class="control-section">
                    <h5 class="control-title">
                        <i class="fas fa-tools"></i>
                        Acciones
                    </h5>
                    <?php if ($puede_configurar_horarios && !$es_dentista_principal && !$es_admin_derivado): ?>
                    <button id="btnBloquearDiaSidebar" class="btn btn-block" style="width:100%; text-align:left; display:flex; align-items:center; gap:8px; background:rgba(239, 68, 68, 0.1); color:#ef4444; border:1px solid rgba(239, 68, 68, 0.2);">
                        <i class="fas fa-ban"></i> Bloquear Espacio / Día
                    </button>
                    <?php endif; ?>
                    <?php if ($puede_configurar_horarios || $es_dentista_principal || $es_admin_derivado): ?>
                    <button id="btnBloquearDentistaSidebar" class="btn btn-block" style="width:100%; text-align:left; display:flex; align-items:center; gap:8px; background:rgba(249, 115, 22, 0.1); color:#f97316; border:1px solid rgba(249, 115, 22, 0.2); margin-top:8px;">
                        <i class="fas fa-user-md"></i> Bloquear Dentista
                    </button>
                    <div class="control-section" style="margin-top:16px;">
                        <h5 class="control-title" style="display:flex;align-items:center;gap:8px;">
                            <i class="fas fa-user-slash"></i>
                            Dentistas bloqueados
                        </h5>
                        <div id="sidebarDentistasBloqueados" style="max-height:220px; overflow-y:auto; padding-right:5px; scrollbar-width: thin; scrollbar-color: #333 #000; color:#e5e7eb; font-size:13px;">
                            <div style="text-align:center;padding:10px;color:#9ca3af;">Cargando dentistas bloqueados...</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($puede_configurar_horarios && !$es_dentista_principal && !$es_admin_derivado): ?>
                    <button id="btnConfigHorariosSidebar" class="btn btn-block" style="width:100%; text-align:left; display:flex; align-items:center; gap:8px; background:rgba(41, 121, 255, 0.1); color:#2979ff; border:1px solid rgba(41, 121, 255, 0.2); margin-top:8px;">
                        <i class="fas fa-clock"></i> Configurar Horarios
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Modalidad -->
                <div class="control-section">
                    <h5 class="control-title">
                        <i class="fas fa-layer-group"></i>
                        Consultorio
                    </h5>
                    <select id="profesional-select" class="form-control">
                        <option value="">Todos</option>
                    </select>
                </div>
                
                <!-- Estado -->
                <div class="control-section">
                    <h5 class="control-title">
                        <i class="fas fa-check-circle"></i>
                        Estado
                    </h5>
                    <select id="estado-select" class="form-control">
                        <option value="">Todos</option>
                    </select>
                </div>
                
                <!-- Mes Actual -->
                <div class="control-section">
                    <h5 class="control-title">
                        <i class="fas fa-calendar-day"></i>
                        Mes Actual
                    </h5>
                    <div id="mini-calendar-actual" class="mini-calendar"></div>
                </div>
                
                <!-- Próximo Mes -->
                <div class="control-section">
                    <h5 class="control-title">
                        <i class="fas fa-calendar-plus"></i>
                        Próximo Mes
                    </h5>
                    <div id="mini-calendar-proximo" class="mini-calendar"></div>
                </div>

                <!-- Leyenda de Estados -->
                <div class="control-section">
                    <h5 class="control-title">
                        <i class="fas fa-palette"></i>
                        Leyenda de Estados
                    </h5>
                    <div class="leyenda-estados">
                        <div class="leyenda-item">
                            <span class="leyenda-color" style="background-color: #2196F3;"></span>
                            <span class="leyenda-texto">Reservado</span>
                        </div>
                        <div class="leyenda-item">
                            <span class="leyenda-color" style="background-color: #FF9800;"></span>
                            <span class="leyenda-texto">Confirmado</span>
                        </div>
                        <div class="leyenda-item">
                            <span class="leyenda-color" style="background-color: #4CAF50;"></span>
                            <span class="leyenda-texto">En espera</span>
                        </div>
                        <div class="leyenda-item">
                            <span class="leyenda-color" style="background-color: #E91E63;"></span>
                            <span class="leyenda-texto">Asistió</span>
                        </div>
                        <div class="leyenda-item">
                            <span class="leyenda-color" style="background-color: #FF7F50;"></span>
                            <span class="leyenda-texto">No Asistió</span>
                        </div>
                         <div class="leyenda-item">
                            <span class="leyenda-color" style="background-color: #797a79ff;"></span>
                            <span class="leyenda-texto">Cancelada</span>
                        </div>
                    </div>
                </div>
            </div> <!-- Cierre de .unified-controls -->
        </div>
        <!-- Resizer para la barra lateral -->
        <div class="sidebar-resizer" id="sidebar-resizer"></div>

        <!-- Calendar Area -->
        <div class="calendar-area">
            <div id="calendar"></div>
        </div>
    </div>

  <!-- Modal para agendar cita -->
  <!-- Modal mejorado para agendar cita -->
  <div id="modalAgendar" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.4);z-index:10000;align-items:flex-start;justify-content:center;overflow-y:auto;padding-top:5vh;">
    <div style="background:#0a0a0a;border-radius:12px;max-width:800px;width:95%;margin:20px;position:relative;box-shadow:0 10px 40px rgba(0,0,0,0.7);border:1px solid #333;">
      <!-- Header del modal -->
      <div style="background: #111;color:white;padding:20px 32px;border-radius:12px 12px 0 0;position:relative;">
        <h3 style="margin:0;font-size:24px;font-weight:600;">
          <i class="fas fa-calendar-plus" style="margin-right:10px;"></i>
          Agendar Nueva Cita
        </h3>
        <button id="cerrarModalAgendar" style="position:absolute;top:15px;right:20px;font-size:24px;background:none;border:none;cursor:pointer;color:white;opacity:0.8;padding:5px;">&times;</button>
      </div>

      <!-- Contenido del modal -->
      <div style="padding:0;">
        <form id="formAgendar">
          <!-- Sección de Fecha y Tiempo -->
          <div style="padding:24px 32px;border-bottom:1px solid #333;">
            <h4 style="margin:0 0 16px 0;color:#e5e7eb;font-size:18px;font-weight:600;">
              <i class="fas fa-clock" style="margin-right:8px;color:#2979ff;"></i>
              Fecha y Horario
            </h4>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:16px;align-items:end;">
              <div>
                <label for="agendarFecha" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Fecha:</label>
                <input type="text" id="agendarFecha" name="fecha" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;" autocomplete="off" />
              </div>
              <div>
                <label for="agendarHoraInicio" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Hora inicio:</label>
                <input type="text" id="agendarHoraInicio" name="hora_inicio" placeholder="HH:MM" class="time-input-manual" data-list="agendarHorasList" autocomplete="off" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;" />
                <datalist id="agendarHorasList"></datalist>
              </div>
              <div>
                <label for="agendarHoraFin" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Hora fin:</label>
                <input type="text" id="agendarHoraFin" name="hora_fin" placeholder="HH:MM" class="time-input-manual" data-list="agendarHorasList" autocomplete="off" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;" />
              </div>
              <div>
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#6b7280;">
                  <input type="checkbox" id="tiempoManual" name="tiempoManual" style="margin:0;">
                  Editar manual
                </label>
              </div>
            </div>
          </div>
          <!-- background for section -->

          <!-- Sección de Paciente -->
          <div style="padding:24px 32px;border-bottom:1px solid #333;background:#0a0a0a;">
            <h4 style="margin:0 0 16px 0;color:#e5e7eb;font-size:18px;font-weight:600;">
              <i class="fas fa-user" style="margin-right:8px;color:#2979ff;"></i>
              Información del Paciente
            </h4>
            
            <!-- Búsqueda de paciente -->
            <div style="position:relative;margin-bottom:16px;">
              <label for="agendarPaciente" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Buscar paciente:</label>
              <div style="position:relative;">
                <input type="text" id="agendarPaciente" name="paciente" placeholder="Escribe el nombre del paciente..." autocomplete="off" 
                       style="width:100%;padding:10px 12px;padding-right:120px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000 !important;color:#e5e7eb !important;" />
                <button type="button" id="btnMostrarRegistroPaciente" 
                        style="position:absolute;right:6px;top:6px;padding:8px 16px;background:#333;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;font-weight:500;">
                  <i class="fas fa-plus" style="margin-right:4px;"></i> Nuevo
                </button>
              </div>
              <div id="pacientesDropdown" style="position:absolute;top:100%;left:0;width:100%;background:#000;z-index:10001;border:1px solid #333;border-top:none;border-radius:0 0 6px 6px;display:none;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.5);"></div>
            </div>

            <!-- Registro de nuevo paciente (expandible) -->
            <div id="registroPacienteBox" style="display:none;background:#000;border:1px solid #333;border-radius:8px;padding:20px;">
              <h5 style="margin:0 0 16px 0;color:#e5e7eb;font-size:16px;font-weight:600;">
                <i class="fas fa-user-plus" style="margin-right:8px;color:#2979ff;"></i>
                Registrar Nuevo Paciente
              </h5>
              
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <div>
                  <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Nombre(s) <span style="color:red">*</span></label>
                  <input type="text" id="nuevoPacienteNombre" placeholder="Nombres" 
                         style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#111;color:#e5e7eb;">
                </div>
                <div>
                  <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Apellido Paterno <span style="color:red">*</span></label>
                  <input type="text" id="nuevoPacienteApellidoPaterno" placeholder="Apellido Paterno" 
                         style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#111;color:#e5e7eb;">
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <div>
                  <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Apellido Materno <span style="color:red">*</span></label>
                  <input type="text" id="nuevoPacienteApellidoMaterno" placeholder="Apellido Materno" 
                         style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#111;color:#e5e7eb;">
                </div>
                <div>
                  <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Teléfono (Celular) <span style="color:red">*</span></label>
                  <input type="text" id="nuevoPacienteTelefono" placeholder="Celular" 
                         style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#111;color:#e5e7eb;">
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
                <div>
                  <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Teléfono:</label>
                  <input type="text" id="nuevoPacienteTelefono" placeholder="Teléfono" 
                         style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#111;color:#e5e7eb;">
                </div>
                <div>
                  <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Tipo:</label>
                  <select id="nuevoPacienteTipo" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#111;color:#e5e7eb;">
                    <option value="" disabled selected>Seleccionar tipo</option>
                  </select>
                </div>
                <div>
                  <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Origen:</label>
                  <select id="nuevoPacienteOrigen" name="origen" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#111;color:#e5e7eb;" onchange="verificarOrigenDoctorAgenda(this)">
                    <option value="">Cargando...</option>
                  </select>
                </div>
              </div>

              <div id="nuevoPacienteDoctorDiv" style="display:none; margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Nombre del Doctor:</label>
                <input type="text" id="nuevoPacienteDoctorNombre" placeholder="Nombre del doctor que recomienda..." 
                       style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#111;color:#e5e7eb;">
              </div>

              <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Fecha de Nacimiento:</label>
                <input type="date" id="nuevoPacienteFechaNacimiento" 
                       style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#111;color:#e5e7eb;">
              </div>

              <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Correo electrónico:</label>
                <input type="email" id="nuevoPacienteCorreo" placeholder="correo@ejemplo.com" 
                       style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#111;color:#e5e7eb;">
              </div>

              <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Alergias:</label>
                <input type="text" id="nuevoPacienteAlergias" placeholder="Especifique alergias importantes..." 
                       style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#111;color:#e5e7eb;">
              </div>

              <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Diagnóstico o motivo del estudio:</label>
                <textarea id="nuevoPacienteDiagnostico" placeholder="Describe el motivo del estudio o diagnóstico..." 
                          style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;min-height:60px;resize:vertical;background:#111;color:#e5e7eb;"></textarea>
              </div>

              <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Comentarios adicionales:</label>
                <textarea id="nuevoPacienteComentarios" placeholder="Información adicional relevante..." 
                          style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;min-height:60px;resize:vertical;background:#111;color:#e5e7eb;"></textarea>
              </div>

              <div style="display:flex;gap:12px;">
                <button type="button" id="btnGuardarPaciente" 
                        style="background:#2979ff;color:#fff;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-weight:500;font-size:14px;">
                  <i class="fas fa-save" style="margin-right:6px;"></i>
                  Guardar Paciente
                </button>
                <button type="button" id="btnCancelarPaciente" 
                        style="background:#333;color:#fff;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-weight:500;font-size:14px;">
                  <i class="fas fa-times" style="margin-right:6px;"></i>
                  Cancelar
                </button>
              </div>
            </div>
          </div>

          <!-- Sección de Servicio -->
          <div style="padding:24px 32px;border-bottom:1px solid #333;background:#0a0a0a;">
            <h4 style="margin:0 0 16px 0;color:#e5e7eb;font-size:18px;font-weight:600;">
              <i class="fas fa-stethoscope" style="margin-right:8px;color:#2979ff;"></i>
              Tratamiento y Médico
            </h4>
            
            <div style="display:grid;grid-template-columns:1.5fr 1.5fr 1fr;gap:16px;">
              <div>
                <label for="agendarModalidad" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Doctor / Especialista:</label>
                <select id="agendarModalidad" name="profesional_id" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;">
                  <option value="">Seleccione médico...</option>
                </select>
                <input type="hidden" id="realModalidadId" name="modalidad_id">
              </div>
              <div style="display:flex; flex-direction:column;">
                <label for="agendarServicio" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Tratamiento:</label>
                <select id="agendarServicio" name="servicio" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;flex-grow:1;">
                  <option value="">Primero seleccione doctor...</option>
                </select>
              </div>
              <div>
                <label for="agendarEstado" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Estado inicial:</label>
                <select id="agendarEstado" name="estado_id" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;">
                  <!-- Opciones cargadas dinámicamente -->
                </select>
              </div>
            </div>

            <!-- Información de duración -->
            <div id="duracionInfo" style="margin-top:12px;padding:12px;background:#000;border:1px solid #333;border-radius:6px;display:none;">
              <p style="margin:0;font-size:13px;color:#e5e7eb;">
                <i class="fas fa-info-circle" style="margin-right:6px;color:#2979ff;"></i>
                <span id="duracionTexto">Duración estimada: </span>
              </p>
            </div>
          </div>

          <!-- Sección de Notas -->
          <div style="padding:24px 32px;border-bottom:1px solid #333;background:#0a0a0a;">
            <button type="button" id="btnToggleInfoAdicional" 
                    style="width:100%;background:#000;color:#e5e7eb;padding:12px 16px;border:1px solid #333;border-radius:6px;cursor:pointer;font-weight:500;display:flex;align-items:center;justify-content:space-between;font-size:14px;">
              <span>
                <i class="fas fa-sticky-note" style="margin-right:8px;color:#2979ff;"></i>
                Información adicional y notas
              </span>
              <i id="iconInfoAdicional" class="fas fa-chevron-down" style="transition:transform 0.2s;"></i>
            </button>
            
            <div id="infoAdicionalBox" style="display:none;margin-top:16px;padding-top:16px;">
              <div style="display:grid;grid-template-columns:1fr;gap:16px;">
                <div>
                  <label for="notaPaciente" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Notas para el paciente:</label>
                  <textarea id="notaPaciente" name="nota_paciente" rows="3" 
                            style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;resize:vertical;background:#111;color:#e5e7eb;" 
                            placeholder="Instrucciones y notas que verá el paciente...">Recuerde llegar 10 minutos antes de su cita y traer sus estudios previos si los tiene.</textarea>
                </div>
                <div>
                  <label for="notaInterna" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Nota interna (uso del personal):</label>
                  <textarea id="notaInterna" name="nota_interna" rows="3" 
                            style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;resize:vertical;background:#111;color:#e5e7eb;" 
                            placeholder="Notas internas para el personal médico..."></textarea>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer con botones -->
          <div style="padding:24px 32px;background:#0a0a0a;border-radius:0 0 12px 12px;border-top:1px solid #333;">
            <div style="display:flex;gap:12px;justify-content:end;">
              <button type="button" onclick="document.getElementById('modalAgendar').style.display='none';" 
                      style="background:#333;color:#fff;padding:12px 24px;border:none;border-radius:6px;cursor:pointer;font-weight:500;font-size:14px;">
                <i class="fas fa-times" style="margin-right:6px;"></i>
                Cancelar
              </button>
              <button type="submit" 
                      id="btnGuardarCita"
                      style="background:#2979ff;color:#fff;padding:12px 24px;border:none;border-radius:6px;cursor:pointer;font-weight:500;font-size:14px;">
                <i class="fas fa-calendar-check" style="margin-right:6px;"></i>
                <span>Guardar Cita</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal para Editar Cita -->
  <div id="modalEditarCita" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);z-index:10000;align-items:center;justify-content:center;overflow-y:auto;">
    <div style="background:#0a0a0a;border-radius:12px;max-width:800px;width:95%;margin:20px;position:relative;box-shadow:0 10px 40px rgba(0,0,0,0.7);border:1px solid #333;">
      <!-- Header del modal -->
      <div style="background: #111;color:white;padding:20px 32px;border-radius:12px 12px 0 0;position:relative;">
        <h3 style="margin:0;font-size:24px;font-weight:600;">
          <i class="fas fa-edit" style="margin-right:10px;"></i>
          Editar Cita
        </h3>
        <button id="cerrarModalEditarCita" style="position:absolute;top:15px;right:20px;font-size:24px;background:none;border:none;cursor:pointer;color:white;opacity:0.8;padding:5px;">&times;</button>
      </div>

      <!-- Contenido del modal -->
      <div style="padding:0;">
        <form id="formEditarCita">
          <!-- Información de la cita actual -->
          <div style="padding:24px 32px;border-bottom:1px solid #333;background:#0a0a0a;">
            <h4 style="margin:0 0 16px 0;color:#e5e7eb;font-size:18px;font-weight:600;">
              <i class="fas fa-info-circle" style="margin-right:8px;color:#2979ff;"></i>
              Información Actual
            </h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div>
                <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Paciente:</label>
                <div id="editarPacienteNombre" style="padding:10px 12px;background:#000;border:1px solid #333;border-radius:6px;font-size:14px;"></div>
              </div>
              <div>
                <label style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Tratamiento:</label>
                <div id="editarServicioNombre" style="padding:10px 12px;background:#000;border:1px solid #333;border-radius:6px;font-size:14px;"></div>
              </div>
            </div>
          </div>

          <!-- Sección de Fecha y Tiempo -->
          <div style="padding:24px 32px;border-bottom:1px solid #333;">
            <h4 style="margin:0 0 16px 0;color:#e5e7eb;font-size:18px;font-weight:600;">
              <i class="fas fa-clock" style="margin-right:8px;color:#2979ff;"></i>
              Fecha y Horario
            </h4>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
              <div>
                <label for="editarFecha" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Fecha:</label>
                <input type="text" id="editarFecha" name="fecha" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;" autocomplete="off" readonly />
              </div>
              <div>
                <label for="editarHoraInicio" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Hora Inicio:</label>
                <input type="text" id="editarHoraInicio" name="hora_inicio" placeholder="HH:MM" class="time-input-manual" data-list="editarHorasList" autocomplete="off" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;" />
                <datalist id="editarHorasList"></datalist>
              </div>
              <div>
                <label for="editarHoraFin" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Hora Fin:</label>
                <input type="text" id="editarHoraFin" name="hora_fin" placeholder="HH:MM" class="time-input-manual" data-list="editarHorasList" autocomplete="off" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;" />
              </div>
              <div style="grid-column: 1 / -1; text-align: right; margin-top: 10px;">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#9ca3af;justify-content:flex-end;">
                  <input type="checkbox" id="editarTiempoManual" style="margin:0;">
                  Editar manual
                </label>
              </div>
            </div>
          </div>

          <!-- Estado de la cita -->
          <div style="padding:24px 32px;border-bottom:1px solid #333;">
            <h4 style="margin:0 0 16px 0;color:#e5e7eb;font-size:18px;font-weight:600;">
              <i class="fas fa-check-circle" style="margin-right:8px;color:#2979ff;"></i>
              Estado de la Cita
            </h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div>
                <label for="editarEstado" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Estado:</label>
                <select id="editarEstado" name="estado_id" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;">
                  <!-- Opciones cargadas dinámicamente -->
                </select>
              </div>
            </div>
          </div>

          <!-- Acciones del modal -->
          <div style="padding:24px 32px;display:flex;gap:12px;justify-content:flex-end;background:#0a0a0a;border-top:1px solid #333;">
            <?php if ($puede_eliminar_citas): ?>
            <button type="button" id="eliminarCitaBtn" style="background:#dc3545;color:white;border:none;padding:12px 24px;border-radius:6px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:8px;">
              <i class="fas fa-trash"></i>
              Eliminar Cita
            </button>
            <?php endif; ?>
            <button type="button" onclick="document.getElementById('modalEditarCita').style.display='none'" style="background:#333;color:white;border:none;padding:12px 24px;border-radius:6px;font-weight:500;cursor:pointer;">
              Cancelar
            </button>
            <?php if ($puede_editar_citas): ?>
            <button type="submit" style="background:#2979ff;color:white;border:none;padding:12px 24px;border-radius:6px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:8px;">
              <i class="fas fa-save"></i>
              Guardar Cambios
            </button>
            <?php endif; ?>
          </div>
          
          <!-- Campo oculto para ID de la cita -->
          <input type="hidden" id="editarCitaId" name="cita_id" />
        </form>
      </div>
    </div>
  </div>

  <!-- Modal para Bloquear Espacio -->
  <div id="modalBloquear" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);z-index:10000;align-items:center;justify-content:center;">
    <div style="background:#0a0a0a;border-radius:12px;max-width:500px;width:95%;margin:20px;position:relative;box-shadow:0 10px 40px rgba(0,0,0,0.7);border:1px solid #333;">
      <div style="background: #111;color:white;padding:20px 32px;border-radius:12px 12px 0 0;position:relative;">
        <h3 style="margin:0;font-size:24px;font-weight:600;">
          <i class="fas fa-lock" style="margin-right:10px;"></i>
          Bloquear Espacio
        </h3>
        <button id="cerrarModalBloquear" style="position:absolute;top:15px;right:20px;font-size:24px;background:none;border:none;cursor:pointer;color:white;opacity:0.8;padding:5px;">&times;</button>
      </div>
      <form id="formBloquear" style="padding:24px 32px;">
        <div style="margin-bottom: 16px;">
            <label for="bloquearModalidadId" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Consultorio:</label>
            <select id="bloquearModalidadId" name="modalidad_id" class="form-control" style="width:100%;"><option value="">Cargando...</option></select>
        </div>
        <div style="margin-bottom: 16px;">
            <div style="color:#9ca3af;font-size:13px;line-height:1.5;">
                Este formulario bloquea consultorio o espacio por día/hora. Para bloquear un dentista específico, use el botón "Bloquear Dentista" en la barra lateral.
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div>
                <label for="bloquearFecha" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Desde:</label>
                <input type="date" id="bloquearFecha" name="fecha" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;">
            </div>
            <div>
                <label for="bloquearFechaFin" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Hasta:</label>
                <input type="date" id="bloquearFechaFin" name="fecha_fin" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div>
                <label for="bloquearHoraInicio" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Hora inicio:</label>
                <input type="time" id="bloquearHoraInicio" name="hora_inicio" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;">
            </div>
            <div>
                <label for="bloquearHoraFin" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Hora fin:</label>
                <input type="time" id="bloquearHoraFin" name="hora_fin" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;">
            </div>
        </div>
        <div style="margin-bottom: 16px;">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="checkTodoDia">
                <label class="custom-control-label" for="checkTodoDia" style="color:#e5e7eb;">Bloquear todo el día</label>
            </div>
        </div>
        <div style="margin-bottom: 16px;">
            <label for="bloquearMotivo" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Motivo:</label>
            <textarea id="bloquearMotivo" name="motivo" rows="3" placeholder="E.g., Mantenimiento, tiempo personal, etc." style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;resize:vertical;background:#000;color:#e5e7eb;"></textarea>
        </div>
        <div style="padding-top:12px;display:flex;gap:12px;justify-content:end;">
          <button type="button" onclick="document.getElementById('modalBloquear').style.display='none';" style="background:#333;color:#fff;padding:12px 24px;border:none;border-radius:6px;cursor:pointer;font-weight:500;font-size:14px;">Cancelar</button>
          <button type="submit" id="btnConfirmarBloqueo" style="background:#2979ff;color:#fff;padding:12px 24px;border:none;border-radius:6px;cursor:pointer;font-weight:500;font-size:14px;">Confirmar Bloqueo</button>
        </div>
        
        <!-- Lista de Bloqueos Activos -->
        <div style="border-top:1px solid #333; margin-top:24px; padding-top:20px;">
            <h5 style="color:#2979ff;font-size:15px;margin-bottom:12px;font-weight:600;"><i class="fas fa-list-ul"></i> Bloqueos Activos (Próximos)</h5>
            <div id="listaBloqueosActivos" style="max-height:180px; overflow-y:auto; padding-right:5px; scrollbar-width: thin; scrollbar-color: #333 #000;"></div>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal para Bloquear Dentista -->
  <div id="modalBloquearDentista" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);z-index:10000;align-items:center;justify-content:center;">
    <div style="background:#0a0a0a;border-radius:12px;max-width:520px;width:95%;margin:20px;position:relative;box-shadow:0 10px 40px rgba(0,0,0,0.7);border:1px solid #333;">
      <div style="background: #111;color:white;padding:20px 32px;border-radius:12px 12px 0 0;position:relative;">
        <h3 style="margin:0;font-size:24px;font-weight:600;">
          <i class="fas fa-user-md" style="margin-right:10px;"></i>
          Bloquear Dentista
        </h3>
        <button id="cerrarModalBloquearDentista" style="position:absolute;top:15px;right:20px;font-size:24px;background:none;border:none;cursor:pointer;color:white;opacity:0.8;padding:5px;">&times;</button>
      </div>
      <form id="formBloquearDoctor" style="padding:24px 32px;">
        <div style="margin-bottom: 16px;">
            <label for="bloquearDoctorProfesionalId" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Seleccione dentista:</label>
            <select id="bloquearDoctorProfesionalId" name="profesional_id" class="form-control" style="width:100%;">
                <option value="">Seleccione dentista</option>
                <?php foreach ($doctores as $doc): ?>
                    <option value="<?= $doc['id'] ?>"><?= htmlspecialchars($doc['nombre'] . ' (' . ucfirst($doc['tipo']) . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div>
                <label for="bloquearDoctorFecha" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Desde:</label>
                <input type="date" id="bloquearDoctorFecha" name="fecha" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;">
            </div>
            <div>
                <label for="bloquearDoctorFechaFin" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Hasta:</label>
                <input type="date" id="bloquearDoctorFechaFin" name="fecha_fin" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div>
                <label for="bloquearDoctorHoraInicio" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Hora inicio:</label>
                <input type="time" id="bloquearDoctorHoraInicio" name="hora_inicio" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;">
            </div>
            <div>
                <label for="bloquearDoctorHoraFin" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Hora fin:</label>
                <input type="time" id="bloquearDoctorHoraFin" name="hora_fin" style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;background:#000;color:#e5e7eb;">
            </div>
        </div>
        <div style="margin-bottom: 16px;">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="checkTodoDiaDoctor">
                <label class="custom-control-label" for="checkTodoDiaDoctor" style="color:#e5e7eb;">Bloquear todo el día</label>
            </div>
        </div>
        <div style="margin-bottom: 16px;">
            <label for="bloquearDoctorMotivo" style="display:block;margin-bottom:6px;font-weight:500;color:#e5e7eb;">Motivo:</label>
            <textarea id="bloquearDoctorMotivo" name="motivo" rows="3" placeholder="E.g., Consulta privada, descanso, capacitación." style="width:100%;padding:10px 12px;border:1px solid #333;border-radius:6px;font-size:14px;resize:vertical;background:#000;color:#e5e7eb;"></textarea>
        </div>
        <div style="padding-top:12px;display:flex;gap:12px;justify-content:end;">
          <button type="button" onclick="document.getElementById('modalBloquearDentista').style.display='none';" style="background:#333;color:#fff;padding:12px 24px;border:none;border-radius:6px;cursor:pointer;font-weight:500;font-size:14px;">Cancelar</button>
          <button type="submit" id="btnConfirmarBloqueoDentista" style="background:#f97316;color:#fff;padding:12px 24px;border:none;border-radius:6px;cursor:pointer;font-weight:500;font-size:14px;">Confirmar Bloqueo</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Configuración de Horarios (Admin) -->
  <div id="modalAdminHorarios" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.6);z-index:10000;align-items:center;justify-content:center;">
    <div style="background:#0a0a0a;border-radius:12px;max-width:600px;width:95%;margin:20px;position:relative;box-shadow:0 10px 40px rgba(0,0,0,0.7);border:1px solid #333;">
      <div style="background: #111;color:white;padding:20px 32px;border-radius:12px 12px 0 0;position:relative;">
        <h3 style="margin:0;font-size:24px;font-weight:600;">
          <i class="fas fa-clock" style="margin-right:10px;"></i>
          Configuración de Horarios
        </h3>
        <button id="cerrarModalAdminHorarios" style="position:absolute;top:15px;right:20px;font-size:24px;background:none;border:none;cursor:pointer;color:white;opacity:0.8;padding:5px;">&times;</button>
      </div>
      <div style="padding:24px 32px; max-height:70vh; overflow-y:auto;">
        
        <div style="margin-bottom: 24px;">
            <h5 style="color:#2979ff;font-size:16px;margin-bottom:8px;font-weight:600;">Intervalo entre Horarios</h5>
            <p style="color:#9ca3af;font-size:13px;margin-bottom:10px;">Define la duración base de los espacios en la agenda.</p>
            <select id="admin_slot_interval" class="form-control" style="width:100%;">
                <option value="15">15 minutos</option>
                <option value="30">30 minutos</option>
                <option value="60">1 hora</option>
            </select>
        </div>

        <div style="border-top:1px solid #333; padding-top:24px;">
            <h5 style="color:#2979ff;font-size:16px;margin-bottom:8px;font-weight:600;">Bloqueo de Tramos Horarios</h5>
            <p style="color:#9ca3af;font-size:13px;margin-bottom:15px;">Periodos fijos donde no se ofrecen citas (ej. hora de comida). Aplica a todos los días.</p>
            
            <div id="admin-blocked-list" style="margin-bottom:15px;"></div>

            <div style="background:#111; padding:15px; border-radius:8px; border:1px solid #333;">
                <h6 style="color:#e5e7eb;font-size:14px;margin-bottom:10px;">Añadir Nuevo Bloqueo</h6>
                <div style="display:grid; grid-template-columns: 1fr 1fr auto; gap:10px; align-items:end;">
                    <div>
                        <label style="display:block;margin-bottom:4px;font-size:12px;color:#9ca3af;">Inicio</label>
                        <input type="time" id="new_block_start" class="form-control">
                    </div>
                    <div>
                        <label style="display:block;margin-bottom:4px;font-size:12px;color:#9ca3af;">Fin</label>
                        <input type="time" id="new_block_end" class="form-control">
                    </div>
                    <button type="button" id="btnAddBlock" class="btn btn-action" style="height:38px;background:#2979ff;border-color:#2979ff;"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>
      </div>
      <div style="padding:20px 32px;background:#0a0a0a;border-radius:0 0 12px 12px;border-top:1px solid #333;display:flex;justify-content:end;gap:12px;">
          <button type="button" onclick="document.getElementById('modalAdminHorarios').style.display='none';" style="background:#333;color:#fff;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;">Cancelar</button>
          <button type="button" id="btnSaveAdminHorarios" style="background:#2979ff;color:#fff;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-weight:600;">Guardar Cambios</button>
      </div>
    </div>
  </div>

  <!-- Menú contextual para agendar o bloquear -->
  <div id="contextMenu" class="context-menu">
    <button id="ctxAgendarBtn"><i class="fas fa-calendar-plus"></i> Agendar Cita</button>
    <?php if ($puede_configurar_horarios): ?>
    <button id="ctxBloquearBtn"><i class="fas fa-lock"></i> Bloquear Espacio</button>
    <?php endif; ?>
  </div>

  
  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.8/index.global.min.js"></script>
  <script>
    // Función de notificaciones tipo Toast (Estilo Expediente)
    function mostrarNotificacion(mensaje, tipo = 'info') {
        const container = document.getElementById('notification-container');
        const toast = document.createElement('div');
        toast.className = `toast-message ${tipo}`;
        toast.textContent = mensaje;
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => container.removeChild(toast), 500);
        }, 5000);
    }

    // Helper para fetch JSON con debug (muestra texto si no es JSON)
    function fetchJsonDebug(url) {
      return fetch(url).then(function(r) {
        return r.text().then(function(text) {
          // Trim possible UTF-8 BOM
          if (text && text.charCodeAt(0) === 0xFEFF) {
            text = text.slice(1);
          }

          try {
            return JSON.parse(text);
          } catch (e) {
            // If the response is HTML that contains JSON somewhere, try to extract the first JSON-like substring
            var cleaned = text;
            if (cleaned.indexOf('<') !== -1) {
              // Heurística: buscar el primer '[' o '{' y la última ']' o '}'
              var firstIdx = Math.max(cleaned.indexOf('{'), cleaned.indexOf('['));
              var lastIdx = Math.max(cleaned.lastIndexOf('}'), cleaned.lastIndexOf(']'));
              if (firstIdx !== -1 && lastIdx !== -1 && lastIdx > firstIdx) {
                var candidate = cleaned.substring(firstIdx, lastIdx + 1);
                try {
                  return JSON.parse(candidate);
                } catch (e2) {
                  // fallthrough: we'll rethrow the original error below with extra info
                }
              }
            }

            console.error('Failure parsing JSON from', url, 'status', r.status, 'responseText (truncated 1000):', (text||'').slice(0,1000));
            var err = new Error('Failure parsing JSON');
            err.responseText = text;
            err.status = r.status;
            throw err;
          }
        });
      });
    }


    // Función para cargar los tipos de paciente dinámicamente
    async function cargarTiposDePaciente() {
        try {
            const response = await fetch('tipos_paciente_json.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const tipos = await response.json();
            const select = document.getElementById('nuevoPacienteTipo');
            select.innerHTML = '<option value="">Seleccione un tipo</option>'; // Opción por defecto
            tipos.forEach(tipo => {
                const option = document.createElement('option');
                option.value = tipo.id;
                option.textContent = tipo.nombre;
                select.appendChild(option);
            });
        } catch (error) {
            console.error("Error al cargar tipos de paciente:", error);
            const select = document.getElementById('nuevoPacienteTipo');
            select.innerHTML = '<option value="">Error al cargar</option>';
        }
    }

    // Paciente autocompletar y registro

    let pacienteInput = document.getElementById('agendarPaciente');
    let pacientesDropdown = document.getElementById('pacientesDropdown');
    let registroPacienteBox = document.getElementById('registroPacienteBox');
    let btnGuardarPaciente = document.getElementById('btnGuardarPaciente');
    let formTitulo = registroPacienteBox.querySelector('h5');

    // Variable para guardar el ID del paciente que se está editando
    let editingPacienteId = null;

    // Debounce utility para no sobrecargar el servidor con requests
    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), delay);
        };
    }

    // Función para buscar pacientes en el servidor
    async function fetchPacientes(term) {
        if (term.length < 1) {
            pacientesDropdown.style.display = 'none';
            return;
        }
        try {
            // Añadimos el usuario_id a la petición para filtrar por el usuario logueado.
            const usuarioId = <?php echo json_encode($user_id); ?>;
            // Usar ruta relativa simple desde index.php
            const response = await fetch(`citas/pacientes_json.php?term=${encodeURIComponent(term)}&usuario_id=${usuarioId}`);
            if (!response.ok) {
                console.warn('Response status:', response.status, 'for URL:', response.url);
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const text = await response.text();
            console.log('Raw response:', text.substring(0, 200)); // Debug: ver primeros 200 caracteres
            const pacientes = JSON.parse(text);
            renderPacientesDropdown(pacientes);
        } catch (error) {
            console.error("Error fetching pacientes:", error);
            pacientesDropdown.style.display = 'none';
        }
    }

    // Función para mostrar los resultados de la búsqueda
    function renderPacientesDropdown(pacientes) {
        pacientesDropdown.innerHTML = '';
        if (pacientes.length === 0) {
            pacientesDropdown.style.display = 'none';
            return;
        }
        pacientes.forEach(p => {
            let item = document.createElement('div');
            item.textContent = p.nombre;
            item.style.padding = '8px 12px';
            item.style.cursor = 'pointer';
            item.style.color = '#e5e7eb';
            item.style.backgroundColor = '#000';
            item.addEventListener('mouseenter', () => item.style.backgroundColor = '#333');
            item.addEventListener('mouseleave', () => item.style.backgroundColor = '#000');
            item.onmousedown = (e) => {
                e.preventDefault(); // Prevenir que el input pierda el foco
                seleccionarPaciente(p);
                pacientesDropdown.style.display = 'none';
            };
            pacientesDropdown.appendChild(item);
        });
        pacientesDropdown.style.display = 'block';
    }

    // Función para poblar el formulario con los datos de un paciente seleccionado
    function seleccionarPaciente(paciente) {
        pacienteInput.value = paciente.nombre;
        pacienteInput.dataset.pacienteId = paciente.id; // Guardar ID para el form de la cita
        
        // Llenar el formulario de edición
        document.getElementById('nuevoPacienteNombre').value = paciente.nombre_solo || '';
        document.getElementById('nuevoPacienteApellidoPaterno').value = paciente.apellido_paterno || '';
        document.getElementById('nuevoPacienteApellidoMaterno').value = paciente.apellido_materno || '';
        document.getElementById('nuevoPacienteTelefono').value = paciente.telefono || '';
        document.getElementById('nuevoPacienteCorreo').value = paciente.correo || '';
        document.getElementById('nuevoPacienteDiagnostico').value = paciente.motivo_consulta || paciente.diagnostico || '';
        document.getElementById('nuevoPacienteAlergias').value = paciente.alergias || '';
        document.getElementById('nuevoPacienteTipo').value = paciente.estado_id || '';

        if (paciente.origen && paciente.origen.startsWith('DOCTOR:')) {
            document.getElementById('nuevoPacienteOrigen').value = 'DOCTOR';
            document.getElementById('nuevoPacienteDoctorNombre').value = paciente.origen.replace('DOCTOR:', '').trim();
            document.getElementById('nuevoPacienteDoctorDiv').style.display = 'block';
        } else if (paciente.recomendado_por_id) {
            document.getElementById('nuevoPacienteOrigen').value = paciente.recomendado_por_id;
            document.getElementById('nuevoPacienteDoctorDiv').style.display = 'none';
        } else {
            document.getElementById('nuevoPacienteOrigen').value = '';
            document.getElementById('nuevoPacienteDoctorDiv').style.display = 'none';
        }

        document.getElementById('nuevoPacienteComentarios').value = paciente.comentarios || '';
        document.getElementById('nuevoPacienteFechaNacimiento').value = paciente.fecha_nacimiento || '';

        // Cambiar a modo edición
        editingPacienteId = paciente.id;
        formTitulo.innerHTML = `<i class="fas fa-user-edit" style="margin-right:8px;color:#6b7280;"></i> Editar Paciente`;
        btnGuardarPaciente.innerHTML = `<i class="fas fa-save" style="margin-right:6px;"></i> Actualizar Paciente`;
        btnGuardarPaciente.style.background = '#ff9800'; // Naranja para indicar actualización
        registroPacienteBox.style.display = 'block';
    }
    
    // Función para limpiar y resetear el formulario para un nuevo paciente
    function limpiarFormularioPaciente() {
        document.getElementById('nuevoPacienteNombre').value = '';
        document.getElementById('nuevoPacienteApellidoPaterno').value = '';
        document.getElementById('nuevoPacienteApellidoMaterno').value = '';
        document.getElementById('nuevoPacienteTelefono').value = '';
        document.getElementById('nuevoPacienteCorreo').value = '';
        document.getElementById('nuevoPacienteDiagnostico').value = '';
        document.getElementById('nuevoPacienteAlergias').value = '';
        document.getElementById('nuevoPacienteTipo').value = '';
        document.getElementById('nuevoPacienteOrigen').value = 'externo';
        document.getElementById('nuevoPacienteDoctorDiv').style.display = 'none';
        document.getElementById('nuevoPacienteDoctorNombre').value = '';
        document.getElementById('nuevoPacienteComentarios').value = '';
        document.getElementById('nuevoPacienteFechaNacimiento').value = '';

        // Cambiar a modo registro
        editingPacienteId = null;
        formTitulo.innerHTML = `<i class="fas fa-user-plus" style="margin-right:8px;color:#6b7280;"></i> Registrar Nuevo Paciente`;
        btnGuardarPaciente.innerHTML = `<i class="fas fa-save" style="margin-right:6px;"></i> Guardar Paciente`;
        btnGuardarPaciente.style.background = '#10b981'; // Verde para nuevo registro
        registroPacienteBox.style.display = 'block';
        document.getElementById('nuevoPacienteNombre').focus();
    }

    // Event Listeners para la búsqueda
    pacienteInput.addEventListener('input', debounce(e => {
        fetchPacientes(e.target.value.trim());
    }, 300));

    pacienteInput.addEventListener('blur', () => {
        // Pequeño delay para permitir el click en el dropdown
        setTimeout(() => {
            pacientesDropdown.style.display = 'none';
        }, 150);
    });

    // Botón "Nuevo" para registrar un paciente
    document.getElementById('btnMostrarRegistroPaciente').onclick = function() {
        limpiarFormularioPaciente();
    };

    // Botón para Guardar (nuevo) o Actualizar (existente) un paciente
    btnGuardarPaciente.onclick = async function() {
        // --- INICIO: Lógica de botón de carga ---
        const originalBtnContent = this.innerHTML;
        const actionText = editingPacienteId ? 'Actualizando...' : 'Guardando...';
        this.disabled = true;
        this.innerHTML = `<i class="fas fa-spinner spinner-icon" style="margin-right:6px;"></i> <span>${actionText}</span>`;
        // --- FIN: Lógica de botón de carga ---

        const origenSelect = document.getElementById('nuevoPacienteOrigen');
        const selectedOption = origenSelect.options[origenSelect.selectedIndex];
        let finalOrigen = "";
        let finalRecId = "";

        if (origenSelect.value === 'DOCTOR') {
            finalOrigen = 'DOCTOR: ' + document.getElementById('nuevoPacienteDoctorNombre').value.trim();
        } else if (origenSelect.value !== "") {
            finalOrigen = selectedOption ? selectedOption.dataset.nombre : "";
            finalRecId = origenSelect.value;
        }

        const pacienteData = {
            id: editingPacienteId, // Será null si es un nuevo paciente
            nombre: document.getElementById('nuevoPacienteNombre').value.trim(),
            apellido_paterno: document.getElementById('nuevoPacienteApellidoPaterno').value.trim(),
            apellido_materno: document.getElementById('nuevoPacienteApellidoMaterno').value.trim(),
            telefono: document.getElementById('nuevoPacienteTelefono').value.trim(),
            correo: document.getElementById('nuevoPacienteCorreo').value.trim(),
            diagnostico: document.getElementById('nuevoPacienteDiagnostico').value.trim(),
            alergias: document.getElementById('nuevoPacienteAlergias').value.trim(),
            estado_id: document.getElementById('nuevoPacienteTipo').value,
            origen: finalOrigen,
            recomendado_por_id: finalRecId,
            comentarios: document.getElementById('nuevoPacienteComentarios').value.trim(),
            fecha_nacimiento: document.getElementById('nuevoPacienteFechaNacimiento').value.trim()
        };

        if (!pacienteData.nombre || !pacienteData.apellido_paterno) {
            mostrarNotificacion('Por favor ingresa nombre y apellido paterno del paciente.', 'warning');
            // Restaurar botón en caso de validación fallida
            this.disabled = false;
            this.innerHTML = originalBtnContent;
            return;
        }

        const url = editingPacienteId ? 'citas/actualizar_paciente.php' : 'citas/guardar_paciente.php';
        const method = 'POST';

        try {
            let body;
            let headers = {};

            if (editingPacienteId) {
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify(pacienteData);
            } else {
                // Para guardar_paciente.php, que espera FormData/urlencoded
                const formData = new FormData();
                for (const key in pacienteData) {
                    if (pacienteData[key] !== null) {
                        formData.append(key, pacienteData[key]);
                    }
                }
                body = formData;
            }

            const response = await fetch(url, { method, headers, body });
            const resp = await response.json();

            if (resp.success) {
                const nombreCompleto = `${pacienteData.nombre} ${pacienteData.apellido}`;
                mostrarNotificacion(`Paciente ${editingPacienteId ? 'actualizado' : 'registrado'} correctamente.`, 'success');
                
                pacienteInput.value = nombreCompleto;
                pacienteInput.dataset.pacienteId = resp.id;

                if (!editingPacienteId) {
                    registroPacienteBox.style.display = 'none';
                }
                
                // Construct the object that `seleccionarPaciente` expects
                const pacienteActualizado = {
                    id: resp.id,
                    nombre: `${pacienteData.nombre} ${pacienteData.apellido}`,
                    nombre_solo: pacienteData.nombre,
                    apellido: pacienteData.apellido,
                    telefono: pacienteData.telefono,
                    correo: pacienteData.correo,
                    diagnostico: pacienteData.diagnostico,
                    estado_id: pacienteData.estado_id,
                    origen: pacienteData.origen,
                    comentarios: pacienteData.comentarios,
                    fecha_nacimiento: pacienteData.fecha_nacimiento
                };
                
                // Re-populate the form with the correct, full data
                seleccionarPaciente(pacienteActualizado);
                
                // Ensure we stay in edit mode with the correct ID
                editingPacienteId = resp.id;

            } else {
                mostrarNotificacion(`Error al ${editingPacienteId ? 'actualizar' : 'guardar'} paciente: ${resp.error || 'Error desconocido'}`, 'error');
                // Restaurar botón en caso de error de la API
                this.disabled = false;
                this.innerHTML = originalBtnContent;
            }
        } catch (error) {
            console.error('Error en la operación de paciente:', error);
            mostrarNotificacion('Hubo un error de conexión.', 'error');
            // Restaurar botón en caso de error de conexión/fetch
            this.disabled = false;
            this.innerHTML = originalBtnContent;
        }
    };

    document.getElementById('btnCancelarPaciente').onclick = function() {
      registroPacienteBox.style.display = 'none';
    };

    // -- Calendarios y demás lógica --
    function cargarProfesionales() {
      // Use the citas path and a debug parser to avoid HTML/login pages breaking JSON.parse
      fetchJsonDebug('citas/recursos_json.php')
        .then(function(data) {
          const select = document.getElementById('profesional-select');
          select.innerHTML = '';
          // Opción 'Todos'
          const optTodos = document.createElement('option');
          optTodos.value = 'todos';
          optTodos.textContent = 'Todos';
          select.appendChild(optTodos);
          // Modalidades
          data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.title;
            select.appendChild(opt);
          });
        })
        .catch(function(err) {
          console.error('Error cargando profesionales:', err);
        });
    }
    cargarProfesionales();

    function cargarEstados() {
      fetch('estados_json.php')
        .then(r => r.json())
        .then(data => {
          // 1. Llenar filtro del sidebar
          const select = document.getElementById('estado-select');
          const agendarSelect = document.getElementById('agendarEstado');
          const editarSelect = document.getElementById('editarEstado');
          
          select.innerHTML = '';
          agendarSelect.innerHTML = '';
          editarSelect.innerHTML = '';

          // Opción 'Todos'
          const optTodos = document.createElement('option');
          optTodos.value = 'todos';
          optTodos.textContent = 'Todos';
          select.appendChild(optTodos);
          
          // Estados
          data.forEach(item => {
            // Opción para filtro
            const optFilter = document.createElement('option');
            optFilter.value = item.id;
            optFilter.textContent = item.nombre.charAt(0).toUpperCase() + item.nombre.slice(1);
            select.appendChild(optFilter);

            // Opción para Agendar
            const optAgendar = document.createElement('option');
            optAgendar.value = item.id;
            optAgendar.textContent = item.nombre.charAt(0).toUpperCase() + item.nombre.slice(1);
            // Seleccionar 'reservado' (ID 1) o el primero por defecto
            if (item.id == 1) optAgendar.selected = true;
            agendarSelect.appendChild(optAgendar);

            // Opción para Editar
            const optEditar = document.createElement('option');
            optEditar.value = item.id;
            optEditar.textContent = item.nombre.charAt(0).toUpperCase() + item.nombre.slice(1);
            editarSelect.appendChild(optEditar);
          });
        });
    }
    cargarEstados();

    // --- Funciones para el Modal de Agendar ---
      function cargarServiciosPorModalidad(modalidadId, doctorId) {
      var servicioSelect = document.getElementById('agendarServicio');
      if (!servicioSelect) return; // Ensure element exists
      
      servicioSelect.innerHTML = '<option value="">Cargando servicios...</option>';
      var duracionInfo = document.getElementById('duracionInfo');
      if (duracionInfo) duracionInfo.style.display = 'none';
      
      if (!modalidadId) {
          servicioSelect.innerHTML = '<option value="">Primero seleccione médico...</option>';
          return;
      }
      
      // Fetch doctor's specialty from the selected modality's owner
        if (!doctorId) {
          const modSelect = document.getElementById('agendarModalidad');
          if (modSelect && modSelect.selectedIndex >= 0) {
            doctorId = modSelect.options[modSelect.selectedIndex].dataset.usuarioId;
          }
      }

      
let params = new URLSearchParams();
      if (modalidadId) params.append('modalidad_id', modalidadId);
      if (doctorId) params.append('medico_id', doctorId);

      let url = 'citas/servicios_json.php?' + params.toString();

      fetch(url)
        .then(r => r.json())
        .then(data => {
          servicioSelect.innerHTML = '<option value="">Seleccione un servicio</option>';
          data.forEach(function(servicio) {
            // Filter services by specialty:
            // - If the service has no specialty (general)
            // - If the service's specialty matches the doctor's specialty
            // (This filtering is now handled by the backend `citas/servicios_json.php`)
            // Solo mostramos servicios si no tienen modalidad o si coinciden (opcional)
            var opt = document.createElement('option');
            opt.value = servicio.id;
            opt.textContent = servicio.nombre;
            opt.setAttribute('data-duracion', servicio.duracion_minutos || 30);
            servicioSelect.appendChild(opt);
          });
          servicioSelect.onchange = manejarCambioServicio;
        })
        .catch(err => {
          console.error('Error:', err);
          servicioSelect.innerHTML = '<option value="">Error al cargar</option>';
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
      configurarInputsTiempoDobleClic();
      const sidebar = document.querySelector('.sidebar');
      const resizer = document.getElementById('sidebar-resizer');
      
      let isResizing = false;
      let initialX;
      let initialWidth;

      if (resizer) {
        resizer.addEventListener('mousedown', function(e) {
            isResizing = true;
            initialX = e.clientX;
            initialWidth = sidebar.offsetWidth;

            // Añadir listeners al documento para capturar eventos globalmente
            document.addEventListener('mousemove', resizeSidebar);
            document.addEventListener('mouseup', stopResizing);

            // Prevenir selección de texto durante el arrastre
            document.body.style.userSelect = 'none';
            document.body.style.cursor = 'ew-resize';
        });
      }

      function resizeSidebar(e) {
          if (!isResizing) return;

          const newWidth = initialWidth + (e.clientX - initialX);
          const minWidth = 250; // Ancho mínimo de la barra lateral
          const maxWidth = 600; // Ancho máximo de la barra lateral

          if (newWidth >= minWidth && newWidth <= maxWidth) {
              sidebar.style.width = `${newWidth}px`;
              // FullCalendar necesita ser notificado para actualizar su tamaño
              if (calendar) {
                  calendar.updateSize();
              }
          }
      }

      function stopResizing() {
          isResizing = false;
          document.removeEventListener('mousemove', resizeSidebar);
          document.removeEventListener('mouseup', stopResizing);
          document.body.style.userSelect = '';
          document.body.style.cursor = '';
      }
      // --- Fin Lógica para el Resizer ---

      // ... (resto del código DOMContentLoaded) ...


      // ... (resto del código DOMContentLoaded) ...

      var modalidadSelect = document.getElementById('profesional-select');
      modalidadSelect.addEventListener('change', function() {
        var modalidadId = modalidadSelect.value;
        cargarServiciosPorModalidad(modalidadId);
        // Filtrar recursos en el calendario
        if (modalidadId === 'todos') {
          calendar.setOption('resources', function(fetchInfo, successCallback, failureCallback) {
            fetchJsonDebug('citas/recursos_json.php').then(successCallback).catch(failureCallback);
          });
        } else {
          fetchJsonDebug('citas/recursos_json.php')
            .then(data => {
              const recurso = data.find(item => String(item.id) == String(modalidadId));
              if (recurso) {
                calendar.setOption('resources', [recurso]);
              }
            })
            .catch(function(err){ console.error('Error fetching recursos for filter:', err); });
        }
      });

      cargarDentistasBloqueadosSidebar();

      var today = new Date();
      var firstDayNext = new Date(today.getFullYear(), today.getMonth() + 1, 1);
      
      // Variable global para almacenar eventos por fecha
      var eventosPorFecha = {};
      
      // Función para actualizar marcadores en mini calendarios
      function actualizarMarcadoresMiniCalendarios() {
        // Obtener eventos del calendario principal
        var eventos = calendar.getEvents();
        eventosPorFecha = {};
        
        eventos.forEach(function(evento) {
          if (evento.start) {
            var fechaKey = evento.start.toISOString().split('T')[0];
            if (!eventosPorFecha[fechaKey]) {
              eventosPorFecha[fechaKey] = [];
            }
            eventosPorFecha[fechaKey].push({
              color: evento.backgroundColor || evento.color || '#2196F3',
              estado: evento.extendedProps.estado || 'reservado'
            });
          }
        });
        
        // Forzar redibujado de los mini calendarios
        setTimeout(function() {
          agregarMarcadoresAFlatpickr();
        }, 100);
      }
      
      // Función para agregar marcadores visuales a las fechas
      function agregarMarcadoresAFlatpickr() {
        document.querySelectorAll('.flatpickr-day').forEach(function(dia) {
          var fecha = dia.dateObj;
          if (fecha) {
            var fechaKey = fecha.toISOString().split('T')[0];
            var eventosDia = eventosPorFecha[fechaKey] || [];
            
            // Limpiar marcadores existentes
            var marcadorExistente = dia.querySelector('.mini-calendar-marker');
            if (marcadorExistente) {
              marcadorExistente.remove();
            }
            
            if (eventosDia.length > 0) {
              var marcador = document.createElement('div');
              marcador.className = 'mini-calendar-marker';
              marcador.style.cssText = `
                position: absolute;
                bottom: 2px;
                right: 2px;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: ${eventosDia[0].color};
                border: 1px solid white;
                box-shadow: 0 0 2px rgba(0,0,0,0.3);
                pointer-events: none;
              `;
              dia.style.position = 'relative';
              dia.appendChild(marcador);
            }
          }
        });
      }
      
      flatpickr('#mini-calendar-actual', {
        locale: flatpickr.l10ns.es,
        inline: true,
        defaultDate: today,
        showMonths: 1,
        onChange: function(selectedDates) {
          if (selectedDates && selectedDates[0]) {
            calendar.changeView('resourceTimeGridDay');
            calendar.gotoDate(selectedDates[0]);
          }
        },
        onMonthChange: function() {
          setTimeout(agregarMarcadoresAFlatpickr, 50);
        },
        onYearChange: function() {
          setTimeout(agregarMarcadoresAFlatpickr, 50);
        },
        onReady: function() {
          setTimeout(agregarMarcadoresAFlatpickr, 100);
        }
      });
      flatpickr('#mini-calendar-proximo', {
        locale: flatpickr.l10ns.es,
        inline: true,
        defaultDate: firstDayNext,
        showMonths: 1,
        onChange: function(selectedDates) {
          if (selectedDates && selectedDates[0]) {
            calendar.changeView('resourceTimeGridDay');
            calendar.gotoDate(selectedDates[0]);
          }
        },
        onMonthChange: function() {
          setTimeout(agregarMarcadoresAFlatpickr, 50);
        },
        onYearChange: function() {
          setTimeout(agregarMarcadoresAFlatpickr, 50);
        },
        onReady: function() {
          setTimeout(agregarMarcadoresAFlatpickr, 100);
        }
      });

      // --- Lógica del Menú Contextual ---
      var contextMenu = document.getElementById('contextMenu');
      var lastSelectionInfo = null;
      var puedeCrear = <?php echo json_encode($puede_crear_citas); ?>;

      // Deshabilitar botones si el usuario no tiene permisos
      if (!puedeCrear) {
          document.getElementById('ctxAgendarBtn').disabled = true;
          document.getElementById('ctxBloquearBtn').disabled = true;
      }

      // Agendar desde el menú contextual
      document.getElementById('ctxAgendarBtn').addEventListener('click', function() {
          if (lastSelectionInfo) {
              abrirModalAgendar(lastSelectionInfo);
          }
          contextMenu.style.display = 'none';
      });

      // Bloquear desde el menú contextual
      var ctxBloquearBtn = document.getElementById('ctxBloquearBtn');
      if (ctxBloquearBtn) {
        ctxBloquearBtn.addEventListener('click', function() {
          if (lastSelectionInfo) {
              abrirModalBloquear(lastSelectionInfo);
          }
          contextMenu.style.display = 'none';
        });
      }

      // Botón lateral para bloquear
      var btnBloquearDiaSidebar = document.getElementById('btnBloquearDiaSidebar');
      if (btnBloquearDiaSidebar) {
          btnBloquearDiaSidebar.addEventListener('click', function() {
              abrirModalBloquear(null);
          });
      }
      var btnBloquearDentistaSidebar = document.getElementById('btnBloquearDentistaSidebar');
      if (btnBloquearDentistaSidebar) {
          btnBloquearDentistaSidebar.addEventListener('click', function() {
              abrirModalBloquearDentista();
          });
      }

      // --- Lógica Modal Configuración Horarios (Admin) ---
      <?php if ($puede_configurar_horarios): ?>
      let adminBlockedTimes = <?php echo $admin_blocked_times ?: '[]'; ?>;
      const adminSlotInterval = "<?php echo $admin_slot_interval; ?>";

      // Botón abrir modal
      const btnConfig = document.getElementById('btnConfigHorariosSidebar');
      if(btnConfig) {
          btnConfig.addEventListener('click', function() {
              document.getElementById('admin_slot_interval').value = adminSlotInterval;
              renderAdminBlockedTimes();
              document.getElementById('modalAdminHorarios').style.display = 'flex';
          });
      }

      // Cerrar modal
      document.getElementById('cerrarModalAdminHorarios').onclick = function() {
          document.getElementById('modalAdminHorarios').style.display = 'none';
      };

      // Renderizar lista de bloqueos
      function renderAdminBlockedTimes() {
          const container = document.getElementById('admin-blocked-list');
          container.innerHTML = '';
          if (adminBlockedTimes.length === 0) {
              container.innerHTML = '<div style="text-align:center;padding:10px;color:#666;font-size:13px;">No hay bloqueos definidos.</div>';
              return;
          }
          adminBlockedTimes.forEach((block, index) => {
              const div = document.createElement('div');
              div.style.cssText = 'display:flex;justify-content:space-between;align-items:center;background:#1f2937;padding:8px 12px;border-radius:6px;margin-bottom:8px;border:1px solid #333;';
              div.innerHTML = `
                  <span style="color:#e5e7eb;font-size:14px;">De <strong>${block.inicio}</strong> a <strong>${block.fin}</strong></span>
                  <button onclick="removeAdminBlock(${index})" style="background:none;border:none;color:#ef4444;cursor:pointer;"><i class="fas fa-trash"></i></button>
              `;
              container.appendChild(div);
          });
      }

      // Añadir bloqueo
      document.getElementById('btnAddBlock').addEventListener('click', function() {
          const start = document.getElementById('new_block_start').value;
          const end = document.getElementById('new_block_end').value;

          if (!start || !end) { mostrarNotificacion('Seleccione horas de inicio y fin', 'warning'); return; }
          if (start >= end) { mostrarNotificacion('La hora de inicio debe ser menor a la final', 'warning'); return; }

          adminBlockedTimes.push({ inicio: start, fin: end });
          adminBlockedTimes.sort((a, b) => a.inicio.localeCompare(b.inicio));
          renderAdminBlockedTimes();
          
          document.getElementById('new_block_start').value = '';
          document.getElementById('new_block_end').value = '';
      });

      // Eliminar bloqueo (Global scope wrapper)
      window.removeAdminBlock = function(index) {
          adminBlockedTimes.splice(index, 1);
          renderAdminBlockedTimes();
      };

      // Guardar cambios
      document.getElementById('btnSaveAdminHorarios').addEventListener('click', function() {
          const btn = this;
          const originalText = btn.innerHTML;
          btn.disabled = true;
          btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

          const settings = {
              slot_interval: document.getElementById('admin_slot_interval').value,
              blocked_times: JSON.stringify(adminBlockedTimes)
          };

          fetch('citas/guardar_config_horarios.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(settings)
          })
          .then(r => r.json())
          .then(data => {
              if (data.success) {
                  mostrarNotificacion('Configuración guardada. Recargando...', 'success');
                  setTimeout(() => location.reload(), 1000); // Recargar para aplicar cambios en calendario
              } else {
                  mostrarNotificacion('Error: ' + data.error, 'error');
                  btn.disabled = false; btn.innerHTML = originalText;
              }
          }).catch(err => { mostrarNotificacion('Error de conexión', 'error'); btn.disabled = false; btn.innerHTML = originalText; });
      });
      <?php endif; ?>

      // Cargar modalidades en el select del modal de bloqueo
      function cargarModalidadesBloqueo() {
          const select = document.getElementById('bloquearModalidadId');
          // Guardar valor actual si existe
          const currentVal = select.value;
          select.innerHTML = '<option value="">Seleccione modalidad</option>';
          
          // Usar la función fetchJsonDebug que ya tienes definida o fetch directo
          fetch('citas/recursos_json.php')
            .then(r => r.json())
            .then(data => {
                data.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.title;
                    select.appendChild(opt);
                });
                if (currentVal) select.value = currentVal;
            })
            .catch(err => console.error('Error cargando modalidades para bloqueo:', err));
      }

      // Checkbox "Todo el día"
      document.getElementById('checkTodoDia').addEventListener('change', function() {
          const horaInicio = document.getElementById('bloquearHoraInicio');
          const horaFin = document.getElementById('bloquearHoraFin');
          
          if(this.checked) {
              horaInicio.value = '00:00';
              horaFin.value = '23:59';
              horaInicio.readOnly = true;
              horaFin.readOnly = true;
              horaInicio.style.opacity = '0.5';
              horaFin.style.opacity = '0.5';
          } else {
              horaInicio.readOnly = false;
              horaFin.readOnly = false;
              horaInicio.style.opacity = '1';
              horaFin.style.opacity = '1';
          }
      });

      document.getElementById('checkTodoDiaDoctor').addEventListener('change', function() {
          const horaInicio = document.getElementById('bloquearDoctorHoraInicio');
          const horaFin = document.getElementById('bloquearDoctorHoraFin');
          
          if(this.checked) {
              horaInicio.value = '00:00';
              horaFin.value = '23:59';
              horaInicio.readOnly = true;
              horaFin.readOnly = true;
              horaInicio.style.opacity = '0.5';
              horaFin.style.opacity = '0.5';
          } else {
              horaInicio.readOnly = false;
              horaFin.readOnly = false;
              horaInicio.style.opacity = '1';
              horaFin.style.opacity = '1';
          }
      });

      // --- Lógica del Modal de Bloqueo ---
      function abrirModalBloquear(info) {
          cargarModalidadesBloqueo(); // Asegurar que la lista esté cargada
          
          // Resetear checkbox
          document.getElementById('checkTodoDia').checked = false;
          document.getElementById('checkTodoDia').dispatchEvent(new Event('change'));

          if (info) {
              // Determinar fechas de inicio y fin (soporte para selección de rango y click simple)
              let start = info.start || info.date;
              let end = info.end || info.date; // Si es click simple, fin es igual a inicio
              let allDay = info.allDay || false;

              // Formatear Fecha Inicio
              document.getElementById('bloquearFecha').value = start.toISOString().split('T')[0];
              
              // Calcular Fecha Fin
              let endDateObj = new Date(end);
              // Si es selección de día completo en FullCalendar, la fecha fin es exclusiva (día siguiente), restamos 1 día
              if (info.end && allDay) {
                  endDateObj.setDate(endDateObj.getDate() - 1);
              }
              document.getElementById('bloquearFechaFin').value = endDateObj.toISOString().split('T')[0];

              // Horas
              let horaInicio = start.toTimeString().substring(0, 5);
              let horaFin = (info.end && !allDay) ? info.end.toTimeString().substring(0, 5) : "09:00";
              if (allDay) { horaInicio = "08:00"; horaFin = "09:00"; } // Valores por defecto si es vista de mes

              document.getElementById('bloquearHoraInicio').value = horaInicio;
              document.getElementById('bloquearHoraFin').value = horaFin;
              
              // Esperar un momento a que se cargue el select para asignar el valor
              setTimeout(() => {
                  if(info.resource) document.getElementById('bloquearModalidadId').value = info.resource.id;
              }, 100);
          } else {
              // Si viene del botón lateral (valores por defecto)
              const today = new Date().toISOString().split('T')[0];
              document.getElementById('bloquearFecha').value = today;
              document.getElementById('bloquearFechaFin').value = today;
              document.getElementById('bloquearHoraInicio').value = "08:00";
              document.getElementById('bloquearHoraFin').value = "09:00";
          }

          cargarBloqueosActivos(); // Cargar la lista al abrir el modal
          document.getElementById('modalBloquear').style.display = 'flex';
      }

      // Función para cargar y renderizar la lista de bloqueos
      function cargarBloqueosActivos() {
          const container = document.getElementById('listaBloqueosActivos');
          container.innerHTML = '<div style="text-align:center;color:#666;font-size:13px;padding:10px;"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';

          fetch('citas/listar_bloqueos.php')
            .then(r => r.json())
            .then(data => {
                container.innerHTML = '';
                if (!data || data.length === 0) {
                    container.innerHTML = '<div style="text-align:center;color:#666;font-size:13px;padding:10px;">No hay bloqueos futuros registrados.</div>';
                    return;
                }

                data.forEach(block => {
                    const div = document.createElement('div');
                    // Estilo idéntico al de configuración de horarios
                    div.style.cssText = 'display:flex;justify-content:space-between;align-items:center;background:#1f2937;padding:10px 12px;border-radius:6px;margin-bottom:8px;border:1px solid #333;';
                    
                    // Formatear fecha
                    let fechaTexto = '';
                    const fInicio = new Date(block.fecha_inicio + 'T00:00:00').toLocaleDateString('es-ES', {day: 'numeric', month: 'short'});
                    
                    if (block.fecha_inicio === block.fecha_fin) {
                        fechaTexto = fInicio;
                    } else {
                        const fFin = new Date(block.fecha_fin + 'T00:00:00').toLocaleDateString('es-ES', {day: 'numeric', month: 'short'});
                        fechaTexto = `${fInicio} - ${fFin}`;
                    }
                    
                    // Guardar IDs en un atributo data para usarlo al eliminar
                    const idsJson = JSON.stringify(block.ids);

                    div.innerHTML = `
                        <div style="overflow:hidden;">
                            <div style="color:#e5e7eb;font-size:14px;font-weight:500;">${fechaTexto} <span style="color:#9ca3af;font-weight:400;">| ${block.hora_inicio} - ${block.hora_fin}</span></div>
                            <div style="color:#d1d5db;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${block.doctor ? block.doctor + ' · ' : ''}${block.modalidad || 'General'} - ${block.motivo || 'Sin motivo'}</div>
                        </div>
                        <button type="button" onclick='eliminarBloqueoGrupo(${idsJson})' style="background:rgba(239, 68, 68, 0.1);border:1px solid rgba(239, 68, 68, 0.3);color:#ef4444;cursor:pointer;padding:6px 10px;border-radius:4px;margin-left:10px;transition:all 0.2s;">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                    container.appendChild(div);
                });
            })
            .catch(err => {
                console.error('Error cargando bloqueos:', err);
                container.innerHTML = '<div style="text-align:center;color:#ef4444;font-size:13px;">Error al cargar lista.</div>';
            })
            .finally(() => {
                cargarDentistasBloqueadosSidebar();
            });
      }

      function cargarDentistasBloqueadosSidebar() {
          const container = document.getElementById('sidebarDentistasBloqueados');
          if (!container) return;

          container.innerHTML = '<div style="text-align:center;color:#9ca3af;font-size:13px;padding:10px;"><i class="fas fa-spinner fa-spin"></i> Cargando dentistas bloqueados...</div>';

          fetch('citas/listar_bloqueos.php')
            .then(r => r.json())
            .then(data => {
                const doctorBlocks = (data || []).filter(block => block.doctor);
                if (!doctorBlocks.length) {
                    container.innerHTML = '<div style="text-align:center;color:#9ca3af;font-size:13px;padding:10px;">No hay dentistas bloqueados.</div>';
                    return;
                }

                container.innerHTML = '';
                doctorBlocks.forEach(block => {
                    const item = document.createElement('div');
                    item.style.cssText = 'background:#111;padding:10px;border:1px solid #333;border-radius:8px;margin-bottom:8px;';
                    const fInicio = new Date(block.fecha_inicio + 'T00:00:00').toLocaleDateString('es-ES', {day: 'numeric', month: 'short'});
                    const fFin = new Date(block.fecha_fin + 'T00:00:00').toLocaleDateString('es-ES', {day: 'numeric', month: 'short'});
                    const fechaTexto = block.fecha_inicio === block.fecha_fin ? fInicio : `${fInicio} - ${fFin}`;
                    item.innerHTML = `
                        <div style="font-weight:600;color:#e5e7eb;">${block.doctor}</div>
                        <div style="color:#9ca3af;font-size:12px;line-height:1.4;">${fechaTexto}<br>${block.hora_inicio} - ${block.hora_fin}</div>
                    `;
                    container.appendChild(item);
                });
            })
            .catch(err => {
                console.error('Error cargando dentistas bloqueados:', err);
                container.innerHTML = '<div style="text-align:center;color:#ef4444;font-size:13px;padding:10px;">Error cargando dentistas bloqueados.</div>';
            });
      }

      // Nueva función para eliminar grupo de bloqueos
      window.eliminarBloqueoGrupo = function(ids) {
          const count = ids.length;
          const msg = count > 1 ? `¿Eliminar este grupo de ${count} bloqueos?` : '¿Eliminar este bloqueo?';
          
          if(!confirm(msg)) return;
          
          fetch('citas/eliminar_bloqueos.php', { 
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ ids: ids })
          })
            .then(r => r.json())
            .then(resp => {
                if(resp.success) {
                    cargarBloqueosActivos(); // Recargar lista
                    calendar.refetchEvents(); // Recargar calendario
                    mostrarNotificacion('Bloqueos eliminados', 'success');
                } else {
                    mostrarNotificacion('Error: ' + resp.error, 'error');
                }
            })
            .catch(err => mostrarNotificacion('Error de conexión', 'error'));
      };

      document.getElementById('cerrarModalBloquear').onclick = function() {
          document.getElementById('modalBloquear').style.display = 'none';
      };

      document.getElementById('cerrarModalBloquearDentista').onclick = function() {
          document.getElementById('modalBloquearDentista').style.display = 'none';
      };

      function abrirModalBloquearDentista() {
          const today = new Date().toISOString().split('T')[0];
          document.getElementById('bloquearDoctorProfesionalId').value = '';
          document.getElementById('bloquearDoctorFecha').value = today;
          document.getElementById('bloquearDoctorFechaFin').value = today;
          document.getElementById('bloquearDoctorHoraInicio').value = '08:00';
          document.getElementById('bloquearDoctorHoraFin').value = '18:00';
          document.getElementById('bloquearDoctorMotivo').value = '';
          document.getElementById('checkTodoDiaDoctor').checked = false;
          document.getElementById('checkTodoDiaDoctor').dispatchEvent(new Event('change'));
          document.getElementById('modalBloquearDentista').style.display = 'flex';
      }

      document.getElementById('formBloquearDoctor').onsubmit = function(e) {
          e.preventDefault();
          const formData = new FormData(this);
          const data = Object.fromEntries(formData.entries());

          if (!data.profesional_id) {
              mostrarNotificacion('Por favor, seleccione un dentista para bloquear.', 'warning');
              return;
          }
          if (!data.fecha) {
              mostrarNotificacion('Por favor, seleccione una fecha.', 'warning');
              return;
          }

          var btn = document.getElementById('btnConfirmarBloqueoDentista');
          const originalContent = btn.innerHTML;
          btn.disabled = true;
          btn.innerHTML = `<i class="fas fa-spinner spinner-icon"></i> <span>Bloqueando...</span>`;

          fetch('bloquear_espacio.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(data)
          })
          .then(response => response.json())
          .then(result => {
              if (result.success) {
                  mostrarNotificacion('Dentista bloqueado correctamente.', 'success');
                  cargarBloqueosActivos();
                  calendar.refetchEvents();
                  document.getElementById('bloquearDoctorMotivo').value = '';
                  document.getElementById('modalBloquearDentista').style.display = 'none';
              } else {
                  mostrarNotificacion('Error al bloquear el dentista: ' + (result.error || 'Error desconocido.'), 'error');
              }
          })
          .catch(error => {
              console.error('Error:', error);
              mostrarNotificacion('Error de conexión al intentar bloquear el dentista.', 'error');
          })
          .finally(() => {
              btn.disabled = false;
              btn.innerHTML = originalContent;
          });
      };

      document.getElementById('formBloquear').onsubmit = function(e) {
          e.preventDefault();
          const formData = new FormData(this);
          const data = Object.fromEntries(formData.entries());

          // Validación frontend antes de enviar
          if (!data.modalidad_id) {
              mostrarNotificacion('Por favor, seleccione un consultorio para bloquear el espacio.', 'warning');
              return;
          }
          if (!data.fecha) {
              mostrarNotificacion('Por favor, seleccione una fecha.', 'warning');
              return;
          }

          var btn = document.getElementById('btnConfirmarBloqueo');
          const originalContent = btn.innerHTML;
          btn.disabled = true;
          btn.innerHTML = `<i class="fas fa-spinner spinner-icon"></i> <span>Bloqueando...</span>`;

          fetch('bloquear_espacio.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(data)
          })
          .then(response => response.json())
          .then(result => {
              if (result.success) {
                  mostrarNotificacion('Espacio bloqueado correctamente.', 'success');
                  // No cerramos modal, recargamos la lista para ver el nuevo bloqueo
                  cargarBloqueosActivos();
                  calendar.refetchEvents();
                  // Opcional: limpiar campos
                  document.getElementById('bloquearMotivo').value = '';
              } else {
                  mostrarNotificacion('Error al bloquear el espacio: ' + (result.error || 'Error desconocido.'), 'error');
              }
          })
          .catch(error => {
              console.error('Error:', error);
              mostrarNotificacion('Error de conexión al intentar bloquear el espacio.', 'error');
          })
          .finally(() => {
              btn.disabled = false;
              btn.innerHTML = originalContent;
          });
      };







      var calendarEl = document.getElementById('calendar');
      var contextMenu = document.getElementById('contextMenu');
      var bloquearBtn = document.getElementById('bloquearBtn');
      var agendarBtn = document.getElementById('agendarBtn');
      var lastDateClickInfo = null;
      var tooltipActivo = null; // Variable global para controlar tooltips

      // Event listener global para cerrar tooltip al hacer click fuera
      document.addEventListener('click', function(e) {
        // Cerrar menú contextual si se hace click fuera
        if (contextMenu.style.display === 'block') {
          if (!contextMenu.contains(e.target)) {
            contextMenu.style.display = 'none';
          }
        }
        
        // Cerrar tooltip si se hace click fuera
        if (tooltipActivo && !tooltipActivo.contains(e.target) && !e.target.closest('.fc-event')) {
          // Verificar si el click es en un punto de estado (no cerrar en ese caso)
          if (e.target.classList.contains('estado-punto') || e.target.closest('.estado-punto')) {
            return; // No cerrar tooltip si se hace click en un punto de estado
          }
          
          // Si el click no es en el tooltip ni en una cita, cerrar tooltip
          if (tooltipActivo.parentNode) {
            document.body.removeChild(tooltipActivo);
          }
          tooltipActivo = null;
          
          // Limpiar referencias en todos los elementos
          var eventos = document.querySelectorAll('.fc-event');
          eventos.forEach(function(evento) {
            if (evento._fcTooltip) {
              evento._fcTooltip = null;
            }
            if (evento._hideTimeout) {
              clearTimeout(evento._hideTimeout);
              evento._hideTimeout = null;
            }
          });
        }
      });

      // Función para cambiar el estado de una cita
      function cambiarEstadoCita(citaId, nuevoEstado, evento, elementoCita) {
        // Mostrar indicador de carga
        var tooltip = elementoCita._fcTooltip;
        if (tooltip && tooltip.parentNode) {
          var loadingDiv = tooltip.querySelector('.estado-puntos');
          if (loadingDiv) {
            loadingDiv.innerHTML = '<span style="font-size:12px;">Actualizando estado...</span>';
          }
        }
        
        var formData = new FormData();
        formData.append('cita_id', citaId);
        formData.append('estado', nuevoEstado);
        
        fetch('actualizar_estado.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Actualizar el evento en el calendario
            evento.setExtendedProp('estado', nuevoEstado);
            evento.setProp('backgroundColor', data.nuevo_color);
            evento.setProp('borderColor', data.nuevo_color);
            evento.setProp('color', data.nuevo_color);
            
            // También aplicar el color directamente al elemento DOM
            elementoCita.style.backgroundColor = data.nuevo_color;
            elementoCita.style.borderColor = data.nuevo_color;
            
            // Actualizar marcadores en mini calendarios
            actualizarMarcadoresMiniCalendarios();
            
            // Cerrar el tooltip actual
            if (tooltip && tooltip.parentNode) {
              document.body.removeChild(tooltip);
              elementoCita._fcTooltip = null;
              tooltipActivo = null;
            } else if (tooltip) {
              // Si el tooltip existe pero ya no tiene parent, solo limpiar las referencias
              elementoCita._fcTooltip = null;
              tooltipActivo = null;
            }
            
            // Mostrar mensaje de éxito
            var successMsg = document.createElement('div');
            successMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 100000; background: #4CAF50; color: white; padding: 12px 20px; border-radius: 4px; font-family: Roboto, sans-serif; box-shadow: 0 2px 8px rgba(0,0,0,0.2);';
            successMsg.textContent = 'Estado actualizado a: ' + nuevoEstado;
            document.body.appendChild(successMsg);
            
            setTimeout(function() {
              if (successMsg.parentNode) {
                document.body.removeChild(successMsg);
              }
            }, 3000);
            
          } else {
            // Restablecer el tooltip si aún existe
            var tooltip = elementoCita._fcTooltip;
            if (tooltip && tooltip.parentNode) {
              var loadingDiv = tooltip.querySelector('.estado-puntos');
              if (loadingDiv) {
                loadingDiv.innerHTML = '<span style="font-size:12px; color:red;">Error al actualizar</span>';
              }
            }
            
            mostrarNotificacion('Error al actualizar el estado: ' + (data.error || 'Error desconocido'), 'error');
          }
        })
        .catch(function(error) {
          console.error('Error:', error);
          
          // Restablecer el tooltip si aún existe
          var tooltip = elementoCita._fcTooltip;
          if (tooltip && tooltip.parentNode) {
            var loadingDiv = tooltip.querySelector('.estado-puntos');
            if (loadingDiv) {
              loadingDiv.innerHTML = '<span style="font-size:12px; color:red;">Error al actualizar</span>';
            }
          }
          
          mostrarNotificacion('Error de conexión al actualizar el estado', 'error');
        });
      }

      // --- CONFIGURACIÓN DINÁMICA DE HORARIOS Y BLOQUEOS VISUALES ---
      // Calcular string de duración para FullCalendar (ej: "00:30:00")
      var confInterval = parseInt("<?php echo $admin_slot_interval; ?>") || 30;
      var confH = Math.floor(confInterval / 60);
      var confM = confInterval % 60;
      var strDuration = String(confH).padStart(2, '0') + ':' + String(confM).padStart(2, '0') + ':00';

      // Calcular y aplicar altura inicial de slots inmediatamente
      try {
        var PixelsPerMinute_init = 50 / 30;
        var initialSlotHeight = Math.round(confInterval * PixelsPerMinute_init);
        document.documentElement.style.setProperty('--fc-slot-height', initialSlotHeight + 'px');
      } catch (e) {
        // no-op
      }

      // Inyectar CSS para ocultar filas de horarios bloqueados
      window.globalBlockedTimes = <?php echo $admin_blocked_times ?: '[]'; ?>;
      var blTimes = window.globalBlockedTimes;
      if (blTimes.length > 0) {
          var cssHide = '';
          blTimes.forEach(function(b) {
              var sParts = b.inicio.split(':');
              var eParts = b.fin.split(':');
              var startM = parseInt(sParts[0])*60 + parseInt(sParts[1]);
              var endM = parseInt(eParts[0])*60 + parseInt(eParts[1]);
              
              // Iterar por cada slot dentro del rango bloqueado
              for (var t = startM; t < endM; t += confInterval) {
                  var th = Math.floor(t / 60);
                  var tm = t % 60;
                  var tStr = String(th).padStart(2, '0') + ':' + String(tm).padStart(2, '0') + ':00';
                  // Ocultar fila del grid y etiqueta del eje
                  cssHide += 'tr[data-time="' + tStr + '"] { display: none !important; } ';
                  cssHide += '.fc-timegrid-slot[data-time="' + tStr + '"] { display: none !important; } ';
              }
          });
          var st = document.createElement('style');
          st.innerHTML = cssHide;
          document.head.appendChild(st);
      }

      window.calendar = new FullCalendar.Calendar(calendarEl, {
        eventDidMount: function(info) {
          var event = info.event;
          var props = event.extendedProps;
          
          // --- INICIO: Lógica diferenciada para bloqueos y citas ---

          // LÓGICA PARA EVENTOS BLOQUEADOS (estado_id == 9)
          if (props && (props.estado_id == 9 || props.estado === 'Bloqueado')) {
            // 1. Tooltip simple que solo muestra el motivo
            var motivo = event.title || 'Bloqueado';
            
            // Usaremos un tooltip personalizado simple para mantener la consistencia
            info.el.addEventListener('mouseenter', function(e) {
                if (tooltipActivo) return; // No mostrar si ya hay uno
                let tip = document.createElement('div');
                tip.className = 'fc-custom-tooltip';
                tip.textContent = motivo;
                document.body.appendChild(tip);
                tip.style.left = (e.clientX + 10) + 'px';
                tip.style.top = (e.clientY + 10) + 'px';
                tooltipActivo = tip;

                info.el.addEventListener('mouseleave', function() {
                    if (tooltipActivo) {
                        document.body.removeChild(tooltipActivo);
                        tooltipActivo = null;
                    }
                }, { once: true });
            });

            // 2. Al hacer clic, preguntar para eliminar el bloqueo
            info.el.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                Swal.fire({
                    title: '¿Eliminar bloqueo?',
                    text: "Motivo: " + motivo,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch(`eliminar_cita.php?cita_id=${event.id}`, { method: 'GET' })
                            .then(r => r.json())
                            .then(resp => {
                                if (resp.success) {
                                    mostrarNotificacion('Bloqueo eliminado correctamente.', 'success');
                                    calendar.refetchEvents();
                                } else {
                                    mostrarNotificacion('Error al eliminar el bloqueo: ' + (resp.error || 'Desconocido'), 'error');
                                }
                            })
                            .catch(err => {
                                console.error('Error al eliminar bloqueo:', err);
                                mostrarNotificacion('Error de conexión al eliminar el bloqueo.', 'error');
                            });
                    }
                });
            });

            // 3. Detener la ejecución para que no se aplique la lógica de citas normales
            return;
          }

          // --- FIN LÓGICA PARA EVENTOS BLOQUEADOS ---


          // --- INICIO LÓGICA PARA CITAS NORMALES (código original) ---
          
          // Asegurar que el color se aplique correctamente
          // Aplicar estilo de tarjeta tipo "Citas de Hoy"
          var color = event.backgroundColor || event.color || '#2196F3';
          
          // Estilo base de tarjeta oscura
          info.el.style.backgroundColor = '#0a0a0a';
          info.el.style.border = '1px solid #222';
          
          // Borde lateral del color del estado/evento
          info.el.style.borderLeft = '4px solid ' + color;
          info.el.style.color = '#fff';
          
          var paciente = event.title.split(' (')[0];
          var doctor = event.extendedProps.doctor_nombre || 'No asignado';
          var servicio = event.title.split('(')[1]?.replace(')','') || '';
          var horaInicio = event.start ? event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
          var horaFin = event.end ? event.end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
          var telefono = event.extendedProps.telefono || '';
          var diagnostico = event.extendedProps.diagnostico || '';
          var pago = event.extendedProps.pago || 'No pagado';
          var atencionEspecial = event.extendedProps.atencion_especial == '1';
          var estadoActual = event.extendedProps.estado || '';
          var tipoPaciente = event.extendedProps.tipo_paciente || '';
          
          var todosLosEstados = [
            {nombre: 'reservado', color: '#2196F3', label: 'Reservado'},
            {nombre: 'confirmado', color: '#FF9800', label: 'Confirmado'},
            {nombre: 'asistió', color: '#E91E63', label: 'Asistió'},
            {nombre: 'no asistió', color: '#FF7F50', label: 'No Asistió'},
            {nombre: 'pendiente', color: '#F44336', label: 'Pendiente'},
            {nombre: 'en espera', color: '#4CAF50', label: 'En Espera'},
            {nombre: 'cancelada', color: '#797a79ff', label: 'Cancelada'}
          ];
          
          var estadoPuntos = todosLosEstados.map(estado => {
            var esActual = estadoActual.toLowerCase() === estado.nombre;
            var claseEstado = esActual ? 'activo' : 'clickeable';
            var border = esActual ? '2px solid #000' : '1px solid #ccc';
            return `
              <div class='estado-punto ${claseEstado}' 
                   data-estado='${estado.nombre}'
                   data-cita-id='${event.id}'
                   style='background-color:${estado.color}; border:${border};'>
                <div class='estado-punto-tooltip'>${estado.label}${esActual ? ' (Actual)' : ' - Click para cambiar'}</div>
              </div>
            `;
          }).join('');
          
          var tooltip = `
            <div style='font-family:Inter,sans-serif;max-width:280px;background:#0a0a0a;color:#e5e7eb;'>
              ${atencionEspecial ? `
                <div style='background:#331111;color:#fca5a5;padding:6px 10px;border-radius:6px;font-size:13px;font-weight:500;margin-bottom:8px;border:1px solid #7f1d1d;'>
                  <i class="fas fa-wheelchair" style="margin-right:6px;"></i> Requiere Asistencia Especial
                </div>
              ` : ''}
              <div style='font-weight:bold;font-size:16px;color:#ffffff;margin-bottom:8px;'>${paciente}</div>
              <div style='margin-bottom:6px; color:#2979ff; font-size:13px; font-weight:600; display:flex; align-items:center; gap:5px;'>
                <i class="fas fa-user-md"></i> <span>Dr(a): ${doctor}</span>
              </div>
              <div style='margin-bottom:6px;color:#d1d5db;font-weight:500;'>${servicio}</div>
              <div style='font-size:14px;color:#9ca3af;margin-bottom:4px;'><span style='margin-right:8px;'>🕒</span>${horaInicio} - ${horaFin}</div>
              <div style='font-size:14px;color:#9ca3af;margin-bottom:4px;'><span style='margin-right:8px;'>💲</span>${pago}</div>
              ${tipoPaciente ? `<div style='font-size:14px;color:#9ca3af;margin-bottom:4px;'><span style='margin-right:8px;'>👤</span>${tipoPaciente}</div>` : ''}
              <div class='estado-puntos' style='margin:8px 0;'>
                <span style='font-size:12px; margin-right:8px; color:#e5e7eb; font-weight:600;'>Estados:</span>
                ${estadoPuntos}
              </div>
              <hr style='margin:8px 0; border:none; border-top:1px solid #333;'>
              <div style='font-size:14px;color:#9ca3af;margin-bottom:4px;'><span style='margin-right:8px;'>📱</span>${telefono}</div>
              <div style='font-size:14px;color:#9ca3af;'><span style='margin-right:8px;'>💬</span>${diagnostico}</div>
            </div>
          `;
          info.el.setAttribute('title', '');
          info.el.addEventListener('mouseenter', function(e) {
            if (tooltipActivo) return;
            if (info.el._hideTimeout) clearTimeout(info.el._hideTimeout);
            
            let tip = document.createElement('div');
            tip.className = 'fc-custom-tooltip';
            tip.innerHTML = tooltip;
            tip.style.cssText = `
              position: absolute; z-index: 99999; background: #0a0a0a; border: 1px solid #333; color: #e5e7eb;
              box-shadow: 0 10px 25px rgba(0,0,0,0.5); padding: 16px; border-radius: 12px;
              font-size: 14px; pointer-events: auto; max-width: 300px; font-family: Inter, sans-serif;
            `;
            tip.style.top = (e.clientY + 15) + 'px';
            tip.style.left = (e.clientX + 15) + 'px';
            document.body.appendChild(tip);
            info.el._fcTooltip = tip;
            tooltipActivo = tip;
            
            tip.addEventListener('mouseenter', function() {
              if (info.el._hideTimeout) clearTimeout(info.el._hideTimeout);
            });
            
            tip.addEventListener('mouseleave', function() {
              info.el._hideTimeout = setTimeout(function() {
                if (info.el._fcTooltip && tooltipActivo === info.el._fcTooltip) {
                  info.el._fcTooltip = null;
                  tooltipActivo = null;
                }
              }, 300);
            });
            
            tip.addEventListener('click', function(e) {
              if (e.target.classList.contains('estado-punto') && e.target.classList.contains('clickeable')) {
                var nuevoEstado = e.target.getAttribute('data-estado');
                var citaId = e.target.getAttribute('data-cita-id');
                if (nuevoEstado && citaId) cambiarEstadoCita(citaId, nuevoEstado, event, info.el);
              }
            });
          });

          info.el.addEventListener('mousemove', function(e) {
            if (info.el._fcTooltip) {
              info.el._fcTooltip.style.top = (e.clientY + 12) + 'px';
              info.el._fcTooltip.style.left = (e.clientX + 12) + 'px';
            }
          });

          info.el.addEventListener('mouseleave', function() {
            info.el._hideTimeout = setTimeout(function() {
              if (info.el._fcTooltip && tooltipActivo === info.el._fcTooltip) {
                document.body.removeChild(info.el._fcTooltip);
                info.el._fcTooltip = null;
                tooltipActivo = null;
              }
            }, 300);
          });
          // Ajuste visual para citas cortas: aumentar altura mínima para mejor legibilidad
          try {
            var evStart = event.start;
            var evEnd = event.end || new Date(+evStart + 30*60*1000);
            var durMin = Math.max(1, Math.round((evEnd - evStart) / 60000));

            var slotDurOpt = window.calendar && window.calendar.getOption ? (window.calendar.getOption('slotDuration') || '00:30:00') : '00:30:00';
            var sd = slotDurOpt.split(':');
            var slotMinutes = (parseInt(sd[0],10)||0)*60 + (parseInt(sd[1],10)||0);

            // Leer altura del slot desde la variable CSS si existe
            var cssSlot = getComputedStyle(document.documentElement).getPropertyValue('--fc-slot-height');
            var slotPx = parseInt(cssSlot, 10) || Math.round(slotMinutes * (50/30));

            // Preferencia mínima: usar 75% de la altura del slot para mejor visibilidad
            // y asegurar un mínimo absoluto (60px) cuando el slot sea muy pequeño.
            var minPreferred = Math.max(60, Math.round(slotPx * 0.75));

            // Altura calculada por duración (uso la misma relación original: 50px por 30min)
            var pixelsPerMinute = 50 / 30;
            var computedH = Math.round(durMin * pixelsPerMinute);

            if (durMin < slotMinutes && computedH < minPreferred) {
              // Forzar altura visual mínima
              info.el.style.height = minPreferred + 'px';
              info.el.style.minHeight = minPreferred + 'px';
              info.el.style.overflow = 'visible';
              info.el.style.position = 'relative';
              info.el.style.zIndex = 12;

              var inner = info.el.querySelector('.fc-event-main') || info.el;
              try {
                inner.style.display = 'block';
                inner.style.padding = '6px 8px';
                inner.style.fontSize = '13px';
                inner.style.lineHeight = (Math.max(16, Math.round(minPreferred * 0.7)) + 'px');
                inner.style.maxHeight = (minPreferred - 6) + 'px';
                inner.style.overflow = 'hidden';
                // permitir que el contenido sobresalga visualmente (superposición controlada)
                info.el.style.pointerEvents = 'auto';
                // Asegurar que el servicio y tiempos estén calculados para usar en el contenedor compacto
                try {
                  var servicioText = servicio || (event.extendedProps && (event.extendedProps.servicio || event.extendedProps.servicio_nombre)) || paciente || event.title || '';
                  var tStart = horaInicio || (event.start ? event.start.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '');
                  var fallbackMinutes = confInterval || 30;
                  try {
                    if (event.extendedProps) {
                      fallbackMinutes = parseInt(event.extendedProps.duracion || event.extendedProps.duracion_minutos || confInterval) || confInterval || fallbackMinutes;
                    }
                  } catch(e) { /* keep fallbackMinutes */ }
                  var tEndDate = event.end || (event.start ? new Date(event.start.getTime() + fallbackMinutes * 60000) : null);
                  var tEnd = horaFin || (tEndDate ? tEndDate.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '');
                } catch(e) { console.warn('short label calc error', e); }
              } catch(e) { console.warn('eventDidMount inner style:', e); }
            }
          } catch(e) { console.warn('eventDidMount sizing:', e); }
          // Asegurar siempre que exista una meta compacta con servicio y tiempo (resiliente a distintos renderizados)
          try {
            var compactAlways = info.el.querySelector('.fc-event-compact-meta');
            if (!compactAlways) {
              compactAlways = document.createElement('div');
              compactAlways.className = 'fc-event-compact-meta';
              info.el.appendChild(compactAlways);
            }
            // Eliminar etiquetas anteriores que podrían duplicar la información
            var oldLabels = info.el.querySelectorAll('.fc-short-event-label, .fc-short-event-time');
            oldLabels.forEach(function(n){ if (n && n.parentNode) n.parentNode.removeChild(n); });
            // Ocultar elementos nativos de FullCalendar para evitar duplicados visuales
            try {
              var natT = info.el.querySelector('.fc-event-time'); if (natT) natT.style.display = 'none';
              var natTitle = info.el.querySelector('.fc-event-title'); if (natTitle) natTitle.style.display = 'none';
            } catch(e) { /* no-op */ }
            compactAlways.style.cssText = 'position:absolute;left:6px;right:6px;top:6px;display:flex;flex-direction:column;gap:2px;pointer-events:none;z-index:20;';
            var svc2 = compactAlways.querySelector('.svc'); if (!svc2) { svc2 = document.createElement('div'); svc2.className = 'svc'; compactAlways.appendChild(svc2); }
            svc2.textContent = servicio || (event.extendedProps && (event.extendedProps.servicio || event.extendedProps.servicio_nombre)) || paciente || event.title || '';
            svc2.style.cssText = 'font-weight:700;color:#fff;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
            var tim2 = compactAlways.querySelector('.tim'); if (!tim2) { tim2 = document.createElement('div'); tim2.className = 'tim'; compactAlways.appendChild(tim2); }
            var sStart = horaInicio || (event.start ? event.start.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '');
            var fallbackMin2 = confInterval || 30;
            var eDate = event.end || (event.start ? new Date(event.start.getTime() + fallbackMin2*60000) : null);
            var sEnd = horaFin || (eDate ? eDate.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '');
            tim2.textContent = sStart + (sEnd ? ' - ' + sEnd : '');
            tim2.style.cssText = 'font-size:12px;color:#d1d5db;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
          } catch(e) { console.warn('ensure compact meta failed', e); }
          // --- FIN LÓGICA CITAS NORMALES ---
        },
        schedulerLicenseKey: 'CC-Attribution-NonCommercial-NoDerivatives',
        initialView: 'resourceTimeGridDay',
        locale: 'es',
        slotDuration: strDuration,
        slotLabelInterval: strDuration,
        timeZone: 'local',
        // Hook para ajustar alturas en cada cambio de rango de fechas o vista
        datesSet: function() {
            if (typeof adjustSlotHeights === 'function') {
                setTimeout(adjustSlotHeights, 250);
            }
        },
        // Load resources via callback to use our debug parser and handle errors
        resources: function(fetchInfo, successCallback, failureCallback) {
          fetchJsonDebug('citas/recursos_json.php')
            .then(function(data) {
              successCallback(data);
            })
            .catch(function(err) {
              console.error('Failed to load resources (recursos_json):', err);
              failureCallback(err);
            });
        },
        events: 'citas/citas_json.php',
        
        // Configuración de recursos para evitar solapamiento de nombres
        resourceAreaWidth: window.innerWidth <= 768 ? '160px' : (window.innerWidth <= 480 ? '120px' : '200px'),
        resourceAreaColumns: [
          {
            field: 'title',
            headerContent: 'Consultorio / Sala',
            width: window.innerWidth <= 768 ? '160px' : (window.innerWidth <= 480 ? '120px' : '200px')
          }
        ],
        resourceLabelContent: function(arg) {
          // Mostrar nombres completos sin abreviaciones
          var title = arg.resource.title;
          
          // Crear contenedor con título e imagen (si existe)
          var img = arg.resource.extendedProps && (arg.resource.extendedProps.imagen || arg.resource.imagen) || '';
          var container = document.createElement('div');
          container.style.cssText = 'text-align:left; padding:4px 6px; box-sizing:border-box;';
          var titleEl = document.createElement('div');
          titleEl.textContent = title;
          titleEl.style.cssText = 'line-height:1.2; font-size:13px; word-break:break-word;';
          container.appendChild(titleEl);
          if (img) {
            var imgEl = document.createElement('img');
            imgEl.src = img;
            imgEl.alt = title;
            imgEl.style.cssText = 'width:40px;height:40px;object-fit:cover;border-radius:6px;display:block;margin-top:6px;';
            imgEl.className = 'fc-resource-img';
            imgEl.onerror = function(){ this.style.display = 'none'; };
            container.appendChild(imgEl);
          }
          return { domNodes: [container] };
        },
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'resourceTimeGridDay,listWeek,rangoTiempo'
        },
        buttonText: {
          today: 'Hoy',
          month: 'Mes',
          week: 'Semana',
          day: 'Día',
          resourceTimeGridDay: 'Día',
          resourceTimeGridWeek: 'Semana'
        },
        // Custom button for Rango de Tiempo (appears in header toolbar)
        customButtons: {
          rangoTiempo: {
            text: 'Rango',
            click: function() {
              var rangoSeleccionado = prompt('Seleccione el rango de tiempo: 15, 30, 60 minutos', '30');
              if (rangoSeleccionado === '15' || rangoSeleccionado === '30' || rangoSeleccionado === '60') {
                var opcion = '00:' + rangoSeleccionado + ':00';
                // Set both the slot duration and the label interval so labels show sub-intervals (e.g. 07:15, 07:45)
                calendar.setOption('slotDuration', opcion);
                calendar.setOption('slotLabelInterval', opcion);
                // Force label format to always show minutes (HH:MM)
                
                adjustSlotHeights();

                calendar.setOption('slotLabelFormat', { hour: '2-digit', minute: '2-digit' });
                alert('Rango de tiempo actualizado a ' + rangoSeleccionado + ' minutos.');
                // If a fallback button exists in resource area, remove it to avoid duplicates
                try { var fb = document.getElementById('btnRangoTiempo'); if (fb) fb.remove(); } catch(e){}
              } else {
                Swal.fire('Error', 'Rango de tiempo no válido.', 'error');
              }
            }
          }
        },
        slotMinTime: "07:00:00",
        slotMaxTime: "23:59:00",
  slotLabelFormat: { hour: '2-digit', minute: '2-digit' },
  allDaySlot: false,
  nowIndicator: true,
  height: 'auto',
        selectable: <?php echo $puede_crear_citas ? 'true' : 'false'; ?>,
  // Al montar la vista, esperamos a que el DOM esté estable antes de calcular
  viewDidMount: function() { setTimeout(adjustSlotHeights, 50); },
  // Importante: Recalcular cuando los eventos terminen de cargarse
  eventSourceSuccess: function() { setTimeout(adjustSlotHeights, 500); },
  select: function(info) {
        if (!<?php echo $puede_crear_citas ? 'true' : 'false'; ?>) return;

    // Usar un timeout para asegurar que el menú contextual aparezca consistentemente,
    // evitando conflictos con otros eventos de clic que podrían cerrarlo prematuramente.
    setTimeout(function() {
      lastSelectionInfo = info;
      
      // Determinar si se han seleccionado múltiples recursos (columnas).
      // Cuando se seleccionan slots en múltiples recursos, `info.resource` es `undefined`.
      const multipleColumnsSelected = !info.resource;
      
      // Habilitar/deshabilitar botones según la selección.
      // Al seleccionar filas (mismo recurso), multipleColumnsSelected es false.
      document.getElementById('ctxAgendarBtn').disabled = multipleColumnsSelected;
      var ctxBloquearBtn = document.getElementById('ctxBloquearBtn');
      if (ctxBloquearBtn) {
          ctxBloquearBtn.disabled = false; // Siempre se puede bloquear cuando el botón existe.
      }
      
      // Posicionar y mostrar el menú.
      if (info.jsEvent) {
        contextMenu.style.left = info.jsEvent.pageX + 'px';
        contextMenu.style.top = info.jsEvent.pageY + 'px';
        contextMenu.style.display = 'block';
      }
    }, 10); // Un pequeño retardo de 10ms es suficiente.
  },
        dateClick: function(info) {
          if (!<?php echo $puede_crear_citas ? 'true' : 'false'; ?>) return;

          // Mejorar detección de eventos con múltiples selectores
          var target = info.jsEvent.target;
          var isEventElement = target.closest('.fc-event') || 
                              target.closest('.fc-timegrid-event') ||
                              target.closest('.fc-event-main') ||
                              target.closest('.fc-event-title') ||
                              target.closest('.fc-event-time') ||
                              target.classList.contains('fc-event') ||
                              target.classList.contains('fc-timegrid-event');
          
          if (!isEventElement) {
            lastDateClickInfo = info;
            contextMenu.style.display = 'block';
            contextMenu.style.left = info.jsEvent.pageX + 'px';
            contextMenu.style.top = info.jsEvent.pageY + 'px';
          }
        },
    eventClick: function(info) {
      if (!<?php echo $puede_editar_citas ? 'true' : 'false'; ?>) return false;

      // Prevenir el comportamiento por defecto del navegador
      info.jsEvent.preventDefault();
      
      // Detener la propagación para evitar que otros listeners se activen
      info.jsEvent.stopPropagation();
      info.jsEvent.stopImmediatePropagation();

      // Llamar a la función que abre el modal de edición
      abrirModalEditarCita(info.event);

      // Devolver false para indicar que hemos manejado el evento
      return false;
    },
  viewDidMount: function() {
          function iniciarObservador(attempt) {
            attempt = attempt || 0;
            // intentos máximos para evitar bucles infinitos
            var MAX_ATTEMPTS = 12;

            // probar varios selectores que podrían contener las columnas/recursos
            var selectors = [
              '.fc-resource-area',
              '.fc-scroller .fc-resource-area',
              '.fc-scroller',
              '.fc-resource',
              '.fc-resource-timegrid',
              '[data-resource-id]'
            ];

            var resourceContainer = null;
            for (var i = 0; i < selectors.length; i++) {
              resourceContainer = document.querySelector(selectors[i]);
              if (resourceContainer) break;
            }

            // Si el botón ya está en el DOM, adjuntar inmediatamente y salir
            var existingBtn = document.getElementById('btnRangoTiempo');
            if (existingBtn) {
              if (!existingBtn.dataset.rangoListener) {
                console.log('Botón btnRangoTiempo encontrado en el DOM (inmediato).');
                existingBtn.addEventListener('click', function() {
                  var rangoSeleccionado = prompt('Seleccione el rango de tiempo: 15, 30, 60 minutos', '30');
                  if (rangoSeleccionado === '15' || rangoSeleccionado === '30' || rangoSeleccionado === '60') {
                    calendar.setOption('slotDuration', '00:' + rangoSeleccionado + ':00');
                            mostrarNotificacion('Rango de tiempo actualizado a ' + rangoSeleccionado + ' minutos.', 'success');
                  } else {
                            mostrarNotificacion('Rango de tiempo no válido.', 'error');
                  }
                });
                existingBtn.dataset.rangoListener = '1';
              }
              return; // ya tenemos lo que necesitamos
            }

            if (resourceContainer) {
              var observer = new MutationObserver(function(mutationsList, observerRef) {
                mutationsList.forEach(function(mutation) {
                  if (mutation.type === 'childList') {
                    var btnRangoTiempo = document.getElementById('btnRangoTiempo');
                    if (btnRangoTiempo) {
                      if (!btnRangoTiempo.dataset.rangoListener) {
                        console.log('Botón btnRangoTiempo encontrado en el DOM.');
                        btnRangoTiempo.addEventListener('click', function() {
                          var rangoSeleccionado = prompt('Seleccione el rango de tiempo: 15, 30, 60 minutos', '30');
                          if (rangoSeleccionado === '15' || rangoSeleccionado === '30' || rangoSeleccionado === '60') {
                            calendar.setOption('slotDuration', '00:' + rangoSeleccionado + ':00');
                            mostrarNotificacion('Rango de tiempo actualizado a ' + rangoSeleccionado + ' minutos.', 'success');
                          } else {
                            mostrarNotificacion('Rango de tiempo no válido.', 'error');
                          }
                        });
                        btnRangoTiempo.dataset.rangoListener = '1';
                      }
                      observerRef.disconnect(); // Dejar de observar una vez que se encuentra el botón
                    }
                  }
                });
              });

              // Iniciar el observador en el contenedor de recursos
              observer.observe(resourceContainer, { childList: true, subtree: true });
            } else {
              attempt++;
              if (attempt <= MAX_ATTEMPTS) {
                console.warn('No se encontró el contenedor de recursos. Reintentando... (intento ' + attempt + '/' + MAX_ATTEMPTS + ')');
                setTimeout(function() { iniciarObservador(attempt); }, 500);
              } else {
                console.error('No se encontró el contenedor de recursos tras ' + MAX_ATTEMPTS + ' intentos. Abortando observador.');
              }
            }
          }

          iniciarObservador(0);
        },
      });
  calendar.render();
  // Ensure slot geometry and event positions are recalculated a few times
  (function ensureSlotLayout(){
    var delays = [80, 220, 700];
    delays.forEach(function(d){
      setTimeout(function(){
        if (typeof adjustSlotHeights === 'function') try { adjustSlotHeights(); } catch(e){}
        if (window.calendar && typeof window.calendar.updateSize === 'function') try { window.calendar.updateSize(); } catch(e){}
      }, d);
    });
  })();
  // Ensure the Rango de Tiempo button exists (fallback if headerContent not rendered)
      // No fallback insertion: use the header toolbar custom button 'rangoTiempo'.
      // Keep the observer logic above to wire any existing #btnRangoTiempo (if present from older code) by attaching listeners.
      // Inyectar estilos para las miniaturas de los recursos
      (function(){
        var style = document.createElement('style');
        style.innerHTML = '.fc .fc-resource .fc-resource-img { width:40px; height:40px; object-fit:cover; border-radius:6px; display:block; margin:0 auto 6px; } .fc .fc-resource { min-height:64px !important; text-align:center; }';
        document.head.appendChild(style);
      })();

      // Inject images for resources after render (fallback)
      function injectResourceImagesIndex(){
        try{ var resources = calendar.getResources(); } catch(e){ resources = []; }
        resources.forEach(function(res){
          var img = (res.extendedProps && (res.extendedProps.imagen || res.imagen)) || '';
          if (!img) return;
          var id = res.id;
          var el = document.querySelector('[data-resource-id="'+id+'"]');
          if (!el) el = document.querySelector('.fc-col-header-cell[data-resource-id="'+id+'"]');
          if (!el) return;
          if (el.querySelector('.fc-resource-img')) return;
          var imgEl = document.createElement('img'); imgEl.src = img; imgEl.className = 'fc-resource-img'; imgEl.onerror = function(){ this.style.display='none'; };
          el.appendChild(imgEl);
        });
      }
      setTimeout(injectResourceImagesIndex, 300);
      try{ new MutationObserver(function(){ setTimeout(injectResourceImagesIndex,150); }).observe(document.getElementById('calendar'), { childList:true, subtree:true }); } catch(e){}
      
      // Actualizar marcadores cuando se carguen los eventos
      setTimeout(function() {
        actualizarMarcadoresMiniCalendarios();
      }, 1000);

      // Ensure the calendar occupies the full calendar-area width and remove any
      // previously injected right column / resizer (#calendarSidebar and .resizer).
      (function(){
        var calRoot = document.getElementById('calendar');
        if (!calRoot) return;

        // Remove injected calendarSidebar if present
        var injectedSidebar = document.getElementById('calendarSidebar');
        if (injectedSidebar && injectedSidebar.parentNode) {
          injectedSidebar.parentNode.removeChild(injectedSidebar);
        }

        // Remove any injected resizer elements
        var injectedResizers = document.querySelectorAll('.calendar-layout .resizer, .resizer');
        injectedResizers.forEach(function(r){ if (r && r.parentNode) r.parentNode.removeChild(r); });

        // If the calendar was wrapped inside a .calendar-main within a .calendar-layout,
        // unwrap it so it returns to its original container (.calendar-area)
        var parent = calRoot.parentNode;
        if (parent && parent.classList && parent.classList.contains('calendar-main')) {
          var layout = parent.parentNode;
          if (layout && layout.classList && layout.classList.contains('calendar-layout')) {
            var container = layout.parentNode;
            if (container) {
              // Replace the layout with the calendar element
              container.replaceChild(calRoot, layout);
            }
          }
        }
        // Finally ensure the calendar's immediate parent has the expected class
        var finalParent = calRoot.parentNode;
        if (finalParent && finalParent.classList && !finalParent.classList.contains('calendar-area')) {
          // Try to find the .calendar-area and move the calendar there
          var desired = document.querySelector('.calendar-area');
          if (desired && desired !== finalParent) {
            desired.appendChild(calRoot);
          }
        }
      })();

      // Create small floating tooltip for time labels on hover
      (function(){
        var tip = document.createElement('div');
        tip.className = 'fc-timecell-tooltip';
        tip.id = 'fc-timecell-tooltip';
        document.body.appendChild(tip);

        function formatTimeFromLabel(labelEl) {
          if (!labelEl) return '';
          var text = labelEl.textContent || labelEl.innerText || '';
          return text.trim();
        }

        function attachSlotListeners(root) {
          if (!root) return;
          var slots = root.querySelectorAll('.fc-timegrid-slot, .fc-timegrid-slot-lane');
          slots.forEach(function(slot){
            if (slot.dataset._hasHover) return;
            slot.dataset._hasHover = '1';
            slot.addEventListener('mouseenter', function(e){
              // Try to derive time from the axis label in the same row
              var row = slot.closest('.fc-timegrid-slot');
              var label = null;
              // look for preceding axis label
              var axis = document.querySelector('.fc-timegrid-axis');
              if (axis) {
                // find label nearest by y position
                var labels = axis.querySelectorAll('.fc-timegrid-slot-label');
                for (var i=0;i<labels.length;i++){
                  var r = labels[i].getBoundingClientRect();
                  var s = slot.getBoundingClientRect();
                  if (Math.abs(r.top - s.top) < 6) { label = labels[i]; break; }
                }
              }
              var txt = formatTimeFromLabel(label) || slot.getAttribute('data-time') || '';
              var tipEl = document.getElementById('fc-timecell-tooltip');
              if (txt) {
                tipEl.textContent = txt;
                tipEl.style.display = 'block';
                tipEl.style.left = (e.clientX + 12) + 'px';
                tipEl.style.top = (e.clientY + 12) + 'px';
              }
            });
            slot.addEventListener('mousemove', function(e){
              var tipEl = document.getElementById('fc-timecell-tooltip');
              if (tipEl && tipEl.style.display === 'block') {
                tipEl.style.left = (e.clientX + 12) + 'px';
                tipEl.style.top = (e.clientY + 12) + 'px';
              }
            });
            slot.addEventListener('mouseleave', function(){
              var tipEl = document.getElementById('fc-timecell-tooltip');
              if (tipEl) tipEl.style.display = 'none';
            });
          });
        }

        // Observe calendar for slot cells creation
        try {
          var mo = new MutationObserver(function(muts){
            muts.forEach(function(m){
              if (m.addedNodes && m.addedNodes.length) {
                m.addedNodes.forEach(function(n){
                  if (!(n instanceof HTMLElement)) return;
                  if (n.matches && (n.matches('.fc-timegrid-slot') || n.matches('.fc-timegrid-slot-lane') || n.querySelector('.fc-timegrid-slot'))) {
                    // attach in a tick
                    setTimeout(function(){ attachSlotListeners(document.getElementById('calendar')); }, 50);
                  }
                });
              }
            });
          });
          mo.observe(document.getElementById('calendar'), { childList:true, subtree:true });
          // initial attach
          setTimeout(function(){ attachSlotListeners(document.getElementById('calendar')); }, 200);
        } catch(e) { console.warn('slot observer failed', e); }
      })();
      
      // Configuración de botones adicionales
      var btnVistaLista = document.getElementById('btnVistaLista');
      if (btnVistaLista) {
          btnVistaLista.addEventListener('click', function() {
              calendar.changeView('listWeek');
          });
      }
      // Función para ajustar la altura de los slots según el intervalo y el espacio disponible
      function adjustSlotHeights() {
        try {
          if (!window.calendar || typeof window.calendar.getOption !== 'function') return;
          
          // Obtenemos la duración del slot actual (ej: 00:30:00 o 01:00:00)
          var slotDur = window.calendar.getOption('slotDuration') || '00:30:00';
          var parts = slotDur.split(':');
          var mins = (parseInt(parts[0],10) || 0) * 60 + (parseInt(parts[1],10) || 0);
          if (!mins || mins <= 0) mins = 30;

          // ESTÁNDAR FIJO: 30 minutos de tiempo equivalen a 50px de altura visual.
          // Esto garantiza que si el rango es de 60 min, la celda mida 100px.
          // Así, una cita de 30 min siempre medirá 50px y será perfectamente legible.
          var PixelsPerMinute = 50 / 30; 
          var slotHeight = Math.round(mins * PixelsPerMinute);

          // Aplicamos la variable CSS
          document.documentElement.style.setProperty('--fc-slot-height', slotHeight + 'px');
          
          // Forzamos a FullCalendar a reposicionar eventos con la nueva geometría
          window.calendar.updateSize();
          setTimeout(function() {
             if (window.calendar) {
                 window.calendar.updateSize();
              }
          }, 150);

        } catch (e) {
          console.warn('adjustSlotHeights error', e);
        }
      }

      // Debounced resize handler
      var _adjustSlotHeightsTimeout = null;
      window.addEventListener('resize', function() {
        if (_adjustSlotHeightsTimeout) clearTimeout(_adjustSlotHeightsTimeout);
        _adjustSlotHeightsTimeout = setTimeout(function(){ if (typeof adjustSlotHeights === 'function') adjustSlotHeights(); }, 180);
      });

      // Asegurar que al cargar la ventana completa se dispare el ajuste
      window.onload = function() {
          setTimeout(function() {
              if (typeof adjustSlotHeights === 'function') adjustSlotHeights();
          }, 500);
      };

      // Función para actualizar la línea de tiempo actual
      function actualizarLineaTiempo() {
        // Forzar actualización del indicador de tiempo actual
        if (calendar.view.type.includes('timeGrid')) {
          calendar.updateSize(); // updateSize es suficiente y no rompe la alineación de las citas
        }
      }
      
      // Actualizar la línea de tiempo cada minuto
      setInterval(actualizarLineaTiempo, 60000); // 60000 ms = 1 minuto
      
      // También actualizar cuando cambie la vista o se refresque el calendario
      calendar.on('viewDidMount', function() {
        setTimeout(actualizarLineaTiempo, 100);
      });
          // Ensure slot heights are correct after view mounts
          if (typeof adjustSlotHeights === 'function') {
              // small timeout to let layout settle
              setTimeout(adjustSlotHeights, 120);
          }

      document.getElementById('profesional-select').addEventListener('change', function() {
        calendar.refetchEvents();
        setTimeout(actualizarMarcadoresMiniCalendarios, 500);
      });
      document.getElementById('estado-select').addEventListener('change', function() {
        var estadoId = this.value;
        if (estadoId === 'todos') {
          calendar.setOption('events', 'citas/citas_json.php');
        } else {
          calendar.setOption('events', function(fetchInfo, successCallback, failureCallback) {
            fetch('citas/citas_json.php')
              .then(r => r.json())
              .then(data => {
                var filtrados = data.filter(ev => {
                  // Filtrar por estado_id si existe, si no por estado (nombre)
                  if (typeof ev.estado_id !== 'undefined') {
                    return String(ev.estado_id) === String(estadoId);
                  } else if (typeof ev.extendedProps !== 'undefined' && typeof ev.extendedProps.estado_id !== 'undefined') {
                    return String(ev.extendedProps.estado_id) === String(estadoId);
                  }
                  return false;
                });
                successCallback(filtrados);
              })
              .catch(failureCallback);
          });
        }
      });

      document.getElementById('cerrarModalAgendar').onclick = function() {
        document.getElementById('modalAgendar').style.display = 'none';
      };

      // Evento para actualizar hora fin automáticamente cuando cambie hora inicio
      document.getElementById('agendarHoraInicio').onchange = function() {
        var tiempoManual = document.getElementById('tiempoManual');
        if (!tiempoManual.checked) {
          manejarCambioServicio(); // Recalcular con la nueva hora
        }
      };

      // Evento para el checkbox de tiempo manual
      document.getElementById('tiempoManual').onchange = function() {
        var horaFinInput = document.getElementById('agendarHoraFin');
        if (this.checked) {
          // Habilitar edición manual
          horaFinInput.readOnly = false;
          horaFinInput.style.backgroundColor = '#000';
          horaFinInput.style.color = '#e5e7eb';
        } else {
          // Desactivar edición manual y recalcular
          horaFinInput.readOnly = true;
          horaFinInput.style.backgroundColor = '#000';
          horaFinInput.style.color = '#e5e7eb';
          manejarCambioServicio(); // Recalcular automáticamente
        }
      };

      
    });
    
    // Funciones auxiliares para el modal de agendar
    function poblarSelectHora(selectId) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        select.innerHTML = '<option value="">Seleccionar hora</option>';
        
        for (let hour = 0; hour < 24; hour++) {
            for (let minute = 0; minute < 60; minute += 30) {
                const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                const option = document.createElement('option');
                option.value = timeString;
                option.textContent = timeString;
                select.appendChild(option);
            }
        }
    }

    // Función para poblar datalists de horas (usada en el modal de edición)
    function poblarDatalistHoras(listId) {
        const datalist = document.getElementById(listId);
        if (!datalist) return;
        datalist.innerHTML = ''; // Limpiar opciones existentes
        for (let hour = 0; hour < 24; hour++) { // Iniciar desde las 00:00
            for (let minute = 0; minute < 60; minute += 30) {
                const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                const option = document.createElement('option');
                option.value = timeString;
                option.label = timeString;
                option.textContent = timeString; // Asegura visibilidad en todos los navegadores
                datalist.appendChild(option);
            }
        }
    }

    // Función auxiliar para activar/desactivar la edición manual de tiempo
    function toggleEditarTiempoManual(horaInicioId, horaFinId, isManual) {
        const horaInicioInput = document.getElementById(horaInicioId);
        const horaFinInput = document.getElementById(horaFinId);
        if (!horaFinInput) return;

        if (isManual) {
            horaFinInput.readOnly = false;
            horaFinInput.style.backgroundColor = '#111';
            horaFinInput.style.color = '#fff';
        } else {
            horaFinInput.readOnly = true;
            horaFinInput.style.backgroundColor = '#000';
            horaFinInput.style.color = '#e5e7eb';
            if (horaInicioId === 'agendarHoraInicio') manejarCambioServicio();
        }
    }

    function inicializarFlatpickrFecha(inputId) {
        const fechaInput = document.getElementById(inputId);
        if (fechaInput && !fechaInput._flatpickr) {
            flatpickr(fechaInput, {
                dateFormat: "Y-m-d",
                minDate: inputId === 'agendarFecha' ? "today" : null,
                locale: "es",
                disableMobile: true
            });
        }
    }
     // Configurar comportamiento de un clic (manual) y doble clic (lista)
    
    // Configurar comportamiento: 1 click -> lista 30min partiendo del actual, Doble click -> edición manual
    function configurarInputsTiempoDobleClic() {
        document.querySelectorAll('.time-input-manual').forEach(input => {
            const listId = input.dataset.list;
            let lastValue = input.value;

            // Al presionar (mousedown), preparamos la lista "limpia"
            input.addEventListener('mousedown', function() {
                if (this.getAttribute('list')) {
                    lastValue = this.value;
                    // Repoblar la lista para que empiece desde la hora actual del campo
                    poblarDatalistHorasDinamico(listId, lastValue);
                    this.value = ''; // Vaciamos para evitar el filtro del navegador
                }
            });

            // Al ganar el foco (tras mousedown o tab)
            input.addEventListener('focus', function() {
                if (!this.getAttribute('list')) {
                    this.setAttribute('list', listId);
                }
            });

            // Si se pierde el foco sin seleccionar nada, restauramos la hora
            input.addEventListener('blur', function() {
                setTimeout(() => {
                    if (this.value === '' && lastValue) {
                        this.value = lastValue;
                    }
                }, 100);
            });

            // Al hacer doble click, quitamos la lista para permitir escritura manual pura
            input.addEventListener('dblclick', function() {
                this.removeAttribute('list');
                if (this.value === '' && lastValue) {
                    this.value = lastValue;
                }
                this.focus();
            });
            
            // Mantener registro de la última hora válida seleccionada o escrita
            input.addEventListener('change', function() {
                if (this.value !== '') lastValue = this.value;
            });
        });
    }

    // Función para poblar la lista de horas partiendo de una específica
    function poblarDatalistHorasDinamico(listId, horaInicioStr) {
        const datalist = document.getElementById(listId);
        if (!datalist) return;
        
        let startHour = 0;
        if (horaInicioStr && horaInicioStr.includes(':')) {
            startHour = parseInt(horaInicioStr.split(':')[0]) || 0;
        }

        datalist.innerHTML = '';
        for (let hour = startHour; hour < 24; hour++) {
            for (let minute = 0; minute < 60; minute += 30) {
                const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                const option = document.createElement('option');
                option.value = timeString;
                option.textContent = timeString;
                datalist.appendChild(option);
            }
        }
    }
    function cargarDoctoresDisponibles() {
        const fechaInput = document.getElementById('agendarFecha');
        const horaInicioInput = document.getElementById('agendarHoraInicio');
        const horaFinInput = document.getElementById('agendarHoraFin');
        const doctorSelect = document.getElementById('agendarDoctor');
        
        if (!fechaInput || !horaInicioInput || !horaFinInput || !doctorSelect) return;
        
        const fecha = fechaInput.value;
        const horaInicio = horaInicioInput.value;
        const horaFin = horaFinInput.value;
        
        if (!fecha || !horaInicio || !horaFin) return;
        
        // Hacer petición AJAX para obtener doctores disponibles
        fetch(`citas/lista_doctores_json.php?fecha=${fecha}&hora_inicio=${horaInicio}&hora_fin=${horaFin}`)
            .then(response => response.json())
            .then(data => {
                // Limpiar opciones existentes
                doctorSelect.innerHTML = '<option value="">Seleccionar Doctor</option>';
                
                // Agregar nuevas opciones
                data.forEach(doctor => {
                    const option = document.createElement('option');
                    option.value = doctor.id;
                    option.textContent = doctor.nombre;
                    doctorSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error cargando doctores:', error);
                mostrarNotificacion('Error al cargar doctores disponibles', 'error');
            });
    }
    
    // La función abrirModalAgendar global que será llamada por el botón "Nueva Cita"
    // y por el evento 'select' de FullCalendar
    function abrirModalAgendar(info) {
    var modal = document.getElementById('modalAgendar'); // Asegúrate de que modal esté definido
    var registroBox = document.getElementById('registroPacienteBox');
    var fechaInput = document.getElementById('agendarFecha');
    var horaInicioInput = document.getElementById('agendarHoraInicio');
    var horaFinInput = document.getElementById('agendarHoraFin');
    var agendarModalidad = document.getElementById('agendarModalidad');

    // Reset patient selection / registro box
    if (pacienteInput) {
      pacienteInput.value = '';
      pacienteInput.removeAttribute('data-paciente-id');
    }
    if (registroBox) registroBox.style.display = 'none';

    // Determine default date/time: prefer lastDateClickInfo (set by calendar), otherwise now rounded to 30m
    var fecha = '';
    var horaInicio = ''; // Asegúrate de que pacienteInput esté definido
    var horaFin = '';
    var now = new Date();
    var isRangeSelection = false;

    if (info && info.start && info.end) {
        fecha = info.start.toISOString().split('T')[0];
        horaInicio = info.start.toTimeString().substring(0, 5);
        horaFin = info.end.toTimeString().substring(0, 5);
        isRangeSelection = true;
    } else if (info && info.date) {
        fecha = info.date.toISOString().split('T')[0];
        horaInicio = info.date.toTimeString().substring(0, 5);
        var dateObj = new Date(info.date);
        dateObj.setMinutes(dateObj.getMinutes() + 30);
        horaFin = dateObj.toTimeString().substring(0, 5);
    } else {
        fecha = now.toISOString().split('T')[0];
        horaInicio = now.toTimeString().substring(0, 5);
        var dateObj = new Date(now);
        dateObj.setMinutes(dateObj.getMinutes() + 30);
        horaFin = dateObj.toTimeString().substring(0, 5);
    }

        // Poblar datalists de horas
        poblarDatalistHoras('agendarHorasList');
        poblarDatalistHoras('editarHorasList'); // También para el modal de edición

    // Inicializar Flatpickr para agendarFecha
    const agendarFechaInput = document.getElementById('agendarFecha');
    if (agendarFechaInput && !agendarFechaInput._flatpickr) {
        flatpickr(agendarFechaInput, {
            dateFormat: "Y-m-d",
            locale: "es",
            disableMobile: true
        });
    }
    // Set date value and update Flatpickr's internal state
    if (agendarFechaInput) {
        agendarFechaInput.value = fecha;
        if (agendarFechaInput._flatpickr) {
            agendarFechaInput._flatpickr.setDate(fecha, false); // Do not open automatically
        }
    }

    // Resetear el checkbox de "Editar manual" y aplicar su lógica
    const agendarTiempoManualCheckbox = document.getElementById('agendarTiempoManual');
    if (agendarTiempoManualCheckbox) agendarTiempoManualCheckbox.checked = false;
    toggleEditarTiempoManual('agendarHoraInicio', 'agendarHoraFin', false);

    if (fechaInput) fechaInput.value = fecha;
    if (horaInicioInput) horaInicioInput.value = horaInicio;
    if (horaFinInput) horaFinInput.value = horaFin;

    // Configurar tiempo manual si es selección de rango
    var tiempoManualCheckbox = document.getElementById('tiempoManual');
    if (tiempoManualCheckbox) {
        if (isRangeSelection) {
            tiempoManualCheckbox.checked = true;
            horaFinInput.readOnly = false;
            horaFinInput.style.backgroundColor = '#000';
            horaFinInput.style.color = '#e5e7eb';
        } else {
            tiempoManualCheckbox.checked = false;
            horaFinInput.readOnly = true;
            horaFinInput.style.backgroundColor = '#000';
            horaFinInput.style.color = '#e5e7eb';
        }
    }

    // Cargar modalidades en el select del modal
    if (agendarModalidad) {
        agendarModalidad.innerHTML = '<option value="">Cargando...</option>';
        
        // 1. Cargar lista completa de doctores para el selector de profesional
        var fechaParam = fecha || (fechaInput ? fechaInput.value : '');
        var horaInicioParam = horaInicio || (horaInicioInput ? horaInicioInput.value : '');
        var horaFinParam = horaFin || (horaFinInput ? horaFinInput.value : '');
        var queryParams = [];
        if (fechaParam) queryParams.push('fecha=' + encodeURIComponent(fechaParam));
        if (horaInicioParam) queryParams.push('hora_inicio=' + encodeURIComponent(horaInicioParam));
        if (horaFinParam) queryParams.push('hora_fin=' + encodeURIComponent(horaFinParam));
        var fetchUrl = 'citas/lista_doctores_json.php' + (queryParams.length ? ('?' + queryParams.join('&')) : '');

        fetchJsonDebug(fetchUrl)
            .then(function(doctores) {
                agendarModalidad.innerHTML = '<option value="">Seleccione doctor...</option>';
                doctores.forEach(function(dr) {
                    var opt = document.createElement('option');
                    opt.value = dr.id;
                    opt.textContent = dr.nombre;
                    agendarModalidad.appendChild(opt);
                });

                // 2. Si se hizo click en una columna (Consultorio), obtener sus datos
                if (info && info.resource) {
                    fetchJsonDebug('citas/recursos_json.php')
                        .then(function(recursos) {
                            const recurso = recursos.find(r => r.id == info.resource.id);
                            if (recurso) {
                                // Guardar ID del consultorio en el campo oculto
                                document.getElementById('realModalidadId').value = recurso.id;
                                
                                // Auto-seleccionar al doctor responsable del consultorio si existe
                                if (recurso.usuario_id) {
                                    agendarModalidad.value = recurso.usuario_id;
                                }
                                
                                // Cargar tratamientos basados en consultorio y médico
                                cargarServiciosPorModalidad(recurso.id, agendarModalidad.value);
                            }
                        });
                } else {
                    document.getElementById('agendarServicio').innerHTML = '<option value="">Primero seleccione doctor...</option>';
                }
            });
            
        agendarModalidad.onchange = function() {
            const doctorId = this.value;
            const modalityId = document.getElementById('realModalidadId').value;
            // Al cambiar el médico, refrescar los tratamientos permitidos
            cargarServiciosPorModalidad(modalityId, doctorId);
        };
    }

    // Show modal and focus patient input
    if (modal) {
      // Inicializar componentes del modal antes de mostrarlo
      inicializarFlatpickrFecha('agendarFecha');
      poblarDatalistHoras('agendarHorasList');
      
      // Establecer valores por defecto si no están establecidos
      if (fechaInput && !fechaInput.value) {
        const today = new Date().toISOString().split('T')[0];
        fechaInput.value = today;
      }
      
      // Seleccionar automáticamente las horas correctas en los selects si vienen del calendario
      if (horaInicio && horaInicioInput) {
        horaInicioInput.value = horaInicio;
      }
      if (horaFin && horaFinInput) {
        horaFinInput.value = horaFin;
      }
      
      // Cargar doctores disponibles después de asignar los valores
      cargarDoctoresDisponibles();
      
      // Calcular hora fin automáticamente cuando cambia hora inicio (reutilizando manejarCambioServicio)
      if (horaInicioInput) {
        horaInicioInput.addEventListener('change', function() {
          manejarCambioServicio();
          cargarDoctoresDisponibles();
        });
      }
      
      // Recargar doctores cuando cambian fecha o hora fin
      if (fechaInput) {
        fechaInput.addEventListener('change', cargarDoctoresDisponibles);
      }
      if (horaFinInput) {
        horaFinInput.addEventListener('change', cargarDoctoresDisponibles);
      }
      
      modal.style.display = 'flex';
      setTimeout(function() {
        if (pacienteInput) pacienteInput.focus();
      }, 200); // Asegúrate de que pacienteInput esté definido
    } else {
        mostrarNotificacion('No se encontró el modal de agendar (modalAgendar) en la página.', 'error');
    }
  }

  // Helper function to toggle manual time editing for both modals
  function toggleEditarTiempoManual(horaInicioId, horaFinId, isManual) {
      const horaInicioInput = document.getElementById(horaInicioId);
      const horaFinInput = document.getElementById(horaFinId);

      if (isManual) {
          horaFinInput.readOnly = false;
          horaFinInput.style.backgroundColor = '#000';
          horaFinInput.style.color = '#e5e7eb';
          // Optionally, make horaInicio editable too if needed for full manual control
          // horaInicioInput.readOnly = false;
      } else {
          horaFinInput.readOnly = true;
          horaFinInput.style.backgroundColor = '#000';
          horaFinInput.style.color = '#e5e7eb';
          // Recalculate horaFin based on horaInicio and service duration if applicable
          if (horaInicioId === 'agendarHoraInicio') manejarCambioServicio(); // Only for agendar modal
      }
  }

  // Event listener para el checkbox "Editar manual" en el modal de agendar
  document.getElementById('tiempoManual').addEventListener('change', function() {
      toggleEditarTiempoManual('agendarHoraInicio', 'agendarHoraFin', this.checked);
  });
    
    // Funciones para sidebar responsivo
    function toggleSidebar() {
      var sidebar = document.querySelector('.sidebar');
      var overlay = document.querySelector('.sidebar-overlay');
      
      sidebar.classList.toggle('active');
      overlay.classList.toggle('active');
    }
    
    function closeSidebar() {
      var sidebar = document.querySelector('.sidebar');
      var overlay = document.querySelector('.sidebar-overlay');
      
      sidebar.classList.remove('active');
      overlay.classList.remove('active');
    }
    
    // Cerrar sidebar al hacer clic en enlaces (móvil)
    document.addEventListener('DOMContentLoaded', function() {
      cargarTiposDePaciente();
      var sidebarLinks = document.querySelectorAll('.sidebar a, .sidebar button');
      sidebarLinks.forEach(function(link) {
        link.addEventListener('click', function() {
          if (window.innerWidth <= 768) {
            setTimeout(closeSidebar, 300);
          }
        });
      });
      
      // Cerrar sidebar al redimensionar ventana
      window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
          closeSidebar();
        }
      });
    });
    
    
    // Funciones para editar citas
    function abrirModalEditarCita(event) {
        // Extraer información del evento
        var paciente = event.extendedProps.paciente_full || event.extendedProps.paciente_nombre_text || event.title.split(' (')[0];
        var servicio = event.extendedProps.servicio || event.extendedProps.servicio_text || event.title.split('(')[1]?.replace(')', '') || '';
        var fecha = event.start.toISOString().split('T')[0];
        var horaInicio = event.start.toTimeString().substring(0, 5);
        var horaFin = event.end ? event.end.toTimeString().substring(0, 5) : '';

        // Llenar el modal con la información básica
        document.getElementById('editarPacienteNombre').textContent = paciente;
        document.getElementById('editarServicioNombre').textContent = servicio;
        document.getElementById('editarCitaId').value = event.id;

        // Inicializar componentes del modal (calendario y datalist)
        inicializarFlatpickrFecha('editarFecha');
        poblarDatalistHoras('editarHorasList');

        // Establecer fecha sin abrir el calendario automáticamente
        const editarFechaInput = document.getElementById('editarFecha');
        if (editarFechaInput) {
            editarFechaInput.value = fecha;
            if (editarFechaInput._flatpickr) {
                editarFechaInput._flatpickr.setDate(fecha, false);
            }
        }

        // Establecer horas
        document.getElementById('editarHoraInicio').value = horaInicio;
        document.getElementById('editarHoraFin').value = horaFin;

      // Control de solo lectura si no tiene permisos de edición
      const puedeEditar = <?php echo $puede_editar_citas ? 'true' : 'false'; ?>;
      if (editarFechaInput && editarFechaInput._flatpickr) {
          if (!puedeEditar) {
              editarFechaInput._flatpickr.set('clickOpens', false);
          } else {
              editarFechaInput._flatpickr.set('clickOpens', true);
          }
          editarFechaInput.readOnly = !puedeEditar; // También el input directo
      }
      document.getElementById('editarHoraInicio').readOnly = !puedeEditar;
      document.getElementById('editarHoraFin').readOnly = !puedeEditar;
      document.getElementById('editarEstado').disabled = !puedeEditar;

      // Lógica para el checkbox "Editar manual" en el modal de edición
      const editarTiempoManualCheckbox = document.getElementById('editarTiempoManual');
      if (editarTiempoManualCheckbox) {
          // Marcamos manual por defecto al editar para permitir cambios inmediatos
          editarTiempoManualCheckbox.checked = true;
          toggleEditarTiempoManual('editarHoraInicio', 'editarHoraFin', editarTiempoManualCheckbox.checked);
          editarTiempoManualCheckbox.disabled = !puedeEditar; // El checkbox también se deshabilita si no hay permisos
      }

      // Seleccionar el estado correcto
      var estadoSelect = document.getElementById('editarEstado');
      // Intentar usar el ID directo si está en extendedProps
      if (event.extendedProps.estado_id) {
          estadoSelect.value = event.extendedProps.estado_id;
      } else {
          // Fallback: tratar de coincidir por texto (menos preciso)
          // Esto es útil si el JSON de eventos no trae estado_id explícito
          for (var i = 0; i < estadoSelect.options.length; i++) {
              if (estadoSelect.options[i].text.toLowerCase() === estado.toLowerCase()) {
                  estadoSelect.selectedIndex = i;
                  break;
              }
          }
      }
      
      // Mostrar el modal
      document.getElementById('modalEditarCita').style.display = 'flex';
    }

    // Event listener para el checkbox "Editar manual" en el modal de edición
    document.getElementById('editarTiempoManual').addEventListener('change', function() {
        toggleEditarTiempoManual('editarHoraInicio', 'editarHoraFin', this.checked);
    });
    
    // Event listener para cerrar modal de editar
    document.getElementById('cerrarModalEditarCita').onclick = function() {
      document.getElementById('modalEditarCita').style.display = 'none';
    };
    
    // Event listener para el formulario de editar cita
    document.getElementById('formEditarCita').onsubmit = function(e) {
      e.preventDefault();
      
      var citaId = document.getElementById('editarCitaId').value;
      var fecha = document.getElementById('editarFecha').value;
      var horaInicio = document.getElementById('editarHoraInicio').value;
      var horaFin = document.getElementById('editarHoraFin').value;
      var estadoId = document.getElementById('editarEstado').value;
      
      var formData = new FormData();
      formData.append('cita_id', citaId);
      formData.append('fecha', fecha);
      formData.append('hora_inicio', horaInicio);
      formData.append('hora_fin', horaFin);
      formData.append('estado_id', estadoId);
      
      fetch('actualizar_cita.php', {
        method: 'POST',
        body: formData
      })
      .then(r => r.json())
      .then(resp => {
        if (resp.success) {
          mostrarNotificacion('Cita actualizada correctamente.', 'success');
          document.getElementById('modalEditarCita').style.display = 'none';
          
          // Recargar eventos del calendario
          if (calendar && typeof calendar.refetchEvents === 'function') {
            calendar.refetchEvents();
          } else {
            // Fallback: recargar la página
            location.reload();
          }
        } else {
          mostrarNotificacion('Error al actualizar cita: ' + (resp.error || ''), 'error');
        }
      })
      .catch(err => {
        console.error('Error:', err);
        mostrarNotificacion('Error de conexión al actualizar la cita', 'error');
      });
    };
    
    // Event listener para eliminar cita
    document.getElementById('eliminarCitaBtn').onclick = function() {
      Swal.fire({
        title: '¿Está seguro?',
        text: "No podrás revertir esto.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
            var citaId = document.getElementById('editarCitaId').value;

            // Cambiamos a método GET para máxima compatibilidad con servidores
            fetch(`eliminar_cita.php?cita_id=${citaId}`, {
              method: 'GET'
            })
            .then(r => r.json())
            .then(resp => {          
              if (resp.success) {
                mostrarNotificacion('Cita eliminada correctamente.', 'success');
                document.getElementById('modalEditarCita').style.display = 'none';
                calendar.refetchEvents();
              } else {
                mostrarNotificacion('Error al eliminar cita: ' + (resp.error || ''), 'error');
              }
            })
            .catch(err => {
              console.error('Error:', err);
              mostrarNotificacion('Error de conexión al eliminar la cita', 'error');
            });
        }
      });
    };
    
    function abrirCatalogo() {
        window.location.href = 'catalogo_servicios.php';
    }
  </script>

  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Flatpickr -->
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

  <script>
    // --- FUNCIONES GLOBALES PARA AGENDADO ---
    
    // Función para calcular hora fin basada en hora inicio y duración
    function calcularHoraFin(horaInicio, duracionMinutos) {
      if (!horaInicio) return '';
      var [horas, minutos] = horaInicio.split(':').map(Number);
      var totalMinutos = horas * 60 + minutos + duracionMinutos;
      var nuevasHoras = Math.floor(totalMinutos / 60);
      var nuevosMinutos = totalMinutos % 60;
      return String(nuevasHoras).padStart(2, '0') + ':' + String(nuevosMinutos).padStart(2, '0');
    }

    // Manejar el cambio de servicio: actualiza duración y carga notas
    function manejarCambioServicio() {
      var servicioSelect = document.getElementById('agendarServicio');
      var selectedOption = servicioSelect.options[servicioSelect.selectedIndex];
      if (!selectedOption || !selectedOption.value) return;

      var duracion = selectedOption.getAttribute('data-duracion');
      var tiempoManual = document.getElementById('tiempoManual');
      var servicioId = selectedOption.value;

      // Cargar notas desde el servidor
      var notaPacienteTextarea = document.getElementById('notaPaciente');
      if (servicioId && notaPacienteTextarea) {
        fetch(`citas/servicio_notas.php?id=${servicioId}`)
          .then(r => r.json())
          .then(data => { notaPacienteTextarea.value = data.notas || ''; })
          .catch(e => console.error("Error al cargar notas:", e));
      }
      
      if (duracion && (!tiempoManual || !tiempoManual.checked)) {
        var horaInicioInput = document.getElementById('agendarHoraInicio');
        if (horaInicioInput.value) {
          document.getElementById('agendarHoraFin').value = calcularHoraFin(horaInicioInput.value, parseInt(duracion));
        }
        var duracionInfo = document.getElementById('duracionInfo');
        if (duracionInfo) {
          document.getElementById('duracionTexto').textContent = `Duración estimada: ${duracion} minutos`;
          duracionInfo.style.display = 'block';
        }
      } else if (!duracion && document.getElementById('duracionInfo')) {
        document.getElementById('duracionInfo').style.display = 'none';
      }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Evento para toggle de información adicional
        document.getElementById('btnToggleInfoAdicional').onclick = function() {
          var infoBox = document.getElementById('infoAdicionalBox');
          var icon = document.getElementById('iconInfoAdicional');
          if (infoBox.style.display === 'none' || infoBox.style.display === '') {
            infoBox.style.display = 'block';
            icon.style.transform = 'rotate(180deg)';
            icon.className = 'fas fa-chevron-up';
          } else {
            infoBox.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
            icon.className = 'fas fa-chevron-down';
          }
        };

        // Manejador de envío del formulario de agendar (Limpio y robusto)
        document.getElementById('formAgendar').onsubmit = function(e) {
          e.preventDefault();
          const submitBtn = document.getElementById('btnGuardarCita');
          if (!submitBtn) return;
          const originalBtnContent = submitBtn.innerHTML;
          
          submitBtn.disabled = true;
          submitBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> <span>Guardando...</span>`;

          var pId = pacienteInput.dataset.pacienteId || '';
          if (!pId) {
            mostrarNotificacion('Error: Seleccione un paciente.', 'warning');
            submitBtn.disabled = false; submitBtn.innerHTML = originalBtnContent;
            return;
          }

          var sSel = document.getElementById('agendarServicio');
          var drSelect = document.getElementById('agendarModalidad');
          var drId = drSelect.value;
          var modId = document.getElementById('realModalidadId').value;

          const citaData = {
              fecha: document.getElementById('agendarFecha').value,
              hora_inicio: document.getElementById('agendarHoraInicio').value,
              hora_fin: document.getElementById('agendarHoraFin').value,
              paciente_id: pId,
              profesional_id: drId,
              servicio_id: sSel.value,
              modalidad_id: modId,
              estado_id: document.getElementById('agendarEstado').value,
              tipo: 'normal',
              nota_interna: document.getElementById('notaInterna').value,
              nota_paciente: document.getElementById('notaPaciente').value,
              atencion_especial: document.getElementById('agendarAtencionEspecial')?.checked ? 1 : 0
          };

          fetch('guardar_cita.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(citaData)
          })
          .then(response => {
              const clone = response.clone();
              return response.json().catch(() => clone.text().then(t => { throw new Error(t); }));
          })
          .then(data => {
              if (data.success) {
                  mostrarNotificacion('Cita agendada correctamente.', 'success');
                  document.getElementById('modalAgendar').style.display = 'none';
                  if (typeof calendar !== 'undefined') calendar.refetchEvents();
              } else {
                  mostrarNotificacion('Error: ' + (data.error || 'Error desconocido'), 'error');
              }
          })
          .catch(err => {
              console.error(err);
              mostrarNotificacion('Error al guardar la cita.', 'error');
          })
          .finally(() => {
              submitBtn.disabled = false;
              submitBtn.innerHTML = originalBtnContent;
          });
        };
    });
  </script>


</body>
</html>