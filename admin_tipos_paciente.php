<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Verificar que solo los admins puedan acceder
if (!puedeRealizar('gestionar_usuarios')) {
    header('Location: index.php');
    exit;
}

$usuario = obtenerUsuarioActual();
$user_nombre = $usuario['nombre'] ?? 'Usuario';
$user_tipo = $usuario['tipo'] ?? 'usuario';

// Configuración del header
$show_calendar = true;
$show_back = true;
$show_admin_tools = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Tipos de Paciente - Hospital Angeles</title>
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
        
        /* Header Styles */
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
            font-family: Arial, sans-serif;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5);
            border-bottom: 1px solid rgba(41, 121, 255, 0.1);
        }
        
        .header-left, .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-section {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            text-align: center;
        }
        
        .header-logo img {
            max-height: 60px;
            margin-left: 10px;
            width: auto;
            filter: drop-shadow(1px 1px 2px rgba(0,0,0,0.1)) brightness(1.1);
        }
        
        .logo-text {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            letter-spacing: 0.5px;
            text-align: center;
        }
        
        .btn-header {
            color: #e5e7eb;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            background: rgba(41, 121, 255, 0.1);
            border: 1px solid rgba(41, 121, 255, 0.2);
            padding: 0.5rem 1rem;
            font-size: 13px;
            cursor: pointer;
            border-radius: 6px;
            text-shadow: 0 0 5px rgba(41, 121, 255, 0.3);
        }
        
        .btn-header:hover {
            background: rgba(41, 121, 255, 0.2);
            color: #fff;
            border-color: rgba(41, 121, 255, 0.5);
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.2);
        }

        .container-custom { max-width: 1200px; margin: 0 auto; padding: 0 15px; }
        
        .page-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.5);
        }

        .table-container {
            background: #0a0a0a;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            overflow-x: auto;
        }

        .actions-bar {
            background: #0a0a0a;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Estilos de Tabla Oscura */
        .table {
            color: #e5e7eb;
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #9ca3af;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 1rem;
            border-top: none;
        }

        .table td {
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding: 1rem;
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(41, 121, 255, 0.05);
            color: #e5e7eb;
        }

        /* Inputs y Buscador */
        .form-control {
            background: #000000;
            border: 1px solid #333;
            color: #e5e7eb;
            border-radius: 8px;
        }

        .form-control:focus {
            background: #000000;
            color: #fff;
            border-color: #2979ff;
            box-shadow: 0 0 0 2px rgba(41, 121, 255, 0.2);
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
        
        /* Modal Oscuro */
        .modal-content {
            background: #0a0a0a;
            border: 1px solid #333;
            color: #e5e7eb;
            box-shadow: 0 10px 40px rgba(0,0,0,0.7);
        }
        
        .modal-header {
            border-bottom: 1px solid #333;
            background: #111;
        }
        
        .modal-footer {
            border-top: 1px solid #333;
            background: #111;
        }
        
        .close {
            color: #e5e7eb;
            text-shadow: none;
            opacity: 0.7;
        }
        
        .close:hover {
            color: #fff;
            opacity: 1;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <div class="container-custom">
        <h1 class="page-title">Catálogo de Tipos de Paciente</h1>
        
        <div class="actions-bar">
            <a href="panel_admin.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
            <button class="btn btn-primary" onclick="abrirModalNuevo()">
                <i class="fas fa-plus"></i> Nuevo Tipo de Paciente
            </button>
        </div>
        
        <div class="table-container">
            <div id="loading" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>
            <table class="table table-hover" id="tipos-table" style="display: none;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Límite Citas Diarias</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tipos-tbody"></tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nuevo/Editar -->
    <div class="modal fade" id="modalTipoPaciente" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Tipo de Paciente</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="formTipoPaciente">
                        <input type="hidden" id="tipo_id" name="id">
                        <div class="form-group">
                            <label for="nombre">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="descripcion">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="citas_infinitas">
                                <label class="custom-control-label" for="citas_infinitas">¿Citas diarias infinitas?</label>
                            </div>
                        </div>
                        <div class="form-group" id="limite_citas_container">
                            <label for="limite_citas_diarias">Límite de citas diarias *</label>
                            <input type="number" class="form-control" id="limite_citas_diarias" name="limite_citas_diarias" min="0" step="1" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarTipo()">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            cargarTipos();

            // Lógica para el checkbox de citas infinitas
            $('#citas_infinitas').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#limite_citas_container').hide();
                    $('#limite_citas_diarias').prop('required', false);
                } else {
                    $('#limite_citas_container').show();
                    $('#limite_citas_diarias').prop('required', true);
                }
            });
        });

        function cargarTipos() {
            $('#loading').show();
            $('#tipos-table').hide();
            
            fetch('tipos_paciente_json.php')
                .then(response => { if (!response.ok) { throw new Error('Error en la red: ' + response.statusText); } return response.json(); })
                .then(data => {
                    const tbody = $('#tipos-tbody');
                    tbody.empty();
                    $('#loading').hide();
                    $('#tipos-table').show();
                    
                    data.forEach(tipo => {
                        // Asegurarse de que los valores numéricos sean tratados como números
                        tipo.id = parseInt(tipo.id, 10);
                        tipo.limite_citas_diarias = parseInt(tipo.limite_citas_diarias, 10);

                        const limiteTexto = tipo.limite_citas_diarias >= 10000 ? '<span class="badge badge-success">Infinitas</span>' : tipo.limite_citas_diarias;
                        const tr = `
                            <tr>
                                <td>${tipo.id}</td>
                                <td><strong>${tipo.nombre}</strong></td>
                                <td>${limiteTexto}</td>
                                <td>${tipo.descripcion || '-'}</td>
                                <td>
                                    <button class="btn btn-sm btn-success" onclick='abrirModalEditar(${JSON.stringify(tipo)})' title="Editar"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger" onclick="eliminarTipo(${tipo.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        `;
                        tbody.append(tr);
                    });
                })
                .catch(error => {
                    console.error('Error al cargar los tipos de paciente:', error);
                    $('#loading').text('Error al cargar los datos.');
                });
        }

        function abrirModalNuevo() {
            $('#formTipoPaciente')[0].reset();
            $('#tipo_id').val('');
            $('#modalTitle').text('Nuevo Tipo de Paciente');
            $('#citas_infinitas').prop('checked', false).trigger('change');
            $('#modalTipoPaciente').modal('show');
        }

        function abrirModalEditar(tipo) {
            $('#formTipoPaciente')[0].reset();
            $('#modalTitle').text('Editar Tipo de Paciente');
            
            $('#tipo_id').val(tipo.id);
            $('#nombre').val(tipo.nombre);
            $('#descripcion').val(tipo.descripcion);

            if (tipo.limite_citas_diarias >= 10000) {
                $('#citas_infinitas').prop('checked', true);
            } else {
                $('#citas_infinitas').prop('checked', false);
                $('#limite_citas_diarias').val(tipo.limite_citas_diarias);
            }
            $('#citas_infinitas').trigger('change');
            
            $('#modalTipoPaciente').modal('show');
        }

        function guardarTipo() {
            const form = $('#formTipoPaciente')[0];
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const id = $('#tipo_id').val();
            const isEdit = !!id;
            const url = isEdit ? 'actualizar_tipo_paciente.php' : 'guardar_tipo_paciente.php';
            
            const data = {
                id: id,
                nombre: $('#nombre').val(),
                descripcion: $('#descripcion').val(),
                limite_citas_diarias: $('#citas_infinitas').is(':checked') ? 10000 : $('#limite_citas_diarias').val()
            };

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    $('#modalTipoPaciente').modal('hide');
                    cargarTipos();
                    alert('Tipo de paciente guardado correctamente.');
                } else {
                    alert('Error: ' + result.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocurrió un error al guardar.');
            });
        }

        function eliminarTipo(id) {
            if (!confirm('¿Está seguro de que desea eliminar este tipo de paciente? \n\nADVERTENCIA: Los pacientes que pertenezcan a este tipo quedarán sin asignación. Esta acción no se puede deshacer.')) {
                return;
            }

            fetch('eliminar_tipo_paciente.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cargarTipos();
                    alert('Tipo de paciente eliminado.');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocurrió un error al eliminar.');
            });
        }
    </script>
</body>
</html>
