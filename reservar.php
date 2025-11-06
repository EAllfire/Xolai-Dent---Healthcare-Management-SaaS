<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar Cita - Hospital Angeles</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Flatpickr CSS para calendario -->
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1f2937;
            --secondary-color: #3b82f6;
            --accent-color: #0f5f85;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --danger-color: #ef4444;
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

        /* Main Content */
        .main-container {
            margin-bottom: 3rem;
        }

        .reservation-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, var(--accent-color), #0f5f85 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .card-header h2 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .card-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }

        .card-body {
            padding: 2.5rem;
        }

        /* Service/Package Summary */
        .service-summary {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--accent-color);
        }

        .service-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .service-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-color);
            margin-bottom: 1rem;
        }

        .service-details {
            color: #6b7280;
            font-size: 0.95rem;
        }

        .service-list {
            list-style: none;
            padding: 0;
            margin-top: 1rem;
        }

        .service-list li {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .service-list li i {
            color: var(--accent-color);
            margin-right: 0.5rem;
            font-size: 0.8rem;
        }

        /* Form Styles */
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            outline: none;
        }

        .form-select {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            outline: none;
        }

        /* Date and Time Selection */
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .time-slot {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 0.75rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .time-slot:hover {
            border-color: var(--accent-color);
            background: #f0fdf4;
        }

        .time-slot.selected {
            background: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
        }

        .time-slot.unavailable {
            background: #f3f4f6;
            border-color: #d1d5db;
            color: #9ca3af;
            cursor: not-allowed;
        }

        /* Alert Messages */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
        }

        .alert-success {
            background: #f0fdf4;
            color: #0f5f85 100%;
            border-left: 4px solid var(--accent-color);
        }

        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
            border-left: 4px solid var(--danger-color);
        }

        /* Buttons */
        .btn-primary {
            background: var(--accent-color);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #0f5f85 100%;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px -4px #144458ff 100%;
        }

        .btn-secondary {
            background: #6b7280;
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #4b5563;
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
            .card-body {
                padding: 1.5rem;
            }

            .time-slots {
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                gap: 0.5rem;
            }

            .time-slot {
                padding: 0.5rem;
                font-size: 0.8rem;
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
            
            <a href="javascript:history.back()" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Regresar
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container main-container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="reservation-card fade-in">
                    <div class="card-header">
                        <h2><i class="fas fa-calendar-plus me-2"></i>Reservar Cita</h2>
                        <p>Complete el formulario para agendar su cita</p>
                    </div>
                    
                    <div class="card-body">
                        <!-- Service/Package Summary -->
                        <div id="serviceSummary" class="service-summary">
                            <!-- Se llenará dinámicamente -->
                        </div>

                        <!-- Alert Messages -->
                        <div id="alertContainer"></div>

                        <!-- Reservation Form -->
                        <form id="reservationForm">
                            <!-- Información Personal -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="fas fa-user"></i>
                                    Información Personal
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="nombre" class="form-label">Nombre Completo *</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="telefono" class="form-label">Teléfono *</label>
                                        <input type="tel" class="form-control" id="telefono" name="telefono" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento *</label>
                                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Selección de Fecha y Hora -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="fas fa-calendar-alt"></i>
                                    Fecha y Hora
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="fecha_cita" class="form-label">Fecha Deseada *</label>
                                        <input type="text" class="form-control" id="fecha_cita" name="fecha_cita" placeholder="Seleccionar fecha" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Horarios Disponibles *</label>
                                        <div id="timeSlots" class="time-slots">
                                            <!-- Se llenarán dinámicamente -->
                                        </div>
                                        <input type="hidden" id="hora_seleccionada" name="hora_seleccionada" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Modalidad (solo para servicios individuales) -->
                            <div class="form-section" id="modalidadSection" style="display: none;">
                                <div class="section-title">
                                    <i class="fas fa-cog"></i>
                                    Modalidad
                                </div>
                                
                                <div class="mb-3">
                                    <label for="modalidad_id" class="form-label">Seleccionar Modalidad *</label>
                                    <select class="form-select" id="modalidad_id" name="modalidad_id">
                                        <option value="">Seleccionar modalidad...</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Información Adicional -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="fas fa-notes-medical"></i>
                                    Información Adicional
                                </div>
                                
                                <div class="mb-3">
                                    <label for="observaciones" class="form-label">Comentarios o Observaciones</label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3" placeholder="Cualquier información adicional que considere relevante..."></textarea>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="acepta_terminos" name="acepta_terminos" required>
                                    <label class="form-check-label" for="acepta_terminos">
                                        Acepto los términos y condiciones del servicio *
                                    </label>
                                </div>
                            </div>

                            <!-- Botones de Acción -->
                            <div class="d-flex gap-3 justify-content-end">
                                <button type="button" class="btn btn-secondary" onclick="history.back()">
                                    Cancelar
                                </button>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-check me-2"></i>
                                    Confirmar Reserva
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    
    <script>
        let reservationData = {};
        let availableSlots = [];
        let flatpickrInstance;

        // Inicializar página
        document.addEventListener('DOMContentLoaded', function() {
            loadReservationData();
            initializeDatePicker();
            loadModalidades();
            
            // Event listeners
            document.getElementById('reservationForm').addEventListener('submit', handleSubmit);
        });

        // Cargar datos de reserva desde URL
        function loadReservationData() {
            const urlParams = new URLSearchParams(window.location.search);
            const tipo = urlParams.get('tipo');
            
            if (tipo === 'servicio' || urlParams.get('servicio_id')) {
                // Servicio individual desde modalidad.php
                reservationData = {
                    tipo: 'servicio',
                    servicio_id: urlParams.get('servicio_id'),
                    servicio_nombre: urlParams.get('servicio_nombre'),
                    modalidad_id: urlParams.get('modalidad_id'),
                    modalidad_nombre: urlParams.get('modalidad_nombre'),
                    duracion: parseInt(urlParams.get('duracion') || '60')
                };
                
                displayServiceSummary();
                document.getElementById('modalidadSection').style.display = 'block';
                
                // Pre-seleccionar la modalidad
                setTimeout(() => {
                    const modalidadSelect = document.getElementById('modalidad_id');
                    if (modalidadSelect && reservationData.modalidad_id) {
                        modalidadSelect.value = reservationData.modalidad_id;
                    }
                }, 1000);
                
            } else if (tipo === 'paquete') {
                // Paquete desde paquetes.php
                reservationData = {
                    tipo: 'paquete',
                    paquete_tipo: urlParams.get('paquete_tipo'),
                    paquete_nombre: urlParams.get('paquete_nombre'),
                    paquete_precio: parseFloat(urlParams.get('paquete_precio')),
                    paquete_servicios: JSON.parse(urlParams.get('paquete_servicios') || '[]')
                };
                
                displayPackageSummary();
            }
        }

        // Mostrar resumen de servicio
        function displayServiceSummary() {
            const summary = document.getElementById('serviceSummary');
            summary.innerHTML = `
                <div class="service-title">
                    <i class="fas fa-medical me-2"></i>
                    ${reservationData.servicio_nombre}
                </div>
                <div class="service-details">
                    <strong>Modalidad:</strong> ${reservationData.modalidad_nombre}<br>
                    <strong>Duración estimada:</strong> ${reservationData.duracion} minutos
                </div>
            `;
        }

        // Mostrar resumen de paquete
        function displayPackageSummary() {
            const servicesList = reservationData.paquete_servicios.map(servicio => 
                `<li><i class="fas fa-check"></i>${servicio}</li>`
            ).join('');
            
            const summary = document.getElementById('serviceSummary');
            summary.innerHTML = `
                <div class="service-title">
                    <i class="fas fa-gift me-2"></i>
                    ${reservationData.paquete_nombre}
                </div>
                <div class="service-price">
                    $${reservationData.paquete_precio.toLocaleString()}
                </div>
                <div class="service-details">
                    <strong>Incluye:</strong>
                    <ul class="service-list">
                        ${servicesList}
                    </ul>
                </div>
            `;
        }

        // Inicializar selector de fecha
        function initializeDatePicker() {
            flatpickrInstance = flatpickr("#fecha_cita", {
                locale: "es",
                minDate: "today",
                maxDate: new Date().fp_incr(60), // 60 días adelante
                dateFormat: "Y-m-d",
                disable: [
                    function(date) {
                        // Deshabilitar domingos (0)
                        return (date.getDay() === 0);
                    }
                ],
                onChange: function(selectedDates, dateStr) {
                    if (dateStr) {
                        loadAvailableSlots(dateStr);
                    }
                }
            });
        }

        // Cargar modalidades disponibles
        async function loadModalidades() {
            if (reservationData.tipo !== 'servicio') return;
            
            try {
                const response = await fetch('recursos_json.php');
                const modalidades = await response.json();
                
                const select = document.getElementById('modalidad_id');
                select.innerHTML = '<option value="">Seleccionar modalidad...</option>';
                
                modalidades.forEach(modalidad => {
                    const selected = modalidad.id == reservationData.modalidad_id ? 'selected' : '';
                    select.innerHTML += `<option value="${modalidad.id}" ${selected}>${modalidad.title}</option>`;
                });
                
            } catch (error) {
                console.error('Error cargando modalidades:', error);
            }
        }

        // Cargar horarios disponibles
        async function loadAvailableSlots(fecha) {
            const slotsContainer = document.getElementById('timeSlots');
            slotsContainer.innerHTML = '<div class="col-12 text-center">Cargando horarios...</div>';
            
            try {
                // Generar horarios de ejemplo (en un caso real, esto vendría del servidor)
                const horarios = generateTimeSlots(fecha);
                displayTimeSlots(horarios);
                
            } catch (error) {
                console.error('Error cargando horarios:', error);
                slotsContainer.innerHTML = '<div class="col-12 text-center text-danger">Error cargando horarios</div>';
            }
        }

        // Generar horarios disponibles (simulado)
        function generateTimeSlots(fecha) {
            const slots = [];
            const startHour = 8; // 8 AM
            const endHour = 18; // 6 PM
            const interval = 60; // 60 minutos
            
            for (let hour = startHour; hour < endHour; hour++) {
                const timeStr = `${hour.toString().padStart(2, '0')}:00`;
                const available = Math.random() > 0.3; // 70% disponibilidad simulada
                
                slots.push({
                    time: timeStr,
                    available: available
                });
            }
            
            return slots;
        }

        // Mostrar horarios disponibles
        function displayTimeSlots(slots) {
            const container = document.getElementById('timeSlots');
            container.innerHTML = '';
            
            slots.forEach(slot => {
                const slotElement = document.createElement('div');
                slotElement.className = `time-slot ${slot.available ? '' : 'unavailable'}`;
                slotElement.textContent = slot.time;
                
                if (slot.available) {
                    slotElement.addEventListener('click', () => selectTimeSlot(slot.time, slotElement));
                }
                
                container.appendChild(slotElement);
            });
        }

        // Seleccionar horario
        function selectTimeSlot(time, element) {
            // Remover selección previa
            document.querySelectorAll('.time-slot.selected').forEach(slot => {
                slot.classList.remove('selected');
            });
            
            // Seleccionar nuevo horario
            element.classList.add('selected');
            document.getElementById('hora_seleccionada').value = time;
        }

        // Manejar envío del formulario
        async function handleSubmit(event) {
            event.preventDefault();
            
            // Validar formulario antes de procesar
            if (!validateForm()) {
                return;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            // Mostrar loading
            submitBtn.innerHTML = '<span class="spinner"></span> Procesando...';
            submitBtn.disabled = true;
            document.getElementById('reservationForm').classList.add('loading');
            
            try {
                const formData = new FormData(event.target);
                
                // Agregar datos de reserva
                if (reservationData.tipo === 'servicio') {
                    formData.append('servicio_id', reservationData.servicio_id);
                    formData.append('modalidad_id', reservationData.modalidad_id);
                    formData.append('tipo_reserva', 'servicio');
                } else if (reservationData.tipo === 'paquete') {
                    formData.append('paquete_tipo', reservationData.paquete_tipo);
                    formData.append('paquete_servicios', JSON.stringify(reservationData.paquete_servicios));
                    formData.append('tipo_reserva', 'paquete');
                    // Para paquetes, usar modalidad por defecto (primera disponible)
                    formData.append('modalidad_id', '1');
                }
                
                // Enviar a endpoint real
                const response = await fetch('guardar_reserva_cliente.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (result.requiere_pago && result.data.pago && result.data.pago.success) {
                        // Redirigir a página de pago
                        showAlert('success', 'Reserva creada exitosamente. Redirigiendo al pago...');
                        
                        setTimeout(() => {
                            window.location.href = result.data.pago.url_pago;
                        }, 2000);
                        
                    } else if (result.requiere_pago) {
                        // Mostrar éxito pero con problema de pago
                        showAlert('success', result.message + ' Puede realizar el pago posteriormente.');
                        
                        // Limpiar formulario
                        limpiarFormulario();
                        
                        // Redirigir después de 5 segundos
                        setTimeout(() => {
                            window.location.href = 'cliente.php';
                        }, 5000);
                    } else {
                        // Reserva sin pago requerido
                        showAlert('success', result.message + ' Te contactaremos pronto para confirmar los detalles.');
                        
                        // Limpiar formulario
                        limpiarFormulario();
                        
                        // Redirigir después de 5 segundos
                        setTimeout(() => {
                            window.location.href = 'cliente.php';
                        }, 5000);
                    }
                } else {
                    throw new Error(result.error || 'Error desconocido');
                }
                
            } catch (error) {
                console.error('Error en reserva:', error);
                showAlert('danger', 'Error: ' + error.message + '. Por favor intente nuevamente.');
            } finally {
                // Restaurar botón
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                document.getElementById('reservationForm').classList.remove('loading');
            }
        }

        // Validar formulario antes de envío
        function validateForm() {
            const requiredFields = [
                { id: 'nombre', message: 'El nombre es requerido' },
                { id: 'telefono', message: 'El teléfono es requerido' },
                { id: 'email', message: 'El email es requerido' },
                { id: 'fecha_nacimiento', message: 'La fecha de nacimiento es requerida' },
                { id: 'fecha_cita', message: 'Debe seleccionar una fecha' },
                { id: 'hora_seleccionada', message: 'Debe seleccionar un horario' }
            ];

            for (const field of requiredFields) {
                const element = document.getElementById(field.id);
                if (!element.value.trim()) {
                    showAlert('danger', field.message);
                    element.focus();
                    return false;
                }
            }

            // Validar email
            const email = document.getElementById('email').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showAlert('danger', 'Por favor ingrese un email válido');
                document.getElementById('email').focus();
                return false;
            }

            // Validar modalidad para servicios individuales
            if (reservationData.tipo === 'servicio') {
                const modalidadSelect = document.getElementById('modalidad_id');
                if (!modalidadSelect.value) {
                    showAlert('danger', 'Debe seleccionar una modalidad');
                    modalidadSelect.focus();
                    return false;
                }
            }

            // Validar términos y condiciones
            const terminos = document.getElementById('acepta_terminos');
            if (!terminos.checked) {
                showAlert('danger', 'Debe aceptar los términos y condiciones');
                terminos.focus();
                return false;
            }

            return true;
        }

        // Limpiar formulario después de reserva exitosa
        function limpiarFormulario() {
            document.getElementById('reservationForm').reset();
            document.getElementById('hora_seleccionada').value = '';
            
            // Limpiar selecciones visuales
            document.querySelectorAll('.time-slot.selected').forEach(slot => {
                slot.classList.remove('selected');
            });
        }

        // Mostrar alertas
        function showAlert(type, message) {
            const container = document.getElementById('alertContainer');
            container.innerHTML = `
                <div class="alert alert-${type} fade-in">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                    ${message}
                </div>
            `;
            
            // Scroll to alert
            container.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    </script>
</body>
</html>