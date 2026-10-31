-- Sólo agrega estado. No procesa fotos antiguas durante la migración.
ALTER TABLE {{P}}notas_fotos ADD COLUMN IF NOT EXISTS procesada DATETIME NULL,
 ADD COLUMN IF NOT EXISTS reintentar TINYINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE {{P}}notas_eventos ADD COLUMN IF NOT EXISTS tipo VARCHAR(24) NOT NULL DEFAULT 'correccion';
INSERT INTO {{P}}config(clave,valor) VALUES ('migracion_c9','1') ON DUPLICATE KEY UPDATE valor=VALUES(valor);
