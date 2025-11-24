<?php
// Logger exclusivo para email

if (!function_exists('log_email')) {

    function log_email($message, $level = 'INFO') {
        $log_file = __DIR__ . '/email_logs.log';

        if (!file_exists($log_file)) {
            @touch($log_file);
            @chmod($log_file, 0666);
        }

        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] [$level] $message\n";

        @file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
    }
}
