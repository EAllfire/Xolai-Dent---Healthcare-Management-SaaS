<?php
// Habilitar la visualización de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- MANEJADOR DE ERRORES GLOBAL ROBUSTO ---
// Captura cualquier error (incluso fatales) y lo convierte en una respuesta JSON.
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($exception) {
    // Limpiar cualquier salida previa que pueda haber ocurrido
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Error fatal en el servidor: ' . $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    exit;
});

// Iniciar el almacenamiento en búfer de salida para capturar cualquier salida inesperada
ob_start();

header('Content-Type: application/json');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/email_functions.php';

// Leer el cuerpo de la solicitud JSON
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

// Si json_decode falla, $data será null.
if (is_null($data)) {
    // Limpiar cualquier salida previa
    ob_clean();
    // Registrar el cuerpo crudo de la solicitud para depuración
    error_log("Cuerpo de solicitud inválido recibido: " . $raw_input);
    http_response_code(400); // Bad Request
    echo json_encode([
        "success" => false,
        "error" => "Error en la solicitud: el cuerpo de la solicitud no es un JSON válido.",
        "raw_input" => $raw_input // Incluir el input crudo para depuración
    ]);
    exit;
}

$fecha = $data['fecha'] ?? '';
$hora_inicio = $data['hora_inicio'] ?? '';
$hora_fin = $data['hora_fin'] ?? '';
$paciente_id = $data['paciente_id'] ?? null;
$servicio_id = $data['servicio_id'] ?? null;
$modalidad_id = $data['modalidad_id'] ?? null;
$estado_id = $data['estado_id'] ?? 1;
$tipo = $data['tipo'] ?? 'normal';
$nota_interna = $data['nota_interna'] ?? '';
$nota_paciente = $data['nota_paciente'] ?? '';

$response = [];

try {
    if (empty($fecha) || empty($hora_inicio) || empty($paciente_id) || empty($modalidad_id)) {
        throw new Exception("Faltan datos obligatorios: fecha, hora_inicio, paciente_id, modalidad_id.");
    }

    // --- LÓGICA DE HORA_FIN ---
    if (empty($hora_fin)) {
        if (!empty($servicio_id)) {
            $stmt_duracion = $conn->prepare("SELECT duracion_minutos FROM portal_servicios WHERE id = ?");
            $stmt_duracion->bind_param("i", $servicio_id);
            $stmt_duracion->execute();
            $stmt_duracion->bind_result($duracion);
            $stmt_duracion->fetch();
            $stmt_duracion->close();
            $minutos_a_sumar = ($duracion > 0) ? intval($duracion) : 30; // Default 30 min if duration is 0 or not found
            $hora_fin = date('H:i:s', strtotime($fecha . ' ' . $hora_inicio) + $minutos_a_sumar * 60);
        } else {
            $hora_fin = date('H:i:s', strtotime($fecha . ' ' . $hora_inicio) + 1800); // Default 30 min
        }
    }

    // --- VERIFICAR EMPALME ---
    $sqlEmpalme = "SELECT COUNT(*) as total FROM agenda_citas 
                   WHERE fecha = ? AND modalidad_id = ? 
                   AND ((hora_inicio < ? AND hora_fin > ?) 
                   OR (hora_inicio >= ? AND hora_inicio < ?))";
    $stmtEmpalme = $conn->prepare($sqlEmpalme);
    $stmtEmpalme->bind_param("sissss", $fecha, $modalidad_id, $hora_fin, $hora_inicio, $hora_inicio, $hora_fin);
    $stmtEmpalme->execute();
    
    // --- INICIO DE CORRECCIÓN ---
    // Cambiar get_result() por store_result() y bind_result() para máxima compatibilidad.
    // Esto evita el error "Commands out of sync" en servidores sin mysqlnd.
    $stmtEmpalme->store_result();
    $stmtEmpalme->bind_result($total_empalme);
    $stmtEmpalme->fetch();
    $stmtEmpalme->close(); // Es crucial cerrar el statement para liberar la conexión.
    // --- FIN DE CORRECCIÓN ---

    if ($total_empalme > 0) {
        throw new Exception("Ya existe una cita en ese horario para la modalidad seleccionada.");
    }

    // --- INSERTAR CITA ---
    $conn->begin_transaction();

    $token = bin2hex(random_bytes(32));

    $sqlInsert = "INSERT INTO agenda_citas (fecha, paciente_id, profesional_id, servicio_id, estado_id, nota_paciente, nota_interna, hora_inicio, hora_fin, modalidad_id, tipo, token) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmtInsert = $conn->prepare($sqlInsert);
    
    // --- SIMPLIFICACIÓN: Por ahora, el profesional_id siempre será NULL ---
    $profesional_id_to_bind = null;

    // --- CORRECCIÓN: Ajustar el tipo de dato para profesional_id ---
    // Cambiamos el tipo de 'profesional_id' de 'i' (integer) a 's' (string).
    // Esto permite que bind_param acepte correctamente el valor `null` sin causar un error fatal.
    // CORRECCIÓN FINAL: La cadena de tipos tenía 11 's'/'i' para 12 '?' en la consulta.
    // Se corrige la cadena de tipos para que coincida con los 12 parámetros y sus tipos.
    $stmtInsert->bind_param(
        "sisiisssssis", 
        $fecha,
        $paciente_id,
        $profesional_id_to_bind,
        $servicio_id,
        $estado_id,
        $nota_paciente,
        $nota_interna,
        $hora_inicio,
        $hora_fin,
        $modalidad_id,
        $tipo,
        $token
    );

    if ($stmtInsert->execute()) {
        $cita_id = $conn->insert_id;
        $conn->commit();
        $response = ["success" => true, "id" => $cita_id];

        // --- ENVIAR CORREO ---
        try {
            $stmt_email = $conn->prepare("SELECT correo FROM portal_pacientes WHERE id = ?");
            $stmt_email->bind_param("i", $paciente_id);
            $stmt_email->execute();
            $stmt_email->bind_result($recipient_email);
            $stmt_email->fetch();
            $stmt_email->close();

            if ($recipient_email) {
                if (send_appointment_confirmation_email($conn, $cita_id, $paciente_id, $recipient_email)) {
                    error_log("Correo de confirmación enviado para la cita ID: " . $cita_id);
                } else {
                    error_log("Falló el envío del correo de confirmación para la cita ID: " . $cita_id);
                }
            }
        } catch (Exception $e) {
            error_log('Error al enviar correo en guardar_cita.php: ' . $e->getMessage());
        }

    } else {
        $conn->rollback();
        throw new Exception("Error al insertar la cita: " . $stmtInsert->error);
    }

} catch (Throwable $e) { 
    // Si la conexión se estableció y sigue activa, intentar revertir.
    if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
        // @ suprime el warning si no hay una transacción activa que revertir.
        @$conn->rollback();
    }
    
    ob_clean(); // Limpiar cualquier salida previa
    // Asegurarse de que el código de estado HTTP refleje el error
    if (!headers_sent()) {
        http_response_code(500);
    }

    // Enviar una respuesta de error JSON más informativa
    $response = [
        "success" => false, 
        "error" => "Ocurrió un error en el servidor al guardar la cita.",
        "error_details" => $e->getMessage(),
        "error_file" => $e->getFile(),
        "error_line" => $e->getLine()
    ];
}

// Finalizar el almacenamiento en búfer de salida y enviar la respuesta
ob_end_clean();
echo json_encode($response);
?>