<?php
header('Content-Type: application/json');

$test_data = [
    [
        "id" => 1,
        "nombre" => "Test Paciente 1",
        "limite_citas_diarias" => 5,
        "descripcion" => "Esto es un dato de prueba"
    ],
    [
        "id" => 2,
        "nombre" => "Test Paciente 2",
        "limite_citas_diarias" => 10000,
        "descripcion" => "Otro dato de prueba"
    ]
];

echo json_encode($test_data);
?>
