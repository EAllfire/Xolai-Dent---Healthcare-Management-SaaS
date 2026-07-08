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
    function enviarWhatsAppSilencioso($telefono, $nombre, $modalidad, $fecha, $hora, $nota, $url_confirmar, $url_reprogramar, $url_cancelar)
    {
        log_message("[WPP] Iniciando enviarWhatsAppSilencioso() para $telefono");

        $template = $GLOBALS['WHATSAPP_CONFIG']['template_name'] ?? 'citaagendada';

        $params = [
            $nombre,
            $modalidad,
            formatearFechaEnEspanol($fecha), // <--- FECHA FORMATEADA
            date('h:i a', strtotime($hora)),   // <--- HORA FORMATEADA (ej: 02:30 pm)
            $nota ?: "Sin notas",
            $url_confirmar,
            $url_reprogramar,
            $url_cancelar
        ];

        return enviar_whatsapp_template($telefono, $template, $params);
    }
}

/**
 * Valida los datos comunes para enviar un mensaje de WhatsApp.
 *
 * @param string $telefono Número de teléfono.
 * @param mixed ...$otros_datos Otros datos a validar que no estén vacíos.
 * @return array ['success' => bool, 'message' => string, 'telefono_limpio' => string]
 */
if (!function_exists('validarDatosWhatsApp')) {
    function validarDatosWhatsApp($telefono, ...$otros_datos) {
        // 1. Validar teléfono
        if (empty($telefono)) {
            return ['success' => false, 'message' => 'El número de teléfono está vacío.'];
        }
        $telefono_limpio = preg_replace('/\D/', '', $telefono);
        if (strlen($telefono_limpio) < 10) {
            return ['success' => false, 'message' => 'El número de teléfono es inválido.'];
        }
        if (strlen($telefono_limpio) === 10) {
            $telefono_limpio = '52' . $telefono_limpio;
        }

        // 2. Validar que otros datos no estén vacíos
        foreach ($otros_datos as $i => $dato) {
            if (empty($dato)) {
                return ['success' => false, 'message' => "El parámetro #" . ($i + 2) . " está vacío."];
            }
        }

        return ['success' => true, 'message' => 'Datos válidos.', 'telefono_limpio' => $telefono_limpio];
    }
}

/**
 * Función genérica para enviar un mensaje de WhatsApp usando una plantilla.
 *
 * @param string $telefono_destinatario Número de teléfono limpio (con código de país).
 * @param string $nombre_plantilla Nombre de la plantilla a usar.
 * @param array $variables Array de strings con las variables para el cuerpo de la plantilla.
 * @return array ['success' => bool, 'message' => string, 'response' => array]
 */
if (!function_exists('enviarMensajeWhatsApp')) {
    function enviarMensajeWhatsApp($telefono_destinatario, $nombre_plantilla, $variables = []) {
        global $WHATSAPP_CONFIG;

        if (empty($WHATSAPP_CONFIG) || !$WHATSAPP_CONFIG['enabled']) {
            log_message("[WPP] Sistema de WhatsApp deshabilitado.");
            return ['success' => false, 'message' => 'Sistema de WhatsApp deshabilitado.'];
        }

        $url = "https://graph.facebook.com/" . $WHATSAPP_CONFIG["api_version"] . "/" . $WHATSAPP_CONFIG["phone_number_id"] . "/messages";

        $parameters = [];
        foreach ($variables as $variable) {
            $parameters[] = ["type" => "text", "text" => (string)$variable];
        }

        $data = [
            "messaging_product" => "whatsapp",
            "to" => $telefono_destinatario,
            "type" => "template",
            "template" => [
                "name" => $nombre_plantilla,
                "language" => ["code" => $WHATSAPP_CONFIG["language_code"]],
                "components" => [
                    ["type" => "body", "parameters" => $parameters]
                ]
            ]
        ];

        $payload = json_encode($data);
        log_message("[WPP] Enviando a $url con payload: $payload");

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $WHATSAPP_CONFIG["access_token"], "Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response_str = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            log_message("[WPP] Error de cURL: " . $error);
            return ['success' => false, 'message' => 'Error de cURL: ' . $error];
        }

        $response_data = json_decode($response_str, true);

        if ($http_code >= 200 && $http_code < 300) {
            return ['success' => true, 'message' => 'Mensaje aceptado por Meta.', 'response' => $response_data];
        } else {
            return ['success' => false, 'message' => 'Error de la API de Meta: ' . ($response_data['error']['message'] ?? $response_str), 'response' => $response_data];
        }
    }
}

/**
 * Formatea una fecha en español.
 * @param string $fecha_str Fecha en formato YYYY-MM-DD.
 * @return string Fecha formateada como "17 de noviembre de 2025".
 */
if (!function_exists('formatearFechaEnEspanol')) {
    function formatearFechaEnEspanol($fecha_str) {
        $timestamp = strtotime($fecha_str);
        if ($timestamp === false) return $fecha_str;

        $meses = [
            'January' => 'enero', 'February' => 'febrero', 'March' => 'marzo',
            'April' => 'abril', 'May' => 'mayo', 'June' => 'junio',
            'July' => 'julio', 'August' => 'agosto', 'September' => 'septiembre',
            'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre'
        ];

        $mes_ingles = date('F', $timestamp);
        $mes_espanol = $meses[$mes_ingles] ?? $mes_ingles;

        return date('d', $timestamp) . ' de ' . $mes_espanol . ' de ' . date('Y', $timestamp);
    }
}
/**
 * Envía un recordatorio de cita 24 horas antes.
 * Usa la plantilla 'recordatorio_cita'.
 *
 * @param string $telefono_destinatario Número de teléfono con código de país (ej. 525512345678).
 * @param string $nombre_paciente Nombre del paciente.
 * @param string $modalidad Nombre de la modalidad del estudio.
 * @param string $fecha Fecha de la cita (YYYY-MM-DD).
 * @param string $hora Hora de la cita (HH:MM:SS).
 * @param string $notas Notas adicionales para el paciente.
 * @return array Resultado del envío.
 */
function enviarWhatsAppRecordatorio(
    $telefono_destinatario,
    $nombre_paciente,
    $modalidad,
    $fecha,
    $hora,
    $notas
) {
    // 1. Validar datos de entrada
    $validacion = validarDatosWhatsApp($telefono_destinatario, $nombre_paciente, $modalidad, $fecha, $hora, $notas);
    if (!$validacion['success']) {
        return $validacion; // Devuelve el error de validación
    }
    $telefono_limpio = $validacion['telefono_limpio'];

    // 2. Preparar las 5 variables para la plantilla 'recordatorio_cita'
    $variables = [
        $nombre_paciente,
        $modalidad,
        formatearFechaEnEspanol($fecha), // Formato "17 de noviembre de 2025"
        date('h:i a', strtotime($hora)), // Formato 02:30 pm
        $notas ?: 'Sin indicaciones adicionales.' // Variable 5
    ];

    $nombre_plantilla_recordatorio = $GLOBALS['WHATSAPP_CONFIG']['template_recordatorio'] ?? 'recordatoriocita';

    // 3. Enviar el mensaje usando la función genérica
    $resultado = enviarMensajeWhatsApp(
        $telefono_limpio,
        $nombre_plantilla_recordatorio, // Usar la plantilla desde la configuración
        $variables
    );

    // 4. Registrar y devolver el resultado
    if ($resultado['success']) {
        log_message("[WPP RECORDATORIO] Recordatorio enviado a $telefono_limpio. Respuesta: " . json_encode($resultado['response']));
        return [
            'success' => true,
            'message' => 'Recordatorio enviado exitosamente.',
            'response' => $resultado['response']
        ];
    } else {
        log_message("[WPP RECORDATORIO] ERROR al enviar recordatorio a $telefono_limpio. Razón: " . $resultado['message']);
        return [
            'success' => false,
            'message' => $resultado['message']
        ];
    }
}