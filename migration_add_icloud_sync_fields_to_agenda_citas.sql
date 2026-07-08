-- Agrega soporte de sincronización iCloud/Apple Calendar a agenda_citas
ALTER TABLE agenda_citas
  ADD COLUMN apple_event_id VARCHAR(255) NULL AFTER nota_interna,
  ADD COLUMN paciente_nombre_text VARCHAR(255) NULL AFTER apple_event_id,
  ADD COLUMN telefono_celular VARCHAR(255) NULL AFTER paciente_nombre_text,
  ADD COLUMN servicio_text VARCHAR(255) NULL AFTER telefono_celular;
