<?php
require_once("includes/db.php");

// Debug: verificar qué pasa con la consulta
echo "DEBUG Estados:<br>";

// Verificar si la tabla existe
$check_table = $conn->query("SHOW TABLES LIKE 'agenda_estado_cita'");
if ($check_table->num_rows == 0) {
    echo "❌ Tabla agenda_estado_cita no existe<br>";
    
    // Buscar tablas similares
    $all_tables = $conn->query("SHOW TABLES LIKE '%estado%'");
    echo "Tablas con 'estado':<br>";
    while ($table = $all_tables->fetch_array()) {
        echo "- " . $table[0] . "<br>";
    }
    exit;
}

echo "✅ Tabla agenda_estado_cita existe<br>";

$sql = "SELECT * FROM agenda_estado_cita ORDER BY id";
$result = $conn->query($sql);

if (!$result) {
    echo "❌ Error en consulta: " . $conn->error . "<br>";
    exit;
}

echo "✅ Consulta exitosa. Registros: " . $result->num_rows . "<br>";

$estados = [];
while ($row = $result->fetch_assoc()) {
    $estados[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre']
    ];
    echo "Estado: " . $row['id'] . " - " . $row['nombre'] . "<br>";
}

echo "<hr>JSON:<br>";
echo json_encode($estados);
?>