<?php
session_start();
// File: agenda/cancelar_paciente.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/debug_log.php';

$cita_id = $_GET['id'] ?? null;
$cita = null;
$mensaje = '';
$error = '';
$cancelacion_exitosa = false;

// --- Lógica de Cancelación (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cita_id_post = $_POST['cita_id'] ?? null;
    if ($cita_id_post) {
        try {
            // 1. El ID del estado "cancelado" es 7
            $estado_cancelado_id = 7;
            
            // 2. Actualizar el estado de la cita
            $stmt_update = $conn->prepare("UPDATE agenda_citas SET estado_id = ? WHERE id = ?");
            $stmt_update->bind_param("ii", $estado_cancelado_id, $cita_id_post);
            
            if ($stmt_update->execute()) {
                $mensaje = "¡Tu cita ha sido cancelada exitosamente!";
                $cancelacion_exitosa = true;
            } else {
                $error = "Error al cancelar la cita. Por favor, intenta de nuevo.";
            }
            $stmt_update->close();
        } catch (Exception $e) {
            log_message("Error al cancelar cita: " . $e->getMessage());
            $error = "Ocurrió un error inesperado.";
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
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $fecha, $hora_inicio, $nota_paciente, $estado_id, $paciente_nombre, $paciente_apellido, $servicio_nombre, $modalidad_nombre, $estado_nombre);
            $stmt->fetch();
            $cita = [
                'id' => $id,
                'fecha' => $fecha,
                'hora_inicio' => $hora_inicio,
                'nota_paciente' => $nota_paciente,
                'estado_id' => $estado_id,
                'paciente_nombre' => $paciente_nombre,
                'paciente_apellido' => $paciente_apellido,
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
    <title>Cancelar Cita - Hospital Angeles</title>
    
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
            --accent-color: #dc3545; /* Red for cancellation */
            --gradient-bg: linear-gradient(135deg, #0f5f85, #1f2937);
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--light-bg);
            padding-top: 80px; /* Space for fixed navbar */
        }
        .navbar {
            background: var(--gradient-bg);
            box-shadow: var(--card-shadow);
        }
        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }
        .logo-text {
            font-size: 1.2rem;
            font-weight: 600;
            margin-left: 10px;
            color: white;
        }
        .main-container {
            padding-top: 4rem;
            padding-bottom: 4rem;
        }
        .cancellation-card {
            background: white;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            padding: 2.5rem;
            max-width: 600px;
            margin: 0 auto;
        }
        .card-header-icon {
            font-size: 2.5rem;
            color: var(--accent-color);
            margin-bottom: 1rem;
        }
        .card-title {
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }
        .info-list {
            list-style: none;
            padding: 0;
        }
        .info-list li {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-list li:last-child {
            border-bottom: none;
        }
        .info-list strong {
            color: #374151;
        }
        .info-list span {
            color: #6b7280;
            text-align: right;
        }
        .btn-cancel {
            background: var(--accent-color);
            border: none;
            border-radius: 50px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
            width: 100%;
            font-size: 1.1rem;
        }
        .btn-cancel:hover {
            background: #b02a37;
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 1rem;
            padding: 0.5em 0.8em;
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="https://angelescuauhtemoc.com/wp-content/uploads/2020/09/logo-50-300x187.png" alt="Hospital Angeles" height="60">
                <div class="logo-text">IMAGENOLOGÍA</div>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container main-container">
        <div class="cancellation-card">
            <div class="text-center">
                <div class="card-header-icon"><i class="fas fa-calendar-times"></i></div>
                <h2 class="card-title">Cancelar Cita</h2>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($mensaje): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>

            <?php if ($cita): ?>
                <ul class="info-list my-4">
                    <li><strong>Paciente:</strong> <span><?php echo htmlspecialchars($cita['paciente_nombre'] . ' ' . $cita['paciente_apellido']); ?></span></li>
                    <li><strong>Servicio:</strong> <span><?php echo htmlspecialchars($cita['servicio_nombre'] ?? 'No especificado'); ?></span></li>
                    <li><strong>Modalidad:</strong> <span><?php echo htmlspecialchars($cita['modalidad_nombre'] ?? 'No especificado'); ?></span></li>
                    <li><strong>Fecha:</strong> <span><?php echo htmlspecialchars(date("d/m/Y", strtotime($cita['fecha']))); ?></span></li>
                    <li><strong>Hora:</strong> <span><?php echo htmlspecialchars(substr($cita['hora_inicio'], 0, 5)); ?></span></li>
                    <li>
                        <strong>Estado:</strong> 
                        <span>
                            <span class="badge rounded-pill <?php echo ($cancelacion_exitosa || $cita['estado_id'] == 7) ? 'bg-danger' : 'bg-warning'; ?> status-badge">
                                <?php echo htmlspecialchars($cancelacion_exitosa ? 'Cancelado' : $cita['estado_nombre']); ?>
                            </span>
                        </span>
                    </li>
                </ul>

                <?php if (!$cancelacion_exitosa && $cita['estado_id'] != 7): ?>
                    <form action="cancelar_paciente.php?id=<?php echo htmlspecialchars($cita_id); ?>" method="POST" class="mt-4">
                        <input type="hidden" name="cita_id" value="<?php echo htmlspecialchars($cita['id']); ?>">
                        <button type="submit" class="btn btn-cancel" onclick="return confirm('¿Estás seguro de que deseas cancelar esta cita?');">
                            <i class="fas fa-times-circle me-2"></i>Confirmar Cancelación
                        </button>
                    </form>
                <?php else: ?>
                    <div class="mt-4">
                        <button type="button" class="btn btn-cancel" disabled>
                            <i class="fas fa-times-circle me-2"></i>Cita Cancelada
                        </button>
                    </div>
                     <div class="text-center mt-4">
                        <a href="/PortaldePacientes/main.php" class="btn btn-secondary">Ir a Mis Citas</a>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>