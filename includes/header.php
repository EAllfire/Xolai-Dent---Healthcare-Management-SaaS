<?php
// Reusable header include.
// Variables you can set before including:
// $show_calendar (bool) - show calendar button (default true)
// $show_back (bool) - show back button (default false)
// $show_admin_tools (bool) - show admin links (default based on $puede_gestionar_usuarios)

$show_calendar = $show_calendar ?? true;
$show_back = $show_back ?? false;
$show_admin_tools = $show_admin_tools ?? ($puede_gestionar_usuarios ?? false);
// Allow pages to hide the mobile menu button when the header would overlap content
$show_mobile_menu = $show_mobile_menu ?? true;
?>
<!-- Header -->
<header class="main-header">
    <div class="header-left">
        <?php if ($show_mobile_menu): ?>
        <button class="mobile-menu-btn" onclick="if(window.toggleSidebar) toggleSidebar();">
            <i class="fas fa-bars"></i>
        </button>
        <?php endif; ?>

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
                <a href="admin_usuarios.php" class="btn-header"><i class="fas fa-users-cog"></i> Admin</a>
                <a href="catalogo_servicios.php" class="btn-header"><i class="fas fa-list"></i> Catálogo</a>
                <a href="admin_modalidades.php" class="btn-header"><i class="fas fa-layer-group"></i> Modalidades</a>
            <?php endif; ?>

            <a href="cliente.php" class="btn-header"><i class="fas fa-user-friends"></i> Vista Cliente</a>
            <a href="reporte.php" class="btn-header"><i class="fas fa-file-alt"></i> Reporte</a>

            <?php if (!empty($puede_crear_citas)): ?>
                <button onclick="if(window.abrirModalAgendar) abrirModalAgendar();" class="btn-header"><i class="fas fa-plus"></i> Nueva Cita</button>
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