<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Verificar permisos: permitir acceso a admin y médicos
$tipo_usuario = $_SESSION['usuario_tipo'] ?? '';

if (!puedeRealizar('ver_catalogo_pacientes') && $tipo_usuario !== 'medico' && $tipo_usuario !== 'dentista') {
    header('Location: index.php');
    exit;
}

$usuario = obtenerUsuarioActual();

// Variables para el header
$user_nombre = $usuario['nombre'] ?? 'Usuario';
$user_tipo = $usuario['tipo'] ?? 'usuario';
$puede_ver_admin = in_array($user_tipo, ['admin', 'medico', 'dentista']);
$es_admin = ($user_tipo === 'admin');

// Configuración del header
$show_calendar = true;
$show_back = true;
$show_admin_tools = true;

// Permisos para gestionar quién recomendó al paciente
$es_dentista_padre = ($user_tipo === 'dentista' && empty($_SESSION['id_padre']));
$mostrarGestionRecomendacion = ($user_tipo === 'admin' || $user_tipo === 'superadmin' || $es_dentista_padre);

// Determinar el tipo de entorno (dental o médico) basado en el padre
$id_padre = $_SESSION['id_padre'] ?? null;
$padre_tipo = '';
if (!empty($id_padre)) {
    $stmt_p = $conn->prepare("SELECT tipo FROM agenda_usuarios WHERE id = ?");
    $stmt_p->bind_param("i", $id_padre);
    $stmt_p->execute();
    $stmt_p->bind_result($padre_tipo);
    $stmt_p->fetch();
    $stmt_p->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Pacientes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo2.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #000000;
            color: #e5e7eb;
            padding-top: 0;
        }
        
        /* Header Styles */
        .main-header {
            background: rgba(10, 10, 10, 0.5);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            mask-image: linear-gradient(to bottom, black 70%, transparent 100%);
            -webkit-mask-image: linear-gradient(to bottom, black 70%, transparent 100%);
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
            border-bottom: none;
            pointer-events: none; /* Hacer transparente a clicks */
        }
        
        .header-left, .header-center, .header-right {
            pointer-events: auto; /* Reactivar clicks en elementos del header */
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
        }
        
        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: white;
            letter-spacing: 1px;
        }
        
        .nav-link {
            color: #a0a0a0;
            font-weight: 500;
            font-size: 15px;
            padding: 8px 16px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        
        .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.12);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
            font-weight: 500;
        }
        
        .btn-header {
            color: #e5e7eb;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-header:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.25);
        }

        .container-custom {
            max-width: 1400px;
            margin: 0 auto;
            padding: 100px 15px 0 15px; /* Added padding-top to compensate for body padding removal */
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #e5e7eb;
        }

        .actions-bar {
            background: transparent;
            padding: 1.5rem;
            border-radius: 0;
            box-shadow: none;
            border: none;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn-glass {
            padding: 10px 20px;
            color: #e5e7eb;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            background: rgba(41, 121, 255, 0.1);
            border: 1px solid rgba(41, 121, 255, 0.2);
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            text-shadow: 0 0 5px rgba(41, 121, 255, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-glass:hover {
            background: rgba(41, 121, 255, 0.2);
            color: #fff;
            border-color: rgba(41, 121, 255, 0.5);
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.2);
            transform: translateY(-2px);
            text-decoration: none;
        }

        /* Grid de Pacientes */
        .patients-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        .patient-card {
            background: #0e0e0e;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s, border-color 0.2s;
        }
        
        .patient-card:hover {
            transform: translateY(-5px);
            border-color: #2979ff;
        }
        
        .patient-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        .patient-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%; /* Circular */
            background-color: #1a1a1a;
            border: 2px solid #333;
            flex-shrink: 0;
        }
        
        .patient-name {
            color: #e5e7eb;
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            text-align: left;
            line-height: 1.2;
        }
        
        .patient-info-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 14px;
            color: #9ca3af;
        }
        
        .patient-info-item i {
            width: 20px;
            margin-right: 8px;
            color: #2979ff;
        }
        
        .patient-actions {
            margin-top: auto;
            padding-top: 15px;
            border-top: 1px solid #222;
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }
        
        .btn-card-action {
            flex: 1;
            padding: 8px 10px;
            color: #e5e7eb;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            background: rgba(41, 121, 255, 0.1);
            border: 1px solid rgba(41, 121, 255, 0.2);
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            text-shadow: 0 0 5px rgba(41, 121, 255, 0.3);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-card-action:hover {
            background: rgba(41, 121, 255, 0.2);
            color: #fff;
            border-color: rgba(41, 121, 255, 0.5);
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.2);
            transform: translateY(-2px);
            text-decoration: none;
        }

        .btn-card-action.btn-delete:hover {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.5);
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.2);
        }

        /* Inputs y Buscador */
        .form-control {
            background: #000;
            color: #e5e7eb;
            padding: 14px 18px;
            border: 1px solid #333;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .form-control:focus {
            outline: none;
            border-color: #2979ff;
            box-shadow: 0 0 0 3px rgba(41, 121, 255, 0.2);
            background: #000;
            color: #fff;
        }

        .input-group-text {
            background: #000;
            border-color: #333;
            color: #9ca3af;
            border-right: none;
        }
        
        .input-group > .form-control {
            border-left: none;
        }

        /* Botones */
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
        
        .btn-success {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }
        
        .btn-danger {
            background: #ef4444;
            border-color: #ef4444;
        }
        
        /* Modal Style - Login Theme */
        .modal-content {
            background: #000000;
            border: 1px solid #333;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.9);
        }
        
        .modal-header {
            border-bottom: 1px solid #222;
            background: transparent;
            padding: 24px 32px;
        }
        
        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #e5e7eb;
            letter-spacing: -0.5px;
        }
        
        .modal-body {
            padding: 32px;
            background: transparent;
        }
        
        .modal-footer {
            border-top: 1px solid #222;
            background: transparent;
            padding: 24px 32px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        .close {
            color: #9ca3af;
            opacity: 1;
            font-size: 28px;
            font-weight: 300;
            text-shadow: none;
            transition: color 0.2s;
        }
        
        .close:hover {
            color: #fff;
        }

        /* Etiquetas del formulario en modal */
        .modal-body label {
            color: #9ca3af;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        /* Botones del Modal */
        .btn-modal-primary {
            padding: 12px 24px;
            color: #e5e7eb;
            font-weight: 600;
            background: rgba(41, 121, 255, 0.1);
            border: 1px solid rgba(41, 121, 255, 0.2);
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s;
            text-shadow: 0 0 10px rgba(41, 121, 255, 0.3);
        }
        .btn-modal-primary:hover {
            background: rgba(41, 121, 255, 0.2);
            color: #fff;
            border-color: rgba(41, 121, 255, 0.5);
            box-shadow: 0 0 15px rgba(41, 121, 255, 0.3);
            transform: translateY(-1px);
        }

        .btn-modal-secondary {
            padding: 12px 24px;
            color: #9ca3af;
            background: transparent;
            border: 1px solid #333;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-modal-secondary:hover {
            background: #111;
            color: #e5e7eb;
            border-color: #555;
        }
        
        .badge-info {
            background: rgba(41, 121, 255, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(41, 121, 255, 0.3);
        }
        
        .badge-secondary {
            background: #374151;
            color: #e5e7eb;
            border: 1px solid #4b5563;
        }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <img src="images/Xolai.png" alt="Xolai Logo" class="header-logo-img">
            <span class="header-title">Xolai</span>
        </div>
        <nav class="header-center">
            <a href="home.php" class="nav-link">Inicio</a>
            <a href="index.php" class="nav-link">Agenda</a>
            <a href="catalogo_pacientes.php" class="nav-link">Pacientes</a>
            <a href="pagos.php" class="nav-link">Pagos</a>
            <?php if ($es_admin): ?>
                <a href="panel_admin.php" class="nav-link">Administración</a>
            <?php endif; ?>
        </nav>
        <div class="header-right">
            <div class="user-info">
                <span><?php echo htmlspecialchars($user_nombre); ?></span>
                <i class="fas fa-user-circle"></i>
            </div>

            <!-- Settings Dropdown -->
            <?php if ($puede_ver_admin): ?>
            <div class="settings-container ml-3">
                <button onclick="toggleSettingsDropdown()" class="btn-header" title="Configuración">
                    <i class="fas fa-cog"></i>
                </button>
                <div id="ajustesDropdown" class="custom-dropdown-menu">
                    <a href="catalogo_servicios.php"><i class="fas fa-stethoscope"></i> Servicios</a>
                    <a href="admin_modalidades.php"><i class="fas fa-layer-group"></i> Modalidades</a>
                    <?php if (in_array($user_tipo, ['admin', 'dentista'])): ?>
                        <a href="admin_usuarios.php"><i class="fas fa-users"></i> Gestionar Equipo</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="header-buttons">
                <a href="logout.php" class="btn-header">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container-custom">
        <h1 class="page-title">Catálogo de Pacientes</h1>
        
        <div class="actions-bar">
            <div>
                <a href="<?php echo ($user_tipo === 'admin') ? 'panel_admin.php' : 'home.php'; ?>" class="btn-glass mr-2"><i class="fas fa-arrow-left"></i> Volver al <?php echo ($user_tipo === 'admin') ? 'Panel' : 'Inicio'; ?></a>
                <button class="btn-glass" onclick="abrirModalNuevoPaciente()">
                    <i class="fas fa-plus"></i> Nuevo Paciente
                </button>
            </div>
            <div class="input-group" style="max-width: 400px;">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                </div>
                <input type="text" id="searchInput" class="form-control" placeholder="Buscar por nombre, apellido, teléfono o correo...">
            </div>
            <div>
                <span id="total-pacientes" class="text-muted">Cargando...</span>
            </div>
        </div>
        
        <div class="patients-grid-container">
            <div id="loading" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Cargando pacientes...</div>
            <div id="pacientes-grid" class="patients-grid" style="display: none;"></div>
            <div id="empty-state" class="text-center p-5" style="display: none;">
                <i class="fas fa-users fa-3x mb-3"></i>
                <h4>No hay pacientes registrados</h4>
                <p>Comience agregando su primer paciente al catálogo.</p>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo/Editar Paciente -->
    <div class="modal fade" id="modalPaciente" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPacienteTitle">Nuevo Paciente</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="formPaciente">
                        <input type="hidden" id="paciente_id" name="id">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="nombre">Nombre(s) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                            <div class="col-md-6 form-group">
                                 <label for="apellido_paterno">Apellido Paterno <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" required>
                           
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                               <label for="apellido_materno">Apellido Materno <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="telefono">Teléfono (Celular) <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="telefono" name="telefono" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="correo">Correo electrónico</label>
                                <input type="email" class="form-control" id="correo" name="correo"> 
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="tel_emergencia">Teléfono de Emergencia</label>
                                <input type="tel" class="form-control" id="tel_emergencia" name="tel_emergencia">
                            </div>
                             </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="rfc">RFC</label>
                                <input type="text" class="form-control" id="rfc" name="rfc" maxlength="13">
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                                <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="direccion">Dirección</label>
                            <input type="text" class="form-control" id="direccion" name="direccion">
                        </div>
                        <div class="form-group">
                            <label for="motivo_consulta">Motivo de consulta</label>
                            <textarea class="form-control" id="motivo_consulta" name="motivo_consulta" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="alergias">Alergias</label>
                                <input type="text" class="form-control" id="alergias" name="alergias" placeholder="Ejem: Penicilina, látex...">
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="medicamentos">Medicamentos que consume actualmente</label>
                                <input type="text" class="form-control" id="medicamentos" name="medicamentos">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="tipo_id">Origen</label>
                                <select class="form-control" id="tipo_id" name="tipo_id">
                                    <!-- Opciones se cargarán dinámicamente -->
                                </select>
                            </div>
                            <div class="col-md-4 form-group" id="recomendado_doctor_div" style="display:none;">
                                <label for="recomendado_doctor_nombre" id="recomendado_doctor_label">Nombre del Doctor</label>
                                <input type="text" class="form-control" id="recomendado_doctor_nombre" name="recomendado_doctor_nombre" placeholder="Nombre del doctor">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="comentarios">Comentarios adicionales</label>
                            <textarea class="form-control" id="comentarios" name="comentarios" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn-modal-primary" onclick="guardarPaciente()">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let pacientes = [];
        let tiposPaciente = [];
        const user_tipo = <?php echo json_encode($user_tipo); ?>;

        $(document).ready(function() {
            cargarPacientes();
            cargarOrigenesRecomendacion(); // Cargar los orígenes de recomendación en el select de origen

            $('#tipo_id').on('change', function() {
                verificarOrigenDoctor();
            });

            $('#searchInput').on('keyup', function() {
                const searchTerm = $(this).val().toLowerCase();
                const pacientesFiltrados = pacientes.filter(paciente => {
                    return (paciente.nombre_solo && paciente.nombre_solo.toLowerCase().includes(searchTerm)) ||
                           (paciente.apellido_paterno && paciente.apellido_paterno.toLowerCase().includes(searchTerm)) ||
                           (paciente.apellido_materno && paciente.apellido_materno.toLowerCase().includes(searchTerm)) ||
                           (paciente.telefono && paciente.telefono.toLowerCase().includes(searchTerm)) ||
                           (paciente.correo && paciente.correo.toLowerCase().includes(searchTerm));
                });
                renderizarPacientes(pacientesFiltrados);
                actualizarContador(pacientesFiltrados.length, pacientes.length);
            });
        });

        // Función para cargar los tipos de paciente como orígenes en el select de origen
        function cargarOrigenesRecomendacion() {
            fetch('tipos_paciente_json.php')
                .then(response => response.json())
                .then(data => {
                    const select = $('#tipo_id');
                    select.empty();
                    select.append('<option value="">-- Seleccionar --</option>'); // Opción por defecto
                    data.forEach(tipo => {
                        select.append(`<option value="${tipo.id}" data-nombre="${tipo.nombre}">${tipo.nombre}</option>`);
                    });
                    select.append('<option value="DOCTOR">Doctor</option>'); // Siempre añadir la opción de Doctor
                    select.append('<option value="PERSONA">Persona</option>'); // Siempre añadir la opción de Persona
                })
                .catch(error => {
                    console.error('Error al cargar orígenes de recomendación:', error);
                    $('#tipo_id').html('<option value="">Error al cargar</option>');
                });
        }
        function verificarOrigenDoctor() {
            const val = $('#tipo_id').val();
            const label = $('#recomendado_doctor_label');
            if (val === 'DOCTOR') {
                label.text('Nombre del Doctor');
                $('#recomendado_doctor_nombre').attr('placeholder', 'Nombre del doctor');
                $('#recomendado_doctor_div').fadeIn();
            } else if (val === 'PERSONA') {
                label.text('Nombre de la Persona');
                $('#recomendado_doctor_nombre').attr('placeholder', 'Nombre de la persona');
                $('#recomendado_doctor_div').fadeIn();
            } else {
                $('#recomendado_doctor_div').hide();
                $('#recomendado_doctor_nombre').val('');
            }
        }

        function cargarPacientes() {
            $('#loading').show();
            $('#pacientes-table').hide();
            $('#empty-state').hide();
            
            fetch('pacientes_json.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    pacientes = data;
                    renderizarPacientes();
                    actualizarContador();
                })
                .catch(error => {
                    console.error('Error al cargar pacientes:', error);
                    $('#loading').hide();
                    $('#empty-state').html(`<h4>Error al cargar pacientes</h4><p>${error.message}</p>`).show();
                });
        }

        function renderizarPacientes(listaPacientes) {
            const lista = listaPacientes || pacientes;
            const grid = document.getElementById('pacientes-grid');
            grid.innerHTML = '';
            $('#loading').hide();

            if (!Array.isArray(lista) || lista.length === 0) {
                $('#pacientes-grid').hide();
                $('#empty-state').show();
                return;
            }

            $('#empty-state').hide();
            $('#pacientes-grid').show();
            
            lista.forEach(paciente => {
                // Redirigir a expediente dental si es dentista (cualquier tipo) o admin derivado
               // Redirigir a expediente dental si es dentista o admin derivado de un dentista
                const padre_tipo = <?php echo json_encode($padre_tipo); ?>;
                const esEntornoDental = (user_tipo === 'dentista' || user_tipo === 'dentista_externo' || ((user_tipo === 'admin' || user_tipo === 'recepcion') && padre_tipo === 'dentista'));
                const expedienteUrl = esEntornoDental ? 'expediente_dentista.php' : 'expediente_clinico.php';
                const seed = encodeURIComponent((paciente.nombre_solo || '') + (paciente.apellido_paterno || '') + (paciente.apellido_materno || ''));
                const avatarUrl = `https://api.dicebear.com/7.x/identicon/svg?seed=${seed}&backgroundColor=transparent`;

                const card = document.createElement('div');
                card.className = 'patient-card';
                card.innerHTML = `
                    <div class="patient-header">
                        <img src="${avatarUrl}" alt="Identicon" class="patient-avatar">
                        <h5 class="patient-name">${paciente.nombre_solo || ''} ${paciente.apellido_paterno || ''} ${paciente.apellido_materno || ''}</h5>
                    </div>
                    <div class="patient-info-item">
                        <i class="fas fa-phone"></i> ${paciente.telefono || 'Sin teléfono'}
                    </div>
                    <div class="patient-info-item">
                        <i class="fas fa-envelope"></i> ${paciente.correo || 'Sin correo'}
                    </div>
                    <div class="patient-info-item">
                        <i class="fas fa-bullhorn"></i> 
                        <span class="badge badge-info ml-1">
                            ${paciente.origen && paciente.origen.startsWith('DOCTOR:') ? paciente.origen.replace('DOCTOR:', 'Doctor: ') : paciente.origen && paciente.origen.startsWith('PERSONA:') ? paciente.origen.replace('PERSONA:', 'Persona: ') : (paciente.origen || 'Otro')}
                        </span>
                    </div>
                    
                    <div class="patient-actions">
                        <button class="btn-card-action" onclick="editarPaciente(${paciente.id})" title="Editar"><i class="fas fa-edit"></i></button>
                        <a href="${expedienteUrl}?id=${paciente.id}" class="btn-card-action" title="Expediente"><i class="fas fa-file-medical"></i></a>
                        <button class="btn-card-action btn-delete" onclick="eliminarPaciente(${paciente.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </div>
                `;
                grid.appendChild(card);
            });
        }

        function actualizarContador(totalFiltrado, totalOriginal) {
            const totalPacientes = totalOriginal !== undefined ? totalOriginal : (Array.isArray(pacientes) ? pacientes.length : 0);
            
            if (totalFiltrado !== undefined && totalFiltrado !== totalPacientes) {
                 document.getElementById('total-pacientes').textContent = `Mostrando ${totalFiltrado} de ${totalPacientes} pacientes`;
            } else {
                 document.getElementById('total-pacientes').textContent = `${totalPacientes} pacientes registrados`;
            }
        }

        function abrirModalNuevoPaciente() {
            document.getElementById('modalPacienteTitle').textContent = 'Nuevo Paciente';
            document.getElementById('formPaciente').reset();
            document.getElementById('paciente_id').value = '';
            $('#recomendado_doctor_div').hide();
            $('#modalPaciente').modal('show');
        }

        function editarPaciente(id) {
            const paciente = pacientes.find(p => p.id == id);
            if (!paciente) return;
            
            document.getElementById('modalPacienteTitle').textContent = 'Editar Paciente';
            document.getElementById('paciente_id').value = paciente.id;
            document.getElementById('nombre').value = paciente.nombre_solo || '';
            document.getElementById('apellido_paterno').value = paciente.apellido_paterno || '';
            document.getElementById('apellido_materno').value = paciente.apellido_materno || '';
            document.getElementById('telefono').value = paciente.telefono || '';
            document.getElementById('correo').value = paciente.correo || '';
            document.getElementById('tipo_id').value = ''; // El select ahora representa el origen
            document.getElementById('fecha_nacimiento').value = paciente.fecha_nacimiento || '';
            
            if (paciente.origen && paciente.origen.startsWith('DOCTOR:')) {
                $('#tipo_id').val('DOCTOR');
                $('#recomendado_doctor_nombre').val(paciente.origen.replace('DOCTOR:', '').trim());
                $('#recomendado_doctor_div').show();
            } else if (paciente.origen && paciente.origen.startsWith('PERSONA:')) {
                $('#tipo_id').val('PERSONA');
                $('#recomendado_doctor_nombre').val(paciente.origen.replace('PERSONA:', '').trim());
                $('#recomendado_doctor_div').show();
            } else if (paciente.recomendado_por_id) {
                $('#tipo_id').val(paciente.recomendado_por_id);
                $('#recomendado_doctor_div').hide();
            } else {
                $('#tipo_id').val('');
                $('#recomendado_doctor_div').hide();
            }

            document.getElementById('tel_emergencia').value = paciente.tel_emergencia || '';
            document.getElementById('rfc').value = paciente.rfc || '';
            document.getElementById('direccion').value = paciente.direccion || '';
            document.getElementById('motivo_consulta').value = paciente.motivo_consulta || '';
            document.getElementById('alergias').value = paciente.diagnostico || '';
            document.getElementById('medicamentos').value = paciente.medicamentos || '';
            document.getElementById('comentarios').value = paciente.comentarios || '';

            $('#modalPaciente').modal('show');
        }

        function guardarPaciente() {
            const form = document.getElementById('formPaciente');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const isEdit = document.getElementById('paciente_id').value !== '';
            const url = isEdit ? 'citas/actualizar_paciente.php' : 'citas/guardar_paciente.php';

            const formData = new FormData(form);
            const origenSelect = document.getElementById('tipo_id');
            const selectedOption = origenSelect.options[origenSelect.selectedIndex];
            const origenValue = formData.get('tipo_id');

            if (origenValue === 'DOCTOR') {
                const nombreDoc = formData.get('recomendado_doctor_nombre');
                formData.set('origen', 'DOCTOR: ' + (nombreDoc ? nombreDoc.trim() : ''));
                formData.set('recomendado_por_id', '');
                formData.set('tipo_id', '');
            } else if (origenValue === 'PERSONA') {
                const nombrePersona = formData.get('recomendado_doctor_nombre');
                formData.set('origen', 'PERSONA: ' + (nombrePersona ? nombrePersona.trim() : ''));
                formData.set('recomendado_por_id', '');
                formData.set('tipo_id', '');
            } else if (origenValue !== "") {
                const nombreOrigen = selectedOption ? selectedOption.dataset.nombre : '';
                formData.set('recomendado_por_id', origenValue);
                formData.set('origen', nombreOrigen);
            } else {
                formData.set('recomendado_por_id', '');
                formData.set('origen', '');
            }

            let fetchOptions = {
                method: 'POST'
            };

            if (isEdit) {
                // Para editar, enviamos JSON como lo espera actualizar_paciente.php
                const data = Object.fromEntries(formData.entries());
                fetchOptions.headers = { 'Content-Type': 'application/json' };
                fetchOptions.body = JSON.stringify(data);
            } else {
                // Para crear, enviamos FormData directamente
                fetchOptions.body = formData;
            }
            
            fetch(url, fetchOptions)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        $('#modalPaciente').modal('hide');
                        cargarPacientes();
                        alert(isEdit ? 'Paciente actualizado correctamente' : 'Paciente creado correctamente');
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al guardar el paciente.');
                });
        }

        function eliminarPaciente(id) {
            const paciente = pacientes.find(p => p.id == id);
            if (!paciente) return;
            
            if (confirm(`¿Está seguro que desea eliminar al paciente "${paciente.nombre}"?`)) {
                fetch('eliminar_paciente.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cargarPacientes();
                        alert('Paciente eliminado correctamente.');
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar el paciente.');
                });
            }
        }

        function toggleSettingsDropdown() {
            document.getElementById("ajustesDropdown").classList.toggle("show");
        }

        window.onclick = function(event) {
            if (!event.target.matches('.btn-header') && !event.target.closest('.btn-header')) {
                var dropdowns = document.getElementsByClassName("custom-dropdown-menu");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
    </script>
</body>
</html>
