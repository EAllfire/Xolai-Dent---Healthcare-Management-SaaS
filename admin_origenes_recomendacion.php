<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Verificar permisos: Admin o Dentista Padre
if (!puedeRealizar('gestionar_origenes_recomendacion')) {
    header('Location: index.php');
    exit;
}

$usuario = obtenerUsuarioActual();
$user_nombre = $usuario['nombre'] ?? 'Usuario';

// Configuración del header para Xolai
$show_calendar = true;
$show_back = true;
$show_admin_tools = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Orígenes de Recomendación</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #000000; color: #e5e7eb; padding-top: 0; margin: 0; }
        
        /* Header Styles - Xolai Style */
        .main-header {
            background: rgba(10, 10, 10, 0.5);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            color: white; height: 80px; display: flex; justify-content: space-between; align-items: center;
            padding: 0 40px; position: fixed; top: 0; left: 0; right: 0; z-index: 1050;
            mask-image: linear-gradient(to bottom, black 70%, transparent 100%);
            -webkit-mask-image: linear-gradient(to bottom, black 70%, transparent 100%);
        }
        
        .header-left, .header-right { display: flex; align-items: center; gap: 20px; }
        .header-logo-img { height: 45px; width: auto; }
        .header-title { font-size: 24px; font-weight: 700; color: white; letter-spacing: 1px; }
        .user-info { display: flex; align-items: center; gap: 10px; font-weight: 500; }

        .btn-header {
            color: #e5e7eb; background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 16px; font-size: 14px; cursor: pointer; border-radius: 10px; text-decoration: none; transition: all 0.2s ease;
        }
        .btn-header:hover { background: rgba(255, 255, 255, 0.15); color: #ffffff; }

        .container-custom { max-width: 1200px; margin: 0 auto; padding: 120px 15px 40px 15px; }
        
        .table-container {
            background: #0a0a0a; border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.05); padding: 1.5rem;
        }

        .actions-bar {
            background: #0a0a0a; padding: 1.5rem; border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;
        }
        
        .table { color: #e5e7eb; margin-bottom: 0; }
        .table thead th {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1); color: #9ca3af; font-weight: 600;
            text-transform: uppercase; font-size: 0.85rem; padding: 1rem; border-top: none;
        }
        .table td { border-top: 1px solid rgba(255, 255, 255, 0.05); padding: 1rem; vertical-align: middle; }
        .table-hover tbody tr:hover { background-color: rgba(41, 121, 255, 0.05); }

        .form-control { background: #000000; border: 1px solid #333; color: #e5e7eb; border-radius: 8px; }
        .form-control:focus { background: #000000; color: #fff; border-color: #2979ff; }
        
        .btn-glass {
            background: rgba(41, 121, 255, 0.1); border: 1px solid rgba(41, 121, 255, 0.2);
            color: #e5e7eb; font-weight: bold; border-radius: 8px; transition: all 0.3s;
        }
        .btn-glass:hover { background: rgba(41, 121, 255, 0.2); color: #fff; border-color: #2979ff; transform: translateY(-2px); }
        
        .modal-content { background: #0a0a0a; border: 1px solid #333; color: #e5e7eb; }
        .modal-header, .modal-footer { border-color: #222; background: #111; }
        .close { color: #fff; text-shadow: none; opacity: 0.8; }
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

    <div class="container-custom">
        <h1 class="mb-4"><i class="fas fa-bullhorn text-primary mr-2"></i> Orígenes de Recomendación</h1>
        
        <div class="actions-bar">
            <a href="panel_admin.php" class="btn btn-header"><i class="fas fa-arrow-left mr-1"></i> Panel</a>
            <button class="btn btn-glass px-4 py-2" onclick="abrirModalNuevo()">
                <i class="fas fa-plus mr-1"></i> Nuevo Origen
            </button>
        </div>
        
        <div class="table-container">
            <div id="loading" class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>
            <table class="table table-hover" id="origenes-table" style="display: none;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre de la Fuente</th>
                        <th class="text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody id="origenes-tbody"></tbody>
            </table>
        </div>
    </div>

    <!-- Modal Nuevo/Editar -->
    <div class="modal fade" id="modalOrigen" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Nuevo Origen</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <form id="formOrigen">
                        <input type="hidden" id="origen_id" name="id">
                        <div class="form-group">
                            <label>Nombre del Origen (ej. TikTok, Convenio Empresa X)</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required placeholder="Ingrese el nombre">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary px-4" onclick="guardarOrigen()">
                        <i class="fas fa-save mr-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            cargarOrigenes();
        });

        function cargarOrigenes() {
            $('#loading').show();
            $('#origenes-table').hide();
            
            fetch('citas/origenes_recomendacion_json.php')
                .then(r => r.json())
                .then(data => {
                    const tbody = $('#origenes-tbody');
                    tbody.empty();
                    $('#loading').hide();
                    $('#origenes-table').show();
                    
                    data.forEach(origen => {
                        tbody.append(`
                            <tr>
                                <td>${origen.id}</td>
                                <td><strong>${origen.nombre}</strong></td>
                                <td class="text-right">
                                    <button class="btn btn-sm btn-outline-success mr-2" onclick='abrirModalEditar(${JSON.stringify(origen)})'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="eliminarOrigen(${origen.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `);
                    });
                })
                .catch(e => {
                    console.error(e);
                    $('#loading').html('<div class="alert alert-danger">Error al conectar con el servidor</div>');
                });
        }

        function abrirModalNuevo() {
            $('#formOrigen')[0].reset();
            $('#origen_id').val('');
            $('#modalTitle').text('Nuevo Origen de Recomendación');
            $('#modalOrigen').modal('show');
        }

        function abrirModalEditar(origen) {
            $('#origen_id').val(origen.id);
            $('#nombre').val(origen.nombre);
            $('#modalTitle').text('Editar Origen');
            $('#modalOrigen').modal('show');
        }

        function guardarOrigen() {
            const id = $('#origen_id').val();
            const nombre = $('#nombre').val().trim();
            if (!nombre) return alert('El nombre es obligatorio');

            const url = id ? 'citas/actualizar_origen_recomendacion.php' : 'citas/crear_origen_recomendacion.php';
            
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, nombre: nombre })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    $('#modalOrigen').modal('hide');
                    cargarOrigenes();
                } else {
                    alert('Error: ' + res.error);
                }
            })
            .catch(() => alert('Ocurrió un error al procesar la solicitud'));
        }

        function eliminarOrigen(id) {
            if (!confirm('¿Está seguro de eliminar este origen? \nNota: Solo podrá eliminarlo si no hay pacientes asociados.')) return;

            fetch('citas/eliminar_origen_recomendacion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    cargarOrigenes();
                } else {
                    alert('Error: ' + res.error);
                }
            })
            .catch(() => alert('Ocurrió un error al eliminar'));
        }
    </script>
</body>
</html>
