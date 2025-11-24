# Integración WhatsApp - Sistema de Citas Hospital Angeles

## 📋 Descripción

Este documento explica cómo se ha integrado WhatsApp al sistema de agendamiento de citas usando la API Cloud de Meta. Cuando se agendan citas (tanto desde el panel administrativo como desde el portal de clientes), se enviará automáticamente un mensaje de confirmación vía WhatsApp.

## 🔧 Configuración Requerida

### 1. Credenciales de Meta Cloud API

Para que WhatsApp funcione, necesitas:

1. **Access Token**: Token de acceso a la API de Meta
2. **Phone Number ID**: ID del número de teléfono WhatsApp Business
3. **Business Account ID** (opcional): ID de la cuenta comercial

### 2. Obtener Credenciales

Sigue estos pasos en Facebook Developer:

1. Ve a https://developers.facebook.com/
2. Crea una aplicación de negocio (Business App)
3. Agrega WhatsApp como producto
4. Configura una cuenta WhatsApp Business
5. Obtén:
   - **Access Token**: En Configuración > Generador de Tokens de Acceso
   - **Phone Number ID**: En WhatsApp Business > API Setup
   - **Business Account ID**: En WhatsApp Business > Account

### 3. Registrar Plantilla "citaagendada"

La plantilla DEBE estar registrada en Meta Cloud Console con el nombre exacto: **citaagendada**

Estructura de la plantilla:
```
Asunto: Confirmación de Cita Médica
Cuerpo:
Hola {{1}},

Tu cita de {{2}} ha sido agendada exitosamente.

📅 Fecha: {{3}}
🕐 Hora: {{4}}
📝 Indicaciones: {{5}}

Recuerda llegar 10 minutos antes de tu cita.

Para cambios o cancelaciones, contáctanos.

Saludos,
Hospital Angeles
```

Notas:
- Las variables {{1}} a {{5}} corresponden a los parámetros del mensaje
- La plantilla debe estar en español (es_MX)
- Debe ser aprobada por Meta antes de usar

## 🔐 Configuración de Variables de Entorno

### Opción 1: Usar archivo .env (Recomendado)

Crea un archivo `.env` en la raíz del proyecto:

```env
WHATSAPP_ENABLED=true
WHATSAPP_ACCESS_TOKEN=your_access_token_here
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id_here
WHATSAPP_BUSINESS_ACCOUNT_ID=your_business_account_id_here
```

### Opción 2: Usar variables de entorno del servidor

Configura las siguientes variables en tu servidor:

```bash
export WHATSAPP_ENABLED=true
export WHATSAPP_ACCESS_TOKEN="your_access_token_here"
export WHATSAPP_PHONE_NUMBER_ID="your_phone_number_id_here"
export WHATSAPP_BUSINESS_ACCOUNT_ID="your_business_account_id_here"
```

### Opción 3: Archivo de configuración directa (Menos seguro)

Edita `/includes/whatsapp_config.php` directamente (NO RECOMENDADO en producción):

```php
$WHATSAPP_CONFIG = [
    'access_token' => 'your_token',
    'phone_number_id' => 'your_phone_id',
    'business_account_id' => 'your_business_id',
    'template_name' => 'citaagendada',
    'language_code' => 'es_MX',
    'enabled' => true
];
```

## 📍 Cómo Funciona la Integración

### Flujo desde Panel Administrativo (index.php)

1. Administrador abre modal "Agendar Nueva Cita"
2. Llena los datos: paciente, modalidad, servicio, fecha, hora
3. Hace clic en "Guardar Cita"
4. Se envía a `guardar_cita.php` via AJAX
5. Se valida la cita y se inserta en BD
6. Se obtienen datos del paciente (nombre, teléfono, modalidad, etc.)
7. **Se envía correo de confirmación** (existente)
8. **Se envía mensaje WhatsApp** con la plantilla "citaagendada"

### Flujo desde Portal de Clientes (reservar.php)

1. Cliente completa formulario de reserva
2. Se validan datos: nombre, email, teléfono, fecha, hora
3. Se envía a `guardar_reserva_cliente.php` via AJAX
4. Se crea paciente en BD (si no existe)
5. Se crea la cita
6. Se confirma la transacción
7. **Se envía mensaje WhatsApp** con confirmación de cita

## 📤 Variables del Mensaje WhatsApp

El mensaje se envía con las siguientes variables:

| Posición | Variable | Descripción | Ejemplo |
|----------|----------|-------------|---------|
| {{1}} | Nombre del paciente | Nombre completo | Juan García |
| {{2}} | Modalidad | Tipo de estudio | Radiografía |
| {{3}} | Fecha | Fecha formateada | 15 de noviembre de 2025 |
| {{4}} | Hora inicio | Hora de la cita | 14:30 |
| {{5}} | Descripción/Indicaciones | Instrucciones del servicio | Estudios simples sin contraste |

## 📝 Archivos Modificados/Creados

### Archivos Nuevos:
- **`/includes/whatsapp_functions.php`**: Contiene todas las funciones para enviar WhatsApp
- **`/includes/whatsapp_config.php`**: Configuración centralizada

### Archivos Modificados:
- **`guardar_cita.php`**: Agregado envío de WhatsApp tras crear cita
- **`guardar_reserva_cliente.php`**: Agregado envío de WhatsApp tras crear reserva

### Sin cambios:
- **`index.php`**: El JavaScript existente sigue funcionando igual
- **`reservar.php`**: El JavaScript existente sigue funcionando igual

## 🔍 Monitoreo y Logs

Todos los envíos de WhatsApp se registran en los logs del servidor:

```bash
# Ver logs en archivo
tail -f /var/log/php-error.log

# Búsqueda de mensajes WhatsApp
grep "WhatsApp" /var/log/php-error.log
```

### Mensajes esperados:

✅ **Envío exitoso:**
```
Mensaje WhatsApp enviado exitosamente. Teléfono: 525551234567, Response: {...}
```

❌ **Error de envío:**
```
Error al enviar WhatsApp (HTTP 400): {...}
```

## 🧪 Pruebas

### Test Manual desde CLI

```bash
php -r "
require_once 'includes/whatsapp_functions.php';
\$resultado = enviarMensajeWhatsAppCita(
    '525551234567',
    'Juan García',
    'Radiografía',
    '2025-11-20',
    '14:30',
    'Estudios de tórax sin contraste'
);
var_dump(\$resultado);
"
```

### Validar Configuración

```bash
php -r "
require_once 'includes/whatsapp_config.php';
require_once 'includes/whatsapp_functions.php';
\$config = obtenerConfiguracionWhatsApp();
if (\$config['success']) {
    echo 'Configuración válida' . PHP_EOL;
    echo 'Phone ID: ' . \$config['config']['phone_number_id'] . PHP_EOL;
} else {
    echo 'Error en configuración: ' . \$config['message'] . PHP_EOL;
}
"
```

## 🚀 Implementación en Producción

### Checklist:

- [ ] Credenciales obtenidas de Meta Cloud
- [ ] Plantilla "citaagendada" registrada y aprobada en Meta
- [ ] Variables de entorno configuradas en servidor
- [ ] Permisos de archivos correctos (644 para archivos config)
- [ ] SSL/TLS habilitado (requerido por Meta)
- [ ] Prueba de envío exitoso a número real
- [ ] Logs siendo monitoreados
- [ ] Backup de credenciales en lugar seguro

## ⚠️ Notas Importantes

1. **Números de teléfono**: Deben tener formato internacional (ej: 525551234567)
2. **Plantilla**: Debe estar exactamente registrada como "citaagendada"
3. **Idioma**: Actualmente configurado para es_MX (español México)
4. **API versión**: v18.0 (se puede actualizar en whatsapp_config.php)
5. **Reintentos**: El sistema intenta 3 veces en caso de error (configurable)
6. **No bloquea**: Los errores de WhatsApp no detienen el flujo de agendamiento

## 🔧 Troubleshooting

### Error: "Configuración de WhatsApp no disponible"
**Solución**: Verifica que WHATSAPP_ACCESS_TOKEN y WHATSAPP_PHONE_NUMBER_ID estén configurados

### Error: "Teléfono inválido"
**Solución**: El número debe tener al menos 10 dígitos (se agrega código 52 si falta)

### Error: "Plantilla no encontrada"
**Solución**: Verifica que "citaagendada" esté registrada y aprobada en Meta Cloud

### Error: "Unauthorized"
**Solución**: Verifica que el Access Token sea válido y no haya expirado

### WhatsApp no se envía pero sin errores
**Solución**: 
- Revisa los logs del servidor
- Valida que WHATSAPP_ENABLED=true
- Verifica permisos de red (firewall, proxy, etc.)

## 📧 Soporte

Para problemas con:
- **WhatsApp API**: https://www.whatsapp.com/business/developers
- **Meta Cloud Console**: https://developers.facebook.com/docs/whatsapp/cloud-api/
- **Sistema de citas**: Contacta al equipo de desarrollo

## 📄 Licencia y Términos

- Asegúrate de cumplir con los términos de servicio de Meta/WhatsApp
- Requiere permiso explícito del cliente para enviar mensajes vía WhatsApp
- Almacena registros de envío para auditoría

---

**Última actualización**: 14 de noviembre de 2025  
**Versión**: 1.0
