<?php
/**
 * Verificar plantilla "citaagendada" en Meta
 */

echo "\n========================================\n";
echo "VERIFICAR PLANTILLA EN META\n";
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
$business_account_id = getenv('WHATSAPP_BUSINESS_ACCOUNT_ID');

echo "📋 DATOS CARGADOS:\n";
echo "   Phone ID: $phone_id\n";
echo "   Business Account: $business_account_id\n\n";

// Obtener lista de plantillas asociadas con este Phone ID
echo "📤 OBTENIENDO PLANTILLAS DEL PHONE ID...\n\n";

$url = "https://graph.facebook.com/v18.0/$phone_id?fields=message_templates&access_token=" . urlencode($access_token);

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

echo "Status: HTTP $http_code\n";

if ($http_code === 200) {
    $data = json_decode($response, true);
    
    if (isset($data['message_templates'])) {
        echo "✓ PLANTILLAS ENCONTRADAS:\n\n";
        
        foreach ($data['message_templates']['data'] as $template) {
            echo "   Nombre: " . $template['name'] . "\n";
            echo "   Status: " . $template['status'] . "\n";
            echo "   Idioma: " . ($template['language'] ?? 'N/A') . "\n";
            
            if (isset($template['components'])) {
                echo "   Componentes:\n";
                foreach ($template['components'] as $comp) {
                    echo "      - " . $comp['type'];
                    if ($comp['type'] === 'body' && isset($comp['parameters'])) {
                        echo " (" . count($comp['parameters']) . " parámetros)\n";
                        foreach ($comp['parameters'] as $param) {
                            echo "        • " . $param['name'] . ": " . ($param['type'] ?? 'text') . "\n";
                        }
                    } else {
                        echo "\n";
                    }
                }
            }
            echo "\n";
        }
    } else {
        echo "No se encontraron plantillas\n";
    }
} else {
    echo "Error al obtener plantillas:\n";
    $data = json_decode($response, true);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

// Si el Business Account está disponible, buscar allí también
if (!empty($business_account_id) && $business_account_id !== '') {
    echo "\n📤 BUSCANDO EN BUSINESS ACCOUNT...\n\n";
    
    $url = "https://graph.facebook.com/v18.0/$business_account_id/message_templates?fields=id,name,status,language,components&access_token=" . urlencode($access_token);
    
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
    
    echo "Status: HTTP $http_code\n";
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        
        if (isset($data['data'])) {
            echo "✓ PLANTILLAS ENCONTRADAS EN BUSINESS ACCOUNT:\n\n";
            
            foreach ($data['data'] as $template) {
                echo "   ID: " . $template['id'] . "\n";
                echo "   Nombre: " . $template['name'] . "\n";
                echo "   Status: " . ($template['status'] ?? 'N/A') . "\n";
                echo "\n";
            }
        }
    }
}

echo "========================================\n";
echo "FIN DE VERIFICACIÓN\n";
echo "========================================\n\n";

?>
