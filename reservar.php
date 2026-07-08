<?php
session_start();
// Recuperar datos del paciente desde la sesión, si existen
$paciente_data = $_SESSION['portal_paciente_data'] ?? null;

// Recuperar el ID del médico de la URL para mantener la navegación
$medico_id = isset($_GET['medico_id']) ? (int)$_GET['medico_id'] : 0;
$link_cliente = "cliente.php";
$params = [];
if ($paciente_data) $params[] = 'portal_usuario_id=' . ($paciente_data['lookup_portal_id'] ?? '');
if ($medico_id > 0) $params[] = 'medico_id=' . $medico_id;
if (!empty($params)) $link_cliente .= '?' . implode('&', $params);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar Cita - Sistema de Gestión de Citas</title>
    
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
            --primary-color: #ffffff;
            --secondary-color: #a0a0a0;
            --accent-color: #2979ff;
            --gradient-bg: #000000;
            --light-bg: #000000;
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            --danger-color: #ef4444;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #e5e7eb;
            background: var(--light-bg);
            padding-top: 0;
        }

        /* Header Styles from cliente.php */
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

        .nav-link {
            color: #a0a0a0 !important;
            font-weight: 500;
            font-size: 15px;
            padding: 8px 16px;
            border-radius: 10px;
            transition: all 0.2s ease;
            text-decoration: none;
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

        .back-btn {
            background: rgba(41, 121, 255, 0.1);
            color: #e5e7eb;
            border: 1px solid rgba(41, 121, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(41, 121, 255, 0.2);
            color: white;
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.2);
        }

        /* Main Content */
        .main-container {
            margin-top: 120px;
            margin-bottom: 3rem;
        }

        .reservation-card {
            background: #0a0a0a;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .card-header {
            background: linear-gradient(135deg, #111 0%, #000 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid rgba(41, 121, 255, 0.2);
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
            background: #111;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--accent-color);
            border: 1px solid #333;
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
            color: #9ca3af;
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
            border-bottom: 1px solid #333;
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
            background: #050505;
            border: 1px solid #333;
            color: #e5e7eb;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .form-control:focus {
            background: #050505;
            color: #fff;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2);
            outline: none;
        }

        .form-select {
            background: #050505;
            border: 1px solid #333;
            color: #e5e7eb;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2);
            outline: none;
        }

        /* Date and Time Selection */
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); /* Columnas más uniformes */
            gap: 12px;
            margin-top: 15px;
        }

        .time-slot {
            background: #111;
            border: 1px solid #333;
            color: #fff;
            border-radius: 10px;
            padding: 12px 5px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .time-slot:hover {
            border-color: var(--accent-color);
            background: rgba(41, 121, 255, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .time-slot.selected {
            background: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
            box-shadow: 0 0 15px rgba(41, 121, 255, 0.5);
            transform: scale(1.05);
            font-weight: 700;
        }

        .time-slot.unavailable {
            background: #222;
            border-color: #333;
            color: #555;
            cursor: not-allowed;
        }

        /* Alert Messages */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
        }

        .alert-success {
            background: rgba(41, 121, 255, 0.1);
            color: #2979ff;
            border-left: 4px solid var(--accent-color);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
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
            background: #2962ff;
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(41, 121, 255, 0.6);
        }

        .btn-secondary {
            background: #333;
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #444;
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
    <!-- Header Mejorado (Estilo cliente.php) -->
    <header class="main-header">
        <div class="header-left">
            <a href="<?php echo $link_cliente; ?>" class="header-logo" style="text-decoration: none; display: flex; align-items: center;">
                <img src="images/Xolai.png" alt="Xolai Logo" class="header-logo-img">
                <span class="header-title" style="margin-left: 10px;">Xolai</span>
            </a>
        </div>
        
        <div class="header-center">
            <div class="header-title d-none d-lg-block">SISTEMA DE GESTIÓN DE CITAS</div>
        </div>
        
        <div class="header-right">
            <div class="contact-info-header d-none d-md-block">
                <div><i class="fas fa-map-marker-alt me-1"></i> Calle 12 335, entre guerrero y rayón, Zona Centro, 31500 Cuauhtémoc, Chih.</div>
                <div><i class="fas fa-phone me-1"></i> +52 625 125 70 48</div>
            </div>
            <nav class="d-flex">
                <a href="<?php echo $link_cliente; ?>" class="nav-link">
                    <i class="fas fa-arrow-left me-1"></i>Regresar
                </a>
            </nav>
        </div>
    </header>

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
                        <div id="serviceSummary" class="service-summary"></div>

                        <!-- Alert Messages -->
                        <div id="alertContainer"></div>

                        <!-- Reservation Form -->
                        <form id="reservationForm" enctype="multipart/form-data">
                            <!-- Información Personal -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="fas fa-user"></i> Información Personal
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="nombre" class="form-label">Nombre Completo *</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" required value="<?php echo htmlspecialchars($paciente_data['nombre_completo'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="telefono" class="form-label">Teléfono *</label>
                                        <input type="tel" class="form-control" id="telefono" name="telefono" required value="<?php echo htmlspecialchars($paciente_data['telefono'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($paciente_data['email'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento *</label>
                                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" required value="<?php echo htmlspecialchars($paciente_data['fecha_nacimiento'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Selección de Fecha y Hora -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="fas fa-calendar-alt"></i> Fecha y Hora
                                </div>
                                <div class="row">
                                    <div class="col-md-5 mb-4">
                                        <label for="fecha_cita" class="form-label">Fecha Deseada *</label>
                                        <div class="input-group">
                                            <span class="input-group-text" style="background:#111;border-color:#333;color:#e5e7eb"><i class="fas fa-calendar"></i></span>
                                            <input type="text" class="form-control" id="fecha_cita" name="fecha_cita" placeholder="Seleccionar fecha" required>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Horarios Disponibles *</label>
                                        <div id="timeSlotsContainer" style="background: #050505; border: 1px solid #333; border-radius: 12px; padding: 20px;">
                                            <div id="timeSlots" class="time-slots">
                                                <p class="text-muted text-center w-100 m-0" style="grid-column: 1/-1;">Seleccione una fecha para ver horarios.</p>
                                            </div>
                                        </div>
                                        <input type="hidden" id="hora_seleccionada" name="hora_seleccionada" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Información Adicional -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="fas fa-notes-medical"></i> Información Adicional
                                </div>
                                <div class="mb-3">
                                    <label for="observaciones" class="form-label">Comentarios u Observaciones</label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="acepta_terminos" name="acepta_terminos" required>
                                    <label class="form-check-label" for="acepta_terminos">
                                        Acepto los términos y condiciones del servicio *
                                    </label>
                                </div>
                            </div>

                            <!-- Botones -->
                            <div class="d-flex gap-3 justify-content-end">
                                <button type="button" class="btn btn-secondary" onclick="history.back()">Cancelar</button>
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="fas fa-check me-2"></i> Confirmar Reserva
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
    let flatpickrInstance;

    // 🔹 Obtener parámetros de la URL
    function getParams() {
        const params = new URLSearchParams(window.location.search);
        
        // Limpieza robusta de modalidad_id
        let modId = params.get('modalidad_id');
        if (modId === 'undefined' || modId === null) modId = '';

        return {
            tipo: params.get('tipo') || '',
            servicio_id: params.get('servicio_id') || '',
            servicio_nombre: params.get('servicio_nombre') || '',
            modalidad_id: modId,
            modalidad_nombre: params.get('modalidad_nombre') || '',
            medico_id: params.get('medico_id') || ''
        };
    }

    // 🔹 Función para agregar el ID del paciente a los datos que se envían
function addPatientIdToData(jsonData) {
    // Obtener portal_usuario_id de la URL si existe
    const urlParams = new URLSearchParams(window.location.search);
    const portalUsuarioId = urlParams.get('portal_usuario_id');
    
    if (portalUsuarioId) {
        jsonData.portal_usuario_id = portalUsuarioId;
    }
    
    return jsonData;
}

    // 🔹 Inicializar todo
    document.addEventListener('DOMContentLoaded', function() {
        reservationData = getParams();
        loadReservationData();
        initializeDatePicker();
        document.getElementById('reservationForm').addEventListener('submit', handleSubmit);
    });

    // 🔹 Mostrar resumen del servicio
    function loadReservationData() {
        if (reservationData.tipo === 'servicio' && reservationData.servicio_nombre) {
            document.getElementById('serviceSummary').innerHTML = `
                <div class="service-title">
                    <i class="fas fa-medical me-2"></i>${reservationData.servicio_nombre}
                </div>`;
        }
    }

    // 🔹 Datepicker
    function initializeDatePicker() {
        flatpickrInstance = flatpickr("#fecha_cita", {
            locale: "es",
            minDate: "today",
            maxDate: new Date().fp_incr(60),
            dateFormat: "Y-m-d",
            onChange: (selectedDates, dateStr) => { if (dateStr) loadAvailableSlots(dateStr); }
        });
    }

    // 🔹 Helper para formato AM/PM
    function formatTime12(time24) {
        if (!time24) return '';
        const [h, m] = time24.split(':');
        let hour = parseInt(h, 10);
        const suffix = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12 || 12; // Convierte 0 a 12
        return `${hour.toString().padStart(2, '0')}:${m} ${suffix}`;
    }

    // 🔹 Generar y mostrar horarios
    async function loadAvailableSlots(fecha) {
        const cont = document.getElementById('timeSlots');
        cont.innerHTML = '<p class="text-muted text-center w-100 m-0" style="grid-column: 1/-1;"><span class="spinner"></span> Cargando horarios...</p>';
        document.getElementById('hora_seleccionada').value = ''; // Limpiar selección previa

        let availableSlots = [];
        try {
            // Se añade servicio_id a la petición para calcular la duración
            const response = await fetch(`horarios_disponibles.php?fecha=${fecha}&modalidad_id=${reservationData.modalidad_id}&servicio_id=${reservationData.servicio_id}&usuario_id=${reservationData.medico_id}`);
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'No se pudo conectar con el servidor de horarios.');
            }
            availableSlots = await response.json();

            if (availableSlots.error) {
                throw new Error(availableSlots.error);
            }

        } catch (error) {
            console.error("Error al cargar horarios:", error);
            cont.innerHTML = `<p class="text-danger text-center w-100 m-0" style="grid-column: 1/-1;">${error.message}</p>`;
            return;
        }

        cont.innerHTML = ''; // Limpiar el contenedor

        if (availableSlots.length === 0) {
            cont.innerHTML = '<p class="text-muted text-center w-100 m-0" style="grid-column: 1/-1;">No hay horarios disponibles para esta fecha.</p>';
            return;
        }
        
        // Agrupar por Mañana / Tarde
        const morning = availableSlots.filter(t => parseInt(t.split(':')[0]) < 12);
        const afternoon = availableSlots.filter(t => parseInt(t.split(':')[0]) >= 12);

        if (morning.length > 0) {
            const h = document.createElement('div');
            h.style.gridColumn = '1/-1';
            h.className = 'text-primary fw-bold mb-2 mt-1';
            h.innerHTML = '<i class="fas fa-sun me-2"></i>Mañana';
            cont.appendChild(h);
            morning.forEach(time => createSlot(time, cont));
        }

        if (afternoon.length > 0) {
            const h = document.createElement('div');
            h.style.gridColumn = '1/-1';
            h.className = 'text-primary fw-bold mb-2 ' + (morning.length > 0 ? 'mt-4' : 'mt-1');
            h.innerHTML = '<i class="fas fa-moon me-2"></i>Tarde';
            cont.appendChild(h);
            afternoon.forEach(time => createSlot(time, cont));
        }
    }

    function createSlot(time, container) {
        // El backend ahora devuelve los horarios disponibles, simplemente los renderizamos
            const div = document.createElement('div');
            div.className = 'time-slot';
            div.textContent = formatTime12(time); // Mostrar formato AM/PM visualmente
            div.onclick = () => selectTimeSlot(time, div);
            container.appendChild(div);
    }

    function selectTimeSlot(time, el) {
        document.querySelectorAll('.time-slot.selected').forEach(s => s.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('hora_seleccionada').value = time;
    }

    // 🔹 Enviar formulario
    async function handleSubmit(e) {
        e.preventDefault();
        if (!validateForm()) return;

        const btn = document.getElementById('submitBtn');
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="spinner"></span> Procesando...';
        btn.disabled = true;

        try {
            const form = e.target;
            
            // 1. Recolectar datos de texto en un objeto JSON
            const jsonData = {
                nombre: form.nombre.value,
                telefono: form.telefono.value,
                email: form.email.value,
                fecha_nacimiento: form.fecha_nacimiento.value,
                fecha_cita: form.fecha_cita.value,
                hora_seleccionada: form.hora_seleccionada.value,
                observaciones: form.observaciones.value,
                atencion_especial: 0, // Valor por defecto eliminado del UI
            };

            // Añadir el ID del paciente a los datos que se envían, si existe
            addPatientIdToData(jsonData);

            // 2. Añadir datos de la reserva al objeto JSON
            if (reservationData.tipo === 'servicio') {
                jsonData.servicio_id = reservationData.servicio_id;
                jsonData.modalidad_id = reservationData.modalidad_id;
                jsonData.tipo_reserva = 'servicio';
            } else {
                jsonData.tipo_reserva = 'paquete';
                jsonData.modalidad_id = '1'; // Modalidad por defecto para paquetes
            }

            // 3. Crear un FormData y añadir el JSON y los archivos
            const formData = new FormData();
            formData.append('json_data', JSON.stringify(jsonData));


            // 4. Enviar el FormData
            const response = await fetch('guardar_reserva_cliente.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                // Usar la URL de redirección que envía el servidor
                showAlert('success', result.message || 'Reserva creada exitosamente. Redirigiendo...');
                setTimeout(() => {
                    window.location.href = '<?php echo $link_cliente; ?>';
                }, 2000);
            } else throw new Error(result.error || 'Error desconocido');
        } catch (err) {
            console.error('Error en reserva:', err);
            showAlert('danger', 'Error: ' + err.message);
        } finally {
            btn.innerHTML = original;
            btn.disabled = false;
        }
    }

    function validateForm() {
        const f = ['nombre','telefono','email','fecha_nacimiento','fecha_cita','hora_seleccionada'];
        for (let id of f) {
            if (!document.getElementById(id).value.trim()) {
                showAlert('danger','Por favor completa todos los campos requeridos.');
                return false;
            }
        }
        if (!document.getElementById('acepta_terminos').checked) {
            showAlert('danger','Debes aceptar los términos.');
            return false;
        }
        return true;
    }

    function showAlert(type, msg) {
        const c = document.getElementById('alertContainer');
        c.innerHTML = `<div class="alert alert-${type} fade-in">${msg}</div>`;
        c.scrollIntoView({behavior:'smooth',block:'center'});
    }
    </script>
</body>
</html>