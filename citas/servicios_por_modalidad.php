<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . "/../includes/db.php";
header('Content-Type: application/json');

        $servicios = []; // Initialize $servicios to an empty array

    

        try {

            $usuario_id = $_SESSION['usuario_id'] ?? null;
            $usuario_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';
            $modalidad_id = isset($_GET['modalidad_id']) ? intval($_GET['modalidad_id']) : 0;

    

            if ($modalidad_id === 0) {

                throw new Exception("Modalidad ID no proporcionado o inválido.");

            }
            
            if (!$usuario_id) {
                throw new Exception("Sesión de usuario no válida.");
            }

    

            // Consulta para obtener servicios basado en modalidad_id directamente de portal_servicios
            
            if ($usuario_tipo === 'superadmin' || $usuario_tipo === 'admin') {
                $sql = "SELECT id, nombre, descripcion, precio, duracion_minutos FROM portal_servicios WHERE modalidad_id = ? ORDER BY nombre ASC";
            } else {
                $sql = "SELECT id, nombre, descripcion, precio, duracion_minutos FROM portal_servicios WHERE modalidad_id = ? AND usuario_id = ? ORDER BY nombre ASC";
            }
            
            $stmt = $conn->prepare($sql);            if ($stmt === false) {
                throw new Exception("Error al preparar la consulta: " . $conn->error);

            }

            if ($usuario_tipo === 'superadmin' || $usuario_tipo === 'admin') {
                $stmt->bind_param("i", $modalidad_id);
            } else {
                $stmt->bind_param("ii", $modalidad_id, $usuario_id);
            }
            $stmt->execute();
            $stmt->bind_result($id, $nombre, $descripcion, $precio, $duracion);
            

            while ($stmt->fetch()) {

                $servicios[] = [
                    'id' => $id,
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'precio' => $precio,
                    'duracion_minutos' => $duracion
                ];

            }
            $stmt->close();

            echo json_encode($servicios);

            

        } catch (Exception $e) {

            // Log the error for debugging purposes

            error_log("Error en servicios_por_modalidad.php: " . $e->getMessage());

            // Return a JSON error response

            echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            // Ensure no other output is sent

            http_response_code(500);

        }?>