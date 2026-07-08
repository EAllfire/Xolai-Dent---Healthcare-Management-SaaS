<?php
session_start();
// Incluir configuración de la base de datos
require_once 'includes/db.php';

$medico_asignado_id = isset($_GET['medico_id']) ? (int)$_GET['medico_id'] : 0;

// Si se recibe un ID de usuario del portal, buscar sus datos y guardarlos en sesión
if (isset($_GET['portal_usuario_id']) && !empty($_GET['portal_usuario_id'])) {
    $portal_usuario_id = (int)$_GET['portal_usuario_id'];

    // --- Lógica de caché corregida ---
    // Prevenir que se vuelva a consultar si ya tenemos los datos del MISMO portal_usuario_id
    if (!isset($_SESSION['portal_paciente_data']) || !isset($_SESSION['portal_paciente_data']['lookup_portal_id']) || $_SESSION['portal_paciente_data']['lookup_portal_id'] !== $portal_usuario_id) {
        
        // 🔹 CORREGIDO: Buscar por la columna portal_usuario_id en la tabla portal_pacientes
        $sql = "SELECT id, nombre, apellido, telefono, correo, fecha_nacimiento, usuario_id FROM portal_pacientes WHERE portal_usuario_id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            error_log('Error en la preparación de la consulta de portal_pacientes: ' . $conn->error);
            unset($_SESSION['portal_paciente_data']);
        } else {
            $stmt->bind_param("i", $portal_usuario_id);
            $stmt->execute();
            
            // Usar bind_result para compatibilidad
            $stmt->bind_result($paciente_id, $nombre, $apellido, $telefono, $correo, $fecha_nacimiento, $usuario_id_medico);

            if ($stmt->fetch()) {
                // Guardar datos en sesión
                $_SESSION['portal_paciente_data'] = [
                    'paciente_id'      => $paciente_id,
                    'lookup_portal_id' => $portal_usuario_id,
                    'nombre_completo'  => trim($nombre . ' ' . $apellido),
                    'telefono'         => $telefono ?? '',
                    'email'            => $correo ?? '',
                    'fecha_nacimiento' => $fecha_nacimiento ? date('Y-m-d', strtotime($fecha_nacimiento)) : '',
                    'medico_id'        => $usuario_id_medico // Guardamos el ID del médico asignado
                ];
                
                // Si encontramos al médico asociado al paciente, lo usamos para filtrar la tienda
                if ($usuario_id_medico) $medico_asignado_id = $usuario_id_medico;
                
                error_log("Paciente encontrado en sesión - Portal Usuario ID: {$portal_usuario_id}, Paciente ID: {$paciente_id}");
            } else {
                unset($_SESSION['portal_paciente_data']);
                error_log("No se encontró paciente con portal_usuario_id: " . $portal_usuario_id);
            }
            $stmt->close();
        }
    }
} elseif (isset($_SESSION['portal_paciente_data']['medico_id']) && $medico_asignado_id == 0) {
    // Recuperar médico de la sesión si ya estaba logueado y no vino por GET
    $medico_asignado_id = $_SESSION['portal_paciente_data']['medico_id'];
}

// Cerrar la conexión solo si fue establecida exitosamente
if ($conn && $conn instanceof mysqli) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Citas - Consultorio Médico San José</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #ffffff;
            --secondary-color: #a0a0a0;
            --accent-color: #2979ff;
            --gradient-bg: #000000;
            --light-bg: #000000;
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #e5e7eb;
            background: var(--light-bg);
            padding-top: 0;
        }

        /* Navigation */
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
            filter: drop-shadow(1px 1px 2px rgba(0,0,0,0.1)) brightness(1.1);
        }
        
        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: white;
            letter-spacing: 1px;
        }

        .logo-section-center {
            text-align: center;
        }

        .nav-link {
            color: #a0a0a0 !important;
            font-weight: 500;
            font-size: 15px;
            padding: 8px 16px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        .nav-link:hover {
            color: white !important;
            background-color: rgba(255, 255, 255, 0.12);
        }

        .contact-info-header {
            font-size: 12px;
            color: #9ca3af;
            text-align: right;
            line-height: 1.4;
            margin-right: 15px;

            }
        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: white !important;
            background: rgba(100, 100, 100, 0.15);
        }

        /* Hero Section */
        .hero {
            background: radial-gradient(circle at center, #111 0%, #000 100%);
            color: white;
            padding: 140px 0 100px 0;
            text-align: center;
            margin-top: 0;
            border-bottom: 1px solid rgba(41, 121, 255, 0.1);
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .hero p {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }

        /* Sections */
        .services-section {
            padding: 80px 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 1rem;
        }

        .section-title p {
            font-size: 1.1rem;
            color: #9ca3af;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Service Cards */
        .service-card {
            background: #0a0a0a;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            height: 100%;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .service-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent-color);
            box-shadow: 0 0 20px rgba(41, 121, 255, 0.2);
        }

        .service-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
            background: linear-gradient(135deg, #111 0%, #222 100%);
            border: 1px solid rgba(41, 121, 255, 0.3);
        }

        .service-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 1rem;
        }

        .service-card p {
            color: #9ca3af;
            margin-bottom: 1.5rem;
        }

        .service-count {
            background: rgba(41, 121, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
            color: var(--accent-color);
            font-weight: 600;
            display: inline-block;
            border: 1px solid rgba(41, 121, 255, 0.2);
        }

        /* Package Cards */
        .package-card {
            background: #0a0a0a;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            height: 100%;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .package-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent-color);
            box-shadow: 0 0 20px rgba(41, 121, 255, 0.2);
        }

        /* Animations */
        .fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Buttons */
        .btn-primary {
            background: var(--accent-color);
            border: none;
            border-radius: 50px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.3);
        }

        .btn-primary:hover {
            background: #2962ff;
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(41, 121, 255, 0.6);
            color: white;
        }

        .btn-outline-light {
            border-radius: 50px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid white;
            color: white;
        }

        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .service-card, .package-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
     <!-- Header Mejorado (Estilo index.php) -->
    <header class="main-header">
        <div class="header-left">
            <div class="header-logo">
                <img src="images/Xolai.png" alt="Xolai Logo" class="header-logo-img">
                <span class="header-title">Xolai</span>
            </div>
        </div>
        
        <div class="header-center">
            <div class="logo-section-center">
                <div class="header-title">SISTEMA DE GESTIÓN DE CITAS</div>
            </div>
        </div>
        
        <div class="header-right">
            <div class="contact-info-header d-none d-md-block">
                <div><i class="fas fa-map-marker-alt me-1"></i> Calle 12 335, entre guerrero y rayón, Zona Centro, 31500 Cuauhtémoc, Chih.</div>
                <div><i class="fas fa-phone me-1"></i> +52 625 125 70 48</div>
            </div>
            <nav class="d-flex">
                <a href="#servicios" class="nav-link">Servicios</a>
                <a href="#paquetes" class="nav-link">Paquetes</a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="fade-in">Dent Clínica de especialidades Dentales</h1>
                    <p class="fade-in">Tu salud es nuestra prioridad. Agenda citas y consultas con la mejor tecnología y atención.</p>
                    <div class="fade-in">
                        <a href="#servicios" class="btn btn-primary btn-lg px-4 py-3 me-3" 
                           style="border-radius: 50px; font-weight: 600;">
                            <i class="fas fa-calendar-alt me-2"></i>Reservar Cita
                        </a>
                        
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="servicios" class="services-section">
        <div class="container">
            <div class="section-title">
                <h2>Catálogo de Servicios</h2>
                <p>Selecciona el estudio que necesitas y agenda tu cita de manera fácil y rápida</p>
            </div>

            <div class="row" id="servicios-grid">
                <!-- Los servicios se cargarán dinámicamente aquí -->
            </div>
        </div>
    </section>

    <!-- Packages Section -->
    <section id="paquetes" class="services-section" style="background: #050505;">
        <div class="container">
            <div class="section-title">
                <h2>Paquetes Especiales</h2>
                <p>Paquetes integrales con descuentos especiales para múltiples estudios</p>
            </div>

            <div class="row" id="paquetes-grid">
                <!-- Los paquetes se cargarán dinámicamente aquí -->
            </div>
        </div>
    </section>

    <!-- My Appointments Section -->
    <section id="mis-citas" class="services-section">
        <div class="container">
            <div class="section-title">
                <h2>Mis Citas</h2>
                <p>Aquí puedes ver y gestionar tus citas agendadas.</p>
            </div>

            <div class="row" id="citas-cliente-grid">
                <!-- Las citas del cliente se cargarán dinámicamente aquí -->
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer style="background: #000000; color: white; padding: 40px 0; margin-top: 60px; border-top: 1px solid rgba(41, 121, 255, 0.1);">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <div class="d-flex align-items-center mb-3">
                        <img src="images/Xolai.png" alt="Xolai Logo" height="45" class="me-3">
                        <div>
                            <h5 style="margin: 0; font-weight: 700;"> </h5>
                            <p style="margin: 0; font-size: 0.9rem; opacity: 0.8;">Atención Dental Integral</p>
                        </div>
                    </div>
                    <p style="opacity: 0.9; margin-bottom: 0;">
                        Comprometidos con la excelencia y la agilidad en la gestión de tu salud a través de nuestro sistema de citas.
                    </p>
                </div>
                <div class="col-lg-6 text-lg-end">
                    <h6 style="font-weight: 600; margin-bottom: 15px;">Contáctanos</h6>
                    <p style="margin: 5px 0; opacity: 0.9;">
                        <i class="fas fa-phone me-2"></i>+52 625 125 70 48

                    </p>
                    
                    <p style="margin: 5px 0; opacity: 0.9;">
                        <i class="fas fa-map-marker-alt me-2"></i>Calle 12 335, entre guerrero y rayón, Zona Centro, 31500 Cuauhtémoc, Chih.
                    </p>
                </div>
            </div>
            <hr style="border-color: rgba(255,255,255,0.2); margin: 30px 0 20px;">
            <div class="text-center" style="opacity: 0.8;">
                <p style="margin: 0; font-size: 0.9rem;">
                    © 2026 Dent Clínica de especialidades Dentales - Todos los derechos reservados
                </p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ID del médico para filtrar (inyectado desde PHP)
        const medicoFiltroId = <?php echo json_encode($medico_asignado_id); ?>;

        // Colores para las modalidades
        const modalidadColors = {
            'radiograf': 'linear-gradient(135deg, #1a1a1a, #333)',
            'tomografia': 'linear-gradient(135deg, #1a1a1a, #333)',
            'mastografia': 'linear-gradient(135deg, #1a1a1a, #333)',
            'sonografia': 'linear-gradient(135deg, #1a1a1a, #333)',
            'laboratorio': 'linear-gradient(135deg, #1a1a1a, #333)',
            'default': 'linear-gradient(135deg, #1a1a1a, #333)'
        };

        // Iconos para las modalidades
        const modalidadIcons = {
            'radiograf': 'fas fa-diagnoses', // Nuevo ícono para Radiología
            'resonancia': 'fas fa-brain',
            'tomograf': 'fas fa-lungs-virus', // Un ícono más representativo para tomografía
            'mastograf': 'fas fa-venus', // Nuevo ícono para Mastografía
            'sonograf': 'fas fa-wave-square', // Ícono para Sonografía/Ultrasonido
            'laboratorio': 'fas fa-flask',
            'default': 'fas fa-stethoscope'
        };

        // Función para obtener color de modalidad
        function getModalidadColor(nombre) {
            const nombreLower = nombre.toLowerCase();
            for (const [key, color] of Object.entries(modalidadColors)) {
                if (nombreLower.includes(key)) {
                    return color;
                }
            }
            return modalidadColors.default;
        }

        // Función para obtener ícono de modalidad
        function getModalidadIcon(nombre) {
            const nombreLower = nombre.toLowerCase();
            // Casos especiales para cubrir múltiples nombres
            if (nombreLower.includes('ultrasonido')) return modalidadIcons['sonograf'];
            if (nombreLower.includes('radiolog')) return modalidadIcons['radiograf'];

            for (const [key, icon] of Object.entries(modalidadIcons)) {
                if (nombreLower.includes(key)) {
                    return icon;
                }
            }
            return modalidadIcons.default;
        }

        // Cargar SERVICIOS directamente (Reemplaza a cargarModalidades)
        async function cargarServicios() {
            try {
                // Usamos el nuevo endpoint publico, pasando el ID del médico si existe
                let url = 'servicios_publicos.php';
                if (medicoFiltroId > 0) {
                    url += `?medico_id=${medicoFiltroId}`;
                }
                
                const response = await fetch(url);
                const servicios = await response.json();
                
                const grid = document.getElementById('servicios-grid');
                grid.innerHTML = '';

                if (servicios.length === 0) {
                    grid.innerHTML = '<div class="col-12 text-center text-muted">No hay servicios disponibles por el momento.</div>';
                    return;
                }
                
                const urlParams = new URLSearchParams(window.location.search);
                const portalUsuarioId = urlParams.get('portal_usuario_id');
                
                for (const servicio of servicios) {
                    const color = getModalidadColor(servicio.modalidad_nombre || 'default');
                    const icon = getModalidadIcon(servicio.modalidad_nombre || 'default');
                    const precioFormatted = servicio.precio ? `$${parseFloat(servicio.precio).toLocaleString('es-MX')}` : 'Consultar';
                    
                    // Construir enlace de reserva directo para el servicio
                    let reservarUrl = `reservar.php?tipo=servicio&servicio_id=${servicio.id}&servicio_nombre=${encodeURIComponent(servicio.nombre)}&modalidad_id=${servicio.modalidad_id || ''}`;
                    if (portalUsuarioId) reservarUrl += `&portal_usuario_id=${portalUsuarioId}`;
                    if (medicoFiltroId > 0) reservarUrl += `&medico_id=${medicoFiltroId}`;
                    
                    const card = document.createElement('div');
                    card.className = 'col-lg-4 col-md-6 mb-4';
                    card.innerHTML = `
                        <div class="service-card fade-in" onclick="window.location.href='${reservarUrl}'">
                            <div class="service-icon" style="background: ${color};">
                                <i class="${icon}" style="color: #2979ff; text-shadow: 0 0 10px rgba(41, 121, 255, 0.5);"></i>
                            </div>
                            <h3>${servicio.nombre}</h3>
                            <p>${servicio.descripcion || servicio.modalidad_nombre || 'Estudio de imagenología'}</p>
                            <div class="service-count" style="color: #2979ff; border-color: #2979ff;">
                                ${precioFormatted}
                            </div>
                        </div>
                    `;
                    grid.appendChild(card);
                }
            } catch (error) {
                console.error('Error cargando modalidades:', error);
                document.getElementById('servicios-grid').innerHTML = '<div class="col-12 text-center text-danger">Error cargando servicios.</div>';
            }
        }

        // Cargar paquetes desde la base de datos
        async function cargarPaquetes() {
            const grid = document.getElementById('paquetes-grid');
            grid.innerHTML = ''; // Limpiar el grid

            try {
                let url = 'citas/paquetes_json.php';
                if (medicoFiltroId > 0) {
                    url += `?medico_id=${medicoFiltroId}`;
                }
                
                const response = await fetch(url);
                const paquetes = await response.json();

                if (!Array.isArray(paquetes) || paquetes.length === 0) {
                    grid.innerHTML = '<div class="col-12 text-center"><p>No hay paquetes especiales disponibles por el momento.</p></div>';
                    return;
                }

                paquetes.forEach((paquete, index) => {
                    const card = document.createElement('div');
                    card.className = 'col-lg-4 col-md-6 mb-4';
                    card.innerHTML = `
                        <div class="package-card fade-in" onclick="verPaquete(${paquete.id})" style="animation-delay: ${index * 0.1}s;">
                            <div class="service-icon" style="background: #1a1a1a; border: 1px solid rgba(41, 121, 255, 0.3);">
                                <i class="fas fa-gift" style="color: #2979ff; text-shadow: 0 0 10px rgba(41, 121, 255, 0.5);"></i>
                            </div>
                            <h3>${paquete.nombre}</h3>
                            <p>${paquete.descripcion || 'Paquete de estudios especializados.'}</p>
                            <div class="service-count">
                                Desde $${parseFloat(paquete.precio).toLocaleString('es-MX', { minimumFractionDigits: 2 })}
                            </div>
                        </div>
                    `;
                    grid.appendChild(card);
                });

            } catch (error) {
                console.error('Error cargando paquetes:', error);
                grid.innerHTML = '<div class="col-12 text-center"><p class="text-danger">Error al cargar los paquetes.</p></div>';
            }
        }

        // Cargar citas del cliente
        async function cargarCitasCliente() {
            try {
                // Inyectar el ID de paciente desde la sesión de PHP
                const clienteId = <?php echo $_SESSION['portal_paciente_data']['paciente_id'] ?? 'null'; ?>;

                if (!clienteId) {
                    console.log("ID de cliente no disponible. Ocultando sección de citas.");
                    document.getElementById('mis-citas').style.display = 'none';
                    return;
                }

                const response = await fetch(`citas_cliente_json.php?cliente_id=${clienteId}`);
                
                if (!response.ok) {
                    // Si el servidor responde con un error (4xx, 5xx), lanzar una excepción
                    throw new Error(`Error del servidor: ${response.status} ${response.statusText}`);
                }

                const citas = await response.json();
                
                const grid = document.getElementById('citas-cliente-grid');
                grid.innerHTML = ''; // Limpiar antes de cargar

                // Verificar si la respuesta es un array y no está vacío
                if (!Array.isArray(citas) || citas.length === 0) {
                    // Si no es un array, podría ser un objeto de error del backend
                    if (citas && citas.success === false) {
                        console.error('Error del backend:', citas.error);
                    }
                    grid.innerHTML = '<div class="col-12 text-center"><p>No tienes citas agendadas.</p></div>';
                    return;
                }
                
                citas.forEach((cita, index) => {
                    const card = document.createElement('div');
                    card.className = 'col-lg-6 col-md-12 mb-4';
                    card.innerHTML = `
                        <div class="card shadow-sm fade-in" style="animation-delay: ${index * 0.1}s; background: #0a0a0a; border: 1px solid #333; color: #fff;">
                            <div class="card-body">
                                <h5 class="card-title">${cita.servicio_nombre}</h5>
                                <h6 class="card-subtitle mb-2 text-muted">${cita.modalidad_nombre}</h6>
                                <p class="card-text">
                                    <strong>Fecha:</strong> ${cita.fecha}<br>
                                    <strong>Hora:</strong> ${cita.hora_inicio} - ${cita.hora_fin}<br>
                                    <strong>Estado:</strong> <span class="badge bg-primary">${cita.estado_nombre}</span>
                                </p>
                                <a href="modificar_cita.php?id=${cita.id}" class="btn btn-sm btn-primary me-2">Modificar</a>
                                <a href="#" onclick="confirmarEliminacion(${cita.id})" class="btn btn-sm btn-danger">Eliminar</a>
                            </div>
                        </div>
                    `;
                    grid.appendChild(card);
                });
            } catch (error) {
                console.error('Error cargando citas del cliente:', error);
                document.getElementById('citas-cliente-grid').innerHTML = '<div class="col-12 text-center"><p class="text-danger">Error al cargar tus citas. Por favor, intenta de nuevo más tarde.</p></div>';
            }
        }

        function confirmarEliminacion(citaId) {
            if (confirm('¿Estás seguro de que deseas eliminar esta cita?')) {
                window.location.href = `eliminar_cita_cliente.php?id=${citaId}`;
            }
        }

        // Función para ver paquete
        function verPaquete(paqueteId) {
            const urlParams = new URLSearchParams(window.location.search);
            const portalUsuarioId = urlParams.get('portal_usuario_id');
            let url = `reservar.php?tipo=paquete&paquete_id=${paqueteId}`;
            if (portalUsuarioId) url += `&portal_usuario_id=${portalUsuarioId}`;
            if (medicoFiltroId > 0) url += `&medico_id=${medicoFiltroId}`;
            window.location.href = url;
        }

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            cargarServicios(); // Ahora carga servicios directamente
            cargarPaquetes();
            cargarCitasCliente(); // Cargar citas del cliente al iniciar

            // Smooth scroll para navegación
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>