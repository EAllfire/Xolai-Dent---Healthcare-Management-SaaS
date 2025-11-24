<?php
/**
 * Debug detallado del envío WhatsApp
 * Muestra exactamente qué se está enviando a Meta API
 */

echo "\n========================================\n";
echo "DEBUG DETALLADO - ENVÍO WHATSAPP\n";
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

echo "📋 INFORMACIÓN DEL TOKEN:\n";
echo "   Longitud: " . strlen($access_token) . " chars\n";
echo "   Primeros 30: " . substr($access_token, 0, 30) . "\n";
echo "   Últimos 30: " . substr($access_token, -30) . "\n";
echo "   Phone ID: $phone_id\n\n";

// Verificar que el token se está leyendo correctamente
echo "🔍 ANÁLISIS DEL TOKEN:\n";

// Buscar caracteres invisibles o problemáticos
$problemas = [];
for ($i = 0; $i < strlen($access_token); $i++) {
    $char = $access_token[$i];
    $ascii = ord($char);
    
    // Verificar caracteres no válidos
    if ($ascii < 32 || $ascii > 126) {
        if ($ascii !== 10 && $ascii !== 13) { // Ignorar \n y \r si están
            $problemas[] = "Posición $i: carácter inválido (ASCII $ascii)";
        }
    }
}

if (empty($problemas)) {
    echo "   ✓ Token contiene solo caracteres válidos (ASCII 32-126)\n";
} else {
    echo "   ✗ Problemas encontrados:\n";
    foreach ($problemas as $p) {
        echo "      $p\n";
    }
}

// Intentar enviar mensaje directamente con curl
echo "\n📤 INTENTANDO ENVÍO DIRECTO CON CURL:\n\n";

$phone_number = '526251281200';  // Sin el +
$url = "https://graph.facebook.com/v18.0/$phone_id/messages";

$payload = [
    'messaging_product' => 'whatsapp',
    'recipient_type' => 'individual',
    'to' => $phone_number,
    'type' => 'template',
    'template' => [
        'name' => 'citaagendada',
        'language' => [
            'code' => 'es_MX'
        ],
        'components' => [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => 'Hospital Angeles'],
                    ['type' => 'text', 'text' => 'Radiografía'],
                    ['type' => 'text', 'text' => '17 de noviembre de 2025'],
                    ['type' => 'text', 'text' => '14:30'],
                    ['type' => 'text', 'text' => 'Estudios de tórax sin contraste']
                ]
            ]
        ]
    ]
];

echo "📍 URL: $url\n";
echo "📦 Método: POST\n";
echo "🔑 Header Authorization: Bearer [token de " . strlen($access_token) . " chars]\n";
echo "📨 Payload JSON:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Enviar petición
echo "⏳ Enviando petición...\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token  // Este es el punto crítico
    ]
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "✉️  RESPUESTA DE META API:\n";
echo "   Status HTTP: $http_code\n";

if ($curl_error) {
    echo "   Error de conexión: $curl_error\n";
} else {
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "   Respuesta (raw): $response\n";
    } else {
        echo "   Respuesta JSON:\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        
        if (isset($data['error'])) {
            echo "\n❌ ERROR DETECTADO:\n";
            echo "   Mensaje: " . ($data['error']['message'] ?? 'N/A') . "\n";
            echo "   Código: " . ($data['error']['code'] ?? 'N/A') . "\n";
            echo "   Type: " . ($data['error']['type'] ?? 'N/A') . "\n";
            
            if ($data['error']['code'] == 190) {
                echo "\n⚠️  ERROR 190: Invalid OAuth access token\n";
                echo "   Posibles causas:\n";
                echo "   1. Token fue revocado\n";
                echo "   2. Token expiró (tienen validez limitada)\n";
                echo "   3. Token no tiene permisos 'whatsapp_business_messaging'\n";
                echo "   4. Líneas en blanco o espacios ocultos en el token\n\n";
                echo "   SOLUCIÓN:\n";
                echo "   Genera un nuevo token PERMANENTE en:\n";
                echo "   https://developers.facebook.com/apps/\n";
                echo "   → App → WhatsApp → API Setup → Generate Token\n";
            }
        } else if (isset($data['messages'])) {
            echo "\n✅ MENSAJE ENVIADO EXITOSAMENTE\n";
            echo "   ID: " . $data['messages'][0]['id'] . "\n";
        }
    }
}

echo "\n========================================\n";
echo "FIN DEL DEBUG\n";
echo "========================================\n\n";

?>
