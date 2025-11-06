<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

try {
    $query = "SELECT id, nombre, descripcion, limite_citas_diarias FROM agenda_tipos_paciente ORDER BY nombre";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }

    $tipos = [];
    while ($row = $result->fetch_assoc()) {
        // Asegurarse de que los valores numéricos se traten como números
        $row['id'] = intval($row['id']);
        $row['limite_citas_diarias'] = intval($row['limite_citas_diarias']);
        $tipos[] = $row;
    }

    echo json_encode($tipos);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>