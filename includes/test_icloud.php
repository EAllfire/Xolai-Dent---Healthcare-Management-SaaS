<?php

$email = "eliordo20@icloud.com";
$app_password = "wiqa-phax-ngeh-fcpi"; // 👈 NO tu contraseña normal

$auth = base64_encode($email . ":" . $app_password);

$url = "https://caldav.icloud.com/";

$xml = '<?xml version="1.0" encoding="utf-8" ?>
<D:propfind xmlns:D="DAV:">
    <D:prop>
        <D:current-user-principal/>
    </D:prop>
</D:propfind>';

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CUSTOMREQUEST => "PROPFIND",
    CURLOPT_POSTFIELDS => $xml,
    CURLOPT_HTTPHEADER => [
        "Depth: 0",
        "Content-Type: text/xml; charset=utf-8",
        "Authorization: Basic $auth",
        "User-Agent: Mozilla/5.0 (Xolai CalDAV Test)"
    ],

    // 🔥 DEBUG TOTAL
    CURLOPT_VERBOSE => true,
    CURLOPT_HEADER => true,

    // ⚠️ SOLO PARA LOCAL (evita errores SSL)
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,

    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$curl_error = curl_error($ch);

curl_close($ch);

// 🔎 OUTPUT BONITO
echo "<h2>Resultado iCloud CalDAV Test</h2>";

echo "<b>HTTP Code:</b> $http_code <br>";
echo "<b>Final URL:</b> $final_url <br>";
echo "<b>cURL Error:</b> " . ($curl_error ?: "NINGUNO") . "<br><br>";

echo "<h3>Respuesta completa:</h3>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";