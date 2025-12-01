<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$response = file_get_contents('php://input');

file_put_contents(__DIR__ . '/includes/debug_guardar_raw.txt', $response . "\n\n", FILE_APPEND);

echo json_encode([
    "success" => true,
    "raw" => $response
]);
