<?php
session_start();
require_once 'includes/db.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: home.php');
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
        try {
            // Buscar usuario - usar nombre_usuario y password (con hash)
            $sql = "SELECT id, id_padre, nombre_usuario, password, nombre, tipo FROM agenda_usuarios WHERE nombre_usuario = ?";
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Error en prepare: " . $conn->error);
            }
            
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id, $id_padre, $nombre_usuario, $password_hash, $nombre, $tipo);
                $stmt->fetch();
                
                $user = [
                    'id' => $id,
                    'id_padre' => $id_padre,
                    'nombre_usuario' => $nombre_usuario,
                    'password' => $password_hash,
                    'nombre' => $nombre,
                    'tipo' => $tipo
                ];
                
                // Verificar password con hash
                if (password_verify($password, $user['password'])) {
                    // Login exitoso
                    $_SESSION['usuario_id'] = $user['id'];
                    $_SESSION['id_padre'] = $user['id_padre'];
                    $_SESSION['usuario_nombre'] = $user['nombre'];
                    $_SESSION['usuario_tipo'] = $user['tipo'];
                    $_SESSION['usuario_login'] = $user['nombre_usuario'];
                    
                    // Redirigir al dashboard
                    header('Location: home.php');
                    exit;
                } else {
                    $error = 'Credenciales incorrectas';
                }
            } else {
                $error = 'Credenciales incorrectas';
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $error = 'Error del sistema: ' . $e->getMessage();
        }
    } else {
        $error = 'Por favor ingresa usuario y contraseña';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Hospital Angeles</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo h2 {
            color: #333;
            font-weight: bold;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px;
            font-weight: bold;
        }
        .form-control {
            border-radius: 25px;
            padding: 12px 20px;
            border: 2px solid #e9ecef;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: none;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <h2>🏥 Hospital Angeles</h2>
            <p class="text-muted">Sistema de Citas</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <input type="text" class="form-control" name="usuario" placeholder="Usuario" required 
                       value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <input type="password" class="form-control" name="password" placeholder="Contraseña" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block btn-login">
                Iniciar Sesión
            </button>
        </form>
        
        <div class="text-center mt-3">
            <small class="text-muted">
                Sistema de Gestión de Citas Médicas
            </small>
        </div>
    </div>
</body>
</html>