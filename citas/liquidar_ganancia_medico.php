<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$doctor = $data['doctor'] ?? '';
$pct = (float)($data['porcentaje'] ?? 0) / 100;
$metodo_pago = $data['metodo_pago'] ?? 'Efectivo';
$f_inicio = $data['fecha_inicio'];
$f_fin = $data['fecha_fin'];

if (empty($doctor)) { echo json_encode(['success' => false, 'error' => 'Doctor no especificado']); exit; }

// Obtener el ID del propietario de la clínica desde la sesión para filtrar expedientes de la misma red
$usuario_id_real = $_SESSION['usuario_id'] ?? 0;
$id_propietario = $_SESSION['id_padre'] ?? $usuario_id_real;

try {
    // 1. Buscar todos los expedientes que pertenecen a la clínica, incluyendo el nombre del creador como fallback
    $sql = "SELECT d.paciente_id, d.registro_pagos_json, u.nombre as fallback_doctor_name 
            FROM agenda_expediente_dentista d
            JOIN portal_pacientes p ON d.paciente_id = p.id
            JOIN agenda_usuarios u ON p.usuario_id = u.id
            WHERE (p.usuario_id = " . (int)$id_propietario . " OR p.usuario_id IN (SELECT id FROM agenda_usuarios WHERE id_padre = " . (int)$id_propietario . "))";
    
    $stmt = $conn->query($sql);
    while ($row = $stmt->fetch_assoc()) {
        $pagos = json_decode($row['registro_pagos_json'], true);
        $cambio = false;

        if (is_array($pagos)) {
            foreach ($pagos as &$p) {
                $fecha = $p['fecha'];
                // Usar la misma lógica que el reporte para identificar al doctor
                $doc_pago = !empty($p['doctor_nombre']) ? $p['doctor_nombre'] : $row['fallback_doctor_name'];
                
                // Se elimina la restricción de pago > 0 para permitir liquidaciones independientemente de los fondos recolectados del paciente
                if ($fecha >= $f_inicio && $fecha <= $f_fin && empty($p['liquidado_medico']) && $doc_pago === $doctor) {
                    $p['liquidado_medico'] = true;
                    $p['fecha_liquidacion'] = date('Y-m-d H:i:s');
                    $p['metodo_liquidacion'] = $metodo_pago;
                    $p['monto_liquidado'] = (float)($p['pago'] ?? 0) * $pct;
                    $cambio = true;
                }
            }
        }

        if ($cambio) {
            $nuevo_json = json_encode($pagos);
            $upd = $conn->prepare("UPDATE agenda_expediente_dentista SET registro_pagos_json = ? WHERE paciente_id = ?");
            $upd->bind_param("si", $nuevo_json, $row['paciente_id']);
            $upd->execute();
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}