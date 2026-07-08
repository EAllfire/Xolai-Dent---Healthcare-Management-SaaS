<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

try {
    // Verificar conexión
    if (!$conn || $conn->connect_error) {
        throw new Exception("Error de conexión a la base de datos.");
    }

    // Obtener el entorno del usuario actual
    $usuario_id = $_SESSION['usuario_id'] ?? 0;
    $id_padre = $_SESSION['id_padre'] ?? 0;
    $entorno_id = ($id_padre > 0) ? $id_padre : $usuario_id;

    // Obtener solo bloqueos (estado 9 o tipo 'bloqueo') a partir de hoy para el entorno correspondiente.
    // Ahora se une con agenda_usuarios para mostrar el nombre del doctor bloqueado.
    $sql = "SELECT c.id, c.fecha, 
                   DATE_FORMAT(c.hora_inicio, '%H:%i') as hora_inicio, 
                   DATE_FORMAT(c.hora_fin, '%H:%i') as hora_fin, 
                   c.nota_interna as motivo, 
                   m.nombre as modalidad,
                   u.nombre as doctor
            FROM agenda_citas c
            LEFT JOIN agenda_usuarios u ON c.profesional_id = u.id
            LEFT JOIN agenda_modalidades m ON c.modalidad_id = m.id
            WHERE (c.estado_id = 9 OR c.tipo = 'bloqueo') 
              AND c.fecha >= CURDATE()
              AND c.usuario_id = ?
            ORDER BY u.nombre, m.nombre, c.hora_inicio, c.hora_fin, c.nota_interna, c.fecha ASC";

    $result = $conn->prepare($sql);
    $result->bind_param("i", $entorno_id);
    $result->execute();
    $result = $result->get_result();

    $raw_blocks = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $raw_blocks[] = $row;
        }
    }

    // Lógica de agrupamiento
    $grouped_blocks = [];
    if (!empty($raw_blocks)) {
        $current_group = null;

        foreach ($raw_blocks as $block) {
            $is_continuation = false;

            if ($current_group) {
                // Verificamos si es el mismo bloque (misma hora, doctor, motivo)
                if ($current_group['hora_inicio'] === $block['hora_inicio'] &&
                    $current_group['hora_fin'] === $block['hora_fin'] &&
                    $current_group['doctor'] === $block['doctor'] &&
                    $current_group['motivo'] === $block['motivo']) {
                    
                    // Verificamos si la fecha es consecutiva (1 día de diferencia)
                    $last_date = new DateTime($current_group['fecha_fin']);
                    $this_date = new DateTime($block['fecha']);
                    $diff = $last_date->diff($this_date);
                    
                    if ($diff->days == 1 && $diff->invert == 0) {
                        $is_continuation = true;
                    }
                }
            }

            if ($is_continuation) {
                // Extendemos el grupo actual
                $current_group['fecha_fin'] = $block['fecha'];
                $current_group['ids'][] = $block['id']; // Añadimos ID para eliminar luego
            } else {
                // Guardamos el grupo anterior si existe
                if ($current_group) {
                    $grouped_blocks[] = $current_group;
                }
                
                // Iniciamos nuevo grupo
                $current_group = [
                    'ids' => [$block['id']],
                    'fecha_inicio' => $block['fecha'],
                    'fecha_fin' => $block['fecha'],
                    'hora_inicio' => $block['hora_inicio'],
                    'hora_fin' => $block['hora_fin'],
                    'doctor' => $block['doctor'] ?? null,
                    'modalidad' => $block['modalidad'] ?? null,
                    'motivo' => $block['motivo'] ?? 'Sin motivo'
                ];
            }
        }
        // Añadir el último grupo
        if ($current_group) {
            $grouped_blocks[] = $current_group;
        }
    }

    echo json_encode($grouped_blocks);

} catch (Exception $e) {
    echo json_encode([]); // Devolver array vacío en caso de error para no romper el frontend
}
?>