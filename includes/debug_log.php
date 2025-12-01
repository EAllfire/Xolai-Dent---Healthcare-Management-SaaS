<?php
// debug_log.php - ruta: Agenda/agenda/includes/debug_log.php

if (!function_exists('log_message')) {
    function log_message($message) {
        $log_file = __DIR__ . '/debug_log.txt'; // archivo: Agenda/agenda/includes/debug.log
        $timestamp = date('Y-m-d H:i:s');
        // Intentar crear/append de forma silenciosa
        @file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
    }
}
