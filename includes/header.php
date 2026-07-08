<?php
// Reusable header include.
// Variables you can set before including:
// $show_calendar (bool) - show calendar button (default true)
// $show_back (bool) - show back button (default false)
// $show_admin_tools (bool) - show admin links (default based on $puede_gestionar_usuarios)

$show_calendar = $show_calendar ?? true;
$show_back = $show_back ?? false;
$show_admin_tools = $show_admin_tools ?? (in_array($_SESSION['usuario_tipo'] ?? '', ['admin', 'superadmin', 'dentista', 'recepcion']));
// Allow pages to hide the mobile menu button when the header would overlap content
$show_mobile_menu = $show_mobile_menu ?? true;
?>
<!-- Header -->
<header class="main-header">
    <div class="header-left">

        <div class="header-logo">
            <img src="images/Xolai.png" alt="Xolai Logo" class="header-logo-img">
            <span class="header-title" style="color:white; font-size:24px; font-weight:700; margin-left:10px;">Xolai</span>
        </div>
    </div>

    <nav class="header-center">
        <a href="home.php" class="nav-link" style="color:#a0a0a0; margin:0 10px;">Inicio</a>
        <a href="index.php" class="nav-link" style="color:#a0a0a0; margin:0 10px;">Agenda</a>
        <a href="panel_admin.php" class="nav-link" style="color:#a0a0a0; margin:0 10px;">Administración</a>
    </nav>

    <div class="header-right">
        <div class="header-buttons">
            <?php if ($show_admin_tools): ?>
                <a href="panel_admin.php" class="btn-header"><i class="fas fa-users-cog"></i> Admin</a>
            <?php endif; ?>

            <?php if (puedeRealizar('ver_catalogo_pacientes')): ?>
                <a href="catalogo_pacientes.php" class="btn-header"><i class="fas fa-hospital-user"></i> Pacientes</a>
            <?php endif; ?>

            <?php if (puedeRealizar('ver_catalogo_citas')): ?>
                <a href="catalogo_citas.php" class="btn-header"><i class="fas fa-calendar-check"></i> Citas</a>
            <?php endif; ?>

            <?php if (puedeRealizar('acceder_reportes')): ?>
                <a href="reporte.php" class="btn-header"><i class="fas fa-file-alt"></i> Reporte</a>
            <?php endif; ?>

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

<style>
/* Prevent header left/right content from expanding over centered logo */
.main-header { padding-left: 20px; padding-right: 20px; }
.main-header .header-left, .main-header .header-right { max-width: calc(50% - 160px); overflow: hidden; align-items: center; }
.main-header .header-left { display:flex; }
.main-header .header-right { display:flex; justify-content:flex-end; }
.logo-section { z-index: 1060; pointer-events: none; }
.btn-header { white-space: nowrap; font-size:13px; color:white; text-decoration:none; padding:6px 10px; }
.main-header { height:80px; }
.logo-text { font-size:20px; }
</style>

<?php
// end include
?>
<?php
// Función para generar el header consistente
function generarHeader($titulo = "IMAGENOLOGÍA", $subtitulo = "", $mostrarUsuario = true) {
    $usuario = obtenerUsuarioActual();
    ?>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="logo-section">
                <img src="images/logo.png" alt="Hospital Angeles">
                <div>
                    <div class="logo-text">HOSPITAL ÁNGELES</div>
                    <small style="color: #6c757d;"><?= $titulo ?><?= $subtitulo ? " - " . $subtitulo : "" ?></small>
                </div>
            </div>
            <?php if ($mostrarUsuario): ?>
            <div class="user-info">
                <?= htmlspecialchars($usuario['nombre']) ?> 
                <?= getBadgeTipoUsuario($usuario['tipo']) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// CSS común para el header
function generarHeaderCSS() {
    ?>
    <style>
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #ffffff;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
        }
        
        .logo-section img {
            height: 50px;
            margin-right: 15px;
        }
        
        .logo-text {
            color: #1f2937;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .user-info {
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
    <?php
}
?>