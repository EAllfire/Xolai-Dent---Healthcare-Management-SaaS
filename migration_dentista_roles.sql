-- Añadir columna para jerarquía de usuarios
ALTER TABLE agenda_usuarios ADD COLUMN id_padre INT NULL DEFAULT NULL AFTER tipo;

-- Crear la relación de clave foránea
ALTER TABLE agenda_usuarios 
ADD CONSTRAINT fk_usuario_padre FOREIGN KEY (id_padre) REFERENCES agenda_usuarios(id) ON DELETE SET NULL;