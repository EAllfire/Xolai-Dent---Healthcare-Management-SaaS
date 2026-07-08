<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto - Hospital Angeles</title>
    
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
            --light-bg: #000000;
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            --danger-color: #ef4444;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #e5e7eb;
            background: var(--light-bg);
            padding-top: 100px;
        }

        /* Header */
        .navbar {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
            padding: 1rem 0;
            border-bottom: 1px solid rgba(41, 121, 255, 0.1);
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
            font-size: 1.5rem;
        }

        .back-btn {
            background: rgba(41, 121, 255, 0.1);
            color: white;
            border: 1px solid rgba(41, 121, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(41, 121, 255, 0.2);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.2);
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #1a1a1a, #0a0a0a);
            color: white;
            padding: 3rem 0;
            margin-bottom: 3rem;
            border-bottom: 1px solid rgba(41, 121, 255, 0.1);
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

        /* Contact Cards */
        .contact-card {
            background: #0a0a0a;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            transition: all 0.4s ease;
            border: 1px solid rgba(255, 255, 255, 0.05);
            height: 100%;
        }

        .contact-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 0 20px rgba(41, 121, 255, 0.2);
            border-color: var(--accent-color);
        }

        .contact-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, #1a1a1a, #333);
            border: 1px solid rgba(41, 121, 255, 0.3);
        }

        .contact-card h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            text-align: center;
        }

        .contact-info {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .contact-info li {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            color: #9ca3af;
        }

        .contact-info li i {
            color: var(--accent-color);
            margin-right: 0.75rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .contact-info li a {
            color: #9ca3af;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .contact-info li a:hover {
            color: var(--accent-color);
        }

        /* Contact Form */
        .contact-form {
            background: #0a0a0a;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .form-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-label {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            background: #050505;
            border: 1px solid #333;
            color: #e5e7eb;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            background: #050505;
            color: #fff;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2);
            outline: none;
        }

        .btn-primary {
            background: var(--accent-color);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.3);
        }

        .btn-primary:hover {
            background: #2962ff;
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(41, 121, 255, 0.6);
        }

        /* Map Section */
        .map-section {
            background: #0a0a0a;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .map-container {
            height: 400px;
            border-radius: 12px;
            overflow: hidden;
            background: #111;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #555;
        }

        .map-placeholder {
            text-align: center;
        }

        .map-placeholder i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--accent-color);
        }

        /* Hours Section */
        .hours-card {
            background: #0a0a0a;
            color: white;
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            border: 1px solid rgba(41, 121, 255, 0.3);
            box-shadow: 0 0 20px rgba(41, 121, 255, 0.1);
        }

        .hours-card h3 {
            color: white;
            margin-bottom: 1.5rem;
        }

        .hours-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .hours-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #e5e7eb;
        }

        .hours-list li:last-child {
            border-bottom: none;
        }

        .day {
            font-weight: 500;
        }

        .time {
            opacity: 0.9;
        }

        /* Alert Messages */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
        }

        .alert-success {
            background: #f0fdf4;
            color: #059669;
            border-left: 4px solid var(--accent-color);
        }

        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
            border-left: 4px solid var(--danger-color);
        }

        /* Loading States */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
            }
            
            .page-header p {
                font-size: 1rem;
            }

            .contact-card {
                padding: 2rem;
            }

            .contact-form {
                padding: 2rem;
            }

            .map-container {
                height: 300px;
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
                    <h1>Contáctanos</h1>
                    <p>Estamos aquí para atenderte y resolver todas tus dudas</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div style="font-size: 4rem; opacity: 0.7;">
                        <i class="fas fa-comments"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Info Section -->
    <div class="container mb-5">
        <div class="row">
            <!-- Teléfonos -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="contact-card fade-in">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <h3>Teléfonos</h3>
                    <ul class="contact-info">
                        <li>
                            <i class="fas fa-phone"></i>
                            <a href="tel:+525555123456">+52 55 5512-3456</a>
                        </li>
                        <li>
                            <i class="fas fa-mobile-alt"></i>
                            <a href="tel:+525555654321">+52 55 5565-4321</a>
                        </li>
                        <li>
                            <i class="fas fa-headset"></i>
                            <span>Lada sin costo: 800-123-4567</span>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span>24 horas para emergencias</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Ubicación -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="contact-card fade-in" style="animation-delay: 0.2s">
                    <div class="contact-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3>Ubicación</h3>
                    <ul class="contact-info">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Av. Revolución 123<br>Col. San Pedro<br>CDMX, México 01000</span>
                        </li>
                        <li>
                            <i class="fas fa-parking"></i>
                            <span>Estacionamiento gratuito</span>
                        </li>
                        <li>
                            <i class="fas fa-wheelchair"></i>
                            <span>Acceso para discapacitados</span>
                        </li>
                        <li>
                            <i class="fas fa-subway"></i>
                            <span>Metro: Línea 1, Estación Hospital</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Email y Redes -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="contact-card fade-in" style="animation-delay: 0.4s">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <h3>Digital</h3>
                    <ul class="contact-info">
                        <li>
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:info@hospitalangeles.com">info@hospitalangeles.com</a>
                        </li>
                        <li>
                            <i class="fas fa-calendar-check"></i>
                            <a href="mailto:citas@hospitalangeles.com">citas@hospitalangeles.com</a>
                        </li>
                        <li>
                            <i class="fab fa-facebook"></i>
                            <a href="#" target="_blank">@HospitalAngelesOficial</a>
                        </li>
                        <li>
                            <i class="fab fa-instagram"></i>
                            <a href="#" target="_blank">@hospital_angeles</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Horarios de Atención -->
        <div class="row mb-5">
            <div class="col-lg-6 col-md-8 mx-auto">
                <div class="hours-card fade-in" style="animation-delay: 0.6s">
                    <h3><i class="fas fa-clock me-2"></i>Horarios de Atención</h3>
                    <ul class="hours-list">
                        <li>
                            <span class="day">Lunes a Viernes</span>
                            <span class="time">7:00 AM - 10:00 PM</span>
                        </li>
                        <li>
                            <span class="day">Sábados</span>
                            <span class="time">8:00 AM - 8:00 PM</span>
                        </li>
                        <li>
                            <span class="day">Domingos</span>
                            <span class="time">9:00 AM - 6:00 PM</span>
                        </li>
                        <li>
                            <span class="day">Urgencias</span>
                            <span class="time">24 horas</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Mapa -->
        <div class="map-section fade-in" style="animation-delay: 0.8s">
            <h3 class="mb-4"><i class="fas fa-map me-2"></i>Cómo llegar</h3>
            <div class="map-container">
                <div class="map-placeholder">
                    <i class="fas fa-map-marked-alt"></i>
                    <p class="mb-0">Mapa interactivo disponible próximamente</p>
                    <small class="text-muted">Av. Revolución 123, Col. San Pedro, CDMX</small>
                </div>
            </div>
        </div>

        <!-- Formulario de Contacto -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="contact-form fade-in" style="animation-delay: 1s">
                    <h3 class="form-title">
                        <i class="fas fa-paper-plane me-2"></i>
                        Envíanos un Mensaje
                    </h3>
                    
                    <div id="alertContainer"></div>
                    
                    <form id="contactForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre Completo *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="telefono" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="telefono" name="telefono">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="asunto" class="form-label">Asunto</label>
                                <select class="form-select" id="asunto" name="asunto">
                                    <option value="">Seleccionar asunto...</option>
                                    <option value="cita">Agendar Cita</option>
                                    <option value="informacion">Solicitar Información</option>
                                    <option value="precio">Consultar Precios</option>
                                    <option value="resultado">Consultar Resultados</option>
                                    <option value="queja">Queja o Sugerencia</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mensaje" class="form-label">Mensaje *</label>
                            <textarea class="form-control" id="mensaje" name="mensaje" rows="5" 
                                placeholder="Escribe tu mensaje aquí..." required></textarea>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="acepta_privacidad" name="acepta_privacidad" required>
                            <label class="form-check-label" for="acepta_privacidad">
                                Acepto el <a href="#" class="text-decoration-none">aviso de privacidad</a> y autorizo el tratamiento de mis datos personales *
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-paper-plane me-2"></i>
                            Enviar Mensaje
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            // Event listener para el formulario
            document.getElementById('contactForm').addEventListener('submit', handleSubmit);
        });

        // Manejar envío del formulario
        async function handleSubmit(event) {
            event.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            // Mostrar loading
            submitBtn.innerHTML = '<span class="spinner"></span> Enviando...';
            submitBtn.disabled = true;
            document.getElementById('contactForm').classList.add('loading');
            
            try {
                const formData = new FormData(event.target);
                
                // Simular envío (en producción, esto iría a un endpoint real)
                await simulateContactSubmission(formData);
                
                // Mostrar éxito
                showAlert('success', '¡Mensaje enviado correctamente! Te contactaremos pronto.');
                
                // Limpiar formulario
                document.getElementById('contactForm').reset();
                
            } catch (error) {
                console.error('Error enviando mensaje:', error);
                showAlert('danger', 'Hubo un error al enviar tu mensaje. Por favor intenta nuevamente.');
            } finally {
                // Restaurar botón
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                document.getElementById('contactForm').classList.remove('loading');
            }
        }

        // Simular envío de contacto
        async function simulateContactSubmission(formData) {
            // Simular delay de red
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            // En producción, aquí se haría el POST real
            console.log('Datos de contacto:', Object.fromEntries(formData));
        }

        // Mostrar alertas
        function showAlert(type, message) {
            const container = document.getElementById('alertContainer');
            container.innerHTML = `
                <div class="alert alert-${type} fade-in mb-3">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                    ${message}
                </div>
            `;
            
            // Scroll to alert
            container.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Auto-hide after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    const alert = container.querySelector('.alert');
                    if (alert) {
                        alert.remove();
                    }
                }, 5000);
            }
        }
    </script>
</body>
</html>