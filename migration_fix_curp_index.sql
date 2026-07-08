-- Corrección para el índice único del CURP
-- Objetivo: Permitir que un mismo CURP exista para diferentes médicos, pero no para el mismo.

-- Paso 1: Eliminar el índice único existente que solo cubre la columna `curp`.
-- El nombre 'idx_curp_unique' se obtuvo del mensaje de error. Si tu índice tiene otro nombre, ajústalo.
ALTER TABLE portal_pacientes DROP INDEX idx_curp_unique;

-- Paso 2: Crear un nuevo índice único compuesto que incluye `usuario_id` y `curp`.
-- Esto asegura que la combinación de (médico, CURP) sea única.
-- MySQL permite múltiples filas con `usuario_id` NULO y el mismo CURP, lo cual es correcto para este caso.
ALTER TABLE portal_pacientes ADD UNIQUE INDEX idx_curp_por_usuario (usuario_id, curp);