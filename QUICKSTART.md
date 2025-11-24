# 🚀 INICIO RÁPIDO - WHATSAPP EN 5 PASOS

## 5️⃣ Pasos para Activar WhatsApp en 1 Hora

### Paso 1: Obtener Credenciales (30 minutos)

```bash
1. Ve a: https://developers.facebook.com/
2. Crea una app de negocio (Business)
3. Agrega WhatsApp como producto
4. Obtén 3 cosas:
   ✓ Access Token
   ✓ Phone Number ID
   ✓ Business Account ID
```

**Resultado esperado**: 3 valores copiados

---

### Paso 2: Configurar Sistema (5 minutos)

```bash
# En tu servidor, copia y edita:
cp .env.example .env

# Edita .env y agrega:
WHATSAPP_ACCESS_TOKEN=EAAxxx...
WHATSAPP_PHONE_NUMBER_ID=123456789
WHATSAPP_BUSINESS_ACCOUNT_ID=987654321
WHATSAPP_ENABLED=true

# Guarda y cierra
```

**Nota**: NO subas .env a git

---

### Paso 3: Registrar Plantilla (5 minutos + 2-24h espera)

```
1. Ve a: Meta Business Suite > WhatsApp
2. Plantillas > Crear
3. Nombre: citaagendada (EXACTO)
4. Idioma: es_MX
5. Categoría: APPOINTMENT_UPDATE
6. Body (contenido del mensaje):

   Hola {{1}},
   
   Tu cita de {{2}} ha sido agendada.
   
   📅 Fecha: {{3}}
   🕐 Hora: {{4}}
   📝 Indicaciones: {{5}}
   
   Recuerda llegar 10 minutos antes.
   
   Hospital Angeles

7. Enviar para aprobación
8. ESPERAR 2-24 horas
```

**Resultado esperado**: Estado "APPROVED" en Meta

---

### Paso 4: Validar Instalación (2 minutos)

```bash
# En tu servidor:
php test_whatsapp.php

# Debería mostrar:
✓ Funciones cargadas
✓ Configuración cargada
✓ Datos válidos
✓ Variables preparadas
✓ Mensaje enviado exitosamente (si tienes creds)
```

**Si ves errores**: Revisar README_WHATSAPP.md

---

### Paso 5: ¡Listo! Empezar a usar (automático)

```
A partir de ahora:
- Admin agenda cita en index.php → WhatsApp se envía ✓
- Cliente reserva en reservar.php → WhatsApp se envía ✓

¡Sin hacer nada más!
```

---

## ✅ Verificación Rápida

```bash
# Ver si está integrado:
grep "whatsapp" guardar_cita.php          # Debería mostrar líneas
grep "whatsapp" guardar_reserva_cliente   # Debería mostrar líneas

# Ver último envío:
tail -20 /var/log/php-error.log | grep -i whatsapp

# Validar sintaxis:
php -l includes/whatsapp_functions.php    # No errors
php -l includes/whatsapp_config.php       # No errors
```

---

## 📱 Ejemplo de Mensaje Que Recibe el Paciente

```
╔════════════════════════════════════════╗
║ HOSPITAL ANGELES                       ║
╠════════════════════════════════════════╣
║                                        ║
║ Hola Juan García,                      ║
║                                        ║
║ Tu cita de Radiografía ha sido         ║
║ agendada exitosamente.                 ║
║                                        ║
║ 📅 Fecha: 15 de noviembre de 2025      ║
║ 🕐 Hora: 14:30                         ║
║ 📝 Indicaciones: Estudios de tórax     ║
║ sin contraste                          ║
║                                        ║
║ Recuerda llegar 10 minutos antes.      ║
║                                        ║
║ Hospital Angeles                       ║
║                                        ║
╚════════════════════════════════════════╝
```

---

## 🆘 Troubleshooting Rápido

| Problema | Solución |
|----------|----------|
| "Config no disponible" | Verifica WHATSAPP_ACCESS_TOKEN en .env |
| "Plantilla no encontrada" | Nombre debe ser exactamente "citaagendada" |
| "Teléfono inválido" | Agregar código país (52 para México) |
| "Unauthorized" | Token expirado, renovar en Meta |
| No ve documentación | Ver DOCUMENTATION_INDEX.md |

---

## 📚 Si Necesitas Más Detalles

```
├─ Configuración completa → WHATSAPP_INTEGRATION.md
├─ Técnico/Arquitectura  → ANALISIS_DETALLADO.md
├─ Setup en Meta          → META_TEMPLATE_SETUP.md
├─ Desarrollo/Código      → DEVELOPER_GUIDE.md
└─ Índice de todo         → DOCUMENTATION_INDEX.md
```

---

## 💡 Notas Importantes

1. **Plantilla**: Debe estar "APPROVED" en Meta
2. **Teléfono**: Incluir código país (525551234567)
3. **Fecha**: Se formatea automáticamente
4. **Errores**: No impiden crear la cita
5. **Logs**: Ver en /var/log/php-error.log

---

## ✨ ¡Listo!

Si completaste todos los pasos:
✅ WhatsApp está activado
✅ Mensajes se envían automáticamente
✅ Sistema está monitoreable
✅ ¡Todo funciona!

**¿Preguntas?** → Leer README_WHATSAPP.md

---

**Creado**: 14 de noviembre de 2025
