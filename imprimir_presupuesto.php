<?php
session_start();
require_once 'includes/db.php';

$id = (int)($_GET['id'] ?? 0);

// Obtener datos completos
$sql = "SELECT p.nombre, p.apellido_paterno, p.apellido_materno, p.apellido, p.telefono, d.presupuesto_json, d.observaciones, u.nombre as medico_nombre 
        FROM portal_pacientes p 
        LEFT JOIN agenda_expediente_dentista d ON p.id = d.paciente_id 
        LEFT JOIN agenda_usuarios u ON p.usuario_id = u.id
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();

// Fallback para el nombre del paciente con los nuevos campos de apellidos
$ap_p = $res['apellido_paterno'] ?: $res['apellido'];
$ap_m = $res['apellido_materno'] ?: '';
$nombre_paciente_completo = trim($res['nombre'] . ' ' . $ap_p . ' ' . $ap_m);

$pres = json_decode($res['presupuesto_json'] ?? '{}', true);
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
<html>
<head>
    <meta charset="UTF-8">
    <title>Presupuesto_<?php echo htmlspecialchars($ap_p); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Modificaciones para mejorar la visualización en Adultos Mayores */
        body { 
            background-color: #f8f9fa; 
            color: #000000; 
            font-family: 'Inter', sans-serif; 
            padding: 40px; 
            font-size: 18px; /* Texto base más grande y legible */
        }
        .budget-container { 
            max-width: 900px; 
            margin: 0 auto; 
            background: #ffffff; 
            border: 1px solid #dee2e6; 
            border-radius: 20px; 
            padding: 50px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
        }
        .clinic-logo { max-height: 90px; width: auto; margin-bottom: 15px; display: block; }
        .header-sub { color: #1a73e8; font-weight: 700; font-size: 18px; text-transform: uppercase; margin-bottom: 20px; }
        .clinic-info { font-size: 16px; color: #222222; line-height: 1.7; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 25px; }
        
        .patient-label { color: #1a73e8; font-weight: 800; font-size: 14px; text-transform: uppercase; margin-bottom: 5px; }
        .patient-name { font-size: 24px; font-weight: 700; color: #000000; }
        .patient-details { font-size: 18px; color: #333333; }

        /* Ajustes de centrado y reducción de ancho horizontal de la tabla */
        .table-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
            margin: 40px 0;
        }
        .budget-table { 
            width: 85%; /* No extendida, más estrecha para evitar fatiga visual */
            margin: 0 auto; 
            border-collapse: collapse; 
            font-size: 18px; /* Números y letras grandes */
        }
        .budget-table th { 
            background-color: #1a73e8;
            color: #ffffff;
            text-align: left; 
            font-size: 15px; 
            text-transform: uppercase; 
            font-weight: 700;
            padding: 14px; 
            border: 1px solid #1a73e8;
        }
        .budget-table td { 
            padding: 16px 14px; /* Espaciado interno generoso */
            border: 1px solid #e0e0e0; 
            color: #000000; 
        }
        .budget-table tbody tr:nth-child(even) {
            background-color: #f9f9f9; /* Filas alternas con un contraste sutil */
        }
        
        .total-row { background: #f1f6fe !important; font-weight: 800; color: #000000; }
        .total-row td { border-top: 2px solid #1a73e8; border-bottom: 2px solid #1a73e8; }
        .total-amount { color: #1a73e8; font-size: 26px; font-weight: 800; }
        
        .footer-note { margin-top: 50px; font-size: 16px; color: #333; text-align: center; border-top: 1px solid #eee; padding-top: 20px; }
        .no-print { margin-bottom: 30px; text-align: center; }
        .btn-print { padding: 15px 35px; background: #1a73e8; color: white; border: none; border-radius: 12px; font-size: 18px; font-weight: 700; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 10px rgba(26,115,232,0.2); }
        .btn-print:hover { background: #1557b0; transform: scale(1.03); }
        
        .wpp-icon { color: #25d366; }
        .phone-icon { color: #1a73e8; }

        @media print { 
            body { background-color: white !important; color: black !important; padding: 0; font-size: 16pt; }
            .budget-container { border: none; box-shadow: none; width: 100%; max-width: none; padding: 0; }
            .no-print { display: none; }
            .budget-table { width: 90%; } /* Ajuste de proporción óptimo en papel impreso */
            .budget-table th { background-color: #1a73e8 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .total-row { background: #f1f6fe !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Imprimir Presupuesto (Letra Grande)</button>
    </div>

    <div class="budget-container">
        <div class="row">
            <div class="col-7">
                <img src="images/dent.png" class="clinic-logo" alt="Logo Dent">
                <div class="header-sub">Clínica de Especialidades</div>
                <div class="clinic-info">
                    Calle 12a #435 entre Guerrero y Rayon, Zona Centro<br>
                    Cd Cuauhtemoc, Chih.<br>
                    <i class="fas fa-phone phone-icon"></i> <i class="fab fa-whatsapp wpp-icon"></i> Recepción: 625 100 0022<br>
                    <i class="fas fa-phone phone-icon"></i> <i class="fab fa-whatsapp wpp-icon"></i> Citas Dr Palacios: 625 125 7048<br>
                    <i class="fas fa-phone phone-icon"></i> <i class="fab fa-whatsapp wpp-icon"></i> Personal: 614 197 2713
                </div>
            </div>
            <div class="col-5 text-right">
                <div class="mb-4">
                    <div class="patient-label">Paciente</div>
                    <div class="patient-name"><?php echo htmlspecialchars($nombre_paciente_completo); ?></div>
                    <div class="patient-details">Tel: <?php echo htmlspecialchars($res['telefono']); ?></div>
                </div>
                <div>
                    <div class="patient-label">Fecha del Presupuesto</div>
                    <div class="patient-details" style="font-weight: 600;"><?php echo date('d/m/Y', strtotime($pres['fecha'])); ?></div>
                </div>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="budget-table">
                <thead>
                    <tr>
                        <th style="width: 15%;" class="text-center">Diente</th>
                        <th style="width: 55%;">Tratamiento / Concepto</th>
                        <th style="width: 15%;" class="text-center">Etapa</th>
                        <th style="width: 15%;" class="text-right">Total</th>
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
                        <td class="text-center" style="font-weight: 600;"><?php echo htmlspecialchars($item['diente'] ?: '--'); ?></td>
                        <td><?php echo htmlspecialchars($nombres_servicios[$item['servicio_id']] ?? 'Tratamiento Dental Especializado'); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($item['cita'] ?: '1'); ?></td>
                        <td class="text-right font-weight-bold">$<?php echo number_format($subtotal, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" class="text-right text-uppercase align-middle" style="font-size: 16px; letter-spacing: 0.5px;">Total Estimado</td>
                        <td class="text-right total-amount">$<?php echo number_format($total, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="footer-note">
            Este presupuesto es vigente durante todo el presente año 2026.<br>
            <strong>Dent - Clínica de Especialidades</strong>
        </div>
    </div>
</body>
</html>