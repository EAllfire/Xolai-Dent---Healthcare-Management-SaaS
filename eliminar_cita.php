<?php
session_start();
// --- MANEJADOR DE ERRORES GLOBAL ---
// Esto captura cualquier error (incluso fatales) y lo convierte en una respuesta JSON.
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($exception) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Error fatal en el servidor: ' . $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);
});

header('Content-Type: application/json');

try {
    // Incluir la base de datos dentro del bloque try/catch
    // Esta es la corrección más importante para evitar el error HTML.
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/auth.php';

    $user_tipo = $_SESSION['usuario_tipo'] ?? '';
    $id_padre = $_SESSION['id_padre'] ?? null;
    $puede_gestionar_bloqueos = in_array($user_tipo, ['superadmin', 'admin', 'medico', 'dentista', 'dentista_externo']) || (empty($id_padre) && $user_tipo === 'dentista');
    
    // Después de incluir db.php, verificamos si la conexión fue exitosa.
    // La variable $conn viene de db.php.
    if (!$conn || $conn->connect_error) {
        throw new Exception("Falló la conexión a la base de datos. Verifique includes/db.php. Error: " . ($conn->connect_error ?? 'No disponible'));
    }

    // Simplificamos la obtención del ID. Como el frontend ahora usa GET, solo necesitamos $_GET.
    $cita_id = $_GET['cita_id'] ?? null;

    // Validación básica
    if (empty($cita_id)) {
        throw new Exception('ID de cita no recibido por el servidor. El script no pudo extraer el ID de la petición.');
    }
    
    $cita_id = intval($cita_id);

    // Verificar que la cita existe
    $stmt = $conn->prepare("SELECT estado_id, tipo FROM agenda_citas WHERE id = ?");
    if ($stmt === false) {
        throw new Exception('Error al preparar la consulta de verificación.');
    }
    $stmt->bind_param("i", $cita_id);
    $stmt->execute();
    
    $stmt->bind_result($estado_id, $tipo_cita);
    if (!$stmt->fetch()) {
        throw new Exception('La cita con el ID ' . $cita_id . ' no fue encontrada.');
    }
    $stmt->close();

    if (($estado_id == 9 || $tipo_cita === 'bloqueo') && !$puede_gestionar_bloqueos) {
        throw new Exception('No tiene permiso para eliminar bloqueos.');
    }

    // Sincronizar eliminación con Apple Calendar (antes de borrar físicamente la cita de la BD)
    try {
        require_once __DIR__ . '/includes/icloud_functions.php';
        deleteCitaFromAppleCalendar($conn, $cita_id);
    } catch (Throwable $e) {
        error_log("Error al eliminar cita $cita_id en Apple Calendar: " . $e->getMessage());
    }

    // Eliminar la cita
    $stmt = $conn->prepare("DELETE FROM agenda_citas WHERE id = ?");
    if ($stmt === false) {
        throw new Exception('Error al preparar la consulta de eliminación.');
    }
    $stmt->bind_param("i", $cita_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cita eliminada correctamente']);
    } else {
        throw new Exception('Error de base de datos al eliminar la cita: ' . $stmt->error);
    }
    $stmt->close();
    
} catch (Exception $e) {
    // Captura cualquier excepción (incluyendo errores de 'require_once') y la muestra en el JSON
    http_response_code(400); // Bad Request para errores de lógica de negocio
    echo json_encode(['success' => false, 'error' => 'Excepción capturada: ' . $e->getMessage()]);
}
?>