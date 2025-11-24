<?php
// guardar_cita.php (ruta: Agenda/agenda/guardar_cita.php)
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// includes (usar require_once para evitar includes múltiples)
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/debug_log.php';
require_once __DIR__ . '/includes/whatsapp_functions.php';
require_once __DIR__ . "/includes/email_functions.php";

// require_once __DIR__ . '/includes/email_functions.php'; // opcional por ahora

// leer JSON
$input = json_decode(file_get_contents("php://input"), true);

// campos usados
$fecha          = $input["fecha"] ?? null;
$hora_inicio    = $input["hora_inicio"] ?? null;
$hora_fin       = $input["hora_fin"] ?? null;
$paciente_id    = $input["paciente_id"] ?? null;
$servicio_id    = $input["servicio_id"] ?? null;
$modalidad_id   = $input["modalidad_id"] ?? null;
$estado_id      = $input["estado_id"] ?? null;
$tipo           = $input["tipo"] ?? "normal";
$nota_interna   = $input["nota_interna"] ?? null;
$nota_paciente  = $input["nota_paciente"] ?? null;

// campos NULL
$profesional_id     = null;
$token              = null;
$url_identificacion = null;
$url_orden_medica   = null;

// validación
if (!$fecha || !$hora_inicio || !$hora_fin || !$paciente_id || !$modalidad_id || !$estado_id) {
    echo json_encode(["success" => false, "error" => "Faltan datos obligatorios"]);
    exit;
}

// preparar INSERT (ajusta tipos si tu DB los requiere)
$sql = "INSERT INTO agenda_citas 
(fecha, hora_inicio, hora_fin, paciente_id, profesional_id,
 servicio_id, modalidad_id, estado_id, tipo, token,
 nota_interna, nota_paciente, url_identificacion, url_orden_medica)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    log_message("[GUARDAR] Error prepare: " . $conn->error);
    echo json_encode(["success" => false, "error" => $conn->error]);
    exit;
}

$stmt->bind_param(
    "sssiiisiisssss",
    $fecha,
    $hora_inicio,
    $hora_fin,
    $paciente_id,
    $profesional_id,
    $servicio_id,
    $modalidad_id,
    $estado_id,
    $tipo,
    $token,
    $nota_interna,
    $nota_paciente,
    $url_identificacion,
    $url_orden_medica
);

if (!$stmt->execute()) {
    log_message("[GUARDAR] Error execute: " . $stmt->error);
    echo json_encode(["success" => false, "error" => $stmt->error]);
    exit;
}

$id_cita = $stmt->insert_id;
$stmt->close();

// obtener datos del paciente (ajusta nombre tabla/columnas)
$res = $conn->query("SELECT nombre, apellido, correo, telefono FROM portal_pacientes WHERE id = " . intval($paciente_id));
$paciente = $res ? $res->fetch_assoc() : null;
$nombre_paciente   = $paciente['nombre'] . ' ' . $paciente['apellido'];
$email_paciente    = $paciente['correo'] ?? '';
$telefono_paciente = $paciente['telefono'] ?? '';

// normalizar telefono para wpp: solo dígitos, agregar 52 si tiene 10
$telefono_paciente = preg_replace('/\D/', '', $telefono_paciente);
if (strlen($telefono_paciente) === 10) $telefono_paciente = '52' . $telefono_paciente;

// obtener nombre de modalidad y servicio
$res2 = $conn->query("SELECT nombre FROM agenda_modalidades WHERE id = " . intval($modalidad_id));
$modalidad = $res2 ? ($res2->fetch_assoc()['nombre'] ?? '') : '';
$res3 = $conn->query("SELECT nombre FROM portal_servicios WHERE id = " . intval($servicio_id));
$servicio = $res3 ? ($res3->fetch_assoc()['nombre'] ?? '') : '';

// enviar whatsapp (silencioso)
try {
    log_message("[GUARDAR] Enviando WPP a $telefono_paciente para cita $id_cita");
    // llamada a la función del include; retorna la respuesta o false
    $wpp_res = enviarWhatsAppSilencioso(
        $telefono_paciente,
        $nombre_paciente,
        $modalidad,
        $fecha,
        $hora_inicio,
        $nota_paciente
    );
    log_message("[GUARDAR] Resultado WPP: " . json_encode($wpp_res));
} catch (Throwable $e) {
    log_message("[GUARDAR] Excepción WPP: " . $e->getMessage());
}


// 🔵 **ENVÍO DE EMAIL CORRECTO – BLOQUE FINAL**
// 🔵 **ENVÍO DE EMAIL CORRECTO – BLOQUE FINAL**
if (!empty($email_paciente) && filter_var($email_paciente, FILTER_VALIDATE_EMAIL)) {

    log_email("[GUARDAR] Enviando EMAIL a $email_paciente");

    $emailVars = [
        "nombre_paciente" => $nombre_paciente,
        "modalidad" => $modalidad,
        "servicio" => $servicio,
        "fecha" => $fecha,
        "hora_inicio" => substr($hora_inicio,0,5),
        "hora_fin" => substr($hora_fin,0,5),
        "notas_paciente" => $nota_paciente,
        "descripcion_servicio" => $nota_interna ?? ""
    ];

    $resultadoEmail = enviarCorreoCita(
        $email_paciente,
        "Confirmación de Cita – Hospital Angeles Cuauhtémoc",
        $emailVars
    );

    log_email("[GUARDAR] Resultado EMAIL: " . json_encode($resultadoEmail));
} else {
    log_email("[GUARDAR] No se envía email: correo vacío o inválido ($email_paciente)");
}


// final
echo json_encode(["success" => true, "message" => "Cita guardada correctamente", "id" => $id_cita]);
exit;
