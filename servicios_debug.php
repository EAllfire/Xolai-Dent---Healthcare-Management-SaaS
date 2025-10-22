<?php
// Debug servicios con duracion
require_once("includes/db.php");

echo "DEBUG SERVICIOS:<br>";

$modalidad_id = $_GET['modalidad_id'] ?? '';
echo "Modalidad ID recibido: '$modalidad_id'<br>";

if (!$modalidad_id || !is_numeric($modalidad_id)) {
    echo "❌ Modalidad ID inválido<br>";
    echo json_encode([]);
    exit;
}

echo "✅ Modalidad ID válido: $modalidad_id<br>";

// Verificar tabla portal_servicios
$check_table = $conn->query("SHOW TABLES LIKE 'portal_servicios'");
if ($check_table->num_rows == 0) {
    echo "❌ Tabla portal_servicios no existe<br>";
    
    // Buscar tablas similares
    $all_tables = $conn->query("SHOW TABLES LIKE '%servicio%'");
    echo "Tablas con 'servicio':<br>";
    while ($table = $all_tables->fetch_array()) {
        echo "- " . $table[0] . "<br>";
    }
    exit;
}

echo "✅ Tabla portal_servicios existe<br>";

// Verificar estructura de la tabla
$columns = $conn->query("DESCRIBE portal_servicios");
echo "Columnas en portal_servicios:<br>";
while ($col = $columns->fetch_assoc()) {
    echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
}

echo "<hr>";

// Verificar qué modalidades existen
echo "<h3>Modalidades disponibles en portal_servicios:</h3>";
$modalidades = $conn->query("SELECT DISTINCT modalidad FROM portal_servicios ORDER BY modalidad");
while ($mod = $modalidades->fetch_assoc()) {
    echo "- Modalidad: " . $mod['modalidad'] . "<br>";
}

echo "<h3>Servicios para modalidad $modalidad_id:</h3>";

$sql = "SELECT id, nombre, duracion AS duracion_minutos FROM portal_servicios WHERE modalidad = ? ORDER BY nombre";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo "❌ Error preparando consulta: " . $conn->error . "<br>";
    exit;
}

$stmt->bind_param("i", $modalidad_id);
$stmt->execute();
$result = $stmt->get_result();

echo "✅ Consulta ejecutada. Registros encontrados: " . $result->num_rows . "<br>";

$servicios = [];
while ($row = $result->fetch_assoc()) {
    $servicios[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'duracion_minutos' => $row['duracion_minutos']
    ];
    echo "Servicio: " . $row['nombre'] . " (Duración: " . $row['duracion_minutos'] . ")<br>";
}

echo "<hr>JSON:<br>";
echo json_encode($servicios);
?>