-- Migration: add imagen column to agenda_modalidades
ALTER TABLE agenda_modalidades
  ADD COLUMN imagen VARCHAR(255) DEFAULT NULL AFTER descripcion;

-- Optional: set some default images (update paths after uploading files)
-- UPDATE agenda_modalidades SET imagen = 'images/modalidades/default.png' WHERE imagen IS NULL;
