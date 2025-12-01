<?php
// notificaciones.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/debug_log.php';
require_once __DIR__ . '/includes/whatsapp_functions.php';
require_once __DIR__ . '/includes/email_functions.php';

// obtener id de cita de argumento
$id_cita = $argv[1] ?? null;
if (!$id_cita) exit;

// obtener datos de la cita
$sql = "SELECT c.fecha, c.hora_inicio, c.hora_fin, c.nota_interna, c.nota_paciente,
        p.nombre, p.apellido, p.correo, p.telefono,
        m.nombre AS modalidad, s.nombre AS servicio
        FROM agenda_citas c
        JOIN portal_pacientes p ON c.paciente_id = p.id
        LEFT JOIN agenda_modalidades m ON c.modalidad_id = m.id
        LEFT JOIN portal_servicios s ON c.servicio_id = s.id
        WHERE c.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_cita);
$stmt->execute();
$res = $stmt->get_result();
$cita = $res->fetch_assoc();
$stmt->close();

if (!$cita) {
    log_message("[NOTIFICACIONES] No se encontró la cita $id_cita");
    exit;
}

// normalizar teléfono
$telefono = preg_replace('/\D/', '', $cita['telefono']);
if (strlen($telefono) === 10) $telefono = '52' . $telefono;
$nombre_paciente = $cita['nombre'] . ' ' . $cita['apellido'];

// Enviar WhatsApp
try {
    log_message("[NOTIFICACIONES] Enviando WPP a $telefono para cita $id_cita");
    $wpp_res = enviarWhatsAppSilencioso(
        $telefono,
        $nombre_paciente,
        $cita['modalidad'],
        $cita['fecha'],
        $cita['hora_inicio'],
        $cita['nota_paciente']
    );
    log_message("[NOTIFICACIONES] Resultado WPP: " . json_encode($wpp_res));
} catch (Throwable $e) {
    log_message("[NOTIFICACIONES] Excepción WPP: " . $e->getMessage());
}

// Enviar correo

$email = $cita['correo'];
if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $emailVars = [
        "nombre_paciente"     => $nombre_paciente,
        "modalidad"           => $cita['modalidad'],
        "servicio"            => $cita['servicio'],
        "fecha"               => $cita['fecha'],
        "hora_inicio"         => substr($cita['hora_inicio'], 0, 5),
        "hora_fin"            => substr($cita['hora_fin'], 0, 5),
        "direccion_hospital"  => "Av. Tecnológico #123, Cuauhtémoc, Chihuahua",
        "google_maps_link"    => "https://maps.app.goo.gl/xxxxxx",
        "link_modificar_cita" => "https://ha.angelescuauhtemoc.com/Agenda/agenda/modificar.php?id=$id_cita",
        "link_cancelar_cita"  => "https://ha.angelescuauhtemoc.com/Agenda/agenda/cancelar.php?id=$id_cita",
        "notas_paciente"      => $cita['nota_paciente'] ?: "Ninguna",
        "descripcion_servicio"=> $cita['nota_interna'] ?: "Sin descripción adicional.",
        "link_tienda_online"  => "https://ha.angelescuauhtemoc.com"
    ];

    $resultadoEmail = enviarCorreoCita(
        $email,
        "Confirmacion de Cita Hospital Angeles Cuauhtemoc",
        $emailVars
    );
    log_email("[NOTIFICACIONES] Resultado EMAIL: " . json_encode($resultadoEmail));
} else {
    log_email("[NOTIFICACIONES] Correo vacío o inválido ($email)");
}
