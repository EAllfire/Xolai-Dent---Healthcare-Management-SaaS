<?php
// /Users/eliordonez/Downloads/Agenda/includes/email_functions.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Carga de Dependencias ---
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
// --- Carga de Configuración de Correo (Más Seguro) ---
require_once __DIR__ . '/email_config.php';

function send_appointment_confirmation_email($conn, $cita_id, $paciente_id, $recipient_email) {
    // --- CORRECCIÓN: Validar que el email del destinatario es válido ---
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        error_log("Intento de envío a un correo inválido: " . $recipient_email);
        return false;
    }

    // Obtener todos los datos necesarios para el correo
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
        error_log("Error al ejecutar la consulta para obtener datos de la cita: " . $stmt->error);
        return false;
    }
    $result = $stmt->get_result(); // Usar get_result() es más limpio si está disponible.
    if ($result->num_rows === 0) {
        error_log("No se encontró la cita con ID: " . $cita_id);
        return false;
    }
    $data = $result->fetch_assoc();
    $stmt->close();

    $nombre_completo_paciente = trim($data['nombre_paciente'] . ' ' . $data['apellido_paciente']);

    // Leer la plantilla de correo
    // --- CORRECCIÓN: Ruta de plantilla más robusta ---
    $template_path = dirname(__DIR__) . '/email_template_cita.html';
    if (!file_exists($template_path)) {
        error_log("No se encuentra la plantilla de email: " . $template_path);
        return false;
    }
    $template = file_get_contents($template_path);

    // Reemplazar los placeholders
    // --- CORRECCIÓN: Construcción de URL más segura ---
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $base_url = $scheme . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2); // Sube dos niveles desde /includes/
    $link_modificar = $base_url . '/modificar_cita.php?token=' . $data['token'];
    $link_cancelar = $base_url . '/eliminar_cita_cliente.php?token=' . $data['token'];

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
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;

        // Remitente y destinatarios
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($recipient_email, $nombre_completo_paciente);

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = "Confirmación de Cita: " . $data['nombre_modalidad'] . " en Hospital Angeles Cuauhtémoc el " . $data['fecha'];
        $mail->Body    = $email_body;
        $mail->AltBody = 'Su cita ha sido confirmada. Por favor, revise los detalles en un cliente de correo compatible con HTML.';
        $mail->CharSet = 'UTF-8';

        $mail->send();
        error_log("Correo de confirmación enviado a: " . $recipient_email);
        return true;
    } catch (Exception $e) {
        // --- MEJORA: Propagar la excepción para un mejor manejo de errores ---
        error_log("Error de PHPMailer al enviar el correo: {$mail->ErrorInfo}");
        throw new Exception("No se pudo enviar el correo de confirmación. Error: {$mail->ErrorInfo}");
    }
}
