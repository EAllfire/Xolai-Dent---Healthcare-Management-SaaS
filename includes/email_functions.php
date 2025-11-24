<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

require_once __DIR__ . '/email_log.php';
require_once __DIR__ . '/email_config.php';

/**
 * Enviar email usando plantilla HTML
 */
function enviarCorreoCita($destinatario, $asunto, $variables)
{
    log_email("[EMAIL] Iniciando envío a $destinatario");

    $template_path = __DIR__ . '/../email_template_cita.html';

    if (!file_exists($template_path)) {
        log_email("[EMAIL] ERROR: No se encontró plantilla $template_path");
        return false;
    }

    // Cargar plantilla
    $html = file_get_contents($template_path);

    // Reemplazar variables
    foreach ($variables as $key => $value) {
        $html = str_replace("{" . $key . "}", $value, $html);
    }

    $mail = new PHPMailer(true);

    try {
        // --- CONFIG SMTP ---
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;   // ssl
        $mail->Port       = SMTP_PORT;     // 465

        $mail->CharSet = "UTF-8";

        // Remitente y destino
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($destinatario);

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $html;

        $mail->send();

        log_email("[EMAIL] Enviado correctamente a $destinatario");

        return true;

    } catch (Exception $e) {
        log_email("[EMAIL] ERROR SMTP: " . $mail->ErrorInfo);
        return false;
    }
}
