<?php
// Endpoint temporal para depuración de requests a guardar_cita.php
// Genera un archivo de log con headers, raw body y variables superglobales.

$logDir = __DIR__ . '/tmp';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$logFile = $logDir . '/request_debug.log';

$now = date('Y-m-d H:i:s');
$headers = function_exists('getallheaders') ? getallheaders() : [];
$raw = file_get_contents('php://input');

$entry = [];
$entry['timestamp'] = $now;
$entry['remote_addr'] = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
$entry['method'] = $_SERVER['REQUEST_METHOD'] ?? 'N/A';
$entry['uri'] = $_SERVER['REQUEST_URI'] ?? 'N/A';
$entry['headers'] = $headers;
$entry['raw_input'] = $raw;
$entry['post'] = $_POST;
$entry['cookies'] = $_COOKIE;
$entry['get'] = $_GET;

file_put_contents($logFile, json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n---\n", FILE_APPEND);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Request logged', 'log' => $logFile]);
