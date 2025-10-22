<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'includes/db.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$mensaje = '';

// Mensaje de logout exitoso
if (isset($_GET['logout'])) {
    $mensaje = 'Sesión cerrada exitosamente';
}

if ($_POST) {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($usuario && $password) {
        // Primero verificar qué campo de contraseña existe
        $check_fields = $conn->query("DESCRIBE agenda_usuarios");
        $has_password_hash = false;
        $has_password = false;
        
        while ($field = $check_fields->fetch_assoc()) {
            if ($field['Field'] === 'password_hash') {
                $has_password_hash = true;
            } elseif ($field['Field'] === 'password') {
                $has_password = true;
            }
        }
        
        // Usar el campo apropiado
        $password_field = $has_password_hash ? 'password_hash' : 'password';
        $activo_field = "COALESCE(activo, 1) as activo"; // Si no existe campo activo, asumir TRUE
        
        $stmt = $conn->prepare("SELECT id, nombre, nombre_usuario, correo, $password_field as password_hash, tipo, $activo_field FROM agenda_usuarios WHERE nombre_usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $nombre, $nombre_usuario, $correo, $password_hash, $tipo, $activo);
            $stmt->fetch();
            
            $user = [
                'id' => $id,
                'nombre' => $nombre,
                'nombre_usuario' => $nombre_usuario,
                'correo' => $correo,
                'password_hash' => $password_hash,
                'tipo' => $tipo,
                'activo' => $activo
            ];
            
            if (!$user['activo']) {
                $error = 'Usuario desactivado. Contacte al administrador.';
            } elseif (password_verify($password, $user['password_hash'])) {
                // Login exitoso
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['nombre'];
                $_SESSION['usuario_usuario'] = $user['nombre_usuario'];
                $_SESSION['usuario_correo'] = $user['correo'];
                $_SESSION['usuario_tipo'] = $user['tipo'];

                header('Location: https://ha.angelescuauhtemoc.com/Agenda/agenda/index.php');
                exit;
            } else {
                $error = 'Nombre de usuario o contraseña incorrectos';
            }
        } else {
            $error = 'Nombre de usuario o contraseña incorrectos';
        }
        $stmt->close();
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
        <title>Iniciar Sesión</title>
    <!-- VERSION 2.0 - LOGIN CON NOMBRE DE USUARIO (<?php echo date('H:i:s'); ?>) -->
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
        
        .login-container {
            background: rgba(68, 162, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 45px;
            border-radius: 20px;
            box-shadow: 
                0 20px 50px rgba(0,0,0,0.3),
                0 0 0 1px rgba(255,255,255,0.1);
            width: 100%;
            max-width: 420px;
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0.8) 100%);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .logo-section img {
            height: 100px;
            margin-bottom: 15px;
            filter: brightness(1.1) contrast(1.1);
        }
        
        .hospital-title {
            color: white;
            margin: 0 0 5px 0;
            font-size: 24px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            letter-spacing: 0.5px;
        }
        
        .hospital-subtitle {
            color: rgba(255,255,255,0.9);
            margin: 0;
            font-size: 14px;
            font-weight: 400;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .login-title {
            color: white;
            font-size: 18px;
            font-weight: 600;
            margin: 25px 0 0 0;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            color: white;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 8px;
            display: block;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .form-control {
            border-radius: 12px;
            padding: 15px 18px;
            background: rgba(255,255,255,0.95);
            color: #333;
            border: 2px solid rgba(255,255,255,0.3);
            font-size: 15px;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
        }
        
        .form-control:focus {
            border-color: rgba(255,255,255,0.6);
            box-shadow: 0 0 0 4px rgba(255,255,255,0.15);
            background: white;
            transform: translateY(-1px);
        }
        
        .form-control::placeholder {
            color: #999;
            font-size: 14px;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #076d99 0%, #064d6b 100%);
            border: none;
            padding: 15px;
            font-weight: 600;
            color: white;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #064d6b 0%, #043a52 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
            color: white;
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            border: none;
            padding: 12px 16px;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.9);
            color: white;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.9);
            color: white;
        }
        
        .register-link {
            text-align: center;
            margin-top: 25px;
        }
        
        .register-link p {
            color: rgba(255,255,255,0.9);
            margin: 0;
            font-size: 14px;
        }
        
        .register-link a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        .register-link a:hover {
            text-decoration: none;
            color: #e8f4f8;
            text-shadow: 0 0 8px rgba(255,255,255,0.5);
        }
        
        .credentials-box {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(5px);
            padding: 18px;
            border-radius: 12px;
            margin-top: 25px;
            font-size: 12px;
            color: #333;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .credentials-box strong {
            color: #076d99;
        }
        
        /* Responsive Design */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 25px;
                margin: 10px;
                border-radius: 16px;
            }
            
            .hospital-title {
                font-size: 20px;
            }
            
            .logo-section img {
                height: 80px;
            }
        }
        
        /* Animación de entrada */
        .login-container {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo-section">
                <img src="images/logo.png" alt="Hospital Angeles">
                
                <p class="hospital-subtitle">IMAGENOLOGÍA - Sistema de Citas</p>
            </div>
            <h3 class="login-title">INICIAR SESIÓN</h3>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="usuario">Nombre de Usuario</label>
                <input type="text" class="form-control" id="usuario" name="usuario" 
                       placeholder="Ingrese su nombre de usuario"
                       value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" 
                       autocomplete="username" maxlength="50" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Ingrese su contraseña"
                       autocomplete="current-password" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block btn-login">
                Iniciar Sesión
            </button>
        </form>
        
        <div class="register-link">
            <p>¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a></p>
        </div>
        
       
    </div>
    
    <script>
        // Enfocar en el campo de usuario al cargar
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('usuario').focus();
        });
        
        // Validación básica del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const usuario = document.getElementById('usuario').value.trim();
            const password = document.getElementById('password').value;
            
            if (!usuario || !password) {
                e.preventDefault();
                alert('Por favor complete todos los campos');
                return false;
            }
            
            // Validar que el nombre de usuario no contenga caracteres especiales problemáticos
            if (!/^[a-zA-Z0-9_.-]+$/.test(usuario)) {
                e.preventDefault();
                alert('El nombre de usuario solo puede contener letras, números, guiones y puntos');
                return false;
            }
            
            // Verificar que no contenga @ (ya no es email)
            if (usuario.includes('@')) {
                e.preventDefault();
                alert('Ingrese su nombre de usuario, no su correo electrónico');
                return false;
            }
        });
    </script>
</body>
</html>