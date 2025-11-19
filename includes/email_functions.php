<?php
// /Users/eliordonez/Downloads/Agenda/includes/email_functions.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Carga de Dependencias ---
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/debug_log.php';

// --- Carga de Configuración de Correo (Más Seguro) ---
// Intentar cargar el archivo de configuración, si existe.
$email_config_path = __DIR__ . '/email_config.php';
if (file_exists($email_config_path)) {
    require_once $email_config_path;
}

// Helper para obtener configuración SMTP de forma robusta:
function smtp_config($key, $default = null) {
    // Primero, constantes definidas por email_config.php
    if (defined($key)) return constant($key);
    // Luego, variables de entorno
    $env = getenv($key);
    if ($env !== false) return $env;
    return $default;
}

function send_appointment_confirmation_email($conn, $cita_id, $paciente_id, $recipient_email) {
    // --- CORRECCIÓN: Validar que el email del destinatario es válido ---
    log_message("[EMAIL] Iniciando envío para Cita ID: {$cita_id} a {$recipient_email}");
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        log_message("[EMAIL] FALLO: El formato del correo '{$recipient_email}' es inválido.");
        return false;
    }

    // Obtener todos los datos necesarios para el correo
    log_message("[EMAIL] Obteniendo datos de la cita de la BD...");
    $sql = "SELECT
                c.fecha,
                c.hora_inicio,
                c.hora_fin,
                c.nota_paciente,
                c.token,
                p.nombre AS nombre_paciente,
                p.apellido AS apellido_paciente,
                s.nombre AS nombre_servicio,
                s.descripcion AS descripcion_servicio,
                m.nombre AS nombre_modalidad
            FROM agenda_citas c
            JOIN portal_pacientes p ON c.paciente_id = p.id
            LEFT JOIN portal_servicios s ON c.servicio_id = s.id
            LEFT JOIN agenda_modalidades m ON c.modalidad_id = m.id
            WHERE c.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cita_id);
    if (!$stmt->execute()) {
        log_message("[EMAIL] FALLO: Error al ejecutar la consulta para obtener datos de la cita: " . $stmt->error);
        return false;
    }

    // --- CORRECCIÓN: Reemplazar get_result() para compatibilidad con servidores sin mysqlnd ---
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        log_message("[EMAIL] FALLO: No se encontró la cita con ID: " . $cita_id);
        return false;
    }

    // Vincular los resultados a variables
    $stmt->bind_result(
        $fecha, $hora_inicio, $hora_fin, $nota_paciente, $token,
        $nombre_paciente, $apellido_paciente, $nombre_servicio,
        $descripcion_servicio, $nombre_modalidad
    );
    $stmt->fetch();

    // Crear el array de datos manualmente
    $data = [
        'fecha' => $fecha, 'hora_inicio' => $hora_inicio, 'hora_fin' => $hora_fin,
        'nota_paciente' => $nota_paciente, 'token' => $token, 'nombre_paciente' => $nombre_paciente,
        'apellido_paciente' => $apellido_paciente, 'nombre_servicio' => $nombre_servicio,
        'descripcion_servicio' => $descripcion_servicio, 'nombre_modalidad' => $nombre_modalidad
    ];
    $stmt->close();

    log_message("[EMAIL] Datos de la cita obtenidos correctamente. Nombre: " . $data['nombre_paciente']);
    $nombre_completo_paciente = trim($data['nombre_paciente'] . ' ' . $data['apellido_paciente']);

    // Leer la plantilla de correo
    // --- CORRECCIÓN: Ruta de plantilla más robusta ---
    $template_path = dirname(__DIR__) . '/email_template_cita.html';
    if (!file_exists($template_path)) {
        log_message("[EMAIL] FALLO: No se encuentra la plantilla de email en la ruta: " . $template_path);
        return false;
    }
    $template = file_get_contents($template_path);

    // Reemplazar los placeholders
    // --- CORRECCIÓN: Construcción de URL más segura ---
    // Es más seguro usar una URL base fija o configurable para evitar problemas con $_SERVER
    // en diferentes entornos o al ejecutar scripts en segundo plano.
    // Asegúrate de que esta URL base sea la correcta para tu despliegue.
    $base_url = 'https://ha.angelescuauhtemoc.com/Agenda'; 

    $link_modificar = $base_url . '/modificar_cita.php?id=' . $cita_id; // Usar ID de cita
    $link_cancelar = $base_url . '/eliminar_cita_cliente.php?id=' . $cita_id; // Usar ID de cita

    $replacements = [
        '{modalidad}' => htmlspecialchars($data['nombre_modalidad']),
        '{fecha}' => htmlspecialchars($data['fecha']),
        '{nombre_paciente}' => htmlspecialchars($nombre_completo_paciente),
        '{servicio}' => htmlspecialchars($data['nombre_servicio']),
        '{hora_inicio}' => htmlspecialchars(substr($data['hora_inicio'], 0, 5)),
        '{hora_fin}' => htmlspecialchars(substr($data['hora_fin'], 0, 5)),
        '{direccion_hospital}' => 'Av. Cuauhtémoc 95, Col. Roma Norte, Cuauhtémoc, 06700 Ciudad de México, CDMX',
        '{google_maps_link}' => 'https://maps.app.goo.gl/u2r3XfnB92fPDRLz7',
        '{link_modificar_cita}' => $link_modificar,
        '{link_cancelar_cita}' => $link_cancelar,
        '{notas_paciente}' => nl2br(htmlspecialchars($data['nota_paciente'])),
        '{descripcion_servicio}' => nl2br(htmlspecialchars($data['descripcion_servicio'])),
        '{link_tienda_online}' => 'https://angelescuauhtemoc.com/servicios-y-especialidades/',
    ];

    $email_body = str_replace(array_keys($replacements), array_values($replacements), $template);

    // Instanciar PHPMailer
    $mail = new PHPMailer(true);

    try {
        log_message("[EMAIL] Configurando servidor SMTP (Host: " . smtp_config('SMTP_HOST', 'smtp.gmail.com') . ", Usuario: " . smtp_config('SMTP_USERNAME', 'eliordo625@gmail.com') . ")");
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host       = smtp_config('SMTP_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = smtp_config('SMTP_USERNAME', 'eliordo625@gmail.com');
        $mail->Password   = smtp_config('SMTP_PASSWORD', 'ctbh gbtt pfek elen');
        $secure = smtp_config('SMTP_SECURE', PHPMailer::ENCRYPTION_SMTPS);
        $mail->SMTPSecure = $secure;
        $mail->Port       = intval(smtp_config('SMTP_PORT', 465));

        // Remitente y destinatarios
        $from_email = smtp_config('SMTP_FROM_EMAIL', 'eliordo625@gmail.com');
        $from_name = smtp_config('SMTP_FROM_NAME', 'Hospital Angeles Cuauhtémoc');
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($recipient_email, $nombre_completo_paciente);

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = "Confirmación de Cita: " . $data['nombre_modalidad'] . " en Hospital Angeles Cuauhtémoc el " . $data['fecha'];
        $mail->Body    = $email_body;
        $mail->AltBody = 'Su cita ha sido confirmada. Por favor, revise los detalles en un cliente de correo compatible con HTML.';
        $mail->CharSet = 'UTF-8';

        $mail->send();
        log_message("[EMAIL] ÉXITO: Correo de confirmación enviado a: " . $recipient_email);
        return true;
    } catch (Exception $e) {
        // --- MEJORA: Propagar la excepción para un mejor manejo de errores ---
        // Registrar el error, pero NO lanzar excepción para que la creación de la cita no falle.
        log_message("[EMAIL] FALLO CRÍTICO: Error de PHPMailer al enviar el correo: {$mail->ErrorInfo}");
        log_message("[EMAIL] Detalle de excepción: " . $e->getMessage());
        return false;
    }
}

// Intentionally omit closing PHP tag to avoid accidental trailing whitespace/output