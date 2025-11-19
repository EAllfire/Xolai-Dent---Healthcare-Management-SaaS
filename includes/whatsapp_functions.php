<?php
// whatsapp_functions.php - ruta: Agenda/agenda/includes/whatsapp_functions.php

// Cargar configuración y utilidades (usar require_once)
require_once __DIR__ . '/whatsapp_config.php';
require_once __DIR__ . '/debug_log.php';

// Proteger definición de enviar_whatsapp_template
if (!function_exists('enviar_whatsapp_template')) {
    function enviar_whatsapp_template($to, $template, $params = [])
    {
        global $WHATSAPP_CONFIG;

        log_message("[WPP] Preparando envío a $to");

        if (empty($WHATSAPP_CONFIG) || !$WHATSAPP_CONFIG['enabled']) {
            log_message("[WPP] ERROR: WHATSAPP DESHABILITADO o configuración ausente");
            return false;
        }
        if (empty($WHATSAPP_CONFIG['access_token'])) {
            log_message("[WPP] ERROR: access_token vacío");
            return false;
        }
        if (empty($WHATSAPP_CONFIG['phone_number_id'])) {
            log_message("[WPP] ERROR: phone_number_id vacío");
            return false;
        }

        $url = "https://graph.facebook.com/" . $WHATSAPP_CONFIG["api_version"]
             . "/" . $WHATSAPP_CONFIG["phone_number_id"] . "/messages";

        $data = [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "template",
            "template" => [
                "name" => $template,
                "language" => ["code" => $WHATSAPP_CONFIG["language_code"]],
                "components" => [
                    [
                        "type" => "body",
                        "parameters" => array_map(function($p) {
                            return ["type" => "text", "text" => $p];
                        }, $params)
                    ]
                ]
            ]
        ];

        $payload = json_encode($data);
        log_message("[WPP] Payload: " . $payload);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $WHATSAPP_CONFIG["access_token"],
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            log_message("[WPP] CURL ERROR: " . $error);
            return false;
        }

        log_message("[WPP] RESPUESTA: " . $response);
        return $response;
    }
}

// Proteger definición de enviarWhatsAppSilencioso
if (!function_exists('enviarWhatsAppSilencioso')) {
    function enviarWhatsAppSilencioso($telefono, $nombre, $modalidad, $fecha, $hora, $nota)
    {
        log_message("[WPP] Iniciando enviarWhatsAppSilencioso() para $telefono");

        $template = $GLOBALS['WHATSAPP_CONFIG']['template_name'] ?? 'citaagendada';

        $params = [
            $nombre,
            $modalidad,
            $fecha,
            $hora,
            $nota ?: "Sin notas"
        ];

        return enviar_whatsapp_template($telefono, $template, $params);
    }
}
