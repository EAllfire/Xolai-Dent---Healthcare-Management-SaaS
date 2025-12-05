<?php
session_start();
// Recuperar datos del paciente desde la sesión, si existen
$paciente_data = $_SESSION['portal_paciente_data'] ?? null;
?>
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
                                    <div class="col-md-6 mb-3">
                                        <label for="fecha_cita" class="form-label">Fecha Deseada *</label>
                                        <input type="text" class="form-control" id="fecha_cita" name="fecha_cita" placeholder="Seleccionar fecha" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Horarios Disponibles *</label>
                                        <div id="timeSlots" class="time-slots"></div>
                                        <input type="hidden" id="hora_seleccionada" name="hora_seleccionada" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Carga de Documentos -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="fas fa-file-upload"></i> Carga de Documentos (Opcional)
                                </div>
                                <div class="mb-3">
                                    <label for="foto_identificacion" class="form-label">Foto de Identificación (INE/Pasaporte)</label>
                                    <input type="file" class="form-control" id="foto_identificacion" name="foto_identificacion" accept="image/*">
                                </div>
                                <div class="mb-3">
                                    <label for="foto_orden" class="form-label">Foto de Orden Médica</label>
                                    <input type="file" class="form-control" id="foto_orden" name="foto_orden" accept="image/*">
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
        return {
            tipo: params.get('tipo') || '',
            servicio_id: params.get('servicio_id') || '',
            servicio_nombre: params.get('servicio_nombre') || '',
            modalidad_id: params.get('modalidad_id') || '',
            modalidad_nombre: params.get('modalidad_nombre') || ''
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
                </div>
                <div class="service-details">
                    <strong>Modalidad:</strong> ${reservationData.modalidad_nombre}
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

    // 🔹 Generar y mostrar horarios
    async function loadAvailableSlots(fecha) {
        const cont = document.getElementById('timeSlots');
        cont.innerHTML = '<p class="text-muted">Cargando horarios disponibles...</p>';
        document.getElementById('hora_seleccionada').value = ''; // Limpiar selección previa

        let availableSlots = [];
        try {
            // Se añade servicio_id a la petición para calcular la duración
            const response = await fetch(`horarios_disponibles.php?fecha=${fecha}&modalidad_id=${reservationData.modalidad_id}&servicio_id=${reservationData.servicio_id}`);
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
            cont.innerHTML = `<p class="text-danger">${error.message}</p>`;
            return;
        }

        cont.innerHTML = ''; // Limpiar el contenedor

        if (availableSlots.length === 0) {
            cont.innerHTML = '<p class="text-muted">No hay horarios disponibles para esta fecha.</p>';
            return;
        }
        
        // El backend ahora devuelve los horarios disponibles, simplemente los renderizamos
        availableSlots.forEach(time => {
            const div = document.createElement('div');
            div.className = 'time-slot';
            div.textContent = time;
            div.onclick = () => selectTimeSlot(time, div);
            cont.appendChild(div);
        });
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

            const ineFile = form.foto_identificacion.files[0];
            if (ineFile) formData.append('foto_identificacion', ineFile);

            const ordenFile = form.foto_orden.files[0];
            if (ordenFile) formData.append('foto_orden', ordenFile);

            // 4. Enviar el FormData
            const response = await fetch('guardar_reserva_cliente.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                // Usar la URL de redirección que envía el servidor
                showAlert('success', result.message || 'Reserva creada exitosamente. Redirigiendo...');
                if (result.redirect_url) {
                    setTimeout(() => {
                        window.location.href = result.redirect_url;
                    }, 2000); // Redirigir después de 2 segundos
                }
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