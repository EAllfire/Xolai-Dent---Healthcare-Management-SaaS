<?php
/**
 * SCRIPT PARA ENVIAR RECORDATORIOS DE WHATSAPP 24 HORAS ANTES DE LA CITA
 * 
 * Este script debe ser ejecutado por un Cron Job en el servidor, por ejemplo, cada hora.
 * Ejemplo de Cron Job (ejecutar cada hora en punto):
 * 0 * * * * /usr/bin/php /ruta/completa/a/tu/proyecto/enviar_recordatorios.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- INICIO: Verificación de Seguridad para Webcron ---
// Define una "llave secreta". Puedes cambiarla por cualquier texto complejo.
define('CRON_SECRET_KEY', 'RecordatorioCitasHAC_2024!');

// El script solo se ejecutará si la URL contiene ?token=HospitalAngeles2025Recordatorios
$token_recibido = $_GET['token'] ?? null;
if ($token_recibido !== CRON_SECRET_KEY) {
    die("Acceso denegado. Token de seguridad inválido.");
}
// --- FIN: Verificación de Seguridad ---

echo "--- Iniciando script de recordatorios de citas (" . date('Y-m-d H:i:s') . ") ---\n";

// Incluir dependencias
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/whatsapp_functions.php';

try {
    // 1. Definir el rango de tiempo para buscar citas (próximas 24 horas)
    $fecha_inicio = new DateTime();
    $fecha_fin = (new DateTime())->modify('+24 hours');

    // 2. Consultar citas que necesitan recordatorio
    //    - Dentro de las próximas 24 horas.
    //    - Que no hayan sido canceladas (estado_id != 7).
    //    - A las que no se les haya enviado un recordatorio (recordatorio_enviado = 0).
    $sql = "
        SELECT 
            c.id, c.fecha, c.hora_inicio, c.nota_paciente,
            p.nombre, p.apellido, p.telefono,
            m.nombre as modalidad_nombre
        FROM agenda_citas c
        JOIN portal_pacientes p ON c.paciente_id = p.id
        JOIN agenda_modalidades m ON c.modalidad_id = m.id
        WHERE 
            CONCAT(c.fecha, ' ', c.hora_inicio) BETWEEN ? AND ?
            AND c.estado_id != 7
            AND c.recordatorio_enviado = 0
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $fecha_inicio_str = $fecha_inicio->format('Y-m-d H:i:s');
    $fecha_fin_str = $fecha_fin->format('Y-m-d H:i:s');
    $stmt->bind_param("ss", $fecha_inicio_str, $fecha_fin_str);
    
    $stmt->execute();
    $stmt->store_result(); // Almacenar resultado para compatibilidad

    // --- Bloque de código modificado para compatibilidad sin mysqlnd ---
    $citas_a_notificar = [];
    $cita_row = [];
    $meta = $stmt->result_metadata();
    while ($field = $meta->fetch_field()) {
        $params[] = &$cita_row[$field->name];
    }
    call_user_func_array([$stmt, 'bind_result'], $params);

    while ($stmt->fetch()) {
        $citas_a_notificar[] = array_map(fn($x) => $x, $cita_row);
    }
    $stmt->close();

    if (empty($citas_a_notificar)) {
        echo "No hay citas para notificar en este momento.\n";
        exit;
    }

    echo "Se encontraron " . count($citas_a_notificar) . " citas para enviar recordatorio.\n";

    // 3. Iterar y enviar recordatorios
    foreach ($citas_a_notificar as $cita) {
        $nombre_completo = trim($cita['nombre'] . ' ' . $cita['apellido']);
        
        echo "Procesando cita ID: {$cita['id']} para {$nombre_completo}...\n";

        $notas_paciente = $cita['nota_paciente'] ?: 'Sin indicaciones adicionales.';

        // Enviar el recordatorio de WhatsApp
        $resultado_wpp = enviarWhatsAppRecordatorio(
            $cita['telefono'],
            $nombre_completo,
            $cita['modalidad_nombre'],
            $cita['fecha'],
            $cita['hora_inicio'],
            $notas_paciente
        );

        if ($resultado_wpp['success']) {
            echo "  -> Recordatorio enviado exitosamente a {$cita['telefono']}.\n";
            
            // 4. Marcar la cita como notificada para no volver a enviarla
            $stmt_update = $conn->prepare("UPDATE agenda_citas SET recordatorio_enviado = 1 WHERE id = ?");
            if ($stmt_update) {
                $stmt_update->bind_param("i", $cita['id']);
                $stmt_update->execute();
                $stmt_update->close();
                echo "  -> Cita marcada como notificada en la base de datos.\n";
            } else {
                error_log("Error al preparar la consulta de actualización para la cita ID: {$cita['id']}");
            }
        } else {
            echo "  -> ERROR al enviar recordatorio para cita ID: {$cita['id']}. Razón: " . ($resultado_wpp['message'] ?? 'Desconocida') . "\n";
        }
    }

    echo "--- Script de recordatorios finalizado (" . date('Y-m-d H:i:s') . ") ---\n";

} catch (Exception $e) {
    $error_message = "Error fatal en el script de recordatorios: " . $e->getMessage();
    error_log($error_message);
    echo $error_message . "\n";
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}