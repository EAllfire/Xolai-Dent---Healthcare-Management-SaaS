<?php
session_start();
require_once("includes/db.php");

// Verificar conexión a la base de datos y mostrar estado
if (!$conn || $conn->connect_error) {
    echo '<div style="background:#dc3545;color:white;padding:15px;margin:10px;border-radius:5px;font-family:Arial;text-align:center;">
        <strong>❌ ERROR DE CONEXIÓN A BASE DE DATOS</strong><br>
        Error: ' . ($conn ? $conn->connect_error : 'No se pudo establecer conexión') . '<br>
        Verificar MAMP y credenciales en includes/db.php
        </div>';
} else {
    echo '<div style="background:#28a745;color:white;padding:8px;margin:10px;border-radius:5px;font-family:Arial;font-size:13px;text-align:center;">
        ✅ DB Conectada: localhost:8883/agenda_hospital (' . date('H:i:s') . ')
        </div>';
}

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
$puede_crear_citas = in_array($user_tipo, ['admin', 'caja']);
$puede_gestionar_usuarios = ($user_tipo === 'admin');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Angeles - Sistema de Citas - v9.0 (<?php echo date('H:i:s'); ?>)</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- FULLCALENDAR CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/index.global.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@6.1.8/index.global.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fullcalendar/resource-timegrid@6.1.8/index.global.min.css" rel="stylesheet">
    <!-- Flatpickr CSS -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            overflow-x: hidden;
        }

        /* Header Styles */
        .main-header {
            background: #1275a0;
            color: white;
            height: 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
            font-family: Arial, sans-serif;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-right {
            display: flex;
            align-items: center;
        }
        
        .logo-section {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            text-align: center;
        }
        
        .header-logo {
            display: flex;
            align-items: center;
        }
        
        .header-logo img {
            max-height: 60px;
            margin-left: 10px;
            width: auto;
            filter: drop-shadow(1px 1px 2px rgba(0,0,0,0.1)) brightness(1.1);
        }
        
        .logo-text {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            letter-spacing: 0.5px;
            text-align: center;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
            font-size: 14px;
            background: rgba(255,255,255,0.1);
            padding: 8px 12px;
            border-radius: 6px;
        }
        
        .user-type {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .header-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: white;
            cursor: pointer;
        }
        
        /* Responsive Header */
        @media (max-width: 768px) {
            .main-header {
                padding: 15px;
                height: 70px;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .header-left {
                gap: 10px;
            }
            
            .header-logo img {
                max-height: 45px;
            }
            
            .logo-text {
                font-size: 18px;
            }
            
            .user-info {
                gap: 4px;
                padding: 6px 8px;
                font-size: 12px;
            }
            
            .user-type {
                display: none;
            }
            
            .header-buttons {
                gap: 0.25rem;
            }
            
            .btn-header {
                padding: 0.4rem 0.8rem !important;
                font-size: 12px !important;
            }
        }
        
        @media (max-width: 480px) {
            .main-header {
                padding: 10px;
                height: 60px;
            }
            
            .header-left {
                gap: 5px;
            }
            
            .header-logo img {
                max-height: 35px;
            }
            
            .logo-text {
                font-size: 16px;
            }
            
            .user-info span:not(.fas) {
                display: none;
            }
            
            .user-info {
                padding: 4px 6px;
            }
            
            .btn-header {
                padding: 0.3rem 0.6rem !important;
                font-size: 11px !important;
            }
        }

        .btn-header {
            color: white;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
            background: none;
            border: none;
            padding: 0.5rem 1rem;
            font-size: 13px;
            cursor: pointer;
        }
        
        .btn-header:hover {
            text-decoration: underline;
            color: #cce7ff;
        }

        /* Main Content */
        .main-content {
            margin-top: 80px;
            display: flex;
            height: calc(100vh - 80px);
        }

        /* Sidebar */
        .sidebar {
            width: 320px;
            background: white;
            border-right: 1px solid rgba(68, 162, 255, 0.2);
            padding: 1.5rem;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(68, 162, 255, 0.3) #f1f5f9;
            transition: transform 0.3s ease-in-out;
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 3px;
        }
        
        /* Modern Select Styles */
        select, .form-control select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background: white;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 40px 10px 12px;
            font-size: 14px;
            color: #374151;
            transition: all 0.2s ease;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        select:hover {
            border-color: #1275a0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        select:focus {
            outline: none;
            border-color: #1275a0;
            box-shadow: 0 0 0 3px rgba(18, 117, 160, 0.1);
        }
        
        select:disabled {
            background-color: #f9fafb;
            color: #9ca3af;
            cursor: not-allowed;
        }
        
        /* Form Control Override */
        .form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background: white;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 40px 10px 12px;
            font-size: 14px;
            color: #374151;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        .form-control:hover {
            border-color: #1275a0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .form-control:focus {
            outline: none;
            border-color: #1275a0;
            box-shadow: 0 0 0 3px rgba(18, 117, 160, 0.1);
        }
        
        /* Calendar Area */
        .calendar-area {
            flex: 1;
            padding: 2rem;
            background: white;
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
                margin-top: 70px;
                height: calc(100vh - 70px);
            }
            
            .sidebar {
                position: fixed;
                left: 0;
                top: 70px;
                height: calc(100vh - 70px);
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
                margin-top: 60px;
                height: calc(100vh - 60px);
            }
            
            .sidebar {
                top: 60px;
                height: calc(100vh - 60px);
                width: 280px;
                padding: 1rem 0.75rem;
            }
            
            .calendar-area {
                padding: 0.75rem;
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
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(18, 117, 160, 0.1);
            backdrop-filter: blur(10px);
        }
        
        /* Contenedor unificado para todos los controles */
        .unified-controls {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(18, 117, 160, 0.1);
            backdrop-filter: blur(10px);
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
            background: linear-gradient(90deg, transparent 0%, rgba(18, 117, 160, 0.2) 20%, rgba(18, 117, 160, 0.2) 80%, transparent 100%);
        }
        
        .control-section:last-child::after {
            display: none;
        }
        
        .control-title {
            color: #1275a0;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .control-title i {
            font-size: 16px;
            color: #1275a0;
        }
        
        .filter-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transform: translateY(-1px);
        }
        
        .filter-card h5 {
            color: #1f2937;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 0.5rem;
            border-left: 3px solid #1f2937;
            padding-left: 0.75rem;
        }
        
        .form-control, .form-select {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            font-size: 13px;
            background: white;
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
            padding: 1.5rem;
            background: white;
        }

        /* Mini Calendarios */
        .mini-calendar {
            background: transparent;
            border-radius: 0;
            padding: 0;
            box-shadow: none;
            margin: 0;
            width: 100%;
            max-width: 270px; /* Ajustar al ancho de la sidebar menos padding */
        }
        
        .mini-calendar .flatpickr-calendar {
            position: static !important;
            width: 100% !important;
            max-width: 270px !important;
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
        
        /* Estilo moderno para el select de meses en Flatpickr */
        .mini-calendar .flatpickr-monthDropdown-months {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background: white !important;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 8px center !important;
            background-size: 12px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 6px !important;
            padding: 6px 25px 6px 10px !important;
            font-size: 13px !important;
            color: #374151 !important;
            transition: all 0.2s ease !important;
            cursor: pointer !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
        }
        
        .mini-calendar .flatpickr-monthDropdown-months:hover {
            border-color: #1275a0 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        }
        
        .mini-calendar .flatpickr-monthDropdown-months:focus {
            outline: none !important;
            border-color: #1275a0 !important;
            box-shadow: 0 0 0 2px rgba(18, 117, 160, 0.1) !important;
        }
        
        /* Estilo moderno para el input de años en Flatpickr */
        .mini-calendar .numInput.flatpickr-year {
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            background: white !important;
            border: 1px solid #d1d5db !important;
            border-radius: 6px !important;
            padding: 6px 8px !important;
            font-size: 13px !important;
            color: #374151 !important;
            transition: all 0.2s ease !important;
            width: 60px !important;
            text-align: center !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
        }
        
        .mini-calendar .numInput.flatpickr-year:hover {
            border-color: #1275a0 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        }
        
        .mini-calendar .numInput.flatpickr-year:focus {
            outline: none !important;
            border-color: #1275a0 !important;
            box-shadow: 0 0 0 2px rgba(18, 117, 160, 0.1) !important;
        }

        .mini-calendar .flatpickr-calendar .flatpickr-weekdays {
            background: transparent !important;
            margin-bottom: 4px;
        }
        
        .mini-calendar .flatpickr-calendar .flatpickr-weekday {
            background: transparent !important;
            color: #6b7280 !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            padding: 4px 0 !important;
        }

        .mini-calendar .flatpickr-calendar .flatpickr-days {
            width: 100% !important;
            max-width: 270px !important;
        }

        .mini-calendar .flatpickr-calendar .flatpickr-day {
            width: 35px !important;
            max-width: 35px !important;
            height: 35px !important;
            line-height: 35px !important;
            margin: 1px !important;
            color: #374151;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 400;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .mini-calendar .flatpickr-calendar .flatpickr-day:hover {
            background: #f3f4f6 !important;
            color: #1f2937 !important;
            transform: scale(1.1);
        }

        .mini-calendar .flatpickr-calendar .flatpickr-day.today {
            background: #e5f3ff !important;
            color: #1275a0 !important;
            border: 2px solid #1275a0 !important;
            font-weight: 600;
        }

        .mini-calendar .flatpickr-calendar .flatpickr-day.selected {
            background: #1275a0 !important;
            color: white !important;
            font-weight: 600 !important;
        }

        .mini-calendar .flatpickr-calendar .flatpickr-day.selected:hover {
            background: #0f5f85 !important;
            transform: scale(1.1);
        }
        
        .mini-calendar .flatpickr-calendar .flatpickr-day.nextMonthDay,
        .mini-calendar .flatpickr-calendar .flatpickr-day.prevMonthDay {
            color: #d1d5db !important;
        }
        
        .mini-calendar .flatpickr-calendar .flatpickr-day.disabled {
            color: #d1d5db !important;
            cursor: not-allowed !important;
        }
        
        /* Ajustar títulos de los mini calendarios */
        .filter-card h5 {
            color: #374151;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        /* Estilos para puntos de estado en tooltip */
        .estado-punto {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: inline-block;
            margin: 2px;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
        }
        
        .estado-punto:hover .estado-punto-tooltip {
            opacity: 1 !important;
        }
        
        .estado-punto.clickeable:hover {
            transform: scale(1.2);
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        
        .estado-punto.activo {
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.4);
        }
        
        .estado-punto-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            padding: 4px 6px;
            border-radius: 4px;
            font-size: 10px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
            z-index: 100000;
        }
        
        /* Línea de tiempo actual personalizada */
        .fc-timegrid-now-indicator-line {
            border-color: #dc3545 !important;
            border-width: 2px !important;
            box-shadow: 0 0 8px rgba(220, 53, 69, 0.3) !important;
        }
        
        .fc-timegrid-now-indicator-arrow {
            border-left-color: #dc3545 !important;
            border-right-color: #dc3545 !important;
        }
        
        /* Mejorar visibilidad de la línea de tiempo */
        .fc-timegrid-slot {
            position: relative;
        }
        
        .fc-timegrid-now-indicator-container {
            z-index: 100 !important;
        }

        /* FullCalendar Customizations */
        .fc {
            font-family: 'Inter', sans-serif;
            background: white !important;
        }
        
        #calendar {
            background: white !important;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .fc-view-harness {
            background: white !important;
        }
        
        .fc-scrollgrid {
            background: white !important;
        }
        
        /* Estilos para recursos y modalidades */
        .fc-resource-area {
            background: #f8f9fa !important;
            border-right: 1px solid #dee2e6 !important;
        }
        
        .fc-resource-area-header {
            background: #e9ecef !important;
            font-weight: 600 !important;
            color: #495057 !important;
        }
        
        .fc-datagrid-cell {
            padding: 8px 12px !important;
            vertical-align: middle !important;
        }
        
        .fc-resource {
            font-size: 13px !important;
            font-weight: 500 !important;
            color: #374151 !important;
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
            background: #1275a0 !important;
            border-color: #0f5e85 !important;
            color: white !important;
            border-radius: 6px !important;
            font-weight: 500 !important;
            padding: 0.4rem 0.8rem !important;
        }
        
        .fc-button-primary:hover {
            background: #0f5e85 !important;
            border-color: #0c4a6b !important;
        }
        
        .fc-event {
            border-radius: 4px !important;
            border: none !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2) !important;
            font-size: 12px !important;
        }
        
        .fc-event:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.25) !important;
            transform: translateY(-1px);
        }
        
        /* Asegurar fondo blanco en todas las áreas del calendario */
        .fc-timegrid-slot,
        .fc-timegrid-col,
        .fc-col-header-cell,
        .fc-scrollgrid-sync-table,
        .fc-resource-timeline,
        .fc-daygrid-day {
            background: white !important;
        }
        
        .fc-timegrid-axis {
            background: #f8f9fa !important;
        }
        
        .fc-header-toolbar {
            margin-bottom: 1.5rem !important;
        }
        
        .fc-today-button, .fc-prev-button, .fc-next-button {
            background: white !important;
            border-color: #d1d5db !important;
            color: #374151 !important;
        }
        
        .fc-today-button:hover, .fc-prev-button:hover, .fc-next-button:hover {
            background: #f3f4f6 !important;
            border-color: #9ca3af !important;
        }

        /* Tooltip Styles */
        .fc-custom-tooltip {
            position: absolute;
            background: #fff;
            border: 1px solid #ccc;
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
            background: white;
            border: 1px solid #d1d5db;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
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
            color: #374151;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .context-menu button:hover { 
            background: #f3f4f6;
            color: #1275a0;
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
            box-shadow: 0 0 0 3px rgba(18, 117, 160, 0.3);
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
            background: #1275a0;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.6rem 1.2rem;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-action:hover {
            background: #0f5e85;
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
        <div class="header-left">
            <button class="mobile-menu-btn" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="header-logo">
                <img src="https://angelescuauhtemoc.com/wp-content/uploads/2020/09/logo-50-300x187.png" alt="Hospital Angeles">
            </div>
            
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($user_nombre); ?></span>
                <span class="user-type">(<?php echo ucfirst($user_tipo); ?>)</span>
            </div>
        </div>
        
        <div class="logo-section">
            <div class="logo-text">IMAGENOLOGÍA</div>
        </div>
        
        <div class="header-right">
            <div class="header-buttons">
                <?php if ($puede_gestionar_usuarios): ?>
                    <a href="admin_usuarios.php" class="btn-header">
                        <i class="fas fa-users-cog"></i> Admin
                    </a>
                    <button onclick="abrirCatalogo()" class="btn-header">
                        <i class="fas fa-list"></i> Catálogo
                    </button>
                <?php endif; ?>
                <a href="cliente.php" class="btn-header">
                    <i class="fas fa-user-friends"></i> Vista Cliente
                </a>
                <?php if ($puede_crear_citas): ?>
                    <button onclick="abrirModalAgendar()" class="btn-header">
                        <i class="fas fa-plus"></i> Nueva Cita
                    </button>
                <?php endif; ?>
                <a href="logout.php" class="btn-header">
                    <i class="fas fa-sign-out-alt"></i> Salir
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <div class="main-content">
        <!-- Sidebar -->
        <div class="sidebar">            
            <!-- Controles Unificados -->
            <div class="unified-controls">
                <!-- Modalidad -->
                <div class="control-section">
                    <h5 class="control-title">
                        <i class="fas fa-layer-group"></i>
                        Modalidad
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
            </div>
        </div>

        <!-- Calendar Area -->
        <div class="calendar-area">
            <div id="calendar"></div>
        </div>
    </div>
  <div id="contextMenu" class="context-menu">
    <button id="bloquearBtn">Bloquear</button>
    <button id="agendarBtn">Agendar</button>
  </div>
  <!-- Modal para agendar cita -->
  <!-- Modal mejorado para agendar cita -->
  <div id="modalAgendar" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.4);z-index:10000;align-items:center;justify-content:center;overflow-y:auto;">
    <div style="background:#fff;border-radius:12px;max-width:800px;width:95%;margin:20px;position:relative;box-shadow:0 10px 30px rgba(0,0,0,0.2);">
      <!-- Header del modal -->
      <div style="background: #1275a0;color:white;padding:20px 32px;border-radius:12px 12px 0 0;position:relative;">
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
          <div style="padding:24px 32px;border-bottom:1px solid #e5e7eb;">
            <h4 style="margin:0 0 16px 0;color:#374151;font-size:18px;font-weight:600;">
              <i class="fas fa-clock" style="margin-right:8px;color:#6b7280;"></i>
              Fecha y Horario
            </h4>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:16px;align-items:end;">
              <div>
                <label for="agendarFecha" style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Fecha:</label>
                <input type="text" id="agendarFecha" name="fecha" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;" autocomplete="off" />
              </div>
              <div>
                <label for="agendarHoraInicio" style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Hora inicio:</label>
                <input type="time" id="agendarHoraInicio" name="hora_inicio" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;" />
              </div>
              <div>
                <label for="agendarHoraFin" style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Hora fin:</label>
                <input type="time" id="agendarHoraFin" name="hora_fin" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;" />
              </div>
              <div>
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#6b7280;">
                  <input type="checkbox" id="tiempoManual" style="margin:0;">
                  Editar manual
                </label>
              </div>
            </div>
          </div>

          <!-- Sección de Paciente -->
          <div style="padding:24px 32px;border-bottom:1px solid #e5e7eb;">
            <h4 style="margin:0 0 16px 0;color:#374151;font-size:18px;font-weight:600;">
              <i class="fas fa-user" style="margin-right:8px;color:#6b7280;"></i>
              Información del Paciente
            </h4>
            
            <!-- Búsqueda de paciente -->
            <div style="position:relative;margin-bottom:16px;">
              <label for="agendarPaciente" style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Buscar paciente:</label>
              <div style="position:relative;">
                <input type="text" id="agendarPaciente" name="paciente" placeholder="Escribe el nombre del paciente..." autocomplete="off" 
                       style="width:100%;padding:10px 12px;padding-right:120px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;" />
                <button type="button" id="btnMostrarRegistroPaciente" 
                        style="position:absolute;right:6px;top:6px;padding:8px 16px;background:#1976d2;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;font-weight:500;">
                  <i class="fas fa-plus" style="margin-right:4px;"></i> Nuevo
                </button>
              </div>
              <div id="pacientesDropdown" style="position:absolute;top:100%;left:0;width:100%;background:#fff;z-index:10001;border:1px solid #d1d5db;border-top:none;border-radius:0 0 6px 6px;display:none;max-height:200px;overflow-y:auto;box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
            </div>

            <!-- Registro de nuevo paciente (expandible) -->
            <div id="registroPacienteBox" style="display:none;background:#f8f9fa;border:1px solid #e5e7eb;border-radius:8px;padding:20px;">
              <h5 style="margin:0 0 16px 0;color:#374151;font-size:16px;font-weight:600;">
                <i class="fas fa-user-plus" style="margin-right:8px;color:#6b7280;"></i>
                Registrar Nuevo Paciente
              </h5>
              
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <div>
                  <label style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Nombre:</label>
                  <input type="text" id="nuevoPacienteNombre" placeholder="Nombre" 
                         style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                </div>
                <div>
                  <label style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Apellido:</label>
                  <input type="text" id="nuevoPacienteApellido" placeholder="Apellido" 
                         style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
                <div>
                  <label style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Teléfono:</label>
                  <input type="text" id="nuevoPacienteTelefono" placeholder="Teléfono" 
                         style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                </div>
                <div>
                  <label style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Tipo:</label>
                  <select id="nuevoPacienteTipo" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                    <option value="niño">Niño</option>
                    <option value="adulto" selected>Adulto</option>
                    <option value="IMSS">IMSS</option>
                    <option value="urgencias">Urgencias</option>
                    <option value="externo">Externo</option>
                    <option value="interno">Interno</option>
                  </select>
                </div>
                <div>
                  <label style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Origen:</label>
                  <select id="nuevoPacienteOrigen" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                    <option value="">Seleccionar origen</option>
                    <option value="urgencias">Urgencias</option>
                    <option value="externo" selected>Externo</option>
                    <option value="interno">Interno</option>
                  </select>
                </div>
              </div>

              <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Correo electrónico:</label>
                <input type="email" id="nuevoPacienteCorreo" placeholder="correo@ejemplo.com" 
                       style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
              </div>

              <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Diagnóstico o motivo del estudio:</label>
                <textarea id="nuevoPacienteDiagnostico" placeholder="Describe el motivo del estudio o diagnóstico..." 
                          style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;min-height:60px;resize:vertical;"></textarea>
              </div>

              <div style="margin-bottom:16px;">
                <label style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Comentarios adicionales:</label>
                <textarea id="nuevoPacienteComentarios" placeholder="Información adicional relevante..." 
                          style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;min-height:60px;resize:vertical;"></textarea>
              </div>

              <div style="display:flex;gap:12px;">
                <button type="button" id="btnGuardarPaciente" 
                        style="background:#10b981;color:#fff;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-weight:500;font-size:14px;">
                  <i class="fas fa-save" style="margin-right:6px;"></i>
                  Guardar Paciente
                </button>
                <button type="button" id="btnCancelarPaciente" 
                        style="background:#6b7280;color:#fff;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-weight:500;font-size:14px;">
                  <i class="fas fa-times" style="margin-right:6px;"></i>
                  Cancelar
                </button>
              </div>
            </div>
          </div>

          <!-- Sección de Servicio -->
          <div style="padding:24px 32px;border-bottom:1px solid #e5e7eb;">
            <h4 style="margin:0 0 16px 0;color:#374151;font-size:18px;font-weight:600;">
              <i class="fas fa-stethoscope" style="margin-right:8px;color:#6b7280;"></i>
              Servicio y Modalidad
            </h4>
            
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
              <div>
                <label for="modalidadSeleccionadaLabel" style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Modalidad:</label>
                <input type="hidden" id="agendarProfesional" name="profesional" />
                <div id="modalidadSeleccionadaLabel" style="display:block;padding:10px 12px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:6px;font-size:14px;color:#6b7280;">
                  Seleccionar modalidad
                </div>
              </div>
              <div>
                <label for="agendarServicio" style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Servicio:</label>
                <select id="agendarServicio" name="servicio" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                  <option value="">Seleccione un servicio</option>
                </select>
              </div>
              <div>
                <label for="agendarEstado" style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Estado inicial:</label>
                <select id="agendarEstado" name="estado_id" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                  <option value="1" selected>Reservado</option>
                  <option value="2">Confirmado</option>
                  <option value="5">Pendiente</option>
                  <option value="6">En espera</option>
                </select>
              </div>
            </div>

            <!-- Información de duración -->
            <div id="duracionInfo" style="margin-top:12px;padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;display:none;">
              <p style="margin:0;font-size:13px;color:#1e40af;">
                <i class="fas fa-info-circle" style="margin-right:6px;"></i>
                <span id="duracionTexto">Duración estimada: </span>
              </p>
            </div>
          </div>

          <!-- Sección de Notas -->
          <div style="padding:24px 32px;border-bottom:1px solid #e5e7eb;">
            <button type="button" id="btnToggleInfoAdicional" 
                    style="width:100%;background:#f9fafb;color:#374151;padding:12px 16px;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;font-weight:500;display:flex;align-items:center;justify-content:space-between;font-size:14px;">
              <span>
                <i class="fas fa-sticky-note" style="margin-right:8px;color:#6b7280;"></i>
                Información adicional y notas
              </span>
              <i id="iconInfoAdicional" class="fas fa-chevron-down" style="transition:transform 0.2s;"></i>
            </button>
            
            <div id="infoAdicionalBox" style="display:none;margin-top:16px;padding-top:16px;">
              <div style="display:grid;grid-template-columns:1fr;gap:16px;">
                <div>
                  <label for="notaPaciente" style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Notas para el paciente:</label>
                  <textarea id="notaPaciente" name="nota_paciente" rows="3" 
                            style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;resize:vertical;" 
                            placeholder="Instrucciones y notas que verá el paciente...">Recuerde llegar 10 minutos antes de su cita y traer sus estudios previos si los tiene.</textarea>
                </div>
                <div>
                  <label for="notaInterna" style="display:block;margin-bottom:6px;font-weight:500;color:#374151;">Nota interna (uso del personal):</label>
                  <textarea id="notaInterna" name="nota_interna" rows="3" 
                            style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;resize:vertical;" 
                            placeholder="Notas internas para el personal médico..."></textarea>
                </div>
                <div>
                    <label class="form-check-label">
                        <input type="checkbox" id="agendarAtencionEspecial" name="atencion_especial" style="margin-right: 8px;">
                        Requiere asistencia especial (silla de ruedas, etc.)
                    </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer con botones -->
          <div style="padding:24px 32px;background:#f9fafb;border-radius:0 0 12px 12px;">
            <div style="display:flex;gap:12px;justify-content:end;">
              <button type="button" onclick="document.getElementById('modalAgendar').style.display='none';" 
                      style="background:#6b7280;color:#fff;padding:12px 24px;border:none;border-radius:6px;cursor:pointer;font-weight:500;font-size:14px;">
                <i class="fas fa-times" style="margin-right:6px;"></i>
                Cancelar
              </button>
              <button type="submit" 
                      style="background:#1275a0;color:#fff;padding:12px 24px;border:none;border-radius:6px;cursor:pointer;font-weight:500;font-size:14px;">
                <i class="fas fa-calendar-check" style="margin-right:6px;"></i>
                Guardar Cita
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar-scheduler@6.1.8/index.global.min.js"></script>
  <script>
    // Paciente autocompletar y registro
    let pacientesList = [];
    let pacienteInput = document.getElementById('agendarPaciente');
    let pacientesDropdown = document.getElementById('pacientesDropdown');

    fetch('pacientes_json.php')
      .then(r => r.json())
      .then(data => {
        pacientesList = data;
      });

    function renderPacientesDropdown(filtro) {
      pacientesDropdown.innerHTML = '';
      let filtroLower = filtro.toLowerCase();
      let filtrados = pacientesList.filter(p => p.nombre.toLowerCase().includes(filtroLower));
      if (filtrados.length === 0) {
        pacientesDropdown.style.display = 'none';
        return;
      }
      filtrados.forEach(p => {
        let item = document.createElement('div');
        item.textContent = p.nombre;
        item.style.padding = '6px 10px';
        item.style.cursor = 'pointer';
        item.onclick = function() {
          pacienteInput.value = p.nombre;
          pacienteInput.dataset.pacienteId = p.id;
          pacientesDropdown.style.display = 'none';
        };
        pacientesDropdown.appendChild(item);
      });
      pacientesDropdown.style.display = 'block';
    }

    pacienteInput.addEventListener('input', function() {
      let val = pacienteInput.value.trim();
      if (val.length > 0) {
        renderPacientesDropdown(val);
      } else {
        pacientesDropdown.style.display = 'none';
      }
    });
    pacienteInput.addEventListener('focus', function() {
      if (pacienteInput.value.trim().length > 0) {
        renderPacientesDropdown(pacienteInput.value.trim());
      }
    });
    pacienteInput.addEventListener('blur', function() {
      setTimeout(function() { pacientesDropdown.style.display = 'none'; }, 150);
    });

    document.getElementById('btnMostrarRegistroPaciente').onclick = function() {
      document.getElementById('registroPacienteBox').style.display = 'block';
      document.getElementById('nuevoPacienteNombre').focus();
    };

    document.getElementById('btnGuardarPaciente').onclick = function() {
      let nombre = document.getElementById('nuevoPacienteNombre').value.trim();
      let apellido = document.getElementById('nuevoPacienteApellido').value.trim();
      let telefono = document.getElementById('nuevoPacienteTelefono').value.trim();
      let correo = document.getElementById('nuevoPacienteCorreo').value.trim();
      let comentarios = document.getElementById('nuevoPacienteComentarios').value.trim();
      let diagnostico = document.getElementById('nuevoPacienteDiagnostico').value.trim();
      let tipo = document.getElementById('nuevoPacienteTipo').value;
      let origen = document.getElementById('nuevoPacienteOrigen').value;

      if (nombre && apellido) {
        let formData = new FormData();
        formData.append('nombre', nombre);
        formData.append('apellido', apellido);
        formData.append('telefono', telefono);
        formData.append('correo', correo);
        formData.append('diagnostico', diagnostico);
        formData.append('tipo', tipo);
        formData.append('origen', origen);
        formData.append('comentarios', comentarios);

        fetch('guardar_paciente.php', {
          method: 'POST',
          body: formData
        })
        .then(r => r.json())
        .then(resp => {
          if (resp.success && resp.id) {
            pacientesList.push({id: resp.id, nombre: `${nombre} ${apellido}`});
            pacienteInput.value = `${nombre} ${apellido}`;
            pacienteInput.dataset.pacienteId = resp.id;
            document.getElementById('registroPacienteBox').style.display = 'none';
            alert('Paciente registrado correctamente.');
          } else {
            alert('Error al guardar paciente: ' + (resp.error || ''));
          }
        });
      } else {
        alert('Por favor ingresa nombre y apellido del paciente.');
      }
    };

    document.getElementById('btnCancelarPaciente').onclick = function() {
      document.getElementById('registroPacienteBox').style.display = 'none';
    };

    // -- Calendarios y demás lógica --
    function cargarProfesionales() {
      fetch('recursos_json.php')
        .then(r => r.json())
        .then(data => {
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
        });
    }
    cargarProfesionales();

    function cargarEstados() {
      fetch('estados_json.php')
        .then(r => r.json())
        .then(data => {
          const select = document.getElementById('estado-select');
          select.innerHTML = '';
          // Opción 'Todos'
          const optTodos = document.createElement('option');
          optTodos.value = 'todos';
          optTodos.textContent = 'Todos';
          select.appendChild(optTodos);
          // Estados
          data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.nombre.charAt(0).toUpperCase() + item.nombre.slice(1);
            select.appendChild(opt);
          });
        });
    }
    cargarEstados();

    document.addEventListener('DOMContentLoaded', function() {
      function cargarServiciosPorModalidad(modalidadId) {
        var servicioSelect = document.getElementById('agendarServicio');
        servicioSelect.innerHTML = '';
        var defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = 'Seleccione un servicio';
        servicioSelect.appendChild(defaultOpt);
        
        // Ocultar información de duración al cambiar modalidad
        var duracionInfo = document.getElementById('duracionInfo');
        if (duracionInfo) duracionInfo.style.display = 'none';
        
        if (!modalidadId || isNaN(modalidadId) || modalidadId <= 0) return;
        
        fetch('servicios_con_duracion.php?modalidad_id=' + modalidadId)
          .then(r => r.json())
          .then(data => {
            data.forEach(function(servicio) {
              var opt = document.createElement('option');
              opt.value = servicio.id;
              opt.textContent = servicio.nombre;
              opt.setAttribute('data-duracion', servicio.duracion_minutos);
              servicioSelect.appendChild(opt);
            });
            
            // Agregar evento para manejar cambio de servicio
            servicioSelect.onchange = function() {
              manejarCambioServicio();
            };
          });
      }

      // Nueva función para manejar el cambio de servicio y actualizar duración
      function manejarCambioServicio() {
        var servicioSelect = document.getElementById('agendarServicio');
        var selectedOption = servicioSelect.options[servicioSelect.selectedIndex];
        var duracion = selectedOption.getAttribute('data-duracion');
        var tiempoManual = document.getElementById('tiempoManual');
        
        if (duracion && !tiempoManual.checked) {
          // Calcular nueva hora fin basada en la duración
          var horaInicioInput = document.getElementById('agendarHoraInicio');
          if (horaInicioInput.value) {
            var horaInicio = horaInicioInput.value;
            var nuevaHoraFin = calcularHoraFin(horaInicio, parseInt(duracion));
            document.getElementById('agendarHoraFin').value = nuevaHoraFin;
          }
          
          // Mostrar información de duración
          var duracionInfo = document.getElementById('duracionInfo');
          var duracionTexto = document.getElementById('duracionTexto');
          if (duracionInfo && duracionTexto) {
            duracionTexto.textContent = `Duración estimada: ${duracion} minutos`;
            duracionInfo.style.display = 'block';
          }
        } else if (!duracion) {
          // Ocultar información de duración si no hay servicio seleccionado
          var duracionInfo = document.getElementById('duracionInfo');
          if (duracionInfo) duracionInfo.style.display = 'none';
        }
      }

      // Función para calcular hora fin basada en hora inicio y duración
      function calcularHoraFin(horaInicio, duracionMinutos) {
        if (!horaInicio) return '';
        
        var [horas, minutos] = horaInicio.split(':').map(Number);
        var totalMinutos = horas * 60 + minutos + duracionMinutos;
        
        var nuevasHoras = Math.floor(totalMinutos / 60);
        var nuevosMinutos = totalMinutos % 60;
        
        return String(nuevasHoras).padStart(2, '0') + ':' + String(nuevosMinutos).padStart(2, '0');
      }

      var modalidadSelect = document.getElementById('profesional-select');
      modalidadSelect.addEventListener('change', function() {
        var modalidadId = modalidadSelect.value;
        cargarServiciosPorModalidad(modalidadId);
        // Filtrar recursos en el calendario
        if (modalidadId === 'todos') {
          calendar.setOption('resources', 'recursos_json.php');
        } else {
          fetch('recursos_json.php')
            .then(r => r.json())
            .then(data => {
              const recurso = data.find(item => item.id == modalidadId);
              if (recurso) {
                calendar.setOption('resources', [recurso]);
              }
            });
        }
      });

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

      var calendarEl = document.getElementById('calendar');
      var contextMenu = document.getElementById('contextMenu');
      var bloquearBtn = document.getElementById('bloquearBtn');
      var agendarBtn = document.getElementById('agendarBtn');
      var lastDateClickInfo = null;
      var tooltipActivo = null; // Variable global para controlar tooltips

      // Event listener global para cerrar tooltip y menú contextual al hacer click fuera
      document.addEventListener('click', function(e) {
        // Cerrar menú contextual si se hace click fuera
        if (contextMenu && contextMenu.style.display === 'block') {
          // Verificar si el click fue dentro del menú contextual o en el área del calendario
          var clickEnMenu = contextMenu.contains(e.target);
          var clickEnCalendario = e.target.closest('#calendar') || e.target.closest('.fc-daygrid-day') || e.target.closest('.fc-timegrid-slot');
          
          if (!clickEnMenu && !clickEnCalendario) {
            contextMenu.style.display = 'none';
            console.log('Menú contextual cerrado por click fuera');
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
            successMsg.style.cssText = `
              position: fixed; top: 20px; right: 20px; z-index: 100000;
              background: #4CAF50; color: white; padding: 12px 20px;
              border-radius: 4px; font-family: Roboto, sans-serif;
              box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            `;
            successMsg.textContent = `Estado actualizado a: ${nuevoEstado}`;
            document.body.appendChild(successMsg);
            
            setTimeout(() => {
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
            
            alert('Error al actualizar el estado: ' + (data.error || 'Error desconocido'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          
          // Restablecer el tooltip si aún existe
          var tooltip = elementoCita._fcTooltip;
          if (tooltip && tooltip.parentNode) {
            var loadingDiv = tooltip.querySelector('.estado-puntos');
            if (loadingDiv) {
              loadingDiv.innerHTML = '<span style="font-size:12px; color:red;">Error al actualizar</span>';
            }
          }
          
          alert('Error de conexión al actualizar el estado');
        });
      }

      var calendar = new FullCalendar.Calendar(calendarEl, {
        eventDidMount: function(info) {
          var event = info.event;
          
          // Asegurar que el color se aplique correctamente
          if (event.color) {
            info.el.style.backgroundColor = event.color;
            info.el.style.borderColor = event.color;
          } else if (event.backgroundColor) {
            info.el.style.backgroundColor = event.backgroundColor;
            info.el.style.borderColor = event.backgroundColor;
          }
          
          var paciente = event.title.split(' (')[0];
          var servicio = event.title.split('(')[1]?.replace(')','') || '';
          var horaInicio = event.start ? event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
          var horaFin = event.end ? event.end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
          var telefono = event.extendedProps.telefono || '';
          var diagnostico = event.extendedProps.diagnostico || '';
          var pago = event.extendedProps.pago || 'No pagado';
          var atencionEspecial = event.extendedProps.atencion_especial == '1';
          var estadoActual = event.extendedProps.estado || '';
          
          // Definir todos los estados y sus colores
          var todosLosEstados = [
            {nombre: 'reservado', color: '#2196F3', label: 'Reservado'},
            {nombre: 'confirmado', color: '#FF9800', label: 'Confirmado'},
            {nombre: 'asistió', color: '#E91E63', label: 'Asistió'},
            {nombre: 'no asistió', color: '#FF7F50', label: 'No asistió'},
            {nombre: 'pendiente', color: '#F44336', label: 'Pendiente'},
            {nombre: 'en espera', color: '#4CAF50', label: 'En espera'}
          ];
          
          // Crear puntos de estados
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
            <div style='font-family:Inter,sans-serif;max-width:280px;background:white;'>
              <div style='font-weight:bold;font-size:16px;color:#1275a0;margin-bottom:8px;'>${paciente}</div>
              ${atencionEspecial ? `
                <div style='background:#fef2f2;color:#991b1b;padding:6px 10px;border-radius:6px;font-size:13px;font-weight:500;margin-bottom:8px;'>
                  <i class="fas fa-wheelchair" style="margin-right:6px;"></i> Requiere Asistencia Especial
                </div>
              ` : ''}
              <div style='margin-bottom:6px;color:#374151;font-weight:500;'>${servicio}</div>
              <div style='font-size:14px;color:#6b7280;margin-bottom:4px;'><span style='margin-right:8px;'>🕒</span>${horaInicio} - ${horaFin}</div>
              <div style='font-size:14px;color:#6b7280;margin-bottom:4px;'><span style='margin-right:8px;'>💲</span>${pago}</div>
              <div class='estado-puntos' style='margin:8px 0;'>
                <span style='font-size:12px; margin-right:8px; color:#374151; font-weight:600;'>Estados:</span>
                ${estadoPuntos}
              </div>
              <hr style='margin:8px 0; border:none; border-top:1px solid #e5e7eb;'>
              <div style='font-size:14px;color:#6b7280;margin-bottom:4px;'><span style='margin-right:8px;'>📱</span>${telefono}</div>
              <div style='font-size:14px;color:#6b7280;'><span style='margin-right:8px;'>💬</span>${diagnostico}</div>
            </div>
          `;
          
          // Eliminar título por defecto del navegador
          info.el.setAttribute('title', '');
          
          // Event listener para mostrar tooltip al pasar mouse
          info.el.addEventListener('mouseenter', function(e) {
            // Si ya hay un tooltip activo, no crear otro
            if (tooltipActivo) {
              return;
            }
            
            // Limpiar cualquier timeout pendiente
            if (info.el._hideTimeout) {
              clearTimeout(info.el._hideTimeout);
              info.el._hideTimeout = null;
            }
            
            let tip = document.createElement('div');
            tip.className = 'fc-custom-tooltip';
            tip.innerHTML = tooltip;
            tip.style.cssText = `
              position: fixed;
              z-index: 99999;
              background: white;
              border: 1px solid #d1d5db;
              box-shadow: 0 10px 25px rgba(0,0,0,0.15);
              padding: 16px;
              border-radius: 12px;
              font-size: 14px;
              pointer-events: auto;
              max-width: 300px;
              font-family: Inter, sans-serif;
            `;
            tip.style.top = (e.clientY + 15) + 'px';
            tip.style.left = (e.clientX + 15) + 'px';
            tip.id = 'fc-tooltip-'+event.id;
            document.body.appendChild(tip);
            info.el._fcTooltip = tip;
            tooltipActivo = tip;
            
            // Prevenir que el tooltip desaparezca al hacer hover sobre él
            tip.addEventListener('mouseenter', function() {
              if (info.el._hideTimeout) {
                clearTimeout(info.el._hideTimeout);
                info.el._hideTimeout = null;
              }
            });
            
            tip.addEventListener('mouseleave', function() {
              info.el._hideTimeout = setTimeout(function() {
                if (info.el._fcTooltip && tooltipActivo === info.el._fcTooltip) {
                  document.body.removeChild(info.el._fcTooltip);
                  info.el._fcTooltip = null;
                  tooltipActivo = null;
                }
              }, 300);
            });
            
            // Agregar event listeners para los clicks en los puntos de estado
            tip.addEventListener('click', function(e) {
              if (e.target.classList.contains('estado-punto') && e.target.classList.contains('clickeable')) {
                var nuevoEstado = e.target.getAttribute('data-estado');
                var citaId = e.target.getAttribute('data-cita-id');
                
                if (nuevoEstado && citaId) {
                  cambiarEstadoCita(citaId, nuevoEstado, event, info.el);
                }
              }
            });
          });
          
          // Event listener para mover tooltip con el mouse
          info.el.addEventListener('mousemove', function(e) {
            if (info.el._fcTooltip) {
              info.el._fcTooltip.style.top = (e.clientY + 15) + 'px';
              info.el._fcTooltip.style.left = (e.clientX + 15) + 'px';
            }
          });
          
          // Event listener para ocultar tooltip al salir del mouse
          info.el.addEventListener('mouseleave', function() {
            info.el._hideTimeout = setTimeout(function() {
              if (info.el._fcTooltip && tooltipActivo === info.el._fcTooltip) {
                // Prevenir error si el tooltip ya fue removido por otra acción
                if (info.el._fcTooltip.parentNode) {
                    document.body.removeChild(info.el._fcTooltip);
                }
                document.body.removeChild(info.el._fcTooltip);
                info.el._fcTooltip = null;
                tooltipActivo = null;
              }
            }, 300);
          });
              <div style='margin-bottom:6px;color:#374151;font-weight:500;'>${servicio}</div>
              ${atencionEspecial ? `
                <div style='background:#fef2f2;color:#991b1b;padding:6px 10px;border-radius:6px;font-size:13px;font-weight:500;margin-bottom:8px;'>
                  <i class="fas fa-wheelchair" style="margin-right:6px;"></i> Requiere Asistencia Especial
                </div>
              ` : ''}
              <div style='font-size:14px;color:#6b7280;margin-bottom:4px;'><span style='margin-right:8px;'>🕒</span>${horaInicio} - ${horaFin}</div>
              <div style='font-size:14px;color:#6b7280;margin-bottom:4px;'><span style='margin-right:8px;'>💲</span>${pago}</div>
              <div class='estado-puntos' style='margin:8px 0;'>
                <span style='font-size:12px; margin-right:8px; color:#374151; font-weight:600;'>Estados:</span>
                ${estadoPuntos}
              </div>
              <hr style='margin:8px 0; border:none; border-top:1px solid #e5e7eb;'>
              <div style='font-size:14px;color:#6b7280;margin-bottom:4px;'><span style='margin-right:8px;'>📱</span>${telefono}</div>
              <div style='font-size:14px;color:#6b7280;'><span style='margin-right:8px;'>💬</span>${diagnostico}</div>
            </div>
          `;
          info.el.setAttribute('title', '');
          info.el.addEventListener('mouseenter', function(e) {
            // Si ya hay un tooltip activo, no crear otro
            if (tooltipActivo) {
              return;
            }
            
            // Limpiar cualquier timeout pendiente
            if (info.el._hideTimeout) {
              clearTimeout(info.el._hideTimeout);
              info.el._hideTimeout = null;
            }
            
            let tip = document.createElement('div');
            tip.className = 'fc-custom-tooltip';
            tip.innerHTML = tooltip;
            tip.style.cssText = `
              position: absolute;
              z-index: 99999;
              background: white;
              border: 1px solid #d1d5db;
              box-shadow: 0 10px 25px rgba(0,0,0,0.15);
              padding: 16px;
              border-radius: 12px;
              font-size: 14px;
              pointer-events: auto;
              max-width: 300px;
              font-family: Inter, sans-serif;
            `;
            tip.style.top = (e.clientY + 15) + 'px';
            tip.style.left = (e.clientX + 15) + 'px';
            tip.id = 'fc-tooltip-'+event.id;
            document.body.appendChild(tip);
            info.el._fcTooltip = tip;
            tooltipActivo = tip; // Marcar como tooltip activo
            
            // Prevenir que el tooltip desaparezca al hacer hover sobre él
            tip.addEventListener('mouseenter', function() {
              if (info.el._hideTimeout) {
                clearTimeout(info.el._hideTimeout);
                info.el._hideTimeout = null;
              }
            });
            
            tip.addEventListener('mouseleave', function() {
              info.el._hideTimeout = setTimeout(function() {
                if (info.el._fcTooltip && tooltipActivo === info.el._fcTooltip) {
                  document.body.removeChild(info.el._fcTooltip);
                  info.el._fcTooltip = null;
                  tooltipActivo = null;
                }
              }, 300);
            });
            
            // Agregar event listeners para los clicks en los puntos de estado
            tip.addEventListener('click', function(e) {
              if (e.target.classList.contains('estado-punto') && e.target.classList.contains('clickeable')) {
                var nuevoEstado = e.target.getAttribute('data-estado');
                var citaId = e.target.getAttribute('data-cita-id');
                
                if (nuevoEstado && citaId) {
                  cambiarEstadoCita(citaId, nuevoEstado, event, info.el);
                }
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
                // Prevenir error si el tooltip ya fue removido por otra acción
                if (info.el._fcTooltip.parentNode) {
                    document.body.removeChild(info.el._fcTooltip);
                }
                info.el._fcTooltip = null;
                tooltipActivo = null;
              }
            }, 300);
          });
        },
        schedulerLicenseKey: 'CC-Attribution-NonCommercial-NoDerivatives',
        initialView: 'resourceTimeGridDay',
        locale: 'es',
        resources: 'recursos_json.php',
        events: 'citas_json.php',
        
        // Configuración de recursos para evitar solapamiento de nombres
        resourceAreaWidth: window.innerWidth <= 768 ? '180px' : (window.innerWidth <= 480 ? '140px' : '250px'),
        resourceAreaColumns: [
          {
            field: 'title',
            headerContent: 'Modalidades',
            width: window.innerWidth <= 768 ? '180px' : (window.innerWidth <= 480 ? '140px' : '250px')
          }
        ],
        resourceLabelContent: function(arg) {
          // Mostrar nombres completos sin abreviaciones
          var title = arg.resource.title;
          
          // Crear elemento con mejor formato
          var element = document.createElement('div');
          element.textContent = title;
          element.style.cssText = `
            line-height: 1.3;
            padding: 2px 0;
            word-break: break-word;
            hyphens: auto;
          `;
          
          return { domNodes: [element] };
        },
        // ...sin eventDidMount personalizado...
    /* ...sin tooltip personalizado... */
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'resourceTimeGridDay,listWeek'
        },
        buttonText: {
          today: 'Hoy',
          month: 'Mes',
          week: 'Semana',
          day: 'Día',
          resourceTimeGridDay: 'Día',
          resourceTimeGridWeek: 'Semana'
        },
        slotMinTime: "07:00:00",
        slotMaxTime: "23:59:00",
        slotDuration: "00:30:00",
        allDaySlot: false, // Quitar la fila de All Day
        nowIndicator: true, // Mostrar línea de tiempo actual
        height: "100vh",
        selectable: true,
        selectMirror: true,
        select: function(info) {
          lastDateClickInfo = info;
          // Cerrar cualquier tooltip activo antes de mostrar menú
          if (tooltipActivo && tooltipActivo.parentNode) {
            document.body.removeChild(tooltipActivo);
            tooltipActivo = null;
          }
          contextMenu.style.display = 'block';
          contextMenu.style.left = info.jsEvent.pageX + 'px';
          contextMenu.style.top = info.jsEvent.pageY + 'px';
          console.log('Menú contextual mostrado en select');
        },
        dateClick: function(info) {
          // Solo mostrar menú si no se hizo click en una cita
          if (!info.jsEvent.target.closest('.fc-event')) {
            lastDateClickInfo = info;
            // Cerrar cualquier tooltip activo antes de mostrar menú
            if (tooltipActivo && tooltipActivo.parentNode) {
              document.body.removeChild(tooltipActivo);
              tooltipActivo = null;
            }
            contextMenu.style.display = 'block';
            contextMenu.style.left = info.jsEvent.pageX + 'px';
            contextMenu.style.top = info.jsEvent.pageY + 'px';
            console.log('Menú contextual mostrado en dateClick');
          }
        },
      });
      calendar.render();
      
      // Actualizar marcadores cuando se carguen los eventos
      setTimeout(function() {
        actualizarMarcadoresMiniCalendarios();
      }, 1000);
      
      // Botón para vista tipo lista
      var btnVistaLista = document.getElementById('btnVistaLista');
      if (btnVistaLista) {
        btnVistaLista.addEventListener('click', function() {
          calendar.changeView('listWeek');
        });
      }
      // Botón para volver a vista calendario
      var btnVistaCalendario = document.getElementById('btnVistaCalendario');
      if (btnVistaCalendario) {
        btnVistaCalendario.addEventListener('click', function() {
          calendar.changeView('resourceTimeGridDay');
        });
      }
      calendar.render();
      
      // Función para actualizar la línea de tiempo actual
      function actualizarLineaTiempo() {
        // Forzar actualización del indicador de tiempo actual
        if (calendar.view.type.includes('timeGrid')) {
          calendar.render(); // Re-render para actualizar la posición de la línea
        }
      }
      
      // Actualizar la línea de tiempo cada minuto
      setInterval(actualizarLineaTiempo, 60000); // 60000 ms = 1 minuto
      
      // También actualizar cuando cambie la vista o se refresque el calendario
      calendar.on('viewDidMount', function() {
        setTimeout(actualizarLineaTiempo, 100);
      });

      document.getElementById('profesional-select').addEventListener('change', function() {
        calendar.refetchEvents();
        setTimeout(actualizarMarcadoresMiniCalendarios, 500);
      });
      document.getElementById('estado-select').addEventListener('change', function() {
        var estadoId = this.value;
        if (estadoId === 'todos') {
          calendar.setOption('events', 'citas_json.php');
        } else {
          calendar.setOption('events', function(fetchInfo, successCallback, failureCallback) {
            fetch('citas_json.php')
              .then(r => r.json())
              .then(data => {
                var filtrados = data.filter(ev => {
                  // Filtrar por estado_id si existe, si no por estado (nombre)
                  if (typeof ev.estado_id !== 'undefined') {
                    return String(ev.estado_id) === String(estadoId);
                  } else if (typeof ev.estado !== 'undefined') {
                    // El valor del select es el id, pero si el JSON tiene nombre, mapearlo
                    var nombres = {
                      '1': 'Reservado',
                      '2': 'Confirmado',
                      '3': 'Asistió',
                      '4': 'No asistió',
                      '5': 'Pendiente',
                      '6': 'En espera'
                    };
                    return ev.estado === nombres[estadoId];
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
          horaFinInput.style.backgroundColor = '#ffffff';
        } else {
          // Desactivar edición manual y recalcular
          horaFinInput.readOnly = true;
          horaFinInput.style.backgroundColor = '#f9fafb';
          manejarCambioServicio(); // Recalcular automáticamente
        }
      };

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

      // FECHA Y HORA DEFAULT SEGÚN CALENDARIO
      agendarBtn.onclick = function() {
        contextMenu.style.display = 'none';
        var fecha = '';
        var horaInicio = '';
        var horaFin = '';
        var now = new Date();

        if (lastDateClickInfo && lastDateClickInfo.start && lastDateClickInfo.end) {
          fecha = lastDateClickInfo.start.toISOString().split('T')[0];
          horaInicio = lastDateClickInfo.start.toTimeString().substring(0,5);
          horaFin = lastDateClickInfo.end.toTimeString().substring(0,5);
        } else if (lastDateClickInfo && lastDateClickInfo.date) {
          fecha = lastDateClickInfo.date.toISOString().split('T')[0];
          horaInicio = lastDateClickInfo.date.toTimeString().substring(0,5);
          var dateObj = new Date(lastDateClickInfo.date);
          dateObj.setMinutes(dateObj.getMinutes() + 30);
          horaFin = dateObj.toTimeString().substring(0,5);
        } else {
          fecha = now.toISOString().split('T')[0];
          horaInicio = now.toTimeString().substring(0,5);
          var dateObj = new Date(now);
          dateObj.setMinutes(dateObj.getMinutes() + 30);
          horaFin = dateObj.toTimeString().substring(0,5);
        }

        document.getElementById('agendarFecha').value = fecha;
        document.getElementById('agendarHoraInicio').value = horaInicio;
        document.getElementById('agendarHoraFin').value = horaFin;
        document.getElementById('agendarPaciente').value = '';
        document.getElementById('agendarServicio').value = '';
        var modalidadLabel = document.getElementById('modalidadSeleccionadaLabel');
        var modalidadNombre = '';
        var modalidadId = '';
        if (lastDateClickInfo && lastDateClickInfo.resource) {
          modalidadNombre = lastDateClickInfo.resource.title || '';
          modalidadId = lastDateClickInfo.resource.id || '';
        }
        modalidadLabel.textContent = modalidadNombre ? modalidadNombre : '(No seleccionado)';
        document.getElementById('agendarProfesional').value = modalidadId;
        cargarServiciosPorModalidad(modalidadId);
        document.getElementById('modalAgendar').style.display = 'flex';
        
        // Resetear la sección de información adicional
        document.getElementById('infoAdicionalBox').style.display = 'none';
        document.getElementById('iconInfoAdicional').style.transform = 'rotate(0deg)';
        document.getElementById('iconInfoAdicional').className = 'fas fa-chevron-down';
        
        setTimeout(function() {
          var pacienteInput = document.getElementById('agendarPaciente');
          if (pacienteInput) pacienteInput.focus();
        }, 200);
      };

      document.getElementById('formAgendar').onsubmit = function(e) {
        e.preventDefault();
        var fecha = document.getElementById('agendarFecha').value;
        var horaInicio = document.getElementById('agendarHoraInicio').value;
        var horaFin = document.getElementById('agendarHoraFin').value;
        var pacienteId = pacienteInput.dataset.pacienteId || '';
        var profesionalId = document.getElementById('agendarProfesional').value;
        var servicioId = document.getElementById('agendarServicio').value;
        var modalidadId = profesionalId;
        var estadoId = document.getElementById('agendarEstado').value;
        var notaInterna = document.getElementById('notaInterna').value;
        var notaPaciente = document.getElementById('notaPaciente').value;
        var atencionEspecial = document.getElementById('agendarAtencionEspecial').checked ? 1 : 0;

        if (!pacienteId) {
          alert('Selecciona o registra un paciente antes de agendar la cita.');
          return;
        }

        var formData = new FormData();
        formData.append('fecha', fecha);
        formData.append('hora_inicio', horaInicio);
        formData.append('hora_fin', horaFin);
        formData.append('paciente_id', pacienteId);
        formData.append('profesional_id', profesionalId);
        formData.append('servicio_id', servicioId);
        formData.append('modalidad_id', modalidadId);
        formData.append('estado_id', estadoId);
        formData.append('tipo', '');
        formData.append('nota_interna', notaInterna);
        formData.append('nota_paciente', notaPaciente);
        formData.append('atencion_especial', atencionEspecial);
        fetch('guardar_cita.php', {
          method: 'POST',
          body: formData
        })
        .then(r => r.json())
        .then(resp => {
          if (resp.success) {
            alert('Cita agendada correctamente.');
            document.getElementById('modalAgendar').style.display = 'none';
            calendar.refetchEvents();
            setTimeout(actualizarMarcadoresMiniCalendarios, 500);
          } else {
            alert('Error al guardar cita: ' + (resp.error || ''));
          }
        });
      };
    });

    // Funciones para botones del header
    function abrirModalAgendar() {
        // Aquí iría la lógica para abrir el modal de agendar
        alert('Función de agendar cita en desarrollo');
    }
    
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
</body>
</html>