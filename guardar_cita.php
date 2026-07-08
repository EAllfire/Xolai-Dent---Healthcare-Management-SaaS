<?php
// guardar_cita.php (ruta: /Applications/MAMP/htdocs/agenda/guardar_cita.php)
// Este archivo se ha copiado desde el subdirectorio "agenda" para que
// las llamadas relativas desde index.php funcionen correctamente.
// Asegúrate de mantenerlo sincronizado si se realizan cambios.
session_start();

date_default_timezone_set('America/Mexico_City');
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// includes (usar require_once para evitar includes múltiples)
try {
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/debug_log.php';
    require_once __DIR__ . "/includes/email_functions.php";
    require_once __DIR__ . "/includes/icloud_functions.php";

// require_once __DIR__ . '/includes/email_functions.php'; // opcional por ahora

// leer JSON
$input = json_decode(file_get_contents("php://input"), true);

// Obtener el ID del usuario de la sesión.
$usuario_id_real = $_SESSION['usuario_id'] ?? null;
$id_propietario = $_SESSION['id_padre'] ?? $usuario_id_real;
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';

// campos usados
$fecha = $input["fecha"] ?? null;
$hora_inicio = $input["hora_inicio"] ?? null;
$hora_fin = $input["hora_fin"] ?? null;
$paciente_id = (int) ($input["paciente_id"] ?? null); // Convierto a entero
$servicio_id = (int) ($input["servicio_id"] ?? null); // Convierto a entero
$modalidad_id_input = (int) ($input["modalidad_id"] ?? 0); 
$profesional_id_input = (int) ($input["profesional_id"] ?? 0); // Recibimos el profesional
$estado_id = (int) ($input["estado_id"] ?? null); // Convierto a entero
$tipo = $input["tipo"] ?? "normal";

// 🔹 CORRECCIÓN: Si es un bloqueo (paciente_id es 0 o vacío), asignar ID 1 para evitar error de BD
if (empty($paciente_id)) {
    $paciente_id = 1; 
}

$nota_interna = $input["nota_interna"] ?? null;
$nota_paciente = $input["nota_paciente"] ?? null;
$atencion_especial = (int) ($input["atencion_especial"] ?? 0); // Recibimos el nuevo campo

// 🔹 LÓGICA DE ASIGNACIÓN DIRECTA
// El ID que viene del calendario es el de la Modalidad.
$modalidad_id = $modalidad_id_input;

// Intentamos obtener el profesional_id asociado a esta modalidad (si existe)
$stmt_prof_check = $conn->prepare("SELECT usuario_id FROM agenda_modalidades WHERE id = ? LIMIT 1");
$stmt_prof_check->bind_param("i", $modalidad_id);
$stmt_prof_check->execute();
$stmt_prof_check->bind_result($found_prof_id);
$modality_has_owner = ($stmt_prof_check->fetch() && $found_prof_id > 0);
// Prioridad: 1. Dueño de la modalidad, 2. Profesional autenticado (dentista/medico), 3. Dueño de la clínica
$profesional_id = ($profesional_id_input > 0) ? $profesional_id_input : ($modality_has_owner ? $found_prof_id : (in_array($usuario_tipo, ['dentista', 'medico']) ? $usuario_id_real : $id_propietario));
$stmt_prof_check->close();

if (!$modalidad_id) $modalidad_id = 8;


$token = null;
$url_identificacion = null;
$url_orden_medica = null;

// validación
if (!$fecha || !$hora_inicio || !$hora_fin || !$paciente_id || !$estado_id || !$id_propietario) {
    echo json_encode(["success" => false, "error" => "Faltan datos obligatorios"]);
    exit;
}

// --- INICIO: VERIFICACIÓN DE DISPONIBILIDAD ---
// Se verifica que no haya ninguna otra cita (que no esté cancelada, estado_id != 7)
// que se solape con el horario solicitado en la misma modalidad.

// Permitir el empalme de citas solo para usuarios tipo 'dentista'
if ($usuario_tipo !== 'dentista') {

$stmt_empalme = $conn->prepare(
    "SELECT COUNT(*) as total FROM agenda_citas 
     WHERE fecha = ? AND (modalidad_id = ? OR profesional_id = ?) AND estado_id != 7 AND id != ? AND hora_inicio < ? AND hora_fin > ?"
);

if ($stmt_empalme) {
    $cita_id_a_ignorar = 0; // Para nuevas citas, no se ignora ninguna.
    $stmt_empalme->bind_param("siiiss", $fecha, $modalidad_id, $profesional_id, $cita_id_a_ignorar, $hora_fin, $hora_inicio);
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
$sql = "INSERT INTO agenda_citas (usuario_id, fecha, hora_inicio, hora_fin, paciente_id, profesional_id, servicio_id, modalidad_id, estado_id, tipo, nota_interna, nota_paciente, url_identificacion, url_orden_medica, atencion_especial) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    log_message("[GUARDAR] Error prepare: " . $conn->error);
    echo json_encode(["success" => false, "error" => $conn->error]);
    exit;
}

$stmt->bind_param(
    "isssiiiiisssssi",
    $id_propietario,
    $fecha,
    $hora_inicio,
    $hora_fin,
    $paciente_id,
    $profesional_id,
    $servicio_id,
    $modalidad_id,
    $estado_id,
    $tipo,
    $nota_interna,
    $nota_paciente,
    $url_identificacion, // Este campo no se está usando en el frontend, pero se mantiene para compatibilidad
    $url_orden_medica,   // Este campo no se está usando en el frontend, pero se mantiene para compatibilidad
    $atencion_especial   // Añadimos el nuevo parámetro
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
$row_p = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
$nombre_paciente = $row_p ? ($row_p['nombre'] . ' ' . ($row_p['apellido'] ?? '')) : 'Paciente';
$email_paciente = $row_p['correo'] ?? '';
$telefono_paciente = $row_p['telefono'] ?? '';

// normalizar telefono para wpp: solo dígitos, agregar 52 si tiene 10
$telefono_paciente = preg_replace('/\D/', '', $telefono_paciente);
if ($telefono_paciente && strlen($telefono_paciente) === 10) {
    $telefono_paciente = '52' . $telefono_paciente;
}

// obtener nombre de modalidad y servicio
$res2 = $conn->query("SELECT nombre FROM agenda_modalidades WHERE id = " . (int)$modalidad_id); // Convierto a entero
$row2 = ($res2 && $res2->num_rows > 0) ? $res2->fetch_assoc() : null;
$modalidad = $row2['nombre'] ?? '';

$res3 = $conn->query("SELECT nombre FROM portal_servicios WHERE id = " . (int)$servicio_id); // Convierto a entero
$row3 = ($res3 && $res3->num_rows > 0) ? $res3->fetch_assoc() : null;
$servicio = $row3['nombre'] ?? '';

/* 🔵 **ENVÍO DE EMAIL CORRECTO – BLOQUE FINAL**

// 🔹 Solo enviar Email si NO es el paciente genérico (ID 1)
if ($paciente_id !== 1 && !empty($email_paciente) && filter_var($email_paciente, FILTER_VALIDATE_EMAIL)) {
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

    // ... resto del bloque de envío de email permanece igual ...
    $resultadoEmail = enviarCorreoCita(
        $email_paciente,
        "Confirmación de Cita Hospital Angeles Cuauhtemoc",
        $emailVars
    );
    log_email("[GUARDAR] Resultado EMAIL: " . json_encode($resultadoEmail));
} else {
    log_email("[GUARDAR] No se envía email: correo vacío o inválido ($email_paciente)");
}
*/

// 🔹 Sincronización con Apple Calendar (La función verificará si el médico tiene sync activo)
syncCitaToAppleCalendar($conn, $id_cita);

// Final
echo json_encode(["success" => true, "message" => "Cita guardada correctamente", "id" => $id_cita]);

} catch (Throwable $e) {
    http_response_code(500);
    error_log("Error en guardar_cita.php: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => $e->getMessage(), "detail" => "Error en línea " . $e->getLine()]);
}
exit;
