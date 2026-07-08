<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$paciente_id = (int)($_GET['paciente_id'] ?? 0);

try {
    $response = [];

    // 1. Datos Personales
    $stmt = $conn->prepare("SELECT * FROM portal_pacientes WHERE id = ?");
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();
    $response['datos_personales'] = $stmt->get_result()->fetch_assoc();

    // 2. Odontograma
    $stmt = $conn->prepare("SELECT tratamientos_json, presupuesto_json, registro_pagos_json, realized_treatments_json, observaciones FROM agenda_expediente_dentista WHERE paciente_id = ?");
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();
    $response['odontograma'] = $stmt->get_result()->fetch_assoc();

    // 3. Obtener Documentos
    $stmt = $conn->prepare("SELECT * FROM agenda_documentos_paciente WHERE paciente_id = ? ORDER BY fecha_carga DESC");
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['documentos'] = [];
    while ($row = $result->fetch_assoc()) {
        $row['ruta_archivo'] = '/' . ltrim($row['ruta_archivo'], '/'); // Asegurar ruta absoluta
        $response['documentos'][] = $row;
    }
    $stmt->close();

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>