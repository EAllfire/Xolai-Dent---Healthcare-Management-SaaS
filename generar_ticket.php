<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado.");
}

$tipo = $_GET['tipo'] ?? 'cita';
$data = [];

if ($tipo === 'cita') {
    $cita_id = (int)($_GET['cita_id'] ?? 0);
    $sql = "SELECT c.fecha, c.hora_inicio, p.nombre, p.apellido, p.apellido_paterno, p.apellido_materno, s.nombre as servicio, s.precio, u.nombre as doctor
            FROM agenda_citas c
            JOIN portal_pacientes p ON c.paciente_id = p.id
            JOIN portal_servicios s ON c.servicio_id = s.id
            LEFT JOIN agenda_usuarios u ON c.profesional_id = u.id
            WHERE c.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cita_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    
    if (!$res) die("Cita no encontrada.");
    
    $ap_p = $res['apellido_paterno'] ?: $res['apellido'];
    $ap_m = $res['apellido_materno'] ?: '';
    $nombre_paciente = trim($res['nombre'] . ' ' . $ap_p . ' ' . $ap_m);

    $data = [
        'fecha' => $res['fecha'],
        'paciente' => $nombre_paciente,
        'servicio' => $res['servicio'],
        'total' => $res['precio'],
        'metodos' => ['Efectivo' => $res['precio']],
        'pendiente' => 0
    ];
} else {
    $paciente_id = (int)($_GET['paciente_id'] ?? 0);
    $stmt = $conn->prepare("SELECT nombre, apellido, apellido_paterno, apellido_materno FROM portal_pacientes WHERE id = ?");
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    
    $ap_p = $p['apellido_paterno'] ?: $p['apellido'];
    $ap_m = $p['apellido_materno'] ?: '';
    $nombre_paciente = trim(($p['nombre'] ?? '') . ' ' . $ap_p . ' ' . $ap_m);

    $metodos = [];
    if(isset($_GET['efe'])) $metodos['Efectivo'] = (float)$_GET['efe'];
    if(isset($_GET['tcred'])) $metodos['Tarj. Crédito'] = (float)$_GET['tcred'];
    if(isset($_GET['tdeb'])) $metodos['Tarj. Débito'] = (float)$_GET['tdeb'];
    if(isset($_GET['tra'])) $metodos['Transferencia'] = (float)$_GET['tra'];
    if(isset($_GET['dlls'])) {
        $rate = (float)($_GET['rate'] ?? 20);
        $dlls = (float)$_GET['dlls'];
        $metodos['Dólares'] = $dlls * $rate;
    }

    $data = [
        'fecha' => $_GET['fecha'] ?? date('Y-m-d'),
        'paciente' => $nombre_paciente,
        'servicio' => 'ABONO A CUENTA',
        'total' => $_GET['total'] ?? 0,
        'metodos' => $metodos,
        'pendiente' => $_GET['pendiente'] ?? 0
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket de Pago</title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 300px; margin: 0 auto; padding: 20px; color: #000; }
        .text-center { text-align: center; }
        .header { border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 15px; }
        .brand { font-size: 20px; font-weight: bold; }
        .item { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 14px; }
        .total { border-top: 1px dashed #000; padding-top: 10px; margin-top: 10px; font-weight: bold; font-size: 16px; }
        .footer { margin-top: 20px; font-size: 12px; border-top: 1px dashed #000; padding-top: 10px; }
        @media print { .no-print { display: none; } }
        .btn-print { background: #000; color: #fff; border: none; padding: 10px; width: 100%; cursor: pointer; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">IMPRIMIR TICKET</button>
    </div>
    <div class="header text-center">
        <div class="brand">DENT</div>
        <div>Clínica de Especialidades Dentales</div>
        <div style="font-size: 12px;">Comprobante de Pago</div>
    </div>
    
    <div class="item">
        <span>Fecha:</span>
        <span><?php echo date('d/m/Y', strtotime($data['fecha'])); ?></span>
    </div>
    <div class="item">
        <span>Paciente:</span>
        <span style="text-align: right;"><?php echo htmlspecialchars($data['paciente']); ?></span>
    </div>
    <div class="item" style="margin-top: 10px; border-bottom: 1px dashed #000; padding-bottom: 5px;">
        <span>Concepto:</span>
        <span style="text-align: right;"><?php echo htmlspecialchars($data['servicio']); ?></span>
    </div>

    <?php foreach($data['metodos'] as $label => $monto): if($monto > 0 || ($label === 'Descuento' && $monto > 0)): ?>
    <div class="item">
        <span><?php echo $label; ?>:</span>
        <span>$<?php echo number_format($monto, 2); ?></span>
    </div>
    <?php endif; endforeach; ?>
    
    <div class="total item">
        <span>TOTAL ABONO:</span>
        <span>$<?php echo number_format($data['total'], 2); ?></span>
    </div>

    <div class="item" style="margin-top: 10px; font-weight: bold; border-top: 1px solid #000; padding-top: 5px;">
        <span>SALDO PENDIENTE:</span>
        <span>$<?php echo number_format($data['pendiente'], 2); ?></span>
    </div>
    
    <div class="footer text-center">
        Gracias por su confianza.<br>
        Conserve este ticket para cualquier aclaración.
    </div>
</body>
</html>