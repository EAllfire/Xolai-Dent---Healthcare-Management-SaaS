<?php
header('Content-Type: application/json');
echo json_encode([
    "success" => true, 
    "message" => "actualizar_cita.php funciona correctamente",
    "datos_recibidos" => $_POST
]);
?>