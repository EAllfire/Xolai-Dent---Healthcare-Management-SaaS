<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (!puedeRealizar('gestionar_servicios')) {
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
    <title>Gestión de Paquetes - Hospital Angeles</title>
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

        .container-custom {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.5);
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            overflow-x: auto;
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

        .input-group-text {
            background: #111;
            border-color: #333;
            color: #9ca3af;
        }

        /* Botones */
        .btn-primary {
            background: #2979ff;
            border-color: #2979ff;
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.3);
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

        .service-list {
            list-style-type: none;
            padding-left: 0;
            margin-bottom: 0;
        }
        
        .service-list li {
            font-size: 0.85em;
            padding: 2px 5px;
            margin-bottom: 3px;
            border-radius: 4px;
            background-color: #333;
            color: #e5e7eb;
            border: 1px solid #444;
            display: inline-block;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container-custom">
        <h1 class="page-title">Catálogo de Paquetes</h1>
        
        <div class="actions-bar">
            <div>
                <a href="panel_admin.php" class="btn btn-secondary mr-2"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
                <button class="btn btn-primary" onclick="abrirModalNuevoPaquete()">
                    <i class="fas fa-plus"></i> Nuevo Paquete
                </button>
            </div>
            <div class="input-group" style="max-width: 400px;">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                </div>
                <input type="text" id="searchInput" class="form-control" placeholder="Buscar por nombre o descripción...">
            </div>
            <div>
                <span id="total-paquetes" class="text-muted">Cargando...</span>
            </div>
        </div>
        
        <div class="table-container">
            <div id="loading" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Cargando paquetes...</div>
            <div id="paquetes-table" style="display: none;">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Precio</th>
                            <th>Servicios Incluidos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="paquetes-tbody"></tbody>
                </table>
            </div>
            <div id="empty-state" class="text-center p-5" style="display: none;">
                <i class="fas fa-box-open fa-3x mb-3"></i>
                <h4>No hay paquetes registrados</h4>
                <p>Comience agregando su primer paquete al catálogo.</p>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo/Editar Paquete -->
    <div class="modal fade" id="modalPaquete" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPaqueteTitle">Nuevo Paquete</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="formPaquete">
                        <input type="hidden" id="paquete_id" name="id">
                        <div class="form-group">
                            <label for="nombre">Nombre del Paquete *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="descripcion">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="precio">Precio *</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                                <input type="number" step="0.01" class="form-control" id="precio" name="precio" required>
                            </div>
                        </div>
                         <div class="form-group">
                            <label for="servicios">Servicios Incluidos</label>
                            <select multiple class="form-control" id="servicios" name="servicios[]" size="8">
                                <!-- Opciones de servicios se cargarán dinámicamente -->
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarPaquete()">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let paquetes = [];
        let servicios = [];

        $(document).ready(function() {
            cargarServicios();
            cargarPaquetes();

            $('#searchInput').on('keyup', function() {
                const searchTerm = $(this).val().toLowerCase();
                const paquetesFiltrados = paquetes.filter(paquete => 
                    (paquete.nombre && paquete.nombre.toLowerCase().includes(searchTerm)) ||
                    (paquete.descripcion && paquete.descripcion.toLowerCase().includes(searchTerm))
                );
                renderizarPaquetes(paquetesFiltrados);
                actualizarContador(paquetesFiltrados.length, paquetes.length);
            });
        });

        function cargarServicios() {
             fetch('citas/servicios_json.php')
                .then(response => response.json())
                .then(data => {
                    servicios = data;
                    const select = $('#servicios');
                    select.empty();
                    servicios.forEach(servicio => {
                        select.append(`<option value="${servicio.id}">${servicio.nombre}</option>`);
                    });
                })
                .catch(error => console.error('Error al cargar servicios:', error));
        }

        function cargarPaquetes() {
            $('#loading').show();
            $('#paquetes-table').hide();
            $('#empty-state').hide();
            
            fetch('citas/paquetes_json.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) throw new Error(data.error);
                    paquetes = data;
                    renderizarPaquetes();
                    actualizarContador();
                })
                .catch(error => {
                    console.error('Error al cargar paquetes:', error);
                    $('#loading').hide();
                    $('#empty-state').html(`<h4>Error al cargar paquetes</h4><p>${error.message}</p>`).show();
                });
        }
        
        function renderizarServiciosDelPaquete(serviciosPaquete) {
            if (!serviciosPaquete || serviciosPaquete.length === 0) return '<small class="text-muted">Sin servicios</small>';
            
            return '<ul class="service-list">' + serviciosPaquete.map(servicio => `<li>${servicio.nombre}</li>`).join('') + '</ul>';
        }

        function renderizarPaquetes(listaPaquetes) {
            const lista = listaPaquetes || paquetes;
            const tbody = document.getElementById('paquetes-tbody');
            tbody.innerHTML = '';
            $('#loading').hide();

            if (!Array.isArray(lista) || lista.length === 0) {
                $('#paquetes-table').hide();
                $('#empty-state').show();
                return;
            }

            $('#empty-state').hide();
            $('#paquetes-table').show();
            
            lista.forEach(paquete => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${paquete.id}</td>
                    <td><strong>${paquete.nombre}</strong></td>
                    <td>${paquete.descripcion || '-'}</td>
                    <td>$${parseFloat(paquete.precio).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    <td>${renderizarServiciosDelPaquete(paquete.servicios)}</td>
                    <td>
                        <button class="btn btn-sm btn-success" onclick="editarPaquete(${paquete.id})" title="Editar"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="eliminarPaquete(${paquete.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function actualizarContador(totalFiltrado, totalOriginal) {
            const totalPaquetes = totalOriginal !== undefined ? totalOriginal : (Array.isArray(paquetes) ? paquetes.length : 0);
            
            if (totalFiltrado !== undefined && totalFiltrado !== totalPaquetes) {
                 $('#total-paquetes').text(`Mostrando ${totalFiltrado} de ${totalPaquetes} paquetes`);
            } else {
                 $('#total-paquetes').text(`${totalPaquetes} paquetes registrados`);
            }
        }

        function abrirModalNuevoPaquete() {
            $('#modalPaqueteTitle').text('Nuevo Paquete');
            $('#formPaquete')[0].reset();
            $('#paquete_id').val('');
            $('#servicios').val([]).trigger('change');
            $('#modalPaquete').modal('show');
        }

        function editarPaquete(id) {
            const paquete = paquetes.find(p => p.id == id);
            if (!paquete) return;
            
            $('#modalPaqueteTitle').text('Editar Paquete');
            $('#paquete_id').val(paquete.id);
            $('#nombre').val(paquete.nombre);
            $('#descripcion').val(paquete.descripcion);
            $('#precio').val(parseFloat(paquete.precio).toFixed(2));

            const serviciosIds = paquete.servicios ? paquete.servicios.map(s => s.id) : [];
            $('#servicios').val(serviciosIds);
            
            $('#modalPaquete').modal('show');
        }

        function guardarPaquete() {
            const form = document.getElementById('formPaquete');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const isEdit = $('#paquete_id').val() !== '';
            const url = isEdit ? 'citas/actualizar_paquete.php' : 'citas/guardar_paquete.php';
            const formData = new FormData(form);
            
            const serviciosSeleccionados = $('#servicios').val();
            formData.delete('servicios[]');
            if (serviciosSeleccionados) {
                serviciosSeleccionados.forEach(servicioId => {
                    formData.append('servicios[]', servicioId);
                });
            }

            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#modalPaquete').modal('hide');
                    cargarPaquetes();
                    alert(isEdit ? 'Paquete actualizado correctamente.' : 'Paquete guardado correctamente.');
                } else {
                    alert('Error: ' + (data.error || 'Ocurrió un error desconocido.'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al guardar el paquete.');
            });
        }

        function eliminarPaquete(id) {
            const paquete = paquetes.find(p => p.id == id);
            if (!paquete) return;
            
            if (confirm(`¿Está seguro que desea eliminar el paquete "${paquete.nombre}"?`)) {
                fetch('citas/eliminar_paquete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cargarPaquetes();
                        alert('Paquete eliminado correctamente.');
                    } else {
                        alert('Error: ' + (data.error || 'No se pudo eliminar el paquete.'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar el paquete.');
                });
            }
        }
    </script>
</body>
</html>