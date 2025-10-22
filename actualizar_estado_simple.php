<?php
header('Content-Type: application/json');

// Test básico sin base de datos
$response = [
    "success" => true,
    "message" => "actualizar_estado.php recibido",
    "metodo" => $_SERVER['REQUEST_METHOD'] ?? 'undefined',
    "datos_post" => $_POST,
    "timestamp" => date('Y-m-d H:i:s')
];

echo json_encode($response);
?>