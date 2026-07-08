<?php
/**
 * Script de Sincronización Apple Calendar -> Xolai
 * Ejecutar manualmente o vía Cron Job
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/icloud_functions.php';

header('Content-Type: text/plain; charset=utf-8');

// Rango de búsqueda: desde ayer hasta 30 días adelante
$start_sync = date('Y-m-d', strtotime('-1 day'));
$end_sync   = date('Y-m-d', strtotime('+30 days'));

echo "=== INICIANDO SINCRONIZACIÓN DESDE ICLOUD ===\n";

// 1. Obtener médicos con iCloud activo
$sql_users = "SELECT id, id_padre, nombre, icloud_email, icloud_app_password, icloud_calendar_name, icloud_calendar_href, especialidad_id FROM agenda_usuarios WHERE icloud_sync_enabled = 1";
$res_users = $conn->query($sql_users);

while ($user = $res_users->fetch_assoc()) {
    echo "Procesando: {$user['nombre']} ({$user['icloud_email']})\n";
    
    // Determinar quién es el propietario de la agenda (la clínica/admin o el médico si es independiente)
    $owner_id = (!empty($user['id_padre']) && $user['id_padre'] > 0) ? (int)$user['id_padre'] : (int)$user['id'];
    $user_id_int = (int)$user['id'];

    // 1.1 Smart Modality Match: Buscar una modalidad que pertenezca al propietario
    // Priorizamos:
    // 1. Modalidad directamente asignada al médico (user_id_int)
    // 2. Modalidad asignada al propietario (owner_id) Y cuyo nombre contenga el nombre del médico
    // 3. Cualquier otra modalidad asignada al propietario (owner_id)
    $stmt_mod = $conn->prepare("
        SELECT id FROM agenda_modalidades 
        WHERE usuario_id = ? OR usuario_id = ? 
        ORDER BY 
            (usuario_id = ?) DESC,                   -- Prioridad 1: Modalidad del médico específico
            (usuario_id = ? AND nombre LIKE ?) DESC, -- Prioridad 2: Modalidad del propietario con nombre del médico
            (usuario_id = ?) DESC                    -- Prioridad 3: Cualquier modalidad del propietario
        LIMIT 1
    ");
    $search_name = "%" . $user['nombre'] . "%";
    // Parámetros: WHERE (user_id_int, owner_id), ORDER BY (user_id_int, owner_id, search_name, owner_id)
    $stmt_mod->bind_param("iiisii", $user_id_int, $owner_id, $user_id_int, $owner_id, $search_name, $owner_id);
    $stmt_mod->execute();
    $doc_mod_id = ($row_mod = $stmt_mod->get_result()->fetch_assoc()) ? $row_mod['id'] : 8; // Fallback al 8 si no tiene una asignada
    $stmt_mod->close();

    echo "   Modalidad asignada para {$user['nombre']}: $doc_mod_id\n"; // Log para verificar la modalidad seleccionada

    $auth = base64_encode($user['icloud_email'] . ":" . $user['icloud_app_password']);
    $calendar_base = discoverICloudCalendarUrl($auth);
    
    if (!$calendar_base) continue;
    
    $calendar_real = discoverCalendarCollection(
        $auth,
        $calendar_base,
        $user['icloud_calendar_name'] ?? null,
        $user['icloud_calendar_href'] ?? null
    );
    if (!$calendar_real) continue;

    // 2. Traer eventos de iCloud
    $events = fetchICloudEvents($auth, $calendar_real, $start_sync, $end_sync);
    echo "   -> Eventos obtenidos: " . count($events) . " desde $calendar_real\n";
    
    foreach ($events as $event) {
        $ics = $event['data'];

        // Unfold ICS para manejar líneas plegadas
        $ics = preg_replace('/\r?\n[ \t]+/', '', $ics);

        // 1. Extraer propiedades usando Regex que soporte parámetros (como ;TZID=... o ;CHARSET=...)
        preg_match('/^UID(?:;[^:]*)?:(.*)$/mi', $ics, $m_uid);
        preg_match('/^SUMMARY(?:;[^:]*)?:(.*)$/mi', $ics, $m_sum);
        preg_match('/^DESCRIPTION(?:;[^:]*)?:(.*)$/mi', $ics, $m_desc);
        preg_match('/^DTSTART(?:;[^:]*)?:(.*)$/mi', $ics, $m_start);
        preg_match('/^DTEND(?:;[^:]*)?:(.*)$/mi', $ics, $m_end);

        $uid = trim($m_uid[1] ?? '');
        $summary = trim($m_sum[1] ?? 'Cita sin nombre');
        $description = trim($m_desc[1] ?? '');

        $raw_start = trim($m_start[1] ?? '');
        $raw_end   = trim($m_end[1] ?? '');

        if (!$uid || !$raw_start) {
            echo "   ⚠️ Evento inválido: UID={$uid} START={$raw_start} SUMMARY={$summary}\n";
            continue;
        }

        echo "   📋 Evento UID: {$uid}\n";
        echo "      SUMMARY: {$summary}\n";
        echo "      DTSTART: {$raw_start}\n";

        // 2. Procesamiento inteligente de fechas
        try {
            $dt_start = new DateTime($raw_start);
            $dt_end   = new DateTime($raw_end ?: $raw_start);

            // Ajuste de zona horaria: Solo si Apple envía la hora en UTC (termina en Z)
            if (strpos($raw_start, 'Z') !== false) {
                $dt_start->modify('-6 hours');
                $dt_end->modify('-6 hours');
            }

            $date_cita  = $dt_start->format('Y-m-d');
            $time_start = $dt_start->format('H:i:s');
            $time_end   = $dt_end->format('H:i:s');
            echo "      ✓ Fecha procesada: {$date_cita} {$time_start} - {$time_end}\n";
        } catch (Exception $e) {
            echo "      ❌ Error procesando fecha para evento $uid: " . $raw_start . " | " . $e->getMessage() . "\n";
            error_log("[iCloud] Error procesando fecha para evento $uid: " . $raw_start);
            continue;
        }

        // 3. Verificar si ya existe en Xolai
        $check = $conn->prepare("SELECT id FROM agenda_citas WHERE apple_event_id = ?");
        $check->bind_param("s", $uid);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $check->close();
            echo "      ⏭️ Ya existe en Xolai, saltando.\n";
            continue; // Ya la tenemos, saltar
        }
        $check->close();

        // 4. Lógica de Importación
        // 4.0 Detectar si es un bloqueo
        $es_bloqueo = (stripos($summary, 'Bloquear') !== false || stripos($summary, 'bloquear') !== false || stripos($summary, 'BLOQUEAR') !== false);
        
        if ($es_bloqueo) {
            echo "      🚫 Detectado como BLOQUEO, procesando como bloqueo.\n";
            // Es un bloqueo - procesar como bloqueo de dentista (no de consultorio)
            echo " > Detectado BLOQUEO DE DENTISTA: $summary | Fecha: $date_cita $time_start - $time_end\n";
            
            // Verificar si ya existe este bloqueo
            $check = $conn->prepare("SELECT id FROM agenda_citas WHERE apple_event_id = ? AND tipo = 'bloqueo'");
            $check->bind_param("s", $uid);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $check->close();
                echo "   ⏭️  Bloqueo ya existe, saltando.\n";
                continue;
            }
            $check->close();
            
            // Todos los bloqueos desde Apple Calendar son bloqueos de dentista
            $ins = $conn->prepare("INSERT INTO agenda_citas 
                (usuario_id, profesional_id, fecha, hora_inicio, hora_fin, paciente_id, servicio_id, modalidad_id, estado_id, tipo, nota_interna, apple_event_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $bloqueo_usuario_id = $owner_id; // El entorno correspondiente
            $bloqueo_profesional_id = $user['id']; // Siempre es bloqueo del dentista actual
            $bloqueo_modalidad_id = null; // No bloquear consultorio
            $bloqueo_paciente_id = 1;
            $bloqueo_servicio_id = 1;
            $bloqueo_estado_id = 9; // Estado bloqueado
            $bloqueo_tipo = 'bloqueo';
            $bloqueo_nota = "Bloqueo de dentista sincronizado desde Apple Calendar: $summary";
            
            $ins->bind_param("iisssiiiisss", 
                $bloqueo_usuario_id,
                $bloqueo_profesional_id,
                $date_cita,
                $time_start,
                $time_end,
                $bloqueo_paciente_id,
                $bloqueo_servicio_id,
                $bloqueo_modalidad_id,
                $bloqueo_estado_id,
                $bloqueo_tipo,
                $bloqueo_nota,
                $uid
            );
            
            if ($ins->execute()) {
                echo "   ✅ Bloqueo de dentista importado con éxito.\n";
            } else {
                echo "   ❌ Error al importar bloqueo: " . $ins->error . "\n";
            }
            $ins->close();
            continue; // Saltar al siguiente evento
        }
        
        // 4.1 Separar Nombre y Tratamiento (Ej: "Rafael Aviñan - Endodoncia")
        $raw_summary = preg_replace('/^Cita\s*[:\-]?\s*/i', '', $summary);
        $raw_summary = preg_replace('/^Appointment\s*[:\-]?\s*/i', '', $raw_summary);
        // Intentar extraer Nombre, Teléfono y Tratamiento desde el SUMMARY
        $paciente_nombre_input = $raw_summary;
        $servicio_nombre_input = '';
        $telefono_extraido = null;

        // 1) Si usa formato con guión: "Nombre - Teléfono - Tratamiento" o "Nombre - Tratamiento"
        $summary_parts = preg_split('/\s*-\s*/', $raw_summary);
        if (count($summary_parts) >= 3 && preg_match('/^\+?\d[\d\s\-\(\)\.]{4,}\d$/', trim($summary_parts[1]))) {
            $paciente_nombre_input = trim($summary_parts[0]);
            $telefono_extraido = trim($summary_parts[1]);
            $servicio_nombre_input = trim(implode(' - ', array_slice($summary_parts, 2)));
        } elseif (count($summary_parts) >= 2) {
            $paciente_nombre_input = trim($summary_parts[0]);
            $servicio_nombre_input = trim(implode(' - ', array_slice($summary_parts, 1)));
            // Si la segunda parte es un teléfono y hay más partes, mejorar servicio
            if (preg_match('/^\+?\d[\d\s\-\(\)\.]{4,}\d$/', $servicio_nombre_input) && count($summary_parts) == 2) {
                $telefono_extraido = $servicio_nombre_input;
                $servicio_nombre_input = '';
            }
        } else {
            // 2) Buscar un token que parezca teléfono (serie de dígitos, con prefijos opcionales)
            if (preg_match('/(\+?\d[\d\s\-\(\)\.]{4,}\d)/', $raw_summary, $m)) {
                $telefono_extraido = trim($m[1]);
                // dividir por el teléfono
                $parts = explode($telefono_extraido, $raw_summary, 2);
                $paciente_nombre_input = trim($parts[0]);
                $servicio_nombre_input = isset($parts[1]) ? trim($parts[1]) : '';
            } else {
                // Si no hay teléfono ni guión, intentar último token como tratamiento
                $tokens = preg_split('/\s+/', $raw_summary);
                if (count($tokens) >= 3) {
                    $last = array_pop($tokens);
                    $maybe_treatment = $last;
                    $rest = implode(' ', $tokens);
                    // heurística: si el último token contiene letras, puede ser tratamiento
                    if (preg_match('/[A-Za-z\-]/', $maybe_treatment)) {
                        $paciente_nombre_input = trim($rest);
                        $servicio_nombre_input = trim($maybe_treatment);
                    } else {
                        $paciente_nombre_input = $raw_summary;
                        $servicio_nombre_input = '';
                    }
                }
            }
        }
        
        echo "      📝 Parsing SUMMARY: '{$summary}'\n";
        echo "         → Nombre input: '{$paciente_nombre_input}' | Servicio input: '{$servicio_nombre_input}'\n";
        
        // 4.2 Smart Match de Paciente: Búsqueda específica por nombre y apellido
        $paciente_id = null; // Default: sin paciente asociado si no hay match confiable
        $paciente_nombre_text = null;
        $telefono_celular = null;
        $nombre_busqueda = trim(preg_replace('/\s+/', ' ', $paciente_nombre_input));
        $paciente_scope_owner = $owner_id;
        $paciente_scope_user = $user_id_int;

        if (!empty($nombre_busqueda)) {
            // 1. Intento: Coincidencia exacta del nombre completo usando campos legacy y nuevos apellidos
            $stmt_p = $conn->prepare(
                "SELECT id FROM portal_pacientes WHERE (" .
                "CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno) = ? OR " .
                "CONCAT_WS(' ', nombre, apellido) = ? OR " .
                "CONCAT_WS(' ', apellido_paterno, apellido_materno, nombre) = ? OR " .
                "CONCAT(nombre, ' ', apellido) = ? OR CONCAT(apellido, ' ', nombre) = ? OR " .
                "nombre = ? OR apellido = ?" .
                ") AND (usuario_id = ? OR usuario_id = ?) LIMIT 1"
            );
            if (!$stmt_p) {
                echo "      ❌ Error preparando búsqueda 1: " . $conn->error . "\n";
            } else {
                $stmt_p->bind_param("sssssssii", $nombre_busqueda, $nombre_busqueda, $nombre_busqueda, $nombre_busqueda, $nombre_busqueda, $nombre_busqueda, $nombre_busqueda, $paciente_scope_owner, $paciente_scope_user);
                $stmt_p->execute();
                if ($row_p = $stmt_p->get_result()->fetch_assoc()) {
                    $paciente_id = $row_p['id'];
                    echo "         ✓ Paciente encontrado por coincidencia exacta: ID={$paciente_id}\n";
                }
                $stmt_p->close();
            }

            // 2. Intento: Búsqueda por partes si no se encontró un match exacto
            if (is_null($paciente_id)) {
                $search_parts = preg_split('/\s+/', $nombre_busqueda);
                if (count($search_parts) >= 2) {
                    $first_name = array_shift($search_parts);
                    $last_name = implode(' ', $search_parts);
                    $term_name = "%$first_name%";
                    $term_last = "%$last_name%";

                    $stmt_p = $conn->prepare(
                        "SELECT id FROM portal_pacientes WHERE (" .
                        "(nombre LIKE ? AND (apellido LIKE ? OR apellido_paterno LIKE ? OR apellido_materno LIKE ?)) OR " .
                        "((apellido LIKE ? OR apellido_paterno LIKE ? OR apellido_materno LIKE ?) AND nombre LIKE ?)" .
                        ") AND (usuario_id = ? OR usuario_id = ?) LIMIT 1"
                    );
                    if (!$stmt_p) {
                        echo "      ❌ Error preparando búsqueda 2: " . $conn->error . "\n";
                    } else {
                        $stmt_p->bind_param("ssssssssii", $term_name, $term_last, $term_last, $term_last, $term_last, $term_last, $term_last, $term_name, $paciente_scope_owner, $paciente_scope_user);
                        $stmt_p->execute();
                        if ($row_p = $stmt_p->get_result()->fetch_assoc()) {
                            $paciente_id = $row_p['id'];
                            echo "         ✓ Paciente encontrado por búsqueda por partes: ID={$paciente_id}\n";
                        }
                        $stmt_p->close();
                    }
                }
            }

            // 3. Fallback final: Búsqueda flexible por texto completo
            if (is_null($paciente_id)) {
                $search_term = "%" . $nombre_busqueda . "%";
                $stmt_p = $conn->prepare(
                    "SELECT id FROM portal_pacientes WHERE (" .
                    "CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno) LIKE ? OR " .
                    "CONCAT_WS(' ', nombre, apellido) LIKE ? OR " .
                    "nombre LIKE ? OR apellido LIKE ? OR apellido_paterno LIKE ? OR apellido_materno LIKE ?" .
                    ") AND (usuario_id = ? OR usuario_id = ?) LIMIT 1"
                );
                if (!$stmt_p) {
                    echo "      ❌ Error preparando búsqueda 3: " . $conn->error . "\n";
                } else {
                    $stmt_p->bind_param("ssssssii", $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $paciente_scope_owner, $paciente_scope_user);
                    $stmt_p->execute();
                    if ($row_p = $stmt_p->get_result()->fetch_assoc()) {
                        $paciente_id = $row_p['id'];
                        echo "         ✓ Paciente encontrado por búsqueda flexible: ID={$paciente_id}\n";
                    }
                    $stmt_p->close();
                }
            }
        }

        // Si no se encontró paciente real, conservar el nombre/telefono originales para revisión
        if (is_null($paciente_id)) {
            $paciente_nombre_text = $paciente_nombre_input ?: null;
            $telefono_celular = $telefono_extraido ?: null;
            if ($paciente_nombre_text) echo "         ⚠️ Paciente no identificado, guardando nombre/texto: {$paciente_nombre_text}\n";
            if ($telefono_celular) echo "         ⚠️ Teléfono detectado: {$telefono_celular}\n";
        }

        // 4.3 Smart Match de Servicio (Tratamiento)
        $servicio_id = null;
        $servicio_scope_owner = $owner_id;
        $servicio_scope_user = $user_id_int;

        if (!empty($servicio_nombre_input)) {
            $stmt_s = $conn->prepare("SELECT id FROM portal_servicios WHERE nombre LIKE ? AND (usuario_id = ? OR usuario_id = ?) LIMIT 1");
            if (!$stmt_s) {
                echo "      ❌ Error preparando búsqueda servicio 1: " . $conn->error . "\n";
            } else {
                $s_term = "%" . $servicio_nombre_input . "%";
                $stmt_s->bind_param("sii", $s_term, $servicio_scope_owner, $servicio_scope_user);
                $stmt_s->execute();
                if ($row_s = $stmt_s->get_result()->fetch_assoc()) {
                    $servicio_id = $row_s['id'];
                    echo "         ✓ Servicio encontrado por nombre: ID={$servicio_id}\n";
                }
                $stmt_s->close();
            }
        }

        if (is_null($servicio_id)) {
            // Si no hay nombre de servicio en el evento o no se encontró el nombre, buscar un servicio genérico
            $stmt_s = $conn->prepare(
                "SELECT id FROM portal_servicios WHERE (" .
                "nombre LIKE '%consulta%' OR nombre LIKE '%general%' OR nombre LIKE '%cita%'" .
                ") AND (usuario_id = ? OR usuario_id = ?) LIMIT 1"
            );
            if (!$stmt_s) {
                echo "      ❌ Error preparando búsqueda servicio genérico: " . $conn->error . "\n";
            } else {
                $stmt_s->bind_param("ii", $servicio_scope_owner, $servicio_scope_user);
                $stmt_s->execute();
                if ($row_s = $stmt_s->get_result()->fetch_assoc()) {
                    $servicio_id = $row_s['id'];
                    echo "         ✓ Servicio genérico encontrado: ID={$servicio_id}\n";
                }
                $stmt_s->close();
            }
        }

        if (is_null($servicio_id)) {
            // Fallback final: tomar el primer servicio del dueño si no se encontró uno genérico
            $stmt_s = $conn->prepare("SELECT id FROM portal_servicios WHERE (usuario_id = ? OR usuario_id = ?) ORDER BY id LIMIT 1");
            if (!$stmt_s) {
                echo "      ❌ Error preparando búsqueda servicio fallback: " . $conn->error . "\n";
            } else {
                $stmt_s->bind_param("ii", $servicio_scope_owner, $servicio_scope_user);
                $stmt_s->execute();
                if ($row_s = $stmt_s->get_result()->fetch_assoc()) {
                    $servicio_id = $row_s['id'];
                    echo "         ✓ Primer servicio del propietario asignado: ID={$servicio_id}\n";
                }
                $stmt_s->close();
            }
        }

        if (is_null($servicio_id)) {
            $servicio_id = 20; // Fallback provisional si no hay servicios en el propietario
            echo "         ⚠️ Usando fallback ID=20\n";
        }

        // Si no hay servicio encontrado, guardar texto libre
        $servicio_text = null;
        if ($servicio_id == 20) {
            // Fallback reservado, pero si no había texto de servicio guardar el raw
            if (!empty($servicio_nombre_input)) $servicio_text = $servicio_nombre_input;
        } elseif (is_null($servicio_id) || $servicio_id == 0) {
            $servicio_text = $servicio_nombre_input ?: null;
            // Normalizar servicio_id a entero (0 si no existe)
            $servicio_id = 0;
        }

        echo "      → Paciente detectado: ID={$paciente_id} | Nombre buscado: '{$paciente_nombre_input}'\n";
        echo "      → Servicio detectado: ID={$servicio_id} | Nombre buscado: '{$servicio_nombre_input}'\n";

        echo " > Detectada: $paciente_nombre_input | Tratamiento: " . ($servicio_nombre_input ?: 'General') . " | Fecha: $date_cita $time_start\n";

        $ins = $conn->prepare("INSERT INTO agenda_citas 
            (usuario_id, profesional_id, fecha, hora_inicio, hora_fin, paciente_id, servicio_id, modalidad_id, estado_id, tipo, nota_paciente, apple_event_id, paciente_nombre_text, telefono_celular, servicio_text) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if (!$ins) {
            echo "      ❌ Error preparando INSERT: " . $conn->error . "\n";
            continue;
        }

        $id_padre = $owner_id; // Propietario de la cita (clínica) para que sea visible en la agenda principal
        $medico_id = $user['id'];
        $modalidad_id = $doc_mod_id; // Se asigna a la modalidad específica del médico
        $estado_id = 1;    // Reservado
        $tipo = "normal";
        
        // Normalizar textos para bind (usar cadena vacía si null)
        $paciente_nombre_text_val = $paciente_nombre_text ?: '';
        $telefono_celular_val = $telefono_celular ?: '';
        $servicio_text_val = $servicio_text ?: '';

        $ins->bind_param("iisssiiiissssss", 
            $id_padre, 
            $medico_id, 
            $date_cita, 
            $time_start, 
            $time_end, 
            $paciente_id, 
            $servicio_id, 
            $modalidad_id, 
            $estado_id, 
            $tipo,
            $raw_summary, // Guardamos el resumen completo de Apple en la nota del paciente
            $uid,
            $paciente_nombre_text_val,
            $telefono_celular_val,
            $servicio_text_val
        );
        
        if ($ins->execute()) {
            echo "      ✅ Importada con éxito (ID inserción: " . $conn->insert_id . ").\n";
        } else {
            echo "      ❌ Error al ejecutar INSERT: " . $ins->error . "\n";
        }
        $ins->close();
    }
}

echo "=== PROCESO FINALIZADO ===\n";
?>
