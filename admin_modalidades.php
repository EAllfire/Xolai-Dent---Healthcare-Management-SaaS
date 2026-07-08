<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// ensure session user vars available for header
$user_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$user_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';
$puede_crear_citas = in_array($user_tipo, ['admin', 'caja']);
$puede_gestionar_usuarios = puedeRealizar('gestionar_usuarios');
$puede_ver_admin = in_array($user_tipo, ['admin', 'medico', 'dentista']);

if (!puedeRealizar('gestionar_modalidades')) {
    header('Location: index.php'); exit;
}
?>
<!doctype html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Modalidades</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #000000; color: #e5e7eb; font-family: 'Inter', sans-serif; margin: 0; padding-top: 0; }

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
            color: #ffffff;
        }

        .settings-container { position: relative; display: inline-block; margin-right: 10px; }
        .settings-btn { 
            background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.1); 
            cursor: pointer; font-size: 1.2rem; color: #e5e7eb; padding: 6px 10px; border-radius: 10px; 
            display: flex; align-items: center; justify-content: center; transition: all 0.3s ease;
        }
        .settings-btn:hover { background: rgba(255, 255, 255, 0.15); color: #ffffff; transform: rotate(90deg); }
        .custom-dropdown-menu { 
            display: none; position: absolute; right: 0; top: 100%; background-color: #0a0a0a; 
            min-width: 200px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); border-radius: 12px; z-index: 1100; 
            margin-top: 10px; border: 1px solid #333; text-align: left;
        }
        .custom-dropdown-menu.show { display: block; }
        .custom-dropdown-menu a { 
            color: #e5e7eb; padding: 12px 20px; text-decoration: none; display: block; 
            font-size: 14px; border-bottom: 1px solid #1a1a1a; transition: all 0.2s;
        }
        .custom-dropdown-menu a:hover { background-color: rgba(41, 121, 255, 0.1); color: #2979ff; }
        
        /* Ensure modality images are displayed as small thumbnails in the table */
        #tableModalidades img.thumb,
        #previewImg.thumb {
            max-width: 100px;
            max-height: 72px;
            width: auto;
            height: auto;
            object-fit: cover;
            border-radius: 6px;
            display: inline-block;
        }

        /* Make table rows vertically centered and prevent large cell heights */
        #tableModalidades td, #tableModalidades th {
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            color: #e5e7eb;
        }
        
        #tableModalidades th {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #9ca3af;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            border-top: none;
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.02);
        }
        
        .table-striped tbody tr:nth-of-type(even) {
            background-color: transparent;
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
        
        .form-control {
            background: #000000;
            border: 1px solid #333;
            color: #e5e7eb;
        }
        
        .form-control:focus {
            background: #000000;
            color: #fff;
            border-color: #2979ff;
        }
        
        .btn-secondary {
            background: #1f2937;
            border-color: #374151;
            color: #e5e7eb;
        }

        /* On small screens, allow images and text to wrap sensibly */
        @media (max-width: 576px) {
            #tableModalidades img.thumb { max-width: 72px; max-height: 56px; }
            #tableModalidades th:nth-child(3), #tableModalidades td:nth-child(3) { width: 80px; }
        }
    </style>
</head>
<body>
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
            <a href="panel_admin.php" class="nav-link active">Administración</a>
        </nav>
        <div class="header-right">
            <div class="user-info">
                <span><?php echo htmlspecialchars($user_nombre); ?></span>
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="settings-container">
                <button onclick="toggleSettingsDropdown()" class="settings-btn"><i class="fas fa-cog"></i></button>
                <div id="ajustesDropdown" class="custom-dropdown-menu">
                    <a href="catalogo_servicios.php"><i class="fas fa-stethoscope"></i> Tratamientos</a>
                    <a href="admin_modalidades.php"><i class="fas fa-layer-group"></i> Consultorios</a>
                </div>
            </div>
            <a href="logout.php" class="btn-header"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </header>
<div class="container-fluid" style="padding-top: 120px;">
    <!-- Contenedor para alertas flotantes -->
    <div id="alert-container" style="position: fixed; top: 100px; right: 20px; z-index: 1055; width: auto; max-width: 400px;"></div>

    <div class="table-responsive">
        <table class="table table-striped" id="tableModalidades">
            <thead>
                <tr>
                    <th style="width:60px">ID</th>
                    <th>Nombre</th>
                    <th>Doctor</th>
                    <th style="width:120px">Imagen</th>
                    <th style="width:250px">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    
    <div class="mt-3 d-flex justify-content-between">
        <div>
            <a href="panel_admin.php" class="btn btn-secondary">Volver al Panel</a>
            <button id="btnNuevo" class="btn btn-primary">Agregar Consultorio</button>
        </div>
        <div>
            <small class="text-muted">Crear / editar / eliminar consultorios y subir imágenes para mostrarlas en el calendario.</small>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal" tabindex="-1" role="dialog" id="addModal">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Agregar Consultorio</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="closeModal('addModal')">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <form id="formAdd">
                <div class="form-group">
                    <label for="add_nombre">Nombre</label>
                    <input class="form-control" id="add_nombre" name="nombre" required>
                </div>
                <div class="form-group">
                    <label for="add_usuario_id">Médico Asignado (Para Sincronización iCloud)</label>
                    <select class="form-control" id="add_usuario_id" name="usuario_id"></select>
                </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancelar</button>
            <button type="button" class="btn btn-primary" id="saveAdd">Guardar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal" tabindex="-1" role="dialog" id="editModal">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Editar Consultorio</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="closeModal('editModal')">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <form id="formEdit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label for="edit_nombre">Nombre</label>
                    <input class="form-control" id="edit_nombre" name="nombre" required>
                </div>
                <div class="form-group">
                    <label for="edit_usuario_id">Doctor Asignado</label>
                    <select class="form-control" id="edit_usuario_id" name="usuario_id"></select>
                </div>
                <!-- Vista previa de imagen y botón para quitar -->
                <div class="form-group">
                    <label>Imagen Actual</label>
                    <div id="edit_image_container">
                        <img id="edit_previewImg" src="" class="thumb" style="display:none; margin-bottom: 10px;">
                        <button type="button" id="btnRemoveImage" class="btn btn-sm btn-outline-warning" style="display:none;">Quitar Imagen</button>
                        <span id="no_image_text" class="text-muted" style="display: block;">No hay imagen asignada.</span>
                    </div>
                </div>
                <div id="editAlert" class="mt-2"></div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancelar</button>
            <button type="button" class="btn btn-primary" id="saveEdit">Guardar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal" tabindex="-1" role="dialog" id="uploadModal">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Subir / Cambiar Imagen</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="closeModal('uploadModal')">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <form id="formUpload">
                <input type="hidden" name="modalidad_id" id="upload_modalidad_id">
                <div class="form-group">
                    <input type="file" class="form-control-file" id="upload_input" name="imagen" accept="image/*" required>
                </div>
                <div class="form-group">
                    <img id="previewImg" src="" class="thumb" style="display:none">
                </div>
                <div id="uploadAlert"></div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('uploadModal')">Cerrar</button>
            <button type="button" class="btn btn-primary" id="btnUpload">Subir Imagen</button>
          </div>
        </div>
      </div>
    </div>
</div>

    <!-- Dependencias: jQuery y Bootstrap Bundle (incluye Popper) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    var candidates = [
        'citas/modalidades_json.php',
        './citas/modalidades_json.php',
        '/agenda/citas/modalidades_json.php'
    ];

    let modalidadesData = []; // Almacenar datos de modalidades

    function showAlert(message, type = 'success') {
        const alertContainer = document.getElementById('alert-container');
        const alertId = 'alert-' + Date.now();
        const alert = `
            <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
            </div>`;
        alertContainer.innerHTML += alert;
        setTimeout(() => { $(`#${alertId}`).alert('close'); }, 5000);
    }

    function parseJsonOrError(response){
        if (!response.ok) {
            if (response.status === 403) return Promise.reject(new Error('403: Acceso denegado — ¿sesión expirada? Por favor inicia sesión.'));
            return response.text().then(function(t){
                var preview = (t||'').toString().slice(0,200);
                return Promise.reject(new Error('HTTP '+response.status+' — respuesta: '+ preview));
            });
        }
        var ct = (response.headers.get('content-type')||'').toLowerCase();
        if (ct.indexOf('application/json') !== -1) return response.json();
        return response.text().then(function(t){
            var preview = (t||'').toString().slice(0,200);
            return Promise.reject(new Error('Respuesta no JSON del servidor: '+ preview));
        });
    }

    function closeModal(id){ document.getElementById(id).style.display='none'; document.getElementById(id).classList.remove('show'); }
    function openModal(id){ document.getElementById(id).style.display='block'; document.getElementById(id).classList.add('show'); }

    function cargarDoctores() {
        const doctorCandidates = [
            'citas/lista_doctores_json.php',
            './citas/lista_doctores_json.php',
            '../citas/lista_doctores_json.php'
        ];

        function tryFetchDoctors(i) {
            if (i >= doctorCandidates.length) return;
            
            fetch(doctorCandidates[i])
                .then(parseJsonOrError)
                .then(data => {
                    const addSelect = document.getElementById('add_usuario_id');
                    const editSelect = document.getElementById('edit_usuario_id');
                    
                    if (!addSelect || !editSelect) return;

                    let options = '<option value="">-- Sin asignar --</option>';
                    if (Array.isArray(data)) {
                        data.forEach(doc => {
                            options += `<option value="${doc.id}">${escapeHtml(doc.nombre)}</option>`;
                        });
                    }
                    
                    addSelect.innerHTML = options;
                    editSelect.innerHTML = options;
                })
                .catch(() => tryFetchDoctors(i + 1));
        }
        tryFetchDoctors(0);
    }

    function fetchLista(){
        var tbody = document.querySelector('#tableModalidades tbody'); tbody.innerHTML = '<tr><td colspan="5">Cargando...</td></tr>';
        function tryFetch(i){
            if (i >= candidates.length) {
                tbody.innerHTML = '<tr><td colspan="5"><div class="alert alert-warning">No se pudo cargar la lista de consultorios. Verifica la ruta a <code>citas/modalidades_json.php</code>.</div></td></tr>';
                return;
            }
            fetch(candidates[i]).then(parseJsonOrError).then(function(data){
                modalidadesData = data; // Guardar datos globalmente
                tbody.innerHTML = '';
                if (!data || data.length === 0) { tbody.innerHTML = '<tr><td colspan="5"><em>No hay consultorios registrados.</em></td></tr>'; return; }
                data.forEach(function(m){
                    var tr = document.createElement('tr');

                    var tdId = document.createElement('td');
                    tdId.textContent = m.id;
                    tr.appendChild(tdId);

                    var tdName = document.createElement('td');
                    tdName.innerHTML = m.nombre ? escapeHtml(m.nombre) : '<em>Sin nombre</em>';
                    tr.appendChild(tdName);

                    var tdMedico = document.createElement('td');
                    tdMedico.innerHTML = '<small class="text-muted">' + (m.medico_nombre ? escapeHtml(m.medico_nombre) : 'N/A') + '</small>';
                    tr.appendChild(tdMedico);

                    var tdImage = document.createElement('td');
                    if (m.imagen && (m.imagen.endsWith('.php') || m.imagen.endsWith('.sql'))) {
                        console.error('URL de imagen inválida para Consultorio ID ' + m.id + ': ' + m.imagen);
                        tdImage.innerHTML = '<div class="text-danger font-weight-bold">URL Inválida</div>';
                    } else {
                        tdImage.innerHTML = m.imagen ? '<img src="'+escapeHtml(m.imagen)+'" class="thumb">' : '<div class="text-muted">Sin imagen</div>';
                    }
                    tr.appendChild(tdImage);

                    var tdActions = document.createElement('td');

                    var btnEdit = document.createElement('button');
                    btnEdit.className = 'btn btn-sm btn-outline-secondary mr-1';
                    btnEdit.innerHTML = '<i class="fas fa-edit"></i> Editar';
                    btnEdit.onclick = function() { openEdit(m.id); };
                    tdActions.appendChild(btnEdit);

                    var btnUpload = document.createElement('button');
                    btnUpload.className = 'btn btn-sm btn-outline-primary mr-1';
                    btnUpload.textContent = 'Subir imagen';
                    btnUpload.onclick = function() { openUpload(m.id); };
                    tdActions.appendChild(btnUpload);

                    var btnDelete = document.createElement('button');
                    btnDelete.className = 'btn btn-sm btn-outline-danger';
                    btnDelete.innerHTML = '<i class="fas fa-trash"></i>';
                    btnDelete.onclick = function() { confirmDelete(m.id, m.nombre); };
                    tdActions.appendChild(btnDelete);

                    tr.appendChild(tdActions);
                    tbody.appendChild(tr);
                });
            }).catch(function(err){
                console.warn('Fetch failed for', candidates[i], err);
                // If non-JSON or 403, show that message and stop trying other candidates
                if (err && err.message && (err.message.indexOf('403:') === 0 || err.message.indexOf('Respuesta no JSON') === 0 || err.message.indexOf('HTTP') === 0)){
                    tbody.innerHTML = '<tr><td colspan="5"><div class="alert alert-danger">'+escapeHtml(err.message)+'</div></td></tr>';
                    return;
                }
                tryFetch(i+1);
            });
        }
        tryFetch(0);
    }

    function escapeHtml(s){ if (!s) return ''; return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }

    document.getElementById('btnNuevo').onclick = function() {
        document.getElementById('add_nombre').value = '';
        openModal('addModal');
    };

    document.getElementById('saveAdd').onclick = function() {
        var nombre = document.getElementById('add_nombre').value.trim();
        var usuario_id = document.getElementById('add_usuario_id').value;
        if (!nombre) { alert('El nombre es requerido.'); return; }
        this.disabled = true;
        fetch('citas/crear_modalidad.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({nombre:nombre, usuario_id:usuario_id})})
            .then(parseJsonOrError)
            .then(j=>{
                this.disabled = false;
                if (j.success) { 
                    closeModal('addModal'); 
                    fetchLista(); 
                    showAlert('Consultorio creado exitosamente.');
                } else { 
                    alert('Error: ' + (j.error || 'Error desconocido.'));
                }
            }).catch(e=>{ this.disabled=false; alert('Error de conexión: '+e.message); });
    };

    function openEdit(id){
        const modalidad = modalidadesData.find(m => m.id == id);
        if (!modalidad) return;

        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nombre').value = modalidad.nombre || '';
        document.getElementById('edit_usuario_id').value = modalidad.usuario_id || '';
        document.getElementById('editAlert').innerHTML = '';

        const imgPreview = document.getElementById('edit_previewImg');
        const btnRemove = document.getElementById('btnRemoveImage');
        const noImageText = document.getElementById('no_image_text');

        if (modalidad.imagen) {
            imgPreview.src = modalidad.imagen;
            imgPreview.style.display = 'block';
            btnRemove.style.display = 'inline-block';
            noImageText.style.display = 'none';
            btnRemove.onclick = function() { removeImage(id); };
        } else {
            imgPreview.src = '';
            imgPreview.style.display = 'none';
            btnRemove.style.display = 'none';
            noImageText.style.display = 'block';
        }

        openModal('editModal');
    }

    document.getElementById('saveEdit').onclick = function(){
        var id = parseInt(document.getElementById('edit_id').value || 0,10);
        var nombre = document.getElementById('edit_nombre').value.trim();
        var usuario_id = document.getElementById('edit_usuario_id').value;
        if (!nombre) { alert('El nombre es requerido.'); return; }
        this.disabled = true;
        fetch('citas/actualizar_modalidad.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:id,nombre:nombre, usuario_id:usuario_id})})
            .then(parseJsonOrError)
            .then(j=>{
                this.disabled = false;
                if (j.success) { 
                    closeModal('editModal'); 
                    fetchLista(); 
                    showAlert('Modalidad actualizada correctamente.');
                } else { 
                    alert('Error: ' + (j.error || 'Error desconocido.'));
                }
            }).catch(e=>{ this.disabled=false; alert('Error de conexión: '+e.message); });
    };

    function openUpload(id){
        document.getElementById('upload_modalidad_id').value = id;
        document.getElementById('uploadAlert').innerHTML='';
        document.getElementById('previewImg').style.display='none';
        document.getElementById('upload_input').value='';
        openModal('uploadModal');
    }

    document.getElementById('upload_input').addEventListener('change', function(e){
        var f = this.files && this.files[0];
        if (!f) { document.getElementById('previewImg').style.display='none'; return; }
        var url = URL.createObjectURL(f);
        var img = document.getElementById('previewImg'); img.src = url; img.style.display='inline-block';
    });

    document.getElementById('btnUpload').onclick = function(){
        var id = document.getElementById('upload_modalidad_id').value;
        var input = document.getElementById('upload_input');
        if (!input.files || !input.files[0]) { alert('Seleccione un archivo.'); return; }
        var fd = new FormData(); fd.append('modalidad_id', id); fd.append('imagen', input.files[0]);
        this.disabled = true;
        fetch('citas/guardar_modalidad_imagen.php',{method:'POST',body:fd})
            .then(parseJsonOrError)
            .then(j=>{
                this.disabled = false;
                if (j.success) { closeModal('uploadModal'); fetchLista(); showAlert('Imagen subida correctamente.'); } else { alert('Error: ' + (j.error || 'Error desconocido.')); }
            }).catch(e=>{ this.disabled=false; alert('Error de conexión: '+e.message); });
    };

    function confirmDelete(id, nombre){
        if (!confirm('Eliminar modalidad "'+(nombre||'')+'"? Esta acción no se puede deshacer.')) return;
        fetch('citas/eliminar_modalidad.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:id})})
            .then(parseJsonOrError)
            .then(j=>{ if (j.success) fetchLista(); else alert('Error: '+(j.error||'')); })
            .catch(e=>alert('Error: '+e.message));
    }

    function removeImage(id) {
        if (!confirm('¿Está seguro de que desea quitar la imagen de este consultorio?')) return;

        fetch('citas/eliminar_imagen_modalidad.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(parseJsonOrError)
        .then(j => {
            if (j.success) {
                showAlert('Imagen eliminada correctamente.', 'success');
                closeModal('editModal');
                fetchLista(); // Refresh the list
            } else {
                showAlert(j.error || 'No se pudo eliminar la imagen.', 'danger');
            }
        })
        .catch(e => {
            showAlert('Error de conexión: ' + e.message, 'danger');
        });
    }

    function toggleSettingsDropdown() {
        document.getElementById("ajustesDropdown").classList.toggle("show");
    }
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

    // Ahora el $ ya está definido porque cargamos jQuery arriba
    $(document).ready(function() {
        cargarDoctores();
        fetchLista();
    });
    </script>
</body>
</html>