-- Sustituir {{P}} por el DB_PREFIJO validado. Ejecutar después de esquema.sql.
-- Idempotente y aditiva: ningún saldo previo cambia, no se borra historial.
CREATE TABLE IF NOT EXISTS {{P}}fondo_cerrojo (
 id TINYINT UNSIGNED PRIMARY KEY CHECK(id=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO {{P}}fondo_cerrojo VALUES (1);
CREATE TABLE IF NOT EXISTS {{P}}fondo_movimientos (
 movimiento BIGINT UNSIGNED PRIMARY KEY,
 delta INT NOT NULL,
 FOREIGN KEY(movimiento) REFERENCES {{P}}movimientos(id),
 CHECK(delta BETWEEN -10000000 AND 10000000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO {{P}}categorias(codigo,nombre,icono,tipo,grupo,sugerido,orden) VALUES
 ('devaluacion','Devaluación','⚖️','egreso','admin',0,54),
 ('devolucion_demerito','Devolución de demérito','↩️','ingreso','admin',0,55),
 ('demerito_tutor','Demérito del tutor','🤝','ingreso','admin',0,56);
