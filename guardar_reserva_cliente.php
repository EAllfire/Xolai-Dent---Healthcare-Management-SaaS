<?php
// =========================
// CONFIGURACIÓN
// =========================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Iniciar sesión para obtener el usuario creador (Admin/Panel) si existe
session_start();

header('Content-Type: application/json');

// Includes
require_once __DIR__ . '/includes/db.php';

// --- FUNCIÓN DE LOG INTEGRADA ---
// Escribirá en un archivo llamado debug_log.txt en la misma carpeta.
// Definida condicionalmente para evitar errores de re-declaración
if (!function_exists('log_message')) {
function log_message($message) {
    $log_file = __DIR__ . '/debug_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] " . print_r($message, true) . "\n";
    // FILE_APPEND para añadir al final, LOCK_EX para evitar escrituras simultáneas.
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}
}

log_message("--- INICIO DE RESERVA ---");

try { // INICIO BLOQUE TRY GLOBAL

// =========================
// MANEJO DE FORM DATA CON JSON
// =========================
if (!isset($_POST['json_data'])) {
    echo json_encode([
        "success" => false,
        "error"   => "No se recibieron datos del formulario"
    ]);
    log_message("ERROR: No se recibió 'json_data' en el POST.");
    exit;
}

// Decodificar los datos JSON
$input_data = json_decode($_POST['json_data'], true);
error_log("Datos JSON recibidos en guardar_reserva_cliente.php: " . print_r($input_data, true));
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        "success" => false,
        "error"   => "Error al decodificar JSON"
    ]);
    log_message("ERROR: Fallo al decodificar el JSON recibido.");
    exit;
}

// =========================
// VALIDAR DATOS BÁSICOS
// =========================
$required = ['nombre', 'telefono', 'email', 'fecha_nacimiento', 'fecha_cita', 'hora_seleccionada'];
foreach ($required as $field) {
    if (!isset($input_data[$field]) || empty($input_data[$field])) {
        echo json_encode([
            "success" => false,
            "error"   => "Faltan datos requeridos: " . $field
        ]);
        log_message("ERROR: Campo requerido faltante: " . $field);
        exit;
    }
}

// =========================
// RECOPILAR DATOS
// =========================
$fecha           = $input_data['fecha_cita'];
$hora            = $input_data['hora_seleccionada'];
$nombre_completo = $input_data['nombre'];
$telefono        = $input_data['telefono'];
$email           = $input_data['email'];
$fecha_nacimiento = $input_data['fecha_nacimiento'];

$servicio_id     = $input_data['servicio_id'] ?? null;
$modalidad_id    = $input_data['modalidad_id'] ?? null;
$observaciones   = $input_data['observaciones'] ?? '';
$atencion_especial = $input_data['atencion_especial'] ?? 0; // Recibimos el nuevo campo

$tipo_reserva    = $input_data['tipo_reserva'] ?? 'servicio';

// 🔹 Obtener portal_usuario_id de los datos
$portal_usuario_id = $input_data['portal_usuario_id'] ?? null;

log_message("Datos recibidos: " . json_encode($input_data));
error_log("Valor de \$portal_usuario_id al inicio: " . var_export($portal_usuario_id, true));

// =========================
// MANEJO DE ARCHIVOS
// =========================
$url_identificacion = null;
$url_orden_medica = null;
$upload_dir = 'uploads/';

if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}

// Procesar foto de identificación
if (isset($_FILES['foto_identificacion']) && $_FILES['foto_identificacion']['error'] === UPLOAD_ERR_OK) {
    $filename = uniqid('ine_') . '_' . basename($_FILES['foto_identificacion']['name']);
    $target_file = $upload_dir . $filename;
    if (move_uploaded_file($_FILES['foto_identificacion']['tmp_name'], $target_file)) {
        $url_identificacion = $target_file;
        log_message("Archivo de identificación subido a: " . $url_identificacion);
    }
}

// Procesar foto de orden médica
if (isset($_FILES['foto_orden']) && $_FILES['foto_orden']['error'] === UPLOAD_ERR_OK) {
    $filename = uniqid('orden_') . '_' . basename($_FILES['foto_orden']['name']);
    $target_file = $upload_dir . $filename;
    if (move_uploaded_file($_FILES['foto_orden']['tmp_name'], $target_file)) {
        $url_orden_medica = $target_file;
        log_message("Archivo de orden médica subido a: " . $url_orden_medica);
    }
}

// =========================
// DETERMINAR ID DEL PACIENTE - LÓGICA CORREGIDA
// =========================
$paciente_id = null;

if (!empty($portal_usuario_id)) {
    log_message("Buscando paciente con portal_usuario_id: " . $portal_usuario_id);
    error_log("BRANCH: Intentando encontrar y actualizar paciente existente con portal_usuario_id: {$portal_usuario_id}");
    // 🔹 BUSCAR EN portal_pacientes POR LA COLUMNA portal_usuario_id
    $stmt = $conn->prepare("SELECT id FROM portal_pacientes WHERE portal_usuario_id = ?");
    $stmt->bind_param("i", $portal_usuario_id);
    $stmt->execute();
    // Reemplazar get_result() con bind_result() para compatibilidad sin mysqlnd
    $stmt->store_result(); // Almacenar el resultado para poder usar num_rows
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($paciente_id_from_db); // Enlazar el ID de la columna a una variable
        $stmt->fetch(); // Obtener el resultado
        $paciente_id = $paciente_id_from_db; // Asignar al paciente_id
        
        // 🔹 ACTUALIZAR DATOS DEL PACIENTE EXISTENTE
        $nombre_partes = explode(' ', trim($nombre_completo), 2);
        $nombre = $nombre_partes[0];
        $apellido = $nombre_partes[1] ?? '';
        
        $stmt_update = $conn->prepare("UPDATE portal_pacientes SET nombre = ?, apellido = ?, telefono = ?, correo = ?, fecha_nacimiento = ? WHERE portal_usuario_id = ?");
        $stmt_update->bind_param("sssssi", $nombre, $apellido, $telefono, $email, $fecha_nacimiento, $portal_usuario_id);
        $stmt_update->execute();
        $stmt_update->close();
        log_message("Paciente existente actualizado. ID: " . $paciente_id);
        
        error_log("✅ Paciente encontrado y actualizado - Portal Usuario ID: {$portal_usuario_id}, Paciente ID REAL: {$paciente_id}");
        
    } else {
        // ❌ No se encontró paciente con ese portal_usuario_id
        error_log("ERROR: No se encontró paciente con portal_usuario_id: {$portal_usuario_id}. Saliendo.");
        echo json_encode([
            "success" => false,
            "error" => "No se encontró un paciente con el ID del portal proporcionado",
            "portal_usuario_id" => $portal_usuario_id
        ]);
        log_message("ERROR: No se encontró paciente con portal_usuario_id: " . $portal_usuario_id);
        exit;
    }
    $stmt->close();
    
} else {
    log_message("Creando nuevo paciente (sin portal_usuario_id).");
    error_log("BRANCH: Creando nuevo paciente (portal_usuario_id está vacío)");
    // =========================
    // OBTENER DUEÑO Y CREAR PACIENTE (SIN PORTAL)
    // =========================

    // --- INICIO: OBTENER EL usuario_id DEL SERVICIO O MODALIDAD ---
    // Un paciente nuevo debe pertenecer a un usuario (clínica/médico).
    // Lo determinamos a partir del servicio o modalidad que se está reservando.
    // O si existe una sesión activa (creado desde panel), usamos ese ID.
    $usuario_propietario_id = null;

    // 1. Prioridad: Usuario logueado (Admin/Recepcionista crea el paciente)
    if (isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id'])) {
        $usuario_propietario_id = $_SESSION['usuario_id'];
        log_message("Usuario creador asignado desde sesión: " . $usuario_propietario_id);
    } else {
        // 2. Fallback: Si es reserva pública, buscar dueño del servicio o modalidad
        if (!empty($servicio_id)) {
            $stmt_owner = $conn->prepare("SELECT usuario_id FROM portal_servicios WHERE id = ?");
            $stmt_owner->bind_param("i", $servicio_id);
            $stmt_owner->execute();
            $stmt_owner->bind_result($owner_id);
            if ($stmt_owner->fetch()) {
                $usuario_propietario_id = $owner_id;
            }
            $stmt_owner->close();
        }
        // Si no se encuentra por servicio, intentar por modalidad
        if (empty($usuario_propietario_id) && !empty($modalidad_id)) {
            $stmt_owner = $conn->prepare("SELECT usuario_id FROM agenda_modalidades WHERE id = ?");
            $stmt_owner->bind_param("i", $modalidad_id);
            $stmt_owner->execute();
            $stmt_owner->bind_result($owner_id);
            if ($stmt_owner->fetch()) {
                $usuario_propietario_id = $owner_id;
            }
            $stmt_owner->close();
        }
    }
    // --- FIN: OBTENER EL usuario_id ---

    $nombre_partes = explode(' ', trim($nombre_completo), 2);
    $nombre = $nombre_partes[0];
    $apellido = $nombre_partes[1] ?? '';

    // =========================
    // SMART MATCHING: BUSCAR PACIENTE EXISTENTE
    // =========================
    $paciente_encontrado_id = null;

    // ESTRATEGIA SEGURA: Coincidencia por (Correo O Teléfono) Y Fecha de Nacimiento.
    // Si el contacto coincide pero la fecha de nacimiento es distinta, se asume que es un familiar (hijo/padre)
    // y se crea un paciente nuevo en lugar de sobrescribir el existente.
    $sql_check = "SELECT id FROM portal_pacientes WHERE (correo = ? OR telefono = ?) AND fecha_nacimiento = ? LIMIT 1";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("sss", $email, $telefono, $fecha_nacimiento);
    $stmt_check->execute();
    $stmt_check->bind_result($found_id);
    if ($stmt_check->fetch()) {
        $paciente_encontrado_id = $found_id;
    }
    $stmt_check->close();

    // CORRECCIÓN: La columna se llama 'tipo_id', no 'tipo'. Asignamos un valor por defecto (ej. 1 para 'Cliente Web')
    $default_tipo_id = 1; // Asumimos que 1 es un tipo de paciente válido como 'Cliente Web'

    if ($paciente_encontrado_id) {
        // CASO A: PACIENTE EXISTE -> Actualizar datos y usar su ID
        $paciente_id = $paciente_encontrado_id;
        log_message("Paciente existente detectado (Smart Match). ID: " . $paciente_id);
        
        // Actualizamos nombre, teléfono, etc. para mantener la info al día
        $stmt_update = $conn->prepare("UPDATE portal_pacientes SET nombre = ?, apellido = ?, telefono = ?, correo = ?, fecha_nacimiento = ? WHERE id = ?");
        $stmt_update->bind_param("sssssi", $nombre, $apellido, $telefono, $email, $fecha_nacimiento, $paciente_id);
        $stmt_update->execute();
        $stmt_update->close();
        
        error_log("Paciente existente actualizado sin login, Paciente ID: {$paciente_id}");
    } else {
        // CASO B: PACIENTE NUEVO -> Crear registro
        $stmt = $conn->prepare("INSERT INTO portal_pacientes (usuario_id, nombre, apellido, telefono, correo, fecha_nacimiento, tipo_id, origen) VALUES (?, ?, ?, ?, ?, ?, ?, 'web')");
        $stmt->bind_param("isssssi", $usuario_propietario_id, $nombre, $apellido, $telefono, $email, $fecha_nacimiento, $default_tipo_id);

        if (!$stmt->execute()) {
            error_log("ERROR: Fallo al crear nuevo paciente: " . $stmt->error);
            echo json_encode([
                "success" => false,
                "error" => "Error al crear paciente",
                "db_error" => $stmt->error
            ]);
            log_message("ERROR: Fallo al crear nuevo paciente: " . $stmt->error);
            exit;
        }

        $paciente_id = $stmt->insert_id;
        $stmt->close();
        error_log("Nuevo paciente creado sin portal_usuario_id, Paciente ID: {$paciente_id}");
    }
}

// =========================
// VALIDAR QUE TENEMOS PACIENTE_ID
// =========================
if (!$paciente_id) {
    echo json_encode([
        "success" => false,
        "error" => "No se pudo determinar el ID del paciente"
    ]);
    log_message("ERROR: El ID del paciente es nulo después de la lógica de creación/búsqueda.");
    exit;
}

// =========================
// VALIDACIÓN DE LÍMITE DE CITAS POR TIPO DE PACIENTE
// =========================
log_message("Iniciando validación de límite de citas para paciente ID: $paciente_id");

// 1. Obtener el tipo_id del paciente
$paciente_tipo_id = null;
if ($paciente_id) {
    $stmt_tipo = $conn->prepare("SELECT tipo_id FROM portal_pacientes WHERE id = ?");
    $stmt_tipo->bind_param("i", $paciente_id);
    $stmt_tipo->execute();
    $stmt_tipo->bind_result($paciente_tipo_id_db);
    if ($stmt_tipo->fetch()) {
        $paciente_tipo_id = $paciente_tipo_id_db;
    }
    $stmt_tipo->close();
}

if ($paciente_tipo_id) {
    log_message("Paciente ID: $paciente_id tiene tipo_id: $paciente_tipo_id");
    // 2. Obtener el límite de citas para ese tipo
    $limite_citas = 10000; // Límite muy alto por defecto (sin límite)
    $stmt_limite = $conn->prepare("SELECT limite_citas_diarias FROM agenda_tipos_paciente WHERE id = ?");
    $stmt_limite->bind_param("i", $paciente_tipo_id);
    $stmt_limite->execute();
    $stmt_limite->bind_result($limite_db);
    if ($stmt_limite->fetch()) {
        $limite_citas = (int)$limite_db;
    }
    $stmt_limite->close();
    log_message("El límite de citas para el tipo_id $paciente_tipo_id es: $limite_citas");

    // 3. Contar citas existentes para ese tipo en esa fecha
    $stmt_conteo = $conn->prepare("SELECT COUNT(c.id) FROM agenda_citas c JOIN portal_pacientes p ON c.paciente_id = p.id WHERE c.fecha = ? AND p.tipo_id = ? AND c.estado_id != 7");
    $stmt_conteo->bind_param("si", $fecha, $paciente_tipo_id);
    $stmt_conteo->execute();
    $stmt_conteo->bind_result($citas_existentes);
    $stmt_conteo->fetch();
    $stmt_conteo->close();
    log_message("Citas existentes para el tipo $paciente_tipo_id en la fecha $fecha: $citas_existentes");

    // 4. Comparar y bloquear si es necesario
    if ($citas_existentes >= $limite_citas) {
        log_message("ERROR: Límite de citas ($limite_citas) alcanzado para el tipo_id $paciente_tipo_id en la fecha $fecha.");
        echo json_encode(["success" => false,"error" => "Se ha alcanzado el límite de citas diarias por hoy. Por favor, intente más tarde."]);
        exit;
    }
} else {
    log_message("ADVERTENCIA: No se pudo determinar el tipo_id para el paciente ID: $paciente_id. No se aplicará límite de citas.");
}

// =========================
// PREPARAR DATOS PARA LA CITA
// =========================
$hora_inicio = $hora . ":00";

    // 1. Obtener la duración del servicio que se quiere agendar
    $duracion_servicio_actual = 30; // Duración por defecto en minutos
    $stmt_duracion = $conn->prepare("SELECT duracion_minutos FROM portal_servicios WHERE id = ?");
    if ($stmt_duracion) {
        $stmt_duracion->bind_param("i", $servicio_id);
        $stmt_duracion->execute();
        $stmt_duracion->bind_result($duracion_db);
        if ($stmt_duracion->fetch()) {
            $duracion_servicio_actual = (int)$duracion_db;
        }
        $stmt_duracion->close();
    } else {
        throw new Exception("Error al preparar la consulta de duración: " . $conn->error);
    }

$hora_fin = date("H:i:s", strtotime($hora_inicio) + ($duracion_servicio_actual * 60));
log_message("Calculando hora_fin. Inicio: $hora_inicio, Duración: $duracion_servicio_actual min, Fin: $hora_fin");

$estado_id = 1; // 1 = Reservado

// DETERMINAR PROFESIONAL: Prioridad al usuario autenticado si es dentista/médico, 
// de lo contrario al dueño del servicio o clínica.
$usuario_tipo_auth = $_SESSION['usuario_tipo'] ?? null;
$usuario_id_auth = $_SESSION['usuario_id'] ?? null;
$profesional_id = (in_array($usuario_tipo_auth, ['dentista', 'medico']) && $usuario_id_auth) 
    ? $usuario_id_auth 
    : ($usuario_propietario_id ?? 1);

// Si es paquete y no tiene servicio_id, buscar uno
if ($tipo_reserva === 'paquete' && empty($servicio_id)) {
    $stmt_servicio = $conn->prepare("SELECT id FROM portal_servicios WHERE nombre LIKE '%paquete%' OR nombre LIKE '%integral%' LIMIT 1");
    if ($stmt_servicio) {
        $stmt_servicio->execute();
        $stmt_servicio->bind_result($servicio_id_temp);
        $stmt_servicio->fetch();
        if ($servicio_id_temp) $servicio_id = $servicio_id_temp;
        $stmt_servicio->close();
    }
}

if (empty($modalidad_id)) $modalidad_id = 1; // Fallback
if (empty($servicio_id)) $servicio_id = 1; // Fallback

// =========================
// VERIFICAR DISPONIBILIDAD
// =========================
$total_empalme = 0;
// Se verifica que no haya ninguna otra cita (que no esté cancelada, estado_id != 7)
// que se solape con el horario solicitado.
$stmt_empalme = $conn->prepare(
    "SELECT COUNT(*) as total FROM agenda_citas 
     WHERE fecha = ? AND (modalidad_id = ? OR profesional_id = ?) AND estado_id != 7 AND hora_inicio < ? AND hora_fin > ?"
);

if ($stmt_empalme) {
    $stmt_empalme->bind_param("siiss", $fecha, $modalidad_id, $profesional_id, $hora_fin, $hora_inicio);
    $stmt_empalme->execute();
    $stmt_empalme->bind_result($total_empalme);
    $stmt_empalme->fetch();
    $stmt_empalme->close();
}

if ($total_empalme > 0) {
    echo json_encode([
        "success" => false,
        "error" => "Ya existe una cita en ese horario para la modalidad seleccionada"
    ]);
    log_message("ERROR: Conflicto de horario detectado. Total de empalmes: " . $total_empalme);
    exit;
}

// =========================
// CREAR CITA EN agenda_citas
// =========================
$nota_interna = "Reserva web - Cliente: " . $nombre_completo . " | Email: " . $email . ($portal_usuario_id ? " | Portal ID: " . $portal_usuario_id : "");
$tipo_cita = ($tipo_reserva === 'paquete') ? 'paquete' : 'individual';

$stmt_cita = $conn->prepare("
    INSERT INTO agenda_citas 
    (usuario_id, fecha, hora_inicio, hora_fin, paciente_id, profesional_id, servicio_id, modalidad_id, estado_id, nota_paciente, nota_interna, tipo, url_identificacion, url_orden_medica, atencion_especial)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt_cita) {
    throw new Exception("Error al preparar la inserción de la cita: " . $conn->error);
}

$stmt_cita->bind_param("isssiiiissssssi", 
    $usuario_propietario_id,
    $fecha, 
    $hora_inicio, 
    $hora_fin, 
    $paciente_id,  // 🔹 ESTE es el ID REAL de portal_pacientes (1 en tu ejemplo)
    $profesional_id, 
    $servicio_id, 
    $modalidad_id, 
    $estado_id, 
    $observaciones, 
    $nota_interna, 
    $tipo_cita,
    $url_identificacion,
    $url_orden_medica,
    $atencion_especial // Añadimos el nuevo parámetro
);

if (!$stmt_cita->execute()) {
    echo json_encode([
        "success" => false,
        "error" => "Error al crear la cita",
        "db_error" => $stmt_cita->error
    ]);
    log_message("ERROR: Fallo al ejecutar la inserción de la cita: " . $stmt_cita->error);
    exit;
}

$cita_id = $stmt_cita->insert_id;
$stmt_cita->close();

// Sincronización con Apple Calendar
try {
    require_once __DIR__ . '/includes/icloud_functions.php';
    syncCitaToAppleCalendar($conn, $cita_id);
} catch (Throwable $e) {
    log_message("ERROR: Fallo al sincronizar cita $cita_id con Apple Calendar: " . $e->getMessage());
}

// =========================
// RESPUESTA FINAL
// =========================
$base_redirect_url = 'cliente.php';
$redirect_url = $base_redirect_url;

if (!empty($portal_usuario_id)) {
    $redirect_url .= '?id=' . urlencode($portal_usuario_id);
}

echo json_encode([
    "success" => true,
    "message" => "Reserva creada exitosamente. Redirigiendo...",
    "redirect_url" => $redirect_url
]);

log_message("--- FIN DE RESERVA EXITOSA (Cita ID: $cita_id) ---");

} catch (Throwable $e) {
    http_response_code(500);
    log_message("CRITICAL ERROR: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
exit;