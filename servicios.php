<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicios - Hospital Angeles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: #000000;
            min-height: 100vh;
        }
        
        /* Header */
        .header {
            background: rgba(10, 10, 10, 0.95);
            color: white;
            padding: 2rem 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-bottom: 1px solid rgba(41, 121, 255, 0.1);
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
        }
        
        .logo-section img {
            height: 50px;
            margin-right: 15px;
        }
        
        .logo-text {
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .back-button {
            background: rgba(41, 121, 255, 0.1);
            color: white;
            border: 1px solid rgba(41, 121, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            background: rgba(41, 121, 255, 0.2);
            color: white;
            text-decoration: none;
            border-color: rgba(41, 121, 255, 0.5);
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.3);
        }
        
        .modalidad-title {
            text-align: center;
            margin-top: 1rem;
            font-size: 1.5rem;
            font-weight: 500;
            opacity: 0.9;
        }
        
        /* Contenido */
        .main-content {
            padding: 3rem 0;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.2rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 3rem;
        }
        
        /* Cards de servicios */
        .service-card {
            background: #0a0a0a;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0 20px rgba(41, 121, 255, 0.2);
            border-color: #2979ff;
        }
        
        .service-header {
            background: linear-gradient(135deg, #111 0%, #000 100%);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .service-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
        }
        
        .service-duration {
            background: rgba(255,255,255,0.2);
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .service-body {
            padding: 1.5rem;
        }
        
        .service-description {
            color: #9ca3af;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .service-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            color: #e5e7eb;
            font-size: 0.9rem;
        }
        
        .detail-item i {
            color: #2979ff;
            width: 20px;
            margin-right: 0.5rem;
        }
        
        .price-tag {
            background: rgba(41, 121, 255, 0.1);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            text-align: center;
            margin-top: 1rem;
            border: 1px solid rgba(41, 121, 255, 0.3);
        }
        
        /* Loading y Estados */
        .loading {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
        
        .loading i {
            font-size: 3rem;
            margin-bottom: 1rem;
            animation: spin 2s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .no-services {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .logo-text {
                font-size: 1.5rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .service-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo-section">
                    <img src="../images/logo.png" alt="Hospital Angeles Logo" onerror="this.style.display='none'">
                    <div class="logo-text">HOSPITAL ANGELES</div>
                </div>
                <a href="index.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Volver al Inicio
                </a>
            </div>
            <div class="modalidad-title" id="modalidadNombre">
                <!-- Se carga dinámicamente -->
            </div>
        </div>
    </header>

    <!-- Contenido principal -->
    <main class="main-content">
        <div class="container">
            <h2 class="section-title">Servicios Disponibles</h2>
            
            <div id="servicios-container" class="row">
                <div class="col-12 loading">
                    <i class="fas fa-spinner"></i>
                    <p>Cargando servicios...</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Obtener parámetros de URL
            const urlParams = new URLSearchParams(window.location.search);
            const modalidadId = urlParams.get('modalidad');
            const modalidadNombre = urlParams.get('nombre');
            
            if (modalidadId && modalidadNombre) {
                $('#modalidadNombre').text(modalidadNombre);
                document.title = `${modalidadNombre} - Hospital Angeles`;
                cargarServicios(modalidadId);
            } else {
                window.location.href = 'index.php';
            }
        });
        
        function cargarServicios(modalidadId) {
            fetch(`api/servicios.php?modalidad_id=${modalidadId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.message);
                    }
                    mostrarServicios(data);
                })
                .catch(error => {
                    console.error('Error:', error);
                    $('#servicios-container').html(`
                        <div class="col-12">
                            <div class="alert alert-danger text-center">
                                <i class="fas fa-exclamation-triangle"></i>
                                Error al cargar los servicios. Por favor, inténtelo más tarde.
                            </div>
                        </div>
                    `);
                });
        }
        
        function mostrarServicios(servicios) {
            if (servicios.length === 0) {
                $('#servicios-container').html(`
                    <div class="col-12 no-services">
                        <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 1rem; color: #6b7280;"></i>
                        <h4>No hay servicios disponibles</h4>
                        <p>Esta modalidad no tiene servicios configurados actualmente.</p>
                    </div>
                `);
                return;
            }
            
            let html = '';
            
            servicios.forEach(servicio => {
                html += `
                    <div class="col-lg-6 col-md-12">
                        <div class="service-card">
                            <div class="service-header">
                                <h3 class="service-name">${servicio.nombre}</h3>
                                <div class="service-duration">
                                    <i class="fas fa-clock"></i> ${servicio.duracion_minutos} min
                                </div>
                            </div>
                            <div class="service-body">
                                <p class="service-description">
                                    ${servicio.descripcion || 'Servicio especializado de imagenología con tecnología de vanguardia.'}
                                </p>
                                
                                <div class="service-details">
                                    <div class="detail-item">
                                        <i class="fas fa-user-md"></i>
                                        Especialistas certificados
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-shield-alt"></i>
                                        Procedimiento seguro
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        Duración: ${servicio.duracion_minutos} minutos
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-calendar-check"></i>
                                        Citas disponibles
                                    </div>
                                </div>
                                
                                ${servicio.precio ? `
                                    <div class="price-tag">
                                        <i class="fas fa-dollar-sign"></i>
                                        Precio: $${parseFloat(servicio.precio).toLocaleString('es-MX', {minimumFractionDigits: 2})} MXN
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $('#servicios-container').html(html);
        }
    </script>
</body>
</html>