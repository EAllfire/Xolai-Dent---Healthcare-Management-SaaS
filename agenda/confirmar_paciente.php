<?php
session_start();
// File: agenda/confirmar_paciente.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Ajustar la ruta para que apunte a la carpeta 'includes' dentro de 'agenda'
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/debug_log.php';

$cita_id = $_GET['id'] ?? null;
$cita = null;
$mensaje = '';
$error = '';
$confirmacion_exitosa = false;

// --- Lógica de Confirmación (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cita_id_post = $_POST['cita_id'] ?? null;
    if ($cita_id_post) {
        // First, check the current status
        $stmt_check = $conn->prepare("SELECT estado_id FROM agenda_citas WHERE id = ?");
        $stmt_check->bind_param("i", $cita_id_post);
        $stmt_check->execute();
        $stmt_check->bind_result($current_estado_id);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($current_estado_id == 7) {
            $error = "Esta cita está cancelada y no puede ser confirmada.";
        } else if ($current_estado_id == 2) {
            $mensaje = "Esta cita ya se encontraba confirmada.";
            $confirmacion_exitosa = true; 
        } else {
            try {
                // 1. El ID del estado "confirmado" es 2
                $estado_confirmado_id = 2;
                
                // 2. Actualizar el estado de la cita
                $stmt_update = $conn->prepare("UPDATE agenda_citas SET estado_id = ? WHERE id = ?");
                $stmt_update->bind_param("ii", $estado_confirmado_id, $cita_id_post);
                
                if ($stmt_update->execute()) {
                    $mensaje = "¡Tu cita ha sido confirmada exitosamente!";
                    $confirmacion_exitosa = true;
                } else {
                    $error = "Error al actualizar la cita. Por favor, intenta de nuevo.";
                }
                $stmt_update->close();
            } catch (Exception $e) {
                log_message("Error al confirmar cita: " . $e->getMessage());
                $error = "Ocurrió un error inesperado.";
            }
        }
    }
}

// --- Lógica para Obtener Datos de la Cita (GET o después de POST) ---
if ($cita_id) {
    try {
        $stmt = $conn->prepare("
            SELECT
                c.id, c.fecha, c.hora_inicio, c.nota_paciente, c.estado_id,
                p.nombre, p.apellido,
                s.nombre AS servicio_nombre,
                m.nombre AS modalidad_nombre,
                e.nombre AS estado_nombre
            FROM
                agenda_citas c
            LEFT JOIN portal_pacientes p ON c.paciente_id = p.id
            LEFT JOIN portal_servicios s ON c.servicio_id = s.id
            LEFT JOIN agenda_modalidades m ON c.modalidad_id = m.id
            LEFT JOIN agenda_estado_cita e ON c.estado_id = e.id
            WHERE
                c.id = ?
        ");
        $stmt->bind_param("i", $cita_id);
        $stmt->execute();
        $stmt->store_result(); // Importante para poder usar num_rows

        if ($stmt->num_rows > 0) {
            // Vincular variables de resultado
            $stmt->bind_result($id, $fecha, $hora_inicio, $nota_paciente, $estado_id, $nombre, $apellido, $servicio_nombre, $modalidad_nombre, $estado_nombre);
            $stmt->fetch();
            
            // Crear el array de la cita manualmente
            $cita = [
                'id' => $id,
                'fecha' => $fecha,
                'hora_inicio' => $hora_inicio,
                'nota_paciente' => $nota_paciente,
                'estado_id' => $estado_id,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'servicio_nombre' => $servicio_nombre,
                'modalidad_nombre' => $modalidad_nombre,
                'estado_nombre' => $estado_nombre,
            ];
        } else {
            $error = "La cita no fue encontrada o no tienes permiso para verla.";
        }
        $stmt->close();
        
    } catch (Exception $e) {
        log_message("Error al obtener datos de cita: " . $e->getMessage());
        $error = "Ocurrió un error al cargar los datos de la cita.";
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
    <title>Confirmar Cita - Hospital Angeles</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
        .confirmation-card { background: white; border-radius: 1rem; box-shadow: var(--card-shadow); padding: 2.5rem; max-width: 600px; margin: 0 auto; }
        .card-header-icon { font-size: 2.5rem; color: var(--accent-color); margin-bottom: 1rem; }
        .card-title { font-weight: 700; color: var(--primary-color); margin-bottom: 1.5rem; }
        .info-list { list-style: none; padding: 0; }
        .info-list li { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #e5e7eb; }
        .info-list li:last-child { border-bottom: none; }
        .info-list strong { color: #374151; }
        .info-list span { color: #6b7280; text-align: right; }
        .btn-action { border: none; border-radius: 50px; padding: 0.875rem 2rem; font-weight: 600; transition: all 0.3s ease; color: white; width: 100%; font-size: 1.1rem; }
        .btn-confirm { background: var(--accent-color); }
        .btn-confirm:hover { background: #0a4a6a; transform: translateY(-2px); }
        .status-badge { font-size: 1rem; padding: 0.5em 0.8em; }
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
        <div class="confirmation-card">
            <div class="text-center">
                <i class="fas fa-calendar-check card-header-icon"></i>
                <h2 class="card-title">Confirmación de Cita</h2>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($mensaje): ?>
                <div class="alert alert-success mt-3"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <?php if ($cita): ?>
                <ul class="info-list my-4">
                    <li><strong>Paciente:</strong> <span><?php echo htmlspecialchars($cita['nombre'] . ' ' . $cita['apellido']); ?></span></li>
                    <li><strong>Servicio:</strong> <span><?php echo htmlspecialchars($cita['servicio_nombre'] ?? 'No especificado'); ?></span></li>
                    <li><strong>Modalidad:</strong> <span><?php echo htmlspecialchars($cita['modalidad_nombre'] ?? 'No especificado'); ?></span></li>
                    <li><strong>Fecha:</strong> <span><?php echo htmlspecialchars(date("d/m/Y", strtotime($cita['fecha']))); ?></span></li>
                    <li><strong>Hora:</strong> <span><?php echo htmlspecialchars(substr($cita['hora_inicio'], 0, 5)); ?></span></li>
                    <li>
                        <strong>Estado:</strong> 
                        <span>
                            <?php
                                $estado_nombre_display = $cita['estado_nombre'];
                                $badge_class = 'bg-warning text-dark'; // Pendiente

                                if ($confirmacion_exitosa || $cita['estado_id'] == 2) {
                                    $badge_class = 'bg-success';
                                    $estado_nombre_display = 'Confirmado';
                                } elseif ($cita['estado_id'] == 7) {
                                    $badge_class = 'bg-danger';
                                    $estado_nombre_display = 'Cancelada';
                                }
                            ?>
                            <span class="badge rounded-pill <?php echo $badge_class; ?> status-badge">
                                <?php echo htmlspecialchars($estado_nombre_display); ?>
                            </span>
                        </span>
                    </li>
                </ul>

                <?php if ($cita['estado_id'] == 7): ?>
                    <div class="alert alert-danger mt-4">Esta cita se encuentra cancelada y no puede ser confirmada.</div>
                    <div class="mt-4">
                        <button type="button" class="btn-action btn btn-secondary" disabled>
                            <i class="fas fa-times-circle me-2"></i>Cita Cancelada
                        </button>
                    </div>
                <?php elseif ($confirmacion_exitosa || $cita['estado_id'] == 2): ?>
                    <div class="mt-4">
                        <button type="button" class="btn-action btn btn-success" disabled>
                            <i class="fas fa-check-circle me-2"></i>Cita Confirmada
                        </button>
                    </div>
                    <div class="text-center mt-4">
                        <a href="/PortaldePacientes/main.php" class="btn btn-outline-secondary">Ir a Mis Citas</a>
                    </div>
                <?php else: ?>
                    <form action="confirmar_paciente.php?id=<?php echo htmlspecialchars($cita_id); ?>" method="POST" class="mt-4">
                        <input type="hidden" name="cita_id" value="<?php echo htmlspecialchars($cita['id']); ?>">
                        <button type="submit" class="btn-action btn-confirm">
                            <i class="fas fa-check-circle me-2"></i>Confirmar mi Asistencia
                        </button>
                    </form>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>