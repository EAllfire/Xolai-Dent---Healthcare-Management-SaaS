<?php
// Script de diagnóstico para login
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Diagnóstico de Login</h2>";

// Test 1: Conexión a base de datos
echo "<h3>1. Test de Conexión a Base de Datos</h3>";
try {
    require_once 'includes/db.php';
    if ($conn) {
        echo "✅ Conexión exitosa<br>";
        echo "Base de datos: " . $conn->select_db('hac') . "<br>";
    } else {
        echo "❌ Error de conexión<br>";
    }
} catch (Exception $e) {
    echo "❌ Excepción: " . $e->getMessage() . "<br>";
}

// Test 2: Verificar tabla de usuarios
echo "<h3>2. Test de Tabla de Usuarios</h3>";
try {
    $result = $conn->query("SHOW TABLES LIKE 'agenda_usuarios'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Tabla agenda_usuarios existe<br>";
        
        // Verificar estructura
        $structure = $conn->query("DESCRIBE agenda_usuarios");
        echo "<strong>Campos:</strong> ";
        while ($field = $structure->fetch_assoc()) {
            echo $field['Field'] . " (" . $field['Type'] . "), ";
        }
        echo "<br>";
        
        // Contar usuarios
        $count = $conn->query("SELECT COUNT(*) as total FROM agenda_usuarios");
        $total = $count->fetch_assoc()['total'];
        echo "Total usuarios: $total<br>";
        
    } else {
        echo "❌ Tabla agenda_usuarios no existe<br>";
    }
} catch (Exception $e) {
    echo "❌ Error al verificar tabla: " . $e->getMessage() . "<br>";
}

// Test 3: Verificar archivos incluidos
echo "<h3>3. Test de Archivos</h3>";
$files = [
    'includes/db.php',
    'includes/auth.php',
    'login_simple.php',
    'admin_usuarios.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file existe<br>";
    } else {
        echo "❌ $file NO existe<br>";
    }
}

// Test 4: Test simple de password_hash
echo "<h3>4. Test de Funciones PHP</h3>";
try {
    $test_hash = password_hash('test123', PASSWORD_DEFAULT);
    $test_verify = password_verify('test123', $test_hash);
    echo "✅ password_hash funciona<br>";
    echo "✅ password_verify funciona: " . ($test_verify ? 'SÍ' : 'NO') . "<br>";
} catch (Exception $e) {
    echo "❌ Error con password functions: " . $e->getMessage() . "<br>";
}

// Test 5: Simulación de login básico
echo "<h3>5. Test de Login Básico</h3>";
try {
    if (isset($conn)) {
        $test_user = 'admin'; // o cualquier usuario que sepas que existe
        $stmt = $conn->prepare("SELECT id, nombre, nombre_usuario, correo, password, tipo FROM agenda_usuarios WHERE nombre_usuario = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $test_user);
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
                
                echo "✅ Usuario '$test_user' encontrado<br>";
                echo "ID: " . $user['id'] . "<br>";
                echo "Nombre: " . $user['nombre'] . "<br>";
                echo "Tipo: " . $user['tipo'] . "<br>";
                echo "Password hash existe: " . (!empty($user['password']) ? 'SÍ' : 'NO') . "<br>";
            } else {
                echo "❌ Usuario '$test_user' no encontrado<br>";
            }
            $stmt->close();
        } else {
            echo "❌ Error preparando consulta<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error en test de login: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>📋 Instrucciones</h3>";
echo "<p>1. Revisa todos los tests arriba</p>";
echo "<p>2. Si hay errores, cópialos y compártelos</p>";
echo "<p>3. Si todo está OK, el problema puede ser en login_simple.php</p>";

echo "<p><strong>Archivos para revisar:</strong></p>";
echo "<p><a href='login_simple.php'>→ Probar login_simple.php</a></p>";
echo "<p><a href='generar_hash.php'>→ Gestión de contraseñas</a></p>";
?>