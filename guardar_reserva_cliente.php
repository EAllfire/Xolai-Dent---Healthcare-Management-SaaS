<?php
header('Content-Type: application/json');
ob_start(); // Iniciar el almacenamiento en búfer de salida para capturar errores inesperados

//  Wrapper para enviar respuestas JSON y terminar el script
function responder_json($data) {
    ob_end_clean(); // Limpiar cualquier salida inesperada
    echo json_encode($data);
    exit;
}

// ===============================
//  CONFIGURACIÓN DE DEPURACIÓN
// ===============================
ini_set('display_errors', 0); // No mostrar errores en la salida
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    // Convertir errores de PHP en excepciones para capturarlos
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// --- INICIO DE CORRECCIÓN ---
// Verificar que la solicitud sea por método POST.
// Esto evita que el script se ejecute si se accede directamente por URL (GET).
// Si no es POST, redirigimos al cliente a la página principal.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cliente.php');
    exit;
}
// --- FIN DE CORRECCIÓN ---

try {
    // ===============================
    //  CONEXIÓN A LA BASE DE DATOS
    // ===============================
    error_log('guardar_reserva_cliente.php - Intentando incluir db.php');
    require_once(__DIR__ . "/includes/db.php");
    error_log('guardar_reserva_cliente.php - db.php incluido correctamente');

    // ===============================
    //  CAPTURA DE DATOS
    // ===============================
    error_log('guardar_reserva_cliente.php - Datos recibidos: ' . json_encode($_POST));

    $nombre_completo = $_POST['nombre'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $email = $_POST['email'] ?? '';
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $fecha_cita = $_POST['fecha_cita'] ?? '';
    $hora_seleccionada = $_POST['hora_seleccionada'] ?? '';
    $modalidad_id = $_POST['modalidad_id'] ?? null;
    $servicio_id = $_POST['servicio_id'] ?? null;
    $tipo_reserva = $_POST['tipo_reserva'] ?? '';
    $observaciones = $_POST['observaciones'] ?? '';
    $paquete_tipo = $_POST['paquete_tipo'] ?? '';
    $paquete_servicios = $_POST['paquete_servicios'] ?? '';

    // --- MANEJO DE ARCHIVOS ---
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $url_identificacion = null;
    if (isset($_FILES['foto_identificacion']) && $_FILES['foto_identificacion']['error'] == 0) {
        $filename = uniqid('ine_') . '_' . basename($_FILES['foto_identificacion']['name']);
        $target_file = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['foto_identificacion']['tmp_name'], $target_file)) {
            $url_identificacion = $target_file;
        }
    }

    $url_orden_medica = null;
    if (isset($_FILES['foto_orden']) && $_FILES['foto_orden']['error'] == 0) {
        $filename = uniqid('orden_') . '_' . basename($_FILES['foto_orden']['name']);
        $target_file = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['foto_orden']['tmp_name'], $target_file)) {
            $url_orden_medica = $target_file;
        }
    }

    // ===============================
    //  VALIDACIÓN DE DATOS
    // ===============================
    if (empty($nombre_completo) || empty($telefono) || empty($email) || empty($fecha_cita) || empty($hora_seleccionada)) {
        throw new Exception("Faltan datos requeridos");
    }

    // Separar nombre y apellido
    $nombre_partes = explode(' ', trim($nombre_completo), 2);
    $nombre = $nombre_partes[0];
    $apellido = isset($nombre_partes[1]) ? $nombre_partes[1] : '';

    $conn->begin_transaction();

    // ===============================
    //  PACIENTE
    // ===============================
    $stmt_check = $conn->prepare("SELECT id FROM portal_pacientes WHERE correo = ? LIMIT 1");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $stmt_check->store_result();
    $stmt_check->bind_result($paciente_id_existente);
    $stmt_check->fetch();

    if ($stmt_check->num_rows > 0) {
        $paciente_id = $paciente_id_existente;
        $stmt_update = $conn->prepare("UPDATE portal_pacientes SET nombre = ?, apellido = ?, telefono = ? WHERE id = ?");
        $stmt_update->bind_param("sssi", $nombre, $apellido, $telefono, $paciente_id);
        $stmt_update->execute();
    } else {
        $stmt_paciente = $conn->prepare("INSERT INTO portal_pacientes (nombre, apellido, telefono, correo, comentarios, tipo, origen) VALUES (?, ?, ?, ?, ?, 'cliente', 'web')");
        $comentarios_paciente = "Fecha nacimiento: " . $fecha_nacimiento . ($observaciones ? " | " . $observaciones : "");
        $stmt_paciente->bind_param("sssss", $nombre, $apellido, $telefono, $email, $comentarios_paciente);
        if (!$stmt_paciente->execute()) {
            throw new Exception("Error al crear paciente: " . $stmt_paciente->error);
        }
        $paciente_id = $conn->insert_id;
    }

    // ===============================
    //  CALCULAR HORAS
    // ===============================
    $hora_inicio = $hora_seleccionada . ":00";
    $hora_fin_timestamp = strtotime($hora_inicio) + (60 * 60);
    $hora_fin = date("H:i:s", $hora_fin_timestamp);

    // ===============================
    //  ESTADO "RESERVADO"
    // ===============================
    $stmt_estado = $conn->prepare("SELECT id FROM agenda_estado_cita WHERE nombre = 'reservado' LIMIT 1");
    $stmt_estado->execute();
    $stmt_estado->store_result();
    $stmt_estado->bind_result($estado_id_existente);
    $stmt_estado->fetch();
    $estado_id = $stmt_estado->num_rows > 0 ? $estado_id_existente : 1;

    // ===============================
    //  PROFESIONAL
    // ===============================
    $stmt_prof = $conn->prepare("SELECT id FROM agenda_profesionales ORDER BY id LIMIT 1");
    $stmt_prof->execute();
    $stmt_prof->store_result();
    $stmt_prof->bind_result($profesional_id_existente);
    $stmt_prof->fetch();

    if ($stmt_prof->num_rows > 0) {
        $profesional_id = $profesional_id_existente;
    } else {
        throw new Exception("No hay profesionales disponibles");
    }

    // ===============================
    //  TIPO DE RESERVA
    // ===============================
    if ($tipo_reserva === 'paquete') {
        $stmt_servicio_paq = $conn->prepare("SELECT id FROM portal_servicios WHERE nombre LIKE '%paquete%' OR nombre LIKE '%integral%' LIMIT 1");
        $stmt_servicio_paq->execute();
        $stmt_servicio_paq->store_result();
        $stmt_servicio_paq->bind_result($servicio_id_existente);
        $stmt_servicio_paq->fetch();

        if ($stmt_servicio_paq->num_rows > 0) {
            $servicio_id = $servicio_id_existente;
        } else {
            $stmt_first_serv = $conn->prepare("SELECT id, modalidad_id FROM portal_servicios ORDER BY id LIMIT 1");
            $stmt_first_serv->execute();
            $stmt_first_serv->store_result();
            $stmt_first_serv->bind_result($servicio_id_data, $modalidad_id_data);
            $stmt_first_serv->fetch();
            if ($stmt_first_serv->num_rows > 0) {
                $servicio_id = $servicio_id_data;
                $modalidad_id = $modalidad_id_data;
            } else {
                throw new Exception("No hay servicios disponibles");
            }
        }

        $nota_paciente = "Paquete: " . $paquete_tipo . " | Servicios: " . $paquete_servicios;
    } else {
        if (!$servicio_id || !$modalidad_id) {
            throw new Exception("Servicio o modalidad no especificados");
        }
        $nota_paciente = $observaciones;
    }

    // --- INICIO DE CORRECCIÓN ---
    // Asegurar que modalidad_id siempre tenga un valor para evitar errores en la consulta de empalmes.
    if (empty($modalidad_id)) {
        $modalidad_id = 1; // Asignar una modalidad por defecto (ej: Radiografía) si no se especifica.
    }
    // --- FIN DE CORRECCIÓN ---
    // ===============================
    //  VERIFICAR EMPALMES
    // ===============================
    $sqlEmpalme = "SELECT COUNT(*) as total FROM agenda_citas 
                   WHERE fecha = ? AND modalidad_id = ? 
                   AND ((hora_inicio < ? AND hora_fin > ?) 
                   OR (hora_inicio < ? AND hora_fin > ?) 
                   OR (hora_inicio >= ? AND hora_inicio < ?))";

    $stmtEmpalme = $conn->prepare($sqlEmpalme);
    $stmtEmpalme->bind_param("sissssss", 
        $fecha_cita, $modalidad_id, 
        $hora_fin, $hora_inicio,
        $hora_inicio, $hora_fin
    );
    $stmtEmpalme->execute();
    
    // --- INICIO DE CORRECCIÓN ---
    // Corregir el manejo de resultados para evitar "Commands out of sync".
    $stmtEmpalme->store_result(); // Almacenar resultado
    $stmtEmpalme->bind_result($total_empalme); // Vincular variable
    $stmtEmpalme->fetch(); // Obtener el valor
    $stmtEmpalme->close(); // ¡Cerrar el statement es fundamental!
    // --- FIN DE CORRECCIÓN ---

    if ($total_empalme > 0) {
        throw new Exception("Ya existe una cita en ese horario para la modalidad seleccionada");
    }

    // ===============================
    //  CREAR LA CITA
    // ===============================
    $stmt_cita = $conn->prepare("INSERT INTO agenda_citas 
        (fecha, hora_inicio, hora_fin, paciente_id, profesional_id, servicio_id, modalidad_id, estado_id, nota_paciente, nota_interna, tipo, url_identificacion, url_orden_medica)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $nota_interna = "Reserva web - Cliente: " . $nombre_completo . " | Email: " . $email;
    $tipo_cita = ($tipo_reserva === 'paquete') ? 'paquete' : 'individual';

    $stmt_cita->bind_param("sssiiiissssss",
        $fecha_cita,
        $hora_inicio,
        $hora_fin,
        $paciente_id,
        $profesional_id,
        $servicio_id,
        $modalidad_id,
        $estado_id,
        $nota_paciente,
        $nota_interna,
        $tipo_cita,
        $url_identificacion,
        $url_orden_medica
    );

    if (!$stmt_cita->execute()) {
        throw new Exception("Error al crear cita: " . $stmt_cita->error);
    }

    $cita_id = $conn->insert_id;

    // --- INICIO DE CORRECCIÓN ---
    // El error "Unknown column 'token'" indica que esta columna no existe en la tabla.
    // Se comenta esta sección para evitar el error. Si la funcionalidad del token es necesaria,
    // se deberá agregar la columna 'token' a la tabla 'agenda_citas'.
    // --- FIN DE CORRECCIÓN ---

    $conn->commit();

    // ===============================
    //  ENVIAR CORREO DE CONFIRMACIÓN
    // ===============================
    try {
        require_once(__DIR__ . '/includes/email_functions.php');
        if (send_appointment_confirmation_email($conn, $cita_id, $paciente_id, $email)) {
            error_log("Correo de confirmación enviado para la cita ID: " . $cita_id);
        } else {
            error_log("Falló el envío del correo de confirmación para la cita ID: " . $cita_id);
        }
    } catch (Exception $e) {
        error_log('Error al enviar correo: ' . $e->getMessage());
    }

    // ===============================
    //  SISTEMA DE PAGOS
    // ===============================
    try {
        require_once(__DIR__ . '/includes/GestorPagos.php');
        $gestorPagos = new GestorPagos($conn);
        $resultado_pago = $gestorPagos->crearPago($cita_id, 'simulador', 'tarjeta');

        if ($resultado_pago['success']) {
            $response = [
                "success" => true,
                "message" => "Reserva creada exitosamente",
                "requiere_pago" => true,
                "data" => [
                    "paciente_id" => $paciente_id,
                    "cita_id" => $cita_id,
                    "fecha" => $fecha_cita,
                    "hora" => $hora_inicio,
                    "tipo" => $tipo_reserva,
                    "pago" => $resultado_pago
                ]
            ];
        } else {
            $response = [
                "success" => true,
                "message" => "Reserva creada. Hubo un problema inicializando el pago.",
                "requiere_pago" => true,
                "pago_error" => $resultado_pago['error'] ?? 'Error desconocido',
                "data" => [
                    "paciente_id" => $paciente_id,
                    "cita_id" => $cita_id,
                    "fecha" => $fecha_cita,
                    "hora" => $hora_inicio,
                    "tipo" => $tipo_reserva
                ]
            ];
        }
    } catch (Exception $e) {
        error_log('Error inicializando pago: ' . $e->getMessage());
        $response = [
            "success" => true,
            "message" => "Reserva creada. El sistema de pagos no está disponible temporalmente.",
            "requiere_pago" => false,
            "data" => [
                "paciente_id" => $paciente_id,
                "cita_id" => $cita_id,
                "fecha" => $fecha_cita,
                "hora" => $hora_inicio,
                "tipo" => $tipo_reserva
            ]
        ];
    }

    error_log('Reserva creada - Paciente ID: ' . $paciente_id . ', Cita ID: ' . $cita_id);

} catch (Exception $e) {
    if (isset($conn) && $conn->ping()) {
        $conn->rollback();
    }
    error_log('Error en guardar_reserva_cliente.php: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    responder_json([
        "success" => false,
        // Si el mensaje de error es sobre una cita existente, muéstralo directamente.
        // De lo contrario, muestra un error genérico.
        "error" => ($e->getMessage() === "Ya existe una cita en ese horario para la modalidad seleccionada")
            ? $e->getMessage()
            : "Error interno del servidor: " . $e->getMessage()
    ]);
}

if (isset($conn) && $conn->ping()) {
    $conn->close();
}

responder_json($response);
?>
