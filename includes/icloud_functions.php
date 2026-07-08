<?php
/**
 * Motor de Sincronización Xolai -> Apple Calendar (iCloud)
 * ✅ Versión FINAL ESTABLE (CalDAV real funcionando)
 */

function syncCitaToAppleCalendar($conn, $cita_id) {

    // 🔍 Obtener datos
    $query = "SELECT c.*, u.icloud_email, u.icloud_app_password, u.icloud_sync_enabled, u.icloud_calendar_name, u.icloud_calendar_href,
                     p.nombre as p_nombre, p.apellido as p_apellido, p.telefono as p_telefono,
                     s.nombre as s_nombre,
                     c.paciente_nombre_text, c.telefono_celular, c.servicio_text
              FROM agenda_citas c
              JOIN agenda_usuarios u ON (CASE WHEN c.profesional_id IS NOT NULL AND c.profesional_id > 0 THEN c.profesional_id ELSE c.usuario_id END) = u.id
              LEFT JOIN portal_pacientes p ON c.paciente_id = p.id
              LEFT JOIN portal_servicios s ON c.servicio_id = s.id
              WHERE c.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $cita_id);
    $stmt->execute();
    $cita = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cita) return false;

    if ($cita['icloud_sync_enabled'] != 1 || empty($cita['icloud_email']) || empty($cita['icloud_app_password'])) {
        return false;
    }

    $email = trim($cita['icloud_email']);
    $password = trim($cita['icloud_app_password']);
    $auth = base64_encode("$email:$password");

    error_log("[iCloud] Sync iniciando para $email (Cita $cita_id)");

    // 🗓️ Crear evento
    $uid = $cita['apple_event_id'] ?: (uniqid() . "-$cita_id@xolai.local");

    $nombrePaciente = trim(implode(' ', array_filter([
        $cita['p_nombre'] ?? '',
        $cita['p_apellido'] ?? ''
    ])));
    if (empty($nombrePaciente) && !empty($cita['paciente_nombre_text'])) {
        $nombrePaciente = trim($cita['paciente_nombre_text']);
    }
    if (empty($nombrePaciente)) {
        $nombrePaciente = 'Paciente sin nombre';
    }

    $telefono = trim($cita['telefono_celular'] ?: $cita['p_telefono'] ?? '');
    $tratamiento = trim($cita['s_nombre'] ?: $cita['servicio_text'] ?? '');

    $summaryParts = [$nombrePaciente];
    if ($telefono !== '') {
        $summaryParts[] = $telefono;
    }
    if ($tratamiento !== '') {
        $summaryParts[] = $tratamiento;
    }
    $summary = implode(' - ', $summaryParts);
    if (empty($summary)) {
        $summary = 'Cita Xolai';
    }

    $description = "Servicio: " . ($tratamiento ?: 'No especificado') . "\\nNotas: " . ($cita['nota_paciente'] ?? '');

    try {
        $localTz = new DateTimeZone('America/Mexico_City');
        $utcTz = new DateTimeZone('UTC');

        $startDt = new DateTime($cita['fecha'] . ' ' . $cita['hora_inicio'], $localTz);
        $endDt = new DateTime($cita['fecha'] . ' ' . $cita['hora_fin'], $localTz);
        $startDt->setTimezone($utcTz);
        $endDt->setTimezone($utcTz);

        $dtStart = $startDt->format('Ymd\THis\Z');
        $dtEnd = $endDt->format('Ymd\THis\Z');
        $dtStamp = (new DateTime('now', $utcTz))->format('Ymd\THis\Z');
    } catch (Exception $e) {
        error_log('[iCloud] ERROR generando fechas UTC: ' . $e->getMessage());
        $dtStart = gmdate('Ymd\THis\Z', strtotime($cita['fecha'] . ' ' . $cita['hora_inicio']));
        $dtEnd = gmdate('Ymd\THis\Z', strtotime($cita['fecha'] . ' ' . $cita['hora_fin']));
        $dtStamp = gmdate('Ymd\THis\Z');
    }

    $vcalendar = "BEGIN:VCALENDAR\r\n" .
                 "VERSION:2.0\r\n" .
                 "PRODID:-//Xolai//Agenda//ES\r\n" .
                 "BEGIN:VEVENT\r\n" .
                 "UID:$uid\r\n" .
                 "DTSTAMP:$dtStamp\r\n" .
                 "DTSTART:$dtStart\r\n" .
                 "DTEND:$dtEnd\r\n" .
                 "SUMMARY:$summary\r\n" .
                 "DESCRIPTION:$description\r\n" .
                 "END:VEVENT\r\n" .
                 "END:VCALENDAR";

    // 🔍 Descubrir base real
    $calendar_base = discoverICloudCalendarUrl($auth);

    if (!$calendar_base) {
        error_log("[iCloud] ERROR: No se pudo descubrir calendar-home-set");
        return false;
    }

    // 📅 Calendario principal
    $calendar_real = discoverCalendarCollection(
        $auth,
        $calendar_base,
        $cita['icloud_calendar_name'] ?? null,
        $cita['icloud_calendar_href'] ?? null
    );

    if (!$calendar_real) {
        error_log("[iCloud] ERROR: No se encontró calendario válido");
        return false;
    }

    $event_url = $calendar_real . $uid . ".ics";

    error_log("[iCloud] EVENT URL FINAL: $event_url");

    // 📤 Enviar evento
    $ch = curl_init($event_url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_POSTFIELDS => $vcalendar,
        CURLOPT_HTTPHEADER => [
            "Content-Type: text/calendar; charset=utf-8",
            "Authorization: Basic $auth",
            "User-Agent: Mozilla/5.0 (Xolai CalDAV)",
            "Content-Length: " . strlen($vcalendar),
            "Expect:"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);

    curl_close($ch);

    if ($curl_error) {
        error_log("[iCloud] CURL ERROR PUT: $curl_error");
    }

    if ($http_code == 201 || $http_code == 204) {

        if (empty($cita['apple_event_id'])) {
            $upd = $conn->prepare("UPDATE agenda_citas SET apple_event_id = ? WHERE id = ?");
            $upd->bind_param("si", $uid, $cita_id);
            $upd->execute();
            $upd->close();
        }

        error_log("[iCloud] ✅ Evento creado correctamente");
        return true;

    } else {
        error_log("[iCloud] ❌ ERROR PUT HTTP $http_code");
        error_log("[iCloud] RESPUESTA: $response");
        return false;
    }
}


/**
 * 🔥 Descubrir servidor REAL de iCloud (pXX) + user_id
 */
function discoverICloudCalendarUrl($auth) {

    $ch = curl_init("https://caldav.icloud.com/");

    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <D:propfind xmlns:D="DAV:">
        <D:prop>
            <D:current-user-principal/>
        </D:prop>
    </D:propfind>';

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "PROPFIND",
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => [
            "Depth: 0",
            "Content-Type: application/xml; charset=utf-8",
            "Authorization: Basic $auth",
            "User-Agent: Mozilla/5.0",
            "Accept: */*"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
    ]);

    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error) {
        error_log("[iCloud] CURL ERROR: $error");
        return null;
    }

    if ($http != 207 || empty($response)) {
        error_log("[iCloud] ERROR HTTP o respuesta vacía: $http");
        error_log("[iCloud] RAW: " . $response);
        return null;
    }

    // 🔥 PARSE XML SEGURO
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadXML($response);

    $hrefs = $doc->getElementsByTagName("href");

    foreach ($hrefs as $href) {
        $value = $href->nodeValue;

        if (strpos($value, '/principal/') !== false) {
            preg_match('/\/([0-9]+)\//', $value, $m);
            $user_id = $m[1];

            $calendar_base = "https://caldav.icloud.com/$user_id/calendars/";

            error_log("[iCloud] Calendar base: $calendar_base");

            return $calendar_base;
        }
    }

    error_log("[iCloud] ERROR: No se encontró principal en XML");
    error_log("[iCloud] RAW XML: " . $response);

    return null;
}
function discoverCalendarCollection($auth, $calendar_base, $targetCalendarName = null, $targetCalendarHref = null) {

    $xml = '<?xml version="1.0" encoding="UTF-8"?>
    <D:propfind xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
        <D:prop>
            <D:resourcetype/>
            <D:displayname/>
        </D:prop>
    </D:propfind>';

    $ch = curl_init($calendar_base);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "PROPFIND",
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => [
            "Depth: 1",
            "Content-Type: application/xml",
            "Authorization: Basic $auth",
            "User-Agent: Mozilla/5.0"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
    ]);

    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($http != 207) {
        error_log("[iCloud] ERROR list calendars HTTP: $http");
        return null;
    }

    // 🔥 PARSE XML
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadXML($response);

    $responses = $doc->getElementsByTagName("response");
    $fallbackCalendar = null;
    $targetCalendarName = trim((string)$targetCalendarName);
    $targetCalendarHref = trim((string)$targetCalendarHref);

    foreach ($responses as $res) {

        $href = "";
        $displayName = "";
        $isCalendar = false;

        foreach ($res->getElementsByTagName("href") as $h) {
            $href = $h->nodeValue;
        }

        foreach ($res->getElementsByTagName("displayname") as $d) {
            $displayName = trim($d->nodeValue);
        }

        foreach ($res->getElementsByTagName("resourcetype") as $rt) {
            if ($rt->getElementsByTagName("calendar")->length > 0) {
                $isCalendar = true;
            }
        }

        if ($isCalendar && !empty($href)) {
            if (strpos($href, 'http') === 0) {
                $resolved = rtrim($href, '/') . '/';
            } else {
                $resolved = rtrim("https://caldav.icloud.com" . $href, '/') . '/';
            }

            if ($targetCalendarHref !== '' && stripos($resolved, $targetCalendarHref) !== false) {
                error_log("[iCloud] Calendario encontrado por href: $resolved");
                return $resolved;
            }

            if ($targetCalendarName !== '' && $displayName !== '' && stripos(mb_strtolower($displayName), mb_strtolower($targetCalendarName)) !== false) {
                error_log("[iCloud] Calendario encontrado por nombre: $displayName ($resolved)");
                return $resolved;
            }

            if ($fallbackCalendar === null) {
                $fallbackCalendar = $resolved;
            }
        }
    }

    if ($targetCalendarName !== '' || $targetCalendarHref !== '') {
        error_log("[iCloud] No se encontró calendario específico para nombre '$targetCalendarName' href '$targetCalendarHref'. Usando primer calendario disponible.");
    }

    if ($fallbackCalendar) {
        return $fallbackCalendar;
    }

    error_log("[iCloud] ❌ No se encontró calendario real");
    return null;
}

/**
 * 📥 Obtener eventos de iCloud en un rango de fechas
 */
function fetchICloudEvents($auth, $calendar_url, $start_date, $end_date) {
    // Formato ISO para CalDAV: YYYYMMDDTHHMMSSZ
    $start = date('Ymd\THis\Z', strtotime($start_date));
    $end   = date('Ymd\THis\Z', strtotime($end_date));

    $xml = '<?xml version="1.0" encoding="utf-8" ?>
    <C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
      <D:prop>
        <D:getetag/>
        <C:calendar-data/>
      </D:prop>
      <C:filter>
        <C:comp-filter name="VCALENDAR">
          <C:comp-filter name="VEVENT">
            <C:time-range start="' . $start . '" end="' . $end . '"/>
          </C:comp-filter>
        </C:comp-filter>
      </C:filter>
    </C:calendar-query>';

    $ch = curl_init($calendar_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "REPORT",
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => [
            "Depth: 1",
            "Content-Type: application/xml; charset=utf-8",
            "Authorization: Basic $auth",
            "User-Agent: Mozilla/5.0"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http != 207) return [];

    $events = [];
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    if ($doc->loadXML($response)) {
        $responses = $doc->getElementsByTagName("response");
        foreach ($responses as $res) {
            $data = $res->getElementsByTagName("calendar-data")->item(0)->nodeValue ?? '';
            if ($data) {
                $events[] = [
                    'href' => $res->getElementsByTagName("href")->item(0)->nodeValue,
                    'data' => $data
                ];
            }
        }
    }
    return $events;
}

/**
 * 🗑️ Eliminar evento de Apple Calendar (iCloud)
 */
function deleteCitaFromAppleCalendar($conn, $cita_id) {
    // 🔍 Obtener datos
    $query = "SELECT c.apple_event_id, u.icloud_email, u.icloud_app_password, u.icloud_sync_enabled, u.icloud_calendar_name, u.icloud_calendar_href
              FROM agenda_citas c
              JOIN agenda_usuarios u ON (CASE WHEN c.profesional_id IS NOT NULL AND c.profesional_id > 0 THEN c.profesional_id ELSE c.usuario_id END) = u.id
              WHERE c.id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("[iCloud] ERROR DELETE: Prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param("i", $cita_id);
    $stmt->execute();
    $cita = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cita || empty($cita['apple_event_id'])) {
        return false;
    }

    if ($cita['icloud_sync_enabled'] != 1 || empty($cita['icloud_email']) || empty($cita['icloud_app_password'])) {
        return false;
    }

    $email = trim($cita['icloud_email']);
    $password = trim($cita['icloud_app_password']);
    $auth = base64_encode("$email:$password");

    error_log("[iCloud] Sync DELETE iniciando para $email (Cita $cita_id, Evento {$cita['apple_event_id']})");

    // 🔍 Descubrir base real
    $calendar_base = discoverICloudCalendarUrl($auth);
    if (!$calendar_base) {
        error_log("[iCloud] ERROR DELETE: No se pudo descubrir calendar-home-set");
        return false;
    }

    // 📅 Calendario principal
    $calendar_real = discoverCalendarCollection(
        $auth,
        $calendar_base,
        $cita['icloud_calendar_name'] ?? null,
        $cita['icloud_calendar_href'] ?? null
    );

    if (!$calendar_real) {
        error_log("[iCloud] ERROR DELETE: No se encontró calendario válido");
        return false;
    }

    $event_url = $calendar_real . $cita['apple_event_id'] . ".ics";
    error_log("[iCloud] DELETE EVENT URL: $event_url");

    // 📤 Enviar DELETE
    $ch = curl_init($event_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "DELETE",
        CURLOPT_HTTPHEADER => [
            "Authorization: Basic $auth",
            "User-Agent: Mozilla/5.0 (Xolai CalDAV)"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("[iCloud] CURL ERROR DELETE: $curl_error");
    }

    if ($http_code == 200 || $http_code == 204) {
        error_log("[iCloud] ✅ Evento eliminado correctamente de iCloud");
        return true;
    } else {
        error_log("[iCloud] ❌ ERROR DELETE HTTP $http_code en iCloud. Respuesta: $response");
        return false;
    }
}
?>