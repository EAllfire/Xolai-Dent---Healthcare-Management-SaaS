<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!puedeRealizar('configurar_sistema')) {
    header('Location: index.php');
    exit;
}

$usuario = obtenerUsuarioActual();
$user_nombre = $usuario['nombre'] ?? 'Usuario';
$user_tipo = $usuario['tipo'] ?? 'usuario';

// Configuración del header
$show_calendar = true;
$show_back = true;
$show_admin_tools = true;

// Cargar configuración actual
$config = [];
try {
    $result = $conn->query("SELECT config_key, config_value FROM agenda_configuracion");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $config[$row['config_key']] = $row['config_value'];
        }
    }
} catch (Exception $e) {
    // Si falla (ej. tabla no existe), usamos defaults sin romper la página
    error_log("Advertencia: No se pudo cargar la configuración: " . $e->getMessage());
}

$slot_interval = $config['slot_interval'] ?? '30';
$blocked_times_json = $config['blocked_times'] ?? '[]';
$blocked_times = json_decode($blocked_times_json, true);
if (!is_array($blocked_times)) $blocked_times = [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Horarios - Hospital Angeles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #000000;
            color: #e5e7eb;
            padding-top: 100px;
        }

        /* Header Styles */
        .main-header {
            background: rgba(5, 5, 5, 0.95);
            backdrop-filter: blur(10px);
            color: white;
            height: 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
            font-family: Arial, sans-serif;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5);
            border-bottom: 1px solid rgba(41, 121, 255, 0.1);
        }
        
        .header-left, .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-section {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            text-align: center;
        }
        
        .header-logo img {
            max-height: 60px;
            margin-left: 10px;
            width: auto;
            filter: drop-shadow(1px 1px 2px rgba(0,0,0,0.1)) brightness(1.1);
        }
        
        .logo-text {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            letter-spacing: 0.5px;
            text-align: center;
        }
        
        .btn-header {
            color: #e5e7eb;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            background: rgba(41, 121, 255, 0.1);
            border: 1px solid rgba(41, 121, 255, 0.2);
            padding: 0.5rem 1rem;
            font-size: 13px;
            cursor: pointer;
            border-radius: 6px;
            text-shadow: 0 0 5px rgba(41, 121, 255, 0.3);
        }
        
        .btn-header:hover {
            background: rgba(41, 121, 255, 0.2);
            color: #fff;
            border-color: rgba(41, 121, 255, 0.5);
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.2);
        }

        .container-custom { max-width: 900px; margin: 0 auto; padding: 0 15px; }
        
        .config-card {
            background: #0a0a0a;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.5);
            text-align: center;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 0.5rem;
            color: #e5e7eb;
        }
        
        .time-block {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #1f2937;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #374151;
        }
        
        .time-block span { font-weight: 500; }
        
        /* Inputs */
        .form-control {
            background: #000000;
            border: 1px solid #333;
            color: #e5e7eb;
            border-radius: 8px;
        }
        
        .form-control:focus {
            background: #000000;
            color: #fff;
            border-color: #2979ff;
            box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2);
        }
        
        .btn-primary {
            background: #2979ff;
            border-color: #2979ff;
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.3);
        }
        
        .btn-primary:hover {
            background: #2962ff;
            border-color: #2962ff;
            box-shadow: 0 0 15px rgba(41, 121, 255, 0.5);
        }
        
        .btn-secondary {
            background: #1f2937;
            border-color: #374151;
            color: #e5e7eb;
        }
        
        .btn-secondary:hover {
            background: #374151;
            border-color: #4b5563;
        }
        
        .btn-info {
            background: #0ea5e9;
            border-color: #0ea5e9;
            color: white;
        }
        
        .btn-info:hover {
            background: #0284c7;
            border-color: #0284c7;
        }
        
        .text-muted { color: #9ca3af !important; }
        
        hr { border-top: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container-custom">
        <h1 class="page-title">Configuración de Horarios Disponibles</h1>

        <div class="config-card">
            <div id="alert-container"></div>

            <!-- Intervalo de Slots -->
            <div class="mb-4">
                <h5 class="section-title"><i class="fas fa-hourglass-half mr-2"></i>Intervalo entre Horarios</h5>
                <p class="text-muted">Define cada cuántos minutos se mostrará un horario disponible al cliente (ej. 8:00, 8:30, 9:00).</p>
                <div class="form-group">
                    <label for="slot_interval">Intervalo de tiempo (en minutos)</label>
                    <select id="slot_interval" class="form-control" style="max-width: 200px;">
                        <option value="15" <?php echo $slot_interval == '15' ? 'selected' : ''; ?>>15 minutos</option>
                        <option value="30" <?php echo $slot_interval == '30' ? 'selected' : ''; ?>>30 minutos</option>
                        <option value="60" <?php echo $slot_interval == '60' ? 'selected' : ''; ?>>1 hora</option>
                    </select>
                </div>
            </div>

            <hr class="my-4">

            <!-- Bloqueo de Horarios -->
            <div>
                <h5 class="section-title"><i class="fas fa-ban mr-2"></i>Bloqueo de Tramos Horarios</h5>
                <p class="text-muted">Define los periodos fijos en los que no se deben ofrecer citas (ej. hora de comida). Estos bloqueos se aplican a todos los días.</p>
                
                <div id="blocked-times-list" class="mb-3">
                    <!-- Los bloques se renderizarán aquí -->
                </div>

                <h6>Añadir Nuevo Bloqueo</h6>
                <div class="form-row align-items-end">
                    <div class="col">
                        <label for="new_start_time">Hora de Inicio</label>
                        <input type="time" id="new_start_time" class="form-control">
                    </div>
                    <div class="col">
                        <label for="new_end_time">Hora de Fin</label>
                        <input type="time" id="new_end_time" class="form-control">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-info" onclick="addBlockedTime()">
                            <i class="fas fa-plus"></i> Añadir
                        </button>
                    </div>
                </div>
            </div>

            <hr class="my-5">

            <div class="d-flex justify-content-between">
                 <a href="panel_admin.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
                <button class="btn btn-primary btn-lg" onclick="saveSettings()">
                    <i class="fas fa-save"></i> Guardar Configuración
                </button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let blockedTimes = <?php echo $blocked_times_json; ?>;

        document.addEventListener('DOMContentLoaded', function() {
            renderBlockedTimes();
        });

        function renderBlockedTimes() {
            const list = document.getElementById('blocked-times-list');
            list.innerHTML = '';
            if (blockedTimes.length === 0) {
                list.innerHTML = '<p class="text-muted small">No hay periodos de bloqueo definidos.</p>';
            } else {
                blockedTimes.forEach((block, index) => {
                    const div = document.createElement('div');
                    div.className = 'time-block';
                    div.innerHTML = `
                        <span>De <strong>${block.inicio}</strong> a <strong>${block.fin}</strong></span>
                        <button class="btn btn-sm btn-danger ml-auto" onclick="removeBlockedTime(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                    list.appendChild(div);
                });
            }
        }

        function addBlockedTime() {
            const start = document.getElementById('new_start_time').value;
            const end = document.getElementById('new_end_time').value;

            if (!start || !end) {
                alert('Por favor, selecciona una hora de inicio y de fin.');
                return;
            }

            if (start >= end) {
                alert('La hora de inicio debe ser anterior a la hora de fin.');
                return;
            }

            blockedTimes.push({ inicio: start, fin: end });
            blockedTimes.sort((a, b) => a.inicio.localeCompare(b.inicio)); // Ordenar
            
            renderBlockedTimes();

            // Limpiar inputs
            document.getElementById('new_start_time').value = '';
            document.getElementById('new_end_time').value = '';
        }

        function removeBlockedTime(index) {
            if (confirm('¿Estás seguro de que quieres eliminar este bloqueo?')) {
                blockedTimes.splice(index, 1);
                renderBlockedTimes();
            }
        }

        function saveSettings() {
            const interval = document.getElementById('slot_interval').value;
            
            const settings = {
                slot_interval: interval,
                blocked_times: JSON.stringify(blockedTimes)
            };

            fetch('citas/guardar_config_horarios.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(settings)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', '¡Configuración guardada exitosamente!');
                } else {
                    showAlert('danger', 'Error al guardar: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Ocurrió un error de conexión.');
            });
        }

        function showAlert(type, message) {
            const container = document.getElementById('alert-container');
            container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>`;
            
            setTimeout(() => {
                $('.alert').alert('close');
            }, 5000);
        }
    </script>
</body>
</html>
