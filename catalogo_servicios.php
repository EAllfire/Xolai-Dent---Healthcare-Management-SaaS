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
    <title>Catálogo de Servicios - Hospital Angeles</title>
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
        <h1 class="page-title">Catálogo de Servicios</h1>
        
        <div class="actions-bar">
            <div>
                <a href="panel_admin.php" class="btn btn-secondary mr-2"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
                <button class="btn btn-primary" onclick="abrirModalNuevoServicio()">
                    <i class="fas fa-plus"></i> Nuevo Servicio
                </button>
                <button class="btn btn-info ml-2" id="bulkEditBtn" onclick="abrirModalBulkEdit()" disabled>
                    <i class="fas fa-edit"></i> Modificar Seleccionados
                </button>
            </div>
            <div class="input-group" style="max-width: 400px;">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                </div>
                <input type="text" id="searchInput" class="form-control" placeholder="Buscar por nombre o descripción...">
            </div>
            <div>
                <span id="total-servicios" class="text-muted">Cargando...</span>
            </div>
        </div>
        
        <div class="table-container">
            <div id="loading" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Cargando servicios...</div>
            <div id="services-table" style="display: none;">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllCheckbox" title="Seleccionar todo"></th>
                            <th>ID</th>
                            <th>Nombre del Servicio</th>
                            <th>Descripción</th>
                            <th>Precio</th>
                            <th>Duración</th>
                            <th>Modalidad</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="services-tbody"></tbody>
                </table>
            </div>
            <div id="empty-state" class="text-center p-5" style="display: none;">
                <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                <h4>No hay servicios registrados</h4>
                <p>Comience agregando su primer servicio al catálogo.</p>
            </div>
        </div>
    </div>

    <!-- Modal Edición Masiva -->
    <div class="modal fade" id="modalBulkEdit" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modificar Descripción de Servicios Seleccionados</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>Se modificarán <strong id="selected-count">0</strong> servicios.</p>
                    <div class="form-group">
                        <label for="bulk_descripcion">Nueva Descripción</label>
                        <textarea class="form-control" id="bulk_descripcion" name="bulk_descripcion" rows="5" placeholder="Escribe la nueva descripción que se aplicará a todos los servicios seleccionados."></textarea>
                    </div>
                    <div class="alert alert-info">
                        <strong>Nota:</strong> Esta acción reemplazará la descripción actual de todos los servicios seleccionados.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarBulkEdit()">
                        <i class="fas fa-save"></i> Aplicar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Nuevo/Editar Servicio -->
    <div class="modal fade" id="modalServicio" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalServicioTitle">Nuevo Servicio</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="formServicio">
                        <input type="hidden" id="servicio_id" name="id">
                        <div class="form-group">
                            <label for="nombre">Nombre del Servicio *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="precio">Precio *</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                                    <input type="number" class="form-control" id="precio" name="precio" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="duracion_minutos">Duración (minutos)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="duracion_minutos" name="duracion_minutos" min="5" max="180" value="30">
                                    <div class="input-group-append"><span class="input-group-text">min</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="descripcion">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Modalidad Asociada</label>
                            <select class="form-control" id="modalidad_id" name="modalidad_id">
                                <option value="">Seleccionar modalidad...</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarServicio()">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let servicios = [];
        let modalidades = [];

        $(document).ready(function() {
            cargarModalidades();
            cargarServicios();

            $('#searchInput').on('keyup', function() {
                const searchTerm = $(this).val().toLowerCase();
                const serviciosFiltrados = servicios.filter(servicio => {
                    return (servicio.nombre && servicio.nombre.toLowerCase().includes(searchTerm)) ||
                           (servicio.descripcion && servicio.descripcion.toLowerCase().includes(searchTerm));
                });
                renderizarServicios(serviciosFiltrados);
                actualizarContador(serviciosFiltrados.length, servicios.length);
            });

            $('#selectAllCheckbox').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.service-checkbox').prop('checked', isChecked).trigger('change');
            });

            // Usamos delegación de eventos para los checkboxes que se crean dinámicamente
            $('#services-tbody').on('change', '.service-checkbox', function() {
                const totalChecked = $('.service-checkbox:checked').length;
                $('#bulkEditBtn').prop('disabled', totalChecked === 0);

                // Sincronizar el checkbox "seleccionar todo"
                const totalCheckboxes = $('.service-checkbox').length;
                if (totalCheckboxes > 0) {
                    $('#selectAllCheckbox').prop('checked', totalChecked === totalCheckboxes);
                }
            });
        });

        function cargarModalidades() {
            fetch('citas/modalidades_json.php')
                .then(response => response.json())
                .then(data => {
                    modalidades = data;
                    const select = document.getElementById('modalidad_id');
                    data.forEach(modalidad => {
                        const option = document.createElement('option');
                        option.value = modalidad.id;
                        option.textContent = modalidad.nombre;
                        select.appendChild(option);
                    });
                })
                .catch(error => console.error('Error al cargar modalidades:', error));
        }

        function cargarServicios() {
            $('#loading').show();
            $('#services-table').hide();
            $('#empty-state').hide();
            
            fetch('citas/servicios_json.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    servicios = data;
                    renderizarServicios();
                    actualizarContador();
                })
                .catch(error => {
                    console.error('Error al cargar servicios:', error);
                    $('#loading').hide();
                    $('#empty-state').html(`<h4>Error al cargar servicios</h4><p>${error.message}</p>`).show();
                });
        }

        function renderizarServicios(listaServicios) {
            const lista = listaServicios || servicios;
            const tbody = document.getElementById('services-tbody');
            tbody.innerHTML = '';
            $('#loading').hide();

            if (!Array.isArray(lista) || lista.length === 0) {
                $('#services-table').hide();
                $('#empty-state').show();
                return;
            }

            $('#empty-state').hide();
            $('#services-table').show();
            
            lista.forEach(servicio => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <input type="checkbox" class="service-checkbox" value="${servicio.id}">
                    </td>
                    <td>${servicio.id}</td>
                    <td><strong>${servicio.nombre}</strong></td>
                    <td>${servicio.descripcion || '-'}</td>
                    <td>$${servicio.precio ? parseFloat(servicio.precio).toLocaleString('es-MX') : '0.00'}</td>
                    <td><span class="badge badge-info">${servicio.duracion_minutos || 30} min</span></td>
                    <td><small class="text-muted">${servicio.modalidad_nombre || '-'}</small></td>
                    <td>
                        <button class="btn btn-sm btn-success" onclick="editarServicio(${servicio.id})" title="Editar"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="eliminarServicio(${servicio.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function actualizarContador(totalFiltrado, totalOriginal) {
            const totalServicios = totalOriginal !== undefined ? totalOriginal : (Array.isArray(servicios) ? servicios.length : 0);
            
            if (totalFiltrado !== undefined && totalFiltrado !== totalServicios) {
                 document.getElementById('total-servicios').textContent = `Mostrando ${totalFiltrado} de ${totalServicios} servicios`;
            } else {
                 document.getElementById('total-servicios').textContent = `${totalServicios} servicios registrados`;
            }
        }

        function abrirModalNuevoServicio() {
            document.getElementById('modalServicioTitle').textContent = 'Nuevo Servicio';
            document.getElementById('formServicio').reset();
            document.getElementById('servicio_id').value = '';
            $('#modalServicio').modal('show');
        }

        function editarServicio(id) {
            const servicio = servicios.find(s => s.id == id);
            if (!servicio) return;
            
            document.getElementById('modalServicioTitle').textContent = 'Editar Servicio';
            document.getElementById('servicio_id').value = servicio.id;
            document.getElementById('nombre').value = servicio.nombre;
            document.getElementById('descripcion').value = servicio.descripcion || '';
            document.getElementById('precio').value = servicio.precio || '';
            document.getElementById('duracion_minutos').value = servicio.duracion_minutos || 30;
            document.getElementById('modalidad_id').value = servicio.modalidad_id || '';
            
            $('#modalServicio').modal('show');
        }

        function guardarServicio() {
            const form = document.getElementById('formServicio');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            const formData = new FormData(form);
            
            const isEdit = document.getElementById('servicio_id').value !== '';
            const url = isEdit ? 'citas/actualizar_servicio.php' : 'citas/crear_servicio.php';
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#modalServicio').modal('hide');
                    cargarServicios();
                    alert(isEdit ? 'Servicio actualizado correctamente' : 'Servicio creado correctamente');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al guardar el servicio.');
            });
        }

        function eliminarServicio(id) {
            const servicio = servicios.find(s => s.id == id);
            if (!servicio) return;
            
            if (confirm(`¿Está seguro que desea eliminar el servicio "${servicio.nombre}"?`)) {
                fetch('citas/eliminar_servicio.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cargarServicios();
                        alert('Servicio eliminado correctamente.');
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar el servicio.');
                });
            }
        }

        function abrirModalBulkEdit() {
            const selectedCount = $('.service-checkbox:checked').length;
            if (selectedCount === 0) {
                alert('Por favor, seleccione al menos un servicio para modificar.');
                return;
            }
            $('#selected-count').text(selectedCount);
            $('#bulk_descripcion').val('');
            $('#modalBulkEdit').modal('show');
        }

        function guardarBulkEdit() {
            const nuevaDescripcion = $('#bulk_descripcion').val();
            const ids = $('.service-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (ids.length === 0) {
                alert('No hay servicios seleccionados.');
                return;
            }

            if (confirm(`¿Está seguro de que desea actualizar la descripción de ${ids.length} servicios?`)) {
                fetch('actualizar_servicios_bulk.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        ids: ids,
                        descripcion: nuevaDescripcion
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        $('#modalBulkEdit').modal('hide');
                        cargarServicios(); // Recargar la lista para ver los cambios
                        alert(`Se actualizaron ${data.affected_rows} servicios correctamente.`);
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ocurrió un error al procesar la solicitud.');
                });
            }
        }
    </script>
</body>
</html>