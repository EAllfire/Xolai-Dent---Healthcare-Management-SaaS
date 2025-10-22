<?php
require_once 'includes/db.php';

echo "<h2>🔧 Gestión de Contraseñas</h2>";

// Mostrar usuarios existentes
echo "<h3>👥 Usuarios Existentes:</h3>";
$result = $conn->query("SELECT id, nombre, nombre_usuario, correo, tipo FROM agenda_usuarios");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse:collapse; margin: 20px 0;'>";
    echo "<tr style='background:#f8f9fa;'><th style='padding:8px;'>ID</th><th style='padding:8px;'>Nombre</th><th style='padding:8px;'>Usuario</th><th style='padding:8px;'>Correo</th><th style='padding:8px;'>Tipo</th><th style='padding:8px;'>Acción</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding:8px;'>" . $row['id'] . "</td>";
        echo "<td style='padding:8px;'>" . htmlspecialchars($row['nombre']) . "</td>";
        echo "<td style='padding:8px;'>" . htmlspecialchars($row['nombre_usuario'] ?? 'N/A') . "</td>";
        echo "<td style='padding:8px;'>" . htmlspecialchars($row['correo']) . "</td>";
        echo "<td style='padding:8px;'>" . $row['tipo'] . "</td>";
        echo "<td style='padding:8px;'>";
        if (isset($_GET['update_user']) && $_GET['update_user'] == $row['id']) {
            echo "<form method='POST' style='display:inline;'>";
            echo "<input type='hidden' name='user_id' value='" . $row['id'] . "'>";
            echo "<input type='password' name='new_password' placeholder='Nueva contraseña' required style='width:120px;'>";
            echo "<button type='submit' name='change_password' style='background:#28a745;color:white;border:none;padding:4px 8px;'>Cambiar</button>";
            echo "</form>";
        } else {
            echo "<a href='?update_user=" . $row['id'] . "' style='background:#007cba;color:white;padding:4px 8px;text-decoration:none;border-radius:2px;'>Cambiar Password</a>";
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Procesar cambio de contraseña
if ($_POST && isset($_POST['change_password'])) {
    $user_id = intval($_POST['user_id']);
    $new_password = $_POST['new_password'];
    
    if ($user_id && $new_password) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE agenda_usuarios SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $password_hash, $user_id);
        
        if ($stmt->execute()) {
            echo "<div style='background:#d4edda;color:#155724;padding:10px;border-radius:4px;margin:10px 0;'>✅ Contraseña actualizada para usuario ID: $user_id</div>";
        } else {
            echo "<div style='background:#f8d7da;color:#721c24;padding:10px;border-radius:4px;margin:10px 0;'>❌ Error al actualizar contraseña</div>";
        }
        $stmt->close();
    }
}

// Crear usuario de prueba
if ($_POST && isset($_POST['create_test_user'])) {
    $nombre = 'Administrador Test';
    $nombre_usuario = 'test';
    $correo = 'test@hospital.com';
    $password = 'test123';
    $tipo = 'admin';
    
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO agenda_usuarios (nombre, nombre_usuario, correo, password, tipo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $nombre, $nombre_usuario, $correo, $password_hash, $tipo);
    
    if ($stmt->execute()) {
        echo "<div style='background:#d4edda;color:#155724;padding:10px;border-radius:4px;margin:10px 0;'>✅ Usuario test creado: usuario='test', password='test123'</div>";
    } else {
        echo "<div style='background:#f8d7da;color:#721c24;padding:10px;border-radius:4px;margin:10px 0;'>❌ Error: " . $conn->error . "</div>";
    }
    $stmt->close();
}

echo "<hr>";
echo "<h3>🔧 Crear Usuario de Prueba</h3>";
echo "<form method='POST'>";
echo "<button type='submit' name='create_test_user' style='background:#28a745;color:white;padding:10px 20px;border:none;border-radius:4px;'>Crear Usuario Test (usuario: test, password: test123)</button>";
echo "</form>";

echo "<hr>";
echo "<p><strong>📋 Después de cambiar contraseñas o crear usuario test:</strong></p>";
echo "<p><a href='login_simple.php' style='background:#007cba;color:white;padding:10px;text-decoration:none;border-radius:4px;'>Probar Login</a></p>";
?>