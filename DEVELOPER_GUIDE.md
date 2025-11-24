# 👨‍💻 GUÍA DEL DESARROLLADOR - INTEGRACIÓN WHATSAPP

## 🎯 Objetivo

Entender cómo se integró WhatsApp al sistema de citas y cómo mantenerlo.

## 📁 Estructura de Archivos

```
Hospital Angeles/
├── includes/
│   ├── whatsapp_functions.php      ← Funciones principales
│   ├── whatsapp_config.php         ← Configuración
│   ├── db.php                      ← Base de datos
│   ├── email_functions.php         ← Correos
│   └── ... (otros)
│
├── guardar_cita.php                ← Admin - Integración ✓
├── guardar_reserva_cliente.php     ← Cliente - Integración ✓
│
├── WHATSAPP_INTEGRATION.md         ← Guía de configuración
├── ANALISIS_DETALLADO.md          ← Análisis técnico
├── META_TEMPLATE_SETUP.md          ← Setup en Meta
├── README_WHATSAPP.md              ← Resumen ejecutivo
└── test_whatsapp.php               ← Script de prueba
```

## 🔍 Anatomía de whatsapp_functions.php

### Función Principal

```php
function enviarMensajeWhatsAppCita(
    $telefono,              // Teléfono destino (con código país)
    $nombre_paciente,       // {{1}}
    $modalidad,            // {{2}}
    $fecha,                // {{3}} - YYYY-MM-DD
    $hora_inicio,          // {{4}} - HH:MM:SS
    $descripcion_servicio  // {{5}}
)
```

### Funciones Auxiliares

```
✓ validarDatosWhatsApp()        - Validación de entrada
✓ obtenerConfiguracionWhatsApp() - Carga de config
✓ prepararVariablesTemplate()   - Preparación de parámetros
✓ formatearFecha()              - Conversión a español
✓ enviarViaMetaAPI()            - HTTP request a Meta
✓ enviarWhatsAppSilencioso()    - Wrapper sin bloqueo
```

## 🔗 Integración en guardar_cita.php

### Línea 36 - Cargar módulo
```php
require_once __DIR__ . '/includes/whatsapp_functions.php';
```

### Línea ~150 - Obtener datos para notificación
```php
// Después de COMMIT, obtener datos para WhatsApp
$stmt_datos = $conn->prepare("
    SELECT 
        p.nombre, p.apellido, p.telefono,
        m.nombre, s.descripcion,
        c.fecha, c.hora_inicio
    FROM agenda_citas c
    JOIN portal_pacientes p ON c.paciente_id = p.id
    LEFT JOIN agenda_modalidades m ON c.modalidad_id = m.id
    LEFT JOIN portal_servicios s ON c.servicio_id = s.id
    WHERE c.id = ?
");
```

### Línea ~180 - Enviar WhatsApp
```php
if ($telefono && !empty($nombre_modalidad)) {
    enviarWhatsAppSilencioso(
        $telefono,
        $nombre_paciente . ' ' . $apellido_paciente,
        $nombre_modalidad,
        $fecha_cita,
        $hora_cita,
        $descripcion_para_wa
    );
}
```

## 🔗 Integración en guardar_reserva_cliente.php

### Línea 49 - Cargar módulo
```php
require_once(__DIR__ . "/includes/whatsapp_functions.php");
```

### Línea ~217 - Obtener datos después de COMMIT
```php
$stmt_notif = $conn->prepare("
    SELECT 
        p.nombre, p.apellido, p.telefono,
        m.nombre, s.descripcion,
        c.fecha, c.hora_inicio
    FROM agenda_citas c
    JOIN portal_pacientes p ON c.paciente_id = p.id
    LEFT JOIN agenda_modalidades m ON c.modalidad_id = m.id
    LEFT JOIN portal_servicios s ON c.servicio_id = s.id
    WHERE c.id = ?
");
```

### Línea ~230 - Enviar WhatsApp
```php
if ($tel_pac && $mod_nombre) {
    enviarWhatsAppSilencioso(
        $tel_pac,
        $nom_pac . ' ' . $ape_pac,
        $mod_nombre,
        $fec_cita,
        $hor_cita,
        $desc_final
    );
}
```

## 🧪 Testing - Cómo Probar

### Test 1: Validación de Configuración

```bash
php -r "
require 'includes/whatsapp_config.php';
require 'includes/whatsapp_functions.php';
\$cfg = obtenerConfiguracionWhatsApp();
echo \$cfg['success'] ? 'OK' : 'ERROR: ' . \$cfg['message'];
"
```

### Test 2: Validación de Datos

```bash
php -r "
require 'includes/whatsapp_functions.php';
\$val = validarDatosWhatsApp('5215551234567', 'Juan', 'Radiografía', '2025-11-20', '14:30', 'Test');
var_dump(\$val);
"
```

### Test 3: Envío Real

```bash
php -r "
require 'includes/whatsapp_functions.php';
\$res = enviarMensajeWhatsAppCita('5215551234567', 'Test', 'Radiografía', '2025-11-20', '14:30', 'Test');
var_dump(\$res);
"
```

### Test 4: Script Completo

```bash
php test_whatsapp.php
```

## 🐛 Debugging

### Ver logs en tiempo real
```bash
tail -f /var/log/php-error.log | grep -E "WhatsApp|Mensaje"
```

### Buscar errores específicos
```bash
grep "Error al enviar WhatsApp" /var/log/php-error.log
grep "Mensaje WhatsApp enviado" /var/log/php-error.log
```

### Habilitar debug en código

```php
// Agregar al inicio de whatsapp_functions.php
define('WHATSAPP_DEBUG', true);

// Luego usar:
if (defined('WHATSAPP_DEBUG') && WHATSAPP_DEBUG) {
    error_log("Debug: " . json_encode($payload));
}
```

## 🔧 Customización

### Cambiar plantilla

En `whatsapp_config.php`:
```php
'template_name' => 'citaagendada', // Cambiar aquí
```

### Cambiar idioma

En `whatsapp_config.php`:
```php
'language_code' => 'es_MX', // a 'es_ES', 'en_US', etc.
```

### Agregar reintentos

En `whatsapp_functions.php`, modificar `enviarViaMetaAPI()`:
```php
$max_retries = 3;
$retry_count = 0;
while ($retry_count < $max_retries) {
    // ... enviar ...
    if (success) break;
    $retry_count++;
    sleep(2); // esperar antes de reintentar
}
```

### Cambiar formato de fecha

En `whatsapp_functions.php`, modificar `formatearFecha()`:
```php
// De: "15 de noviembre de 2025"
// A: "15/11/2025"
return date('d/m/Y', $timestamp);
```

## 🚨 Manejo de Errores

### Flujo de errores

```
Error en WhatsApp
        ↓
Capturado en try-catch
        ↓
Registrado en error_log()
        ↓
NO bloquea flujo
        ↓
Cita se crea igual
        ↓
Usuario no notificado del error
```

### Tipos de errores comunes

| Error | Causa | Solución |
|-------|-------|----------|
| "Config no disponible" | WHATSAPP_ACCESS_TOKEN vacío | Revisar .env |
| "Teléfono inválido" | < 10 dígitos | Validar entrada |
| "Plantilla no encontrada" | Nombre incorrecto en Meta | Registrar "citaagendada" |
| "Unauthorized" | Token expirado | Renovar en Meta |
| "API Error 400" | Variables inválidas | Revisar formato |
| "Connection timeout" | Red lenta | Reintentar |

### Logs de ejemplo

Éxito:
```
Mensaje WhatsApp enviado exitosamente. Teléfono: 525551234567, Response: {"messages":[{"id":"wamid.xxxxx"}]}
```

Error:
```
Error al enviar WhatsApp (HTTP 401): {"error":{"code":401,"message":"Unauthorized"}}
```

## 📊 Monitoreo en Producción

### Dashboard de logs

```bash
# Ver todos los intentos de WhatsApp
grep -i "whatsapp" /var/log/php-error.log | tail -20

# Contar éxitos
grep -c "enviado exitosamente" /var/log/php-error.log

# Contar errores
grep -c "Error al enviar" /var/log/php-error.log
```

### Alertas recomendadas

```bash
# Alerta si 5+ errores en 1 hora
0 * * * * grep "Error al enviar WhatsApp" /var/log/php-error.log | grep "$(date -d '-1 hour' +'%b %d %H')" | wc -l | awk '{if($1>5) print "Alerta: "  $1 " errores de WhatsApp"}' | mail -s "WhatsApp Errors Alert" admin@hospital.com
```

## 🔐 Seguridad - Checklist

- [ ] Credenciales en variables de entorno (NO en código)
- [ ] .env en .gitignore
- [ ] SSL/TLS habilitado (requerido por Meta)
- [ ] Validación de teléfono antes de enviar
- [ ] Logs sin credenciales completas
- [ ] Rate limiting en API (opcional)
- [ ] Backups de tokens seguros
- [ ] Tokens rotados regularmente

## 📈 Performance

### Timing esperado

- Validación: ~5ms
- Obtención de datos: ~50ms
- Envío HTTP a Meta: ~500-2000ms
- **Total**: ~600-2000ms (no bloquea UX)

### Optimizaciones

1. **Envío asincrónico** (queue):
```php
// En lugar de enviar directo:
// enviarWhatsAppSilencioso(...);

// Encolar para enviar después:
queue_add_job('send_whatsapp', [
    'telefono' => $tel,
    'nombre' => $nombre,
    // ...
]);
```

2. **Caché de configuración**:
```php
// En lugar de cargar cada vez:
static $config_cache = null;
if ($config_cache === null) {
    $config_cache = obtenerConfiguracionWhatsApp();
}
```

3. **Batch sending** (si muchos usuarios):
```php
// Enviar múltiples en una transacción
foreach ($citas as $cita) {
    queue_add_job(...);
}
// Procesar queue después
```

## 🔄 Versioning

Si necesitas cambios:

### v1.1 - Cambios menores

```php
// En whatsapp_config.php
const WHATSAPP_VERSION = '1.1';

// En whatsapp_functions.php
function enviarMensajeWhatsAppCita(
    // ... parámetros iguales ...
    $config_whatsapp = null
) {
    // ... código mejorado ...
}
```

### v2.0 - Cambios mayores

- Nueva estructura de plantilla
- Nuevas variables
- Cambio de API

## 📝 Mantenimiento

### Checklist mensual

- [ ] Revisar logs de WhatsApp
- [ ] Verificar tasa de éxito/error
- [ ] Renovar tokens si es necesario
- [ ] Verificar límites de Meta no excedidos
- [ ] Actualizar documentación

### Checklist anual

- [ ] Revisar si Meta tiene versión API más nueva
- [ ] Evaluar nuevas funcionalidades
- [ ] Auditoría de seguridad
- [ ] Optimizaciones de performance

## 🆘 Troubleshooting Rápido

**Problema: WhatsApp no envía silencioso, parece que nada ocurre**
```bash
# Solución:
1. Revisar logs: tail -f /var/log/php-error.log
2. Buscar errores: grep -i "whatsapp" logs
3. Validar config: php -c "require 'includes/whatsapp_config.php'; var_dump($WHATSAPP_CONFIG);"
```

**Problema: Dice "Configuración no disponible"**
```bash
# Solución:
1. Verifica .env existe
2. Verifica WHATSAPP_ACCESS_TOKEN tiene valor
3. Verifica WHATSAPP_PHONE_NUMBER_ID tiene valor
```

**Problema: "Teléfono inválido"**
```bash
# Solución:
# El teléfono debe:
1. Tener al menos 10 dígitos
2. Incluir código país (52 para México)
3. Ejemplo válido: 5215551234567
```

## 🎓 Aprendizaje Recomendado

1. Leer: `whatsapp_functions.php` (comentarios)
2. Leer: `ANALISIS_DETALLADO.md` (arquitectura)
3. Probar: `test_whatsapp.php` (hands-on)
4. Revisar: Logs cuando agendes cita (real-world)
5. Modificar: Cambios menores (práctica)

## 📚 Referencias

- Meta WhatsApp API: https://developers.facebook.com/docs/whatsapp
- Cloud API Reference: https://developers.facebook.com/docs/cloud-api/
- Message Templates: https://developers.facebook.com/docs/whatsapp/message-templates/

## 💬 Q&A

**¿Puedo deshabilitar WhatsApp?**
```php
// En .env:
WHATSAPP_ENABLED=false

// En código:
if (!$config['enabled']) return;
```

**¿Puedo enviar más variables?**
Sí, necesitas crear nueva plantilla en Meta con más {{n}}.

**¿Puedo cambiar el formato del mensaje?**
Sí, edita la plantilla en Meta (requiere nueva aprobación).

**¿Qué pasa con pacientes sin teléfono?**
Se valida y se salta el envío. La cita se crea igual.

---

**Documento actualizado**: 14 de noviembre de 2025  
**Versión**: 1.0  
**Para**: Desarrolladores y DevOps
