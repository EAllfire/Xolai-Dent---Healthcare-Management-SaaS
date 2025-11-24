<?php
// Endpoint temporal para depuración que ECHOA los headers, raw body y superglobales
header('Content-Type: application/json');

$headers = function_exists('getallheaders') ? getallheaders() : [];
$raw = file_get_contents('php://input');

$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
    'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
    'headers' => $headers,
    'raw_input' => $raw,
    'post' => $_POST,
    'get' => $_GET,
    'cookies' => $_COOKIE
];

// Evitar revelar información sensible en producción
if (isset($response['headers']['Authorization'])) {
    $response['headers']['Authorization'] = 'REDACTED';
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
