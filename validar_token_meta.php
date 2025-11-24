<?php
/**
 * Script para validar y diagnosticar el token de acceso Meta
 */

echo "\n========================================\n";
echo "VALIDACIÓN DE TOKEN META\n";
echo "========================================\n\n";

// Cargar .env
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    $lineas = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        if (strpos(trim($linea), '#') === 0) continue;
        if (strpos($linea, '=') !== false) {
            list($clave, $valor) = explode('=', $linea, 2);
            $clave = trim($clave);
            $valor = trim($valor);
            if ((substr($valor, 0, 1) === '"' && substr($valor, -1) === '"') ||
                (substr($valor, 0, 1) === "'" && substr($valor, -1) === "'")) {
                $valor = substr($valor, 1, -1);
            }
            if (!getenv($clave)) {
                putenv("$clave=$valor");
            }
        }
    }
}

$access_token = getenv('WHATSAPP_ACCESS_TOKEN');
$phone_id = getenv('WHATSAPP_PHONE_NUMBER_ID');

echo "📋 TOKEN ENCONTRADO EN .env:\n";
echo "   Token length: " . strlen($access_token) . " chars\n";
echo "   Primeros 20 chars: " . substr($access_token, 0, 20) . "...\n";
echo "   Últimos 10 chars: ..." . substr($access_token, -10) . "\n";
echo "   Phone ID: $phone_id\n\n";

// Validar formato del token
echo "🔍 VALIDACIÓN DE FORMATO:\n";
$errores = [];

if (strlen($access_token) < 100) {
    $errores[] = "Token muy corto (< 100 chars). ¿Está completo?";
}

if (strpos($access_token, ' ') !== false) {
    $errores[] = "Token contiene espacios - ¡Inválido!";
}

if (strpos($access_token, '\n') !== false || strpos($access_token, '\r') !== false) {
    $errores[] = "Token contiene saltos de línea - ¡Inválido!";
}

if ($errores) {
    foreach ($errores as $error) {
        echo "   ✗ $error\n";
    }
    echo "\n";
} else {
    echo "   ✓ Formato parece correcto\n\n";
}

// Intentar validar el token con Meta API
echo "🌐 VERIFICANDO TOKEN CON META API:\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://graph.facebook.com/v18.0/me?access_token=" . urlencode($access_token),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => [
        'User-Agent: WhatsApp-Integration/1.0'
    ]
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo "✗ ERROR DE CONEXIÓN: $curl_error\n";
} else {
    $data = json_decode($response, true);
    
    if ($http_code === 200 && isset($data['id'])) {
        echo "✓ TOKEN VÁLIDO\n";
        echo "   ID: " . $data['id'] . "\n";
        echo "   Nombre: " . ($data['name'] ?? 'N/A') . "\n";
    } else {
        echo "✗ TOKEN NO VÁLIDO (HTTP $http_code)\n";
        if (isset($data['error'])) {
            echo "   Error: " . $data['error']['message'] . "\n";
            echo "   Código: " . $data['error']['code'] . "\n";
            
            if ($data['error']['code'] == 190) {
                echo "\n⚠️  POSIBLES CAUSAS:\n";
                echo "   1. Token ha expirado (válido por 2 meses)\n";
                echo "   2. Token fue revocado\n";
                echo "   3. Token no fue copiado correctamente\n";
                echo "   4. Token contiene caracteres invisibles\n";
            }
        }
        echo "\n   Respuesta completa:\n";
        echo "   " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
}

echo "\n========================================\n";
echo "PRÓXIMOS PASOS:\n";
echo "========================================\n";
echo "1. Si el token es VÁLIDO, el problema está en otro lado\n";
echo "2. Si el token NO ES VÁLIDO, necesitas generar uno nuevo en:\n";
echo "   https://developers.facebook.com/apps/\n";
echo "   → Selecciona tu app\n";
echo "   → WhatsApp → API Setup\n";
echo "   → Genera nuevo Permanent Token\n";
echo "3. Actualiza el .env con el nuevo token\n";
echo "4. Ejecuta este script nuevamente\n";
echo "\n";

?>