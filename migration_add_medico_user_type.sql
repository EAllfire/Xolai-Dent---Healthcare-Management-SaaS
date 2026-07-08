-- Agrega el tipo de usuario 'medico' a la tabla agenda_usuarios
ALTER TABLE agenda_usuarios MODIFY COLUMN tipo ENUM('admin', 'caja', 'lectura', 'medico') NOT NULL;