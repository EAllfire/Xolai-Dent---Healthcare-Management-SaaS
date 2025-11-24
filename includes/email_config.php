<?php
// includes/email_config.php

// --- CONFIGURACIÓN DE CORREO ELECTRÓNICO (SMTP Gmail) ---
// Usa una contraseña de aplicación generada desde:
// https://myaccount.google.com/apppasswords

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'eliordo625@gmail.com'); 
define('SMTP_PASSWORD', 'ctbhgbttpfekelen');  // ← SIN ESPACIOS
define('SMTP_PORT', 465); // SSL
define('SMTP_SECURE', 'ssl'); // Gmail SSL

define('SMTP_FROM_EMAIL', 'eliordo625@gmail.com');
define('SMTP_FROM_NAME', 'Hospital Angeles Cuauhtémoc');
