<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!puedeRealizar('gestionar_pacientes')) {
    header('Location: index.php');
    exit;
}

$paciente_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($paciente_id === 0) {
    die("ID de paciente no válido.");
}

// Obtener paciente y validar scope según permisos
$stmt = $conn->prepare("SELECT nombre, apellido, fecha_nacimiento, usuario_id FROM portal_pacientes WHERE id = ?");
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$result = $stmt->get_result();
$paciente = $result->fetch_assoc();
$stmt->close();

// Validar permisos de acceso al expediente
$allowed = obtenerIdsPermitidos();
if ($paciente) {
    $owner = intval($paciente['usuario_id'] ?? 0);
    if ($allowed === null) {
        // ok
    } elseif (is_array($allowed) && in_array('PARENT_ONLY', $allowed)) {
        $parent = $_SESSION['id_padre'] ?? null;
        if (!$parent || $owner !== intval($parent)) { die('Acceso denegado.'); }
    } elseif (is_array($allowed) && in_array('SELF_AND_CHILDREN', $allowed)) {
        $self = $_SESSION['usuario_id'] ?? 0;
        if ($owner !== intval($self)) {
            $stmtC = $conn->prepare("SELECT COUNT(*) as c FROM agenda_usuarios WHERE id = ? AND id_padre = ?");
            $stmtC->bind_param('ii', $owner, $self);
            $stmtC->execute(); $r = $stmtC->get_result()->fetch_assoc(); $stmtC->close();
            if (intval($r['c']) === 0) die('Acceso denegado.');
        }
    } elseif (is_array($allowed) && count($allowed) > 0) {
        $allowed_ints = array_map('intval', $allowed);
        if (!in_array($owner, $allowed_ints)) die('Acceso denegado.');
    }
}
if (!$paciente) {
    die("Paciente no encontrado.");
}
$nombre_completo = trim($paciente['nombre'] . ' ' . $paciente['apellido']);
$seed = urlencode(($paciente['nombre'] ?? '') . ($paciente['apellido'] ?? ''));
$avatar_url = "https://api.dicebear.com/7.x/identicon/svg?seed={$seed}&backgroundColor=transparent";

$show_back = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expediente Clínico - <?php echo htmlspecialchars($nombre_completo); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #000000;
            color: #e5e7eb;
            padding-top: 100px;
        }
        .main-header {
            background: rgba(5, 5, 5, 0.95);
            backdrop-filter: blur(10px);
            color: white;
            height: 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5);
            border-bottom: 1px solid rgba(41, 121, 255, 0.1);
        }
        .header-left, .header-right { display: flex; align-items: center; gap: 15px; }
        .btn-header {
            color: #e5e7eb;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn-header:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.25);
            box-shadow: none;
        }
        .container-custom { max-width: 1400px; margin: 0 auto; padding: 0 15px; }
        .page-title { font-size: 2rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; }
        .nav-tabs .nav-link {
            background: #0a0a0a; border: 1px solid #333; color: #9ca3af;
            border-bottom: none; border-radius: 8px 8px 0 0;
        }
        .nav-tabs .nav-link.active { background: #111; color: #2979ff; border-color: #333 #333 #111; }
        .tab-content { background: #0a0a0a; border: 1px solid #333; border-top: none; padding: 2rem; border-radius: 0 0 16px 16px; }
        .form-control, .form-control:focus { background: #000; border: 1px solid #333; color: #e5e7eb; border-radius: 8px; }
        .form-control:focus { border-color: #2979ff; box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2); }
        label { font-weight: 500; }
        /* Botones estilo Catálogo */
        .btn-primary {
            background: #2979ff;
            border-color: #2979ff;
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.3);
            font-weight: 600;
        }
        .btn-primary:hover {
            background: #2962ff;
            border-color: #2962ff;
            box-shadow: 0 0 15px rgba(41, 121, 255, 0.5);
        }
        .btn-secondary {
            background: #1f2937;
            border-color: #374151;
            color: #e5e7eb;
        }
        .btn-secondary:hover {
            background: #374151;
            border-color: #4b5563;
        }
        .document-list-item {
            background: #111; padding: 1rem; border-radius: 8px;
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 0.5rem; border: 1px solid #333;
        }

        /* Notificaciones Toast */
        #notification-container {
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 1060;
            width: 300px;
        }
        .toast-message {
            background-color: #1f2937;
            color: #e5e7eb;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            border-left: 5px solid #6b7280;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.4s cubic-bezier(0.215, 0.610, 0.355, 1);
        }
        .toast-message.show {
            opacity: 1;
            transform: translateX(0);
        }
        .toast-message.success { border-left-color: #10b981; }
        .toast-message.error { border-left-color: #ef4444; }
        .toast-message.info { border-left-color: #3b82f6; }

        /* Estilos optimizados para impresión */
        @media print {
            .main-header, .nav-tabs, #formSignosVitales, #formNotaEvolucion, #formDocumento, .btn-header, .btn-sm, #notification-container { display: none !important; }
            body { background-color: white !important; color: black !important; padding-top: 20px !important; }
            .container-custom { width: 100% !important; max-width: none !important; padding: 0 !important; }
            .tab-content { background: white !important; border: none !important; padding: 0 !important; }
            .tab-pane { display: block !important; opacity: 1 !important; border: none !important; page-break-after: always; visibility: visible !important; }
            .form-control, textarea { border: none !important; color: black !important; background: white !important; padding: 5px 0 !important; height: auto !important; resize: none !important; }
        }

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
                    <small class="text-muted">Expediente Clínico</small>
                </div>
            </div>
        </div>
        <div class="header-right">
            <button class="btn-header mr-2" onclick="window.print()">
                <i class="fas fa-print"></i> Imprimir
            </button>
            <button class="btn-header" onclick="guardarTodo()">
                <i class="fas fa-save"></i> Guardar Todo
            </button>
        </div>
    </header>

    <div class="container-custom mt-4">
        <!-- Cabecera exclusiva para el documento impreso -->
        <div class="d-none d-print-block mb-4 text-dark">
            <h2 class="mb-1">EXPEDIENTE CLÍNICO</h2>
            <h4 class="mb-2"><?php echo htmlspecialchars($nombre_completo); ?></h4>
            <p class="mb-0">Fecha de impresión: <?php echo date('d/m/Y H:i'); ?> | Consultorio Médico San José</p>
            <hr style="border-top: 2px solid #000;">
        </div>

        <ul class="nav nav-tabs" id="expedienteTab" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" id="personales-tab" data-toggle="tab" href="#personales" role="tab">Datos Personales</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="historia-tab" data-toggle="tab" href="#historia" role="tab">Historia Clínica</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="signos-tab" data-toggle="tab" href="#signos" role="tab">Signos Vitales</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="evolucion-tab" data-toggle="tab" href="#evolucion" role="tab">Notas de Evolución</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="documentos-tab" data-toggle="tab" href="#documentos" role="tab">Documentos</a>
            </li>
        </ul>

        <div class="tab-content" id="expedienteTabContent">
            <!-- Tab Datos Personales -->
            <div class="tab-pane fade show active" id="personales" role="tabpanel">
                <form id="formDatosPersonales">
                    <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="dp_nombre">Nombre(s)</label>
                            <input type="text" class="form-control" id="dp_nombre" name="nombre">
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="dp_apellido">Apellido(s)</label>
                            <input type="text" class="form-control" id="dp_apellido" name="apellido">
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="dp_fecha_nacimiento">Fecha de Nacimiento</label>
                            <input type="date" class="form-control" id="dp_fecha_nacimiento" name="fecha_nacimiento">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="dp_curp">CURP</label>
                            <input type="text" class="form-control" id="dp_curp" name="curp" maxlength="18" style="text-transform:uppercase;">
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="dp_telefono">Teléfono Celular</label>
                            <input type="tel" class="form-control" id="dp_telefono" name="telefono">
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="dp_correo">Correo Electrónico</label>
                            <input type="email" class="form-control" id="dp_correo" name="correo">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="dp_direccion">Dirección</label>
                        <textarea class="form-control" id="dp_direccion" name="direccion" rows="3"></textarea>
                    </div>
                </form>
            </div>

            <!-- Tab Historia Clínica -->
            <div class="tab-pane fade" id="historia" role="tabpanel">
                <form id="formHistoriaClinica">
                    <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="antecedentes_heredofamiliares">Antecedentes Heredo-familiares</label>
                            <textarea class="form-control" id="antecedentes_heredofamiliares" name="antecedentes_heredofamiliares" rows="4"></textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="antecedentes_personales_patologicos">Antecedentes Personales Patológicos</label>
                            <textarea class="form-control" id="antecedentes_personales_patologicos" name="antecedentes_personales_patologicos" rows="4"></textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="antecedentes_personales_no_patologicos">Antecedentes Personales No Patológicos</label>
                            <textarea class="form-control" id="antecedentes_personales_no_patologicos" name="antecedentes_personales_no_patologicos" rows="4"></textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="padecimiento_actual">Padecimiento Actual</label>
                            <textarea class="form-control" id="padecimiento_actual" name="padecimiento_actual" rows="4"></textarea>
                        </div>
                        <div class="col-md-12 form-group">
                            <label for="exploracion_fisica">Exploración Física</label>
                            <textarea class="form-control" id="exploracion_fisica" name="exploracion_fisica" rows="5"></textarea>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="diagnostico_principal">Diagnóstico Principal</label>
                            <input type="text" class="form-control" id="diagnostico_principal" name="diagnostico_principal">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="otros_diagnosticos">Otros Diagnósticos</label>
                            <textarea class="form-control" id="otros_diagnosticos" name="otros_diagnosticos" rows="2"></textarea>
                        </div>
                         <div class="col-md-6 form-group">
                            <label for="plan_tratamiento">Plan de Tratamiento</label>
                            <textarea class="form-control" id="plan_tratamiento" name="plan_tratamiento" rows="3"></textarea>
                        </div>
                         <div class="col-md-6 form-group">
                            <label for="pronostico">Pronóstico</label>
                            <textarea class="form-control" id="pronostico" name="pronostico" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tab Signos Vitales -->
            <div class="tab-pane fade" id="signos" role="tabpanel">
                <h5>Registrar Nuevos Signos Vitales</h5>
                <form id="formSignosVitales" class="mb-4">
                     <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">
                    <div class="row">
                        <div class="col-md-2 form-group"><label>P. Arterial</label><input type="text" name="presion_arterial" class="form-control" placeholder="120/80"></div>
                        <div class="col-md-2 form-group"><label>F. Cardiaca</label><input type="number" name="frecuencia_cardiaca" class="form-control" placeholder="lpm"></div>
                        <div class="col-md-2 form-group"><label>F. Resp.</label><input type="number" name="frecuencia_respiratoria" class="form-control" placeholder="rpm"></div>
                        <div class="col-md-2 form-group"><label>Temp. °C</label><input type="number" step="0.1" name="temperatura_celsius" class="form-control"></div>
                        <div class="col-md-2 form-group"><label>Peso (kg)</label><input type="number" step="0.1" name="peso_kg" class="form-control"></div>
                        <div class="col-md-2 form-group"><label>Talla (cm)</label><input type="number" step="0.1" name="talla_cm" class="form-control"></div>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm">Agregar Registro</button>
                </form>
                <hr>
                <h5>Historial de Signos Vitales</h5>
                <div id="historialSignosVitales" class="table-responsive"></div>
            </div>

            <!-- Tab Notas de Evolución -->
            <div class="tab-pane fade" id="evolucion" role="tabpanel">
                <h5>Agregar Nota de Evolución (Formato SOAP)</h5>
                <form id="formNotaEvolucion" class="mb-4">
                    <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">
                    <div class="form-group">
                        <label>S (Subjetivo): ¿Qué dice el paciente?</label>
                        <textarea name="nota_subjetivo" class="form-control" rows="2"></textarea>
                    </div>
                     <div class="form-group">
                        <label>O (Objetivo): Hallazgos, signos vitales, etc.</label>
                        <textarea name="nota_objetivo" class="form-control" rows="2"></textarea>
                    </div>
                     <div class="form-group">
                        <label>A (Análisis): Interpretación y diagnóstico diferencial.</label>
                        <textarea name="nota_analisis" class="form-control" rows="2"></textarea>
                    </div>
                     <div class="form-group">
                        <label>P (Plan): Pasos a seguir, estudios, tratamiento.</label>
                        <textarea name="nota_plan" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm">Agregar Nota</button>
                </form>
                <hr>
                <h5>Historial de Notas</h5>
                <div id="historialNotasEvolucion"></div>
            </div>

            <!-- Tab Documentos -->
            <div class="tab-pane fade" id="documentos" role="tabpanel">
                <h5>Subir Nuevo Documento</h5>
                <form id="formDocumento" class="mb-4" enctype="multipart/form-data">
                    <input type="hidden" name="paciente_id" value="<?php echo $paciente_id; ?>">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Tipo de Documento</label>
                            <input type="text" name="tipo_documento" class="form-control" placeholder="Ej: Laboratorio, INE, Orden Médica">
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
                <div id="listaDocumentos"></div>
            </div>
        </div>
    </div>

    <div id="notification-container"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const pacienteId = <?php echo $paciente_id; ?>;

        $(document).ready(function() {
            cargarExpediente();
            
            $('#formDatosPersonales').on('submit', function(e) { e.preventDefault(); guardarDatosPersonales(); });
            $('#formHistoriaClinica').on('submit', function(e) { e.preventDefault(); guardarHistoriaClinica(); });
            $('#formSignosVitales').on('submit', function(e) { e.preventDefault(); guardarSignosVitales(); });
            $('#formNotaEvolucion').on('submit', function(e) { e.preventDefault(); guardarNotaEvolucion(); });
            $('#formDocumento').on('submit', function(e) { e.preventDefault(); guardarDocumento(); });
        });

        async function guardarTodo() {
            // Guardar las dos pestañas principales de datos. Las otras son para añadir registros.
            const dpSuccess = await guardarDatosPersonales();
            if (!dpSuccess) return; // Detener si falla la validación de datos personales

            const hcSuccess = await guardarHistoriaClinica();
            if (!hcSuccess) return; // Detener si falla la validación de historia clínica

            mostrarNotificacion('Cambios guardados exitosamente.', 'success');
        }

        async function cargarExpediente() {
            try {
                const response = await fetch(`get_expediente.php?paciente_id=${pacienteId}`);
                if (!response.ok) throw new Error('Error al cargar los datos del expediente.');
                
                const data = await response.json();

                // Cargar Datos Personales
                if (data.datos_personales) {
                    const dp = data.datos_personales;
                    document.getElementById('dp_nombre').value = dp.nombre || '';
                    document.getElementById('dp_apellido').value = dp.apellido || '';
                    document.getElementById('dp_fecha_nacimiento').value = dp.fecha_nacimiento || '';
                    document.getElementById('dp_curp').value = dp.curp || '';
                    document.getElementById('dp_telefono').value = dp.telefono || '';
                    document.getElementById('dp_correo').value = dp.correo || '';
                    document.getElementById('dp_direccion').value = dp.direccion || '';
                }

                // Cargar Historia Clínica
                if (data.historia_clinica) {
                    for (const key in data.historia_clinica) {
                        const element = document.getElementById(key);
                        if (element) {
                            element.value = data.historia_clinica[key] || '';
                        }
                    }
                }

                // Cargar Signos Vitales
                renderSignosVitales(data.signos_vitales || []);

                // Cargar Notas de Evolución
                renderNotasEvolucion(data.notas_evolucion || []);

                // Cargar Documentos
                renderDocumentos(data.documentos || []);

            } catch (error) {
                console.error(error);
                alert(error.message);
            }
        }

        function renderSignosVitales(signos) {
            const container = $('#historialSignosVitales');
            if (signos.length === 0) {
                container.html('<p class="text-muted">No hay registros de signos vitales.</p>');
                return;
            }
            let table = '<table class="table table-dark table-sm"><thead><tr><th>Fecha</th><th>P.A.</th><th>F.C.</th><th>F.R.</th><th>Temp</th><th>Peso</th><th>Talla</th><th>Notas</th></tr></thead><tbody>';
            signos.forEach(s => {
                table += `<tr>
                    <td>${new Date(s.fecha_toma).toLocaleString()}</td>
                    <td>${s.presion_arterial || '-'}</td>
                    <td>${s.frecuencia_cardiaca || '-'}</td>
                    <td>${s.frecuencia_respiratoria || '-'}</td>
                    <td>${s.temperatura_celsius || '-'}</td>
                    <td>${s.peso_kg || '-'}</td>
                    <td>${s.talla_cm || '-'}</td>
                    <td>${s.notas || ''}</td>
                </tr>`;
            });
            table += '</tbody></table>';
            container.html(table);
        }

        function renderNotasEvolucion(notas) {
            const container = $('#historialNotasEvolucion');
             if (notas.length === 0) {
                container.html('<p class="text-muted">No hay notas de evolución.</p>');
                return;
            }
            let html = '';
            notas.forEach(n => {
                html += `<div class="document-list-item">
                    <div>
                        <strong>${new Date(n.fecha_nota).toLocaleString()}</strong> por ${n.usuario_nombre || 'N/A'}
                        <p class="mb-0 mt-2"><strong>S:</strong> ${n.nota_subjetivo || ''}</p>
                        <p class="mb-0"><strong>O:</strong> ${n.nota_objetivo || ''}</p>
                        <p class="mb-0"><strong>A:</strong> ${n.nota_analisis || ''}</p>
                        <p class="mb-0"><strong>P:</strong> ${n.nota_plan || ''}</p>
                    </div>
                </div>`;
            });
            container.html(html);
        }

        function renderDocumentos(documentos) {
            const container = $('#listaDocumentos');
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

        function mostrarNotificacion(mensaje, tipo = 'info') {
            const container = document.getElementById('notification-container');
            const toast = document.createElement('div');
            toast.className = `toast-message ${tipo}`;
            toast.textContent = mensaje;
            container.appendChild(toast);
            setTimeout(() => {
                toast.classList.add('show');
            }, 10); // Pequeño delay para que la transición se aplique

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => container.removeChild(toast), 500);
            }, 5000);
        }

        async function guardarDatosPersonales() {
            const form = document.getElementById('formDatosPersonales');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            const response = await fetch('guardar_expediente.php?seccion=datos_personales', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (!result.success) {
                mostrarNotificacion('Error al guardar datos personales: ' + result.error, 'error');
                return false;
            } else {
                // Actualizar el nombre en el header si cambió
                const nombreCompleto = (data.nombre || '') + ' ' + (data.apellido || '');
                document.querySelector('.main-header .text-muted').textContent = nombreCompleto.trim();
                mostrarNotificacion('Datos personales guardados.', 'success');
                return true;
            }
        }

        async function guardarHistoriaClinica() {
            const form = document.getElementById('formHistoriaClinica');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            const response = await fetch('guardar_expediente.php?seccion=historia_clinica', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (!result.success) {
                mostrarNotificacion('Error al guardar la historia clínica: ' + result.error, 'error');
                return false;
            }
            return true;
        }

        async function guardarSignosVitales() {
            const form = document.getElementById('formSignosVitales');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            // Validación: al menos un campo debe tener valor
            if (!data.presion_arterial && !data.frecuencia_cardiaca && !data.temperatura_celsius && !data.peso_kg) {
                mostrarNotificacion('Debe ingresar al menos un signo vital para agregar un registro.', 'error');
                return;
            }

            const response = await fetch('guardar_expediente.php?seccion=signos_vitales', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) {
                form.reset();
                cargarExpediente();
                mostrarNotificacion('Signos vitales agregados.', 'success');
            } else {
                mostrarNotificacion('Error al guardar signos vitales: ' + result.error, 'error');
            }
        }

        async function guardarNotaEvolucion() {
            const form = document.getElementById('formNotaEvolucion');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            // Validación: al menos el campo subjetivo o el de análisis debe tener contenido.
            if (!data.nota_subjetivo && !data.nota_analisis) {
                mostrarNotificacion('Para agregar una nota, debe llenar al menos los campos "Subjetivo" o "Análisis".', 'error');
                return;
            }

            const response = await fetch('guardar_expediente.php?seccion=nota_evolucion', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            if (result.success) {
                form.reset();
                cargarExpediente();
                mostrarNotificacion('Nota de evolución agregada.', 'success');
            } else {
                mostrarNotificacion('Error al guardar la nota: ' + result.error, 'error');
            }
        }

        async function guardarDocumento() {
            const form = document.getElementById('formDocumento');
            const formData = new FormData(form);
            
            // Validación
            if (!formData.get('archivo').name || !formData.get('tipo_documento')) {
                mostrarNotificacion('Debe seleccionar un archivo y especificar el tipo de documento.', 'error');
                return;
            }

            const response = await fetch('guardar_expediente.php?seccion=documento', {
                method: 'POST',
                body: formData // No se usa JSON.stringify para FormData
            });
            const result = await response.json();
            if (result.success) {
                form.reset();
                cargarExpediente();
                mostrarNotificacion('Documento subido exitosamente.', 'success');
            } else {
                mostrarNotificacion('Error al subir el documento: ' + result.error, 'error');
            }
        }
    </script>
</body>
</html>