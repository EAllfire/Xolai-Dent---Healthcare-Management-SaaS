<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Verificar que solo los admins puedan acceder
$usuario = obtenerUsuarioActual();
$user_nombre = $usuario['nombre'] ?? 'Usuario';
$user_tipo = $usuario['tipo'] ?? 'usuario';
$es_dentista_principal = ($user_tipo === 'dentista' && empty($_SESSION['id_padre']));

if (!puedeRealizar('gestionar_especialidades') && !$es_dentista_principal) { 
    header('Location: index.php');
    exit;
}
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
    <title>Administrar Especialidades</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #000000;
            color: #e5e7eb;
            padding-top: 0;
            margin: 0;
        }
        
        /* Header Styles - Xolai Style */
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
            mask-image: linear-gradient(to bottom, black 70%, transparent 100%);
            -webkit-mask-image: linear-gradient(to bottom, black 70%, transparent 100%);
        }
        
        .header-left, .header-center, .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .header-left { flex: 1; justify-content: flex-start; }
        .header-center { flex: 2; justify-content: center; }
        .header-right { flex: 1; justify-content: flex-end; }

        .header-logo-img { height: 45px; width: auto; }
        .header-title { font-size: 24px; font-weight: 700; color: white; letter-spacing: 1px; }
        
        .nav-link {
            color: #a0a0a0;
            font-weight: 500;
            font-size: 15px;
            padding: 8px 16px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        .nav-link:hover { color: white; background-color: rgba(255, 255, 255, 0.12); }
        
        .user-info { display: flex; align-items: center; gap: 10px; font-weight: 500; }

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
        .btn-header:hover { background: rgba(255, 255, 255, 0.15); color: #ffffff; }

        .container-custom { max-width: 1200px; margin: 0 auto; padding: 120px 15px 0 15px; }
        
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
    <header class="main-header">
        <div class="header-left">
            <img src="images/Xolai.png" alt="Xolai Logo" class="header-logo-img">
            <span class="header-title">Xolai</span>
        </div>
        <div class="header-right">
            <div class="user-info">
                <span><?php echo htmlspecialchars($user_nombre); ?></span>
                <i class="fas fa-user-circle"></i>
            </div>
            <a href="logout.php" class="btn-header"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container-custom">
        <h1 class="page-title">Administrar Especialidades</h1>
        
        <div class="actions-bar">
            <a href="panel_admin.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
            <button class="btn btn-primary" onclick="abrirModalNuevo()">
                <i class="fas fa-plus"></i> Nueva Especialidad
            </button>
        </div>
        
        <div class="table-container">
            <div id="loading" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>
            <table class="table table-hover" id="especialidades-table" style="display: none;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="especialidades-tbody"></tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nuevo/Editar -->
    <div class="modal fade" id="modalEspecialidad" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nueva Especialidad</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="formEspecialidad">
                        <input type="hidden" id="especialidad_id" name="id">
                        <div class="form-group">
                            <label for="nombre">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarEspecialidad()">
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
            cargarEspecialidades();
        });

        function cargarEspecialidades() {
            $('#loading').show();
            $('#especialidades-table').hide();
            
            fetch('citas/especialidades_json.php')
                .then(response => { if (!response.ok) { throw new Error('Error en la red: ' + response.statusText); } return response.json(); })
                .then(data => {
                    const tbody = $('#especialidades-tbody');
                    tbody.empty();
                    $('#loading').hide();
                    $('#especialidades-table').show();
                    
                    data.forEach(especialidad => {
                        const tr = `
                            <tr>
                                <td>${especialidad.id}</td>
                                <td><strong>${especialidad.nombre}</strong></td>
                                <td>
                                    <button class="btn btn-sm btn-success" onclick='abrirModalEditar(${JSON.stringify(especialidad)})' title="Editar"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger" onclick="eliminarEspecialidad(${especialidad.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        `;
                        tbody.append(tr);
                    });
                })
                .catch(error => {
                    console.error('Error al cargar las especialidades:', error);
                    $('#loading').text('Error al cargar los datos.');
                });
        }

        function abrirModalNuevo() {
            $('#formEspecialidad')[0].reset();
            $('#especialidad_id').val('');
            $('#modalTitle').text('Nueva Especialidad');
            $('#modalEspecialidad').modal('show');
        }

        function abrirModalEditar(especialidad) {
            $('#formEspecialidad')[0].reset();
            $('#modalTitle').text('Editar Especialidad');
            
            $('#especialidad_id').val(especialidad.id);
            $('#nombre').val(especialidad.nombre);
            
            $('#modalEspecialidad').modal('show');
        }

        function guardarEspecialidad() {
            const form = $('#formEspecialidad')[0];
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const id = $('#especialidad_id').val();
            const isEdit = !!id;
            const url = isEdit ? 'citas/actualizar_especialidad.php' : 'citas/crear_especialidad.php';
            
            const data = {
                id: id,
                nombre: $('#nombre').val()
            };

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    $('#modalEspecialidad').modal('hide');
                    cargarEspecialidades();
                    alert('Especialidad guardada correctamente.');
                } else {
                    alert('Error: ' + result.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocurrió un error al guardar.');
            });
        }

        function eliminarEspecialidad(id) {
            if (!confirm('¿Está seguro de que desea eliminar esta especialidad? \n\nADVERTENCIA: Los usuarios y servicios asociados a esta especialidad quedarán sin asignación. Esta acción no se puede deshacer.')) {
                return;
            }

            fetch('citas/eliminar_especialidad.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cargarEspecialidades();
                    alert('Especialidad eliminada.');
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