<?php
require_once "email_config.php";
require_once "PHPMailer/src/PHPMailer.php";
require_once "PHPMailer/src/SMTP.php";
require_once "PHPMailer/src/Exception.php";

use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);

$mail->isSMTP();
$mail->Host = SMTP_HOST;
$mail->Port = SMTP_PORT;
$mail->SMTPAuth = SMTP_AUTH;
$mail->SMTPAutoTLS = false;
$mail->SMTPSecure = false;
$mail->Username = SMTP_USERNAME;
$mail->Password = SMTP_PASSWORD;

$mail->setFrom(SMTP_FROM_EMAIL, "Prueba SMTP");
$mail->addAddress("eliordo625@gmail.com");

$mail->Subject = "PRUEBA SMTP DIRECTA";
$mail->Body = "Probando SMTP directo desde GoDaddy.";

$mail->SMTPDebug = 2;

$mail->Debugoutput = function ($msg, $level) {
    echo "<br>DEBUG ($level): $msg";
};

try {
    $mail->send();
    echo "<br><br> **ENVÍO OK**";
} catch (Exception $e) {
    echo "<br><br>ERROR: " . $e->getMessage();
}
