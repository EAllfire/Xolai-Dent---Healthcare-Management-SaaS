<?php
// Configuración de base de datos - Hospital Angeles  
// Actualizado: 3 de marzo de 2026

// Detectar si estamos en MAMP local o servidor remoto
function isLocalMAMP() {
    // Check for MAMP environment
    if (strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'MAMP') !== false) return true;
    if (file_exists('/Applications/MAMP/bin/mysql')) return true;
    if (defined('MAMP_PHP')) return true;
    // Also check if running from MAMP htdocs
    if (strpos(__DIR__, '/Applications/MAMP/htdocs') !== false) return true;
    return false;
}

// MAMP local DEFAULTS (override for production below)
if (isLocalMAMP()) {
    $servername = "localhost";
    $username = "root";
    $password = "root";
    $dbname = "agenda_hospital";
    $port = 8889; // MAMP's default MySQL port
} else {
    // CONFIGURACIÓN REMOTA (PRODUCCIÓN)
    $servername = "localhost";
    $username = "root";
    $password = "root";
    $dbname = "agenda_hospital";
    $port = "8883";
}

// Crear conexión con configuración mejorada (incluye puerto cuando está disponible)
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Configurar charset para caracteres especiales
$conn->set_charset("utf8");

// Revisar conexión
if ($conn->connect_error) {
  // Log del error para debugging, pero no terminar el script.
  // El script que lo incluye se encargará de manejar el error.
  error_log("Error de conexión BD: " . $conn->connect_error);
} else {
  // Configurar zona horaria para MySQL solo si la conexión fue exitosa
  $conn->query("SET time_zone = '-06:00'"); // Zona horaria México Central
}

// Intentionally omit closing PHP tag to avoid accidental trailing whitespace/output