<?php
/**
 * debug_helper.php
 *
 * Un sistema simple para mostrar los logs de error en pantalla durante el desarrollo.
 *
 * CÓMO USARLO:
 * 1. Define('DEBUG_MODE', true); al inicio de tu script principal (ej. guardar_cita.php).
 * 2. Incluye este archivo: require_once 'includes/debug_helper.php';
 * 3. Usa la función log_message('Tu mensaje de error') en lugar de error_log().
 * 4. Al final de tu script, llama a print_debug_buffer(); para mostrar los logs.
 */

// Almacén global para los mensajes de log
$GLOBALS['debug_buffer'] = [];

/**
 * Registra un mensaje. Si DEBUG_MODE está activado, lo guarda en un buffer para mostrarlo en pantalla.
 * Si no, usa el error_log estándar del servidor.
 *
 * @param string $message El mensaje a registrar.
 */
function log_message($message) {
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        $GLOBALS['debug_buffer'][] = "[" . date("Y-m-d H:i:s") . "] " . $message;
    } else {
        error_log($message);
    }
}

/**
 * Imprime todos los mensajes de log acumulados en un formato legible.
 * Debe llamarse al final del script.
 */
function print_debug_buffer() {
    if (defined('DEBUG_MODE') && DEBUG_MODE === true && !empty($GLOBALS['debug_buffer'])) {
        echo "\n\n<pre style='background-color: #1a1a1a; color: #00ff00; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 14px; text-align: left; white-space: pre-wrap; word-wrap: break-word;'>";
        echo "--- DEBUG LOGS ---\n";
        echo htmlspecialchars(implode("\n", $GLOBALS['debug_buffer']));
        echo "\n--- END OF LOGS ---";
        echo "</pre>";
    }
}