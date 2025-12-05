-- Add the estado_id column to the portal_pacientes table
ALTER TABLE portal_pacientes ADD COLUMN estado_id INT NULL;

-- Add a foreign key constraint to link to the agenda_tipos_paciente table
-- This ensures data integrity. When a tipo_paciente is deleted, the corresponding
-- patient's estado_id will be set to NULL.
ALTER TABLE portal_pacientes 
ADD CONSTRAINT fk_paciente_tipo 
FOREIGN KEY (estado_id) 
REFERENCES agenda_tipos_paciente(id)
ON DELETE SET NULL;

-- Note: You may want to run an UPDATE query to populate the new 'estado_id' column
-- for existing patients based on the values in the old 'tipo' column.
-- Example: UPDATE portal_pacientes SET estado_id = (SELECT id FROM agenda_tipos_paciente WHERE nombre = 'Particular') WHERE tipo = 'adulto';
