-- MariaDB, idempotente. Los gastos antiguos pendientes se resuelven sin convertirlos.
CREATE TABLE IF NOT EXISTS {{P}}notas_fotos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 usuario BIGINT UNSIGNED NOT NULL, actor BIGINT UNSIGNED NOT NULL,
 archivo CHAR(37) NOT NULL UNIQUE, ancho SMALLINT UNSIGNED NOT NULL, alto SMALLINT UNSIGNED NOT NULL, fecha DATETIME NOT NULL,
 FOREIGN KEY(usuario) REFERENCES {{P}}usuarios(id), FOREIGN KEY(actor) REFERENCES {{P}}usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}notas_fotos_intentos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor BIGINT UNSIGNED NOT NULL, fecha DATETIME NOT NULL,
 INDEX(actor,fecha), FOREIGN KEY(actor) REFERENCES {{P}}usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE {{P}}movimientos ADD COLUMN IF NOT EXISTS fecha_nota DATE NULL,
 ADD COLUMN IF NOT EXISTS tipo_nota VARCHAR(30) NULL,
 ADD COLUMN IF NOT EXISTS foto_nota BIGINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS grado_original INT NULL,
 ADD COLUMN IF NOT EXISTS correccion_de BIGINT UNSIGNED NULL UNIQUE;
ALTER TABLE {{P}}movimientos ADD FOREIGN KEY IF NOT EXISTS {{P}}fk_nota_foto (foto_nota) REFERENCES {{P}}notas_fotos(id);
ALTER TABLE {{P}}movimientos ADD FOREIGN KEY IF NOT EXISTS {{P}}fk_nota_correccion (correccion_de) REFERENCES {{P}}movimientos(id);
CREATE TABLE IF NOT EXISTS {{P}}notas_eventos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 movimiento BIGINT UNSIGNED NOT NULL, nuevo BIGINT UNSIGNED NULL,
 actor BIGINT UNSIGNED NOT NULL, fecha DATETIME NOT NULL, motivo VARCHAR(230) NOT NULL,
 anterior LONGTEXT NOT NULL, actual LONGTEXT NOT NULL,
 INDEX(movimiento), INDEX(nuevo),
 FOREIGN KEY(movimiento) REFERENCES {{P}}movimientos(id), FOREIGN KEY(nuevo) REFERENCES {{P}}movimientos(id),
 FOREIGN KEY(actor) REFERENCES {{P}}usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO {{P}}config(clave,valor) VALUES ('migracion_c8','1');
