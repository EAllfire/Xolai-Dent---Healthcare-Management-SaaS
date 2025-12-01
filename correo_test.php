<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {

    // ACTIVAR DEBUG DETALLADO
    $mail->SMTPDebug = 2;              // Muestra toda la conversación SMTP
    $mail->Debugoutput = 'html';       // Debug bonito en pantalla

    // CONFIGURACIÓN SMTP REAL
    $mail->isSMTP();
    $mail->Host       = 'p3plzcpnl506465.prod.phx3.secureserver.net';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'ha@ha.angelescuauhtemoc.com';
    $mail->Password   = 'HACangeles2025/';
    $mail->SMTPSecure = 'ssl';         // Para puerto 465
    $mail->Port       = 465;

    // REMITENTE
    $mail->setFrom('ha@ha.angelescuauhtemoc.com', 'TEST SMTP');

    // DESTINATARIO
    $mail->addAddress('eliordo625@gmail.com');

    // CONTENIDO
    $mail->isHTML(true);
    $mail->Subject = 'TEST SMTP - DEBUG';
    $mail->Body    = '<h3>Prueba con debug completo</h3>';

    // Enviar
    if ($mail->send()) {
        echo "<h2>✔ CORREO ENVIADO</h2>";
    } else {
        echo "<h2>✖ ERROR: " . $mail->ErrorInfo . "</h2>";
    }

} catch (Exception $e) {
    echo "<h2>✖ EXCEPCIÓN: " . $mail->ErrorInfo . "</h2>";
}
?>
