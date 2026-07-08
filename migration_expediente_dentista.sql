-- Crear tabla para el expediente dental
CREATE TABLE IF NOT EXISTS agenda_expediente_dentista (
    paciente_id INT PRIMARY KEY,
    tratamientos_json JSON,
    observaciones TEXT,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES portal_pacientes(id) ON DELETE CASCADE
);