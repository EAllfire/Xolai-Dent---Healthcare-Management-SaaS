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

// Variables para el header
$user_nombre = $usuario['nombre'] ?? 'Usuario';
$user_tipo = $usuario['tipo'] ?? 'usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Pacientes - Hospital Angeles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
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
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
            font-family: Arial, sans-serif;
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
            display: flex;
            align-items: center;
            flex-direction: column;
            text-align: center;
        }
        .header-logo img {
            max-height: 60px;
        }
        .logo-text {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .btn-header {
            color: white;
            text-decoration: none;
            font-weight: bold;
            padding: 0.5rem 1rem;
            font-size: 13px;
        }
        .container-custom {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        .page-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 2rem;
            text-align: center;
        }
        .actions-bar {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table-container {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
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
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($user_nombre); ?></span>
                <span class="user-type">(<?php echo ucfirst($user_tipo); ?>)</span>
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
        <h1 class="page-title">Catálogo de Pacientes</h1>
        
        <div class="actions-bar">
            <div>
                <a href="panel_admin.php" class="btn btn-secondary mr-2"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
                <button class="btn btn-primary" onclick="abrirModalNuevoPaciente()">
                    <i class="fas fa-plus"></i> Nuevo Paciente
                </button>
            </div>
            <div class="input-group" style="max-width: 400px;">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                </div>
                <input type="text" id="searchInput" class="form-control" placeholder="Buscar por nombre, apellido, teléfono o correo...">
            </div>
            <div>
                <span id="total-pacientes" class="text-muted">Cargando...</span>
            </div>
        </div>
        
        <div class="table-container">
            <div id="loading" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Cargando pacientes...</div>
            <div id="pacientes-table" style="display: none;">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Apellidos</th>
                            <th>Teléfono</th>
                            <th>Correo</th>
                            <th>Tipo</th>
                            <th>Origen</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="pacientes-tbody"></tbody>
                </table>
            </div>
            <div id="empty-state" class="text-center p-5" style="display: none;">
                <i class="fas fa-users fa-3x mb-3"></i>
                <h4>No hay pacientes registrados</h4>
                <p>Comience agregando su primer paciente al catálogo.</p>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo/Editar Paciente -->
    <div class="modal fade" id="modalPaciente" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPacienteTitle">Nuevo Paciente</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="formPaciente">
                        <input type="hidden" id="paciente_id" name="id">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="nombre">Nombre(s) *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="apellido">Apellido(s) *</label>
                                <input type="text" class="form-control" id="apellido" name="apellido" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="telefono">Teléfono</label>
                                <input type="tel" class="form-control" id="telefono" name="telefono">
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="correo">Correo electrónico</label>
                                <input type="email" class="form-control" id="correo" name="correo">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="tipo">Tipo</label>
                                <select class="form-control" id="tipo" name="tipo">
                                    <option value="adulto" selected>Adulto</option>
                                    <option value="niño">Niño</option>
                                    <option value="IMSS">IMSS</option>
                                    <option value="urgencias">Urgencias</option>
                                    <option value="externo">Externo</option>
                                    <option value="interno">Interno</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="origen">Origen</label>
                                <select class="form-control" id="origen" name="origen">
                                    <option value="externo" selected>Externo</option>
                                    <option value="urgencias">Urgencias</option>
                                    <option value="interno">Interno</option>
                                    <option value="">Otro</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="comentarios">Comentarios adicionales</label>
                            <textarea class="form-control" id="comentarios" name="comentarios" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarPaciente()">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let pacientes = [];

        $(document).ready(function() {
            cargarPacientes();

            $('#searchInput').on('keyup', function() {
                const searchTerm = $(this).val().toLowerCase();
                const pacientesFiltrados = pacientes.filter(paciente => {
                    return (paciente.nombre_solo && paciente.nombre_solo.toLowerCase().includes(searchTerm)) ||
                           (paciente.apellido && paciente.apellido.toLowerCase().includes(searchTerm)) ||
                           (paciente.telefono && paciente.telefono.toLowerCase().includes(searchTerm)) ||
                           (paciente.correo && paciente.correo.toLowerCase().includes(searchTerm));
                });
                renderizarPacientes(pacientesFiltrados);
                actualizarContador(pacientesFiltrados.length, pacientes.length);
            });
        });

        function cargarPacientes() {
            $('#loading').show();
            $('#pacientes-table').hide();
            $('#empty-state').hide();
            
            fetch('citas/pacientes_json.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    pacientes = data;
                    renderizarPacientes();
                    actualizarContador();
                })
                .catch(error => {
                    console.error('Error al cargar pacientes:', error);
                    $('#loading').hide();
                    $('#empty-state').html(`<h4>Error al cargar pacientes</h4><p>${error.message}</p>`).show();
                });
        }

        function renderizarPacientes(listaPacientes) {
            const lista = listaPacientes || pacientes;
            const tbody = document.getElementById('pacientes-tbody');
            tbody.innerHTML = '';
            $('#loading').hide();

            if (!Array.isArray(lista) || lista.length === 0) {
                $('#pacientes-table').hide();
                $('#empty-state').show();
                return;
            }

            $('#empty-state').hide();
            $('#pacientes-table').show();
            
            lista.forEach(paciente => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${paciente.id}</td>
                    <td><strong>${paciente.nombre_solo || ''}</strong></td>
                    <td><strong>${paciente.apellido || ''}</strong></td>
                    <td>${paciente.telefono || '-'}</td>
                    <td>${paciente.correo || '-'}</td>
                    <td><span class="badge badge-secondary">${paciente.tipo || '-'}</span></td>
                    <td><span class="badge badge-info">${paciente.origen || '-'}</span></td>
                    <td>
                        <button class="btn btn-sm btn-success" onclick="editarPaciente(${paciente.id})" title="Editar"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="eliminarPaciente(${paciente.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function actualizarContador(totalFiltrado, totalOriginal) {
            const totalPacientes = totalOriginal !== undefined ? totalOriginal : (Array.isArray(pacientes) ? pacientes.length : 0);
            
            if (totalFiltrado !== undefined && totalFiltrado !== totalPacientes) {
                 document.getElementById('total-pacientes').textContent = `Mostrando ${totalFiltrado} de ${totalPacientes} pacientes`;
            } else {
                 document.getElementById('total-pacientes').textContent = `${totalPacientes} pacientes registrados`;
            }
        }

        function abrirModalNuevoPaciente() {
            document.getElementById('modalPacienteTitle').textContent = 'Nuevo Paciente';
            document.getElementById('formPaciente').reset();
            document.getElementById('paciente_id').value = '';
            $('#modalPaciente').modal('show');
        }

        function editarPaciente(id) {
            const paciente = pacientes.find(p => p.id == id);
            if (!paciente) return;
            
            document.getElementById('modalPacienteTitle').textContent = 'Editar Paciente';
            document.getElementById('paciente_id').value = paciente.id;
            document.getElementById('nombre').value = paciente.nombre_solo || '';
            document.getElementById('apellido').value = paciente.apellido || '';
            document.getElementById('telefono').value = paciente.telefono || '';
            document.getElementById('correo').value = paciente.correo || '';
            document.getElementById('tipo').value = paciente.tipo || 'adulto';
            document.getElementById('origen').value = paciente.origen || 'externo';
            document.getElementById('comentarios').value = paciente.comentarios || '';
            
            $('#modalPaciente').modal('show');
        }

        function guardarPaciente() {
            const form = document.getElementById('formPaciente');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const isEdit = document.getElementById('paciente_id').value !== '';
            const url = isEdit ? 'actualizar_paciente.php' : 'citas/guardar_paciente.php'; // La URL para actualizar es correcta

            let fetchOptions = {
                method: 'POST'
            };

            if (isEdit) {
                // Para editar, enviamos JSON como lo espera actualizar_paciente.php
                const data = Object.fromEntries(new FormData(form));
                fetchOptions.headers = { 'Content-Type': 'application/json' };
                fetchOptions.body = JSON.stringify(data);
            } else {
                // Para crear, enviamos FormData como lo espera guardar_paciente.php
                fetchOptions.body = new FormData(form);
            }
            
            fetch(url, fetchOptions)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        $('#modalPaciente').modal('hide');
                        cargarPacientes();
                        alert(isEdit ? 'Paciente actualizado correctamente' : 'Paciente creado correctamente');
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al guardar el paciente.');
                });
        }

        function eliminarPaciente(id) {
            const paciente = pacientes.find(p => p.id == id);
            if (!paciente) return;
            
            if (confirm(`¿Está seguro que desea eliminar al paciente "${paciente.nombre}"?`)) {
                fetch('eliminar_paciente.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cargarPacientes();
                        alert('Paciente eliminado correctamente.');
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar el paciente.');
                });
            }
        }
    </script>
</body>
</html>
