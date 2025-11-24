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
