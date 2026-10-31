-- MariaDB. Reemplazar {{P}} por el DB_PREFIJO validado. Reaplicable sin borrar historial.
ALTER TABLE {{P}}metas ADD COLUMN IF NOT EXISTS borrado_por BIGINT UNSIGNED NULL,
 ADD COLUMN IF NOT EXISTS borrada DATETIME NULL,
 ADD COLUMN IF NOT EXISTS estado_fecha DATETIME NULL;
ALTER TABLE {{P}}metas MODIFY COLUMN estado ENUM('activa','por_aprobar','cumplida','archivada','rescate_pedido','rescatada','borrada') NOT NULL DEFAULT 'activa';
UPDATE {{P}}metas SET estado='borrada',borrada=COALESCE(borrada,UTC_TIMESTAMP()),estado_fecha=COALESCE(estado_fecha,UTC_TIMESTAMP()) WHERE estado='archivada';
UPDATE {{P}}metas SET estado='activa',estado_fecha=COALESCE(estado_fecha,UTC_TIMESTAMP()) WHERE estado='por_aprobar';
ALTER TABLE {{P}}metas MODIFY COLUMN estado ENUM('activa','cumplida','rescate_pedido','rescatada','borrada') NOT NULL DEFAULT 'activa';
ALTER TABLE {{P}}rescates ADD COLUMN IF NOT EXISTS meta BIGINT UNSIGNED NULL;
ALTER TABLE {{P}}rescates ADD FOREIGN KEY IF NOT EXISTS {{P}}fk_rescate_meta (meta) REFERENCES {{P}}metas(id);
ALTER TABLE {{P}}metas ADD FOREIGN KEY IF NOT EXISTS {{P}}fk_meta_borrador (borrado_por) REFERENCES {{P}}usuarios(id);
CREATE TABLE IF NOT EXISTS {{P}}efectivo_movimientos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 usuario BIGINT UNSIGNED NOT NULL, tipo ENUM('ingreso','egreso') NOT NULL,
 monto INT UNSIGNED NOT NULL CHECK(monto BETWEEN 1 AND 10000000),
 nota VARCHAR(230) NOT NULL, fecha DATETIME NOT NULL, creado_por BIGINT UNSIGNED NOT NULL,
 anulado_por BIGINT UNSIGNED NULL, anulado DATETIME NULL,
 inverso_de BIGINT UNSIGNED NULL UNIQUE, inverso_id BIGINT UNSIGNED NULL UNIQUE,
 INDEX(usuario,fecha),
 FOREIGN KEY(usuario) REFERENCES {{P}}usuarios(id), FOREIGN KEY(creado_por) REFERENCES {{P}}usuarios(id),
 FOREIGN KEY(anulado_por) REFERENCES {{P}}usuarios(id),
 FOREIGN KEY(inverso_de) REFERENCES {{P}}efectivo_movimientos(id), FOREIGN KEY(inverso_id) REFERENCES {{P}}efectivo_movimientos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}avatares (
 usuario BIGINT UNSIGNED PRIMARY KEY, archivo CHAR(37) NOT NULL, actualizado DATETIME NOT NULL,
 FOREIGN KEY(usuario) REFERENCES {{P}}usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}avatares_eventos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario BIGINT UNSIGNED NOT NULL,
 accion ENUM('intento','subir','quitar') NOT NULL, fecha DATETIME NOT NULL,
 INDEX(usuario,accion,fecha), FOREIGN KEY(usuario) REFERENCES {{P}}usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO {{P}}config(clave,valor) VALUES ('migracion_c7','1');
