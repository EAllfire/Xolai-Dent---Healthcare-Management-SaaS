<?php
// Configuración de base de datos - Hospital Angeles  
// Actualizado: 7 de octubre de 2025 - Conexión remota

// CONFIGURACIÓN REMOTA (PRODUCCIÓN)
$servername = "localhost";  // localhost para mejor rendimiento en el mismo servidor
$username = "eli";
$password = "HACeli2025";
$dbname = "hac";

/*$servername = "localhost";  // localhost para mejor rendimiento en el mismo servidor
$username = "root";
$password = "root";
$dbname = "agenda_hospital";
$port = "8883";*/
// Crear conexión con configuración mejorada
$conn = new mysqli($servername, $username, $password, $dbname );

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