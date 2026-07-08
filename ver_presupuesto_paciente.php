<?php
require_once 'includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id === 0) die("Acceso no válido.");

// Obtener datos
$stmt = $conn->prepare("
    SELECT p.nombre, p.apellido_paterno, p.apellido_materno, p.apellido, p.telefono, d.presupuesto_json, d.observaciones, u.nombre as medico_nombre 
    FROM portal_pacientes p 
    LEFT JOIN agenda_expediente_dentista d ON p.id = d.paciente_id 
    LEFT JOIN agenda_usuarios u ON p.usuario_id = u.id
    WHERE p.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

if (!$res || !$res['presupuesto_json']) die("Presupuesto no disponible.");

$ap_p = $res['apellido_paterno'] ?: $res['apellido'];
$ap_m = $res['apellido_materno'] ?: '';
$nombre_paciente_completo = trim($res['nombre'] . ' ' . $ap_p . ' ' . $ap_m);

$pres = json_decode($res['presupuesto_json'], true);
$items = $pres['items'] ?? [];

// Obtener nombres reales de los servicios desde el catálogo
$nombres_servicios = [];
if (!empty($items)) {
    $ids = array_unique(array_filter(array_column($items, 'servicio_id')));
    if (!empty($ids)) {
        $sql_serv = "SELECT id, nombre FROM portal_servicios WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")";
        $res_serv = $conn->query($sql_serv);
        while ($s = $res_serv->fetch_assoc()) {
            $nombres_servicios[$s['id']] = $s['nombre'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto Dental - <?php echo htmlspecialchars($nombre_paciente_completo); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; color: #000000; font-family: 'Inter', sans-serif; padding: 30px 15px; }
        .budget-container { max-width: 800px; margin: 0 auto; background: #ffffff; border: 1px solid #dee2e6; border-radius: 20px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .clinic-logo { max-height: 80px; width: auto; margin-bottom: 15px; display: block; }
        .header-brand { display: none; }
        .header-sub { color: #2979ff; font-weight: 600; font-size: 14px; text-transform: uppercase; margin-bottom: 25px; }
        .clinic-info { font-size: 13px; color: #000000; line-height: 1.6; margin-bottom: 30px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        .patient-info { margin-bottom: 20px; }
        .patient-label { color: #2979ff; font-weight: 700; font-size: 12px; text-transform: uppercase; margin-bottom: 5px; }
        .table thead th { border-top: none; color: #333; font-size: 12px; text-transform: uppercase; border-bottom: 2px solid #f0f0f0; }
        .table td { border-bottom: 1px solid #f9f9f9; vertical-align: middle; font-size: 14px; color: #000000; }
        .total-row { background: #f8faff; font-weight: 800; color: #000000; }
        .total-amount { color: #2979ff; font-size: 24px; }
        .text-accent { color: #2979ff; }
        .phone-icon { color: #2979ff; margin-right: 5px; }
        .footer-note { margin-top: 40px; font-size: 12px; color: #333; text-align: center; }
        .wpp-icon { color: #25d366; margin-right: 5px; }
        .badge-secondary { background-color: #f0f0f0; color: #000; border: 1px solid #ccc; padding: 5px 10px; }
        .text-muted { color: #333 !important; }

        /* Ajustes para dispositivos móviles */
        @media (max-width: 576px) {
            body { padding: 15px 5px; }
            .budget-container { padding: 25px 15px; border-radius: 12px; }
            .clinic-logo { max-height: 60px; }
            .header-sub { font-size: 11px; }
            .table td, .table th { font-size: 12px; padding: 10px 5px; }
            .total-amount { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="budget-container">
        <div class="row">
            <div class="col-md-7">
                <img src="images/dent.png" class="clinic-logo" alt="Logo Dent">
                <div class="header-sub">Clínica de Especialidades</div>
                <div class="clinic-info">
                    Calle 12a #435 entre Guerrero y Rayon, Zona Centro Cd Cuauhtemoc, Chih.<br>
                    <i class="fas fa-phone phone-icon"></i> <i class="fab fa-whatsapp wpp-icon"></i> Recepción: 625 100 00 22<br>
                    <i class="fas fa-phone phone-icon"></i> <i class="fab fa-whatsapp wpp-icon"></i> Citas Dr Palacios: 625 125 70 48<br>
                    <i class="fas fa-phone phone-icon"></i> <i class="fab fa-whatsapp wpp-icon"></i> Personal: 614 197 27 13
                </div>
            </div>
            <div class="col-md-5 text-md-right mt-3 mt-md-0">
                <div class="patient-info">
                    <div class="patient-label">Paciente</div>
                    <h5 class="mb-0"><?php echo htmlspecialchars($nombre_paciente_completo); ?></h5>
                    <small class="text-muted">Presupuesto: <?php echo date('d/m/Y', strtotime($pres['fecha'])); ?></small>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Diente</th>
                        <th>Tratamiento / Servicio</th>
                        <th class="text-center">Etapa</th>
                        <th class="text-right">Precio</th>
                        <th class="text-right">Descuento</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total = 0;
                    foreach($items as $item): 
                        $base = (float)($item['base'] ?? 0);
                        $descuento = (float)($item['descuento'] ?? 0);
                        $subtotal = $base - $descuento;
                        $total += $subtotal;
                    ?>
                    <tr>
                        <td><span class="badge badge-secondary"><?php echo $item['diente'] ?: '--'; ?></span></td>
                        <td><?php echo htmlspecialchars($nombres_servicios[$item['servicio_id']] ?? 'Tratamiento Dental Especializado'); ?></td>
                        <td class="text-center"><?php echo $item['cita'] ?: '1'; ?></td>
                        <td class="text-right">$<?php echo number_format($base, 2); ?></td>
                        <td class="text-right text-danger"><?php echo $descuento > 0 ? '-$' . number_format($descuento, 2) : '$0.00'; ?></td>
                        <td class="text-right font-weight-bold text-accent">$<?php echo number_format($subtotal, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="5" class="text-right align-middle text-uppercase">Total Estimado</td>
                        <td class="text-right total-amount">$<?php echo number_format($total, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if(isset($res['observaciones']) && $res['observaciones']): ?>
            <div class="mt-4">
                <div class="patient-label">Observaciones</div>
                <p class="small text-muted"><?php echo nl2br(htmlspecialchars($res['observaciones'])); ?></p>
            </div>
        <?php endif; ?>

        <div class="footer-note">
            Este presupuesto es vigente durante todo el presente año, 2026.<br>
            <strong>Dent - Clínica de Especialidades</strong>
        </div>
    </div>
</body>
</html>
