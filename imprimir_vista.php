<?php
require_once 'includes/db.php';

$modalidad = $_GET['modalidad'] ?? 'all';
$fecha = $_GET['fecha'] ?? date('Y-m-d');

// Build query
$params = [];
$where = "WHERE fecha = ?";
$params[] = $fecha;

afterTrim:
if ($modalidad !== 'all') {
    $where .= " AND (c.modalidad_id = ? OR s.modalidad_id = ?)";
    $params[] = $modalidad;
    $params[] = $modalidad;
}

$sql = "SELECT c.id, c.hora_inicio, c.hora_fin,
           p.nombre AS paciente_nombre, p.apellido AS paciente_apellido, p.telefono AS paciente_telefono,
           p.diagnostico AS paciente_diagnostico, p.tipo AS paciente_tipo, p.origen AS paciente_origen,
           c.nota_paciente AS comentario_cita,
           s.nombre AS servicio, ec.nombre AS estado
    FROM agenda_citas c
    LEFT JOIN portal_pacientes p ON c.paciente_id = p.id
    LEFT JOIN portal_servicios s ON c.servicio_id = s.id
    LEFT JOIN agenda_modalidades m ON c.modalidad_id = m.id
    LEFT JOIN agenda_estado_cita ec ON c.estado_id = ec.id
    $where
    ORDER BY c.hora_inicio ASC";

// Prepare and execute in a mysqlnd-safe way
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Error en la consulta: ' . $conn->error);
}

// Bind params dynamically
$types = '';
foreach ($params as $p) {
    $types .= is_int($p) ? 'i' : 's';
}
if ($types) {
    // bind_param requires variables
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

$stmt->execute();
$stmt->store_result();

$rows = [];
if ($stmt->num_rows > 0) {
    // Bind result columns (mysqlnd-safe)
    $stmt->bind_result($col_id, $col_hora_inicio, $col_hora_fin, $col_nombre, $col_apellido, $col_telefono, $col_diagnostico, $col_paciente_tipo, $col_origen, $col_comentario_cita, $col_servicio, $col_estado);
    while ($stmt->fetch()) {
        $rows[] = [
            'id' => $col_id,
            'hora_inicio' => $col_hora_inicio,
            'hora_fin' => $col_hora_fin,
            'paciente' => trim(($col_nombre ?? '') . ' ' . ($col_apellido ?? '')),
            'telefono' => $col_telefono,
            'diagnostico' => $col_diagnostico,
            'tipo' => $col_paciente_tipo,
            'origen' => $col_origen,
            'comentarios' => $col_comentario_cita,
            'servicio' => $col_servicio,
            'estado' => $col_estado
        ];
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Impresión - <?= htmlspecialchars($fecha) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f4f4f4; }
    </style>
</head>
<body>
    <h2>Agenda - <?= htmlspecialchars($fecha) ?></h2>
    <p>Modalidad: <?= $modalidad === 'all' ? 'Todas' : htmlspecialchars($modalidad) ?></p>
    <table>
        <thead>
            <tr>
                <th>Hora</th>
                <th>Cliente</th>
                <th>Teléfono</th>
                <th>Diagnóstico / Motivo</th>
                <th>Tipo de paciente</th>
                <th>Comentarios adicionales</th>
                <th>Origen</th>
                <th>Urgencias</th>
                <th>Servicio</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($rows) === 0): ?>
                <tr><td colspan="10">No hay citas para esta fecha y modalidad</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <?php
                                $h1 = $row['hora_inicio'] ? date('H:i', strtotime($row['hora_inicio'])) : '';
                                $h2 = $row['hora_fin'] ? date('H:i', strtotime($row['hora_fin'])) : '';
                                echo htmlspecialchars(trim($h1 . ($h2 ? ' - ' . $h2 : '')));
                            ?>
                        </td>
                        <td><?= htmlspecialchars($row['paciente'] ?: '---') ?></td>
                        <td><?= htmlspecialchars($row['telefono'] ?: '---') ?></td>
                        <td><?= htmlspecialchars($row['diagnostico'] ?: '---') ?></td>
                        <td><?= htmlspecialchars($row['tipo'] ?: '---') ?></td>
                        <td><?= htmlspecialchars($row['comentarios'] ?: $row['comentarios'] ?: '---') ?></td>
                        <td><?= htmlspecialchars($row['origen'] ?: '---') ?></td>
                        <td><?= htmlspecialchars(strtolower(trim($row['origen'])) === 'urgencias' ? 'Sí' : '') ?></td>
                        <td><?= htmlspecialchars($row['servicio'] ?: '---') ?></td>
                        <td><?= htmlspecialchars($row['estado'] ?: '---') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        // Trigger print automatically when loaded
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
