<?php
session_start();
require_once("includes/db.php");
require_once("includes/auth.php");

// Validar sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['usuario_id'];
$user_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$user_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';

// Solo permitir acceso a Administradores y al Dentista Principal (Padre)
// El dentista colaborador (con id_padre) tiene prohibido el acceso a gestión.
$es_admin = ($user_tipo === 'admin');
$es_recepcion = ($user_tipo === 'recepcion');
$es_dentista_principal = ($user_tipo === 'dentista' && empty($_SESSION['id_padre']));

// Identificar si es una cuenta "Raíz" (Sin padre) o Superadmin
$es_dueno_principal = ($es_admin || $es_dentista_principal) && empty($_SESSION['id_padre']);
$es_super = ($user_tipo === 'superadmin');

if (!$es_admin && !$es_dentista_principal && !$es_recepcion && !$es_super) {
    header("Location: index.php");
    exit();
}

// Prepare header flags
$puede_ver_admin = ($user_tipo === 'superadmin') || in_array($user_tipo, ['admin', 'medico', 'dentista', 'recepcion']);
$puede_crear_citas = puedeRealizar('crear_citas');
$puede_gestionar_usuarios = puedeRealizar('gestionar_usuarios');
$show_calendar = false; // admin panel doesn't need calendar button
$show_back = true; // show back button to return
$show_admin_tools = $puede_gestionar_usuarios;

// Identificar si es un administrador con superior
$es_admin_derivado = ($user_tipo === 'admin' && !empty($_SESSION['id_padre']));
$es_admin_sistema = $es_super || $es_dueno_principal; // Superadmin y dueños ven config. técnica

// Quién puede ver reportes de dinero/pagos
// Bloquear dentista derivado (con id_padre)
$puede_ver_reporte_dental = $es_admin || $es_dentista_principal || $es_super || $es_recepcion || ($user_tipo === 'caja');

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Administración</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
  body {
    font-family: 'Inter', sans-serif;
    background-color: #000000;
    color: #e5e7eb;
    margin: 0;
    padding-top: 0;
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
  .custom-dropdown-menu.show { display: block; }
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

  /* Page-specific admin styles */
  main{padding:120px 24px 24px 24px;max-width:1100px;margin:0 auto}
  .admin-grid{display:flex;gap:18px;flex-wrap:wrap;justify-content:center}
  .admin-card{
      background:#0a0a0a;
      border-radius:12px;
      box-shadow:0 8px 24px rgba(0,0,0,0.5);
      width:320px;
      min-height:160px;
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      text-align:center;
      padding:20px;
      color:#e5e7eb;
      text-decoration:none;
      border: 1px solid rgba(255, 255, 255, 0.05);
      transition: all 0.3s ease;
  }
  .admin-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 0 20px rgba(41, 121, 255, 0.2);
      border-color: #2979ff;
      text-decoration: none;
      color: #fff;
  }
  .admin-card i{font-size:36px;margin-bottom:12px;color:#2979ff; text-shadow: 0 0 10px rgba(41, 121, 255, 0.4);}
  .admin-card span{display:block;font-weight:600;font-size:18px}
  @media(max-width:768px){.admin-card{width:100%;min-height:120px;padding:16px}}
  .panel-legend{max-width:1100px;margin:10px auto 22px;color:#9ca3af;text-align:center}
  
  .btn-secondary {
      background: #111;
      border: 1px solid #333;
      color: #e5e7eb;
  }
  .btn-secondary:hover {
      background: #333;
      border-color: #555;
  }
  </style>
</head>
<body>

<!-- Header (Xolai Style) -->
<header class="main-header">
  <div class="header-left">
    <img src="images/Xolai.png" alt="Xolai Logo" class="header-logo-img">
    <span class="header-title">Xolai</span>
  </div>
  <nav class="header-center">
    <a href="home.php" class="nav-link">Inicio</a>
    <a href="index.php" class="nav-link">Agenda</a>
    <a href="catalogo_pacientes.php" class="nav-link">Pacientes</a>
      <?php if (puedeRealizar('acceder_reportes')): ?>
        <a href="pagos.php" class="nav-link">Pagos</a>
      <?php endif; ?>
    <a href="panel_admin.php" class="nav-link active">Administración</a>
  </nav>
  <div class="header-right">
    <div class="user-info">
      <span><?php echo htmlspecialchars($user_nombre ?? 'Usuario'); ?></span>
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
            <a href="admin_origenes_recomendacion.php"><i class="fas fa-bullhorn"></i> Orígenes Recomendación</a>
            <?php if ($puede_gestionar_usuarios): ?>
                <a href="admin_usuarios.php"><i class="fas fa-users"></i> Gestionar Equipo</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="header-buttons">
      <a href="logout.php" class="btn-header"><i class="fas fa-sign-out-alt"></i> Salir</a>
    </div>
  </div>
</header>

<main>
  <div class="text-center mb-4">
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-calendar-alt"></i> Volver al Calendario</a>
  </div>
  <div class="panel-legend">Panel de administración — elija una sección para gestionar</div>
  <div class="admin-grid">
    <a href="catalogo_servicios.php" class="admin-card" title="Catálogo de servicios">
      <i class="fa-solid fa-list"></i>
      <span>Catálogo</span>
      <small style="color:#6b7280;margin-top:8px">Gestionar tratamientos</small>
    </a>

    <?php if (puedeRealizar('ver_catalogo_pacientes')): ?>
    <a href="catalogo_pacientes.php" class="admin-card" title="Catálogo de pacientes">
      <i class="fa-solid fa-hospital-user"></i>
      <span>Pacientes</span>
      <small style="color:#6b7280;margin-top:8px">Gestionar la base de datos de pacientes</small>
    </a>
    <?php endif; ?>

    <?php if (puedeRealizar('ver_catalogo_citas')): ?>
    <a href="catalogo_citas.php" class="admin-card" title="Catálogo de Citas">
      <i class="fa-solid fa-calendar-check"></i>
      <span>Citas</span>
      <small style="color:#6b7280;margin-top:8px">Ver y buscar todas las citas agendadas</small>
    </a>
    <?php endif; ?>

    <a href="admin_modalidades.php" class="admin-card" title="Modalidades">
      <i class="fa-solid fa-layer-group"></i>
      <span>Consultorios</span>
      <small style="color:#6b7280;margin-top:8px">Gestionar consultorios / recursos</small>
    </a>

    <?php if ($es_dueno_principal || $es_admin || $user_tipo === 'recepcion' || $es_super): ?>
    <a href="admin_usuarios.php" class="admin-card" title="Usuarios">
      <i class="fa-solid fa-users"></i>
      <span>Usuarios</span>
      <small style="color:#6b7280;margin-top:8px">Crear, editar y asignar permisos</small>
    </a>
    <?php endif; ?>

    <?php if (puedeRealizar('gestionar_especialidades') || $es_admin || $es_dentista_principal): ?>
        <a href="admin_origenes_recomendacion.php" class="admin-card" title="Orígenes de Recomendación">
          <i class="fa-solid fa-bullhorn"></i>
          <span>Recomendaciones</span>
          <small style="color:#6b7280;margin-top:8px">Gestionar fuentes de pacientes</small>
        </a>

        <a href="admin_tipos_paciente.php" class="admin-card" title="Tipos de Paciente">
          <i class="fa-solid fa-users-gear"></i>
          <span>Tipos de Paciente</span>
          <small style="color:#6b7280;margin-top:8px">Gestionar origen de paciente</small>
        </a>
        <a href="admin_especialidades.php" class="admin-card" title="Administrar Especialidades">
          <i class="fa-solid fa-tags"></i>
          <span>Especialidades</span>
          <small style="color:#6b7280;margin-top:8px">Gestionar especialidades de tratamientos</small>
        </a>

    <?php endif; ?>

    <?php if ($es_super || $es_admin || $es_dentista_principal): ?>
        <a href="paquetes_admin.php" class="admin-card" title="Catálogo de Paquetes">
          <i class="fa-solid fa-box-open"></i>
          <span>Paquetes</span>
          <small style="color:#6b7280;margin-top:8px">Crear y administrar paquetes de tratamientos</small>
        </a>

        <a href="admin_horarios.php" class="admin-card" title="Configurar Horarios">
          <i class="fa-solid fa-clock"></i>
          <span>Horarios</span>
          <small style="color:#6b7280;margin-top:8px">Definir intervalos y bloqueos de la agenda</small>
        </a>
    <?php endif; ?>

    <?php if ($puede_ver_reporte_dental): ?>
    <a href="reportes_dent.php" class="admin-card" title="Reportes Dentales">
      <i class="fa-solid fa-file-invoice-dollar"></i>
      <span>Reporte Dental</span>
      <small style="color:#6b7280;margin-top:8px">Balances y liquidaciones médicas</small>
    </a>
    <?php endif; ?>
  </div>
</main>

<script>
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
</script>
</body>
</html>
