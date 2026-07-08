<?php
session_start();
require_once 'includes/db.php';

$modalidad_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$modalidad_nombre = isset($_GET['nombre']) ? htmlspecialchars($_GET['nombre']) : 'Servicios';
$portal_usuario_id = isset($_GET['portal_usuario_id']) ? (int)$_GET['portal_usuario_id'] : null;

if ($modalidad_id === 0) {
    die("Error: Modalidad no especificada.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Servicios de <?php echo $modalidad_nombre; ?> - Hospital Angeles</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #ffffff;
            --secondary-color: #a0a0a0;
            --accent-color: #2979ff;
            --gradient-bg: #000000;
            --light-bg: #000000;
            --card-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--light-bg);
            padding-top: 100px;
        }
        .navbar {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.5);
            padding: 1rem 0;
            border-bottom: 1px solid rgba(41, 121, 255, 0.1);
        }
        .navbar-brand {
            font-weight: 700;
            color: white !important;
            text-decoration: none;
        }
        .logo-text {
            font-size: 1.2rem;
            font-weight: 600;
            margin-left: 10px;
            color: white;
        }
        .back-btn {
            background: rgba(41, 121, 255, 0.1);
            color: white;
            border: 1px solid rgba(41, 121, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .back-btn:hover { background: rgba(41, 121, 255, 0.2); color: white; box-shadow: 0 0 10px rgba(41, 121, 255, 0.2); }
        .page-header { text-align: center; margin-bottom: 3rem; }
        .page-header h1 { font-size: 2.5rem; font-weight: 700; color: var(--primary-color); }
        .page-header p { color: #9ca3af; }
        .service-card {
            background: #0a0a0a;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .service-card:hover { transform: translateY(-5px); border-color: var(--accent-color); box-shadow: 0 0 20px rgba(41, 121, 255, 0.2); }
        .service-card-header { display: flex; align-items: center; margin-bottom: 1rem; }
        .service-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        .service-card h3 { font-size: 1.3rem; font-weight: 600; color: var(--primary-color); margin: 0; }
        .service-card p { color: #9ca3af; margin-bottom: 1.5rem; font-size: 0.95rem; }
        .service-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            margin-bottom: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #333;
        }
        .service-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent-color);
        }
        .service-duration { font-size: 0.9rem; color: #9ca3af; font-weight: 500; }
        .btn-reservar {
            background: var(--accent-color);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.3);
        }
        .btn-reservar:hover { background: #2962ff; box-shadow: 0 0 20px rgba(41, 121, 255, 0.6); }
        .fade-in { animation: fadeInUp 0.6s ease-out; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="cliente.php<?php echo $portal_usuario_id ? '?portal_usuario_id=' . $portal_usuario_id : ''; ?>">
                <img src="https://angelescuauhtemoc.com/wp-content/uploads/2020/09/logo-50-300x187.png" alt="Hospital Angeles" height="60">
                <div class="logo-text">IMAGENOLOGÍA</div>
            </a>
            <a href="cliente.php<?php echo $portal_usuario_id ? '?portal_usuario_id=' . $portal_usuario_id : ''; ?>" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i>Regresar
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container" style="margin-top: 3rem; margin-bottom: 3rem;">
        <div class="page-header fade-in">
            <h1><?php echo $modalidad_nombre; ?></h1>
            <p>Selecciona el estudio que necesitas para continuar con tu reservación.</p>
        </div>

        <div class="row" id="servicios-grid">
            <!-- Los servicios se cargarán aquí -->
            <div class="col-12 text-center">
                <p>Cargando servicios...</p>
            </div>
        </div>
    </div>

    <script>
        // Misma lógica de íconos y colores que en cliente.php
        const modalidadColors = {
            'radiograf': 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
            'tomografia': 'linear-gradient(135deg, #f59e0b, #d97706)',
            'mastografia': 'linear-gradient(135deg, #ec4899, #db2777)',
            'sonografia': 'linear-gradient(135deg, #06b6d4, #0891b2)',
            'laboratorio': 'linear-gradient(135deg, #6b7280, #4b5563)',
            'default': 'linear-gradient(135deg, #6b7280, #4b5563)'
        };

        const modalidadIcons = {
            'radiograf': 'fas fa-diagnoses',
            'resonancia': 'fas fa-brain',
            'tomograf': 'fas fa-lungs-virus',
            'mastograf': 'fas fa-venus',
            'sonograf': 'fas fa-wave-square',
            'laboratorio': 'fas fa-flask',
            'default': 'fas fa-stethoscope'
        };

        function getModalidadColor(nombre) {
            const nombreLower = nombre.toLowerCase();
            for (const [key, color] of Object.entries(modalidadColors)) {
                if (nombreLower.includes(key)) return color;
            }
            return modalidadColors.default;
        }

        function getModalidadIcon(nombre) {
            const nombreLower = nombre.toLowerCase();
            if (nombreLower.includes('ultrasonido')) return modalidadIcons['sonograf'];
            if (nombreLower.includes('radiolog')) return modalidadIcons['radiograf'];
            for (const [key, icon] of Object.entries(modalidadIcons)) {
                if (nombreLower.includes(key)) return icon;
            }
            return modalidadIcons.default;
        }

        async function cargarServicios() {
            const modalidadId = <?php echo $modalidad_id; ?>;
            const modalidadNombre = "<?php echo $modalidad_nombre; ?>";
            const portalUsuarioId = <?php echo $portal_usuario_id ?? 'null'; ?>;

            const grid = document.getElementById('servicios-grid');
            grid.innerHTML = '';

            try {
                const response = await fetch(`citas/servicios_por_modalidad.php?modalidad_id=${modalidadId}`);
                const servicios = await response.json();

                if (servicios.length === 0) {
                    grid.innerHTML = '<div class="col-12 text-center"><p>No hay servicios disponibles para esta modalidad.</p></div>';
                    return;
                }

                const iconClass = getModalidadIcon(modalidadNombre);
                const color = getModalidadColor(modalidadNombre);

                servicios.forEach(servicio => {
                    const card = document.createElement('div');
                    card.className = 'col-lg-4 col-md-6 mb-4';
                    
                    let reservarUrl = `reservar.php?tipo=servicio&servicio_id=${servicio.id}&servicio_nombre=${encodeURIComponent(servicio.nombre)}&modalidad_id=${modalidadId}&modalidad_nombre=${encodeURIComponent(modalidadNombre)}`;
                    if (portalUsuarioId) {
                        reservarUrl += `&portal_usuario_id=${portalUsuarioId}`;
                    }

                    const precioHtml = servicio.precio ? `<div class="service-price">$${parseFloat(servicio.precio).toLocaleString('es-MX', { minimumFractionDigits: 2 })}</div>` : '';
                    const duracionHtml = servicio.duracion_minutos ? `<div class="service-duration"><i class="fas fa-clock me-2"></i>${servicio.duracion_minutos} min</div>` : '';

                    card.innerHTML = `
                        <div class="service-card fade-in">
                            <div>
                                <div class="service-card-header">
                                    <div class="service-icon" style="background: ${color};">
                                        <i class="${iconClass}"></i>
                                    </div>
                                    <h3>${servicio.nombre}</h3>
                                </div>
                                <p>${servicio.descripcion || 'Estudio especializado para un diagnóstico preciso.'}</p>
                                <div class="service-meta">
                                    ${precioHtml}
                                    ${duracionHtml}
                                </div>
                            </div>
                            <div class="text-center">
                                <a href="${reservarUrl}" class="btn-reservar">
                                    <i class="fas fa-calendar-alt me-2"></i>Reservar
                                </a>
                            </div>
                        </div>
                    `;
                    grid.appendChild(card);
                });

            } catch (error) {
                console.error('Error cargando servicios:', error);
                grid.innerHTML = '<div class="col-12 text-center"><p class="text-danger">Error al cargar los servicios.</p></div>';
            }
        }

        document.addEventListener('DOMContentLoaded', cargarServicios);
    </script>
</body>
</html>