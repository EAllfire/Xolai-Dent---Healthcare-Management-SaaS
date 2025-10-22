<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Login Ultra Simple</h2>";

// Conexión básica
$host = 'localhost';
$username = 'eli';
$password = 'Colombia.2024*';
$database = 'hac';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error);
}

echo "✅ Conectado a la base de datos<br><br>";

// Procesar login
if ($_POST && isset($_POST['usuario']) && isset($_POST['password'])) {
    $usuario = $_POST['usuario'];
    $password_input = $_POST['password'];
    
    echo "<h3>Procesando login para: $usuario</h3>";
    
    // Buscar usuario
    $sql = "SELECT id, nombre, nombre_usuario, correo, password, tipo FROM agenda_usuarios WHERE nombre_usuario = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("❌ Error preparando consulta: " . $conn->error);
    }
    
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $nombre, $nombre_usuario, $correo, $password, $tipo);
        $stmt->fetch();
        
        $user = [
            'id' => $id,
            'nombre' => $nombre,
            'nombre_usuario' => $nombre_usuario,
            'correo' => $correo,
            'password' => $password,
            'tipo' => $tipo
        ];
        
        echo "✅ Usuario encontrado: " . $user['nombre'] . "<br>";
        
        // Verificar password
        if (password_verify($password_input, $user['password'])) {
            echo "✅ Contraseña correcta<br>";
            echo "<div style='background:#d4edda;padding:10px;margin:10px 0;'>🎉 LOGIN EXITOSO</div>";
            echo "<p><a href='index.php'>→ Ir al sistema</a></p>";
        } else {
            echo "❌ Contraseña incorrecta<br>";
            // Para debug, mostrar el hash
            echo "Hash en DB: " . substr($user['password'], 0, 20) . "...<br>";
        }
    } else {
        echo "❌ Usuario no encontrado<br>";
    }
    
    $stmt->close();
    echo "<hr>";
}

// Mostrar usuarios disponibles
echo "<h3>👥 Usuarios Disponibles:</h3>";
$result = $conn->query("SELECT nombre_usuario, nombre, tipo FROM agenda_usuarios");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "• Usuario: <strong>" . $row['nombre_usuario'] . "</strong> (" . $row['nombre'] . ") - Tipo: " . $row['tipo'] . "<br>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Login Test</title>
</head>
<body>
    <h3>🔐 Formulario de Login</h3>
    <form method="POST">
        <p>
            <label>Usuario:</label><br>
            <input type="text" name="usuario" required style="padding:5px; width:200px;">
        </p>
        <p>
            <label>Contraseña:</label><br>
            <input type="password" name="password" required style="padding:5px; width:200px;">
        </p>
        <p>
            <button type="submit" style="background:#007cba;color:white;padding:10px 20px;border:none;">Probar Login</button>
        </p>
    </form>
    
    <hr>
    <p><strong>Usuarios de prueba sugeridos:</strong></p>
    <p>• admin / admin123</p>
    <p>• test / test123 (si lo creaste)</p>
    
    <p><a href="debug_login.php">← Volver al diagnóstico</a></p>
</body>
</html>