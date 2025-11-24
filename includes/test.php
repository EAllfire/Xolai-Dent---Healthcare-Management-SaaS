<?php
header("Content-Type: text/plain");

$host = "smtp.gmail.com";
$puertos = [25, 465, 587, 388, 16, 26];

echo "=== PRUEBA DE PUERTOS SMTP ===\n";
echo "Probando conexión con: $host\n\n";

foreach ($puertos as $port) {
    echo "Probando puerto $port... ";

    $conexion = @fsockopen($host, $port, $errno, $errstr, 10);

    if (!$conexion) {
        echo "❌ ERROR\n";
        echo "   Código: $errno | Mensaje: $errstr\n\n";
    } else {
        echo "✅ ABIERTO\n\n";
        fclose($conexion);
    }
}

echo "=== PRUEBA FINALIZADA ===\n";
?>
