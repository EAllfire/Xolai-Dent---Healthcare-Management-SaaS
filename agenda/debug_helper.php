<?php
// debug_helper.php

// Initialize debug buffer
if (!isset($GLOBALS['debug_buffer'])) {
    $GLOBALS['debug_buffer'] = [];
}

/**
 * Adds a message to the global debug buffer.
 * @param string $message The debug message to log.
 */
function log_message($message) {
    // You could add a timestamp or other context here if needed
    $GLOBALS['debug_buffer'][] = $message;
}

/**
 * Returns all buffered debug logs as a single formatted string.
 * This function does NOT print anything.
 * @return string The formatted log content.
 */
function get_debug_buffer_as_string() {
    if (empty($GLOBALS['debug_buffer'])) {
        return '';
    }

    $output = "--- DEBUG LOGS ---\n";
    foreach ($GLOBALS['debug_buffer'] as $log) {
        $output .= htmlspecialchars($log) . "\n";
    }
    $output .= "--- END OF LOGS ---";
    
    return $output;
}

/**
 * Clears the debug buffer.
 */
function clear_debug_buffer() {
    $GLOBALS['debug_buffer'] = [];
}

// Intentionally omit closing PHP tag to prevent accidental whitespace output

/**
 * Writes the current debug buffer to a physical log file.
 * @param string $file Optional: Log filename
 */
function save_debug_to_file($file = 'debug_log.txt') {
    $log_content = get_debug_buffer_as_string();

    // Get absolute path to the directory where this file lives
    $path = __DIR__ . '/' . $file;

    // Append logs to the file with a timestamp
    file_put_contents($path, "\n[" . date('Y-m-d H:i:s') . "]\n" . $log_content . "\n", FILE_APPEND);
}

/**
 * Imprime el búfer de depuración en la pantalla si DEBUG_MODE está activado.
 * Es útil para las respuestas AJAX donde no se puede ver la salida de var_dump directamente.
 */
function print_debug_buffer() {
    if (defined('DEBUG_MODE') && DEBUG_MODE === true && !empty($GLOBALS['debug_buffer'])) {
        // Obtener el contenido del búfer como una cadena
        $log_content = get_debug_buffer_as_string();
        
        // Imprimirlo en un formato que no interfiera con el JSON si se muestra
        // Se usa un <textarea> para contener todo el texto de depuración.
        echo "\n<textarea style='width:100%; height:150px; background-color:#111; color:#0f0; border:1px solid #333; font-family:monospace; font-size:12px; margin-top:20px;'>";
        echo $log_content;
        echo "</textarea>";
    }
}
