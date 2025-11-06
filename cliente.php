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
    
    <link href="css/cliente.css" rel="stylesheet">
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

        // Función para ver modalidad
        function verModalidad(id, nombre) {
            // Redirigir a página de servicios de la modalidad
            window.location.href = `modalidad.php?id=${id}&nombre=${encodeURIComponent(nombre)}`;
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
</body>
</html>