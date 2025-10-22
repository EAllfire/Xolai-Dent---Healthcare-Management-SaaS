<?php
// Debug endpoint: devuelve DESCRIBE y filas de portal_servicios en JSON (no requiere auth)
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

$out = [
    'ok' => true,
    'timestamp' => date('c')
];

// DESCRIBE
$res = $conn->query("DESCRIBE portal_servicios");
if (!$res) {
    $out['describe_error'] = $conn->error;
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
$cols = [];
while ($r = $res->fetch_assoc()) {
    $cols[] = $r;
}
$out['columns'] = $cols;

// Count
$c = $conn->query("SELECT COUNT(*) as total FROM portal_servicios");
if ($c) {
    $out['total'] = intval($c->fetch_assoc()['total']);
} else {
    $out['total_error'] = $conn->error;
}

// Sample rows
$s = $conn->query("SELECT * FROM portal_servicios LIMIT 20");
if ($s) {
    $rows = [];
    while ($r = $s->fetch_assoc()) {
        $rows[] = $r;
    }
    $out['sample_rows'] = $rows;
} else {
    $out['sample_rows_error'] = $conn->error;
}

// Quick hint for field mapping
$out['hint'] = "If columns include 'modalidad_id' or 'duracion', note them; the production endpoints must use the correct names (e.g. modalidad vs modalidad_id, duracion vs duracion_minutos).";

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
