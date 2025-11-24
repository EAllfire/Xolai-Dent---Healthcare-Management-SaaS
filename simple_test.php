<?php
$token = trim(file_get_contents('.env_token.txt') ?? '');
if (!$token) {
    $env_content = file_get_contents('.env');
    preg_match('/WHATSAPP_ACCESS_TOKEN=(.+)/', $env_content, $m);
    $token = trim($m[1] ?? '');
}

$phone_id = '524158624107021';

echo "🔍 OBTENIENDO DATOS DEL PHONE NUMBER\n\n";

$ch = curl_init("https://graph.facebook.com/v18.0/$phone_id?access_token=" . urlencode($token));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resp = json_decode(curl_exec($ch), true);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code == 200) {
    echo "✓ ID: " . $resp['id'] . "\n";
    echo "✓ Número: " . ($resp['display_phone_number'] ?? 'N/A') . "\n";
} else {
    echo "Error: " . json_encode($resp) . "\n";
    exit(1);
}

?>
