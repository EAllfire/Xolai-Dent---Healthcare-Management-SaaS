<?php
// Configuración de base de datos - Hospital Angeles
// Actualizado: 7 de octubre de 2025

// CONFIGURACIÓN REMOTA (PRODUCCIÓN)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "agenda_hospital";
$port = "8883"; // MySQL default port; overridden for MAMP when needed

/* 
// CONFIGURACIÓN LOCAL (DESARROLLO - MAMP)
// Descomenta estas líneas para usar base de datos local
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "agenda_hospital";
*/

// Crear conexión con configuración mejorada
$conn = new mysqli($servername, $username, $password, $dbname);

// Configurar charset para caracteres especiales
$conn->set_charset("utf8");

// Revisar conexión
if ($conn->connect_error) {
  // Log del error para debugging
  error_log("Error de conexión BD: " . $conn->connect_error);
  
  if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
    header('Content-Type: application/json');
    echo json_encode([
      "success" => false, 
      "error" => "Error de conexión a la base de datos",
      "details" => $conn->connect_error
    ]);
    exit;
  } else {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
  }
}

// Configurar zona horaria para MySQL
$conn->query("SET time_zone = '-06:00'"); // Zona horaria México Central
?>