<?php
session_start();
// Incluir configuración de la base de datos
require_once 'includes/db_config.php';

// Si se recibe un ID de usuario del portal, buscar sus datos y guardarlos en sesión
if (isset($_GET['portal_usuario_id']) && !empty($_GET['portal_usuario_id'])) {
    $portal_usuario_id = (int)$_GET['portal_usuario_id'];

    // --- Lógica de caché corregida ---
    // Prevenir que se vuelva a consultar si ya tenemos los datos del MISMO portal_usuario_id
    if (!isset($_SESSION['portal_paciente_data']) || !isset($_SESSION['portal_paciente_data']['lookup_portal_id']) || $_SESSION['portal_paciente_data']['lookup_portal_id'] !== $portal_usuario_id) {
        
        // 🔹 CORREGIDO: Buscar por la columna portal_usuario_id en la tabla portal_pacientes
        $sql = "SELECT id, nombre, apellido, telefono, correo, fecha_nacimiento FROM portal_pacientes WHERE portal_usuario_id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            error_log('Error en la preparación de la consulta de portal_pacientes: ' . $conn->error);
            unset($_SESSION['portal_paciente_data']);
        } else {
            $stmt->bind_param("i", $portal_usuario_id);
            $stmt->execute();
            
            // Usar bind_result para compatibilidad
            $stmt->bind_result($paciente_id, $nombre, $apellido, $telefono, $correo, $fecha_nacimiento);

            if ($stmt->fetch()) {
                // Guardar datos en sesión
                $_SESSION['portal_paciente_data'] = [
                    'paciente_id'      => $paciente_id,
                    'lookup_portal_id' => $portal_usuario_id,
                    'nombre_completo'  => trim($nombre . ' ' . $apellido),
                    'telefono'         => $telefono ?? '',
                    'email'            => $correo ?? '',
                    'fecha_nacimiento' => $fecha_nacimiento ? date('Y-m-d', strtotime($fecha_nacimiento)) : ''
                ];
                error_log("Paciente encontrado en sesión - Portal Usuario ID: {$portal_usuario_id}, Paciente ID: {$paciente_id}");
            } else {
                unset($_SESSION['portal_paciente_data']);
                error_log("No se encontró paciente con portal_usuario_id: " . $portal_usuario_id);
            }
            $stmt->close();
        }
    }
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
    <title>Hospital Angeles - Servicios de Imagenología</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1f2937;
            --secondary-color: #3b82f6;
            --accent-color: #0f5f85;
            --gradient-bg: linear-gradient(135deg, #0f5f85, #1f2937);
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #374151;
            background: var(--light-bg);
            padding-top: 80px;
        }

        /* Navigation */
        .navbar {
            background: var(--gradient-bg);
            box-shadow: var(--card-shadow);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            color: white !important;
            text-decoration: none;
        }

        .logo-text {
            font-size: 1.2rem;
            font-weight: 600;
            margin-left: 10px;
            color: white;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
        }

        /* Hero Section */
        .hero {
            background: var(--gradient-bg);
            color: white;
            padding: 100px 0;
            text-align: center;
            margin-top: -80px;
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
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .section-title p {
            font-size: 1.1rem;
            color: #6b7280;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Service Cards */
        .service-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            height: 100%;
            border: 2px solid transparent;
        }

        .service-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent-color);
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
        }

        .service-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .service-card p {
            color: #6b7280;
            margin-bottom: 1.5rem;
        }

        .service-count {
            background: #f8fafc;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
            color: var(--accent-color);
            font-weight: 600;
            display: inline-block;
        }

        /* Package Cards */
        .package-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            height: 100%;
            border: 2px solid #e5e7eb;
        }

        .package-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent-color);
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
        }

        .btn-primary:hover {
            background: #0a4a6a;
            transform: translateY(-2px);
        }

        .btn-outline-light {
            border-radius: 50px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
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
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="https://angelescuauhtemoc.com/wp-content/uploads/2020/09/logo-50-300x187.png" alt="Hospital Angeles" height="60">
                <div class="logo-text">IMAGENOLOGÍA</div>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    style="border: none; background: none; color: white;">
                <i class="fas fa-bars" style="color: white; font-size: 1.5rem;"></i>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#servicios">
                            <i class="fas fa-stethoscope me-1"></i>Servicios
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#paquetes">
                            <i class="fas fa-box me-1"></i>Paquetes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contacto.php">
                            <i class="fas fa-phone me-1"></i>Contacto
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-calendar me-1"></i>Admin
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="fade-in">Servicios de Imagenología</h1>
                    <p class="fade-in">Tecnología de vanguardia para diagnósticos precisos con el más alto estándar de calidad</p>
                    <div class="fade-in">
                        <a href="#servicios" class="btn btn-primary btn-lg px-4 py-3 me-3" 
                           style="border-radius: 50px; font-weight: 600;">
                            <i class="fas fa-calendar-alt me-2"></i>Reservar Cita
                        </a>
                        <a href="contacto.php" class="btn btn-outline-light btn-lg px-4 py-3" 
                           style="border-radius: 50px; font-weight: 600;">
                            <i class="fas fa-phone me-2"></i>Contacto
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
                <h2>Nuestros Servicios</h2>
                <p>Selecciona la modalidad de estudio que necesitas y agenda tu cita de manera fácil y rápida</p>
            </div>

            <div class="row" id="modalidades-grid">
                <!-- Las modalidades se cargarán dinámicamente aquí -->
            </div>
        </div>
    </section>

    <!-- Packages Section -->
    <section id="paquetes" class="services-section" style="background: white;">
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
    <footer style="background: var(--gradient-bg); color: white; padding: 40px 0; margin-top: 60px;">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <div class="d-flex align-items-center mb-3">
                        <img src="https://angelescuauhtemoc.com/wp-content/uploads/2020/09/logo-50-300x187.png" 
                             alt="Hospital Angeles" height="50" class="me-3">
                        <div>
                            <h5 style="margin: 0; font-weight: 700;">Hospital Angeles</h5>
                            <p style="margin: 0; font-size: 0.9rem; opacity: 0.8;">Imagenología</p>
                        </div>
                    </div>
                    <p style="opacity: 0.9; margin-bottom: 0;">
                        Tecnología de vanguardia para diagnósticos precisos con el más alto estándar de calidad médica.
                    </p>
                </div>
                <div class="col-lg-6 text-lg-end">
                    <h6 style="font-weight: 600; margin-bottom: 15px;">Contáctanos</h6>
                    <p style="margin: 5px 0; opacity: 0.9;">
                        <i class="fas fa-phone me-2"></i>+52 (55) 1234-5678
                    </p>
                    <p style="margin: 5px 0; opacity: 0.9;">
                        <i class="fas fa-envelope me-2"></i>contacto@hospitalangeles.com
                    </p>
                    <p style="margin: 5px 0; opacity: 0.9;">
                        <i class="fas fa-map-marker-alt me-2"></i>Ciudad de México, México
                    </p>
                </div>
            </div>
            <hr style="border-color: rgba(255,255,255,0.2); margin: 30px 0 20px;">
            <div class="text-center" style="opacity: 0.8;">
                <p style="margin: 0; font-size: 0.9rem;">
                    © 2025 Hospital Angeles - Todos los derechos reservados
                </p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Colores para las modalidades
        const modalidadColors = {
            'radiografia': 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
            'tomografia': 'linear-gradient(135deg, #f59e0b, #d97706)',
            'mastografia': 'linear-gradient(135deg, #ec4899, #db2777)',
            'sonografia': 'linear-gradient(135deg, #06b6d4, #0891b2)',
            'laboratorios': 'linear-gradient(135deg, #10b981, #059669)',
            'default': 'linear-gradient(135deg, #6b7280, #4b5563)'
        };

        // Iconos para las modalidades
        const modalidadIcons = {
            'radiografia': 'fas fa-x-ray',
            'resonancia': 'fas fa-brain', 
            'tomografia': 'fas fa-lungs',
            'mastografia': 'fas fa-heartbeat',
            'sonografia': 'fas fa-baby',
            'laboratorios': 'fas fa-flask',
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
            for (const [key, icon] of Object.entries(modalidadIcons)) {
                if (nombreLower.includes(key)) {
                    return icon;
                }
            }
            return modalidadIcons.default;
        }

        // Cargar modalidades
        async function cargarModalidades() {
            try {
                const response = await fetch('recursos_json.php');
                const modalidades = await response.json();
                
                const grid = document.getElementById('modalidades-grid');
                
                for (const modalidad of modalidades) {
                    // Obtener servicios de esta modalidad
                    const serviciosResponse = await fetch(`citas/servicios_por_modalidad.php?modalidad_id=${modalidad.id}`);
                    const servicios = await serviciosResponse.json();
                    
                    const color = getModalidadColor(modalidad.title);
                    const icon = getModalidadIcon(modalidad.title);
                    
                    const card = document.createElement('div');
                    card.className = 'col-lg-4 col-md-6 mb-4';
                    card.innerHTML = `
                        <div class="service-card fade-in" onclick="verModalidad(${modalidad.id}, '${modalidad.title.replace(/'/g, "\\'")}')">
                            <div class="service-icon" style="background: ${color};">
                                <i class="${icon}"></i>
                            </div>
                            <h3>${modalidad.title}</h3>
                            <p>Estudios especializados con equipos de última generación</p>
                            <div class="service-count">
                                ${servicios.length} servicio${servicios.length !== 1 ? 's' : ''} disponible${servicios.length !== 1 ? 's' : ''}
                            </div>
                        </div>
                    `;
                    grid.appendChild(card);
                }
            } catch (error) {
                console.error('Error cargando modalidades:', error);
            }
        }

        // Cargar paquetes (simulado por ahora)
        function cargarPaquetes() {
            const paquetes = [
                {
                    nombre: 'Paquete Básico',
                    descripcion: 'Estudios esenciales para chequeo general',
                    servicios: ['Radiografía de Tórax', 'Análisis de Sangre', 'Electrocardiograma'],
                    precio: '$2,500'
                },
                {
                    nombre: 'Paquete Completo',
                    descripcion: 'Evaluación integral con múltiples estudios',
                    servicios: ['Resonancia Magnética', 'Tomografía', 'Ultrasonido', 'Laboratorios'],
                    precio: '$8,500'
                },
                {
                    nombre: 'Paquete Premium',
                    descripcion: 'Lo más completo en diagnóstico por imagen',
                    servicios: ['Todos los estudios disponibles', 'Consulta especializada', 'Seguimiento'],
                    precio: '$12,500'
                }
            ];

            const grid = document.getElementById('paquetes-grid');
            
            paquetes.forEach((paquete, index) => {
                const card = document.createElement('div');
                card.className = 'col-lg-4 col-md-6 mb-4';
                card.innerHTML = `
                    <div class="package-card fade-in" onclick="verPaquete('${paquete.nombre}')" style="animation-delay: ${index * 0.1}s">
                        <div class="service-icon">
                            <i class="fas fa-gift"></i>
                        </div>
                        <h3>${paquete.nombre}</h3>
                        <p>${paquete.descripcion}</p>
                        <div class="service-count">
                            Desde ${paquete.precio}
                        </div>
                    </div>
                `;
                grid.appendChild(card);
            });
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
                        <div class="card shadow-sm fade-in" style="animation-delay: ${index * 0.1}s;">
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

        // Función para ver modalidad
        function verModalidad(id, nombre) {
            const urlParams = new URLSearchParams(window.location.search);
            const portalUsuarioId = urlParams.get('portal_usuario_id');

            // Redirigir a página de servicios de la modalidad
            let url = `modalidad.php?id=${id}&nombre=${encodeURIComponent(nombre)}`;
            if (portalUsuarioId) url += `&portal_usuario_id=${portalUsuarioId}`;
            window.location.href = url;
        }

        // Función para ver paquete
        function verPaquete(nombre) {
            // Redirigir a página de paquetes
            window.location.href = `paquetes.php?paquete=${encodeURIComponent(nombre)}`;
        }

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            cargarModalidades();
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