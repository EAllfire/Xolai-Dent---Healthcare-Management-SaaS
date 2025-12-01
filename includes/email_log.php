<?php
// email_log.php – genera logs detallados del correo

$EMAIL_LOG_DIR = __DIR__ . "/correo_logs/";

if (!file_exists($EMAIL_LOG_DIR)) {
    mkdir($EMAIL_LOG_DIR, 0775, true);
}

$EMAIL_LOG_FILE = $EMAIL_LOG_DIR . "correo_log.txt";

function log_email($mensaje) {
    global $EMAIL_LOG_FILE;
    $fecha = date("Y-m-d H:i:s");
    file_put_contents($EMAIL_LOG_FILE, "[$fecha] INFO: $mensaje\n", FILE_APPEND);
}

function log_email_error($mensaje) {
    global $EMAIL_LOG_FILE;
    $fecha = date("Y-m-d H:i:s");
    file_put_contents($EMAIL_LOG_FILE, "[$fecha] ERROR: $mensaje\n", FILE_APPEND);
}
