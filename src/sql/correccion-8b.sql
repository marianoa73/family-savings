-- Aditiva e idempotente. No modifica estados ni saldos existentes.
CREATE TABLE IF NOT EXISTS {{P}}categorias_eventos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 categoria BIGINT UNSIGNED NOT NULL,
 actor BIGINT UNSIGNED NOT NULL,
 activa TINYINT UNSIGNED NOT NULL,
 fecha DATETIME NOT NULL,
 FOREIGN KEY(categoria) REFERENCES {{P}}categorias(id),
 FOREIGN KEY(actor) REFERENCES {{P}}usuarios(id),
 INDEX(categoria,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO {{P}}categorias(codigo,nombre,icono,tipo,grupo,sugerido,orden) VALUES
 ('retiro_compra','Comprar algo que quiero','🛍️','egreso','gasto',0,40),
 ('retiro_banco','Pasarlo a mi cuenta del banco','🏦','egreso','gasto',0,50);
INSERT INTO {{P}}config(clave,valor) VALUES ('migracion_c8b','1') ON DUPLICATE KEY UPDATE valor=VALUES(valor);
