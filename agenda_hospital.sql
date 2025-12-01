CREATE DATABASE agenda_hospital;
USE agenda_hospital;

-- Tabla agenda_usuarios
CREATE TABLE agenda_usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  correo VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  tipo ENUM('admin','caja','lectura') NOT NULL
);

-- Insertar usuario administrador por defecto
-- Usuario: admin@hospital.com
-- Contraseña: admin123
INSERT INTO agenda_usuarios (nombre, correo, password, tipo) VALUES 
('Administrador', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');


-- Tabla portal_pacientes
CREATE TABLE portal_pacientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100),
  telefono VARCHAR(20),
  correo VARCHAR(100),
  diagnostico VARCHAR(255),
  tipo VARCHAR(50),
  origen VARCHAR(100)
);

-- Insertar pacientes de prueba
INSERT INTO pacientes (nombre, apellido, telefono, correo, diagnostico, tipo, origen) VALUES
('Juan', 'Pérez', '625118881', 'juan.perez@email.com', 'Chequeo general', 'adulto', 'externo'),
('María', 'González', '625118882', 'maria.gonzalez@email.com', 'Dolor abdominal', 'adulto', 'urgencias'),
('Pedro', 'Martínez', '625118883', 'pedro.martinez@email.com', 'Fractura tobillo', 'adulto', 'urgencias'),
('Ana', 'López', '625118884', 'ana.lopez@email.com', 'Chequeo prenatal', 'adulto', 'externo'),
('Carlos', 'Rodríguez', '625118885', 'carlos.rodriguez@email.com', 'Examen cardiológico', 'adulto', 'interno');

-- Tabla agenda_profesionales
CREATE TABLE agenda_profesionales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  especialidad VARCHAR(100)
);

-- Tabla servicios
CREATE TABLE portal_servicios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  modalidad_id INT,
  FOREIGN KEY (modalidad_id) REFERENCES agenda_modalidades(id)
);

INSERT INTO portal_servicios (nombre, modalidad_id) VALUES
('Radiografía', 1),
('Radiografía', 2),
('Resonancia Magnética', 3),
('Tomografía', 4),
('Mastografía', 5),
('Sonografía', 6),
('Sonografía', 7),
('Biometría Hemática', 8),
('Química Sanguínea', 8),
('Examen General de Orina', 8),
('Perfil Lipídico', 8),
('Pruebas de Función Hepática', 8),
('Pruebas de Función Renal', 8),
('Hormonal Tiroideo', 8),
('Marcadores Tumorales', 8);

-- Tabla modalidades (recursos para el calendario)
CREATE TABLE agenda_modalidades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  descripcion TEXT
);

INSERT INTO agenda_modalidades (nombre) VALUES
('Radiografía Sala 1'),
('Radiografía Sala 2'),
('Resonancia Magnética'),
('Tomografía'),
('Mastografía'),
('Sonografía Sala 1'),
('Sonografía Sala 2'),
-- Modalidades de Laboratorios (sin control de salas específicas)
('Laboratorios');

-- Tabla agenda_paquetes
CREATE TABLE agenda_paquetes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(200) NOT NULL,
  descripcion TEXT,
  precio DECIMAL(10,2) NOT NULL,
  servicios_incluidos JSON,
  duracion_dias INT DEFAULT 30,
  activo BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar paquetes de ejemplo
INSERT INTO agenda_paquetes (nombre, descripcion, precio, servicios_incluidos, duracion_dias) VALUES
('Paquete Maternidad', 'Atención integral durante el embarazo', 12000.00, '["Consulta Prenatal", "Ultrasonido Obstétrico", "Laboratorios Maternales", "Atención del Parto"]', 270),
('Paquete Chequeo Ejecutivo', 'Chequeo médico completo con estudios de imagen y laboratorios', 8500.00, '["Consulta Médica", "Radiografía de Tórax", "Electrocardiograma", "Perfil Completo de Laboratorios"]', 365),
('Paquete Cesárea', 'Procedimiento de cesárea con hospitalización y medicamentos', 25000.00, '["Cesárea", "Hospitalización 3 días", "Medicamentos", "Consulta de seguimiento"]', 30),
('Paquete Cirugía General', 'Cirugía general ambulatoria con consultas de seguimiento', 18000.00, '["Consulta Preoperatoria", "Cirugía", "Medicamentos", "Consultas de Seguimiento"]', 60),
('Paquete Cirugía Cardiovascular', 'Procedimiento cardiovascular con hospitalización especializada', 45000.00, '["Estudios Preoperatorios", "Cirugía Cardiovascular", "Hospitalización", "Seguimiento Especializado"]', 90);

-- Tabla para ventas de paquetes
CREATE TABLE agenda_ventas_paquetes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  paciente_id INT,
  paquete_id INT,
  fecha_venta DATE NOT NULL,
  fecha_vencimiento DATE,
  precio_pagado DECIMAL(10,2),
  estado ENUM('activo', 'vencido', 'usado', 'cancelado') DEFAULT 'activo',
  notas TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (paciente_id) REFERENCES portal_pacientes(id),
  FOREIGN KEY (paquete_id) REFERENCES agenda_paquetes(id)
);

-- Tabla para ventas de servicios individuales
CREATE TABLE agenda_ventas_servicios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  paciente_id INT,
  servicio_id INT,
  cita_id INT NULL, -- Puede estar asociada a una cita o ser venta directa
  fecha_venta DATE NOT NULL,
  precio_pagado DECIMAL(10,2),
  descuento DECIMAL(5,2) DEFAULT 0.00, -- Porcentaje de descuento aplicado
  estado ENUM('pendiente', 'pagado', 'cancelado') DEFAULT 'pendiente',
  metodo_pago ENUM('efectivo', 'tarjeta', 'transferencia', 'cheque') DEFAULT 'efectivo',
  notas TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (paciente_id) REFERENCES portal_pacientes(id),
  FOREIGN KEY (servicio_id) REFERENCES portal_servicios(id),
  FOREIGN KEY (cita_id) REFERENCES agenda_citas(id)
);

-- Tabla estados de citas
CREATE TABLE agenda_estado_cita (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL
);

INSERT INTO agenda_estado_cita (id, nombre) VALUES
(1, 'reservado'),
(2, 'confirmado'),
(3, 'asistió'),
(4, 'no asistió'),
(5, 'pendiente'),
(6, 'en espera'),
(7, 'cancelada');
-- Tabla citas
CREATE TABLE agenda_citas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fecha DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  paciente_id INT,
  profesional_id INT,
  servicio_id INT,
  modalidad_id INT,
  estado_id INT DEFAULT 5,
  nota_paciente TEXT,
  nota_interna TEXT,
  tipo VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (paciente_id) REFERENCES portal_pacientes(id),
  FOREIGN KEY (profesional_id) REFERENCES agenda_profesionales(id),
  FOREIGN KEY (servicio_id) REFERENCES portal_servicios(id),
  FOREIGN KEY (modalidad_id) REFERENCES agenda_modalidades(id),
  FOREIGN KEY (estado_id) REFERENCES agenda_estado_cita(id)
);
