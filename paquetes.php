<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paquetes - Hospital Angeles</title>
    
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
            --accent-color:#0f5f85 100%;
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
            background: linear-gradient(135deg, var(--accent-color), #059669);
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

        /* Package Cards */
        .package-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            transition: all 0.4s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .package-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .package-card:hover::before {
            transform: scaleX(1);
        }

        .package-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.2);
            border-color: var(--accent-color);
        }

        .package-card.featured {
            background: linear-gradient(135deg, var(--accent-color), #0f5f85 100%);
            color: white;
            transform: scale(1.05);
        }

        .package-card.featured::before {
            background: white;
        }

        .package-card.featured:hover {
            transform: scale(1.05) translateY(-8px);
        }

        .package-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .package-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 1rem;
            background: linear-gradient(135deg, var(--accent-color), #0f5f85 100%);
        }

        .package-card.featured .package-icon {
            background: rgba(255, 255, 255, 0.2);
        }

        .package-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .package-card.featured .package-title {
            color: white;
        }

        .package-subtitle {
            color: #6b7280;
            font-size: 1rem;
            margin-bottom: 1.5rem;
        }

        .package-card.featured .package-subtitle {
            color: rgba(255, 255, 255, 0.9);
        }

        .package-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--accent-color);
            text-align: center;
            margin-bottom: 2rem;
        }

        .package-card.featured .package-price {
            color: white;
        }

        .package-features {
            list-style: none;
            padding: 0;
            margin-bottom: 2rem;
        }

        .package-features li {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .package-features li i {
            color: var(--accent-color);
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .package-card.featured .package-features li i {
            color: rgba(255, 255, 255, 0.9);
        }

        .package-card.featured .package-features li {
            color: white;
        }

        .book-btn {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 1.1rem;
        }

        .book-btn:hover {
            background: #0f5f85 100%;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px -4px #144458ff 100%;
        }

        .package-card.featured .book-btn {
            background: white;
            color: var(--accent-color);
        }

        .package-card.featured .book-btn:hover {
            background: #f9fafb;
            color: #0f5f85 100%;
        }

        .popular-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* Info Section */
        .info-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .info-section h3 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .info-section p {
            color: #6b7280;
            line-height: 1.7;
        }

        .info-list {
            list-style: none;
            padding: 0;
        }

        .info-list li {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
            color: #6b7280;
        }

        .info-list li i {
            color: var(--accent-color);
            margin-right: 0.75rem;
            margin-top: 0.25rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
            }
            
            .page-header p {
                font-size: 1rem;
            }

            .package-card {
                padding: 2rem;
            }

            .package-card.featured {
                transform: none;
            }

            .package-card.featured:hover {
                transform: translateY(-8px);
            }

            .package-price {
                font-size: 2rem;
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
                    <h1>Paquetes Especiales</h1>
                    <p>Ahorra con nuestros paquetes integrales diseñados para tu bienestar</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div style="font-size: 4rem; opacity: 0.7;">
                        <i class="fas fa-gift"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Packages Section -->
    <div class="container mb-5">
        <div class="row">
            <!-- Paquete Básico -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="package-card fade-in">
                    <div class="package-header">
                        <div class="package-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="package-title">Paquete Básico</div>
                        <div class="package-subtitle">Chequeo General</div>
                    </div>
                    
                    <div class="package-price">$2,500</div>
                    
                    <ul class="package-features">
                        <li><i class="fas fa-check"></i>Radiografía de Tórax</li>
                        <li><i class="fas fa-check"></i>Análisis de Sangre Completo</li>
                        <li><i class="fas fa-check"></i>Electrocardiograma</li>
                        <li><i class="fas fa-check"></i>Consulta General</li>
                        <li><i class="fas fa-check"></i>Resultados en 24hrs</li>
                    </ul>
                    
                    <button class="book-btn" onclick="reservarPaquete('basico')">
                        <i class="fas fa-calendar-plus me-2"></i>
                        Reservar Paquete
                    </button>
                </div>
            </div>

            <!-- Paquete Completo -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="package-card featured fade-in" style="animation-delay: 0.2s">
                    <div class="popular-badge">Más Popular</div>
                    <div class="package-header">
                        <div class="package-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="package-title">Paquete Completo</div>
                        <div class="package-subtitle">Evaluación Integral</div>
                    </div>
                    
                    <div class="package-price">$7,500</div>
                    
                    <ul class="package-features">
                        <li><i class="fas fa-check"></i>Resonancia Magnética</li>
                        <li><i class="fas fa-check"></i>Tomografía Computarizada</li>
                        <li><i class="fas fa-check"></i>Ultrasonido Completo</li>
                        <li><i class="fas fa-check"></i>Laboratorios Completos</li>
                        <li><i class="fas fa-check"></i>Electrocardiograma</li>
                        <li><i class="fas fa-check"></i>Consulta Especializada</li>
                        <li><i class="fas fa-check"></i>Resultados en 48hrs</li>
                    </ul>
                    
                    <button class="book-btn" onclick="reservarPaquete('completo')">
                        <i class="fas fa-calendar-plus me-2"></i>
                        Reservar Paquete
                    </button>
                </div>
            </div>

            <!-- Paquete Premium -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="package-card fade-in" style="animation-delay: 0.4s">
                    <div class="package-header">
                        <div class="package-icon">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div class="package-title">Paquete Premium</div>
                        <div class="package-subtitle">Diagnóstico Completo</div>
                    </div>
                    
                    <div class="package-price">$12,500</div>
                    
                    <ul class="package-features">
                        <li><i class="fas fa-check"></i>Todos los estudios disponibles</li>
                        <li><i class="fas fa-check"></i>Mastografía Digital</li>
                        <li><i class="fas fa-check"></i>Densitometría Ósea</li>
                        <li><i class="fas fa-check"></i>Laboratorios Especializados</li>
                        <li><i class="fas fa-check"></i>Consulta con 2 especialistas</li>
                        <li><i class="fas fa-check"></i>Seguimiento por 6 meses</li>
                        <li><i class="fas fa-check"></i>Resultados prioritarios</li>
                    </ul>
                    
                    <button class="book-btn" onclick="reservarPaquete('premium')">
                        <i class="fas fa-calendar-plus me-2"></i>
                        Reservar Paquete
                    </button>
                </div>
            </div>
        </div>

        <!-- Info Section -->
        <div class="row mt-5">
            <div class="col-lg-6 mb-4">
                <div class="info-section fade-in" style="animation-delay: 0.6s">
                    <h3><i class="fas fa-info-circle me-2 text-primary"></i>¿Por qué elegir un paquete?</h3>
                    <ul class="info-list">
                        <li>
                            <i class="fas fa-dollar-sign"></i>
                            <span><strong>Ahorro significativo:</strong> Hasta 30% menos que estudios individuales</span>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span><strong>Conveniencia:</strong> Todos los estudios en una sola visita</span>
                        </li>
                        <li>
                            <i class="fas fa-user-md"></i>
                            <span><strong>Atención integral:</strong> Evaluación completa de tu estado de salud</span>
                        </li>
                        <li>
                            <i class="fas fa-chart-line"></i>
                            <span><strong>Seguimiento:</strong> Monitoreo continuo de tu bienestar</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="info-section fade-in" style="animation-delay: 0.8s">
                    <h3><i class="fas fa-calendar-check me-2 text-success"></i>Proceso de reserva</h3>
                    <ul class="info-list">
                        <li>
                            <i class="fas fa-mouse-pointer"></i>
                            <span><strong>1. Selecciona:</strong> Elige el paquete que mejor se adapte a tus necesidades</span>
                        </li>
                        <li>
                            <i class="fas fa-calendar-alt"></i>
                            <span><strong>2. Agenda:</strong> Programa tu cita en el horario que prefieras</span>
                        </li>
                        <li>
                            <i class="fas fa-file-medical"></i>
                            <span><strong>3. Prepárate:</strong> Recibe las indicaciones específicas para tus estudios</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span><strong>4. Asiste:</strong> Acude a tu cita y completa todos los estudios</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Función para reservar paquete
        function reservarPaquete(tipoPaquete) {
            // Información de los paquetes
            const paquetes = {
                'basico': {
                    nombre: 'Paquete Básico',
                    precio: 2500,
                    servicios: ['Radiografía de Tórax', 'Análisis de Sangre Completo', 'Electrocardiograma', 'Consulta General']
                },
                'completo': {
                    nombre: 'Paquete Completo',
                    precio: 7500,
                    servicios: ['Resonancia Magnética', 'Tomografía Computarizada', 'Ultrasonido Completo', 'Laboratorios Completos', 'Electrocardiograma', 'Consulta Especializada']
                },
                'premium': {
                    nombre: 'Paquete Premium',
                    precio: 12500,
                    servicios: ['Todos los estudios disponibles', 'Mastografía Digital', 'Densitometría Ósea', 'Laboratorios Especializados', 'Consulta con 2 especialistas', 'Seguimiento por 6 meses']
                }
            };

            const paquete = paquetes[tipoPaquete];
            if (paquete) {
                // Redirigir a página de reserva con información del paquete
                const params = new URLSearchParams({
                    tipo: 'paquete',
                    paquete_tipo: tipoPaquete,
                    paquete_nombre: paquete.nombre,
                    paquete_precio: paquete.precio,
                    paquete_servicios: JSON.stringify(paquete.servicios)
                });
                
                window.location.href = `reservar.php?${params.toString()}`;
            }
        }

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth scroll para navegación interna si es necesario
            console.log('Página de paquetes cargada');
        });
    </script>
</body>
</html>