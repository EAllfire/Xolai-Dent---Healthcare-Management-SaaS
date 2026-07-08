<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Verificar que solo los admins puedan acceder
if (!puedeRealizar('gestionar_servicios') && $_SESSION['usuario_tipo'] !== 'dentista') {
    header('Location: index.php');
    exit;
}

$usuario = obtenerUsuarioActual();

// Variables para el header
$user_nombre = $usuario['nombre'] ?? 'Usuario';
$user_tipo = $usuario['tipo'] ?? 'usuario';
$es_admin = ($user_tipo === 'admin');
$puede_ver_admin = in_array($user_tipo, ['admin', 'medico', 'dentista']);

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
    <title>Catálogo de Trataminetos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #000000; /* Fondo negro */
            color: #e5e7eb; /* Texto claro */
            padding-top: 0;
            margin: 0;
        }
        
        /* Header Styles (Igual que Home) */
        .main-header {
            background: rgba(10, 10, 10, 0.5);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            color: white;
            height: 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 40px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
        }
        
        .header-left, .header-center, .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .header-left { flex: 1; justify-content: flex-start; }
        .header-center { flex: 2; justify-content: center; }
        .header-right { flex: 1; justify-content: flex-end; }

        .header-logo-img {
            height: 45px;
            width: auto;
        }
        
        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: white;
            letter-spacing: 1px;
        }
        
        .nav-link {
            color: #a0a0a0;
            font-weight: 500;
            font-size: 15px;
            padding: 8px 16px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.12);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .btn-header {
            color: #e5e7eb;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-header:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.25);
        }

        /* Settings Dropdown Styles */
        .settings-container {
            position: relative;
            display: inline-block;
            margin-right: 10px;
        }
        .settings-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            font-size: 1.2rem;
            color: #e5e7eb;
            padding: 6px 10px;
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .settings-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            transform: rotate(90deg);
        }
        .custom-dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: #0a0a0a;
            min-width: 200px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            border-radius: 12px;
            z-index: 1100;
            overflow: hidden;
            margin-top: 10px;
            border: 1px solid #333;
            text-align: left;
        }
        .custom-dropdown-menu.show {
            display: block;
        }
        .custom-dropdown-menu a {
            color: #e5e7eb;
            padding: 12px 20px;
            text-decoration: none;
            display: block;
            font-size: 14px;
            transition: all 0.2s;
            border-bottom: 1px solid #1a1a1a;
        }
        .custom-dropdown-menu a:hover {
            background-color: rgba(41, 121, 255, 0.1);
            color: #2979ff;
        }

        /* Page Content Styles */
        .container-custom {
            max-width: 1400px;
            margin: 0 auto;
            padding: 120px 40px 40px 40px;
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
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-container {
            background: #0a0a0a;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            overflow-x: auto;
        }

        .table {
            color: #e5e7eb;
            margin-bottom: 0;
        }
        .table thead th {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #9ca3af;
            border-top: none;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        .table td {
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(41, 121, 255, 0.05);
            color: #e5e7eb;
        }

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

        .btn-primary {
            background: #2979ff;
            border-color: #2979ff;
        }
        .btn-primary:hover {
            background: #2962ff;
            border-color: #2962ff;
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
        .btn-info {
            background: #0ea5e9;
            border-color: #0ea5e9;
            color: white;
        }
        
        /* Modal Styles */
        .modal-content {
            background: #0a0a0a;
            border: 1px solid #333;
            color: #e5e7eb;
            box-shadow: 0 10px 40px rgba(0,0,0,0.7);
        }
        .modal-header { border-bottom: 1px solid #333; background: #111; }
        .modal-footer { border-top: 1px solid #333; background: #111; }
        .close { color: #e5e7eb; opacity: 0.7; }
        .close:hover { color: #fff; opacity: 1; }
        .badge-info { background: rgba(41, 121, 255, 0.15); color: #60a5fa; border: 1px solid rgba(41, 121, 255, 0.3); }
    </style>

    <header class="main-header">
        <div class="header-left">
            <img src="images/Xolai.png" alt="Xolai Logo" class="header-logo-img">
            <span class="header-title">Xolai</span>
        </div>
        <nav class="header-center">
            <a href="home.php" class="nav-link">Inicio</a>
            <a href="index.php" class="nav-link">Agenda</a>
            <a href="catalogo_pacientes.php" class="nav-link">Pacientes</a>
            <a href="pagos.php" class="nav-link">Pagos</a>
            <?php if ($puede_ver_admin): ?>
                <a href="panel_admin.php" class="nav-link">Administración</a>
            <?php endif; ?>
        </nav>
        <div class="header-right">
            <div class="user-info">
                <span><?php echo htmlspecialchars($user_nombre); ?></span>
                <i class="fas fa-user-circle"></i>
            </div>
      
            <?php if ($puede_ver_admin): ?>
            <div class="settings-container">
                <button onclick="toggleSettingsDropdown()" class="settings-btn" title="Configuración">
                    <i class="fas fa-cog"></i>
                </button>
                <div id="ajustesDropdown" class="custom-dropdown-menu">
                    <a href="catalogo_servicios.php"><i class="fas fa-stethoscope"></i> Tratamientos</a>
                    <a href="admin_modalidades.php"><i class="fas fa-layer-group"></i> Consultorios</a>
                    <?php if (in_array($user_tipo, ['admin', 'dentista'])): ?>
                        <a href="admin_usuarios.php"><i class="fas fa-users"></i> Gestionar Equipo</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="header-buttons">
                <a href="logout.php" class="btn-header">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <div class="container-custom">
        <div class="actions-bar">
            <div>
                <a href="panel_admin.php" class="btn btn-secondary mr-2"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
                <button class="btn btn-primary" onclick="abrirModalNuevoServicio()">
                    <i class="fas fa-plus"></i> Nuevo Tratamiento
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
            <div id="loading" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Cargando trataminetos...</div>
            <div id="services-table" style="display: none;">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllCheckbox" title="Seleccionar todo"></th>
                            <th>ID</th>
                            <th>Nombre del Tratamineto</th>
                            <th>Descripción</th>
                            <th>Precio</th>
                            <th>Duración</th>
                            <th>Especialidad</th>
                            <th>Dóctor</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="services-tbody"></tbody>
                </table>
            </div>
            <div id="empty-state" class="text-center p-5" style="display: none;">
                <i class="fas fa-list fa-3x mb-3 text-muted"></i>
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
                    <h5 class="modal-title">Modificar Descripción de Tratamientos Seleccionados</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>Se modificarán <strong id="selected-count">0</strong> tratamientos.</p>
                    <div class="form-group">
                        <label for="bulk_descripcion">Nueva Descripción</label>
                        <textarea class="form-control" id="bulk_descripcion" name="bulk_descripcion" rows="5" placeholder="Escribe la nueva descripción que se aplicará a todos los servicios seleccionados."></textarea>
                    </div>
                    <div class="alert alert-info">
                        <strong>Nota:</strong> Esta acción reemplazará la descripción actual de todos los tratamientos seleccionados.
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
                    <h5 class="modal-title" id="modalServicioTitle">Nuevo Tratamiento</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="formServicio">
                        <div class="form-group">
                            <input type="hidden" id="servicio_id" name="id">
                            <label for="nombre">Nombre del Tratamiento *</label>
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
                            <label for="especialidad_id">Especialidad Asociada</label>
                            <select class="form-control" id="especialidad_id" name="especialidad_id">
                                <option value="">General (Sin especialidad)</option>
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
        let especialidades = [];
        let servicios = [];
        
        $(document).ready(function() {
            cargarEspecialidades();
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

        function cargarEspecialidades() {
            fetch('citas/especialidades_json.php')
                .then(response => response.json())
                .then(data => {
                    if (!Array.isArray(data)) return;
                    especialidades = data;
                    const select = document.getElementById('especialidad_id');
                    select.innerHTML = '<option value="">General (Sin especialidad)</option>';
                    data.forEach(especialidad => {
                        const option = document.createElement('option');
                        option.value = especialidad.id;
                        option.textContent = especialidad.nombre;
                        select.appendChild(option);
                    });
                })
                .catch(error => console.error('Error al cargar especialidades:', error));
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
                    <td><small class="text-muted">${servicio.especialidad_nombre || 'General'}</small></td>
                    <td><small class="text-muted">${servicio.medico_nombre || ''}</small></td>
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
                 document.getElementById('total-servicios').textContent = `${totalServicios} tratamientos registrados`;
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
            document.getElementById('especialidad_id').value = servicio.especialidad_id || '';
            
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

        function toggleSettingsDropdown() {
            document.getElementById("ajustesDropdown").classList.toggle("show");
        }

        // Cerrar el dropdown si se hace click fuera
        window.onclick = function(event) {
            if (!event.target.matches('.settings-btn') && !event.target.closest('.settings-btn')) {
                var dropdowns = document.getElementsByClassName("custom-dropdown-menu");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
    </script>
</body>
</html>