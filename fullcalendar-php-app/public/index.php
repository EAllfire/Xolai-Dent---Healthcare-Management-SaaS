<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agenda FullCalendar</title>
  <!-- CSS locales -->
  <link href="assets/css/core.css" rel="stylesheet">
  <link href="assets/css/timegrid.css" rel="stylesheet">
  <link href="assets/css/resource-timegrid.css" rel="stylesheet">
  <!-- JS desde CDN (si falla, avísame y te los paso locales) -->
  <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.19/index.global.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/timegrid@6.1.19/index.global.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/resource-timegrid@6.1.19/index.global.min.js"></script>
  <style>
    html, body {
      height: 100%;
      margin: 0;
      padding: 0;
      background: #f5f5f5;
      width: 100vw;
      overflow: hidden;
    }
    #main-container {
      display: flex;
      height: 100vh;
      width: 100vw;
      margin: 0;
      padding: 0;
    }
    #sidebar {
      width: 420px;
      min-width: 320px;
      height: 100vh;
      margin: 0;
      box-sizing: border-box;
      background: #fff;
      overflow-y: auto;
    }
    #calendar {
      flex: 1;
      min-width: 0;
      height: 100vh;
      margin: 0;
      max-width: 100vw;
      max-height: 100vh;
    }
    .fc-col-header-cell, .fc-resource { min-width: 180px !important; }

    /* Tooltip Styles */
    .fc-custom-tooltip {
        position: absolute;
        background: #fff;
        border: 1px solid #ccc;
        box-shadow: 0 2px 8px rgba(0,0,0,0.13);
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 15px;
        pointer-events: auto;
        z-index: 99999;
        max-width: 280px;
    }

    /* Estado Puntos */
    .estado-puntos {
        display: flex;
        gap: 8px;
        margin: 8px 0;
        align-items: center;
    }
    
    .estado-punto {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        cursor: pointer;
        position: relative;
        transition: all 0.2s ease;
    }
    
    .estado-punto:hover {
        transform: scale(1.3);
        box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    }
    
    .estado-punto:active {
        transform: scale(1.1);
    }
    
    .estado-punto.clickeable {
        opacity: 0.6;
    }
    
    .estado-punto.clickeable:hover {
        opacity: 1;
    }
    
    .estado-punto.activo {
        opacity: 1 !important;
    }
    
    .estado-punto-tooltip {
        position: absolute;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
        z-index: 100001;
    }
    
    .estado-punto:hover .estado-punto-tooltip {
        opacity: 1;
    }
  </style>
</head>
<body>
  <div id="calendar"></div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var calendarEl = document.getElementById('calendar');
      var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'resourceTimeGridWeek', // vista con recursos
        locale: 'es',
        schedulerLicenseKey: 'CC-Attribution-NonCommercial-NoDerivatives',
        resources: 'recursos_json.php',  // endpoint para recursos
        events: 'citas_json.php',        // endpoint para citas
        editable: true,
        selectable: true,
        height: '100vh',
        eventClick: function(info) {
          alert('Evento: ' + info.event.title);
        },
        eventDidMount: function(info) {
          var event = info.event;
          var paciente = event.title.split(' (')[0];
          var servicio = event.title.split('(')[1]?.replace(')', '') || '';
          var horaInicio = event.start ? event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
          var horaFin = event.end ? event.end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
          var telefono = event.extendedProps.telefono || '';
          var diagnostico = event.extendedProps.diagnostico || '';
          var pago = event.extendedProps.pago || 'No especificado';
          var estadoActual = event.extendedProps.estado || '';
          var tipoPaciente = event.extendedProps.tipo_paciente || '';

          // Definir todos los estados y sus colores
          var todosLosEstados = [
            {nombre: 'reservado', color: '#2196F3', label: 'Reservado'},
            {nombre: 'confirmado', color: '#FF9800', label: 'Confirmado'},
            {nombre: 'asistió', color: '#E91E63', label: 'Asistió'},
            {nombre: 'no asistió', color: '#FF7F50', label: 'No asistió'},
            {nombre: 'pendiente', color: '#F44336', label: 'Pendiente'},
            {nombre: 'en espera', color: '#4CAF50', label: 'En espera'}
          ];

          // Crear puntos de estados
          var estadoPuntos = todosLosEstados.map(estado => {
            var esActual = estadoActual.toLowerCase() === estado.nombre;
            var claseEstado = esActual ? 'activo' : 'clickeable';
            var border = esActual ? '2px solid #000' : '1px solid #ccc';
            return `
              <div class='estado-punto ${claseEstado}'
                   data-estado='${estado.nombre}'
                   data-cita-id='${event.id}'
                   style='background-color:${estado.color}; border:${border};'>
                <div class='estado-punto-tooltip'>${estado.label}${esActual ? ' (Actual)' : ' - Click para cambiar'}</div>
              </div>
            `;
          }).join('');

          var tooltip = `
            <div style='font-family:Inter,sans-serif;max-width:280px;background:white;'>
              <div style='font-weight:bold;font-size:16px;color:#1275a0;margin-bottom:8px;'>${paciente}</div>
              <div style='margin-bottom:6px;color:#374151;font-weight:500;'>${servicio}</div>
              <div style='font-size:14px;color:#6b7280;margin-bottom:4px;'><span style='margin-right:8px;'>🕒</span>${horaInicio} - ${horaFin}</div>
              <div style='font-size:14px;color:#6b7280;margin-bottom:4px;'><span style='margin-right:8px;'>💲</span>${pago}</div>
              ${tipoPaciente ? `<div style='font-size:14px;color:#6b7280;margin-bottom:4px;'><span style='margin-right:8px;'>👤</span>${tipoPaciente}</div>` : ''}
              <div class='estado-puntos' style='margin:8px 0;'>
                <span style='font-size:12px; margin-right:8px; color:#374151; font-weight:600;'>Estados:</span>
                ${estadoPuntos}
              </div>
              <hr style='margin:8px 0; border:none; border-top:1px solid #e5e7eb;'>
              <div style='font-size:14px;color:#6b7280;margin-bottom:4px;'><span style='margin-right:8px;'>📱</span>${telefono}</div>
              <div style='font-size:14px;color:#6b7280;'><span style='margin-right:8px;'>💬</span>${diagnostico}</div>
            </div>
          `;
          info.el.setAttribute('title', '');
          info.el.addEventListener('mouseenter', function(e) {
            if (tooltipActivo) {
              return;
            }
            if (info.el._hideTimeout) {
              clearTimeout(info.el._hideTimeout);
              info.el._hideTimeout = null;
            }
            let tip = document.createElement('div');
            tip.className = 'fc-custom-tooltip';
            tip.innerHTML = tooltip;
            tip.style.cssText = `
              position: absolute;
              z-index: 99999;
              background: white;
              border: 1px solid #d1d5db;
              box-shadow: 0 10px 25px rgba(0,0,0,0.15);
              padding: 16px;
              border-radius: 12px;
              font-size: 14px;
              pointer-events: auto;
              max-width: 300px;
              font-family: Inter, sans-serif;
            `;
            tip.style.top = (e.clientY + 15) + 'px';
            tip.style.left = (e.clientX + 15) + 'px';
            tip.id = 'fc-tooltip-'+event.id;
            document.body.appendChild(tip);
            info.el._fcTooltip = tip;
            tooltipActivo = tip;

            tip.addEventListener('mouseenter', function() {
              if (info.el._hideTimeout) {
                clearTimeout(info.el._hideTimeout);
                info.el._hideTimeout = null;
              }
            });

            tip.addEventListener('mouseleave', function() {
              info.el._hideTimeout = setTimeout(function() {
                if (info.el._fcTooltip && tooltipActivo === info.el._fcTooltip) {
                  document.body.removeChild(info.el._fcTooltip);
                  info.el._fcTooltip = null;
                  tooltipActivo = null;
                }
              }, 300);
            });

            tip.addEventListener('click', function(e) {
              if (e.target.classList.contains('estado-punto') && e.target.classList.contains('clickeable')) {
                var nuevoEstado = e.target.getAttribute('data-estado');
                var citaId = e.target.getAttribute('data-cita-id');

                if (nuevoEstado && citaId) {
                  cambiarEstadoCita(citaId, nuevoEstado, event, info.el);
                }
              }
            });
          });
          info.el.addEventListener('mousemove', function(e) {
            if (info.el._fcTooltip) {
              info.el._fcTooltip.style.top = (e.clientY + 12) + 'px';
              info.el._fcTooltip.style.left = (e.clientX + 12) + 'px';
            }
          });
          info.el.addEventListener('mouseleave', function() {
            info.el._hideTimeout = setTimeout(function() {
              if (info.el._fcTooltip && tooltipActivo === info.el._fcTooltip) {
                document.body.removeChild(info.el._fcTooltip);
                info.el._fcTooltip = null;
                tooltipActivo = null;
              }
            }, 300);
          });
        }
      });
      var tooltipActivo = null; // Variable global para controlar tooltips

      // Event listener global para cerrar tooltip al hacer click fuera
      document.addEventListener('click', function(e) {
        if (tooltipActivo && !tooltipActivo.contains(e.target) && !e.target.closest('.fc-event')) {
          if (e.target.classList.contains('estado-punto') || e.target.closest('.estado-punto')) {
            return;
          }
          if (tooltipActivo.parentNode) {
            document.body.removeChild(tooltipActivo);
          }
          tooltipActivo = null;
          var eventos = document.querySelectorAll('.fc-event');
          eventos.forEach(function(evento) {
            if (evento._fcTooltip) {
              evento._fcTooltip = null;
            }
            if (evento._hideTimeout) {
              clearTimeout(evento._hideTimeout);
              evento._hideTimeout = null;
            }
          });
        }
      });

      // Función para cambiar el estado de una cita
      function cambiarEstadoCita(citaId, nuevoEstado, evento, elementoCita) {
        var tooltip = elementoCita._fcTooltip;
        if (tooltip && tooltip.parentNode) {
          var loadingDiv = tooltip.querySelector('.estado-puntos');
          if (loadingDiv) {
            loadingDiv.innerHTML = '<span style="font-size:12px;">Actualizando estado...</span>';
          }
        }
        var formData = new FormData();
        formData.append('cita_id', citaId);
        formData.append('estado', nuevoEstado);
        fetch('../../actualizar_estado.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            evento.setExtendedProp('estado', nuevoEstado);
            evento.setProp('backgroundColor', data.nuevo_color);
            evento.setProp('borderColor', data.nuevo_color);
            evento.setProp('color', data.nuevo_color);
            elementoCita.style.backgroundColor = data.nuevo_color;
            elementoCita.style.borderColor = data.nuevo_color;
            if (tooltip && tooltip.parentNode) {
              document.body.removeChild(tooltip);
              elementoCita._fcTooltip = null;
              tooltipActivo = null;
            } else if (tooltip) {
              elementoCita._fcTooltip = null;
              tooltipActivo = null;
            }
            var successMsg = document.createElement('div');
            successMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 100000; background: #4CAF50; color: white; padding: 12px 20px; border-radius: 4px; font-family: Roboto, sans-serif; box-shadow: 0 2px 8px rgba(0,0,0,0.2);';
            successMsg.textContent = 'Estado actualizado a: ' + nuevoEstado;
            document.body.appendChild(successMsg);
            setTimeout(function() {
              if (successMsg.parentNode) {
                document.body.removeChild(successMsg);
              }
            }, 3000);
          } else {
            var tooltip = elementoCita._fcTooltip;
            if (tooltip && tooltip.parentNode) {
              var loadingDiv = tooltip.querySelector('.estado-puntos');
              if (loadingDiv) {
                loadingDiv.innerHTML = '<span style="font-size:12px; color:red;">Error al actualizar</span>';
              }
            }
            alert('Error al actualizar el estado: ' + (data.error || 'Error desconocido'));
          }
        })
        .catch(function(error) {
          console.error('Error:', error);
          var tooltip = elementoCita._fcTooltip;
          if (tooltip && tooltip.parentNode) {
            var loadingDiv = tooltip.querySelector('.estado-puntos');
            if (loadingDiv) {
              loadingDiv.innerHTML = '<span style="font-size:12px; color:red;">Error al actualizar</span>';
            }
          }
          alert('Error de conexión al actualizar el estado');
        });
      }

      calendar.render();

      // Esperar a que el calendario esté renderizado antes de inicializar flatpickr
      setTimeout(function() {
        if (window.flatpickr && document.getElementById('mini-calendar-actual')) {
          flatpickr('#mini-calendar-actual', {
            locale: flatpickr.l10ns.es,
            inline: true,
            defaultDate: new Date(),
            showMonths: 1,
            onChange: function(selectedDates) {
              if (selectedDates && selectedDates[0]) {
                console.log('Mini calendario actual: fecha seleccionada', selectedDates[0]);
                calendar.changeView('resourceTimeGridDay');
                calendar.gotoDate(selectedDates[0]);
              } else {
                console.log('Mini calendario actual: sin fecha seleccionada');
              }
            }
          });
        }
        if (window.flatpickr && document.getElementById('mini-calendar-proximo')) {
          var today = new Date();
          var firstDayNext = new Date(today.getFullYear(), today.getMonth() + 1, 1);
          flatpickr('#mini-calendar-proximo', {
            locale: flatpickr.l10ns.es,
            inline: true,
            defaultDate: firstDayNext,
            showMonths: 1,
            onChange: function(selectedDates) {
              if (selectedDates && selectedDates[0]) {
                console.log('Mini calendario próximo: fecha seleccionada', selectedDates[0]);
                calendar.changeView('resourceTimeGridDay');
                calendar.gotoDate(selectedDates[0]);
              } else {
                console.log('Mini calendario próximo: sin fecha seleccionada');
              }
            }
          });
        }
      }, 300);
    });
  </script>
</body>
</html>