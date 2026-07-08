<?php
session_start();
require_once('includes/db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_usuario = $_POST['nombre_usuario'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($nombre_usuario) || empty($password)) {
        header('Location: login.php?error=Usuario y contraseña son requeridos.');
        exit;
    }

    // Usar consultas preparadas para prevenir inyección SQL
    $stmt = $conn->prepare("SELECT id, nombre, password, tipo, activo FROM agenda_usuarios WHERE nombre_usuario = ?");
    if (!$stmt) {
        // En un entorno de producción, registrar este error en lugar de mostrarlo.
        header('Location: login.php?error=Error del servidor. Intente más tarde.');
        exit;
    }

    $stmt->bind_param("s", $nombre_usuario);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $usuario = $result->fetch_assoc();

        // Verificar si el usuario está activo
        if (!$usuario['activo']) {
            header('Location: login.php?error=La cuenta de usuario está desactivada.');
            exit;
        }

        // Verificar la contraseña
        if (password_verify($password, $usuario['password'])) {
            // Contraseña correcta, iniciar sesión
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_tipo'] = $usuario['tipo'];
            
            // Redirigir al panel principal
            header('Location: index.php');
            exit;
        } else {
            // Contraseña incorrecta
            header('Location: login.php?error=Nombre de usuario o contraseña incorrectos.');
            exit;
        }
    } else {
        // Usuario no encontrado
        header('Location: login.php?error=Nombre de usuario o contraseña incorrectos.');
        exit;
    }

    $stmt->close();
    $conn->close();

} else {
    // Si no es POST, redirigir al login
    header('Location: login.php');
    exit;
}
?>