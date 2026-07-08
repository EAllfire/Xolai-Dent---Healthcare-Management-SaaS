<?php
session_start();
require_once 'includes/db.php';

// Si el usuario ya está logueado, redirigir al panel principal.
if (isset($_SESSION['usuario_id'])) {
    header('Location: home.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($nombre_usuario && $password) {
        // Buscar usuario por nombre de usuario
        $stmt = $conn->prepare("SELECT id, nombre, nombre_usuario, password, tipo, id_padre FROM agenda_usuarios WHERE nombre_usuario = ?");
        if ($stmt) {
            $stmt->bind_param("s", $nombre_usuario);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $nombre, $db_usuario, $password_hash, $tipo, $id_padre);
                $stmt->fetch();

                if (password_verify($password, $password_hash)) {
                    // Login exitoso
                    $_SESSION['usuario_id'] = $id;
                    $_SESSION['usuario_nombre'] = $nombre;
                    $_SESSION['usuario_tipo'] = $tipo;
                    $_SESSION['usuario_login'] = $db_usuario;
                    $_SESSION['id_padre'] = $id_padre; // Guardamos el vínculo
                    
                    header('Location: home.php');
                    exit;
                } else {
                    $error = 'Contraseña incorrecta.';
                }
            } else {
                $error = 'Usuario no encontrado.';
            }
            $stmt->close();
        } else {
            $error = 'Error de conexión con la base de datos.';
        }
    } else {
        $error = 'Por favor ingrese usuario y contraseña.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Xolai</title>
    <link rel="icon" type="image/png" href="images/logo2.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            height: 100%;
            font-family: 'Inter', sans-serif;
            background-color: #000;
            overflow: hidden;
            color: #e5e7eb;
        }

        .login-container {
            display: flex;
            height: 100vh;
            width: 100vw;
        }

        /* Columna Izquierda - Slider de Imágenes */
        .left-column {
            width: 40%; /* Columna de imagen más estrecha */
            overflow: hidden; /* Oculta lo que se salga de la columna */
            background-color: #000000; /* Fondo negro como solicitado */
            position: relative;
        }

        .image-slider {
            position: relative;
            width: 100%;
            height: 100%;
        }

        /* Columna Derecha - Formulario de Login */
        .right-column {
            width: 60%; /* Columna de login más ancha */
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0; /* Quitamos el padding para que el login-box ocupe todo */
            position: relative; /* Para posicionar el logo */
        }

        .top-left-logo {
            position: absolute;
            top: 140px;
            left: 75px;
            width: 180px;
            height: auto;
            opacity: 0.8;
        }

        .login-box {
            width: 100%;
            height: 100%;
            background: #0e0e0e; /* Aumentar el padding izquierdo para más espacio */
            padding: 2rem 2rem 2rem 100px;
            display: flex;
            flex-direction: column;
            justify-content: center; /* Centra el contenido verticalmente */
            align-items: flex-start; /* Alinea los elementos a la izquierda */
            text-align: left; /* Alinea el texto a la izquierda */
            /* Quitamos los estilos de "caja" para que llene la columna */
            border-radius: 0;
            box-shadow: none;
            border: none;
            overflow-y: auto; /* Para pantallas pequeñas */
        }
        .login-box h2 {
            color: #e5e7eb;
            font-size: 32px; /* Fuente más grande */
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-box p {
            color: #6b7280;
            font-size: 15px; /* Fuente más grande */
            margin-bottom: 28px;
        }

        /* Contenedor para el formulario para mantener un ancho legible */
        .login-box > h2,
        .login-box > p,
        .login-box > .error-message,
        .login-box > form {
            width: 100%;
            max-width: 480px; /* Ocupa más espacio, más largos */
        }

        .input-group {
            position: relative;
            margin-bottom: 28px; /* Espacio más consistente */
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .input-group input {
            width: 100%;
            background: #000;
            color: #e5e7eb;
            padding: 14px 18px 14px 48px; /* Espacios más delgados */
            border: 1px solid #333;
            border-radius: 8px;
            font-size: 17px; /* Fuente más grande */
            transition: border-color 0.2s, box-shadow 0.2s;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .input-group input:focus {
            outline: none;
            border-color: #2979ff;
            box-shadow: 0 0 0 3px rgba(41, 121, 255, 0.2);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            color: #e5e7eb;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            background: rgba(41, 121, 255, 0.1);
            border: 1px solid rgba(41, 121, 255, 0.2);
            border-radius: 8px;
            font-size: 18px; /* Fuente más grande */
            cursor: pointer;
            text-shadow: 0 0 5px rgba(41, 121, 255, 0.3);
        }

        .btn-login:hover {
            background: rgba(41, 121, 255, 0.2);
            color: #fff;
            border-color: rgba(41, 121, 255, 0.5);
            box-shadow: 0 0 10px rgba(41, 121, 255, 0.2);
            transform: translateY(-2px);
        }

        .error-message {
            background-color: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: left;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .left-column {
                display: none; /* Ocultar la columna de imagen en móviles */
            }
            .right-column {
                width: 100%;
            }
            .login-box {
                padding: 30px;
            }
        }

        /* --- Slider --- */
        .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transform: translateX(100%); /* Inicia fuera de la pantalla a la derecha */
            transition: transform 0.9s cubic-bezier(0.65, 0, 0.35, 1), opacity 0.7s ease-in-out;
            will-change: transform, opacity;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
        }

        .slide.active {
            transform: translateX(0); /* Se mueve al centro */
            opacity: 1;
            z-index: 10;
        }

        .slide.exiting {
            transform: translateX(-100%); /* Se mueve fuera hacia la izquierda */
            z-index: 9;
        }

        .slide img {
            width: 100%;
            max-height: 55vh; /* Imagen más pequeña */
            object-fit: contain; /* No recorta la imagen */
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            margin-bottom: 2rem;
        }

        .info-box {
            position: static; /* Ya no es absoluto */
            background: none; /* Sin fondo degradado */
            color: white; /* Justificar el texto de las descripciones */
            text-align: justify;
            max-width: 85%;
        }

        .info-box h3 {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #e5e7eb;
            text-shadow: 0 1px 3px rgba(0,0,0,0.5);
        }

        .info-box p {
            font-size: 15px;
            line-height: 1.6;
            color: #9ca3af;
            margin-bottom: 0;
        }

        /* Manual slide controls */
        .slide-control {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 50%;
            z-index: 30;
        }
        .slide-control.prev {
            left: 0;
        }
        .slide-control.next {
            right: 0;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <!-- Columna Izquierda con Slider -->
        <div class="left-column">
            <div class="image-slider">
                <div class="slide active">
                    <img src="images/uno.png" alt="Imagen de fondo 1">
                    <div class="info-box">
                        <h3>Agendamiento de citas con Xolai Management</h3>
                        <p>Obtén una visión completa del calendario de citas de todas tus salas y sucursales, aprovecha las predicciones de los mejores horarios para agendar a tus pacientes y reagenda citas con un solo clic. Gestiona y visualiza de manera eficiente tus salas activas e inactivas para optimizar tus procesos de agendamiento.</p>
                    </div>
                </div>
                <div class="slide">
                    <img src="images/dos.png" alt="Imagen de fondo 2">
                    <div class="info-box">
                        <h3>Gestión Inteligente de Pacientes</h3>
                        <p>Centraliza la información de tus pacientes, accede a historiales completos y mejora la comunicación. Nuestro sistema te permite buscar, registrar y actualizar datos de pacientes de forma rápida y segura, optimizando el flujo de trabajo y la atención al cliente.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha con Formulario -->
        <div class="right-column">
            <img src="images/logo2.png" alt="Xolai Logo" class="top-left-logo" />
            <div class="login-box">
                <h2>Bienvenido de Nuevo</h2>
                <p>Ingresa tus credenciales para acceder al sistema de citas.</p>

                <?php
                if (isset($_GET['error'])) {
                    echo '<div class="error-message"><i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>' . htmlspecialchars($_GET['error']) . '</div>';
                }
                if ($error) {
                    echo '<div class="error-message"><i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>' . htmlspecialchars($error) . '</div>';
                }
                ?>

                <form method="POST">
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="nombre_usuario" placeholder="Nombre de usuario" required>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Contraseña" required>
                    </div>
                    <button type="submit" class="btn-login">Ingresar</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const slides = document.querySelectorAll('.image-slider .slide');
            const leftColumn = document.querySelector('.left-column');
            let currentIndex = 0;
            let slideInterval;
            let isTransitioning = false;

            function showSlide(nextIndex, direction = 'next') {
                if (isTransitioning || nextIndex === currentIndex) return;
                isTransitioning = true;

                const currentSlide = slides[currentIndex];
                const nextSlide = slides[nextIndex];

                // Animate slides
                nextSlide.classList.add('active');
                currentSlide.classList.add('exiting');
                currentSlide.classList.remove('active');

                // After transition, clean up classes
                setTimeout(() => {
                    currentSlide.classList.remove('exiting');
                    isTransitioning = false;
                }, 900); // Match CSS transition duration

                currentIndex = nextIndex;
            }

            function next() {
                const nextIndex = (currentIndex + 1) % slides.length;
                showSlide(nextIndex, 'next');
            }

            function prev() {
                const prevIndex = (currentIndex - 1 + slides.length) % slides.length;
                showSlide(prevIndex, 'prev');
            }

            function startSlider() {
                slideInterval = setInterval(next, 7000); // Change every 7 seconds
            }

            leftColumn.addEventListener('click', (e) => {
                clearInterval(slideInterval); // Stop auto-slide on interaction
                if (e.offsetX > leftColumn.offsetWidth / 2) {
                    next();
                } else {
                    prev();
                }
                slideInterval = setInterval(next, 7000); // Restart auto-slide
            });

            startSlider();
        });
    </script>

</body>
</html>