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
        
        /* Header Styles - Same as index.php */
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
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-right {
            display: flex;
            align-items: center;
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
            font-size: 14px;
            background: rgba(255,255,255,0.1);
            padding: 8px 12px;
            border-radius: 6px;
        }
        
        .user-type {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .btn-header {
            color: white;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
            background: none;
            border: none;
            padding: 0.5rem 1rem;
            font-size: 13px;
            cursor: pointer;
        }
        
        .btn-header:hover {
            text-decoration: underline;
            color: #cce7ff;
        }
        
        .container-custom {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .page-title {
            color: #1f2937;
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
        
        .btn-primary-custom {
            background-color: #007bff;
            border-color: #007bff;
            color: #ffffff;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        
        .services-grid {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        
        .table-custom {
            margin-bottom: 0;
        }
        
        .table-custom th {
            background-color: #f8f9fa;
            color: #1f2937;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table-custom td {
            vertical-align: middle;
            color: #333;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            margin: 0 0.125rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-edit {
            background-color: #28a745;
            color: #ffffff;
        }
        
        .btn-edit:hover {
            background-color: #1e7e34;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: #ffffff;
        }
        
        .btn-delete:hover {
            background-color: #bd2130;
        }
        
        .modal-content {
            border-radius: 8px;
            border: none;
        }
        
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-radius: 8px 8px 0 0;
        }
        
        .form-control {
            border: 1px solid #ced4da;
            border-radius: 4px;
            color: #333;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6c757d;
            cursor: pointer;
        }
        
        .close-btn:hover {
            color: #000000;
        }
        
        .user-info {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        /* Modern Select Styles */
        select, .form-control select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background: white;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 40px 10px 12px;
            font-size: 14px;
            color: #374151;
            transition: all 0.2s ease;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        select:hover {
            border-color: #1275a0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        select:focus {
            outline: none;
            border-color: #1275a0;
            box-shadow: 0 0 0 3px rgba(18, 117, 160, 0.1);
        }
        
        select:disabled {
            background-color: #f9fafb;
            color: #9ca3af;
            cursor: not-allowed;
        }
        
        /* Form Control Override */
        .form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background: white;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 40px 10px 12px;
            font-size: 14px;
            color: #374151;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        .form-control:hover {
            border-color: #1275a0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .form-control:focus {
            outline: none;
            border-color: #1275a0;
            box-shadow: 0 0 0 3px rgba(18, 117, 160, 0.1);
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
            <div class="header-buttons">
                <a href="index.php" class="btn-header">
                    <i class="fas fa-calendar"></i> Calendario
                </a>
                <a href="admin_usuarios.php" class="btn-header">
                    <i class="fas fa-users-cog"></i> Admin
                </a>
                <a href="cliente.php" class="btn-header">
                    <i class="fas fa-user-friends"></i> Vista Cliente
                </a>
                <a href="logout.php" class="btn-header">
                    <i class="fas fa-sign-out-alt"></i> Salir
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container-custom">
        <h1 class="page-title">Catálogo de Servicios</h1>
        
        <!-- Actions Bar -->
        <div class="actions-bar">
            <div>
                <button class="btn-primary-custom" onclick="abrirModalNuevoServicio()">
                    <i class="fas fa-plus"></i> Nuevo Servicio
                </button>
            </div>
            <div>
                <span id="total-servicios" class="text-muted">Cargando...</span>
            </div>
        </div>
        
        <!-- Services Grid -->
        <div class="services-grid">
            <div id="loading" class="loading">
                <i class="fas fa-spinner fa-spin"></i> Cargando servicios...
            </div>
            <div id="services-table" style="display: none;">
                <table class="table table-custom table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre del Servicio</th>
                            <th>Descripción</th>
                            <th>Precio</th>
                            <th>Duración</th>
                            <th>Modalidad</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="services-tbody">
                        <!-- Los servicios se cargarán aquí -->
                    </tbody>
                </table>
            </div>
            <div id="empty-state" class="empty-state" style="display: none;">
                <i class="fas fa-clipboard-list"></i>
                <h4>No hay servicios registrados</h4>
                <p>Comience agregando su primer servicio al catálogo</p>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo/Editar Servicio -->
    <div class="modal fade" id="modalServicio" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalServicioTitle">Nuevo Servicio</h5>
                    <button type="button" class="close-btn" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formServicio">
                        <input type="hidden" id="servicio_id" name="id">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="nombre">Nombre del Servicio *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="precio">Precio *</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">$</span>
                                        </div>
                                        <input type="number" class="form-control" id="precio" name="precio" step="0.01" min="0" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="duracion_minutos">Duración (minutos)</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="duracion_minutos" name="duracion_minutos" min="5" max="180" value="30">
                                        <div class="input-group-append">
                                            <span class="input-group-text">min</span>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">Tiempo estimado del procedimiento (5-180 minutos)</small>
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
                                <!-- Las modalidades se cargarán aquí -->
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let servicios = [];
        let modalidades = [];
        
        // Inicializar cuando el documento esté listo
        $(document).ready(function() {
            cargarModalidades();
            cargarServicios();
        });
        
        // Cargar modalidades disponibles
        function cargarModalidades() {
            fetch('citas/modalidades_json.php')
                .then(response => response.json())
                .then(data => {
                    modalidades = data;
                    renderizarModalidades();
                })
                .catch(error => {
                    console.error('Error al cargar modalidades:', error);
                });
        }
        
        // Renderizar dropdown de modalidades
        function renderizarModalidades() {
            const select = document.getElementById('modalidad_id');
            
            modalidades.forEach(modalidad => {
                const option = document.createElement('option');
                option.value = modalidad.id;
                option.textContent = modalidad.nombre;
                select.appendChild(option);
            });
        }
        
        // Cargar servicios
        function cargarServicios() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('services-table').style.display = 'none';
            document.getElementById('empty-state').style.display = 'none';
            
            fetch('citas/servicios_json.php')
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Datos recibidos:', data);
                    
                    // Verificar si la respuesta es un error
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    // Verificar si es un array
                    if (!Array.isArray(data)) {
                        throw new Error('Los datos recibidos no son un array válido');
                    }
                    
                    servicios = data;
                    renderizarServicios();
                    actualizarContador();
                })
                .catch(error => {
                    console.error('Error al cargar servicios:', error);
                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('empty-state').style.display = 'block';
                    
                    // Mostrar error más específico
                    const emptyState = document.getElementById('empty-state');
                    emptyState.innerHTML = `
                        <i class="fas fa-exclamation-triangle"></i>
                        <h4>Error al cargar servicios</h4>
                        <p>Error: ${error.message}</p>
                        <button class="btn btn-primary" onclick="cargarServicios()">
                            <i class="fas fa-refresh"></i> Intentar de nuevo
                        </button>
                    `;
                });
        }
        
        // Renderizar tabla de servicios
        function renderizarServicios() {
            const tbody = document.getElementById('services-tbody');
            tbody.innerHTML = '';
            
            document.getElementById('loading').style.display = 'none';
            
            // Verificar que servicios sea un array válido
            if (!Array.isArray(servicios) || servicios.length === 0) {
                document.getElementById('empty-state').style.display = 'block';
                return;
            }
            
            document.getElementById('services-table').style.display = 'block';
            
            servicios.forEach(servicio => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${servicio.id}</td>
                    <td><strong>${servicio.nombre}</strong></td>
                    <td>${servicio.descripcion || 'Sin descripción'}</td>
                    <td>
                        <strong class="text-success">$${servicio.precio ? parseFloat(servicio.precio).toLocaleString('es-MX', {minimumFractionDigits: 2}) : '0.00'}</strong>
                    </td>
                    <td>
                        <span class="badge badge-info">
                            ${servicio.duracion_minutos || 30} min
                        </span>
                    </td>
                    <td>
                        <small class="text-muted">
                            ${servicio.modalidad_nombre || 'Sin modalidad'}
                        </small>
                    </td>
                    <td>
                        <button class="btn-action btn-edit" onclick="editarServicio(${servicio.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action btn-delete" onclick="eliminarServicio(${servicio.id})" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
        
        // Actualizar contador
        function actualizarContador() {
            const total = Array.isArray(servicios) ? servicios.length : 0;
            document.getElementById('total-servicios').textContent = `${total} servicios registrados`;
        }
        
        // Abrir modal para nuevo servicio
        function abrirModalNuevoServicio() {
            document.getElementById('modalServicioTitle').textContent = 'Nuevo Servicio';
            document.getElementById('formServicio').reset();
            document.getElementById('servicio_id').value = '';
            document.getElementById('modalidad_id').value = '';
            
            $('#modalServicio').modal('show');
        }
        
        // Editar servicio
        function editarServicio(id) {
            const servicio = servicios.find(s => s.id === id);
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
        
        // Guardar servicio
        function guardarServicio() {
            const formData = new FormData(document.getElementById('formServicio'));
            
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
                alert('Error al guardar el servicio');
            });
        }
        
        // Eliminar servicio
        function eliminarServicio(id) {
            const servicio = servicios.find(s => s.id === id);
            if (!servicio) return;
            
            if (confirm(`¿Está seguro que desea eliminar el servicio "${servicio.nombre}"?`)) {
                fetch('citas/eliminar_servicio.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cargarServicios();
                        alert('Servicio eliminado correctamente');
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar el servicio');
                });
            }
        }
    </script>
</body>
</html>
