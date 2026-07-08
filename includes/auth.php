<?php
// Función para verificar si el usuario está logueado
function verificarSesion() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Función para verificar permisos por tipo de usuario
function verificarPermisos($permisos_requeridos) {
    verificarSesion();
    
    $tipo_usuario = $_SESSION['usuario_tipo'] ?? '';
    
    // Solo el Superadmin tiene acceso global absoluto sin restricciones.
    if ($tipo_usuario === 'superadmin') {
        return true;
    }
    
    // Verificar permisos específicos
    if (is_array($permisos_requeridos)) {
        return in_array($tipo_usuario, $permisos_requeridos);
    } else {
        return $tipo_usuario === $permisos_requeridos;
    }
}

// Función para obtener información del usuario actual
function obtenerUsuarioActual() {
    return [
        'id' => $_SESSION['usuario_id'] ?? null,
        'nombre' => $_SESSION['usuario_nombre'] ?? '',
        'correo' => $_SESSION['usuario_correo'] ?? '',
        'tipo' => $_SESSION['usuario_tipo'] ?? ''
    ];
}

// Devuelve un array de IDs de usuarios que el usuario actual puede ver/gestionar.
// - Si retorna null => sin restricción (acceso global)
// - Si retorna array de ints => limitar consultas a esos user_ids
function obtenerIdsPermitidos() {
    $tipo = $_SESSION['usuario_tipo'] ?? '';
    $uid = $_SESSION['usuario_id'] ?? null;
    $id_padre = $_SESSION['id_padre'] ?? null;

    // Superadmin sin restricciones
    if ($tipo === 'superadmin') return null;

    // Dentista principal o cualquier usuario raíz (sin padre) ve su equipo
    if (empty($id_padre)) {
        return ['SELF_AND_CHILDREN'];
    }

    // Usuarios con un superior heredan el alcance del equipo de su padre
    return ['PARENT_ONLY'];
}

// Función para generar badge de tipo de usuario
function getBadgeTipoUsuario($tipo) {
    $badges = [
        'admin' => '<span class="badge badge-danger">Admin</span>',
        'caja' => '<span class="badge badge-warning">Caja</span>',
        'lectura' => '<span class="badge badge-secondary">Lectura</span>',
        'medico' => '<span class="badge badge-secondary">Médico</span>',
        'superadmin' => '<span class="badge badge-info">Super Admin</span>',
        'dentista' => '<span class="badge badge-primary" style="background-color: #2979ff;">Dentista</span>',
        'recepcion' => '<span class="badge badge-success">Recepción</span>',
        'dentista_externo' => '<span class="badge badge-info">Dentista Externo</span>'
    ];
    return $badges[$tipo] ?? '<span class="badge badge-dark">Desconocido</span>';
}

// Función para verificar si el usuario puede realizar una acción específica
function puedeRealizar($accion) {
    $tipo_usuario = strtolower($_SESSION['usuario_tipo'] ?? '');
    
    // Superadmin puede hacer todo
    if ($tipo_usuario === 'superadmin') {
        return true;
    }

    $permisos = [
        'crear_citas' => ['admin', 'caja', 'medico', 'dentista', 'recepcion'],
        'editar_citas' => ['admin', 'caja', 'medico', 'dentista', 'recepcion'],
        'eliminar_citas' => ['admin', 'caja', 'medico', 'dentista', 'recepcion'],
        'cambiar_estados' => ['admin', 'caja', 'medico', 'dentista', 'recepcion'],
        'ver_citas' => ['admin', 'caja', 'lectura', 'medico', 'dentista', 'recepcion'], 
        'gestionar_usuarios' => ['admin', 'dentista', 'medico', 'recepcion'],
        'gestionar_servicios' => ['admin', 'medico', 'dentista', 'recepcion'],
        'acceder_reportes' => ['admin', 'caja', 'lectura', 'dentista', 'recepcion'], 
        'configurar_sistema' => ['superadmin', 'admin', 'dentista', 'medico'],
        'ver_catalogo_pacientes' => ['admin', 'caja', 'lectura', 'medico', 'dentista', 'recepcion', 'dentista_externo'],
        'ver_catalogo_citas' => ['admin', 'caja', 'lectura', 'medico', 'dentista', 'recepcion'],
        'gestionar_pacientes' => ['admin', 'caja', 'medico', 'dentista', 'recepcion', 'dentista_externo'],
        'expediente_clinico' => ['admin', 'caja', 'lectura', 'medico', 'dentista', 'recepcion', 'dentista_externo', 'superadmin'],
        'ver_catalogo_servicios' => ['admin', 'caja', 'lectura', 'medico', 'dentista', 'recepcion'],
        'gestionar_modalidades' => ['admin', 'superadmin', 'medico', 'dentista', 'recepcion'],
        'gestionar_especialidades' => ['admin', 'superadmin', 'dentista', 'medico'],
        'gestionar_origenes_recomendacion' => ['admin', 'superadmin', 'dentista', 'medico']
    ];
    
    $permitidos = $permisos[$accion] ?? [];
    if (!in_array($tipo_usuario, $permitidos)) return false;

    $id_padre = (int)($_SESSION['id_padre'] ?? 0);
    $uid = (int)($_SESSION['usuario_id'] ?? 0);

    // Un colaborador es alguien que tiene un padre asignado que NO es él mismo y NO es cero.
    // Si es 0 o el mismo ID, es la Cuenta Principal.
    // CORRECCIÓN: Los usuarios tipo 'admin' NO son considerados colaboradores restringidos,
    // incluso si tienen un superior (Admin Derivado), ya que deben tener acceso a gestión.
    $es_colaborador = ($id_padre > 0 && $id_padre !== $uid && $tipo_usuario !== 'admin');

    // Acciones administrativas de alta seguridad reservadas para el Dueño Principal (Padre).
    // Se libera 'gestionar_especialidades' para que los profesionales principales puedan 
    // administrar sus catálogos sin bloqueos de jerarquía.
    $acciones_admin = [
        'gestionar_usuarios', 'gestionar_servicios', 'gestionar_modalidades', 
        'gestionar_origenes_recomendacion', 'gestionar_especialidades', 'configurar_sistema'
    ];
    
    // Bloquear acciones administrativas solo si es colaborador
    if (in_array($accion, $acciones_admin) && $es_colaborador) {
        return false;
    }

    if (($tipo_usuario === 'dentista' || $tipo_usuario === 'recepcion' || $es_colaborador) && $accion === 'configurar_sistema') {
        return false;
    }

    if ($es_colaborador && $accion === 'acceder_reportes') {
        return false;
    }

    // Dentista externo: Solo lectura visual de agenda, sin tabla, sin edición, sin creación
    if ($tipo_usuario === 'dentista_externo' && in_array($accion, ['crear_citas', 'editar_citas', 'eliminar_citas', 'cambiar_estados', 'ver_catalogo_citas'])) {
        return false;
    }

    return true;
}

// Función para cerrar sesión
function cerrarSesion() {
    session_start();
    session_destroy();
    header('Location: login.php');
    exit;
}
?>