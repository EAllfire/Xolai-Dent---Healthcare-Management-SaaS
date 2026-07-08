<?php
session_start();
// File: agenda/reprogramar_paciente.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/debug_log.php';

$cita_id = $_GET['id'] ?? null;
$cita = null;
$mensaje = '';
$error = '';
$reprogramacion_exitosa = false;

// --- Lógica de Reprogramación (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cita_id_post = $_POST['cita_id'] ?? null;
    $nueva_fecha = $_POST['nueva_fecha'] ?? null;
    $nueva_hora = $_POST['hora_seleccionada'] ?? null; // Cambiado para que coincida con el JS

    if ($cita_id_post && $nueva_fecha && $nueva_hora) {
        try {
            // First, check the current status
            $stmt_check = $conn->prepare("SELECT estado_id, modalidad_id FROM agenda_citas WHERE id = ?");
            $stmt_check->bind_param("i", $cita_id_post);
            $stmt_check->execute();
            $stmt_check->bind_result($current_estado_id, $modalidad_id);
            $stmt_check->fetch();
            $stmt_check->close();

            if ($current_estado_id == 7) {
                $error = "Esta cita está cancelada y no puede ser reprogramada.";
            } elseif ($modalidad_id) {
                // 2. Verificar disponibilidad
                $nueva_hora_inicio = $nueva_hora . ":00";
                $nueva_hora_fin = date("H:i:s", strtotime($nueva_hora_inicio) + 3600); // Asumimos 1 hora

                $stmt_empalme = $conn->prepare("SELECT COUNT(*) as total FROM agenda_citas WHERE fecha = ? AND modalidad_id = ? AND hora_inicio < ? AND hora_fin > ? AND id != ?");
                $stmt_empalme->bind_param("sissi", $nueva_fecha, $modalidad_id, $nueva_hora_fin, $nueva_hora_inicio, $cita_id_post);
                $stmt_empalme->execute();
                $stmt_empalme->bind_result($total_empalme);
                $stmt_empalme->fetch();
                $stmt_empalme->close();

                if ($total_empalme > 0) {
                    $error = "El horario seleccionado ya no está disponible. Por favor, elige otro.";
                } else {
                    // 3. Actualizar la cita
                    $stmt_update = $conn->prepare("UPDATE agenda_citas SET fecha = ?, hora_inicio = ?, hora_fin = ? WHERE id = ?");
                    $stmt_update->bind_param("sssi", $nueva_fecha, $nueva_hora_inicio, $nueva_hora_fin, $cita_id_post);

                    if ($stmt_update->execute()) {
                        $mensaje = "¡Tu cita ha sido reprogramada exitosamente!";
                        $reprogramacion_exitosa = true;
                    } else {
                        $error = "Error al reprogramar la cita.";
                    }
                    $stmt_update->close();
                }
            } else {
                $error = "No se pudo encontrar la cita original.";
            }
        } catch (Exception $e) {
            log_message("Error al reprogramar cita: " . $e->getMessage());
            $error = "Ocurrió un error inesperado.";
        }
    } else {
        $error = "Faltan datos para reprogramar la cita (fecha u hora).";
    }
}

// --- Lógica para Obtener Datos de la Cita (GET o después de POST) ---
if ($cita_id) {
    try {
        $stmt = $conn->prepare("
            SELECT c.id, c.fecha, c.hora_inicio, c.modalidad_id, c.servicio_id, c.estado_id, p.nombre, p.apellido, s.nombre AS servicio_nombre, m.nombre AS modalidad_nombre, e.nombre AS estado_nombre
            FROM agenda_citas c
            LEFT JOIN portal_pacientes p ON c.paciente_id = p.id
            LEFT JOIN portal_servicios s ON c.servicio_id = s.id
            LEFT JOIN agenda_modalidades m ON c.modalidad_id = m.id
            LEFT JOIN agenda_estado_cita e ON c.estado_id = e.id
            WHERE c.id = ?
        ");
        $stmt->bind_param("i", $cita_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $fecha, $hora_inicio, $modalidad_id_db, $servicio_id_db, $estado_id_db, $paciente_nombre, $paciente_apellido, $servicio_nombre, $modalidad_nombre, $estado_nombre);
            $stmt->fetch();
            $cita = [
                'id' => $id,
                'fecha' => $fecha,
                'hora_inicio' => $hora_inicio,
                'modalidad_id' => $modalidad_id_db,
                'servicio_id' => $servicio_id_db,
                'estado_id' => $estado_id_db,
                'paciente_nombre' => $paciente_nombre,
                'paciente_apellido' => $paciente_apellido,
                'servicio_nombre' => $servicio_nombre,
                'modalidad_nombre' => $modalidad_nombre,
                'estado_nombre' => $estado_nombre,
            ];
            if ($reprogramacion_exitosa) {
                $cita['fecha'] = $nueva_fecha;
                $cita['hora_inicio'] = $nueva_hora_inicio;
            }
        } else {
            $error = "La cita no fue encontrada.";
        }
        $stmt->close();
    } catch (Exception $e) {
        log_message("Error al obtener datos de cita: " . $e->getMessage());
        $error = "Ocurrió un error al cargar los datos.";
    }
} else {
    $error = "No se proporcionó un ID de cita.";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reprogramar Cita - Hospital Angeles</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1f2937;
            --secondary-color: #3b82f6;
            --accent-color: #0f5f85;
            --gradient-bg: linear-gradient(135deg, #0f5f85, #1f2937);
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        body { font-family: 'Inter', sans-serif; background: var(--light-bg); padding-top: 80px; }
        .navbar { background: var(--gradient-bg); box-shadow: var(--card-shadow); }
        .navbar-brand, .logo-text { font-weight: 700; color: white !important; }
        .logo-text { font-size: 1.2rem; margin-left: 10px; }
        .main-container { padding-top: 4rem; padding-bottom: 4rem; }
        .reprogram-card { background: white; border-radius: 1rem; box-shadow: var(--card-shadow); padding: 2.5rem; max-width: 600px; margin: 0 auto; }
        .card-header-icon { font-size: 2.5rem; color: var(--accent-color); margin-bottom: 1rem; }
        .card-title { font-weight: 700; color: var(--primary-color); }
        .info-list { list-style: none; padding: 0; }
        .info-list li { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #e5e7eb; }
        .info-list strong, .info-list span { color: #6b7280; }
        .info-list strong { color: #374151; }
        .form-label { font-weight: 500; color: var(--primary-color); }
        .btn-reprogram { background: var(--accent-color); border: none; border-radius: 50px; padding: 0.875rem 2rem; font-weight: 600; color: white; width: 100%; font-size: 1.1rem; }
        .btn-reprogram:hover { background: #0a4a6a; }
        .time-slots { display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 0.5rem; margin-top: 1rem; }
        .time-slot { background: white; border: 2px solid #e5e7eb; border-radius: 8px; padding: 0.5rem; text-align: center; cursor: pointer; transition: all 0.3s ease; font-weight: 500; font-size: 0.9rem; }
        .time-slot:hover { border-color: var(--accent-color); }
        .time-slot.selected { background: var(--accent-color); border-color: var(--accent-color); color: white; }
        .time-slot.unavailable { background: #f3f4f6; border-color: #d1d5db; color: #9ca3af; cursor: not-allowed; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="https://angelescuauhtemoc.com/wp-content/uploads/2020/09/logo-50-300x187.png" alt="Hospital Angeles" height="60">
                <div class="logo-text">IMAGENOLOGÍA</div>
            </a>
        </div>
    </nav>

    <div class="container main-container">
        <div class="reprogram-card">
            <div class="text-center">
                <div class="card-header-icon"><i class="fas fa-calendar-alt"></i></div>
                <h2 class="card-title">Reprogramar Cita</h2>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($mensaje): ?>
                <div class="alert alert-success mt-3"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <?php if ($cita): ?>
                <h4 class="mt-4 mb-3 fs-5 text-secondary">Datos de tu Cita Actual</h4>
                <ul class="info-list mb-4">
                    <li><strong>Paciente:</strong> <span><?php echo htmlspecialchars($cita['paciente_nombre'] . ' ' . $cita['paciente_apellido']); ?></span></li>
                    <li><strong>Servicio:</strong> <span><?php echo htmlspecialchars($cita['servicio_nombre'] ?? 'No especificado'); ?></span></li>
                    <li><strong>Fecha Actual:</strong> <span><?php echo htmlspecialchars(date("d/m/Y", strtotime($cita['fecha']))); ?></span></li>
                    <li><strong>Hora Actual:</strong> <span><?php echo htmlspecialchars(substr($cita['hora_inicio'], 0, 5)); ?></span></li>
                </ul>

                <?php if ($reprogramacion_exitosa): ?>
                     
                <?php elseif ($cita['estado_id'] == 7): ?>
                    <div class="alert alert-danger mt-4">Esta cita se encuentra cancelada y no puede ser reprogramada.</div>
                    <div class="mt-4">
                        <button type="button" class="btn btn-secondary" disabled>
                            <i class="fas fa-times-circle me-2"></i>Cita Cancelada
                        </button>
                    </div>
                <?php else: ?>
                    <hr class="my-4">
                    <h4 class="mb-3 fs-5 text-secondary">Elige tu Nuevo Horario</h4>
                    <form id="reprogramForm" action="reprogramar_paciente.php?id=<?php echo htmlspecialchars($cita_id); ?>" method="POST">
                        <input type="hidden" name="cita_id" value="<?php echo htmlspecialchars($cita['id']); ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nueva_fecha" class="form-label">Nueva Fecha *</label>
                                <input type="text" class="form-control" id="nueva_fecha" name="nueva_fecha" placeholder="Seleccionar fecha" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Horarios Disponibles *</label>
                                <div id="timeSlots" class="time-slots">
                                    <p class="text-muted small">Selecciona una fecha para ver los horarios.</p>
                                </div>
                                <input type="hidden" id="hora_seleccionada" name="hora_seleccionada" required>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-reprogram">
                                <i class="fas fa-check-circle me-2"></i>Reprogramar Cita
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Solo inicializar si tenemos una cita y el formulario está presente
            if (document.getElementById('reprogramForm')) {
                const modalidadId = <?php echo $cita['modalidad_id'] ?? 'null'; ?>;
                if(modalidadId) {
                    initializeDatePicker(modalidadId);
                }
            }
        });

        function initializeDatePicker(modalidadId) {
            flatpickr("#nueva_fecha", {
                locale: "es",
                minDate: "today",
                maxDate: new Date().fp_incr(60),
                dateFormat: "Y-m-d",
                disable: [date => date.getDay() === 0], // No domingos
                onChange: (selectedDates, dateStr) => {
                    if (dateStr) {
                        loadAvailableSlots(dateStr, modalidadId);
                    }
                }
            });
        }

        async function loadAvailableSlots(fecha, modalidadId) {
            const slotsContainer = document.getElementById('timeSlots');
            slotsContainer.innerHTML = '<p class="text-muted small">Cargando horarios...</p>';
            document.getElementById('hora_seleccionada').value = ''; // Limpiar selección previa

            let availableSlots = [];
            try {
                // El endpoint necesita el ID de la cita actual para excluirla de los horarios ocupados
                const citaId = <?php echo $cita['id'] ?? 'null'; ?>;
                const servicioId = <?php echo $cita['servicio_id'] ?? 'null'; ?>;
                const response = await fetch(`horarios_disponibles.php?fecha=${fecha}&modalidad_id=${modalidadId}&servicio_id=${servicioId}&cita_id=${citaId}`);
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || 'No se pudo conectar con el servidor de horarios.');
                }
                availableSlots = await response.json();

                if (availableSlots.error) {
                    throw new Error(availableSlots.error);
                }

                slotsContainer.innerHTML = '';

                if (availableSlots.length === 0) {
                    slotsContainer.innerHTML = '<p class="text-danger small">No hay horarios disponibles para esta fecha.</p>';
                    return;
                }
                
                availableSlots.forEach(time => {
                    const div = document.createElement('div');
                    div.className = 'time-slot';
                    div.textContent = time;
                    div.onclick = () => selectTimeSlot(time, div);
                    slotsContainer.appendChild(div);
                });

            } catch (error) {
                console.error("Error al cargar horarios:", error);
                slotsContainer.innerHTML = '<p class="text-danger small">No se pudieron cargar los horarios.</p>';
            }
        }

        function selectTimeSlot(time, el) {
            document.querySelectorAll('.time-slot.selected').forEach(s => s.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('hora_seleccionada').value = time;
        }
    </script>
</body>
</html>
