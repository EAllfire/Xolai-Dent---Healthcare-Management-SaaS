<?php
header('Content-Type: application/json');
echo json_encode([
    "success" => true, 
    "message" => "actualizar_estado.php funciona correctamente",
    "datos_recibidos" => $_POST
]);
?>