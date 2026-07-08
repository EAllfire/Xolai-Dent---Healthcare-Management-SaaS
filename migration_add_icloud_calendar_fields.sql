-- Agrega soporte para seleccionar el calendario de iCloud por nombre o href
ALTER TABLE agenda_usuarios
  ADD COLUMN icloud_calendar_name VARCHAR(255) NULL AFTER icloud_app_password,
  ADD COLUMN icloud_calendar_href VARCHAR(255) NULL AFTER icloud_calendar_name;
