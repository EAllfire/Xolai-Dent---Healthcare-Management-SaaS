<?php
session_start();
require_once("includes/db.php");

// Validar sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['usuario_id'];
$user_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$user_tipo = $_SESSION['usuario_tipo'] ?? 'usuario';

// Solo permitir acceso a administradores
if ($user_tipo !== 'admin') {
    header("Location: index.php");
    exit();
}

// Prepare header flags
$puede_crear_citas = in_array($user_tipo,['admin','caja']);
$puede_gestionar_usuarios = ($user_tipo === 'admin');
$show_calendar = false; // admin panel doesn't need calendar button
$show_back = true; // show back button to return
$show_admin_tools = $puede_gestionar_usuarios;

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

  /* Page-specific admin styles */
  .container-custom { max-width: 1200px; margin: 0 auto; padding: 0 15px; }
  main{padding:24px;max-width:1100px;margin:0 auto}
  .admin-grid{display:flex;gap:18px;flex-wrap:wrap;justify-content:center}
  .admin-card{background:#fff;border-radius:12px;box-shadow:0 8px 24px rgba(15,23,42,0.06);width:320px;min-height:160px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:20px;color:#0f172a;text-decoration:none}
  .admin-card i{font-size:36px;margin-bottom:12px;color:#1275a0}
  .admin-card span{display:block;font-weight:600;font-size:18px}
  @media(max-width:768px){.admin-card{width:100%;min-height:120px;padding:16px}}
  .panel-legend{max-width:1100px;margin:10px auto 22px;color:#6b7280;text-align:center}
  </style>
</head>
<body>

<!-- Header (from index.php design) -->
<header class="main-header">
  <div class="header-left">
    <div class="header-logo">
      <img src="https://angelescuauhtemoc.com/wp-content/uploads/2020/09/logo-50-300x187.png" alt="Hospital Angeles">
    </div>
    <div class="user-info">
      <i class="fas fa-user-circle"></i>
      <span><?php echo htmlspecialchars($user_nombre ?? 'Usuario'); ?></span>
      <span class="user-type">(<?php echo ucfirst($user_tipo ?? 'usuario'); ?>)</span>
    </div>
  </div>
  <div class="logo-section">
    <div class="logo-text">IMAGENOLOGÍA</div>
  </div>
  <div class="header-right">
    <div class="header-buttons">
      <?php if ($show_admin_tools): ?>
   
      <?php endif; ?>

      <a href="reporte.php" class="btn-header"><i class="fas fa-file-alt"></i> Reporte</a>

     

      <?php if ($show_calendar): ?>
        <a href="index.php" class="btn-header"><i class="fas fa-calendar"></i> Calendario</a>
      <?php endif; ?>

      <?php if ($show_back): ?>
        <button onclick="history.back();" class="btn-header"><i class="fas fa-arrow-left"></i> Volver</button>
      <?php endif; ?>

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
      <small style="color:#6b7280;margin-top:8px">Gestionar servicios y paquetes</small>
    </a>

    <a href="catalogo_pacientes.php" class="admin-card" title="Catálogo de pacientes">
      <i class="fa-solid fa-hospital-user"></i>
      <span>Pacientes</span>
      <small style="color:#6b7280;margin-top:8px">Gestionar la base de datos de pacientes</small>
    </a>

    <a href="admin_modalidades.php" class="admin-card" title="Modalidades">
      <i class="fa-solid fa-layer-group"></i>
      <span>Modalidades</span>
      <small style="color:#6b7280;margin-top:8px">Gestionar modalidades / recursos</small>
    </a>

    <a href="admin_usuarios.php" class="admin-card" title="Usuarios">
      <i class="fa-solid fa-users"></i>
      <span>Usuarios</span>
      <small style="color:#6b7280;margin-top:8px">Crear, editar y asignar permisos</small>
    </a>

    <a href="admin_tipos_paciente.php" class="admin-card" title="Tipos de Paciente">
      <i class="fa-solid fa-users-gear"></i>
      <span>Tipos de Paciente</span>
      <small style="color:#6b7280;margin-top:8px">Gestionar los tipos de paciente</small>
    </a>
  </div>
</main>

</body>
</html>


