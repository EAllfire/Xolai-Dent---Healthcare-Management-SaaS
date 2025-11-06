<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';

// ensure session user vars available for header
$user_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$user_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';
$puede_crear_citas = in_array($user_tipo, ['admin', 'caja']);
$puede_gestionar_usuarios = ($user_tipo === 'admin');
if (!puedeRealizar('gestionar_usuarios')) {
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
    <link rel="stylesheet" href="css/header.css">
    <style>
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
        }

        /* On small screens, allow images and text to wrap sensibly */
        @media (max-width: 576px) {
            #tableModalidades img.thumb { max-width: 72px; max-height: 56px; }
            #tableModalidades th:nth-child(3), #tableModalidades td:nth-child(3) { width: 80px; }
        }
    </style>
</head>
<body>
    <?php
    $show_calendar = true; $show_back = false; $show_admin_tools = $puede_gestionar_usuarios; $show_mobile_menu = false;
    include __DIR__ . '/includes/header.php';
    ?>
<div class="container-fluid" style="padding-top: 100px;">
    <div class="table-responsive">
        <table class="table table-striped" id="tableModalidades">
            <thead>
                <tr>
                    <th style="width:60px">ID</th>
                    <th>Nombre</th>
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
            <button id="btnNuevo" class="btn btn-primary">Agregar Modalidad</button>
        </div>
        <div>
            <small class="text-muted">Crear / editar / eliminar modalidades y subir imágenes para mostrarlas en el calendario.</small>
        </div>
    </div>

    <!-- Add Modal -->
    <div class="modal" tabindex="-1" role="dialog" id="addModal">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Agregar Modalidad</h5>
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
                <div id="addAlert"></div>
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
            <h5 class="modal-title">Editar Modalidad</h5>
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
                <div id="editAlert"></div>
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


    <script>
    var candidates = [
        'citas/modalidades_json.php',
        './citas/modalidades_json.php',
        '/agenda/citas/modalidades_json.php'
    ];

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

    function showAlert(container, html, type){
        container.innerHTML = '<div class="alert alert-'+(type||'danger')+'">'+html+'</div>';
    }

    function closeModal(id){ document.getElementById(id).style.display='none'; document.getElementById(id).classList.remove('show'); }
    function openModal(id){ document.getElementById(id).style.display='block'; document.getElementById(id).classList.add('show'); }

    function fetchLista(){
        var tbody = document.querySelector('#tableModalidades tbody'); tbody.innerHTML = '<tr><td colspan="4">Cargando...</td></tr>';
        function tryFetch(i){
            if (i >= candidates.length) {
                tbody.innerHTML = '<tr><td colspan="4"><div class="alert alert-warning">No se pudo cargar la lista de modalidades. Verifica la ruta a <code>citas/modalidades_json.php</code>.</div></td></tr>';
                return;
            }
            fetch(candidates[i]).then(parseJsonOrError).then(function(data){
                tbody.innerHTML = '';
                if (!data || data.length === 0) { tbody.innerHTML = '<tr><td colspan="4"><em>No hay modalidades registradas.</em></td></tr>'; return; }
                data.forEach(function(m){
                    var tr = document.createElement('tr');

                    var tdId = document.createElement('td');
                    tdId.textContent = m.id;
                    tr.appendChild(tdId);

                    var tdName = document.createElement('td');
                    tdName.innerHTML = m.nombre ? escapeHtml(m.nombre) : '<em>Sin nombre</em>';
                    tr.appendChild(tdName);

                    var tdImage = document.createElement('td');
                    if (m.imagen && (m.imagen.endsWith('.php') || m.imagen.endsWith('.sql'))) {
                        console.error('URL de imagen inválida para modalidad ID ' + m.id + ': ' + m.imagen);
                        tdImage.innerHTML = '<div class="text-danger font-weight-bold">URL Inválida</div>';
                    } else {
                        tdImage.innerHTML = m.imagen ? '<img src="'+escapeHtml(m.imagen)+'" class="thumb">' : '<div class="text-muted">Sin imagen</div>';
                    }
                    tr.appendChild(tdImage);

                    var tdActions = document.createElement('td');

                    var btnEdit = document.createElement('button');
                    btnEdit.className = 'btn btn-sm btn-outline-secondary mr-1';
                    btnEdit.textContent = 'Editar';
                    btnEdit.onclick = function() { openEdit(m.id, m.nombre); };
                    tdActions.appendChild(btnEdit);

                    var btnUpload = document.createElement('button');
                    btnUpload.className = 'btn btn-sm btn-outline-primary mr-1';
                    btnUpload.textContent = 'Subir imagen';
                    btnUpload.onclick = function() { openUpload(m.id); };
                    tdActions.appendChild(btnUpload);

                    var btnDelete = document.createElement('button');
                    btnDelete.className = 'btn btn-sm btn-outline-danger';
                    btnDelete.textContent = 'Eliminar';
                    btnDelete.onclick = function() { confirmDelete(m.id, m.nombre); };
                    tdActions.appendChild(btnDelete);

                    tr.appendChild(tdActions);
                    tbody.appendChild(tr);
                });
            }).catch(function(err){
                console.warn('Fetch failed for', candidates[i], err);
                // If non-JSON or 403, show that message and stop trying other candidates
                if (err && err.message && (err.message.indexOf('403:') === 0 || err.message.indexOf('Respuesta no JSON') === 0 || err.message.indexOf('HTTP') === 0)){
                    tbody.innerHTML = '<tr><td colspan="4"><div class="alert alert-danger">'+escapeHtml(err.message)+'</div></td></tr>';
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
        document.getElementById('addAlert').innerHTML = '';
        openModal('addModal');
    };

    document.getElementById('saveAdd').onclick = function() {
        var nombre = document.getElementById('add_nombre').value.trim();
        if (!nombre) { showAlert(document.getElementById('addAlert'),'Nombre requerido','warning'); return; }
        this.disabled = true;
        fetch('citas/crear_modalidad.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({nombre:nombre})})
            .then(parseJsonOrError)
            .then(j=>{
                this.disabled = false;
                if (j.success) { closeModal('addModal'); fetchLista(); } else { showAlert(document.getElementById('addAlert'), j.error || 'Error'); }
            }).catch(e=>{ this.disabled=false; showAlert(document.getElementById('addAlert'), 'Error: '+e.message); });
    };

    function openEdit(id, nombre){
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nombre').value = nombre || '';
        document.getElementById('editAlert').innerHTML='';
        openModal('editModal');
    }

    document.getElementById('saveEdit').onclick = function(){
        var id = parseInt(document.getElementById('edit_id').value || 0,10);
        var nombre = document.getElementById('edit_nombre').value.trim();
        if (!nombre) { showAlert(document.getElementById('editAlert'),'Nombre requerido','warning'); return; }
        this.disabled = true;
        fetch('citas/actualizar_modalidad.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:id,nombre:nombre})})
            .then(parseJsonOrError)
            .then(j=>{
                this.disabled = false;
                if (j.success) { closeModal('editModal'); fetchLista(); } else { showAlert(document.getElementById('editAlert'), j.error || 'Error'); }
            }).catch(e=>{ this.disabled=false; showAlert(document.getElementById('editAlert'), 'Error: '+e.message); });
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
        if (!input.files || !input.files[0]) { showAlert(document.getElementById('uploadAlert'),'Seleccione un archivo','warning'); return; }
        var fd = new FormData(); fd.append('modalidad_id', id); fd.append('imagen', input.files[0]);
        this.disabled = true;
        fetch('citas/guardar_modalidad_imagen.php',{method:'POST',body:fd})
            .then(parseJsonOrError)
            .then(j=>{
                this.disabled = false;
                if (j.success) { closeModal('uploadModal'); fetchLista(); } else { showAlert(document.getElementById('uploadAlert'), j.error || 'Error'); }
            }).catch(e=>{ this.disabled=false; showAlert(document.getElementById('uploadAlert'), 'Error: '+e.message); });
    };

    function confirmDelete(id, nombre){
        if (!confirm('Eliminar modalidad "'+(nombre||'')+'"? Esta acción no se puede deshacer.')) return;
        fetch('citas/eliminar_modalidad.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:id})})
            .then(parseJsonOrError)
            .then(j=>{ if (j.success) fetchLista(); else alert('Error: '+(j.error||'')); })
            .catch(e=>alert('Error: '+e.message));
    }

    // initial load
    fetchLista();
    </script>
</body>
</html>