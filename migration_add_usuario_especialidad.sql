-- Migration: add especialidad column to agenda_usuarios
ALTER TABLE `agenda_usuarios` ADD COLUMN `especialidad` VARCHAR(64) DEFAULT NULL AFTER `tipo`;
