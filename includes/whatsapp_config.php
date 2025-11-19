<?php
/**
 * Configuración de WhatsApp Cloud API
 */

// ----------------------
// CARGAR env
// ----------------------
$envPath = __DIR__ . '/../env';

if (file_exists($envPath)) {
    $envData = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if ($envData) {
        foreach ($envData as $key => $value) {
            putenv("$key=$value");
        }
    }
}

// ----------------------
// CONFIG GLOBAL
// ----------------------
$WHATSAPP_CONFIG = [
    "access_token"      => getenv("WHATSAPP_ACCESS_TOKEN") ?: "",
    "phone_number_id"   => getenv("WHATSAPP_PHONE_NUMBER_ID") ?: "",
    "business_account_id" => getenv("WHATSAPP_BUSINESS_ACCOUNT_ID") ?: "",
    "template_name"     => "citaagendada",
    "language_code"     => "es_MX",
    "api_version"       => "v18.0",

    // 🔥 FORZAMOS HABILITACIÓN PARA PRUEBAS
    "enabled"           => true
];

?>
