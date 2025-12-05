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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Tipos de Paciente - Hospital Angeles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            padding-top: 100px;
        }
        .main-header {
            background: #1275a0;
            color: white;
            height: 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
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
        }
        .header-logo img { max-height: 60px; }
        .logo-text { margin: 0; font-size: 24px; font-weight: bold; }
        .btn-header { color: white; text-decoration: none; font-weight: bold; padding: 0.5rem 1rem; font-size: 13px; }
        .container-custom { max-width: 1200px; margin: 2rem auto; }
        .page-title { font-size: 2rem; font-weight: 600; margin-bottom: 2rem; text-align: center; }
        .table-container {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        .actions-bar {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="header-left">
            <div class="header-logo">
                <img src="https://angelescuauhtemoc.com/wp-content/uploads/2020/09/logo-50-300x187.png" alt="Hospital Angeles">
            </div>
        </div>
        <div class="logo-section">
            <div class="logo-text">IMAGENOLOGÍA</div>
        </div>
        <div class="header-right">
            <a href="index.php" class="btn-header"><i class="fas fa-calendar"></i> Calendario</a>
            <a href="panel_admin.php" class="btn-header"><i class="fas fa-cog"></i> Panel de Administración</a>
            <a href="logout.php" class="btn-header"><i class="fas fa-sign-out-alt"></i> Salir</a>
        </div>
    </header>

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
