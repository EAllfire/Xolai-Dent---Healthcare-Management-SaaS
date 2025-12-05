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
$fecha = $input["fecha"] ?? null;
$hora_inicio = $input["hora_inicio"] ?? null;
$hora_fin = $input["hora_fin"] ?? null;
$paciente_id = (int) ($input["paciente_id"] ?? null); // Convierto a entero
$servicio_id = (int) ($input["servicio_id"] ?? null); // Convierto a entero
$modalidad_id = (int) ($input["modalidad_id"] ?? null); // Convierto a entero
$estado_id = (int) ($input["estado_id"] ?? null); // Convierto a entero
$tipo = $input["tipo"] ?? "normal";
$nota_interna = $input["nota_interna"] ?? null;
$nota_paciente = $input["nota_paciente"] ?? null;

// campos NULL
$profesional_id = null;
$token = null;
$url_identificacion = null;
$url_orden_medica = null;

// validación
if (!$fecha || !$hora_inicio || !$hora_fin || !$paciente_id || !$modalidad_id || !$estado_id) {
    echo json_encode(["success" => false, "error" => "Faltan datos obligatorios"]);
    exit;
}

// --- INICIO: VERIFICACIÓN DE DISPONIBILIDAD ---
// Se verifica que no haya ninguna otra cita (que no esté cancelada, estado_id != 7)
// que se solape con el horario solicitado en la misma modalidad.
$stmt_empalme = $conn->prepare(
    "SELECT COUNT(*) as total FROM agenda_citas 
     WHERE fecha = ? AND modalidad_id = ? AND estado_id != 7 AND id != ? AND hora_inicio < ? AND hora_fin > ?"
);

if ($stmt_empalme) {
    $cita_id_a_ignorar = 0; // Para nuevas citas, no se ignora ninguna.
    $stmt_empalme->bind_param("siiss", $fecha, $modalidad_id, $cita_id_a_ignorar, $hora_fin, $hora_inicio);
    $stmt_empalme->execute();
    $stmt_empalme->bind_result($total_empalme);
    $stmt_empalme->fetch();
    $stmt_empalme->close();

    if ($total_empalme > 0) {
        echo json_encode([
            "success" => false,
            "error" => "Conflicto de horario. Ya existe una cita o un bloqueo en ese espacio para la modalidad seleccionada."
        ]);
        exit;
    }
} else {
    // No se pudo preparar la consulta, por seguridad, no se procede.
    echo json_encode(["success" => false, "error" => "Error al verificar la disponibilidad del horario."]);
    exit;
}
// --- FIN: VERIFICACIÓN DE DISPONIBILIDAD ---

// --- INICIO: Lógica de Edad para Notificación (MOVIMOS ESTE BLOQUE ANTES DEL INSERT) ---
// Obtener la fecha de nacimiento del paciente para calcular la edad
$fecha_nacimiento_paciente = null;
if ($paciente_id > 0) {
    $stmt_fn = $conn->prepare("SELECT fecha_nacimiento FROM portal_pacientes WHERE id = ?");
    $stmt_fn->bind_param("i", $paciente_id);
    $stmt_fn->execute();
    $stmt_fn->bind_result($fecha_nacimiento_paciente);
    $stmt_fn->fetch();
    $stmt_fn->close();

    if ($fecha_nacimiento_paciente) {
        try {
            $fecha_nac = new DateTime($fecha_nacimiento_paciente);
            $hoy = new DateTime();
            $edad = $hoy->diff($fecha_nac)->y;

            if ($edad < 18) {
                // Si es menor de edad, añadir la nota para el tutor
                $nota_paciente = ($nota_paciente ? $nota_paciente . " " : "") . "Favor de presentarse acompañado de su tutor legal.";
            }
        } catch (Exception $e) {
            log_message("[GUARDAR CITA] Error al calcular la edad para la cita: " . $e->getMessage());
        }
    }
}
// --- FIN: Lógica de Edad para Notificación ---

// preparar INSERT (ajusta tipos si tu DB los requiere)
$sql = "INSERT INTO agenda_citas (fecha, hora_inicio, hora_fin, paciente_id, profesional_id, servicio_id, modalidad_id, estado_id, tipo, token, nota_interna, nota_paciente, url_identificacion, url_orden_medica) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
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
$res = $conn->query("SELECT nombre, apellido, correo, telefono FROM portal_pacientes WHERE id = " . (int)$paciente_id); // Convierto a entero
$paciente = $res ? $res->fetch_assoc() : null;
$nombre_paciente = $paciente['nombre'] . ' ' . $paciente['apellido'];
$email_paciente = $paciente['correo'] ?? '';
$telefono_paciente = $paciente['telefono'] ?? '';

// normalizar telefono para wpp: solo dígitos, agregar 52 si tiene 10
$telefono_paciente = preg_replace('/\D/', '', $telefono_paciente);
if (strlen($telefono_paciente) === 10) {
    $telefono_paciente = '52' . $telefono_paciente;
}

// obtener nombre de modalidad y servicio
$res2 = $conn->query("SELECT nombre FROM agenda_modalidades WHERE id = " . (int)$modalidad_id); // Convierto a entero
$modalidad = $res2 ? ($res2->fetch_assoc()['nombre'] ?? '') : '';

$res3 = $conn->query("SELECT nombre FROM portal_servicios WHERE id = " . (int)$servicio_id); // Convierto a entero
$servicio = $res3 ? ($res3->fetch_assoc()['nombre'] ?? '') : '';

// enviar whatsapp (silencioso)

try {
    log_message("[GUARDAR] Enviando WPP a $telefono_paciente para cita $id_cita");
    
    // Crear URLs para acciones del paciente
    $url_base = "https://ha.angelescuauhtemoc.com/Agenda/agenda/";
    $url_confirmar = $url_base . "confirmar_paciente.php?id=" . $id_cita;
    $url_reprogramar = $url_base . "reprogramar_paciente.php?id=" . $id_cita;
    $url_cancelar = $url_base . "cancelar_paciente.php?id=" . $id_cita;

    // llamada a la función del include; retorna la respuesta o false
    $wpp_res = enviarWhatsAppSilencioso(
        $telefono_paciente,
        $nombre_paciente,
        $modalidad,
        $fecha,
        $hora_inicio,
        $nota_paciente,
        $url_confirmar,
        $url_reprogramar,
        $url_cancelar
    );
    log_message("[GUARDAR] Resultado WPP: " . json_encode($wpp_res));
} catch (Throwable $e) {
    log_message("[GUARDAR] Excepción WPP: " . $e->getMessage());
}

// 🔵 **ENVÍO DE EMAIL CORRECTO – BLOQUE FINAL**

if (!empty($email_paciente) && filter_var($email_paciente, FILTER_VALIDATE_EMAIL)) {
    log_email("[GUARDAR] Enviando EMAIL a $email_paciente");
    $emailVars = [
        "nombre_paciente" => $nombre_paciente,
        "modalidad" => $modalidad,
        "servicio" => $servicio,
        "fecha" => $fecha,
        "hora_inicio" => substr($hora_inicio, 0, 5),
        "hora_fin" => substr($hora_fin, 0, 5),
        "notas_paciente" => $nota_paciente,
        "descripcion_servicio" => $nota_interna ?? ""
    ];

    $resultadoEmail = enviarCorreoCita(
        $email_paciente,
        "Confirmación de Cita Hospital Angeles Cuauhtemoc",
        $emailVars
    );
    log_email("[GUARDAR] Resultado EMAIL: " . json_encode($resultadoEmail));
} else {
    log_email("[GUARDAR] No se envía email: correo vacío o inválido ($email_paciente)");
}

// final
echo json_encode(["success" => true, "message" => "Cita guardada correctamente", "id" => $id_cita]);
exit;