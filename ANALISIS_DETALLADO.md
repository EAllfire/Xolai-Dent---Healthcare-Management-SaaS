# ANÁLISIS DETALLADO DEL SISTEMA DE AGENDAMIENTO CON INTEGRACIÓN WHATSAPP

## 📊 Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────────────────┐
│                    SISTEMA HOSPITALARIO DE CITAS                     │
└─────────────────────────────────────────────────────────────────────┘

                              FRONTEND
        ┌──────────────────────┬──────────────────────┐
        │                      │                      │
    index.php            reservar.php           cliente.php
   (Admin Panel)       (Client Portal)       (Client View)
        │                      │                      │
        └──────────┬───────────┴──────────┬───────────┘
                   │                      │
                   ▼                      ▼
            ┌─────────────────┐  ┌──────────────────────┐
            │  guardar_cita   │  │ guardar_reserva_     │
            │    .php         │  │  cliente.php         │
            │  (ADMIN FLOW)   │  │  (CLIENT FLOW)       │
            └────────┬────────┘  └──────────┬───────────┘
                     │                       │
                     └───────────┬───────────┘
                                 ▼
                    ┌──────────────────────────┐
                    │   VALIDACIONES Y BD      │
                    │  - Verificar empalmes    │
                    │  - Crear paciente        │
                    │  - Insertar cita         │
                    │  - Confirmar transacción │
                    └────────────┬─────────────┘
                                 │
                    ┌────────────┬┴───────────┐
                    │            │            │
                    ▼            ▼            ▼
              EMAIL SEND    WHATSAPP SEND   PAYMENT PROCESS
              (Correo)      (Nuevo!)       (Solo reservas)
```

## 🔄 Flujos de Agendamiento Detallados

### FLUJO 1: AGENDAMIENTO DESDE PANEL ADMIN (index.php)

```
1. PRESENTACIÓN
   ├─ Usuario logueado en index.php
   ├─ Modal "Agendar Nueva Cita" se abre
   └─ Modal permite seleccionar:
       ├─ Paciente (búsqueda/creación)
       ├─ Modalidad (Radiografía, Resonancia, etc.)
       ├─ Servicio (según modalidad seleccionada)
       ├─ Fecha y Hora
       ├─ Estado inicial (Reservado, Confirmado, etc.)
       └─ Notas (paciente e internas)

2. VALIDACIÓN EN CLIENTE (JavaScript en index.php)
   ├─ Verificar que paciente_id no esté vacío
   ├─ Verificar que todas las fechas/horas sean válidas
   ├─ Verificar que modalidad_id esté seleccionado
   └─ Construir objeto JSON con datos

3. ENVÍO AL SERVIDOR
   ├─ método: POST
   ├─ endpoint: guardar_cita.php
   ├─ headers: Content-Type: application/json
   └─ body: JSON con todos los datos

4. PROCESAMIENTO EN guardar_cita.php
   ├─ Parsear JSON
   ├─ Validar datos obligatorios
   ├─ CÁLCULO DE HORA_FIN
   │  ├─ Si hora_fin está vacía
   │  ├─ Buscar duracion_minutos en portal_servicios
   │  └─ Sumar minutos a hora_inicio
   ├─ VERIFICAR EMPALMES
   │  ├─ SELECT COUNT(*) FROM agenda_citas
   │  ├─ WHERE fecha = ? AND modalidad_id = ?
   │  └─ Verificar no se superponen horarios
   ├─ INSERTAR EN BD
   │  ├─ INSERT INTO agenda_citas (...)
   │  ├─ Autoincrement genera cita_id
   │  └─ Confirmar transacción (COMMIT)
   ├─ OBTENER DATOS PARA NOTIFICACIONES
   │  ├─ SELECT nombre, apellido, telefono, correo, modalidad, etc.
   │  └─ FROM agenda_citas JOIN portal_pacientes ...
   ├─ ENVIAR CORREO ✉️
   │  ├─ send_appointment_confirmation_email()
   │  ├─ Plantilla HTML con detalles
   │  └─ Registrar en logs
   ├─ ENVIAR WHATSAPP 🚀 (NUEVO)
   │  ├─ Validar teléfono
   │  ├─ Preparar variables para plantilla
   │  ├─ Conectar a Meta Cloud API
   │  ├─ Enviar mensaje (no bloquea flujo)
   │  └─ Registrar en logs
   └─ RETORNAR JSON
      ├─ { success: true, id: cita_id }
      └─ o { success: false, error: "..." }

5. RESPUESTA EN CLIENTE (JavaScript en index.php)
   ├─ if (resp.success)
   │  ├─ alert('Cita agendada correctamente')
   │  ├─ Cerrar modal
   │  └─ calendar.refetchEvents() // Recargar calendario
   └─ else
      └─ alert('Error: ' + resp.error)
```

### FLUJO 2: AGENDAMIENTO DESDE PORTAL DE CLIENTES (reservar.php)

```
1. PRESENTACIÓN
   ├─ Formulario público en reservar.php
   ├─ Datos del paciente (si es nuevo)
   │  ├─ Nombre, Apellido
   │  ├─ Teléfono, Email
   │  ├─ Fecha de nacimiento
   │  └─ Tipo de paciente (Niño, Adulto, IMSS, etc.)
   ├─ Datos de la cita
   │  ├─ Modalidad
   │  ├─ Servicio
   │  ├─ Fecha y Hora
   │  └─ Observaciones
   ├─ Documentos (Identificación, Orden médica)
   └─ Aceptar términos y privacidad

2. VALIDACIÓN EN CLIENTE (JavaScript en reservar.php)
   ├─ Verificar campos obligatorios
   ├─ Validar formato de email
   ├─ Validar formato de teléfono
   ├─ Validar que fecha no sea pasada
   ├─ Subir archivos adjuntos a servidor
   └─ Enviar FormData a guardar_reserva_cliente.php

3. PROCESAMIENTO EN guardar_reserva_cliente.php
   ├─ Validar método POST
   ├─ Parsear JSON de datos
   ├─ Validar campos requeridos
   ├─ PROCESAR ARCHIVOS ADJUNTOS
   │  ├─ Crear carpeta uploads/ si no existe
   │  ├─ Guardar foto de identificación
   │  └─ Guardar foto de orden médica
   ├─ BEGIN TRANSACTION
   ├─ GESTIÓN DE PACIENTE
   │  ├─ SELECT id FROM portal_pacientes WHERE correo = ?
   │  ├─ Si existe:
   │  │  └─ UPDATE nombre, apellido, teléfono
   │  └─ Si no existe:
   │     └─ INSERT new paciente
   ├─ DETERMINAR MODALIDAD/SERVICIO
   │  ├─ Si tipo_reserva = 'paquete'
   │  │  └─ Buscar servicio con 'paquete' o 'integral'
   │  └─ Si especificado
   │     └─ Usar modalidad_id y servicio_id proporcionados
   ├─ VERIFICAR EMPALMES (igual que en admin)
   ├─ CREATE CITA
   │  ├─ INSERT INTO agenda_citas
   │  └─ Generar cita_id
   ├─ CLOSE STATEMENTS
   ├─ COMMIT TRANSACTION
   ├─ ENVIAR NOTIFICACIONES (NUEVO)
   │  ├─ Obtener datos de la cita y paciente
   │  ├─ ENVIAR WHATSAPP 🚀
   │  │  ├─ Validar teléfono
   │  │  ├─ Preparar variables
   │  │  ├─ Enviar vía Meta API
   │  │  └─ No bloquea si falla
   │  └─ Registrar en logs
   ├─ PROCESAR PAGO
   │  ├─ require GestorPagos.php
   │  ├─ crearPago() con cita_id
   │  ├─ La cita ya está creada (no afecta)
   │  └─ Procesar pago de forma independiente
   └─ RETORNAR JSON
      └─ { success: true, message: "...", data: {...} }

4. RESPUESTA EN CLIENTE (JavaScript en reservar.php)
   ├─ if (resp.success)
   │  ├─ Mostrar mensaje de éxito
   │  ├─ Redirigir a página de confirmación
   │  └─ Iniciar proceso de pago
   └─ else
      └─ Mostrar error detallado
```

## 📱 INTEGRACIÓN WHATSAPP - DETALLES TÉCNICOS

### Ubicación de Código WhatsApp

```
includes/
├─ whatsapp_functions.php ......... Funciones principales
├─ whatsapp_config.php ........... Configuración de API
│
guardar_cita.php ................. Integrado en línea ~150-180
guardar_reserva_cliente.php ....... Integrado en línea ~217-250
```

### Variables del Mensaje WhatsApp

La plantilla "citaagendada" espera 5 parámetros:

```
Plantilla en Meta:
"Hola {{1}}, tu cita de {{2}} está agendada para {{3}} a las {{4}}. {{5}}"

Mapeamiento:
├─ {{1}} = Nombre completo paciente (string)
├─ {{2}} = Nombre de modalidad (string: "Radiografía", "Resonancia", etc.)
├─ {{3}} = Fecha formateada (string: "15 de noviembre de 2025")
├─ {{4}} = Hora sin segundos (string: "14:30")
└─ {{5}} = Descripción/indicaciones servicio (string)
```

### Función Principal: enviarMensajeWhatsAppCita()

```php
enviarMensajeWhatsAppCita(
    $telefono,              // "5215551234567" (con código país)
    $nombre_paciente,       // "Juan García"
    $modalidad,            // "Radiografía"
    $fecha,                // "2025-11-20" (YYYY-MM-DD)
    $hora_inicio,          // "14:30:00" (HH:MM:SS)
    $descripcion_servicio  // "Estudios de tórax sin contraste"
)
```

### Validaciones Implementadas

```
✓ Teléfono:
  ├─ Mínimo 10 dígitos
  ├─ Agrega código país (52) si falta
  └─ Remueve caracteres especiales

✓ Nombre:
  ├─ No puede estar vacío
  └─ Trimmed

✓ Modalidad:
  ├─ No puede estar vacío
  └─ Debe coincidir en base de datos

✓ Fecha:
  ├─ Formato YYYY-MM-DD
  ├─ No puede ser anterior a hoy
  └─ Formateada a español (ej: "15 de noviembre de 2025")

✓ Hora:
  ├─ Formato HH:MM o HH:MM:SS
  └─ Convertida a HH:MM (sin segundos)

✓ Descripción:
  ├─ No puede estar vacía
  └─ Máximo 1024 caracteres recomendado
```

### Manejo de Errores

```
Estrategia: NO BLOQUEA EL FLUJO
├─ Si falla WhatsApp:
│  ├─ Se registra error en logs
│  ├─ La cita se crea normalmente
│  ├─ El usuario no ve error (enviado silenciosamente)
│  └─ Admin puede revisar logs más tarde
└─ Si falla Email:
   ├─ Similar a WhatsApp
   └─ Independiente de WhatsApp

Errores capturados:
├─ Configuración incompleta
├─ Teléfono inválido
├─ API no responde
├─ Plantilla no encontrada
├─ Token expirado
└─ Límite de API alcanzado
```

## 📊 Bases de Datos Involucradas

```
agenda_citas (tabla principal)
├─ id: int AUTO_INCREMENT
├─ fecha: DATE
├─ hora_inicio: TIME
├─ hora_fin: TIME
├─ paciente_id: int (FK -> portal_pacientes.id)
├─ profesional_id: int (FK -> agenda_profesionales.id, NULL)
├─ servicio_id: int (FK -> portal_servicios.id)
├─ modalidad_id: int (FK -> agenda_modalidades.id)
├─ estado_id: int (FK -> agenda_estado_cita.id)
├─ nota_paciente: text
├─ nota_interna: text
├─ tipo: enum('normal', 'paquete', 'bloqueo')
├─ token: varchar(64) (para email token)
└─ [otros campos]

portal_pacientes (pacientes)
├─ id: int AUTO_INCREMENT
├─ nombre: varchar
├─ apellido: varchar
├─ telefono: varchar ◄── USADO PARA WHATSAPP
├─ correo: varchar (UNIQUE)
├─ comentarios: text
├─ tipo: varchar (niño, adulto, IMSS, etc.)
├─ origen: varchar (urgencias, externo, interno)
└─ [otros campos]

agenda_modalidades (tipos de estudios)
├─ id: int
├─ nombre: varchar ◄── MODALIDAD PARA WHATSAPP
└─ [otros campos]

portal_servicios (servicios específicos)
├─ id: int
├─ nombre: varchar
├─ descripcion: text ◄── INDICACIONES PARA WHATSAPP
├─ duracion_minutos: int
├─ modalidad_id: int
└─ [otros campos]

agenda_estado_cita (estados)
├─ id: int
├─ nombre: varchar (reservado, confirmado, asistió, etc.)
└─ [otros campos]
```

## 🔐 Seguridad

```
Credenciales:
├─ Variables de entorno (RECOMENDADO)
│  └─ WHATSAPP_ACCESS_TOKEN
│  └─ WHATSAPP_PHONE_NUMBER_ID
│  └─ WHATSAPP_BUSINESS_ACCOUNT_ID
├─ Archivo .env (NUNCA commitear)
└─ whatsapp_config.php (para fallback)

Protecciones:
├─ SSL/TLS obligatorio (requerido por Meta)
├─ Token validado en cada request
├─ Teléfono validado antes de enviar
├─ Logs registran intentos (sin credenciales)
├─ JSON_UNESCAPED_UNICODE para caracteres especiales
└─ Error handler captura excepciones

Logs NO incluyen:
├─ Access Token completo (solo primeros/últimos caracteres)
├─ Números de teléfono completos (en logs sensibles)
└─ Datos personales innecesarios
```

## 📈 Monitoreo

```
Logs generados en:
├─ /var/log/php-error.log (típicamente)
├─ sys_get_temp_dir() . '/agenda_whatsapp.log' (si existe)
└─ error_log() del sistema operativo

Buscar en logs:
├─ "Mensaje WhatsApp enviado" = Éxito ✓
├─ "Error al enviar WhatsApp" = Fallo ✗
├─ "WhatsApp no enviado" = No intentado (config falta)
└─ "Excepción al enviar WhatsApp" = Excepción no manejada

Métricas:
├─ Contador de mensajes enviados
├─ Contador de fallos
├─ Tiempo de respuesta API
└─ Códigos HTTP retornados por Meta
```

## 🧪 Pruebas Recomendadas

### 1. Prueba de Validación
```bash
php test_whatsapp.php
```

### 2. Prueba de Configuración
```bash
php -r "require 'includes/whatsapp_config.php'; var_dump(\$WHATSAPP_CONFIG);"
```

### 3. Prueba Manual desde Admin
- Agendar una cita desde index.php
- Verificar en logs
- Revisar que el paciente recibió el mensaje

### 4. Prueba Manual desde Cliente
- Completar reserva en reservar.php
- Recibir confirmación por email
- Recibir WhatsApp en teléfono registrado

## 📋 Checklist de Implementación

- [x] Crear archivo whatsapp_functions.php con todas las funciones
- [x] Crear archivo whatsapp_config.php con configuración
- [x] Integrar en guardar_cita.php
- [x] Integrar en guardar_reserva_cliente.php
- [x] Crear documentación WHATSAPP_INTEGRATION.md
- [x] Crear archivo .env.example
- [x] Crear script de prueba test_whatsapp.php
- [ ] Registrar plantilla "citaagendada" en Meta
- [ ] Obtener credenciales de API
- [ ] Configurar variables de entorno en servidor
- [ ] Pruebas en ambiente staging
- [ ] Pruebas en ambiente producción
- [ ] Monitoreo y logs activos

## 📞 Variables WhatsApp Mapeo Final

```
FROM guardar_cita.php:
├─ p.nombre . ' ' . p.apellido ............... {{1}}
├─ m.nombre (modalidad) ..................... {{2}}
├─ c.fecha (DATE, formato: "15 de noviembre") {{3}}
├─ c.hora_inicio (TIME, formato: "14:30") ... {{4}}
└─ s.descripcion (service description) ..... {{5}}

FROM guardar_reserva_cliente.php:
├─ p.nombre . ' ' . p.apellido ............... {{1}}
├─ m.nombre (modalidad) ..................... {{2}}
├─ c.fecha (DATE, formato: "15 de noviembre") {{3}}
├─ c.hora_inicio (TIME, formato: "14:30") ... {{4}}
└─ s.descripcion (service description) ..... {{5}}
```

---

**Documento preparado**: 14 de noviembre de 2025  
**Autor**: Sistema de Análisis de Citas  
**Versión**: 1.0
