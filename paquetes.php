<?php
require_once 'includes/db.php';

try {
    $conn = obtenerConexion();
    
    // 1. Obtener todos los paquetes
    $sql_paquetes = "SELECT id, nombre, descripcion, precio FROM agenda_paquetes ORDER BY precio";
    $stmt_paquetes = $conn->prepare($sql_paquetes);
    $stmt_paquetes->execute();
    $paquetes = $stmt_paquetes->fetchAll(PDO::FETCH_ASSOC);

    // 2. Para cada paquete, obtener sus servicios asociados
    $sql_servicios = "SELECT s.id, s.nombre
                      FROM agenda_paquete_servicios aps
                      JOIN portal_servicios s ON aps.servicio_id = s.id
                      WHERE aps.paquete_id = :paquete_id";
    $stmt_servicios = $conn->prepare($sql_servicios);

    foreach ($paquetes as $key => $paquete) {
        $stmt_servicios->execute([':paquete_id' => $paquete['id']]);
        $servicios = $stmt_servicios->fetchAll(PDO::FETCH_ASSOC);
        $paquetes[$key]['servicios'] = $servicios ?: [];
    }

} catch (PDOException $e) {
    // Manejo de error básico
    die("Error al conectar con la base de datos: " . $e->getMessage());
}

function isFeatured($packageName) {
    // Lógica para determinar si un paquete es "featured" (Más Popular)
    // Se puede basar en el nombre, un campo en la BD, o cualquier otra lógica.
    // Aquí, lo basamos en si el nombre contiene "Completo".
    return strpos(strtolower($packageName), 'completo') !== false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paquetes - Hospital Angeles</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="cliente.php">
                <img src="images/logo.png" alt="Hospital Angeles" height="50" class="me-3">
                <div>
                    <div style="font-size: 1.2rem; font-weight: 700;">Hospital Angeles</div>
                    <div style="font-size: 0.8rem; color: #6b7280; font-weight: 400;">Imagenología</div>
                </div>
            </a>
            
            <a href="cliente.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Regresar
            </a>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1>Paquetes Especiales</h1>
                    <p>Ahorra con nuestros paquetes integrales diseñados para tu bienestar</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div style="font-size: 4rem; opacity: 0.7;">
                        <i class="fas fa-gift"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Packages Section -->
    <div class="container mb-5">
        <div class="row">
            <?php if (empty($paquetes)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center" role="alert">
                        <h4 class="alert-heading">No hay paquetes disponibles</h4>
                        <p>Por el momento no tenemos paquetes especiales. Por favor, vuelva a intentarlo más tarde.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($paquetes as $index => $paquete): ?>
                    <?php 
                        $featured = isFeatured($paquete['nombre']);
                        $cardClass = $featured ? 'package-card featured' : 'package-card';
                        $animationDelay = $index * 0.2;
                        
                        $params = http_build_query([
                            'tipo' => 'paquete',
                            'paquete_id' => $paquete['id'],
                            'paquete_nombre' => $paquete['nombre'],
                            'paquete_precio' => $paquete['precio'],
                            'paquete_servicios' => json_encode(array_column($paquete['servicios'], 'nombre'))
                        ]);
                        $reservarUrl = "reservar.php?" . $params;
                    ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="<?php echo $cardClass; ?> fade-in" style="animation-delay: <?php echo $animationDelay; ?>s">
                            <?php if ($featured): ?>
                                <div class="popular-badge">Más Popular</div>
                            <?php endif; ?>
                            
                            <div class="package-header">
                                <div class="package-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="package-title"><?php echo htmlspecialchars($paquete['nombre']); ?></div>
                                <div class="package-subtitle"><?php echo htmlspecialchars($paquete['descripcion'] ?? 'Evaluación Integral'); ?></div>
                            </div>
                            
                            <div class="package-price">$<?php echo number_format($paquete['precio'], 2); ?></div>
                            
                            <ul class="package-features">
                                <?php if (empty($paquete['servicios'])): ?>
                                    <li><i class="fas fa-times text-danger"></i> No hay servicios especificados.</li>
                                <?php else: ?>
                                    <?php foreach($paquete['servicios'] as $servicio): ?>
                                        <li><i class="fas fa-check"></i><?php echo htmlspecialchars($servicio['nombre']); ?></li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                            
                            <a href="<?php echo $reservarUrl; ?>" class="book-btn text-center text-decoration-none">
                                <i class="fas fa-calendar-plus me-2"></i>
                                Reservar Paquete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Info Section -->
        <div class="row mt-5">
            <div class="col-lg-6 mb-4">
                <div class="info-section fade-in" style="animation-delay: 0.6s">
                    <h3><i class="fas fa-info-circle me-2 text-primary"></i>¿Por qué elegir un paquete?</h3>
                    <ul class="info-list">
                        <li>
                            <i class="fas fa-dollar-sign"></i>
                            <span><strong>Ahorro significativo:</strong> Hasta 30% menos que estudios individuales</span>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span><strong>Conveniencia:</strong> Todos los estudios en una sola visita</span>
                        </li>
                        <li>
                            <i class="fas fa-user-md"></i>
                            <span><strong>Atención integral:</strong> Evaluación completa de tu estado de salud</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="info-section fade-in" style="animation-delay: 0.8s">
                    <h3><i class="fas fa-calendar-check me-2 text-success"></i>Proceso de reserva</h3>
                    <ul class="info-list">
                        <li>
                            <i class="fas fa-mouse-pointer"></i>
                            <span><strong>1. Selecciona:</strong> Elige el paquete que mejor se adapte a tus necesidades</span>
                        </li>
                        <li>
                            <i class="fas fa-calendar-alt"></i>
                            <span><strong>2. Agenda:</strong> Programa tu cita en el horario que prefieras</span>
                        </li>
                        <li>
                            <i class="fas fa-file-medical"></i>
                            <span><strong>3. Prepárate:</strong> Recibe las indicaciones específicas para tus estudios</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span><strong>4. Asiste:</strong> Acude a tu cita y completa todos los estudios</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Página de paquetes dinámica cargada');
        });
    </script>
</body>
</html>
