<?php include("../includes/db.php"); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agenda Hospital Ángeles</title>

  <!-- FullCalendar con timeline -->
  <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
  <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>

  <!-- FontAwesome para iconos -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      background: #f8f9fa;
    }
    #calendar {
      max-width: 95%;
      margin: 20px auto;
    }

    /* Estilos del Header y Dropdown */
    .header-bar {
      background-color: #ffffff;
      padding: 15px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
      margin-bottom: 20px;
    }
    .header-title {
      margin: 0;
      font-size: 1.8rem;
      color: #333;
    }
    .settings-container {
      position: relative;
      display: inline-block;
    }
    .settings-btn {
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1.5rem;
      color: #555;
      padding: 8px;
      transition: transform 0.3s ease;
    }
    .settings-btn:hover {
      color: #000;
      transform: rotate(90deg);
    }
    .dropdown-menu {
      display: none;
      position: absolute;
      right: 0;
      top: 100%;
      background-color: #fff;
      min-width: 200px;
      box-shadow: 0 8px 16px rgba(0,0,0,0.15);
      border-radius: 8px;
      z-index: 1000;
      overflow: hidden;
      margin-top: 5px;
      border: 1px solid #eee;
    }
    .dropdown-menu a {
      color: #333;
      padding: 12px 20px;
      text-decoration: none;
      display: block;
      font-size: 15px;
      transition: background 0.2s;
    }
    .dropdown-menu a:hover {
      background-color: #f8f9fa;
      color: #0056b3;
    }
    .dropdown-menu a i {
      margin-right: 10px;
      width: 20px;
      text-align: center;
    }
    .show {
      display: block;
    }
  </style>
</head>
<body>
  <div class="header-bar">
    <h2 class="header-title">Agenda Imagenología</h2>
    <div class="settings-container">
      <button onclick="toggleDropdown()" class="settings-btn" title="Configuración">
        <i class="fa-solid fa-gear"></i>
      </button>
      <div id="ajustesDropdown" class="dropdown-menu">
        <a href="../catalogo_servicios.php"><i class="fa-solid fa-stethoscope"></i> Servicios</a>
        <a href="../modalidades.php"><i class="fa-solid fa-layer-group"></i> Modalidades</a>
      </div>
    </div>
  </div>

  <div id="calendar"></div>

  <script>
  function toggleDropdown() {
    document.getElementById("ajustesDropdown").classList.toggle("show");
  }

  // Cerrar el dropdown si se hace click fuera de él
  window.onclick = function(event) {
    if (!event.target.matches('.settings-btn') && !event.target.closest('.settings-btn')) {
      var dropdowns = document.getElementsByClassName("dropdown-menu");
      for (var i = 0; i < dropdowns.length; i++) {
        var openDropdown = dropdowns[i];
        if (openDropdown.classList.contains('show')) {
          openDropdown.classList.remove('show');
        }
      }
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
      schedulerLicenseKey: 'GPL-My-Project-Is-Open-Source', // licencia open source
      initialView: 'resourceTimeGridDay', // vista con recursos (servicios) en columnas
      locale: 'es',
      slotMinTime: "07:00:00", // inicio del día
      slotMaxTime: "23:00:00", // fin del día
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,resourceTimeGridDay,resourceTimeGridWeek'
      },
      resources: 'recursos_json.php',  // servicios/modalidades
      events: 'citas_json.php'         // citas por día/servicio
    });
    calendar.render();
  });
  </script>
</body>
</html>
