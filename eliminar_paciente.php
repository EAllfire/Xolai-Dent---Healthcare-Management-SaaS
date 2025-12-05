<?php
header('Content-Type: application/json');
require_once __DIR__ . '/includes/db.php';

// Get the posted data
$data = json_decode(file_get_contents("php://input"));

$id = $data->id ?? null;

if (!$id || !is_numeric($id)) {
    echo json_encode(['success' => false, 'error' => 'ID de paciente no válido.']);
    exit;
}

// Check for related appointments
$stmt_check = $conn->prepare("SELECT COUNT(*) FROM agenda_citas WHERE paciente_id = ?");
$stmt_check->bind_param("i", $id);
$stmt_check->execute();
$stmt_check->bind_result($num_citas);
$stmt_check->fetch();
$stmt_check->close();

if ($num_citas > 0) {
    echo json_encode(['success' => false, 'error' => 'No se puede eliminar el paciente porque tiene ' . $num_citas . ' cita(s) asociada(s).']);
    exit;
}

// Proceed with deletion
$stmt = $conn->prepare("DELETE FROM portal_pacientes WHERE id = ?");
if ($stmt === false) {
    echo json_encode(['success' => false, 'error' => 'Error en la preparación de la consulta: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Paciente eliminado correctamente.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se encontró el paciente para eliminar.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error al eliminar el paciente: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>