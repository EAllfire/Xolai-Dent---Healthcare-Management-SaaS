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

error_log("DEBUG: Including header.php");
include_once __DIR__ . '/includes/header.php';
error_log("DEBUG: Header included. User Name: " . ($user_nombre ?? 'N/A') . ", User Type: " . ($user_tipo ?? 'N/A'));

// Solo admins pueden acceder a este panel
if (!puedeRealizar('gestionar_usuarios')) {
    header('Location: index.php');
    exit;
}

// Permisos (coincidente con index.php)
$puede_crear_citas = in_array($user_tipo, ['admin', 'caja']);
$puede_gestionar_usuarios = ($user_tipo === 'admin');

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
    $usuario_id = intval($_POST['usuario_id']);
    $nombre = trim($_POST['nombre'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $cambiar_password = !empty($_POST['password']);
    
    if ($usuario_id && $nombre && $tipo) {
        if ($cambiar_password) {
            $password = $_POST['password'];
            if (strlen($password) < 6) {
                $error = 'La contraseña debe tener al menos 6 caracteres';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE agenda_usuarios SET nombre = ?, tipo = ?, password = ? WHERE id = ?");
                $stmt->bind_param("sssi", $nombre, $tipo, $password_hash, $usuario_id);
            }
        } else {
            $stmt = $conn->prepare("UPDATE agenda_usuarios SET nombre = ?, tipo = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nombre, $tipo, $usuario_id);
        }
        
        error_log("DEBUG: Editing user - ID: " . $usuario_id . ", Nombre: " . $nombre . ", Tipo: " . $tipo . ", Cambiar Password: " . ($cambiar_password ? 'Yes' : 'No'));
        if (!isset($error) && $stmt->execute()) {
            error_log("DEBUG: User update successful for ID: " . $usuario_id);
            header('Location: admin_usuarios.php?success=update');
            exit;
        } elseif (!isset($error)) {
            $error = 'Error al actualizar el usuario: ' . $stmt->error;
            error_log("ERROR: User update failed for ID: " . $usuario_id . " - " . $stmt->error);
        }
        $stmt->close();
    } else {
        $error = 'Por favor complete todos los campos obligatorios.';
    }
}

// Procesar creación de usuario genérico (admin, caja, lectura)
if ($_POST && isset($_POST['crear_usuario'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $password = $_POST['password'] ?? '';
    $tipo = $_POST['tipo'] ?? 'lectura';

    if ($nombre && $password) {
        if (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $nombre_usuario = strtolower(str_replace(' ', '', $nombre)) . rand(10,99);
            
            // Generar un correo electrónico único para cumplir con la restricción de la base de datos
            $email = 'user_' . $nombre_usuario . '@generated.com';

            $stmt2 = $conn->prepare("INSERT INTO agenda_usuarios (nombre, nombre_usuario, correo, password, tipo) VALUES (?, ?, ?, ?, ?)");
            $stmt2->bind_param('sssss', $nombre, $nombre_usuario, $email, $password_hash, $tipo);
            
            if ($stmt2->execute()) {
                header('Location: admin_usuarios.php?success=create');
                exit;
            } else {
                // Si el correo generado ya existe (muy poco probable), intentar con uno nuevo
                if ($stmt2->errno == 1062) { // Error de entrada duplicada
                    $email = 'user_' . $nombre_usuario . '_' . time() . '@generated.com';
                    $stmt2->bind_param('sssss', $nombre, $nombre_usuario, $email, $password_hash, $tipo);
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
    <style>
        body {
            padding-top: 90px; /* Ajuste para el header fijo */
        }
    </style>
</head>
<body>
    <div class="container mt-4">
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
                            <select id="tipo_crear" name="tipo" class="form-control" required>
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
                                <tr><th>Nombre</th><th>Rol</th><th>Acciones</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                                        <td><?= htmlspecialchars($usuario['tipo']) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="editarUsuario(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['nombre']) ?>', '<?= htmlspecialchars($usuario['tipo']) ?>')">Editar</button>
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
          <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Editar Usuario</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <form method="POST">
              <input type="hidden" name="usuario_id" id="edit_usuario_id">
              <div class="form-group">
                <label for="edit_usuario_nombre">Nombre</label>
                <input type="text" name="nombre" id="edit_usuario_nombre" class="form-control" required>
              </div>
              <div class="form-group">
                <label for="edit_usuario_tipo">Tipo</label>
                <select name="tipo" id="edit_usuario_tipo" class="form-control" required>
                  <option value="lectura">Solo Lectura</option>
                  <option value="caja">Caja</option>
                  <option value="admin">Administrador</option>
                </select>
              </div>
              <div class="form-group">
                <label for="edit_password">Nueva Contraseña (dejar en blanco para no cambiar)</label>
                <input id="edit_password" type="password" name="password" class="form-control">
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="submit" name="editar_usuario" class="btn btn-primary">Guardar Cambios</button>
              </div>
            </form>
          </div>
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
        function editarUsuario(id, nombre, tipo) {
            document.getElementById('edit_usuario_id').value = id;
            document.getElementById('edit_usuario_nombre').value = nombre;
            var tipoSelect = document.getElementById('edit_usuario_tipo');
            for (var i = 0; i < tipoSelect.options.length; i++) {
                if (tipoSelect.options[i].value == tipo) {
                    tipoSelect.options[i].selected = true;
                    break;
                }
            }
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