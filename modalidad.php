<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicios - Hospital Angeles</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1275a0;
            --secondary-color: #3b82f6;
            --accent-color: #0f5f85 100%;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #374151;
            background: var(--light-bg);
            padding-top: 100px;
        }

        /* Header */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
            font-size: 1.5rem;
        }

        .back-btn {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #2563eb;
            color: white;
            transform: translateY(-2px);
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #374151);
            color: white;
            padding: 3rem 0;
            margin-bottom: 3rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        /* Service Cards */
        .service-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            height: 100%;
        }

        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -4px rgba(0, 0, 0, 0.15);
            border-color: var(--secondary-color);
        }

        .service-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .service-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .service-info h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .service-duration {
            color: #6b7280;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .service-description {
            color: #6b7280;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .service-details {
            background: var(--light-bg);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .detail-item:last-child {
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 500;
            color: var(--primary-color);
        }

        .detail-value {
            color: #6b7280;
        }

        .book-btn {
            background: linear-gradient(135deg, var(--accent-color), #0f5f85 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .book-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px -4px #0f5f85 100%;
        }

        /* Loading State */
        .loading-card {
            background: white;
            border-radius: 16px;
            padding: 3rem 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            text-align: center;
        }

        .spinner-border {
            color: var(--secondary-color);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
        }

        .empty-state i {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: #6b7280;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
            }
            
            .page-header p {
                font-size: 1rem;
            }

            .service-card {
                padding: 1.5rem;
            }

            .service-header {
                flex-direction: column;
                text-align: center;
            }

            .service-icon {
                margin-right: 0;
                margin-bottom: 1rem;
            }
        }

        /* Animation */
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="cliente.php">
                <img src="images/logo.png" alt="Hospital Angeles" height="50" class="me-3">
                <div>
                    <div style="font-size: 1.2rem; font-weight: 700;">Hospital Angeles</div>
                    <div style="font-size: 0.8rem; color: #6b7280; font-weight: 400;">Imagenología</div>
                </div>
            </a>
            
            <a href="cliente.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Regresar
            </a>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 id="modalidad-titulo">Cargando servicios...</h1>
                    <p id="modalidad-descripcion">Selecciona el servicio que necesitas</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div id="modalidad-icono" class="d-inline-block" style="font-size: 4rem; opacity: 0.7;">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <div class="container mb-5">
        <div class="row" id="servicios-container">
            <!-- Loading State -->
            <div class="col-12" id="loading-state">
                <div class="loading-card">
                    <div class="spinner-border mb-3" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mb-0">Cargando servicios disponibles...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Obtener parámetros de la URL
        const urlParams = new URLSearchParams(window.location.search);
        const modalidadId = urlParams.get('id');
        const modalidadNombre = urlParams.get('nombre');

        // Colores e iconos para modalidades
        const modalidadConfig = {
            'radiografia': { 
                color: 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
                icon: 'fas fa-x-ray',
                descripcion: 'Estudios de rayos X con equipos digitales de alta resolución'
            },
            'resonancia': { 
                color: 'linear-gradient(135deg, #6b7280, #4b5563)',
                icon: 'fas fa-brain',
                descripcion: 'Resonancia magnética con tecnología de vanguardia'
            },
            'tomografia': { 
                color: 'linear-gradient(135deg, #f59e0b, #d97706)',
                icon: 'fas fa-lungs',
                descripcion: 'Tomografía computarizada multicorte'
            },
            'mastografia': { 
                color: 'linear-gradient(135deg, #ec4899, #db2777)',
                icon: 'fas fa-heartbeat',
                descripcion: 'Mastografía digital para detección temprana'
            },
            'sonografia': { 
                color: 'linear-gradient(135deg, #06b6d4, #0891b2)',
                icon: 'fas fa-baby',
                descripcion: 'Ultrasonido con equipos de última generación'
            },
            'laboratorios': { 
                color: 'linear-gradient(135deg, #10b981, #059669)',
                icon: 'fas fa-flask',
                descripcion: 'Análisis clínicos con tecnología automatizada'
            },
            'default': { 
                color: 'linear-gradient(135deg, #6b7280, #4b5563)',
                icon: 'fas fa-stethoscope',
                descripcion: 'Servicios especializados de diagnóstico'
            }
        };

        function getModalidadConfig(nombre) {
            const nombreLower = nombre.toLowerCase();
            for (const [key, config] of Object.entries(modalidadConfig)) {
                if (nombreLower.includes(key)) {
                    return config;
                }
            }
            return modalidadConfig.default;
        }

        // Cargar servicios de la modalidad
        async function cargarServicios() {
            try {
                if (!modalidadId || !modalidadNombre) {
                    mostrarError('Parámetros de modalidad no válidos');
                    return;
                }

                // Configurar header de la página
                const config = getModalidadConfig(modalidadNombre);
                document.getElementById('modalidad-titulo').textContent = modalidadNombre;
                document.getElementById('modalidad-descripcion').textContent = config.descripcion;
                document.getElementById('modalidad-icono').innerHTML = `<i class="${config.icon}"></i>`;

                // Cargar servicios
                const response = await fetch(`citas/servicios_por_modalidad.php?modalidad_id=${modalidadId}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const servicios = await response.json();
                
                // Ocultar loading
                document.getElementById('loading-state').style.display = 'none';
                
                if (servicios.length === 0) {
                    mostrarEstadoVacio();
                    return;
                }

                mostrarServicios(servicios, config);
                
            } catch (error) {
                console.error('Error cargando servicios:', error);
                mostrarError('Error al cargar los servicios. Por favor, inténtalo de nuevo.');
            }
        }

        function mostrarServicios(servicios, config) {
            const container = document.getElementById('servicios-container');
            
            servicios.forEach((servicio, index) => {
                const duracionTexto = servicio.duracion_minutos ? 
                    `${servicio.duracion_minutos} minutos` : 'Consultar';

                const precio = servicio.precio || 'Consultar';
                
                const card = document.createElement('div');
                card.className = 'col-lg-6 col-xl-4 mb-4';
                card.innerHTML = `
                    <div class="service-card fade-in" style="animation-delay: ${index * 0.1}s">
                        <div class="service-header">
                            <div class="service-icon" style="background: ${config.color};">
                                <i class="${config.icon}"></i>
                            </div>
                            <div class="service-info">
                                <h3>${servicio.nombre}</h3>
                                <div class="service-duration">
                                    <i class="fas fa-clock"></i>
                                    ${duracionTexto}
                                </div>
                            </div>
                        </div>
                        
                        <div class="service-description">
                            ${servicio.descripcion || 'Servicio especializado con equipos de última generación para obtener resultados precisos y confiables.'}
                        </div>
                        
                        <div class="service-details">
                            <div class="detail-item">
                                <span class="detail-label">Duración:</span>
                                <span class="detail-value">${duracionTexto}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Preparación:</span>
                                <span class="detail-value">Consultar indicaciones</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Precio:</span>
                                <span class="detail-value">${precio}</span>
                            </div>
                        </div>
                        
                        <button class="book-btn" onclick="reservarServicio(${servicio.id}, '${servicio.nombre.replace(/'/g, "\\'")}', ${modalidadId}, '${modalidadNombre.replace(/'/g, "\\'")}')">
                            <i class="fas fa-calendar-plus me-2"></i>
                            Reservar Cita
                        </button>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        function mostrarEstadoVacio() {
            const container = document.getElementById('servicios-container');
            container.innerHTML = `
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No hay servicios disponibles</h3>
                        <p>No se encontraron servicios para esta modalidad en este momento.</p>
                        <a href="cliente.php" class="btn btn-primary mt-3">
                            <i class="fas fa-arrow-left me-2"></i>
                            Regresar al inicio
                        </a>
                    </div>
                </div>
            `;
        }

        function mostrarError(mensaje) {
            const container = document.getElementById('servicios-container');
            container.innerHTML = `
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Error</h3>
                        <p>${mensaje}</p>
                        <a href="cliente.php" class="btn btn-primary mt-3">
                            <i class="fas fa-arrow-left me-2"></i>
                            Regresar al inicio
                        </a>
                    </div>
                </div>
            `;
        }

        // Función para reservar servicio
        function reservarServicio(servicioId, servicioNombre, modalidadId, modalidadNombre) {
            // Redirigir a página de reserva
            const params = new URLSearchParams({
                tipo: 'servicio',
                servicio_id: servicioId,
                servicio_nombre: servicioNombre,
                modalidad_id: modalidadId,
                modalidad_nombre: modalidadNombre
            });
            
            window.location.href = `reservar.php?${params.toString()}`;
        }

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            cargarServicios();
        });
    </script>
</body>
</html>