<?php
// email_functions.php – versión estable para GoDaddy con SMTP local y UTF-8

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";
require_once __DIR__ . "/PHPMailer/src/Exception.php";

require_once __DIR__ . "/email_log.php";
require_once __DIR__ . "/email_config.php";

function enviarCorreoCita($emailDestino, $asunto, $vars)
{
    log_email("Iniciando envío de correo a $emailDestino");

    try {
        $mail = new PHPMailer(true);

        // ===== SMTP LOCAL (Exim/Postfix del servidor GoDaddy) =====
        $mail->isSMTP();
        $mail->Host       = 'localhost'; // envío directo desde el servidor
        $mail->Port       = 25;          // puerto estándar de envío local
        $mail->SMTPAuth   = false;       // no requiere autenticación
        $mail->SMTPSecure = false;       // sin TLS
        $mail->SMTPAutoTLS = false;      // no forzar TLS

        // ===== UTF-8 =====
        $mail->CharSet  = "UTF-8";
        $mail->Encoding = "base64";

        // ===== NO DEBUG EN RESPUESTA HTTP =====
        $mail->SMTPDebug  = 0;
        $mail->Debugoutput = function ($str, $level) {
            log_email("[SMTP DEBUG] $str");
        };

        // ===== REMITENTE =====
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        // DESTINATARIO
        $mail->addAddress($emailDestino);

        // ===== CONTENIDO =====
        $mail->isHTML(true);

        $mail->Subject = $asunto;

        $mail->Body = "
            <meta charset='UTF-8'>
            <h2>Confirmación de Cita</h2>

            <p><strong>Paciente:</strong> {$vars['nombre_paciente']}</p>
            <p><strong>Modalidad:</strong> {$vars['modalidad']}</p>
            <p><strong>Servicio:</strong> {$vars['servicio']}</p>
            <p><strong>Fecha:</strong> {$vars['fecha']}</p>
            <p><strong>Hora:</strong> {$vars['hora_inicio']} – {$vars['hora_fin']}</p>
            <p><strong>Notas:</strong> {$vars['notas_paciente']}</p>

            <br><small>Este es un correo automático, por favor no responder.</small>
        ";

        $mail->AltBody = "Confirmación de cita para {$vars['nombre_paciente']}";

        log_email("Intentando enviar correo con asunto '$asunto'...");

        // ===== ENVIAR =====
        if (!$mail->send()) {
            log_email_error("PHPMailer->send() devolvió FALSE");
            log_email_error("Detalle: " . $mail->ErrorInfo);
            return ["success" => false, "error" => $mail->ErrorInfo];
        }

        log_email("Correo enviado correctamente a $emailDestino");
        return ["success" => true];

    } catch (Exception $e) {
        log_email_error("Excepción PHPMailer: " . $e->getMessage());
        return ["success" => false, "error" => $e->getMessage()];
    }
}
