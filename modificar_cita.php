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

// Obtener los datos de la cita
$stmt = $conn->prepare(
    "SELECT c.id, c.fecha, c.hora_inicio, c.hora_fin, p.nombre AS paciente_nombre, s.nombre AS servicio_nombre, m.nombre AS modalidad_nombre
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
    <title>Modificar Cita - Hospital Angeles</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="css/cliente.css" rel="stylesheet">
    <style>
        .form-control[readonly] {
            background-color: #e9ecef;
            opacity: 1;
        }
    </style>
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
                    <h1 class="fade-in">Modificar Cita</h1>
                    <p class="fade-in">Ajusta la fecha y hora de tu cita. Si necesitas cambiar el tipo de estudio, por favor cancela esta cita y crea una nueva.</p>
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
                            <form id="form-modificar-cita">
                                <input type="hidden" name="cita_id" value="<?php echo htmlspecialchars($cita['id']); ?>">
                                
                                <div class="mb-4">
                                    <label for="paciente_nombre" class="form-label">Paciente</label>
                                    <input type="text" id="paciente_nombre" class="form-control" value="<?php echo htmlspecialchars($cita['paciente_nombre']); ?>" readonly>
                                </div>

                                <div class="mb-4">
                                    <label for="modalidad_nombre" class="form-label">Modalidad</label>
                                    <input type="text" id="modalidad_nombre" class="form-control" value="<?php echo htmlspecialchars($cita['modalidad_nombre']); ?>" readonly>
                                </div>

                                <div class="mb-4">
                                    <label for="servicio_nombre" class="form-label">Servicio</label>
                                    <input type="text" id="servicio_nombre" class="form-control" value="<?php echo htmlspecialchars($cita['servicio_nombre']); ?>" readonly>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <label for="fecha" class="form-label">Nueva Fecha</label>
                                        <input type="date" id="fecha" name="fecha" class="form-control" value="<?php echo htmlspecialchars($cita['fecha']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <label for="hora_inicio" class="form-label">Nueva Hora</label>
                                        <input type="time" id="hora_inicio" name="hora_inicio" class="form-control" value="<?php echo htmlspecialchars($cita['hora_inicio']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">Guardar Cambios</button>
                                    <a href="cliente.php" class="btn btn-outline-secondary">Cancelar</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.getElementById('form-modificar-cita').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        
        // Añadimos campos que no están en el formulario pero son requeridos por el backend
        // El estado_id se mantiene, y hora_fin se puede recalcular o enviar vacío si el backend lo maneja.
        // Por simplicidad, lo omitimos para que el backend use la duración del servicio.
        formData.append('estado_id', '1'); // Asumimos '1' como estado 'programada'

        try {
            const response = await fetch('actualizar_cita_cliente.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Cita Actualizada',
                    text: 'Tu cita ha sido modificada exitosamente.',
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
                    text: result.error || 'No se pudo actualizar la cita.'
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error de Conexión',
                text: 'Hubo un problema al conectar con el servidor.'
            });
        }
    });
    </script>

</body>
</html>
