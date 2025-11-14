<?php
session_start();
// Simulación de autenticación de cliente
// En un caso real, aquí verificarías la sesión del cliente.
if (!isset($_SESSION['cliente_id'])) {
    // Para pruebas, asignamos un ID de cliente simulado si no existe.
    // Esto debería ser reemplazado por un sistema de login real.
    // $_SESSION['cliente_id'] = 1; 
}

require_once 'includes/db.php';

// Validar que se ha proporcionado un ID de cita
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: No se ha proporcionado un ID de cita.");
}

$cita_id = intval($_GET['id']);

// Obtener los datos de la cita para mostrar en la confirmación
$stmt = $conn->prepare(
    "SELECT c.id, c.fecha, c.hora_inicio, p.nombre AS paciente_nombre, s.nombre AS servicio_nombre, m.nombre AS modalidad_nombre
     FROM agenda_citas c
     JOIN agenda_pacientes p ON c.paciente_id = p.id
     JOIN agenda_servicios s ON c.servicio_id = s.id
     JOIN agenda_modalidades m ON c.modalidad_id = m.id
     WHERE c.id = ?"
);
$stmt->bind_param("i", $cita_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Error: La cita no fue encontrada.");
}

$cita = $result->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Cita - Hospital Angeles</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="css/cliente.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="cliente.php">
                <img src="https://angelescuauhtemoc.com/wp-content/uploads/2020/09/logo-50-300x187.png" alt="Hospital Angeles" height="60">
                <div class="logo-text">IMAGENOLOGÍA</div>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <section class="hero">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="fade-in">Eliminar Cita</h1>
                    <p class="fade-in">Por favor, confirma que deseas eliminar la siguiente cita.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="services-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-body p-5">
                            <h4 class="card-title text-center mb-4">Detalles de la Cita a Eliminar</h4>
                            <p><strong>Paciente:</strong> <?php echo htmlspecialchars($cita['paciente_nombre']); ?></p>
                            <p><strong>Modalidad:</strong> <?php echo htmlspecialchars($cita['modalidad_nombre']); ?></p>
                            <p><strong>Servicio:</strong> <?php echo htmlspecialchars($cita['servicio_nombre']); ?></p>
                            <p><strong>Fecha:</strong> <?php echo htmlspecialchars($cita['fecha']); ?></p>
                            <p><strong>Hora:</strong> <?php echo htmlspecialchars($cita['hora_inicio']); ?></p>
                            
                            <div class="alert alert-warning text-center mt-4" role="alert">
                                ¡Atención! Esta acción no se puede deshacer.
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button id="btn-eliminar-cita" class="btn btn-danger btn-lg">Confirmar Eliminación</button>
                                <a href="cliente.php" class="btn btn-outline-secondary">Cancelar</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.getElementById('btn-eliminar-cita').addEventListener('click', async function() {
        const citaId = <?php echo json_encode($cita_id); ?>;

        Swal.fire({
            title: '¿Estás seguro?',
            text: "¡No podrás revertir esto!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const response = await fetch(`eliminar_cita.php?cita_id=${citaId}`, {
                        method: 'GET' // eliminar_cita.php usa GET
                    });

                    const result = await response.json();

                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Cita Eliminada',
                            text: 'Tu cita ha sido eliminada exitosamente.',
                            timer: 2000,
                            timerProgressBar: true,
                            willClose: () => {
                                window.location.href = 'cliente.php';
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: result.error || 'No se pudo eliminar la cita.'
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Conexión',
                        text: 'Hubo un problema al conectar con el servidor.'
                    });
                }
            }
        });
    });
    </script>

</body>
</html>
