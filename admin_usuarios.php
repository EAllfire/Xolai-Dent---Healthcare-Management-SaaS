<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
// set header behavior for admin users page
$show_calendar = true;
$show_back = false;
include_once __DIR__ . '/includes/header.php';

// Solo admins pueden acceder a este panel
if (!puedeRealizar('gestionar_usuarios')) {
    header('Location: index.php');
    exit;
}

// Obtener información del usuario actual para el header
$user_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$user_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';

// Permisos (coincidente con index.php)
$puede_crear_citas = in_array($user_tipo, ['admin', 'caja']);
$puede_gestionar_usuarios = ($user_tipo === 'admin');

$error = '';
$success = '';

// Procesar eliminación de usuario
if ($_POST && isset($_POST['eliminar_usuario'])) {
    $usuario_id = intval($_POST['usuario_id']);
    
    if ($usuario_id && $usuario_id != $_SESSION['usuario_id']) {
        $stmt = $conn->prepare("DELETE FROM agenda_usuarios WHERE id = ?");
        $stmt->bind_param("i", $usuario_id);
        
        if ($stmt->execute()) {
            $success = 'Usuario eliminado exitosamente.';
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
    $usuario_id = intval($_POST['usuario_id']);
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $cambiar_password = !empty($_POST['password']);
    
    if ($usuario_id && $nombre && $email && $tipo) {
        // Verificar si el correo ya existe en otro usuario
        $stmt = $conn->prepare("SELECT id FROM agenda_usuarios WHERE correo = ? AND id != ?");
        $stmt->bind_param("si", $email, $usuario_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = 'El correo ya está registrado por otro usuario';
        } else {
            if ($cambiar_password) {
                $password = $_POST['password'];
                if (strlen($password) < 6) {
                    $error = 'La contraseña debe tener al menos 6 caracteres';
                } else {
                    // Usar password con hash
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE agenda_usuarios SET nombre = ?, correo = ?, tipo = ?, password = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $nombre, $email, $tipo, $password_hash, $usuario_id);
                }
            } else {
                $stmt = $conn->prepare("UPDATE agenda_usuarios SET nombre = ?, correo = ?, tipo = ? WHERE id = ?");
                $stmt->bind_param("sssi", $nombre, $email, $tipo, $usuario_id);
            }
            
            if (!isset($error) && $stmt->execute()) {
                $success = 'Usuario actualizado exitosamente.';
            } elseif (!isset($error)) {
                $error = 'Error al actualizar el usuario.';
            }
        }
        $stmt->close();
    } else {
        $error = 'Por favor complete todos los campos obligatorios.';
    }
}

// Procesar creación de usuario genérico (admin, caja, lectura)
if ($_POST && isset($_POST['crear_usuario'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $tipo = $_POST['tipo'] ?? 'lectura';

    if ($nombre && $email && $password) {
        if (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres';
        } else {
            // verificar email único
            $stmt = $conn->prepare("SELECT id FROM agenda_usuarios WHERE correo = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = 'El correo ya está registrado';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $nombre_usuario = strtolower(str_replace(' ', '', $nombre)) . rand(10,99);
                $stmt2 = $conn->prepare("INSERT INTO agenda_usuarios (nombre, nombre_usuario, correo, password, tipo) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param('sssss', $nombre, $nombre_usuario, $email, $password_hash, $tipo);
                if ($stmt2->execute()) {
                    $success = 'Usuario creado: ' . $nombre_usuario;
                    // refresh users list
                    $result = $conn->query("SELECT id, nombre, correo, tipo FROM agenda_usuarios ORDER BY tipo DESC, nombre ASC");
                    $usuarios = [];
                    while ($row = $result->fetch_assoc()) { $usuarios[] = $row; }
                } else {
                    $error = 'Error al crear usuario: ' . $stmt2->error;
                }
                $stmt2->close();
            }
            $stmt->close();
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
                $success = 'Usuario creado: ' . $nombre_usuario;
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
$result = $conn->query("SELECT id, nombre, correo, tipo FROM agenda_usuarios ORDER BY tipo DESC, nombre ASC");
while ($row = $result->fetch_assoc()) {
    $usuarios[] = $row;
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
    <link rel="stylesheet" href="css/header.css">
</head>
<body>
    <?php
    // include shared header, on admin pages we may want back button hidden and calendar visible
    $show_calendar = true;
    $show_back = false;
    $show_mobile_menu = false;
    include __DIR__ . '/includes/header.php';
    ?>

    <div class="container mt-4">
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
                            <label>Nombre completo</label>
                            <input name="nombre" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Correo</label>
                            <input name="email" type="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Contraseña</label>
                            <input name="password" type="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Rol</label>
                            <select name="tipo" class="form-control" required>
                                <option value="lectura">Solo Lectura</option>
                                <option value="caja">Caja</option>
                                <option value="admin">Administrador</option>
                            </select>
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
                                <tr><th>Nombre</th><th>Correo</th><th>Rol</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                                        <td><?= htmlspecialchars($usuario['correo']) ?></td>
                                        <td><?= htmlspecialchars($usuario['tipo']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
            $('#modalEditar').modal('show');
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
    </script>
</body>
</html>