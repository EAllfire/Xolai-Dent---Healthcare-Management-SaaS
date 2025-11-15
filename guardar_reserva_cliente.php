<?php
header('Content-Type: application/json');
ob_start(); // Iniciar el almacenamiento en búfer de salida para capturar errores inesperados

/**
 * Envía una respuesta JSON estandarizada y termina la ejecución del script.
 * @param array $data Los datos a codificar en JSON.
 * @param int $statusCode El código de estado HTTP a enviar.
 */
function responder_json($data, $statusCode = 200) {
    // Limpiar cualquier salida de búfer inesperada (como errores de PHP si display_errors está activado)
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ===============================
//  CONFIGURACIÓN DE ERRORES Y DEPURACIÓN
// ===============================
ini_set('display_errors', 0); // No mostrar errores en producción. Usar logs.
error_reporting(E_ALL);

// Convertir todos los errores de PHP en excepciones para poder capturarlos en el bloque try-catch.
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Proteger el script para que solo acepte peticiones POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['success' => false, 'error' => 'Método no permitido.'], 405);
}

$conn = null; // Inicializar la variable de conexión

try {
    // ===============================
    //  CONEXIÓN A LA BASE DE DATOS
    // ===============================
    require_once(__DIR__ . "/includes/db.php");
    if ($conn->connect_error) {
        // Este error se maneja dentro de db.php, pero es una doble verificación.
        throw new Exception("Error de conexión a la base de datos.");
    }

    // ===============================
    //  CAPTURA Y VALIDACIÓN DE DATOS DE ENTRADA
    // ===============================
    // Los datos de texto vienen en un campo 'json_data' y los archivos en $_FILES.
    if (!isset($_POST['json_data'])) {
        throw new Exception("No se recibieron los datos del formulario (json_data faltante).");
    }
    $input_data = json_decode($_POST['json_data'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error al procesar los datos del formulario (formato JSON inválido).");
    }

    // Asignar variables desde el JSON decodificado
    $nombre_completo = $input_data['nombre'] ?? '';
    $telefono = $input_data['telefono'] ?? '';
    $email = $input_data['email'] ?? '';
    $fecha_nacimiento = $input_data['fecha_nacimiento'] ?? '';
    $fecha_cita = $input_data['fecha_cita'] ?? '';
    $hora_seleccionada = $input_data['hora_seleccionada'] ?? '';
    $modalidad_id = !empty($input_data['modalidad_id']) ? (int)$input_data['modalidad_id'] : null;
    $servicio_id = !empty($input_data['servicio_id']) ? (int)$input_data['servicio_id'] : null;
    $tipo_reserva = $input_data['tipo_reserva'] ?? '';
    $observaciones = $input_data['observaciones'] ?? '';

    // Validación de campos requeridos
    if (empty($nombre_completo) || empty($telefono) || empty($email) || empty($fecha_cita) || empty($hora_seleccionada) || empty($fecha_nacimiento)) {
        throw new Exception("Faltan datos requeridos: Nombre, teléfono, email, fecha de nacimiento, fecha de cita u hora.");
    }

    // ===============================
    //  MANEJO DE ARCHIVOS (SI EXISTEN)
    // ===============================
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
    
    $url_identificacion = null;
    if (isset($_FILES['foto_identificacion']) && $_FILES['foto_identificacion']['error'] === UPLOAD_ERR_OK) {
        $filename = uniqid('ine_') . '_' . basename($_FILES['foto_identificacion']['name']);
        $target_file = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['foto_identificacion']['tmp_name'], $target_file)) $url_identificacion = $target_file;
    }
    
    $url_orden_medica = null;
    if (isset($_FILES['foto_orden']) && $_FILES['foto_orden']['error'] === UPLOAD_ERR_OK) {
        $filename = uniqid('orden_') . '_' . basename($_FILES['foto_orden']['name']);
        $target_file = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['foto_orden']['tmp_name'], $target_file)) $url_orden_medica = $target_file;
    }

    // ===============================
    //  LÓGICA DE NEGOCIO Y BASE DE DATOS
    // ===============================
    $conn->begin_transaction();

    $nombre_partes = explode(' ', trim($nombre_completo), 2);
    $nombre = $nombre_partes[0];
    $apellido = $nombre_partes[1] ?? '';

    // 1. GESTIÓN DE PACIENTE (BUSCAR O CREAR)
    $stmt_check = $conn->prepare("SELECT id FROM portal_pacientes WHERE correo = ? LIMIT 1");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    if ($stmt_check->num_rows > 0) {
        $stmt_check->bind_result($paciente_id_existente);
        $stmt_check->fetch();
        $paciente_id = $paciente_id_existente;
        $stmt_update = $conn->prepare("UPDATE portal_pacientes SET nombre = ?, apellido = ?, telefono = ? WHERE id = ?");
        $stmt_update->bind_param("sssi", $nombre, $apellido, $telefono, $paciente_id);
        if (!$stmt_update->execute()) throw new Exception("Error al actualizar paciente: " . $stmt_update->error);
    } else {
        $stmt_paciente = $conn->prepare("INSERT INTO portal_pacientes (nombre, apellido, telefono, correo, comentarios, tipo, origen) VALUES (?, ?, ?, ?, ?, 'cliente', 'web')");
        $comentarios_paciente = "Fecha nacimiento: " . $fecha_nacimiento . ($observaciones ? " | " . $observaciones : "");
        $stmt_paciente->bind_param("sssss", $nombre, $apellido, $telefono, $email, $comentarios_paciente);
        if (!$stmt_paciente->execute()) throw new Exception("Error al crear paciente: " . $stmt_paciente->error);
        $paciente_id = $conn->insert_id;
    }

    // 2. PREPARACIÓN DE DATOS DE LA CITA
    $hora_inicio = $hora_seleccionada . ":00";
    $hora_fin = date("H:i:s", strtotime($hora_inicio) + 3600); // Duración de 1 hora por defecto

    $stmt_estado = $conn->prepare("SELECT id FROM agenda_estado_cita WHERE nombre = 'reservado' LIMIT 1");
    $stmt_estado->execute();
    $stmt_estado->store_result();
    $stmt_estado->bind_result($estado_id_existente);
    $stmt_estado->fetch();
    $estado_id = $estado_id_existente ?? 1; // Usar ID 1 como fallback

    $stmt_prof = $conn->prepare("SELECT id FROM agenda_profesionales ORDER BY id LIMIT 1");
    $stmt_prof->execute();
    $stmt_prof->store_result();
    $stmt_prof->bind_result($profesional_id_existente);
    $stmt_prof->fetch();
    $profesional_id = $profesional_id_existente;
    if (!$profesional_id) throw new Exception("No hay profesionales disponibles para asignar la cita.");

    if ($tipo_reserva === 'paquete') {
        $stmt_servicio_paq = $conn->prepare("SELECT id FROM portal_servicios WHERE nombre LIKE '%paquete%' OR nombre LIKE '%integral%' LIMIT 1");
        $stmt_servicio_paq->execute();
        $stmt_servicio_paq->store_result();
        if ($stmt_servicio_paq->num_rows > 0) {
            $stmt_servicio_paq->bind_result($servicio_id);
            $stmt_servicio_paq->fetch();
        } else {
            $stmt_first_serv = $conn->prepare("SELECT id, modalidad_id FROM portal_servicios ORDER BY id LIMIT 1");
            $stmt_first_serv->execute();
            $stmt_first_serv->store_result();
            if ($stmt_first_serv->num_rows > 0) {
                $stmt_first_serv->bind_result($servicio_id_data, $modalidad_id_data);
                $stmt_first_serv->fetch();
                $servicio_id = $servicio_id_data;
                $modalidad_id = $modalidad_id_data;
            } else {
                throw new Exception("No hay servicios disponibles");
            }
        }
        $nota_paciente = "Reserva de paquete web. Detalles en observaciones.";
    } else {
        if (empty($servicio_id) || empty($modalidad_id)) throw new Exception("Servicio o modalidad no especificados para la reserva.");
        $nota_paciente = $observaciones;
    }

    if (empty($modalidad_id)) $modalidad_id = 1; // Fallback final para modalidad

    // 3. VERIFICAR EMPALMES DE HORARIO
    $sqlEmpalme = "SELECT COUNT(*) as total FROM agenda_citas 
                   WHERE fecha = ? AND modalidad_id = ? 
                   AND hora_inicio < ? AND hora_fin > ?";
    $stmtEmpalme = $conn->prepare($sqlEmpalme);
    $stmtEmpalme->bind_param("siss", $fecha_cita, $modalidad_id, $hora_fin, $hora_inicio);
    $stmtEmpalme->execute();
    $stmtEmpalme->store_result();
    $stmtEmpalme->bind_result($total_empalme);
    $stmtEmpalme->fetch();

    if ($total_empalme > 0) throw new Exception("Ya existe una cita en ese horario para la modalidad seleccionada.");

    // 4. CREAR LA CITA
    $stmt_cita = $conn->prepare("INSERT INTO agenda_citas 
        (fecha, hora_inicio, hora_fin, paciente_id, profesional_id, servicio_id, modalidad_id, estado_id, nota_paciente, nota_interna, tipo, url_identificacion, url_orden_medica)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $nota_interna = "Reserva web - Cliente: " . $nombre_completo . " | Email: " . $email;
    $tipo_cita = ($tipo_reserva === 'paquete') ? 'paquete' : 'individual';
    $stmt_cita->bind_param("sssiiiissssss", $fecha_cita, $hora_inicio, $hora_fin, $paciente_id, $profesional_id, $servicio_id, $modalidad_id, $estado_id, $nota_paciente, $nota_interna, $tipo_cita, $url_identificacion, $url_orden_medica);
    if (!$stmt_cita->execute()) throw new Exception("Error al crear la cita: " . $stmt_cita->error);
    $cita_id = $conn->insert_id;

    // 5. CERRAR TODOS LOS STATEMENTS ANTES DE CONFIRMAR LA TRANSACCIÓN
    $stmt_check->close();
    if (isset($stmt_update)) $stmt_update->close();
    if (isset($stmt_paciente)) $stmt_paciente->close();
    $stmt_estado->close();
    $stmt_prof->close();
    if (isset($stmt_servicio_paq)) $stmt_servicio_paq->close();
    if (isset($stmt_first_serv)) $stmt_first_serv->close();
    $stmtEmpalme->close();
    $stmt_cita->close();

    // 6. CONFIRMAR LA TRANSACCIÓN
    $conn->commit();

    // 7. PROCESAR PAGO (POST-TRANSACCIÓN)
    $response = [];
    try {
        require_once(__DIR__ . '/includes/GestorPagos.php');
        $gestorPagos = new GestorPagos($conn);
        $resultado_pago = $gestorPagos->crearPago($cita_id, 'simulador', 'tarjeta');

        if ($resultado_pago['success']) {
            $response['success'] = true;
            $response['message'] = "Reserva creada exitosamente.";
            $response['data'] = ["cita_id" => $cita_id, "pago" => $resultado_pago];
        } else {
            $response['success'] = true; // La cita se creó, pero el pago falló.
            $response['message'] = "Reserva creada, pero hubo un problema al iniciar el pago.";
            $response['pago_error'] = $resultado_pago['error'] ?? 'Error desconocido';
            $response['data'] = ["cita_id" => $cita_id];
        }
    } catch (Exception $e) {
        error_log('Error inicializando pago: ' . $e->getMessage());
        $response['success'] = true; // La cita se creó, pero el sistema de pagos no está disponible.
        $response['message'] = "Reserva creada. El sistema de pagos no está disponible en este momento.";
        $response['data'] = ["cita_id" => $cita_id];
    }

    error_log("Reserva exitosa - Cita ID: {$cita_id}, Paciente ID: {$paciente_id}");
    responder_json($response, 200);

} catch (Exception $e) {
    // Si algo falla, revertir la transacción.
    if ($conn && $conn->ping()) {
        $conn->rollback();
    }
    // Registrar el error detallado en los logs del servidor.
    error_log("Error en guardar_reserva_cliente.php: " . $e->getMessage() . " en " . $e->getFile() . " línea " . $e->getLine());
    
    // Enviar una respuesta de error genérica pero útil al cliente.
    $userMessage = $e->getMessage();
    // Filtrar mensajes para no exponer detalles internos, excepto los que son seguros.
    if (strpos($userMessage, 'Ya existe una cita') === false) {
        $userMessage = 'Ocurrió un error inesperado al procesar su solicitud.';
    }
    responder_json(['success' => false, 'error' => $userMessage], 500);

} finally {
    // Asegurarse de que la conexión a la base de datos siempre se cierre.
    if ($conn) {
        $conn->close();
    }
}

?>
    $conn->close();
}

responder_json($response);
?>
