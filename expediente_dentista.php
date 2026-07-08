<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!puedeRealizar('gestionar_pacientes') || !in_array($_SESSION['usuario_tipo'], ['dentista', 'dentista_externo', 'admin', 'recepcion'])) {
    header('Location: index.php');
    exit;
}

$paciente_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($paciente_id === 0) {
    die("ID de paciente no válido.");
}

// Obtener datos básicos del paciente
$stmt = $conn->prepare("SELECT nombre, apellido_paterno, apellido_materno, usuario_id FROM portal_pacientes WHERE id = ?");
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$result = $stmt->get_result();
$paciente = $result->fetch_assoc();
if (!$paciente) {
    die("Paciente no encontrado.");
}
$patient_owner = $paciente['usuario_id'] ?? null;
// Verificar owner-scope
$allowed = obtenerIdsPermitidos();
if ($allowed !== null) {
    if (is_array($allowed) && in_array('PARENT_ONLY', $allowed)) {
        $parent = $_SESSION['id_padre'] ?? null;
        if (!$parent || $patient_owner != $parent) { header('Location: index.php'); exit; }
    } elseif (is_array($allowed) && in_array('SELF_AND_CHILDREN', $allowed)) {
        $self = $_SESSION['usuario_id'] ?? 0;
        if ($patient_owner != $self) {
            $stmt_ch = $conn->prepare("SELECT COUNT(*) as cnt FROM agenda_usuarios WHERE id = ? AND id_padre = ?");
            $stmt_ch->bind_param('ii', $patient_owner, $self);
            $stmt_ch->execute(); $res_ch = $stmt_ch->get_result(); $rch = $res_ch->fetch_assoc(); $stmt_ch->close();
            if ((int)$rch['cnt'] === 0) { header('Location: index.php'); exit; }
        }
    } elseif (is_array($allowed) && count($allowed)>0) {
        if (!in_array((int)$patient_owner, array_map('intval', $allowed))) { header('Location: index.php'); exit; }
    }
}
$nombre_completo = trim($paciente['nombre'] . ' ' . ($paciente['apellido_paterno'] ?? '') . ' ' . ($paciente['apellido_materno'] ?? ''));
$seed = urlencode(($paciente['nombre'] ?? '') . ($paciente['apellido'] ?? ''));
$avatar_url = "https://api.dicebear.com/7.x/identicon/svg?seed={$seed}&backgroundColor=transparent";
$usuario_nombre_actual = $_SESSION['usuario_nombre'] ?? 'Médico';
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expediente Dental - <?php echo htmlspecialchars($nombre_completo); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #000000; color: #e5e7eb; padding-top: 100px; }
        .main-header { background: rgba(5, 5, 5, 0.95); backdrop-filter: blur(10px); color: white; height: 80px; display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom-left-radius: 20px; border-bottom-right-radius: 20px; position: fixed; top: 0; left: 0; right: 0; z-index: 1050; box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5); border-bottom: 1px solid rgba(41, 121, 255, 0.1); }
        .header-left, .header-right { display: flex; align-items: center; gap: 15px; }
        .btn-header { color: #e5e7eb; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1); padding: 8px 16px; font-size: 14px; cursor: pointer; border-radius: 10px; text-decoration: none; font-weight: 500; transition: all 0.2s ease; }
        .btn-header:hover { background: rgba(255, 255, 255, 0.15); color: #ffffff; }
        .btn-qr-medico { border-color: rgba(41, 121, 255, 0.5); color: #2979ff; }
        .container-custom { max-width: 1400px; margin: 0 auto; padding: 0 15px; }
        .nav-tabs .nav-link { background: #0a0a0a; border: 1px solid #333; color: #9ca3af; border-bottom: none; border-radius: 8px 8px 0 0; }
        .nav-tabs .nav-link.active { background: #111; color: #2979ff; border-color: #333 #333 #111; }
        .tab-content { background: #0a0a0a; border: 1px solid #333; border-top: none; padding: 2rem; border-radius: 0 0 16px 16px; }
        .form-control, .form-control:focus { background: #000; border: 1px solid #333; color: #e5e7eb; border-radius: 8px; font-size: 13px; }
        .form-control:focus { border-color: #2979ff; }
        .quadrant-card { background: #111; border: 1px solid #222; border-radius: 12px; padding: 15px; margin-bottom: 20px; }
        .quadrant-title { color: #2979ff; font-weight: 700; font-size: 14px; margin-bottom: 15px; text-transform: uppercase; }
        .tooth-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .tooth-number { width: 34px; height: 34px; background: #222; border-radius: 6px; border: 1px solid #333; transition: all 0.3s ease; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 13px; color: #fff; }
        .tooth-number.treated { background: #10b981 !important; border-color: #10b981 !important; box-shadow: 0 0 10px rgba(16, 185, 129, 0.4); }
        .budget-table th { font-size: 12px; text-transform: uppercase; color: #9ca3af; border-top: none; }
        .budget-table td { vertical-align: middle; }
        .budget-card-stat { background: #111; border: 1px solid #222; padding: 15px; border-radius: 12px; text-align: center; }
        
        /* Estilo para filas de tratamientos realizados */
        tr.fila-realizada { background: rgba(16, 185, 129, 0.05) !important; }
        tr.fila-realizada .b-total { color: #10b981 !important; }

        /* Estilos para filas de pagos más oscuras y redondeadas */
        #pagos-body tr td { background: #050505; border-top: 6px solid #000; border-bottom: none; }
        #pagos-body tr td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
        #pagos-body tr td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }

        /* Corregir visibilidad de campos bloqueados (evitar blanco sobre blanco) */
        #pagos-body tr.row-bloqueada input:disabled, 
        #pagos-body tr.row-bloqueada select:disabled {
            background-color: #000 !important;
            color: #ffffff !important;
            border-color: #333 !important;
            opacity: 0.8;
        }

        /* Estilos para Modales Oscuros */
        .modal-content { background: #0a0a0a; border: 1px solid #333; color: #e5e7eb; box-shadow: 0 10px 40px rgba(0,0,0,0.7); }
        .modal-header, .modal-footer { border-color: #222; background: #111; }
        .modal-header .close { color: #fff; text-shadow: none; opacity: 0.8; }
        .modal-header .close:hover { color: #2979ff; }

        #notification-container { position: fixed; top: 100px; right: 20px; z-index: 1060; width: 300px; }
        .toast-message { background-color: #1f2937; color: #e5e7eb; padding: 15px 20px; border-radius: 8px; margin-bottom: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.4); border-left: 5px solid #6b7280; opacity: 0; transform: translateX(100%); transition: all 0.4s cubic-bezier(0.215, 0.610, 0.355, 1); }
        .toast-message.show { opacity: 1; transform: translateX(0); }
        .toast-message.success { border-left-color: #10b981; }
        .toast-message.error { border-left-color: #ef4444; }
        .tooth-treatments { display: flex; flex-direction: column; gap: 6px; }
        .tooth-treatment-row { width: 100%; }
        .add-tooth-treatment { min-width: 38px; min-height: 38px; }
        .tooth-count { background: rgba(41, 121, 255, 0.15); color: #2979ff; border: 1px solid rgba(41, 121, 255, 0.3); }

        /* Estilos para el tooltip del diente */
        .tooth-number[title]:hover::after {
            content: attr(title);
            position: absolute;
            background: #333;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 10;
            transform: translateX(-50%);
            left: 50%;
            bottom: 100%;
        }
    </style>
    <style>
        /* Estilos para el dropdown de búsqueda de tratamientos */
        .position-relative {
            position: relative;
        }
        .tooth-search-results {
            position: absolute;
            z-index: 2100; /* Superior a observaciones y otros controles */
            width: 100%;
            background-color: #0a0a0a; /* Dark background for dropdown */
            border: 1px solid #333;
            border-top: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.5);
            border-radius: 0 0 8px 8px;
            display: none; /* Hidden by default */
        }
        .tooth-search-results .dropdown-item {
            color: #e5e7eb; /* Light text color */
            padding: 8px 15px;
            cursor: pointer;
        }
        .tooth-search-results .dropdown-item:hover {
            background-color: #2979ff; /* Accent color on hover */
            color: #fff;
        }
        /* Asegurar que las tablas no corten los dropdowns */
        .tab-pane .table-responsive { overflow: visible !important; }
        #pagos .table-responsive, #tratamiento .table-responsive { overflow: visible !important; }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="header-left">
            <a href="catalogo_pacientes.php" class="btn-header mr-3"><i class="fas fa-arrow-left"></i></a>
            <div class="d-flex align-items-center">
                <img src="<?php echo $avatar_url; ?>" alt="Identicon" class="mr-3" style="width: 50px; height: 50px; border-radius: 50%; background-color: #1a1a1a; border: 2px solid #333;">
                <div>
                    <h5 class="mb-0 text-white"><?php echo htmlspecialchars($nombre_completo); ?></h5>
                    <small class="text-muted">Expediente Dental</small>
                </div>
            </div>
        </div>
        <div class="header-right">
            <button class="btn-header btn-qr-medico" onclick="generarQRMedico()"><i class="fas fa-qrcode"></i> QR Médico</button>
            <button class="btn-header" onclick="guardarTodo()"><i class="fas fa-save"></i> Guardar Todo</button>
        </div>
    </header>

    <div class="container-custom mt-4">
        <ul class="nav nav-tabs" id="expedienteTab" role="tablist">
            <li class="nav-item"><a class="nav-link active" id="personales-tab" data-toggle="tab" href="#personales">Datos Personales</a></li>
            <li class="nav-item"><a class="nav-link" id="tratamiento-tab" data-toggle="tab" href="#tratamiento">Odontograma / Tratamiento</a></li>
            <li class="nav-item"><a class="nav-link" id="presupuesto-tab" data-toggle="tab" href="#presupuesto">Presupuesto</a></li>
            <li class="nav-item"><a class="nav-link" id="documentos-tab" data-toggle="tab" href="#documentos">Documentos</a></li>
            <li class="nav-item"><a class="nav-link" id="pagos-tab" data-toggle="tab" href="#pagos">Registro de Tratamientos y Pagos</a></li>
        </ul>

        <div class="tab-content" id="expedienteTabContent">
            <!-- Tab Datos Personales -->
            <div class="tab-pane fade show active" id="personales" role="tabpanel">
                <form id="formDatosPersonales">
                    <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">
                    <div class="row">
                        <div class="col-md-4 form-group"><label>Nombre(s)</label><input type="text" class="form-control" id="dp_nombre" name="nombre"></div>
                        <div class="col-md-4 form-group"><label>Apellido Paterno</label><input type="text" class="form-control" id="dp_apellido_paterno" name="apellido_paterno"></div>
                        <div class="col-md-4 form-group"><label>Apellido Materno</label><input type="text" class="form-control" id="dp_apellido_materno" name="apellido_materno"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group"><label>Fecha de Nacimiento</label><input type="date" class="form-control" id="dp_fecha_nacimiento" name="fecha_nacimiento"></div>
                        <div class="col-md-4 form-group"><label>RFC</label><input type="text" class="form-control" id="dp_rfc" name="rfc" maxlength="13" style="text-transform:uppercase;"></div>
                        <div class="col-md-4 form-group"><label>Teléfono Celular</label><input type="tel" class="form-control" id="dp_telefono" name="telefono"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group"><label>Correo Electrónico</label><input type="email" class="form-control" id="dp_correo" name="correo"></div>
                        <div class="col-md-4 form-group"><label>Teléfono de Emergencia</label><input type="tel" class="form-control" id="dp_tel_emergencia" name="tel_emergencia"></div>
                        <div class="col-md-4 form-group"><label>CURP</label><input type="text" class="form-control" id="dp_curp" name="curp" maxlength="18" style="text-transform:uppercase;"></div>
                    </div>
                    <div class="form-group"><label>Dirección</label><textarea class="form-control" id="dp_direccion" name="direccion" rows="2"></textarea></div>
                    <div class="form-group"><label>Motivo de Consulta</label><textarea class="form-control" id="dp_motivo_consulta" name="motivo_consulta" rows="2"></textarea></div>
                    <div class="row">
                        <div class="col-md-6 form-group"><label>Alergias</label><input type="text" class="form-control" id="dp_alergias" name="alergias"></div>
                        <div class="col-md-6 form-group"><label>Medicamentos actuales</label><input type="text" class="form-control" id="dp_medicamentos" name="medicamentos"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Origen</label>
                            <select class="form-control" id="dp_origen" name="origen" onchange="verificarOrigenDoctorExpediente()">
                                <!-- Opciones cargadas dinámicamente desde el catálogo -->
                            </select>
                        </div>
                        <div class="col-md-6 form-group" id="dp_recomendado_doctor_div" style="display:none;">
                            <label id="dp_recomendado_doctor_label">Nombre del Doctor</label>
                            <input type="text" class="form-control" id="dp_recomendado_doctor_nombre" name="recomendado_doctor_nombre" placeholder="Nombre del doctor">
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tab Odontograma -->
            <div class="tab-pane fade" id="tratamiento" role="tabpanel">
                <div class="row" id="odontograma-grid">
                    <!-- Cuadrantes generados por JS -->
                </div>
                <div class="col-12 mt-4">
                    <h5 class="quadrant-title">Tratamientos Generales (No asociados a un diente)</h5>
                    <div class="table-responsive">
                        <table class="table table-dark budget-table" id="tablaTratamientosGenerales">
                            <thead>
                                <tr>
                                    <th>Tratamiento / Servicio</th>
                                    <th style="width: 250px;">Médico Asignado</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="general-treatments-body"></tbody>
                        </table>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" onclick="agregarFilaTratamientoGeneral()"><i class="fas fa-plus"></i> Agregar tratamiento general</button>
                </div>
                <div class="form-group mt-4">
                    <label>Observaciones Generales</label>
                    <textarea class="form-control" id="observaciones_dentista" rows="4" placeholder="Notas sobre el estado general bucal o plan de tratamiento..."></textarea>
                </div>
            </div>

            <!-- Tab Presupuesto -->
            <div class="tab-pane fade" id="presupuesto" role="tabpanel">
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label class="text-muted small font-weight-bold">FECHA DEL PRESUPUESTO</label>
                        <input type="date" id="presupuesto_fecha" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark budget-table" id="tablaPresupuesto">
                        <thead>
                            <tr>
                                <th style="width: 100px;">Diente</th>
                                <th>Tratamiento / Servicio</th>
                                <th>Médico Asignado</th>
                                <th style="width: 130px;">Precio ($)</th>
                                <th style="width: 130px;">Descuento ($)</th>
                                <th style="width: 130px;">Total ($)</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="presupuesto-body">
                            <!-- Filas dinámicas -->
                        </tbody>
                    </table>
                </div>
                <div class="d-flex mb-4" style="gap: 10px;">
                    <button class="btn btn-sm btn-outline-primary" onclick="agregarFilaPresupuesto()"><i class="fas fa-plus"></i> Agregar concepto</button>
                    <button class="btn btn-sm btn-outline-info" onclick="sincronizarConOdontograma()"><i class="fas fa-sync"></i> Cargar desde Odontograma</button>
                    <button class="btn btn-sm btn-outline-success" onclick="imprimirPresupuesto()"><i class="fas fa-print"></i> Imprimir / Enviar</button>
                    <button class="btn btn-sm btn-outline-warning" onclick="generarQRPaciente()"><i class="fas fa-qrcode"></i> QR Paciente</button>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="budget-card-stat"><small class="text-muted d-block">TOTAL PRESUPUESTO</small><h3 id="total-general" class="mb-0">$0.00</h3></div>
                    </div>
                </div>
            </div>

            <!-- Tab Documentos -->
            <div class="tab-pane fade" id="documentos" role="tabpanel">
                <h5>Subir Nuevo Documento</h5>
                <form id="formDocumento" class="mb-4" enctype="multipart/form-data">
                    <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Tipo de Documento</label>
                            <input type="text" name="tipo_documento" class="form-control" placeholder="Ej: Radiografía, INE, Receta">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Archivo</label>
                            <input type="file" name="archivo" class="form-control" required>
                        </div>
                        <div class="col-md-2 form-group d-flex align-items-end">
                            <button type="submit" class="btn btn-secondary btn-block">Subir</button>
                        </div>
                    </div>
                </form>
                <hr>
                <h5>Documentos Anexos</h5>
                <div id="listaDocumentos">
                    <p class="text-muted">No hay documentos adjuntos.</p>
                </div>
            </div>



            <!-- Tab Registro de Pagos -->
            <div class="tab-pane fade" id="pagos" role="tabpanel">
                <div class="row mb-3 align-items-center">
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="background:#111; color:#2979ff; border-color:#333; font-weight:bold;">T.C. Dólar $</span>
                            </div>
                            <input type="number" id="tipo_cambio_dolar" class="form-control" value="20.00" oninput="calcularTotalesPagos()">
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-dark budget-table" id="tablaRegistroPagos">
                        <thead>
                            <tr>
                                <th style="width: 150px;">Fecha</th>
                                <th style="width: 220px;">Métodos de Pago</th>
                                <th style="width: 140px;">Total Abono ($)</th>
                                <th style="width: 120px;"></th>
                            </tr>
                        </thead>
                        <tbody id="pagos-body">
                            <!-- Filas dinámicas -->
                        </tbody>
                    </table>
                </div>
                <div class="d-flex mb-3" style="gap: 10px;">
                    <button class="btn btn-sm btn-outline-primary" onclick="agregarFilaPago()"><i class="fas fa-plus"></i> Registrar Nuevo Abono</button>
                    <button class="btn btn-sm btn-outline-success" onclick="liquidarSaldoRestante()"><i class="fas fa-check-double"></i> Liquidar Saldo</button>
                    <button class="btn btn-sm btn-outline-warning" onclick="abrirModalSeleccionarTratamientos()"><i class="fas fa-check-double"></i> Seleccionar Tratamientos para Pago</button>
                </div>

                <h6 class="mt-4 text-primary font-weight-bold"><i class="fas fa-hand-holding-medical mr-2"></i> Tratamientos Aplicados / Cobro Seleccionado</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-dark budget-table" style="background: rgba(41, 121, 255, 0.05); border-radius: 8px;">
                        <thead>
                            <tr>
                                <th style="width: 120px;">Fecha Aplic.</th>
                                <th style="width: 80px;" class="text-center">Diente</th>
                                <th>Tratamiento</th>
                                <th>Doctor</th>
                                <th class="text-right" style="width: 150px;">Total ($)</th>
                            </tr>
                        </thead>
                        <tbody id="realized-treatments-list-body">
                            <tr><td colspan="5" class="text-center text-muted">No hay tratamientos seleccionados para cobro en esta cuenta.</td></tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="budget-card-stat"><small class="text-muted d-block">DEUDA TOTAL (PRESUPUESTO)</small><h3 id="p-total-final" class="mb-0">$0.00</h3></div>
                    </div>
                    <div class="col-md-4">
                        <div class="budget-card-stat"><small class="text-muted d-block text-success">TOTAL ABONADO</small><h3 id="p-total-pagado" class="mb-0 text-success">$0.00</h3></div>
                    </div>
                    <div class="col-md-4">
                        <div class="budget-card-stat"><small class="text-muted d-block text-warning">PENDIENTE</small><h3 id="p-total-pendiente" class="mb-0 text-warning">$0.00</h3></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Códigos QR -->
    <div class="modal fade" id="modalQR" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content text-center" style="background: #111; border: 1px solid #333;">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-white" id="qrTitle">Código QR</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body pb-5">
                    <div id="qrContainer" class="bg-white p-3 rounded mb-3 d-inline-block">
                        <img id="qrImage" src="" alt="QR Code" style="width: 200px; height: 200px;">
                    </div>
                    <p class="text-muted small" id="qrText">Escanea para acceder</p>
                </div>
            </div>
        </div>
    </div>

    <div id="notification-container"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const pacienteId = <?php echo $paciente_id; ?>;
        const medicoActual = <?php echo json_encode($usuario_nombre_actual); ?>;
        let serviciosData = [];
        let serviciosOptions = '';
        let medicosOptions = '';

        const cuadrantes = [
            { id: 'Q1', title: 'Cuadrante 1', teeth: [11, 12, 13, 14, 15, 16, 17, 18] },
            { id: 'Q2', title: 'Cuadrante 2', teeth: [21, 22, 23, 24, 25, 26, 27, 28] },
            { id: 'Q4', title: 'Cuadrante 4', teeth: [41, 42, 43, 44, 45, 46, 47, 48] },
            { id: 'Q3', title: 'Cuadrante 3', teeth: [31, 32, 33, 34, 35, 36, 37, 38] }
        ];

        $(document).ready(async function() {
            await cargarServicios();
            await cargarMedicos();
            await cargarOrigenesRecomendacionExpediente(); // Cargar los orígenes de recomendación
            renderOdontograma();
            cargarExpediente();
            $('#formDocumento').on('submit', function(e) { e.preventDefault(); guardarDocumento(); });
        });

        // Registrar automáticamente qué médico selecciona el tratamiento en el odontograma
        $(document).on('change', '.tooth-select', function() {
                    const tooth = $(this).data('tooth');
    
        if ($(this).val()) {
                $(this).attr('data-doctor', medicoActual);
            } else {
                $(this).removeAttr('data-doctor');
            }
                        actualizarEstadoVisualDiente(tooth);

        });

        function actualizarEstadoVisualDiente(toothId) {
            const box = $(`.tooth-number[data-tooth-id="${toothId}"]`);
            let treated = false;
            $(`.tooth-treatment-row[data-tooth="${toothId}"]`).each(function() {
                const searchInput = $(this).find('.tooth-search-input');
                const hiddenInput = $(this).find('.tooth-service-id');
                const serviceName = searchInput.val().trim();
                const serviceId = hiddenInput.val();
                if (serviceName !== '' && (serviceId !== '' || serviceName !== '')) {
                    treated = true;
                    return false;
                }
            });

            if (treated) {
                box.addClass('treated');
            } else {
                box.removeClass('treated');
            }
        }
        
        async function cargarServicios() {
            try {
                const response = await fetch('citas/servicios_json.php');
                serviciosData = await response.json();
                serviciosOptions = '<option value=""></option>';
                serviciosData.forEach(s => {
                    serviciosOptions += `<option value="${s.id}" data-price="${s.precio}">${s.nombre}</option>`;
                });
            } catch (e) { console.error('Error al cargar servicios', e); }
        }

        async function cargarMedicos() {
            try {
                const response = await fetch('citas/lista_doctores_json.php');
                const medicosData = await response.json();
                medicosOptions = '<option value="">Sin asignar</option>';
                medicosData.forEach(m => {
                    medicosOptions += `<option value="${m.nombre}">${m.nombre}</option>`;
                });
            } catch (e) { console.error('Error al cargar médicos', e); }
        }

        // Función para cargar los orígenes de recomendación dinámicamente en el expediente
        async function cargarOrigenesRecomendacionExpediente() {
            try {
                const response = await fetch('citas/origenes_recomendacion_json.php');
                const data = await response.json();
                const select = $('#dp_origen');
                select.empty();
                select.append('<option value="">-- Seleccionar --</option>'); // Opción por defecto
                data.forEach(o => {
                    select.append(`<option value="${o.id}" data-nombre="${o.nombre}">${o.nombre}</option>`);
                });
                select.append('<option value="DOCTOR">Doctor</option>'); // Siempre añadir la opción de Doctor
                select.append('<option value="PERSONA">Persona</option>'); // Siempre añadir la opción de Persona
            } catch (e) {
                console.error('Error al cargar orígenes de recomendación para expediente', e);
                $('#dp_origen').html('<option value="">Error al cargar</option>');
            }
        }

        function renderOdontograma() {
            const grid = $('#odontograma-grid');
            cuadrantes.forEach(c => {
                let html = `<div class="col-md-6"><div class="quadrant-card"><div class="quadrant-title">${c.title}</div>`;
                c.teeth.forEach(t => {
                    html += `<div class="tooth-row">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center">
                                <div class="tooth-number" data-tooth-id="${t}" title="Diente ${t}">${t}</div>
                                <span class="badge badge-secondary badge-pill tooth-count ml-2" data-tooth="${t}" style="display:none; font-size:11px; padding:4px 8px;">1</span>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-info add-tooth-treatment" data-tooth="${t}" title="Agregar tratamiento"><i class="fas fa-plus"></i></button>
                        </div>
                        <div class="tooth-treatments" data-tooth="${t}"></div>
                    </div>`;
                });
                html += `</div></div>`;
                grid.append(html);
            });

            cuadrantes.forEach(c => {
                c.teeth.forEach(t => addToothTreatmentRow(t));
            });
        }

        function createToothTreatmentRow(tooth, data = null) {
            const rowId = 'tooth-' + tooth + '-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
            let serviceName = '';
            let serviceId = '';
            let doctor = medicoActual;

            if (data) {
                if (typeof data !== 'object') {
                    const service = serviciosData.find(s => s.id == data);
                    serviceId = data;
                    serviceName = service ? service.nombre : '';
                } else {
                    serviceId = data.service_id || data.servicio_id || '';
                    serviceName = data.service_name || data.nombre || '';
                    doctor = data.doctor_nombre || medicoActual;
                }
            }

            const row = $(
                `<div class="tooth-treatment-row" data-tooth="${tooth}" id="${rowId}">
                    <div class="d-flex align-items-center" style="gap:10px; margin-bottom: 6px;">
                        <div class="position-relative flex-grow-1">
                            <input type="text" class="form-control tooth-search-input" placeholder="Buscar tratamiento..." data-tooth="${tooth}" autocomplete="off" value="${serviceName}">
                            <input type="hidden" class="tooth-service-id" data-tooth="${tooth}" data-doctor="${doctor}" value="${serviceId}">
                            <div class="tooth-search-results dropdown-menu"></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-tooth-treatment" data-tooth="${tooth}" title="Eliminar tratamiento"><i class="fas fa-times"></i></button>
                    </div>
                </div>`
            );

            row.find('.remove-tooth-treatment').on('click', function() {
                const container = $(`.tooth-treatments[data-tooth="${tooth}"]`);
                row.remove();
                if (container.find('.tooth-treatment-row').length === 0) {
                    addToothTreatmentRow(tooth);
                }
                refreshToothControls(tooth);
                actualizarEstadoVisualDiente(tooth);
            });

            return row;
        }

        function refreshToothControls(tooth) {
            const rows = $(`.tooth-treatment-row[data-tooth="${tooth}"]`);
            const count = rows.length;
            const badge = $(`.tooth-count[data-tooth="${tooth}"]`);
            badge.toggle(count > 1).text(count);
            rows.find('.remove-tooth-treatment').toggle(count > 1);
        }

        function addToothTreatmentRow(tooth, data = null) {
            const container = $(`.tooth-treatments[data-tooth="${tooth}"]`);
            const row = createToothTreatmentRow(tooth, data);
            container.append(row);
            refreshToothControls(tooth);
            return row;
        }

        function agregarFilaTratamientoGeneral(data = null) {
            const tbody = $('#general-treatments-body');
            const rowId = 'gen-' + Date.now() + Math.floor(Math.random() * 1000);
            const doctor = data ? (data.doctor_nombre || '') : medicoActual;

            const row = $(`
                <tr id="row-${rowId}">
                    <td>
                        <div class="position-relative">
                            <input type="text" class="form-control general-search-input" placeholder="" value="${data ? (data.nombre || '') : ''}" autocomplete="off">
                            <input type="hidden" class="general-service-id" value="${data ? (data.service_id || '') : ''}">
                            <div class="tooth-search-results dropdown-menu"></div>
                        </div>
                    </td>
                    <td><select class="form-control general-doctor" style="background:rgba(255,255,255,0.05); border:1px solid #222; color:#2979ff; font-weight:600; font-size:11px;">${medicosOptions}</select></td>
                    <td class="text-center"><button class="btn btn-link text-danger p-0" onclick="$('#row-${rowId}').remove();"><i class="fas fa-times"></i></button></td>
                </tr>
            `);
            if (doctor) row.find('.general-doctor').val(doctor);
            tbody.append(row);
        }

        // Lógica unificada para inputs de búsqueda (Odontograma y Presupuesto)
        $(document).on('click', '.add-tooth-treatment', function() {
            addToothTreatmentRow($(this).data('tooth'));
        });

        $(document).on('input', '.tooth-search-input, .general-search-input, .b-servicio-search', function() {
            const input = $(this);
            const searchTerm = input.val().toLowerCase();
            const resultsDropdown = input.siblings('.tooth-search-results');
            
            resultsDropdown.empty();
            const filteredServices = serviciosData.filter(s => s.nombre.toLowerCase().includes(searchTerm));

            filteredServices.forEach(s => {
                const item = $(`<a class="dropdown-item" href="#" data-service-id="${s.id}" data-service-name="${s.nombre}">${s.nombre}</a>`);
                item.on('click', function(e) {
                    e.preventDefault();
                    let assignedDoc = medicoActual;
                    if (s.medico_nombre) {
                        assignedDoc = s.medico_nombre.split(',')[0].trim();
                    }

                    input.val(s.nombre);
                    input.siblings('input[type="hidden"]').val(s.id);
                    
                    if (input.hasClass('tooth-search-input')) {
                        input.siblings('input[type="hidden"]').attr('data-doctor', assignedDoc);
                        actualizarEstadoVisualDiente(input.data('tooth'));
                    } else if (input.hasClass('general-search-input')) {
                        input.closest('tr').find('.general-doctor').val(assignedDoc);
                    } else if (input.hasClass('b-servicio-search')) {
                        input.closest('tr').find('.b-doctor').val(assignedDoc);
                        input.closest('tr').find('.b-base').val(s.precio || 0);
                        calcularTotales();
                    }
                    resultsDropdown.hide();
                });
                resultsDropdown.append(item);
            });

            // Opción especial: OTRO
            const otherItem = $(`<a class="dropdown-item" href="#" style="color:#2979ff; font-weight:bold; border-top:1px solid #333;">-- OTRO (Especial) --</a>`);
            otherItem.on('click', function(e) {
                e.preventDefault();
                input.val('');
                input.siblings('input[type="hidden"]').val('OTHER');
                resultsDropdown.hide();
                input.focus();
                if (input.hasClass('tooth-search-input')) {
                    input.siblings('input[type="hidden"]').attr('data-doctor', medicoActual);
                    actualizarEstadoVisualDiente(input.data('tooth'));
                }
            });
            resultsDropdown.append(otherItem);
            resultsDropdown.show();
        });

        // Cerrar dropdowns al hacer clic fuera
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.position-relative').length) {
                $('.tooth-search-results').hide();
            }
        });

        $(document).on('keyup', '.tooth-search-input', function() {
            actualizarEstadoVisualDiente($(this).data('tooth'));
        });

        async function cargarExpediente() {
            try {
                const response = await fetch(`get_expediente_dentista.php?paciente_id=${pacienteId}`);
                const data = await response.json();

                if (data.datos_personales) {
                    const dp = data.datos_personales;
                    $('#dp_nombre').val(dp.nombre || '');
                    $('#dp_apellido_paterno').val(dp.apellido_paterno || '');
                    $('#dp_apellido_materno').val(dp.apellido_materno || '');
                    $('#dp_fecha_nacimiento').val(dp.fecha_nacimiento || '');
                    $('#dp_rfc').val(dp.rfc || '');
                    $('#dp_tel_emergencia').val(dp.tel_emergencia || '');
                    $('#dp_motivo_consulta').val(dp.motivo_consulta || '');
                    $('#dp_alergias').val(dp.alergias || '');
                    $('#dp_medicamentos').val(dp.medicamentos || '');
                    $('#dp_curp').val(dp.curp || '');
                    $('#dp_telefono').val(dp.telefono || '');
                    $('#dp_correo').val(dp.correo || '');
                    $('#dp_direccion').val(dp.direccion || '');
                    
                    if (dp.origen && dp.origen.startsWith('DOCTOR:')) {
                        $('#dp_origen').val('DOCTOR');
                        $('#dp_recomendado_doctor_nombre').val(dp.origen.replace('DOCTOR:', '').trim());
                        $('#dp_recomendado_doctor_div').show();
                    } else if (dp.origen && dp.origen.startsWith('PERSONA:')) {
                        $('#dp_origen').val('PERSONA');
                        $('#dp_recomendado_doctor_nombre').val(dp.origen.replace('PERSONA:', '').trim());
                        $('#dp_recomendado_doctor_div').show();
                    } else if (dp.recomendado_por_id) {
                        $('#dp_origen').val(dp.recomendado_por_id);
                        $('#dp_recomendado_doctor_div').hide();
                    } else {
                        $('#dp_origen').val(dp.origen || '');
                        $('#dp_recomendado_doctor_div').hide();
                    }
                    verificarOrigenDoctorExpediente();
                }

                if (data.odontograma) {
                    const o = data.odontograma;
                    $('#observaciones_dentista').val(o.observaciones || '');

                    // Cargar tratamientos realizados para el cálculo de deuda
                    if (o.realized_treatments_json) {
                        selectedRealizedTreatments = JSON.parse(o.realized_treatments_json);
                        renderRealizedTreatmentsList();
                    }

                    if (o.tratamientos_json && serviciosData.length > 0) { // Asegurarse que serviciosData esté cargada
                        const trats = JSON.parse(o.tratamientos_json);
                        const teethData = trats.teeth || trats;
                        const generalData = trats.general || [];

                        $('.tooth-treatments').each(function() {
                            const tooth = $(this).data('tooth');
                            $(this).empty();
                            const tData = teethData[tooth];
                            if (tData) {
                                const entries = Array.isArray(tData) ? tData : [tData];
                                entries.forEach(entry => addToothTreatmentRow(tooth, entry));
                            } else {
                                addToothTreatmentRow(tooth);
                            }
                            refreshToothControls(tooth);
                            actualizarEstadoVisualDiente(tooth);
                        });

                        $('#general-treatments-body').empty();
                        generalData.forEach(g => agregarFilaTratamientoGeneral(g));
                    }
                }

                if (data.odontograma && data.odontograma.presupuesto_json) {
                    const pres = JSON.parse(data.odontograma.presupuesto_json);
                    if (pres.fecha) $('#presupuesto_fecha').val(pres.fecha);
                    if (pres.items) {
                        $('#presupuesto-body').empty();
                        pres.items.forEach(item => {
                            if (!item.servicio_nombre && item.servicio_id && item.servicio_id !== 'OTHER') {
                                const s = serviciosData.find(x => x.id == item.servicio_id);
                                if (s) item.servicio_nombre = s.nombre;
                            }
                            agregarFilaPresupuesto(item);
                        });
                    }
                }

                if (data.odontograma && data.odontograma.registro_pagos_json) {
                    const pagos = JSON.parse(data.odontograma.registro_pagos_json);
                    pagos.forEach(pago => agregarFilaPago(pago));
                }

                // Cargar Documentos
                if (data.documentos) {
                    renderDocumentos(data.documentos);
                }
            } catch (e) { console.error('Error al cargar expediente', e); }
        }

        function agregarFilaPresupuesto(data = null) {
            const tbody = $('#presupuesto-body');
            const rowId = (data && data.local_id) ? data.local_id : 'row-' + Date.now() + Math.floor(Math.random() * 1000);
            
            // LÓGICA DE ATRIBUCIÓN: 
            let doctor = data ? (data.doctor_nombre || '') : medicoActual;
            const base = data ? (parseFloat(data.base) || 0) : 0;
            const descuento = data ? (parseFloat(data.descuento) || 0) : 0;
            const totalRow = base - descuento;
            const isDone = data && data.realizado == 1;
             const serviceName = data ? (data.servicio_nombre || '') : '';
            const serviceId = data ? (data.servicio_id || '') : '';


            const row = $(`
                <tr id="${rowId}" class="${isDone ? 'fila-realizada' : ''}">
                    <input type="hidden" class="b-local-id" value="${rowId}">
                    <td><input type="text" class="form-control b-diente text-center" value="${data ? (data.diente || '') : ''}" placeholder="--"></td>
<td>
                        <div class="position-relative">
                            <input type="text" class="form-control b-servicio-search" placeholder="" value="${serviceName}" autocomplete="off">
                            <input type="hidden" class="b-servicio-id" value="${serviceId}">
                            <div class="tooth-search-results dropdown-menu"></div>
                        </div>
                    </td>
                    <td><select class="form-control b-doctor" style="background:rgba(255,255,255,0.05); border:1px solid #222; color:#2979ff; font-weight:600; font-size:11px;">${medicosOptions}</select></td>
                    <td><input type="number" class="form-control b-base" value="${base}" onchange="calcularTotales()"></td>
                    <td><input type="number" class="form-control b-descuento" value="${descuento}" onchange="calcularTotales()" placeholder="0.00"></td>
                    <td><input type="number" class="form-control b-total" value="${totalRow.toFixed(2)}" readonly style="background:transparent; border:none; color:#2979ff; font-weight:bold;"></td>
                    <td><input type="number" class="form-control b-cita text-center" value="${data ? (data.cita || '') : ''}" placeholder="Etapa"></td>
                    <td class="text-center"><button class="btn btn-link text-danger p-0" onclick="confirmarEliminarFilaPresupuesto('${rowId}')"><i class="fas fa-times"></i></button></td>
                </tr>
            `);

            if (doctor) row.find('.b-doctor').val(doctor);
            tbody.append(row);
            calcularTotales();
        }

        function confirmarEliminarFilaPresupuesto(rowId) {
            if (confirm('¿Deseas eliminar este servicio del presupuesto?')) {
                $(`#${rowId}`).remove();
                calcularTotales();
            }
        }

        function actualizarPrecioBase(select) {
            const precio = $(select).find(':selected').data('price') || 0;
            $(select).closest('tr').find('.b-base').val(precio);
            calcularTotales();
        }

        function agregarFilaPago(data = null) {
            const tbody = $('#pagos-body');
            const rowId = Date.now() + Math.floor(Math.random() * 1000);
            const fecha = data ? data.fecha : new Date().toISOString().split('T')[0];

            const efe = data ? (parseFloat(data.monto_efectivo) || 0) : 0;
            const tcred = data ? (parseFloat(data.monto_tarjeta_credito) || 0) : 0;
            const tdeb = data ? (parseFloat(data.monto_tarjeta_debito) || 0) : 0;
            const dlls = data ? (parseFloat(data.monto_dlls) || 0) : 0;
            const tra = data ? (parseFloat(data.monto_transferencia) || 0) : 0;
            const isLocked = data && data.bloqueado == 1;
            
            const rate = parseFloat($('#tipo_cambio_dolar').val()) || 1;
            const totalDineroRow = efe + tcred + tdeb + (dlls * rate) + tra;
            const inputAttr = isLocked ? 'disabled' : '';
            const rowClass = isLocked ? 'row-bloqueada' : '';

            const row = $(`
                <tr id="pay-row-${rowId}" class="${rowClass}">
                    <td><input type="date" class="form-control p-fecha" value="${fecha}" ${inputAttr}></td>
                    <td>
                        <button class="btn btn-sm btn-block btn-outline-info mb-1" onclick="$(this).next().toggle()" style="font-size:11px;">
                            <i class="fas fa-wallet mr-1"></i> Detallar montos
                        </button>
                        <div class="payment-details-box" style="display:none; background:rgba(0,0,0,0.3); padding:8px; border-radius:8px; border:1px solid #222;">
                            <div class="input-group input-group-sm mb-1"><div class="input-group-prepend"><span class="input-group-text" style="font-size:9px; width:55px;">Efectivo</span></div><input type="number" class="form-control p-efe" value="${efe}" oninput="calcularTotalesPagos()" ${inputAttr}></div>
                            <div class="input-group input-group-sm mb-1"><div class="input-group-prepend"><span class="input-group-text" style="font-size:9px; width:55px;">T. Cred</span></div><input type="number" class="form-control p-tcred" value="${tcred}" oninput="calcularTotalesPagos()" ${inputAttr}></div>
                            <div class="input-group input-group-sm mb-1"><div class="input-group-prepend"><span class="input-group-text" style="font-size:9px; width:55px;">T. Deb</span></div><input type="number" class="form-control p-tdeb" value="${tdeb}" oninput="calcularTotalesPagos()" ${inputAttr}></div>
                            <div class="input-group input-group-sm mb-1"><div class="input-group-prepend"><span class="input-group-text" style="font-size:9px; width:55px;">Dólares</span></div><input type="number" class="form-control p-dlls" value="${dlls}" oninput="calcularTotalesPagos()" ${inputAttr}></div>
                            <div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text" style="font-size:9px; width:55px;">Transf</span></div><input type="number" class="form-control p-tra" value="${tra}" oninput="calcularTotalesPagos()" ${inputAttr}></div>
                        </div>
                    </td>
                    <td><input type="number" class="form-control p-total-abono" value="${totalDineroRow.toFixed(2)}" readonly style="background:transparent; border:none; color:#10b981; font-weight:bold;"></td>
                    <td class="text-center" style="white-space: nowrap;">
                        <button type="button" class="btn btn-link text-success p-0 mr-2" onclick="liquidarFila('${rowId}')" title="Confirmar Pago" ${isLocked ? 'disabled style="opacity:0.5"' : ''}><i class="fas fa-check-double"></i></button>
                        <button type="button" class="btn btn-link text-info p-0 mr-2" onclick="imprimirTicketDental('${rowId}')" title="Imprimir Ticket"><i class="fas fa-receipt"></i></button>
                        <button type="button" class="btn btn-link text-danger p-0" onclick="$('#pay-row-${rowId}').remove(); calcularTotalesPagos();" ${inputAttr}><i class="fas fa-times"></i></button>
                    </td>
                </tr>
            `);

            tbody.append(row);
            calcularTotalesPagos();
        }

        function imprimirTicketDental(rowId) {
            const row = $(`#pay-row-${rowId}`);
            const fecha = row.find('.p-fecha').val();
            const totalAbono = row.find('.p-total-abono').val();

            const efe = parseFloat(row.find('.p-efe').val()) || 0;
            const tcred = parseFloat(row.find('.p-tcred').val()) || 0;
            const tdeb = parseFloat(row.find('.p-tdeb').val()) || 0;
            const dlls = parseFloat(row.find('.p-dlls').val()) || 0;
            const tra = parseFloat(row.find('.p-tra').val()) || 0;
    
            const rate = parseFloat($('#tipo_cambio_dolar').val()) || 1;

            const pendienteTxt = $('#p-total-pendiente').text().replace(/[^0-9.-]+/g,"");
            const pendiente = parseFloat(pendienteTxt) || 0;

            if (parseFloat(totalAbono) <= 0) {
                mostrarNotificacion('El monto del pago o descuento debe ser mayor a 0 para generar un ticket.', 'error');
                return;
            }

            let url = `generar_ticket.php?tipo=dental&paciente_id=${pacienteId}&fecha=${fecha}&total=${totalAbono}&pendiente=${pendiente}&rate=${rate}`;
            if(efe > 0) url += `&efe=${efe}`;
            if(tcred > 0) url += `&tcred=${tcred}`;
            if(tdeb > 0) url += `&tdeb=${tdeb}`;
            if(dlls > 0) url += `&dlls=${dlls}`;
            if(tra > 0) url += `&tra=${tra}`;

            window.open(url, '_blank', 'width=400,height=600');
        }

        
            

        function calcularTotalesPagos() {
           let totalAbonadoEfectivo = 0;
            const rate = parseFloat($('#tipo_cambio_dolar').val()) || 1;
           
            $('#pagos-body tr').each(function() {

                
const efe = parseFloat($(this).find('.p-efe').val()) || 0;
                const tra = parseFloat($(this).find('.p-tra').val()) || 0;
                const tcred = parseFloat($(this).find('.p-tcred').val()) || 0;
                const tdeb = parseFloat($(this).find('.p-tdeb').val()) || 0;
                const dlls = parseFloat($(this).find('.p-dlls').val()) || 0;
                const totalDineroRow = efe + tcred + tdeb + (dlls * rate) + tra;
                
                $(this).find('.p-total-abono').val(totalDineroRow.toFixed(2));
                
                totalAbonadoEfectivo += totalDineroRow;

});
            // Obtener deuda total de los tratamientos seleccionados como "realizados"
            let totalDeuda = 0;
            selectedRealizedTreatments.forEach(item => {
                totalDeuda += (parseFloat(item.total) || 0);
            });

            $('#p-total-final').text(`$${totalDeuda.toLocaleString('es-MX', {minimumFractionDigits:2})}`);
            $('#p-total-pagado').text(`$${totalAbonadoEfectivo.toLocaleString('es-MX', {minimumFractionDigits:2})}`);
            
            const saldoPendiente = totalDeuda - totalAbonadoEfectivo;
            $('#p-total-pendiente').text(`$${saldoPendiente.toLocaleString('es-MX', {minimumFractionDigits:2})}`);
        }

        async function liquidarFila(rowId) {
            const targetRow = $(`#pay-row-${rowId}`);
            const totalRow = parseFloat(targetRow.find('.p-total-abono').val()) || 0;

            if (totalRow <= 0) {
                mostrarNotificacion('Ingrese montos antes de confirmar el pago.', 'warning');
                return;
            }

            if (!confirm('¿Confirmar pago? Esta acción bloqueará la edición de esta fila para asegurar la contabilidad.')) return;

            targetRow.addClass('row-bloqueada');
            targetRow.find('input, .btn-success').prop('disabled', true);
            calcularTotalesPagos();
            
            // Persistir inmediatamente el estado 'bloqueado' y el monto del pago
            const success = await guardarPagos();
            if (success) {
                mostrarNotificacion('Pago confirmado y guardado correctamente.', 'success');
            } else {
                mostrarNotificacion('Error al sincronizar con el servidor.', 'error');
            }
        }

        function liquidarSaldoRestante() {
            calcularTotalesPagos();
            const pendienteTxt = $('#p-total-pendiente').text().replace(/[^0-9.-]+/g,"");
            const pendiente = parseFloat(pendienteTxt) || 0;

            if (pendiente <= 0) {
                mostrarNotificacion('El paciente no tiene saldo pendiente.', 'success');
                return;
            }

            if (confirm(`¿Desea registrar un abono para liquidar el total restante de $${pendiente.toFixed(2)}?`)) {
                agregarFilaPago({
                    fecha: new Date().toISOString().split('T')[0],
                    monto_efectivo: pendiente,
                    monto_tarjeta_credito: 0,
                    monto_tarjeta_debito: 0,
                    monto_dlls: 0,
                    monto_transferencia: 0,
                });
                mostrarNotificacion('Saldo liquidado en nueva fila.', 'success');
            }
        }

        function calcularTotales() {
            let totalGral = 0;
            $('#presupuesto-body tr').each(function() {
                const base = parseFloat($(this).find('.b-base').val()) || 0;
                const descuento = parseFloat($(this).find('.b-descuento').val()) || 0;
                const totalRow = base - descuento;
                
                $(this).find('.b-total').val(totalRow.toFixed(2));
                totalGral += totalRow;
            });

            $('#total-general').text(`$${totalGral.toLocaleString('es-MX', {minimumFractionDigits:2})}`);
            // Importante: Actualizar la deuda en la pestaña de pagos también
            if (typeof calcularTotalesPagos === 'function') calcularTotalesPagos();
        }

        function sincronizarConOdontograma() {
            $('.tooth-search-input').each(function() {
                const tooth = $(this).data('tooth');
                const hiddenInput = $(`.tooth-service-id[data-tooth="${tooth}"]`);
                const serviceId = hiddenInput.val();
                const serviceName = $(this).val().trim();
                let doctor = hiddenInput.attr('data-doctor');
                
                if (serviceName !== '') {
                    if (!doctor) doctor = medicoActual;

                    // Evitar duplicar si ya existe el par diente-servicio
                    let existe = false;
                    $('#presupuesto-body tr').each(function() {
                        if ($(this).find('.b-diente').val() == tooth && $(this).find('.b-servicio-search').val() == serviceName) {
                            existe = true;
                        }
                    });

                    if (!existe) {
                        const serviceData = (serviceId && serviceId !== 'OTHER') ? serviciosData.find(s => s.id == serviceId) : null;
                        agregarFilaPresupuesto({
                            diente: tooth,
                            servicio_id: serviceId || 'OTHER',
                            servicio_nombre: serviceName,
                            doctor_nombre: doctor,
                            base: serviceData ? serviceData.precio : 0,
                            cita: ''
                        });
                    }
                }
            });

            // Sincronizar tratamientos generales
            $('#general-treatments-body tr').each(function() {
                const hiddenInput = $(this).find('.general-service-id');
                const serviceId = hiddenInput.val();
                const serviceName = $(this).find('.general-search-input').val().trim();
                const doctor = $(this).find('.general-doctor').val();

                if (serviceName !== '') {
                    let existe = false;
                    $('#presupuesto-body tr').each(function() {
                        if (($(this).find('.b-diente').val() === '' || $(this).find('.b-diente').val() === '--') && 
                            $(this).find('.b-servicio-search').val() == serviceName) {
                            existe = true;
                        }
                    });

                    if (!existe) {
                        const serviceData = (serviceId && serviceId !== 'OTHER') ? serviciosData.find(s => s.id == serviceId) : null;
                        agregarFilaPresupuesto({
                            diente: '',
                            servicio_id: serviceId || 'OTHER',
                            servicio_nombre: serviceName,
                            doctor_nombre: doctor || medicoActual,
                            base: serviceData ? serviceData.precio : 0,
                            cita: ''
                        });
                    }
                }
            });

            // Guardar automáticamente para asegurar que los cambios se persistan
            guardarPresupuesto().then(() => {
                mostrarNotificacion('Presupuesto actualizado y guardado desde el odontograma.', 'success');
            });
        }

        function sincronizarConPresupuesto() {
            // Marcar filas actuales para evitar duplicados en la misma sesión
            $('#pagos-body tr').addClass('sync-old');

            $('#presupuesto-body tr').each(function() {
                const diente = $(this).find('.b-diente').val() || '';
                const servicioId = $(this).find('.b-servicio').val();
                const doctor = $(this).find('.b-doctor').val();
                const base = $(this).find('.b-base').val();

                if (servicioId) {
                    let vinculada = false;
                    $('.sync-old').each(function() {
                        const pDiente = $(this).find('.p-diente').val() || '';
                        const pServicio = $(this).find('.p-servicio').val();
                        const pPago = parseFloat($(this).find('.p-pago').val()) || 0;

                        if (pDiente == diente && pServicio == servicioId && !$(this).hasClass('sync-processed')) {
                            // Si la fila existe y no tiene abonos, actualizamos precio y médico con lo último del presupuesto
                            if (pPago == 0) {
                                $(this).find('.p-base').val(base);
                                $(this).find('.p-doctor').val(doctor);
                            }
                            $(this).addClass('sync-processed');
                            vinculada = true;
                            return false; // Salir del bucle interno
                        }
                    });

                    if (!vinculada) {
                        // Si es un concepto nuevo, lo agregamos
                        agregarFilaPago({
                            fecha: new Date().toISOString().split('T')[0],
                            diente: diente,
                            servicio_id: servicioId,
                            doctor_nombre: doctor || medicoActual,
                            base: parseFloat(base) || 0,
                            ajuste: 0,
                            pago: 0,
                            metodo: 'Efectivo'
                        });
                    }
                }
            });
            $('.sync-old').removeClass('sync-old sync-processed');
            calcularTotalesPagos();
            mostrarNotificacion('Se han jalado los conceptos del presupuesto al registro.', 'success');
        }

        function sincronizarPagosConOdontograma() {
            $('#pagos-body tr').addClass('sync-old');

            $('.tooth-search-input').each(function() {
                const tooth = $(this).data('tooth');
                const serviceId = $(`.tooth-service-id[data-tooth="${tooth}"]`).val();
                
                let doctor = $(`.tooth-service-id[data-tooth="${tooth}"]`).attr('data-doctor');
                if (!doctor && serviceId) doctor = medicoActual;

                if (serviceId) {
                    let vinculada = false;
                    $('.sync-old').each(function() {
                        if ($(this).find('.p-diente').val() == tooth && $(this).find('.p-servicio').val() == serviceId && !$(this).hasClass('sync-processed')) {
                            $(this).addClass('sync-processed');
                            vinculada = true;
                            return false;
                        }
                    });

                    if (!vinculada) {
                        const serviceData = serviciosData.find(s => s.id == serviceId);
                        agregarFilaPago({
                            fecha: new Date().toISOString().split('T')[0],
                            diente: tooth,
                            servicio_id: serviceId,
                            doctor_nombre: doctor,
                            base: serviceData ? serviceData.precio : 0,
                            ajuste: 0,
                            pago: 0,
                            metodo: 'Efectivo'
                        });
                    }
                }
            });
            $('.sync-old').removeClass('sync-old sync-processed');
            calcularTotalesPagos();
            mostrarNotificacion('Registro de pagos actualizado con los datos del odontograma.', 'info');
        }

        let selectedRealizedTreatments = []; // Global para almacenar tratamientos seleccionados como "realizados"

        function renderRealizedTreatmentsList() {
            const tbody = $('#realized-treatments-list-body');
            tbody.empty();
            if (selectedRealizedTreatments.length === 0) {
                tbody.append('<tr><td colspan="5" class="text-center text-muted">No hay tratamientos seleccionados para cobro en esta cuenta.</td></tr>');
                return;
            }
            selectedRealizedTreatments.forEach(item => {
                const totalVal = parseFloat(item.total) || 0;
                tbody.append(`
                    <tr>
                        <td><small>${item.fecha_aplicacion}</small></td>
                        <td class="text-center">${item.diente || '--'}</td>
                        <td>${item.servicio_nombre}</td>
                        <td><small>${item.doctor_nombre || 'N/A'}</small></td>
                        <td class="text-right font-weight-bold text-accent">$${totalVal.toLocaleString('es-MX', {minimumFractionDigits: 2})}</td>
                    </tr>
                `);
            });
        }

        function abrirModalSeleccionarTratamientos() {
            const tbody = $('#modal-realizar-tratamientos-body');
            tbody.empty();

            // Obtener los ítems del presupuesto actual
            const presupuestoItems = [];
            $('#presupuesto-body tr').each(function() {
                const diente = $(this).find('.b-diente').val() || '';
                const serviceId = $(this).find('.b-servicio-id').val();
                const serviceName = $(this).find('.b-servicio-search').val();
                const doctor = $(this).find('.b-doctor').val();
                const base = parseFloat($(this).find('.b-base').val()) || 0;
                const descuento = parseFloat($(this).find('.b-descuento').val()) || 0;
                const totalItem = base - descuento;
                const cita = $(this).find('.b-cita').val();
                const rowId = $(this).attr('id');

                presupuestoItems.push({
                    diente: diente,
                    servicio_id: serviceId,
                    servicio_nombre: serviceName,
                    doctor_nombre: doctor,
                    base: base,
                    descuento: descuento,
                    total: totalItem,
                    cita: cita,
                    row_id: rowId
                });
            });

            presupuestoItems.forEach(item => {
                const isSelected = selectedRealizedTreatments.some(rt => rt.row_id === item.row_id);
                const fechaAplicacion = isSelected ? selectedRealizedTreatments.find(rt => rt.row_id === item.row_id).fecha_aplicacion : new Date().toISOString().split('T')[0];

                tbody.append(`
                    <tr>
                        <td><input type="checkbox" class="realizar-checkbox" data-row-id="${item.row_id}" ${isSelected ? 'checked' : ''}></td>
                        <td>${item.diente || '--'}</td>
                        <td>${item.servicio_nombre}</td>
                        <td>${item.doctor_nombre || 'N/A'}</td>
                        <td class="text-right">$${item.total.toLocaleString('es-MX', {minimumFractionDigits: 2})}</td>
                        <td><input type="date" class="form-control form-control-sm fecha-aplicacion-input" value="${fechaAplicacion}" ${!isSelected ? 'disabled' : ''}></td>
                    </tr>
                `);
            });

            // Habilitar/deshabilitar fecha de aplicación al marcar checkbox
            $(document).on('change', '.realizar-checkbox', function() {
                $(this).closest('tr').find('.fecha-aplicacion-input').prop('disabled', !this.checked);
            });

            $('#modalSeleccionarTratamientos').modal('show');
        }

        function confirmarSeleccionTratamientos() {
            selectedRealizedTreatments = [];
            $('#modal-realizar-tratamientos-body tr').each(function() {
                const checkbox = $(this).find('.realizar-checkbox');
                if (checkbox.is(':checked')) {
                    const rowId = checkbox.data('row-id');
                    const fechaAplicacion = $(this).find('.fecha-aplicacion-input').val();
                    const originalRow = $('#' + rowId);

                    if (originalRow.length) {
                        const base = parseFloat(originalRow.find('.b-base').val()) || 0;
                        const descuento = parseFloat(originalRow.find('.b-descuento').val()) || 0;
                        const total = base - descuento;

                        selectedRealizedTreatments.push({
                            row_id: rowId,
                            diente: originalRow.find('.b-diente').val() || '',
                            servicio_id: originalRow.find('.b-servicio-id').val(),
                            servicio_nombre: originalRow.find('.b-servicio-search').val(),
                            doctor_nombre: originalRow.find('.b-doctor').val(),
                            base: base,
                            descuento: descuento,
                            total: total,
                            cita: originalRow.find('.b-cita').val() || '',
                            fecha_aplicacion: fechaAplicacion
                        });
                    }
                }
            });
            $('#modalSeleccionarTratamientos').modal('hide');
            renderRealizedTreatmentsList();
            calcularTotalesPagos(); // Recalcular los totales de pagos con los nuevos tratamientos realizados
        }

        async function guardarTodo() {
            const dpSuccess = await guardarDatosPersonales();
            if (!dpSuccess) return;

            const odSuccess = await guardarOdontograma();
            const prSuccess = await guardarPresupuesto();
            const pgSuccess = await guardarPagos();
                        const docSuccess = await guardarDocumento(); // Se llama de forma segura

            
            if (odSuccess && prSuccess && pgSuccess && docSuccess) 
                mostrarNotificacion('Expediente, presupuesto y pagos guardados correctamente.', 'success');
        }

        async function guardarDatosPersonales() {
            const formData = new FormData(document.getElementById('formDatosPersonales'));
            const origenSelect = document.getElementById('dp_origen');
            const selectedOption = origenSelect.options[origenSelect.selectedIndex];

            if (origenSelect.value === 'DOCTOR') {
                const nombreDoc = formData.get('recomendado_doctor_nombre');
                formData.set('origen', 'DOCTOR: ' + (nombreDoc ? nombreDoc.trim() : ''));
                formData.set('recomendado_por_id', '');
            } else if (origenSelect.value === 'PERSONA') {
                const nombrePersona = formData.get('recomendado_doctor_nombre');
                formData.set('origen', 'PERSONA: ' + (nombrePersona ? nombrePersona.trim() : ''));
                formData.set('recomendado_por_id', '');
            } else if (origenSelect.value !== "") {
                formData.set('recomendado_por_id', origenSelect.value);
                formData.set('origen', selectedOption ? selectedOption.dataset.nombre : "");
            } else {
                formData.set('recomendado_por_id', '');
            }
            
            const data = Object.fromEntries(formData.entries());
            const response = await fetch('guardar_expediente.php?seccion=datos_personales', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            return result.success;
        }

        async function guardarOdontograma() {
            const teethTrats = {};
            $('.tooth-search-input').each(function() {
                const tooth = $(this).data('tooth');
                const hiddenInput = $(this).siblings('.tooth-service-id');
                const serviceId = hiddenInput.val();
                const serviceName = $(this).val().trim();
                const doctor = hiddenInput.attr('data-doctor');
                if (serviceName !== '') {
                    if (!teethTrats[tooth]) teethTrats[tooth] = [];
                    teethTrats[tooth].push({
                        service_id: serviceId || 'OTHER',
                        service_name: serviceName,
                        doctor_nombre: doctor || medicoActual
                    });
                }
            });

            const generalTrats = [];
            $('#general-treatments-body tr').each(function() {
                const serviceId = $(this).find('.general-service-id').val();
                const serviceName = $(this).find('.general-search-input').val().trim();
                const doctor = $(this).find('.general-doctor').val();
                if (serviceName !== '') {
                    generalTrats.push({
                        service_id: serviceId || 'OTHER',
                        nombre: serviceName,
                        doctor_nombre: doctor || medicoActual
                    });
                }
            });

            const data = {
                paciente_id: pacienteId,
                tratamientos: {
                    teeth: teethTrats,
                    general: generalTrats,
                },
                observaciones: $('#observaciones_dentista').val()
            };

            const response = await fetch('guardar_expediente.php?seccion=odontograma', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            // No mostrar notificación aquí, se muestra en guardarTodo
            const result = await response.json();
            if (!result.success) mostrarNotificacion('Error al guardar odontograma.', 'error');
            return result.success;
        }

        async function guardarPresupuesto() {
            const items = [];
            $('#presupuesto-body tr').each(function() {
                items.push({
                    local_id: $(this).find('.b-local-id').val(),
                    diente: $(this).find('.b-diente').val(),
                    servicio_id: $(this).find('.b-servicio-id').val(),
                    servicio_nombre: $(this).find('.b-servicio-search').val(),
                    doctor_nombre: $(this).find('.b-doctor').val(),
                    base: $(this).find('.b-base').val(),
                    descuento: $(this).find('.b-descuento').val(),
                    cita: $(this).find('.b-cita').val(),
                    realizado: $(this).find('.b-realizado').is(':checked') ? 1 : 0
                });
            });

            const data = {
                paciente_id: pacienteId,
                presupuesto: {
                    fecha: $('#presupuesto_fecha').val(),
                    items: items
                },
                realized_treatments: selectedRealizedTreatments // Guardar los tratamientos realizados
            };

            const response = await fetch('guardar_expediente.php?seccion=presupuesto_dental', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            // No mostrar notificación aquí, se muestra en guardarTodo
            const result = await response.json();
            return result.success;
        }

         async function guardarPagos() {
            const items = [];
            $('#pagos-body tr').each(function() {
                items.push({
                    fecha: $(this).find('.p-fecha').val(),
                    pago: $(this).find('.p-total-abono').val(), // Campo total para reportes
                    monto_efectivo: $(this).find('.p-efe').val(),
                    monto_tarjeta_credito: $(this).find('.p-tcred').val(),
                    monto_tarjeta_debito: $(this).find('.p-tdeb').val(),
                    monto_dlls: $(this).find('.p-dlls').val(),
                    monto_transferencia: $(this).find('.p-tra').val(),
                    monto_tipo_cambio: $('#tipo_cambio_dolar').val(),
                    bloqueado: $(this).hasClass('row-bloqueada') ? 1 : 0
                });
            });

            const response = await fetch('guardar_expediente.php?seccion=registro_pagos_dental', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ paciente_id: pacienteId, pagos: items })
            });
            // No mostrar notificación aquí, se muestra en guardarTodo
            const result = await response.json();
            return result.success;
        }

        async function guardarDocumento() {
            const form = document.getElementById('formDocumento');
            const fileInput = form.querySelector('input[name="archivo"]');
            
            // Si no hay archivo seleccionado, no es un error, simplemente retornamos éxito
            if (!fileInput.files || fileInput.files.length === 0) {
                return true; 
            }
            const formData = new FormData(form);
            
            // Validación
             if (!formData.get('tipo_documento')) {
                mostrarNotificacion('Especifique el tipo de documento antes de subir.', 'error');
                return false;
            }

            const response = await fetch('guardar_expediente.php?seccion=documento', {
                method: 'POST',
                body: formData // No se usa JSON.stringify para FormData
            });
            const result = await response.json();
            if (result.success) {
                form.reset();
                cargarExpediente(); // Recargar todos los datos, incluyendo documentos
                mostrarNotificacion('Documento subido exitosamente.', 'success');
            } else {
                mostrarNotificacion('Error al subir el documento: ' + result.error, 'error');
            }
        }

        function renderDocumentos(documentos) {
            const container = $('#listaDocumentos');
            container.empty(); // Limpiar antes de renderizar
            if (documentos.length === 0) {
                container.html('<p class="text-muted">No hay documentos adjuntos.</p>');
                return;
            }
            let html = '';
            documentos.forEach(d => {
                html += `<div class="document-list-item">
                    <div>
                        <i class="fas fa-file-alt mr-2"></i>
                        <strong>${d.nombre_documento}</strong>
                        <small class="text-muted d-block">Tipo: ${d.tipo_documento || 'General'} | Subido: ${new Date(d.fecha_carga).toLocaleDateString()}</small>
                    </div>
                    <a href="${d.ruta_archivo}" target="_blank" class="btn btn-sm btn-outline-primary">Ver</a>
                </div>`;
            });
            container.html(html);
        }

        function verificarOrigenDoctorExpediente() {
            const val = $('#dp_origen').val();
            const label = $('#dp_recomendado_doctor_label');
            if (val === 'DOCTOR') {
                label.text('Nombre del Doctor');
                $('#dp_recomendado_doctor_nombre').attr('placeholder', 'Nombre del doctor');
                $('#dp_recomendado_doctor_div').fadeIn();
            } else if (val === 'PERSONA') {
                label.text('Nombre de la Persona');
                $('#dp_recomendado_doctor_nombre').attr('placeholder', 'Nombre de la persona');
                $('#dp_recomendado_doctor_div').fadeIn();
            } else {
                $('#dp_recomendado_doctor_div').hide();
                $('#dp_recomendado_doctor_nombre').val('');
            }
        }

        function generarQRMedico() {
            const url = window.location.href; // URL actual con el ID del paciente
            mostrarQR(url, "Expediente Completo (Médico)");
        }

        function generarQRPaciente() {
            // Generamos una URL pública simplificada para el paciente
            const baseUrl = window.location.origin + window.location.pathname.replace('expediente_dentista.php', 'ver_presupuesto_paciente.php');
            const url = `${baseUrl}?id=${pacienteId}&t=${btoa(pacienteId).substring(0, 8)}`;
            mostrarQR(url, "Presupuesto para el Paciente");
        }

        function mostrarQR(url, title) {
            const qrApi = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(url)}`;
            $('#qrTitle').text(title);
            $('#qrImage').attr('src', qrApi);
            $('#qrText').text("Enlace: " + url.substring(0, 30) + "...");
            $('#modalQR').modal('show');
        }

        function imprimirPresupuesto() {
            // Guardamos primero para asegurar que los datos actuales se impriman
            guardarPresupuesto().then(success => {
                const printUrl = `imprimir_presupuesto.php?id=${pacienteId}`;
                window.open(printUrl, '_blank');
            });
        }

        function mostrarNotificacion(mensaje, tipo = 'info') {
            const container = document.getElementById('notification-container');
            const toast = document.createElement('div');
            toast.className = `toast-message ${tipo}`;
            toast.textContent = mensaje;
            container.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => container.removeChild(toast), 500);
            }, 5000);
        }

        // Modal para seleccionar tratamientos realizados
        // Este modal debe ir al final del body, antes del cierre de </body>
        // <div class="modal fade" id="modalSeleccionarTratamientos" tabindex="-1" role="dialog" aria-labelledby="modalSeleccionarTratamientosLabel" aria-hidden="true">
        //     <div class="modal-dialog modal-lg" role="document">
        //         <div class="modal-content">
        //             <div class="modal-header">
        //                 <h5 class="modal-title" id="modalSeleccionarTratamientosLabel">Seleccionar Tratamientos Realizados</h5>
        //                 <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        //                     <span aria-hidden="true">&times;</span>
        //                 </button>
        //             </div>
        //             <div class="modal-body">
        //                 <p class="text-muted">Selecciona los tratamientos del presupuesto que ya han sido aplicados o se van a pagar.</p>
        //                 <div class="table-responsive">
        //                     <table class="table table-dark budget-table">
        //                         <thead>
        //                             <tr>
        //                                 <th style="width: 50px;"></th>
        //                                 <th style="width: 80px;">Diente</th>
        //                                 <th>Tratamiento</th>
        //                                 <th>Doctor</th>
        //                                 <th class="text-right">Total ($)</th>
        //                                 <th style="width: 150px;">Fecha Aplicación</th>
        //                             </tr>
        //                         </thead>
        //                         <tbody id="modal-realizar-tratamientos-body">
        //                             <!-- Contenido dinámico -->
        //                         </tbody>
        //                     </table>
        //                 </div>
        //             </div>
        //             <div class="modal-footer">
        //                 <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        //                 <button type="button" class="btn btn-primary" onclick="confirmarSeleccionTratamientos()">Confirmar Selección</button>
        //             </div>
        //         </div>
        //     </div>
        // </div>
    </script>
</body>
</html>

<!-- Modal para Seleccionar Tratamientos Realizados -->
<div class="modal fade" id="modalSeleccionarTratamientos" tabindex="-1" role="dialog" aria-labelledby="modalSeleccionarTratamientosLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalSeleccionarTratamientosLabel">Seleccionar Tratamientos Realizados</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Selecciona los tratamientos del presupuesto que ya han sido aplicados o se van a pagar.</p>
                <div class="table-responsive">
                    <table class="table table-dark budget-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;"></th>
                                <th style="width: 80px;">Diente</th>
                                <th>Tratamiento</th>
                                <th>Doctor</th>
                                <th class="text-right">Total ($)</th>
                                <th style="width: 150px;">Fecha Aplicación</th>
                            </tr>
                        </thead>
                        <tbody id="modal-realizar-tratamientos-body">
                            <!-- Contenido dinámico -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarSeleccionTratamientos()">Confirmar Selección</button>
            </div>
        </div>
    </div>
</div>