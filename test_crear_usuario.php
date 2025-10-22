<?php
require_once 'includes/db.php';

$error = '';
$success = '';

if ($_POST && isset($_POST['crear_usuario'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $tipo = $_POST['tipo'] ?? 'lectura';
    
    if ($nombre && $nombre_usuario && $email && $password) {
        // Verificar si el usuario ya existe
        $stmt = $conn->prepare("SELECT id FROM agenda_usuarios WHERE nombre_usuario = ? OR correo = ?");
        $stmt->bind_param("ss", $nombre_usuario, $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = 'El usuario o correo ya existe';
        } else {
            // Crear usuario
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO agenda_usuarios (nombre, nombre_usuario, correo, password, tipo) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nombre, $nombre_usuario, $email, $password_hash, $tipo);
            
            if ($stmt->execute()) {
                $success = "Usuario '$nombre_usuario' creado exitosamente";
            } else {
                $error = 'Error al crear usuario: ' . $conn->error;
            }
        }
        $stmt->close();
    } else {
        $error = 'Complete todos los campos';
    }
}

// Mostrar usuarios existentes
echo "<h2>🔧 Test Crear Usuario</h2>";

if ($error) {
    echo "<div style='background:#f8d7da;color:#721c24;padding:10px;margin:10px 0;border-radius:4px;'>❌ $error</div>";
}

if ($success) {
    echo "<div style='background:#d4edda;color:#155724;padding:10px;margin:10px 0;border-radius:4px;'>✅ $success</div>";
}

echo "<h3>👥 Usuarios Existentes:</h3>";
$result = $conn->query("SELECT nombre_usuario, nombre, correo, tipo FROM agenda_usuarios ORDER BY id DESC");
if ($result) {
    echo "<table border='1' style='border-collapse:collapse;margin:20px 0;'>";
    echo "<tr style='background:#f8f9fa;'><th style='padding:8px;'>Usuario</th><th style='padding:8px;'>Nombre</th><th style='padding:8px;'>Correo</th><th style='padding:8px;'>Tipo</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding:8px;'>" . htmlspecialchars($row['nombre_usuario']) . "</td>";
        echo "<td style='padding:8px;'>" . htmlspecialchars($row['nombre']) . "</td>";
        echo "<td style='padding:8px;'>" . htmlspecialchars($row['correo']) . "</td>";
        echo "<td style='padding:8px;'>" . $row['tipo'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Crear Usuario</title>
</head>
<body>
    <h3>➕ Crear Nuevo Usuario</h3>
    <form method="POST">
        <table>
            <tr>
                <td><label>Nombre completo:</label></td>
                <td><input type="text" name="nombre" required style="padding:5px;width:200px;"></td>
            </tr>
            <tr>
                <td><label>Nombre de usuario:</label></td>
                <td><input type="text" name="nombre_usuario" required style="padding:5px;width:200px;"></td>
            </tr>
            <tr>
                <td><label>Correo:</label></td>
                <td><input type="email" name="email" required style="padding:5px;width:200px;"></td>
            </tr>
            <tr>
                <td><label>Contraseña:</label></td>
                <td><input type="password" name="password" required style="padding:5px;width:200px;"></td>
            </tr>
            <tr>
                <td><label>Tipo:</label></td>
                <td>
                    <select name="tipo" style="padding:5px;width:212px;">
                        <option value="admin">Administrador</option>
                        <option value="caja">Caja</option>
                        <option value="lectura" selected>Lectura</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <button type="submit" name="crear_usuario" style="background:#28a745;color:white;padding:10px 20px;border:none;border-radius:4px;margin-top:10px;">Crear Usuario</button>
                </td>
            </tr>
        </table>
    </form>
    
    <hr>
    <p><a href="admin_usuarios.php">← Volver al admin normal</a></p>
    <p><a href="login_test.php">→ Probar login</a></p>
</body>
</html>