<?php
/**
 * Obtener información de la cuenta de WhatsApp Business
 */

echo "\n========================================\n";
echo "OBTENER INFO DE WHATSAPP BUSINESS\n";
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

echo "📱 OBTENIENDO INFORMACIÓN DEL PHONE NUMBER...\n\n";

// Obtener información del teléfono incluyendo la cuenta de negocio
$url = "https://graph.facebook.com/v18.0/$phone_id?fields=id,display_phone_number,verified_name,quality_rating,messaging_limits,business_account_id&access_token=" . urlencode($access_token);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status HTTP: $http_code\n\n";

$data = json_decode($response, true);

if ($http_code === 200) {
    echo "✓ INFORMACIÓN DEL TELÉFONO:\n";
    echo "  ID: " . ($data['id'] ?? 'N/A') . "\n";
    echo "  Número: " . ($data['display_phone_number'] ?? 'N/A') . "\n";
    echo "  Nombre verificado: " . ($data['verified_name'] ?? 'N/A') . "\n";
    echo "  Business Account ID: " . ($data['business_account_id'] ?? 'NO ENCONTRADO') . "\n";
    echo "  Rating: " . ($data['quality_rating'] ?? 'N/A') . "\n";
    
    if (isset($data['business_account_id'])) {
        $biz_account = $data['business_account_id'];
        
        echo "\n📋 OBTENER PLANTILLAS DEL BUSINESS ACCOUNT ($biz_account)...\n\n";
        
        $url2 = "https://graph.facebook.com/v18.0/$biz_account/message_templates?fields=id,name,status,language,components&access_token=" . urlencode($access_token);
        
        $ch2 = curl_init();
        curl_setopt_array($ch2, [
            CURLOPT_URL => $url2,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response2 = curl_exec($ch2);
        $http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
        
        echo "Status HTTP: $http_code2\n";
        
        if ($http_code2 === 200) {
            $data2 = json_decode($response2, true);
            
            if (isset($data2['data']) && !empty($data2['data'])) {
                echo "\n✅ PLANTILLAS ENCONTRADAS:\n\n";
                
                $plantilla_encontrada = false;
                foreach ($data2['data'] as $template) {
                    $nombre = $template['name'] ?? 'N/A';
                    echo "📌 " . $nombre . "\n";
                    echo "   ID: " . ($template['id'] ?? 'N/A') . "\n";
                    echo "   Status: " . ($template['status'] ?? 'DESCONOCIDO') . "\n";
                    echo "   Idioma: " . ($template['language'] ?? 'N/A') . "\n";
                    
                    if ($nombre === 'citaagendada') {
                        $plantilla_encontrada = true;
                        echo "   ✅ ESTA ES LA PLANTILLA QUE NECESITAMOS\n";
                        
                        if (isset($template['components'])) {
                            echo "   Estructura:\n";
                            foreach ($template['components'] as $comp) {
                                if ($comp['type'] === 'body' && isset($comp['parameters'])) {
                                    echo "      Body: " . count($comp['parameters']) . " parámetros\n";
                                    foreach ($comp['parameters'] as $i => $param) {
                                        $var_num = $i + 1;
                                        echo "         {{$var_num}}: " . $param['name'] . " (" . ($param['type'] ?? 'text') . ")\n";
                                    }
                                }
                            }
                        }
                    }
                    echo "\n";
                }
                
                if (!$plantilla_encontrada) {
                    echo "\n⚠️  PLANTILLA 'citaagendada' NO ENCONTRADA\n";
                    echo "   CREAR PLANTILLA EN META:\n";
                    echo "   1. Ve a: https://developers.facebook.com/apps/\n";
                    echo "   2. Selecciona tu app\n";
                    echo "   3. WhatsApp → Message Templates\n";
                    echo "   4. Crea nueva plantilla:\n";
                    echo "      - Nombre: citaagendada\n";
                    echo "      - Categoría: BOOKING_UPDATE o APPOINTMENT_UPDATE\n";
                    echo "      - Contenido:\n";
                    echo "        Hola {{1}}, tu cita está confirmada:\n";
                    echo "        📋 Servicio: {{2}}\n";
                    echo "        📅 Fecha: {{3}}\n";
                    echo "        🕐 Hora: {{4}}\n";
                    echo "        ℹ️ {{5}}\n";
                }
            } else {
                echo "\n❌ NO SE ENCONTRARON PLANTILLAS\n";
                echo "   Respuesta: " . json_encode($data2, JSON_PRETTY_PRINT) . "\n";
            }
        } else {
            echo "❌ ERROR al obtener plantillas:\n";
            echo json_encode(json_decode($response2, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
} else {
    echo "❌ ERROR al obtener información del teléfono:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n========================================\n";

?>
