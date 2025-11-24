# ✅ CHECKLIST DE IMPLEMENTACIÓN WHATSAPP

## 📋 Fase 1: Preparación (COMPLETADA)

### Análisis
- [x] Revisar código actual de guardar_cita.php
- [x] Revisar código actual de guardar_reserva_cliente.php
- [x] Mapear variables necesarias para WhatsApp
- [x] Identificar puntos de integración
- [x] Documentar flujos existentes

### Desarrollo
- [x] Crear whatsapp_functions.php
- [x] Crear whatsapp_config.php
- [x] Implementar validaciones
- [x] Implementar envío via Meta API
- [x] Integrar en guardar_cita.php
- [x] Integrar en guardar_reserva_cliente.php
- [x] Agregar manejo de errores

### Testing Inicial
- [x] Revisar sintaxis de PHP
- [x] Validar funciones principales
- [x] Crear script de prueba (test_whatsapp.php)

### Documentación
- [x] Crear WHATSAPP_INTEGRATION.md
- [x] Crear ANALISIS_DETALLADO.md
- [x] Crear META_TEMPLATE_SETUP.md
- [x] Crear RESUMEN_IMPLEMENTACION.md
- [x] Crear README_WHATSAPP.md
- [x] Crear DEVELOPER_GUIDE.md
- [x] Crear .env.example

---

## 🔧 Fase 2: Configuración (ACCIÓN REQUERIDA)

### Credenciales Meta
- [ ] Crear/acceder a cuenta Facebook Developers
- [ ] Crear app Business o usar existente
- [ ] Agregar WhatsApp como producto
- [ ] Configurar WhatsApp Business Account
- [ ] Obtener **Access Token**
- [ ] Obtener **Phone Number ID**
- [ ] Obtener **Business Account ID**
- [ ] Guardar credenciales en lugar seguro

### Plantilla WhatsApp
- [ ] Ir a Meta Business Suite > WhatsApp
- [ ] Crear nueva plantilla
  - [ ] Nombre: `citaagendada` (EXACTO)
  - [ ] Idioma: `es_MX` (Español México)
  - [ ] Categoría: `APPOINTMENT_UPDATE`
  - [ ] 5 parámetros: {{1}} a {{5}}
  - [ ] Incluir header (opcional)
  - [ ] Incluir body (requerido)
  - [ ] Incluir footer (opcional)
  - [ ] Incluir botones (opcional)
- [ ] Enviar para aprobación
- [ ] Esperar aprobación (2-24 horas)
- [ ] Verificar estado = APPROVED

### Configuración Local
- [ ] Copiar .env.example a .env
- [ ] Agregar WHATSAPP_ACCESS_TOKEN
- [ ] Agregar WHATSAPP_PHONE_NUMBER_ID
- [ ] Agregar WHATSAPP_BUSINESS_ACCOUNT_ID (opcional)
- [ ] Establecer WHATSAPP_ENABLED=true
- [ ] NO subir .env a git

---

## 🧪 Fase 3: Testing (ACCIÓN REQUERIDA)

### Test de Configuración
- [ ] Ejecutar: `php test_whatsapp.php`
- [ ] Verificar: "✓ Configuración cargada"
- [ ] Verificar: "✓ Datos válidos"
- [ ] Verificar: "✓ Variables preparadas"

### Test de Validaciones
```bash
# Test de teléfono válido
php -r "require 'includes/whatsapp_functions.php'; var_dump(validarDatosWhatsApp('5215551234567', 'Juan', 'Radiografía', '2025-11-20', '14:30', 'Test'));"

# Test de teléfono inválido
php -r "require 'includes/whatsapp_functions.php'; var_dump(validarDatosWhatsApp('123', 'Juan', 'Radiografía', '2025-11-20', '14:30', 'Test'));"

# Test de fecha válida
php -r "require 'includes/whatsapp_functions.php'; var_dump(validarDatosWhatsApp('5215551234567', 'Juan', 'Radiografía', '2025-11-20', '14:30', 'Test'));"

# Test de fecha pasada
php -r "require 'includes/whatsapp_functions.php'; var_dump(validarDatosWhatsApp('5215551234567', 'Juan', 'Radiografía', '2025-01-01', '14:30', 'Test'));"
```

### Test Manual en Dev
- [ ] Agendar cita en index.php (admin)
- [ ] Revisar logs: "Mensaje WhatsApp enviado" o error
- [ ] Si error: Revisar causa en logs

### Test Manual en Staging
- [ ] Registrar cita en reservar.php (cliente)
- [ ] Revisar logs
- [ ] Verificar que paciente recibió WhatsApp

### Test en Producción
- [ ] Agendar cita real en admin
- [ ] Enviar teléfono real de paciente
- [ ] Paciente recibe WhatsApp
- [ ] Validar formato y variables correctas
- [ ] Revisar logs no hay errores

---

## 🚀 Fase 4: Deployment (ACCIÓN REQUERIDA)

### Ambiente Staging
- [ ] Copiar archivos nuevos a staging
  - [ ] /includes/whatsapp_functions.php
  - [ ] /includes/whatsapp_config.php
  - [ ] test_whatsapp.php
- [ ] Copiar archivos modificados
  - [ ] guardar_cita.php
  - [ ] guardar_reserva_cliente.php
- [ ] Copiar documentación
  - [ ] *.md files
  - [ ] .env.example
- [ ] Configurar .env en staging
- [ ] Ejecutar test_whatsapp.php
- [ ] Testing de flujos (admin + cliente)

### Ambiente Producción
- [ ] Backup de código actual
- [ ] Copiar archivos nuevos a producción
  - [ ] /includes/whatsapp_functions.php
  - [ ] /includes/whatsapp_config.php
- [ ] Copiar archivos modificados
  - [ ] guardar_cita.php
  - [ ] guardar_reserva_cliente.php
- [ ] Configurar variables de entorno en servidor
  - [ ] WHATSAPP_ACCESS_TOKEN
  - [ ] WHATSAPP_PHONE_NUMBER_ID
  - [ ] WHATSAPP_BUSINESS_ACCOUNT_ID
  - [ ] WHATSAPP_ENABLED=true
- [ ] NO copiar .env (usar variables de servidor)
- [ ] Verificar permisos de archivos
- [ ] Reiniciar PHP-FPM/Apache si es necesario

### Post-Deployment
- [ ] Monitorear logs: `tail -f /var/log/php-error.log | grep -i whatsapp`
- [ ] Agendar cita de prueba en producción
- [ ] Verificar WhatsApp se envió
- [ ] Avisar al team que está activo

---

## 📊 Fase 5: Monitoreo Continuo (CONTINUO)

### Diario
- [ ] Revisar logs de WhatsApp 1 vez
- [ ] Verificar no hay errores recurrentes
- [ ] Revisar tasa de éxito/error

### Semanal
- [ ] Analizar patrones de errores
- [ ] Verificar tokens no expiraron
- [ ] Revisar límites de API no superados

### Mensual
- [ ] Reporte de mensajes enviados
- [ ] Verificar plantilla está aprobada
- [ ] Revisar costos de Meta

### Trimestralmente
- [ ] Auditoría de seguridad
- [ ] Revisión de credenciales
- [ ] Evaluación de performance

---

## 🆘 Troubleshooting Rápido

| Síntoma | Verificar | Solucionar |
|---------|-----------|-----------|
| WhatsApp no envía | Logs de error | Revisar WHATSAPP_ACCESS_TOKEN |
| "Plantilla no encontrada" | Nombre plantilla en Meta | Verificar nombre = "citaagendada" |
| "Unauthorized" | Token de acceso | Renovar token en Meta |
| "Teléfono inválido" | Formato de entrada | Agregar código país (52) |
| Error 400 | Variables | Validar formato de fecha |
| Sin respuesta API | Red/firewall | Verificar SSL y conexión |

---

## 📝 Comandos Útiles

```bash
# Ver últimos 20 envíos WhatsApp
grep "Mensaje WhatsApp" /var/log/php-error.log | tail -20

# Contar envíos exitosos hoy
grep "Mensaje WhatsApp enviado" /var/log/php-error.log | grep "$(date +'%Y-%m-%d')" | wc -l

# Contar errores hoy
grep "Error al enviar WhatsApp" /var/log/php-error.log | grep "$(date +'%Y-%m-%d')" | wc -l

# Ver errores específicos
grep "Error al enviar WhatsApp" /var/log/php-error.log | tail -10

# Test de configuración
php test_whatsapp.php

# Validar sintaxis
php -l includes/whatsapp_functions.php
php -l includes/whatsapp_config.php

# Ver status de plantilla
# (Hacer login en Meta Business y revisar)
```

---

## 🎯 Metas y KPIs

### Corto Plazo (Primer mes)
- [x] Implementación completa
- [ ] 100+ citas agendadas con WhatsApp
- [ ] Tasa de error < 5%
- [ ] Pacientes reportan recibir mensajes

### Mediano Plazo (Trimestre)
- [ ] 1000+ citas enviadas por WhatsApp
- [ ] Tasa de error < 2%
- [ ] Feedback positivo de usuarios
- [ ] Reducción de llamadas de confirmación

### Largo Plazo (Año)
- [ ] 10000+ citas
- [ ] Tasa de error < 1%
- [ ] Expansión a otras notificaciones (recordatorios, etc.)
- [ ] Integración con WhatsApp Business API oficial

---

## 📞 Contactos y Recursos

### Meta Support
- WhatsApp Business API: https://www.whatsapp.com/business/
- Developers: https://developers.facebook.com/docs/whatsapp
- Support: https://www.facebook.com/help/contact/251638379677976

### Equipo Interno
- **DevOps**: [Configurar servidor + variables]
- **QA**: [Testing y validación]
- **Admin**: [Monitoreo diario]
- **Desarrollo**: [Mantenimiento código]

---

## ✨ Status General

```
IMPLEMENTACIÓN: ✅ COMPLETA
DOCUMENTACIÓN: ✅ COMPLETA
TESTING: ⏳ PENDIENTE (requiere credenciales)
DEPLOYMENT: ⏳ PENDIENTE (requiere credenciales)
MONITOREO: ⏳ PENDIENTE (post-deployment)
```

---

## 🎓 Notas Importantes

1. **Plantilla DEBE ser registrada en Meta** - Sin esto, no funciona
2. **Credenciales NUNCA en código** - Usar variables de entorno
3. **WhatsApp NO bloquea la cita** - Si falla, se crea igual
4. **Revisar logs regularmente** - Detectar problemas temprano
5. **Token expira** - Renovar antes de expiración
6. **Costo bajo** - Meta cobra ~$0.0045 por mensaje

---

## 🚀 Siguiente Paso

**Acción inmediata**: Obtener credenciales de Meta (30 min)

```
1. Ve a https://developers.facebook.com/
2. Crea app o usa existente
3. Agrega WhatsApp
4. Obtén credenciales
5. Pasa a Fase 2: Configuración
```

---

**Checklist Versión**: 1.0  
**Última Actualización**: 14 de noviembre de 2025  
**Completitud**: 50% (Fase 1 y 2)  
**Siguiente Fase**: Configuración de credenciales
