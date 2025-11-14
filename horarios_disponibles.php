<?php
header('Content-Type: application/json');

// Habilitar el registro de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('log_errors', 1);
ini_set('error_log', 'php-error.log'); // Asegúrate de que este archivo tenga permisos de escritura

// Usar __DIR__ para asegurar que la ruta al archivo de base de datos sea siempre correcta.
require_once(__DIR__ . "/includes/db.php");

$fecha = $_GET['fecha'] ?? '';
$modalidad_id = isset($_GET['modalidad_id']) ? intval($_GET['modalidad_id']) : 0;

if (empty($fecha) || $modalidad_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // Verificación de la conexión a la base de datos
    if (!$conn) {
        throw new Exception("No se pudo establecer la conexión a la base de datos.");
    }

    // Consulta para obtener las horas de inicio de las citas ya reservadas para una fecha y modalidad específicas.
    // En el servidor remoto, la modalidad se guarda en `profesional_id`.
    // Se filtra por `profesional_id` para obtener los horarios de la modalidad correcta.
    $sql = "SELECT TIME_FORMAT(hora_inicio, '%H:%i') as hora FROM agenda_citas WHERE fecha = ? AND profesional_id = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // Si la preparación de la consulta falla, es un error grave.
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param("si", $fecha, $modalidad_id);
    $stmt->execute();
    
    // Usar bind_result y fetch para compatibilidad con servidores sin mysqlnd
    $stmt->store_result();
    $stmt->bind_result($hora_ocupada);

    $horarios_ocupados = [];
    while ($stmt->fetch()) {
        if ($hora_ocupada) {
            $horarios_ocupados[] = $hora_ocupada;
        }
    }
    $stmt->close();

    echo json_encode($horarios_ocupados);

} catch (Exception $e) {
    // Registrar el error real en el log del servidor, incluyendo la consulta SQL si está disponible.
    $error_message = "Error en horarios_disponibles.php: " . $e->getMessage();
    if (isset($sql)) { $error_message .= " | SQL: " . $sql; }
    error_log($error_message);
    http_response_code(500); // Enviar un código de estado 500
    echo json_encode(['error' => $error_message]); // Mostrar el error detallado en la respuesta JSON
}
?>