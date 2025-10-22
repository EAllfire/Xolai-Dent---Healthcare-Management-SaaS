<?php
// Debug login paso a paso
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>🔍 Debug Login</h2>";

// Paso 1: Sesiones
echo "<h3>Paso 1: Sesiones</h3>";
session_start();
echo "✅ Sesión iniciada<br>";

// Paso 2: Conexión DB
echo "<h3>Paso 2: Base de Datos</h3>";
try {
    require_once 'includes/db.php';
    echo "✅ includes/db.php incluido<br>";
    if (isset($conn)) {
        echo "✅ Variable \$conn existe<br>";
    } else {
        echo "❌ Variable \$conn no existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Error en DB: " . $e->getMessage() . "<br>";
    exit;
}

// Paso 3: Verificar tabla usuarios
echo "<h3>Paso 3: Tabla Usuarios</h3>";
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM agenda_usuarios");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✅ Tabla agenda_usuarios existe con " . $row['total'] . " usuarios<br>";
    }
} catch (Exception $e) {
    echo "❌ Error en tabla: " . $e->getMessage() . "<br>";
}

// Paso 4: Test datos POST
echo "<h3>Paso 4: Datos POST</h3>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "✅ Método POST recibido<br>";
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    echo "Usuario recibido: '$usuario'<br>";
    echo "Password recibido: " . (empty($password) ? "vacío" : "con datos") . "<br>";
    
    if ($usuario && $password) {
        // Test consulta usuario
        echo "<h3>Paso 5: Buscar Usuario</h3>";
        $sql = "SELECT id, usuario, password_hash, nombre, tipo FROM agenda_usuarios WHERE usuario = '$usuario'";
        echo "SQL: $sql<br>";
        
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            echo "✅ Usuario encontrado<br>";
            $user = $result->fetch_assoc();
            echo "ID: " . $user['id'] . "<br>";
            echo "Nombre: " . $user['nombre'] . "<br>";
            echo "Tipo: " . $user['tipo'] . "<br>";
            
            // Test password
            if (password_verify($password, $user['password_hash'])) {
                echo "✅ Password correcto<br>";
            } else {
                echo "❌ Password incorrecto<br>";
            }
        } else {
            echo "❌ Usuario no encontrado<br>";
        }
    }
} else {
    echo "ℹ️ Método GET - mostrando formulario<br>";
}

echo "<hr>";
echo "<h3>Formulario de Prueba:</h3>";
echo '<form method="POST">
    <p>Usuario: <input type="text" name="usuario" value="admin"></p>
    <p>Password: <input type="password" name="password"></p>
    <p><button type="submit">Probar Login</button></p>
</form>';
?>