<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
// set header behavior for admin users page
$show_calendar = true;
$show_back = false;
$show_mobile_menu = false;

// Obtener información del usuario actual para el header
$user_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$user_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';
$es_usuario_super = ($user_tipo === 'superadmin');
$es_dentista_principal = ($user_tipo === 'dentista' && empty($_SESSION['id_padre']));
$es_usuario_super = ($user_tipo === 'superadmin');

// Verificar permisos centralizados en auth.php
if (!puedeRealizar('gestionar_usuarios')) {
    header('Location: index.php');
    exit;
}

// Permisos (coincidente con index.php)
$puede_crear_citas = in_array($user_tipo, ['admin', 'caja']);
$puede_gestionar_usuarios = puedeRealizar('gestionar_usuarios');
$puede_ver_admin = in_array($user_tipo, ['admin', 'medico', 'dentista']);

$error = '';
$success = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] == 'update') {
        $success = 'Usuario actualizado exitosamente.';
    }
    if ($_GET['success'] == 'delete') {
        $success = 'Usuario eliminado exitosamente.';
    }
    if ($_GET['success'] == 'create') {
        $success = 'Usuario creado exitosamente.';
    }
}

// Procesar eliminación de usuario
if ($_POST && isset($_POST['eliminar_usuario'])) {
    $usuario_id = intval($_POST['usuario_id']);
    
    if ($usuario_id && $usuario_id != $_SESSION['usuario_id']) {
        $stmt = $conn->prepare("DELETE FROM agenda_usuarios WHERE id = ?");
        $stmt->bind_param("i", $usuario_id);
        
        if ($stmt->execute()) {
            header('Location: admin_usuarios.php?success=delete');
            exit;
        } else {
            $error = 'Error al eliminar el usuario.';
        }
        $stmt->close();
    } else {
        $error = 'No puedes eliminar tu propio usuario.';
    }
}

// Procesar edición de usuario
    if ($_POST && isset($_POST['editar_usuario'])) {
    $usuario_id = intval($_POST['usuario_id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $icloud_email = trim($_POST['icloud_email'] ?? '');
    $icloud_app_password = $_POST['icloud_app_password'] ?? '';
    $icloud_calendar_name = trim($_POST['icloud_calendar_name'] ?? '');
    $icloud_calendar_href = trim($_POST['icloud_calendar_href'] ?? '');
    $icloud_sync_enabled = isset($_POST['icloud_sync_enabled']) ? 1 : 0;
    // Si es dentista, el id_padre es él mismo. Si es admin, usa el del post.
    $id_padre = ($user_tipo === 'dentista') ? $_SESSION['usuario_id'] : (!empty($_POST['id_padre']) ? intval($_POST['id_padre']) : null);
    $especialidad_id = !empty($_POST['especialidad_id']) ? intval($_POST['especialidad_id']) : null;
    $password = $_POST['password'] ?? '';
    $cambiar_password = !empty($password);
    
    if (!$usuario_id || !$nombre || !$tipo) {
        $error = 'Por favor complete todos los campos obligatorios.';
    } elseif ($cambiar_password && strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        // Todas las validaciones pasaron, proceder con la base de datos
        $stmt = null;
        if ($cambiar_password) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE agenda_usuarios SET nombre = ?, tipo = ?, password = ?, id_padre = ?, especialidad_id = ?, icloud_email = ?, icloud_app_password = ?, icloud_calendar_name = ?, icloud_calendar_href = ?, icloud_sync_enabled = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("sssissssisi", $nombre, $tipo, $password_hash, $id_padre, $especialidad_id, $icloud_email, $icloud_app_password, $icloud_calendar_name, $icloud_calendar_href, $icloud_sync_enabled, $usuario_id);
            }
        } else {
            $stmt = $conn->prepare("UPDATE agenda_usuarios SET nombre = ?, tipo = ?, id_padre = ?, especialidad_id = ?, icloud_email = ?, icloud_app_password = ?, icloud_calendar_name = ?, icloud_calendar_href = ?, icloud_sync_enabled = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ssissssisi", $nombre, $tipo, $id_padre, $especialidad_id, $icloud_email, $icloud_app_password, $icloud_calendar_name, $icloud_calendar_href, $icloud_sync_enabled, $usuario_id);
            }
        }

        if (!$stmt) {
            $error = "Error al preparar la consulta: " . $conn->error;
        } else {
            if ($stmt->execute()) {
                header('Location: admin_usuarios.php?success=update');
                exit;
            } else {
                $error = 'Error al actualizar el usuario: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Procesar creación de usuario genérico (admin, caja, lectura)
    if ($_POST && isset($_POST['crear_usuario'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $password = $_POST['password'] ?? '';
    $tipo = $_POST['tipo'] ?? 'lectura';
    $icloud_email = trim($_POST['icloud_email'] ?? '');
    $icloud_app_password = $_POST['icloud_app_password'] ?? '';
    $icloud_calendar_name = trim($_POST['icloud_calendar_name'] ?? '');
    $icloud_calendar_href = trim($_POST['icloud_calendar_href'] ?? '');
    $icloud_sync_enabled = isset($_POST['icloud_sync_enabled']) ? 1 : 0;
    // Forzar id_padre si el creador es dentista
    $id_padre = ($user_tipo === 'dentista') ? $_SESSION['usuario_id'] : (!empty($_POST['id_padre']) ? intval($_POST['id_padre']) : null);
    $especialidad_id = !empty($_POST['especialidad_id']) ? intval($_POST['especialidad_id']) : null;

    if ($nombre && $password) {
        if (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $nombre_usuario = strtolower(str_replace(' ', '', $nombre)) . rand(10,99);
            
            // Generar un correo electrónico único para cumplir con la restricción de la base de datos
            $email = 'user_' . $nombre_usuario . '@generated.com';

            $stmt2 = $conn->prepare("INSERT INTO agenda_usuarios (nombre, nombre_usuario, correo, password, tipo, especialidad_id, id_padre, icloud_email, icloud_app_password, icloud_calendar_name, icloud_calendar_href, icloud_sync_enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt2->bind_param('sssssiisssss', $nombre, $nombre_usuario, $email, $password_hash, $tipo, $especialidad_id, $id_padre, $icloud_email, $icloud_app_password, $icloud_calendar_name, $icloud_calendar_href, $icloud_sync_enabled);
            
            if ($stmt2->execute()) {
                header('Location: admin_usuarios.php?success=create');
                exit;
            } else {
                // Si el correo generado ya existe (muy poco probable), intentar con uno nuevo
                if ($stmt2->errno == 1062) { // Error de entrada duplicada
                    $email = 'user_' . $nombre_usuario . '_' . time() . '@generated.com';
                    // re-bind with corrected param types and try again
                    $stmt2->bind_param('sssssiisssss', $nombre, $nombre_usuario, $email, $password_hash, $tipo, $especialidad_id, $id_padre, $icloud_email, $icloud_app_password, $icloud_calendar_name, $icloud_calendar_href, $icloud_sync_enabled);
                    if ($stmt2->execute()) {
                        header('Location: admin_usuarios.php?success=create');
                        exit;
                    } else {
                        $error = 'Error al crear usuario: ' . $stmt2->error;
                    }
                } else {
                    $error = 'Error al crear usuario: ' . $stmt2->error;
                }
            }
            $stmt2->close();
        }
    } else {
        $error = 'Por favor complete todos los campos';
    }
}

// Procesar creación de usuario tipo caja/lectura (simple flow)
if ($_POST && isset($_POST['crear_tipo'])) {
    $tipo_nuevo = $_POST['crear_tipo'];
    $nombre = trim($_POST['nombre_simple'] ?? '');
    $email = trim($_POST['email_simple'] ?? '');
    $password = $_POST['password_simple'] ?? '';
    if ($nombre && $email && $password && strlen($password) >= 6) {
        // verificar email único
        $stmt = $conn->prepare("SELECT id FROM agenda_usuarios WHERE correo = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'El correo ya está registrado';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $nombre_usuario = strtolower(str_replace(' ', '', $nombre)) . rand(10,99);
            $stmt2 = $conn->prepare("INSERT INTO agenda_usuarios (nombre, nombre_usuario, correo, password, tipo) VALUES (?, ?, ?, ?, ?)");
            $stmt2->bind_param('sssss', $nombre, $nombre_usuario, $email, $password_hash, $tipo_nuevo);
            if ($stmt2->execute()) {
                header('Location: admin_usuarios.php?success=create');
                exit;
            } else {
                $error = 'Error al crear usuario: ' . $stmt2->error;
            }
            $stmt2->close();
        }
        $stmt->close();
    } else {
        $error = 'Datos inválidos para crear usuario simple.';
    }
}

// Obtener todos los usuarios para mostrar
    $usuarios = [];
    // Aplicar restricción de visibilidad basada en permisos/rol
    $allowed_ids_scope = obtenerIdsPermitidos();
    if ($allowed_ids_scope === null) {
        // Sin restricción (superadmin o admin principal)
        $result = $conn->query("SELECT au.id, au.nombre, au.nombre_usuario, au.correo, au.tipo, au.especialidad_id, ae.nombre as especialidad_nombre, au.id_padre, au.icloud_email, au.icloud_app_password, au.icloud_calendar_name, au.icloud_calendar_href, au.icloud_sync_enabled FROM agenda_usuarios au LEFT JOIN agenda_especialidades ae ON au.especialidad_id = ae.id ORDER BY au.tipo DESC, au.nombre ASC");
    } elseif (is_array($allowed_ids_scope) && in_array('SELF_AND_CHILDREN', $allowed_ids_scope)) {
        // Dentista principal: ver self + hijos
        $stmt_list = $conn->prepare("SELECT au.id, au.nombre, au.nombre_usuario, au.correo, au.tipo, au.especialidad_id, ae.nombre as especialidad_nombre, au.id_padre, au.icloud_email, au.icloud_app_password, au.icloud_calendar_name, au.icloud_calendar_href, au.icloud_sync_enabled FROM agenda_usuarios au LEFT JOIN agenda_especialidades ae ON au.especialidad_id = ae.id WHERE au.id_padre = ? OR au.id = ? ORDER BY au.nombre ASC");
        $stmt_list->bind_param("ii", $_SESSION['usuario_id'], $_SESSION['usuario_id']);
        $stmt_list->execute();
        $result = $stmt_list->get_result();
    } elseif (is_array($allowed_ids_scope) && in_array('PARENT_ONLY', $allowed_ids_scope)) {
        // Admin derivado: ver usuarios/pacientes asociados al padre (id_padre)
        $parentId = $_SESSION['id_padre'] ?? null;
        if ($parentId) {
            $stmt_list = $conn->prepare("SELECT au.id, au.nombre, au.nombre_usuario, au.correo, au.tipo, au.especialidad_id, ae.nombre as especialidad_nombre, au.id_padre, au.icloud_email, au.icloud_app_password, au.icloud_calendar_name, au.icloud_calendar_href, au.icloud_sync_enabled FROM agenda_usuarios au LEFT JOIN agenda_especialidades ae ON au.especialidad_id = ae.id WHERE au.id_padre = ? OR au.id = ? ORDER BY au.nombre ASC");
            $stmt_list->bind_param("ii", $parentId, $parentId);
            $stmt_list->execute();
            $result = $stmt_list->get_result();
        } else {
            // fallback: solo ver él mismo
            $stmt_list = $conn->prepare("SELECT au.id, au.nombre, au.nombre_usuario, au.correo, au.tipo, au.especialidad_id, ae.nombre as especialidad_nombre, au.id_padre, au.icloud_email, au.icloud_app_password, au.icloud_calendar_name, au.icloud_calendar_href, au.icloud_sync_enabled FROM agenda_usuarios au LEFT JOIN agenda_especialidades ae ON au.especialidad_id = ae.id WHERE au.id = ?");
            $stmt_list->bind_param("i", $_SESSION['usuario_id']);
            $stmt_list->execute();
            $result = $stmt_list->get_result();
        }
    } else { // Fallback para otros tipos de allowed_ids_scope (ej. array de IDs específicos)
        $ids_str = implode(',', array_map('intval', $allowed_ids_scope));
        $result = $conn->query("SELECT au.id, au.nombre, au.nombre_usuario, au.correo, au.tipo, au.especialidad_id, ae.nombre as especialidad_nombre, au.id_padre, au.icloud_email, au.icloud_app_password, au.icloud_calendar_name, au.icloud_calendar_href, au.icloud_sync_enabled FROM agenda_usuarios au LEFT JOIN agenda_especialidades ae ON au.especialidad_id = ae.id WHERE au.id IN ($ids_str) ORDER BY au.nombre ASC");
    }
while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
}

// Obtener lista de posibles superiores (Admins, Médicos o Dentistas que no sean derivados)
$superiores = [];
$res_sup = $conn->query("SELECT id, nombre FROM agenda_usuarios WHERE tipo IN ('admin', 'medico', 'dentista') AND id_padre IS NULL ORDER BY nombre ASC");
while ($row = $res_sup->fetch_assoc()) {
    $superiores[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            padding-top: 0;
            background-color: #000000;
            color: #e5e7eb;
            font-family: 'Inter', sans-serif;
            margin: 0;
        }

        /* Header Styles - Xolai Style */
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
            gap: 10px;
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
            transition: all 0.2s ease;
        }
        .btn-header:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }

        .settings-container { position: relative; display: inline-block; margin-right: 10px; }
        .settings-btn { 
            background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1); 
            cursor: pointer; font-size: 1.2rem; color: #e5e7eb; padding: 6px 10px; border-radius: 10px; 
            display: flex; align-items: center; justify-content: center; transition: all 0.3s ease;
        }
        .settings-btn:hover { background: rgba(255, 255, 255, 0.15); color: #ffffff; transform: rotate(90deg); }
        .custom-dropdown-menu { 
            display: none; position: absolute; right: 0; top: 100%; background-color: #0a0a0a; 
            min-width: 200px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); border-radius: 12px; z-index: 1100; 
            margin-top: 10px; border: 1px solid #333; text-align: left;
        }
        .custom-dropdown-menu.show { display: block; }
        .custom-dropdown-menu a { 
            color: #e5e7eb; padding: 12px 20px; text-decoration: none; display: block; 
            font-size: 14px; border-bottom: 1px solid #1a1a1a; transition: all 0.2s;
        }
        .custom-dropdown-menu a:hover { background-color: rgba(41, 121, 255, 0.1); color: #2979ff; }
        
        .card {
            background: #0a0a0a;
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .form-control {
            background: #000000;
            border: 1px solid #333;
            color: #e5e7eb;
        }
        
        .form-control:focus {
            background: #000000;
            color: #fff;
            border-color: #2979ff;
        }
        
        .table { color: #e5e7eb; }
        .table th { border-top: none; border-bottom: 1px solid #333; color: #9ca3af; }
        .table td { border-top: 1px solid #333; }
        
        /* Modal Oscuro */
        .modal-content {
            background: #0a0a0a;
            border: 1px solid #333;
            color: #e5e7eb;
            box-shadow: 0 10px 40px rgba(0,0,0,0.7);
        }
        
        .modal-header {
            border-bottom: 1px solid #333;
            background: #111;
        }
        
        .modal-footer {
            border-top: 1px solid #333;
            background: #111;
        }
        
        .close {
            color: #e5e7eb;
            text-shadow: none;
            opacity: 0.7;
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
            <a href="panel_admin.php" class="nav-link active">Administración</a>
        </nav>
        <div class="header-right">
            <div class="user-info">
                <span><?php echo htmlspecialchars($user_nombre); ?></span>
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="settings-container">
                <button onclick="toggleSettingsDropdown()" class="settings-btn"><i class="fas fa-cog"></i></button>
                <div id="ajustesDropdown" class="custom-dropdown-menu">
                    <a href="catalogo_servicios.php"><i class="fas fa-stethoscope"></i> Servicios</a>
                    <a href="admin_modalidades.php"><i class="fas fa-layer-group"></i> Modalidades</a>
                    <a href="admin_origenes_recomendacion.php"><i class="fas fa-bullhorn"></i> Orígenes Recomendación</a>
                </div>
            </div>
            <a href="logout.php" class="btn-header"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </header>
    <div class="container mt-4" style="padding-top: 120px;">
        <a href="panel_admin.php" class="btn btn-secondary mb-3">Volver al Panel</a>
        <div class="row">
            <div class="col-md-5">
                <div class="card p-3">
                    <h5>Crear Usuario</h5>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label for="nombre_crear">Nombre completo</label>
                            <input id="nombre_crear" name="nombre" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="password_crear">Contraseña</label>
                            <input id="password_crear" name="password" type="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="tipo_crear">Rol</label>
                            <select id="tipo_crear" name="tipo" class="form-control" required onchange="toggleICloudFields(this.value, 'icloud_fields_crear')">
                                <?php if($es_usuario_super): // Superadmin puede crear todos los tipos ?>
                                <option value="superadmin">Super Admin</option>
                                <option value="admin">Administrador</option>
                                <option value="lectura">Solo Lectura</option>
                                <option value="caja">Caja</option>
                                <option value="medico">Médico</option>
                                <option value="dentista">Dentista (Principal)</option>
                                <option value="recepcion">Recepción</option>
                                <option value="dentista_externo">Dentista externo</option>
                                <?php elseif($user_tipo === 'admin'): // Admin de clínica: puede crear usuarios de su entorno ?>
                                <option value="lectura">Solo Lectura</option>
                                <option value="caja">Caja</option>
                                <option value="medico">Médico</option>
                                <option value="dentista">Dentista (Principal)</option>
                                <option value="recepcion">Recepción</option>
                                <option value="dentista_externo">Dentista externo</option>
                                <option value="admin">Administrador de Clínica</option>
                                <?php elseif($es_dentista_principal): // El Dentista Principal puede crear roles de clínica y colaboradores ?>
                                <option value="lectura">Solo Lectura</option>
                                <option value="caja">Caja</option>
                                <option value="medico">Médico</option>
                                <option value="recepcion">Recepción</option>
                                <option value="dentista">Dentista (Colaborador)</option>
                                <option value="dentista_externo">Dentista externo</option>
                                <option value="admin">Administrador de Clínica</option>
                                <?php elseif($user_tipo === 'recepcion' || ($user_tipo === 'dentista' && !$es_dentista_principal)): // Recepción o Dentista Colaborador ?>
                                <option value="recepcion">Recepción</option>
                                <option value="dentista">Dentista (Colaborador)</option>
                                <option value="dentista_externo">Dentista externo</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group" id="especialidad_container_crear" style="display:none;">
                            <label for="especialidad_crear">Especialidad</label>
                            <select id="especialidad_crear" name="especialidad_id" class="form-control">
                                <option value="">Seleccione especialidad</option>
                                <!-- Options loaded dynamically -->
                            </select>
                        </div>
                        <!-- Campos iCloud para Dentista -->
                        <div id="icloud_fields_crear" style="display:none; border: 1px solid #333; padding: 15px; border-radius: 8px; margin-bottom: 15px; background: rgba(41, 121, 255, 0.05);">
                            <h6 class="text-primary mb-3"><i class="fab fa-apple mr-2"></i> Configuración Apple Calendar</h6>
                            <div class="form-group">
                                <label>Correo iCloud</label>
                                <input type="email" name="icloud_email" class="form-control" placeholder="ejemplo@icloud.com">
                            </div>
                            <div class="form-group">
                                <label>Nombre de la Agenda iCloud</label>
                                <input type="text" name="icloud_calendar_name" class="form-control" placeholder="Ej. Agenda Hospital Ángeles">
                                <small class="form-text text-muted">Usa este nombre para seleccionar la agenda correcta dentro de tu cuenta iCloud.</small>
                            </div>
                            <div class="form-group">
                                <label>URL / Href del Calendario (opcional)</label>
                                <input type="text" name="icloud_calendar_href" class="form-control" placeholder="/123456789/calendars/XXXXXXXX/">
                                <small class="form-text text-muted">Si se conoce el href exacto, se usará como selección prioritaria.</small>
                            </div>
                            <div class="form-group">
                                <label>Contraseña de Aplicación</label>
                                <input type="password" name="icloud_app_password" class="form-control" placeholder="xxxx-xxxx-xxxx-xxxx">
                            </div>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="sync_crear" name="icloud_sync_enabled">
                                <label class="custom-control-label" for="sync_crear">Activar sincronización</label>
                            </div>
                        </div>
                        <div class="form-group" <?= ($user_tipo === 'dentista') ? 'style="display:none;"' : '' ?>>
                            <label for="id_padre_crear">Superior (Dentista Responsable)</label>
                            <select id="id_padre_crear" name="id_padre" class="form-control">
                                <option value="">Ninguno (Es cuenta principal)</option>
                                <?php foreach ($superiores as $sup): ?>
                                    <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                Si selecciona un superior, este usuario verá los mismos datos que el superior elegido.
                            </small>
                        </div>
                        <button type="submit" name="crear_usuario" class="btn btn-primary">Crear Usuario</button>
                    </form>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card p-3">
                    <h5>Lista de Usuarios</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Especialidades</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($usuario['nombre_usuario']) ?>
                                            <?php if ($usuario['id_padre']): ?>
                                                <br><small class="badge badge-secondary">Derivado</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($usuario['tipo']) ?></td>
                                        <td><?= htmlspecialchars($usuario['especialidad_nombre'] ?? 'N/A') ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="editarUsuario(<?= $usuario['id'] ?>, <?= htmlspecialchars(json_encode($usuario['nombre']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($usuario['tipo']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($usuario['id_padre'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($usuario['icloud_email'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($usuario['icloud_app_password'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($usuario['icloud_calendar_name'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($usuario['icloud_calendar_href'] ?? ''), ENT_QUOTES) ?>, <?= $usuario['icloud_sync_enabled'] ?>, <?= htmlspecialchars(json_encode($usuario['especialidad_id'] ?? ''), ENT_QUOTES) ?>)">Editar</button>
                                            <button class="btn btn-sm btn-danger" onclick="eliminarUsuario(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['nombre']) ?>')">Eliminar</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar -->
    <div class="modal fade" id="modalEditar" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <form method="POST">
            <div class="modal-header">
              <h5 class="modal-title" id="exampleModalLabel">Editar Usuario</h5>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="usuario_id" id="edit_usuario_id">
                <div class="form-group">
                  <label for="edit_usuario_nombre">Nombre</label>
                  <input type="text" name="nombre" id="edit_usuario_nombre" class="form-control" required>
                </div>
                <div class="form-group">
                  <label for="edit_usuario_tipo">Tipo</label>
                <select name="tipo" id="edit_usuario_tipo" class="form-control" required onchange="toggleICloudFields(this.value, 'icloud_fields_edit')">
                    <?php if($es_usuario_super): // Superadmin puede editar todos los tipos ?>
                    <option value="superadmin">Super Admin</option>
                    <option value="admin">Administrador</option>
                    <option value="lectura">Solo Lectura</option>
                    <option value="caja">Caja</option>
                    <option value="medico">Médico</option>
                    <option value="dentista">Dentista (Principal)</option>
                    <option value="recepcion">Recepción</option>
                    <option value="dentista_externo">Dentista externo</option>
                    <?php elseif($user_tipo === 'admin'): // El administrador general puede editar a todos los tipos, incluyendo Dentista (Principal) ?>
                    <option value="lectura">Solo Lectura</option>
                    <option value="caja">Caja</option>
                    <option value="medico">Médico</option>
                    <option value="dentista">Dentista (Principal)</option>
                    <option value="admin">Administrador</option>
                    <?php elseif($es_dentista_principal): // El Dentista Principal puede editar a roles de clínica y Administradores ?>
                    <option value="lectura">Solo Lectura</option>
                    <option value="caja">Caja</option>
                    <option value="medico">Médico</option>
                    <option value="recepcion">Recepción</option>
                    <option value="dentista">Dentista (Colaborador)</option>
                    <option value="dentista_externo">Dentista externo</option>
                    <option value="admin">Administrador de Clínica</option>
                    <?php elseif($user_tipo === 'recepcion' || ($user_tipo === 'dentista' && !$es_dentista_principal)): // Recepción o Dentista Colaborador ?>
                    <option value="recepcion">Recepción</option>
                    <option value="dentista">Dentista (Colaborador)</option>
                    <option value="dentista_externo">Dentista externo</option>
                    <?php endif; ?>
                  </select>
                </div>
                <div class="form-group" id="especialidad_container_edit" style="display:none;">
                    <label for="edit_especialidad_id">Especialidad</label>
                    <select id="edit_especialidad_id" name="especialidad_id" class="form-control">
                        <option value="">Seleccione especialidad</option>
                        <!-- Options loaded dynamically -->
                    </select>
                </div>
                <!-- Campos iCloud para Dentista (Edición) -->
                <div id="icloud_fields_edit" style="display:none; border: 1px solid #333; padding: 15px; border-radius: 8px; margin-bottom: 15px; background: rgba(41, 121, 255, 0.05);">
                    <h6 class="text-primary mb-3"><i class="fab fa-apple mr-2"></i> Configuración Apple Calendar</h6>
                    <div class="form-group">
                        <label>Correo iCloud</label>
                        <input type="email" name="icloud_email" id="edit_icloud_email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Nombre de la Agenda iCloud</label>
                        <input type="text" name="icloud_calendar_name" id="edit_icloud_calendar_name" class="form-control">
                        <small class="form-text text-muted">Usa este nombre para seleccionar la agenda correcta dentro de iCloud.</small>
                    </div>
                    <div class="form-group">
                        <label>URL / Href del Calendario (opcional)</label>
                        <input type="text" name="icloud_calendar_href" id="edit_icloud_calendar_href" class="form-control">
                        <small class="form-text text-muted">Si se conoce el href exacto, se usará como selección prioritaria.</small>
                    </div>
                    <div class="form-group">
                        <label>Contraseña de Aplicación</label>
                        <input type="password" name="icloud_app_password" id="edit_icloud_app_password" class="form-control">
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="edit_icloud_sync_enabled" name="icloud_sync_enabled">
                        <label class="custom-control-label" for="edit_icloud_sync_enabled">Activar sincronización</label>
                    </div>
                </div>
                <div class="form-group" <?= ($user_tipo === 'dentista') ? 'style="display:none;"' : '' ?>>
                  <label for="edit_id_padre">Superior (Dentista Responsable)</label>
                  <select name="id_padre" id="edit_id_padre" class="form-control">
                    <option value="">Ninguno (Es cuenta principal)</option>
                    <?php foreach ($superiores as $sup): ?>
                        <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['nombre']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label for="edit_password">Nueva Contraseña (dejar en blanco para no cambiar)</label>
                  <input id="edit_password" type="password" name="password" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
              <button type="submit" name="editar_usuario" class="btn btn-primary">Guardar Cambios</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Eliminar -->
    <div class="modal fade" id="modalEliminar" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Eliminar Usuario</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <p>¿Estás seguro de que quieres eliminar al usuario <strong id="delete_usuario_nombre"></strong>?</p>
            <form method="POST">
              <input type="hidden" name="usuario_id" id="delete_usuario_id">
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" name="eliminar_usuario" class="btn btn-danger">Eliminar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function editarUsuario(id, nombre, tipo, idPadre, icloudEmail, icloudPass, icloudCalendarName, icloudCalendarHref, icloudSync, especialidadId) {
            document.getElementById('edit_usuario_id').value = id;
            document.getElementById('edit_usuario_nombre').value = nombre;
            document.getElementById('edit_id_padre').value = idPadre || '';

            // Llenar campos iCloud
            document.getElementById('edit_icloud_email').value = icloudEmail || '';
            document.getElementById('edit_icloud_calendar_name').value = icloudCalendarName || '';
            document.getElementById('edit_icloud_calendar_href').value = icloudCalendarHref || '';
            document.getElementById('edit_icloud_sync_enabled').checked = (icloudSync == 1);

            var tipoSelect = document.getElementById('edit_usuario_tipo');
            for (var i = 0; i < tipoSelect.options.length; i++) {
                if (tipoSelect.options[i].value == tipo) {
                    tipoSelect.options[i].selected = true;
                    break;
                }
            }
            toggleICloudFields(tipo, 'icloud_fields_edit'); // This will also toggle specialty fields
            document.getElementById('edit_especialidad_id').value = especialidadId || '';
            $('#modalEditar').modal('show');
        }

        function toggleICloudFields(tipo, containerId) {
            const container = document.getElementById(containerId);
            if (!container) return;
            // Los campos de iCloud ahora están disponibles para Dentistas y Administradores
            container.style.display = (tipo === 'dentista' || tipo === 'admin') ? 'block' : 'none';
            
            try {
                var especialidadCrear = document.getElementById('especialidad_container_crear');
                var especialidadEdit = document.getElementById('especialidad_container_edit');
                const mostrarEspecialidad = (tipo === 'dentista' || tipo === 'medico' || tipo === 'dentista_externo' || tipo === 'admin');
                if (especialidadCrear) especialidadCrear.style.display = mostrarEspecialidad ? 'block' : 'none';
                if (especialidadEdit) especialidadEdit.style.display = mostrarEspecialidad ? 'block' : 'none';
            } catch(e){}
        }
        
        function eliminarUsuario(id, nombre) {
            document.getElementById('delete_usuario_id').value = id;
            document.getElementById('delete_usuario_nombre').textContent = nombre;
            $('#modalEliminar').modal('show');
        }
        
        // Auto-cerrar alertas después de 5 segundos
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);

        // Crear usuarios tipo caja / lectura desde header buttons
        function crearUsuarioConTipo(tipo) {
            var nombre = prompt('Nombre del usuario:');
            if (!nombre) return;
            var correo = prompt('Correo del usuario:');
            if (!correo) return;
            var password = prompt('Contraseña (mínimo 6 caracteres):');
            if (!password || password.length < 6) { alert('Contraseña inválida'); return; }

            var form = document.createElement('form');
            form.method = 'POST'; form.style.display = 'none';
            var i1 = document.createElement('input'); i1.name='crear_tipo'; i1.value=tipo; form.appendChild(i1);
            var n = document.createElement('input'); n.name='nombre_simple'; n.value=nombre; form.appendChild(n);
            var e = document.createElement('input'); e.name='email_simple'; e.value=correo; form.appendChild(e);
            var p = document.createElement('input'); p.name='password_simple'; p.value=password; form.appendChild(p);
            document.body.appendChild(form); form.submit();
        }

        var btnCaja = document.getElementById('btnCrearCaja');
        var btnLect = document.getElementById('btnCrearLectura');
        if (btnCaja) btnCaja.addEventListener('click', function(e){ e.preventDefault(); crearUsuarioConTipo('caja'); });
        if (btnLect) btnLect.addEventListener('click', function(e){ e.preventDefault(); crearUsuarioConTipo('lectura'); });

    // Panel buttons
    var btnCajaPanel = document.getElementById('btnCrearCajaPanel');
    var btnLectPanel = document.getElementById('btnCrearLecturaPanel');
    if (btnCajaPanel) btnCajaPanel.addEventListener('click', function(e){ e.preventDefault(); crearUsuarioConTipo('caja'); });
    if (btnLectPanel) btnLectPanel.addEventListener('click', function(e){ e.preventDefault(); crearUsuarioConTipo('lectura'); });

    function toggleSettingsDropdown() {
        document.getElementById("ajustesDropdown").classList.toggle("show");
    }
    window.onclick = function(event) {
        if (!event.target.matches('.settings-btn') && !event.target.closest('.settings-btn')) {
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
    <script>
        function escapeHtml(s){ if (!s) return ''; return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }

        // Function to load specialties into dropdowns
        async function cargarEspecialidades() {
            try {
                const response = await fetch('citas/especialidades_json.php');
                const especialidades = await response.json();

                const selectCrear = document.getElementById('especialidad_crear');
                const selectEditar = document.getElementById('edit_especialidad_id');

                selectCrear.innerHTML = '<option value="">Seleccione especialidad</option>';
                selectEditar.innerHTML = '<option value="">Seleccione especialidad</option>';

                especialidades.forEach(esp => {
                    const option = `<option value="${esp.id}">${escapeHtml(esp.nombre)}</option>`;
                    selectCrear.innerHTML += option;
                    selectEditar.innerHTML += option;
                });
            } catch (error) {
                console.error('Error al cargar especialidades:', error);
            }
        }
        cargarEspecialidades();
    </script>
</body>
</html>