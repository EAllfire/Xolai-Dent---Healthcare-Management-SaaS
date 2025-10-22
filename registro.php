<?php
session_start();
require_once 'includes/db.php';

// Si ya está logueado, no redirigir: mostrar mensaje informativo y permitir crear cuentas
$logueado = isset($_SESSION['usuario_id']);
$logueado_nombre = $_SESSION['usuario_nombre'] ?? null;
$logueado_tipo = $_SESSION['usuario_tipo'] ?? null;

$error = '';
$success = '';

if ($_POST) {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $tipo = $_POST['tipo'] ?? '';
    
    if ($nombre && $correo && $password && $confirm_password && $tipo) {
        if ($password !== $confirm_password) {
            $error = 'Las contraseñas no coinciden';
        } elseif (strlen($password) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres';
        } elseif (!in_array($tipo, ['caja', 'lectura'])) {
            $error = 'Tipo de usuario no válido. Solo se permiten usuarios de Caja y Lectura.';
        } else {
            // Verificar si el correo ya existe
            $stmt = $conn->prepare("SELECT id FROM agenda_usuarios WHERE correo = ?");
            $stmt->bind_param("s", $correo);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'El correo electrónico ya está registrado';
            } else {
                // Crear usuario
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO agenda_usuarios (nombre, correo, password, tipo) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $nombre, $correo, $password_hash, $tipo);
                
                if ($stmt->execute()) {
                    $success = 'Usuario registrado exitosamente. Ya puedes iniciar sesión.';
                } else {
                    $error = 'Error al crear el usuario';
                }
            }
            $stmt->close();
        }
    } else {
        $error = 'Por favor complete todos los campos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Agenda Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body {
            background: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }

        .register-container {
            background: rgba(68, 162, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow:
                0 20px 50px rgba(0,0,0,0.3),
                0 0 0 1px rgba(255,255,255,0.1);
            width: 100%;
            max-width: 450px;
            position: relative;
            color: white;
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-header h2 {
            color: white;
            margin-bottom: 5px;
        }
        .register-header p {
            color: rgba(255,255,255,0.9);
            font-size: 14px;
        }
        .btn-register {
            background-color: #005ea6;
            border: none;
            padding: 12px;
            font-weight: bold;
            color: white;
            border-radius: 8px;
        }
        .btn-register:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background-color: #004a86;
        }
        .alert {
            border-radius: 8px;
            font-size: 14px;
            color: #0b2540;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            background: white;
            color: #333;
        }
        /* Force small muted text to be white on colored container */
        .form-text.text-muted { color: rgba(255,255,255,0.95) !important; }
        /* Labels and headings inside the colored container should be white */
        .register-container label { color: white; }
        .form-text {
            color: rgba(255,255,255,0.9);
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: rgba(255,255,255,0.95);
        }
        .login-link a {
            color: white;
            text-decoration: underline;
        }
        .login-link a:hover {
            text-decoration: none;
        }
        .tipo-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 8px;
        }
        .badge-admin { background: #dc3545; color: white; }
        .badge-caja { background: #fd7e14; color: white; }
        .badge-lectura { background: #6c757d; color: white; }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="images/logo.png" alt="Hospital Angeles" style="height: 60px; margin-bottom: 10px;">
                
                <p style="color: #ffffffff; margin: 5px 0 0 0;">IMAGENOLOGÍA - Sistema de Citas</p>
            </div>
            <p>Crear Nueva Cuenta</p>
        </div>
        <?php if ($logueado): ?>
            <div class="alert alert-info">
                Estás logueado como <strong><?= htmlspecialchars($logueado_nombre ?? 'usuario') ?></strong> (<?= htmlspecialchars($logueado_tipo ?? '') ?>).
                Si deseas crear otra cuenta, <a href="logout.php" class="alert-link">cierra sesión</a> y vuelve aquí, o continúa para crear la cuenta mientras mantienes la sesión.
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="nombre">Nombre Completo</label>
                <input type="text" class="form-control" id="nombre" name="nombre" 
                       value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="correo">Correo Electrónico</label>
                <input type="email" class="form-control" id="correo" name="correo" 
                       value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="tipo">Tipo de Usuario</label>
                <select class="form-control" id="tipo" name="tipo" required>
                    <option value="">Seleccionar tipo de usuario</option>
                    <option value="caja" <?= ($_POST['tipo'] ?? '') === 'caja' ? 'selected' : '' ?>>
                        Caja <span class="tipo-badge badge-caja">Gestión de Citas</span>
                    </option>
                    <option value="lectura" <?= ($_POST['tipo'] ?? '') === 'lectura' ? 'selected' : '' ?>>
                        Lectura <span class="tipo-badge badge-lectura">Solo Ver</span>
                    </option>
                </select>
                <small class="form-text text-muted">
                    <strong>Caja:</strong> Crear/editar citas | 
                    <strong>Lectura:</strong> Solo visualizar<br>
                    <em>Nota: Solo un administrador puede crear otros administradores</em>
                </small>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required>
                <small class="form-text text-muted">Mínimo 6 caracteres</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block btn-register">
                Crear Cuenta
            </button>
        </form>
        
        <div class="login-link">
            <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
        </div>
    </div>
</body>
</html>