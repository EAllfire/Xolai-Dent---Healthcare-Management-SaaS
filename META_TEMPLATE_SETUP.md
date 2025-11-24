# 📋 PLANTILLA WHATSAPP "citaagendada" - SETUP EN META

## Registro de Plantilla en Meta Cloud Console

### Paso 1: Acceder a Meta Business Suite

1. Ve a https://business.facebook.com/
2. Selecciona tu cuenta comercial
3. Ve a WhatsApp > Configuración de API

### Paso 2: Crear Plantilla

1. Haz clic en "Crear plantilla"
2. Nombre de la plantilla: **`citaagendada`** (EXACTAMENTE así)
3. Categoría: **APPOINTMENT_UPDATE** (Actualización de cita)
4. Idioma: **Español (México)** - es_MX

### Paso 3: Estructura de la Plantilla

```
COMPONENTE: HEADER (Opcional)
Tipo: TEXTO
Contenido: Confirmación de Cita Médica

COMPONENTE: BODY (Requerido)
Contenido:

Hola {{1}},

Tu cita de {{2}} ha sido agendada exitosamente.

📅 Fecha: {{3}}
🕐 Hora: {{4}}
📝 Indicaciones: {{5}}

Recuerda llegar 10 minutos antes de tu cita y traer tus estudios previos.

Para cambios o cancelaciones, contáctanos al +52-555-1234567.

Saludos,
Hospital Angeles - Imagenología

COMPONENTE: FOOTER (Opcional)
Contenido: Hospital Angeles | Sistema Automatizado de Citas

COMPONENTE: BUTTONS (Opcional)
Botón 1: Confirmar Asistencia (URL o PHONE_NUMBER)
Botón 2: Reprogramar Cita (URL o PHONE_NUMBER)
```

### Paso 4: Parámetros

La plantilla define estos parámetros:

```
{{1}} - STRING - Nombre del paciente (máx 160 caracteres)
{{2}} - STRING - Modalidad (máx 160 caracteres)
{{3}} - STRING - Fecha (máx 160 caracteres)
{{4}} - STRING - Hora (máx 160 caracteres)
{{5}} - STRING - Indicaciones (máx 160 caracteres)
```

### Paso 5: Guardar y Esperar Aprobación

- Haz clic en "Enviar para aprobación"
- Meta tardará 2-24 horas en revisar
- Recibirás notificación cuando esté aprobada
- Estado aparecerá como **APPROVED**

---

## 🎨 Variaciones de Plantilla

### Opción 1: SIMPLE (Recomendada)

```
Hola {{1}},

Tu cita de {{2}} está confirmada:

📅 {{3}}
🕐 {{4}}
📝 {{5}}

¡Hasta pronto!
Hospital Angeles
```

**Ventajas**: Simple, rápida, clara
**Desventajas**: Menos detalle

### Opción 2: DETALLADA

```
Estimado {{1}},

¡Gracias por agendar tu cita!

Tu cita ha sido confirmada con los siguientes detalles:

SERVICIO: {{2}}
FECHA: {{3}}
HORA: {{4}}
INDICACIONES: {{5}}

IMPORTANTE:
✓ Llega 10 minutos antes
✓ Trae tus documentos de identificación
✓ Trae tus estudios previos si los tienes
✓ Vístete cómodamente para el estudio

Si necesitas cambios, contáctanos.

Hospital Angeles
Imagenología y Diagnóstico
```

**Ventajas**: Muy completa, profesional
**Desventajas**: Más texto (algunos celulares lo ven cortado)

### Opción 3: ULTRAMINIMAL

```
Cita confirmada {{1}}
{{2}} - {{3}} a las {{4}}
{{5}}
Hospital Angeles
```

**Ventajas**: Muy corta, directa
**Desventajas**: Muy poco detalle

---

## 📱 Vista en WhatsApp del Mensaje

### Después de enviar, el cliente verá:

```
╔════════════════════════════════════════╗
║  Hospital Angeles                      ║
║  MENSAJE DE PLANTILLA APROBADA         ║
╠════════════════════════════════════════╣
║                                        ║
║  Hola Juan García,                     ║
║                                        ║
║  Tu cita de Radiografía ha sido        ║
║  agendada exitosamente.                ║
║                                        ║
║  📅 Fecha: 15 de noviembre de 2025     ║
║  🕐 Hora: 14:30                        ║
║  📝 Indicaciones: Estudios de tórax    ║
║  sin contraste                         ║
║                                        ║
║  Recuerda llegar 10 minutos antes...   ║
║                                        ║
║  Saludos,                              ║
║  Hospital Angeles - Imagenología       ║
║                                        ║
╠════════════════════════════════════════╣
║ [Botón 1: Confirmar] [Botón 2: Cambiar] 
╚════════════════════════════════════════╝
```

---

## 🔧 Testing de Plantilla

### Test 1: En Meta Developer Console

1. Ve a "Test de Plantilla" en Meta
2. Selecciona "citaagendada"
3. Ingresa valores de prueba:
   - {{1}}: "Test Cliente"
   - {{2}}: "Radiografía"
   - {{3}}: "15 de noviembre de 2025"
   - {{4}}: "14:30"
   - {{5}}: "Estudios de tórax"
4. Haz clic en "Enviar mensaje de prueba"

### Test 2: Desde Script PHP

```php
<?php
require_once 'includes/whatsapp_functions.php';

$resultado = enviarMensajeWhatsAppCita(
    '5215551234567',           // Tu número de WhatsApp
    'Test Cliente',             // {{1}}
    'Radiografía',             // {{2}}
    '2025-11-15',              // {{3}}
    '14:30:00',                // {{4}}
    'Estudios de tórax'        // {{5}}
);

if ($resultado['success']) {
    echo "✓ Mensaje enviado: " . $resultado['response']['messages'][0]['id'];
} else {
    echo "✗ Error: " . $resultado['message'];
}
```

### Test 3: Verificación en WhatsApp

- Abre WhatsApp en el número registrado
- Busca el mensaje del negocio
- Verifica que se vea correctamente formateado
- Prueba los botones si los agregaste

---

## 🚨 Estados Posibles de Plantilla

| Estado | Significado | Acción |
|--------|------------|--------|
| PENDING | En revisión por Meta | Esperar 2-24 horas |
| APPROVED | Aprobada y lista | Usar normalmente ✓ |
| REJECTED | Rechazada | Revisar razón, editar, reenviar |
| DISABLED | Deshabilitada por Meta | Contactar soporte |

### Si es REJECTED:

1. Revisa el motivo en "Detalles de rechazo"
2. Razones comunes:
   - Contenido no apropiado
   - Formato incorrecto
   - Variables no usadas correctamente
   - Violación de términos
3. Edita la plantilla
4. Reenvía para aprobación

---

## 📊 Límites y Consideraciones

```
Límites de Plantilla:
├─ Nombre: máximo 512 caracteres
├─ Header: máximo 60 caracteres
├─ Body: máximo 1024 caracteres
├─ Footer: máximo 60 caracteres
├─ Variables: máximo 5 por plantilla
└─ Botones: máximo 3 botones

Requerimientos Meta:
├─ Debe ser de categoría válida
├─ Contenido debe ser clara y relevante
├─ No puede contener variables NO documentadas
├─ Debe estar en idioma correcto
├─ No puede ser engañoso
└─ Debe respetar términos de servicio
```

---

## 🔄 Versiones y Updates

Si necesitas cambiar la plantilla:

### Opción 1: Modificar Existente
- Meta permite editar plantillas no aprobadas
- Envía nuevamente para aprobación
- ID cambia, versión incrementa

### Opción 2: Crear Nueva Versión
- Crea "citaagendada_v2"
- Modifica configuración en whatsapp_config.php
- Mantén compatibilidad con variables

### Opción 3: Fallback
```php
// En whatsapp_config.php, si "citaagendada" falla:
'template_name' => getenv('WHATSAPP_TEMPLATE') ?: 'citaagendada',
// Permite cambiar sin código
```

---

## 📞 Números de Prueba Meta

Meta proporciona números de prueba:

```
Número de prueba: +1 415 523 8886 (para testing)
Código: 666666
```

Úsalos SOLO para testing en desarrollo.

---

## ✅ Checklist Final

- [ ] Plantilla "citaagendada" creada en Meta
- [ ] Categoría: APPOINTMENT_UPDATE
- [ ] Idioma: es_MX (Español México)
- [ ] 5 parámetros definidos correctamente
- [ ] Variables mapeadas en código PHP
- [ ] Plantilla enviada para aprobación
- [ ] Estado: APPROVED
- [ ] Número de teléfono agrega a WhatsApp Business Account
- [ ] Access Token obtenido
- [ ] Phone Number ID obtenido
- [ ] Prueba de envío exitosa

---

## 🎓 Recursos Meta

- Documentación: https://developers.facebook.com/docs/whatsapp/message-templates/
- Template Builder: https://www.whatsapp.com/business/templates/
- API Reference: https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
- Best Practices: https://www.whatsapp.com/business/guide/

---

**Última actualización**: 14 de noviembre de 2025
