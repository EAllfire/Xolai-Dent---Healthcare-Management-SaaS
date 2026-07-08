<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Evitar que errores o advertencias rompan la respuesta JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

if (!puedeRealizar('gestionar_pacientes')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tienes permiso para realizar esta acción.']);
    exit;
}

$seccion = $_GET['seccion'] ?? '';
$usuario_id = $_SESSION['usuario_id'] ?? null;
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Médico';

try {
    switch ($seccion) {
        case 'datos_personales':
            $data = json_decode(file_get_contents('php://input'), true);
            $paciente_id = (int)($data['paciente_id'] ?? 0);
            if ($paciente_id === 0) throw new Exception('ID de paciente no válido.');

            // --- INICIO: Validación de CURP duplicado por usuario ---
            // Un mismo CURP puede existir para diferentes médicos, pero no para el mismo.
            $curp = trim($data['curp'] ?? '');
            // Tratar la cadena vacía como NULL para evitar problemas con el índice único.
            if ($curp === '') {
                $curp = null;
            }

            if (!empty($curp)) {
                // 1. Obtener el ID del usuario que está realizando la acción (el médico logueado).
                $medico_actual_id = $_SESSION['usuario_id'] ?? null;

                // 2. Buscar si ya existe otro paciente con el mismo CURP que pertenezca al médico actual.
                // Esto asegura que el CURP sea único para los pacientes de cada médico.
                if ($medico_actual_id) {
                    // Se busca si existe otro paciente (id != ?) con el mismo CURP para el médico actual (usuario_id = ?).
                    // Esta consulta ignora correctamente a los pacientes con usuario_id NULO o que pertenecen a otros médicos.
                    $stmt_check = $conn->prepare("SELECT id FROM portal_pacientes WHERE curp = ? AND id != ? AND usuario_id = ?");
                    $stmt_check->bind_param("sii", $curp, $paciente_id, $medico_actual_id);
                    $stmt_check->execute();
                    $stmt_check->store_result();
                    if ($stmt_check->num_rows > 0) throw new Exception('Ya tiene otro paciente registrado con este mismo CURP.');
                    $stmt_check->close();
                }
            }
            // --- FIN: Validación de CURP ---

            $sql = "UPDATE portal_pacientes SET 
                        nombre = ?, 
                        apellido_paterno = ?, 
                        apellido_materno = ?, 
                        fecha_nacimiento = ?, 
                        curp = ?, 
                        telefono = ?, 
                        correo = ?, 
                        direccion = ?,
                        motivo_consulta = ?,
                        alergias = ?,
                        medicamentos = ?,
                        tel_emergencia = ?,
                        rfc = ?,
                        origen = ?, 
                        recomendado_por_id = ?,
                        comentarios = ?
                    WHERE id = ?";
            
            $fecha_nacimiento = !empty($data['fecha_nacimiento']) ? $data['fecha_nacimiento'] : null;

            $nombre = $data['nombre'] ?? '';
            $apellido_paterno = $data['apellido_paterno'] ?? '';
            $apellido_materno = $data['apellido_materno'] ?? '';
            $telefono = $data['telefono'] ?? '';
            $correo = $data['correo'] ?? '';
            $direccion = $data['direccion'] ?? '';
            $motivo_consulta = $data['motivo_consulta'] ?? '';
            $alergias = $data['alergias'] ?? '';
            $medicamentos = $data['medicamentos'] ?? '';
            $tel_emergencia = $data['tel_emergencia'] ?? '';
            $rfc = $data['rfc'] ?? '';
            $origen = $data['origen'] ?? ''; // Permitir que sea vacío si no se selecciona
            $rec_id = !empty($data['recomendado_por_id']) ? (int)$data['recomendado_por_id'] : null;
            $comentarios = $data['comentarios'] ?? '';

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error al preparar la actualización de datos: " . $conn->error);
            }

                $stmt->bind_param("sssssssssssssssii", 
                $nombre,
                $apellido_paterno,
                $apellido_materno,
                $fecha_nacimiento,
                $curp,
                $telefono,
                $correo,
                $direccion,
                $motivo_consulta,
                $alergias,
                $medicamentos,
                $tel_emergencia,
                $rfc,
                $origen,
                $rec_id,
                $comentarios,
                $paciente_id
            );
            if (!$stmt->execute()) throw new Exception('Error al actualizar los datos personales: ' . $stmt->error);
            echo json_encode(['success' => true]);
            break;

        case 'historia_clinica':
            $data = json_decode(file_get_contents('php://input'), true);
            $paciente_id = (int)($data['paciente_id'] ?? 0);
            if ($paciente_id === 0) throw new Exception('ID de paciente no válido.');

            // Lógica de UPSERT (INSERT ... ON DUPLICATE KEY UPDATE)
            $sql = "INSERT INTO agenda_expediente_clinico (paciente_id, antecedentes_heredofamiliares, antecedentes_personales_patologicos, antecedentes_personales_no_patologicos, padecimiento_actual, exploracion_fisica, diagnostico_principal, otros_diagnosticos, plan_tratamiento, pronostico)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    antecedentes_heredofamiliares = VALUES(antecedentes_heredofamiliares),
                    antecedentes_personales_patologicos = VALUES(antecedentes_personales_patologicos),
                    antecedentes_personales_no_patologicos = VALUES(antecedentes_personales_no_patologicos),
                    padecimiento_actual = VALUES(padecimiento_actual),
                    exploracion_fisica = VALUES(exploracion_fisica),
                    diagnostico_principal = VALUES(diagnostico_principal),
                    otros_diagnosticos = VALUES(otros_diagnosticos),
                    plan_tratamiento = VALUES(plan_tratamiento),
                    pronostico = VALUES(pronostico)";
            
            $ant_heredo = $data['antecedentes_heredofamiliares'] ?? '';
            $ant_pat = $data['antecedentes_personales_patologicos'] ?? '';
            $ant_no_pat = $data['antecedentes_personales_no_patologicos'] ?? '';
            $pad_act = $data['padecimiento_actual'] ?? '';
            $exp_fis = $data['exploracion_fisica'] ?? '';
            $diag_pri = $data['diagnostico_principal'] ?? '';
            $diag_otr = $data['otros_diagnosticos'] ?? '';
            $pla_tra = $data['plan_tratamiento'] ?? '';
            $pronostico = $data['pronostico'] ?? '';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssssssss", 
                $paciente_id,
                $ant_heredo,
                $ant_pat,
                $ant_no_pat,
                $pad_act,
                $exp_fis,
                $diag_pri,
                $diag_otr,
                $pla_tra,
                $pronostico
            );
            if (!$stmt->execute()) throw new Exception('Error al guardar la historia clínica: ' . $stmt->error);
            echo json_encode(['success' => true]);
            break;

        case 'signos_vitales':
            $data = json_decode(file_get_contents('php://input'), true);
            $paciente_id = (int)($data['paciente_id'] ?? 0);
            if ($paciente_id === 0) throw new Exception('ID de paciente no válido.');

            $presion = $data['presion_arterial'] ?? '';
            $f_card = (int)($data['frecuencia_cardiaca'] ?? 0);
            $f_resp = (int)($data['frecuencia_respiratoria'] ?? 0);
            $temp = (float)($data['temperatura_celsius'] ?? 0);
            $peso = (float)($data['peso_kg'] ?? 0);
            $talla = (float)($data['talla_cm'] ?? 0);

            $sql = "INSERT INTO agenda_signos_vitales (paciente_id, presion_arterial, frecuencia_cardiaca, frecuencia_respiratoria, temperatura_celsius, peso_kg, talla_cm) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isiiidd",
                $paciente_id,
                $presion,
                $f_card,
                $f_resp,
                $temp,
                $peso,
                $talla
            );
            if (!$stmt->execute()) throw new Exception('Error al guardar signos vitales: ' . $stmt->error);
            echo json_encode(['success' => true]);
            break;

        case 'nota_evolucion':
            $data = json_decode(file_get_contents('php://input'), true);
            $paciente_id = (int)($data['paciente_id'] ?? 0);
            if ($paciente_id === 0) throw new Exception('ID de paciente no válido.');

            $subjetivo = $data['nota_subjetivo'] ?? '';
            $objetivo = $data['nota_objetivo'] ?? '';
            $analisis = $data['nota_analisis'] ?? '';
            $plan = $data['nota_plan'] ?? '';

            $sql = "INSERT INTO agenda_notas_evolucion (paciente_id, usuario_id, nota_subjetivo, nota_objetivo, nota_analisis, nota_plan) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iissss",
                $paciente_id,
                $usuario_id,
                $subjetivo,
                $objetivo,
                $analisis,
                $plan
            );
            if (!$stmt->execute()) throw new Exception('Error al guardar la nota de evolución: ' . $stmt->error);
            echo json_encode(['success' => true]);
            break;

        case 'odontograma':
            $data = json_decode(file_get_contents('php://input'), true);
            $paciente_id = (int)($data['paciente_id'] ?? 0);
            if ($paciente_id === 0) throw new Exception('ID de paciente no válido.');

            $tratamientos_arr = $data['tratamientos'] ?? [];
            
            // Inyectar atribución de médico manejando la nueva estructura anidada
            if (is_array($tratamientos_arr)) {
                // Procesar tratamientos por diente
                if (isset($tratamientos_arr['teeth']) && is_array($tratamientos_arr['teeth'])) {
                    foreach ($tratamientos_arr['teeth'] as &$t) {
                        if (is_array($t) && empty($t['doctor_nombre'])) $t['doctor_nombre'] = $usuario_nombre;
                    }
                }
                // Procesar tratamientos generales
                if (isset($tratamientos_arr['general']) && is_array($tratamientos_arr['general'])) {
                    foreach ($tratamientos_arr['general'] as &$t) {
                        if (is_array($t) && empty($t['doctor_nombre'])) $t['doctor_nombre'] = $usuario_nombre;
                    }
                }
            }

            $tratamientos = json_encode($tratamientos_arr);
            $observaciones = $data['observaciones'] ?? '';

            $sql = "INSERT INTO agenda_expediente_dentista (paciente_id, tratamientos_json, observaciones)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    tratamientos_json = VALUES(tratamientos_json),
                    observaciones = VALUES(observaciones)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $paciente_id, $tratamientos, $observaciones);
            if (!$stmt->execute()) throw new Exception('Error al guardar el odontograma: ' . $stmt->error);
            echo json_encode(['success' => true]);
            break;

              case 'presupuesto_dental':
            $data = json_decode(file_get_contents('php://input'), true);
            $paciente_id = (int)($data['paciente_id'] ?? 0);
            if ($paciente_id === 0) throw new Exception('ID de paciente no válido.');

            $presupuesto_data = $data['presupuesto'] ?? [];
            $realized_treatments_data = $data['realized_treatments'] ?? [];

            // Inyectar atribución de médico en los items del presupuesto
            if (isset($presupuesto_data['items']) && is_array($presupuesto_data['items'])) {
                foreach ($presupuesto_data['items'] as &$item) {
                    if (empty($item['doctor_nombre'])) {
                        $item['doctor_nombre'] = $usuario_nombre;
                    }
                    unset($item['realizado']); // Eliminar flag antiguo para usar el nuevo sistema de cobro
                }
            }
            $presupuesto = json_encode($presupuesto_data);
            $realized_treatments = json_encode($realized_treatments_data);

            $sql = "INSERT INTO agenda_expediente_dentista (paciente_id, presupuesto_json, realized_treatments_json)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    presupuesto_json = VALUES(presupuesto_json),
                    realized_treatments_json = VALUES(realized_treatments_json)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $paciente_id, $presupuesto, $realized_treatments);
            if (!$stmt->execute()) throw new Exception('Error al guardar el presupuesto: ' . $stmt->error);
            echo json_encode(['success' => true]);
            break;

        case 'registro_pagos_dental':
            $data = json_decode(file_get_contents('php://input'), true);
            $paciente_id = (int)($data['paciente_id'] ?? 0);
            if ($paciente_id === 0) throw new Exception('ID de paciente no válido.');

            $pagos_arr = $data['pagos'] ?? [];
            // Inyectar atribución de médico en el registro de pagos
            if (is_array($pagos_arr)) {
                foreach ($pagos_arr as &$p) {
                    if (empty($p['doctor_nombre'])) {
                        $p['doctor_nombre'] = $usuario_nombre;
                    }
                }
            }
            $pagos = json_encode($pagos_arr);

            $sql = "INSERT INTO agenda_expediente_dentista (paciente_id, registro_pagos_json)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE
                    registro_pagos_json = VALUES(registro_pagos_json)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $paciente_id, $pagos);
            if (!$stmt->execute()) throw new Exception('Error al guardar el registro de pagos: ' . $stmt->error);
            echo json_encode(['success' => true]);
            break;

        case 'documento':
            $paciente_id = (int)($_POST['paciente_id'] ?? 0);
            if ($paciente_id === 0) throw new Exception('ID de paciente no válido.');
            if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Error en la subida del archivo o archivo no proporcionado.');
            }

            // Usar una ruta absoluta basada en el DOCUMENT_ROOT para evitar problemas con rutas relativas.
            // Esto asegura que los archivos se guarden en la carpeta 'uploads' en la raíz del sitio web.
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/expedientes/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_info = pathinfo($_FILES['archivo']['name']);
            $file_name = 'paciente_' . $paciente_id . '_' . uniqid() . '.' . $file_info['extension'];
            $target_file = $upload_dir . $file_name;

            if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $target_file)) {
                throw new Exception('No se pudo mover el archivo subido.');
            }

            $tipo_documento = $_POST['tipo_documento'] ?? 'General';
            $nombre_documento = $_FILES['archivo']['name'];
            $ruta_relativa_db = 'uploads/expedientes/' . $file_name;

            $sql = "INSERT INTO agenda_documentos_paciente (paciente_id, usuario_id, nombre_documento, tipo_documento, ruta_archivo) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisss", $paciente_id, $usuario_id, $nombre_documento, $tipo_documento, $ruta_relativa_db);
            if (!$stmt->execute()) throw new Exception('Error al guardar el registro del documento en la base de datos: ' . $stmt->error);
            echo json_encode(['success' => true, 'message' => 'Documento subido correctamente.']);
            break;

        default:
            throw new Exception('Sección no válida.');
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error en guardar_expediente.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>