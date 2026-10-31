-- MariaDB 10.11. Reemplazar {{P}} por DB_PREFIJO validado (o vacío).
-- La aplicación ejecuta este mismo archivo. No contiene usuarios.
CREATE TABLE IF NOT EXISTS {{P}}usuarios (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(40) NOT NULL UNIQUE,
 rol ENUM('admin','nino') NOT NULL, pin_hash VARCHAR(255) NOT NULL, mail VARCHAR(120) NULL,
 activo TINYINT NOT NULL DEFAULT 1, debe_cambiar TINYINT NOT NULL DEFAULT 0,
 fallos INT NOT NULL DEFAULT 0, ventana DATETIME NULL, bloqueo DATETIME NULL,
 mensualidad INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}sesiones (
 token_hash CHAR(64) PRIMARY KEY, usuario BIGINT UNSIGNED NOT NULL, visto DATETIME NOT NULL,
 INDEX(usuario), FOREIGN KEY(usuario) REFERENCES {{P}}usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}limites (
 ip_hash CHAR(64) PRIMARY KEY, fallos INT NOT NULL DEFAULT 0, ventana DATETIME NULL, bloqueo DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}accesos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario BIGINT UNSIGNED NULL, tipo VARCHAR(30) NOT NULL,
 fecha DATETIME NOT NULL, ip_hash CHAR(64) NOT NULL, dispositivo VARCHAR(60) NOT NULL,
 INDEX(usuario,fecha), INDEX(tipo,fecha), INDEX(ip_hash,fecha),
 FOREIGN KEY(usuario) REFERENCES {{P}}usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}recuperaciones (
 token_hash CHAR(64) PRIMARY KEY, usuario BIGINT UNSIGNED NOT NULL, creado DATETIME NOT NULL,
 vence DATETIME NOT NULL, usado DATETIME NULL, INDEX(usuario,creado),
 FOREIGN KEY(usuario) REFERENCES {{P}}usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}categorias (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(40) NOT NULL UNIQUE,
 nombre VARCHAR(100) NOT NULL, icono VARCHAR(20) NOT NULL,
 tipo ENUM('ingreso','egreso') NOT NULL, grupo ENUM('ingreso','tarea','gasto','admin') NOT NULL,
 sugerido INT UNSIGNED NOT NULL DEFAULT 0, activa TINYINT NOT NULL DEFAULT 1, orden INT NOT NULL DEFAULT 0,
 CHECK(sugerido <= 10000000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}sugerencias (
 usuario BIGINT UNSIGNED NOT NULL, categoria BIGINT UNSIGNED NOT NULL, monto INT UNSIGNED NOT NULL,
 PRIMARY KEY(usuario,categoria), FOREIGN KEY(usuario) REFERENCES {{P}}usuarios(id),
 FOREIGN KEY(categoria) REFERENCES {{P}}categorias(id), CHECK(monto <= 10000000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}premios (nota INT PRIMARY KEY, monto INT UNSIGNED NOT NULL, CHECK(monto <= 10000000))
 ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}config (clave VARCHAR(40) PRIMARY KEY, valor VARCHAR(100) NOT NULL)
 ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}envios (
 token CHAR(64) PRIMARY KEY, usuario BIGINT UNSIGNED NOT NULL, fecha DATETIME NOT NULL,
 FOREIGN KEY(usuario) REFERENCES {{P}}usuarios(id), INDEX(fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}rescates (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario BIGINT UNSIGNED NOT NULL, monto INT UNSIGNED NOT NULL,
 motivo VARCHAR(100) NOT NULL, nota VARCHAR(300) NOT NULL DEFAULT '',
 estado ENUM('pendiente','aprobado','entregado','rechazado','cancelado') NOT NULL DEFAULT 'pendiente',
 fecha DATETIME NOT NULL, creado_por BIGINT UNSIGNED NOT NULL, decidido_por BIGINT UNSIGNED NULL,
 aprobado_por BIGINT UNSIGNED NULL, aprobado DATETIME NULL,
 decidido DATETIME NULL, entregado_por BIGINT UNSIGNED NULL, entregado DATETIME NULL, medio VARCHAR(40) NULL,
 INDEX(usuario,estado), FOREIGN KEY(usuario) REFERENCES {{P}}usuarios(id),
 FOREIGN KEY(creado_por) REFERENCES {{P}}usuarios(id), FOREIGN KEY(decidido_por) REFERENCES {{P}}usuarios(id),
 FOREIGN KEY(entregado_por) REFERENCES {{P}}usuarios(id), FOREIGN KEY(aprobado_por) REFERENCES {{P}}usuarios(id),
 CHECK(monto BETWEEN 1 AND 10000000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}movimientos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario BIGINT UNSIGNED NOT NULL,
 tipo ENUM('ingreso','egreso') NOT NULL, monto INT UNSIGNED NOT NULL, categoria BIGINT UNSIGNED NULL,
 concepto VARCHAR(240) NOT NULL, icono VARCHAR(20) NOT NULL, nota VARCHAR(400) NOT NULL DEFAULT '',
 grado INT NULL, materia VARCHAR(80) NULL,
 estado ENUM('pendiente','confirmado','rechazado','anulado') NOT NULL,
 fecha DATETIME NOT NULL, creado_por BIGINT UNSIGNED NOT NULL, confirmado_por BIGINT UNSIGNED NULL,
 confirmado DATETIME NULL, anulado_por BIGINT UNSIGNED NULL, anulado DATETIME NULL,
 motivo_anulacion VARCHAR(300) NULL, inverso_de BIGINT UNSIGNED NULL UNIQUE, inverso_id BIGINT UNSIGNED NULL UNIQUE,
 rescate BIGINT UNSIGNED NULL UNIQUE,
 INDEX(usuario,fecha), INDEX(estado), FOREIGN KEY(usuario) REFERENCES {{P}}usuarios(id),
 FOREIGN KEY(categoria) REFERENCES {{P}}categorias(id), FOREIGN KEY(creado_por) REFERENCES {{P}}usuarios(id),
 FOREIGN KEY(confirmado_por) REFERENCES {{P}}usuarios(id), FOREIGN KEY(anulado_por) REFERENCES {{P}}usuarios(id),
 FOREIGN KEY(rescate) REFERENCES {{P}}rescates(id), FOREIGN KEY(inverso_de) REFERENCES {{P}}movimientos(id),
 FOREIGN KEY(inverso_id) REFERENCES {{P}}movimientos(id), CHECK(monto <= 10000000),
 CHECK(monto > 0 OR (grado IS NOT NULL AND grado < 7))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS {{P}}metas (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario BIGINT UNSIGNED NOT NULL, nombre VARCHAR(80) NOT NULL,
 monto INT UNSIGNED NOT NULL, estado ENUM('activa','por_aprobar','cumplida','archivada') NOT NULL DEFAULT 'activa',
 creado_por BIGINT UNSIGNED NOT NULL, fecha DATETIME NOT NULL, revisado_por BIGINT UNSIGNED NULL, revisado DATETIME NULL, INDEX(usuario,estado),
 FOREIGN KEY(usuario) REFERENCES {{P}}usuarios(id), FOREIGN KEY(creado_por) REFERENCES {{P}}usuarios(id),
 CHECK(monto BETWEEN 1 AND 10000000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO {{P}}config VALUES ('interes','0');
INSERT IGNORE INTO {{P}}premios VALUES (10,20000),(9,10000),(8,5000),(7,2500),(6,0),(5,0),(4,0),(3,0),(2,0),(1,0),(0,0);
INSERT IGNORE INTO {{P}}categorias(codigo,nombre,icono,tipo,grupo,sugerido,orden) VALUES
 ('nota','Nota del colegio','📚','ingreso','ingreso',0,1),
 ('mensualidad','Mensualidad para gastos','📅','ingreso','ingreso',0,2),
 ('cumple','Cumpleaños','🎂','ingreso','ingreso',0,3),
 ('regalo','Regalo','🎁','ingreso','ingreso',0,4),
 ('nino','Día del Niño','🎈','ingreso','ingreso',0,5),
 ('navidad','Navidad','🎄','ingreso','ingreso',0,6),
 ('reyes','Reyes','👑','ingreso','ingreso',0,7),
 ('pascua','Pascua','🐣','ingreso','ingreso',0,8),
 ('raton','Ratón Pérez','🦷','ingreso','ingreso',0,9),
 ('logro','Logro o premio','🏆','ingreso','ingreso',0,10),
 ('venta','Venta de algo propio','🏷️','ingreso','ingreso',0,11),
 ('interes','Interés por ahorrar','🌱','ingreso','ingreso',0,12),
 ('t01','Tender la cama toda la semana','🛏️','ingreso','tarea',1000,21),
 ('t02','Sacar la basura toda la semana','🗑️','ingreso','tarea',1000,22),
 ('t03','Poner y levantar la mesa toda la semana','🍽️','ingreso','tarea',1500,23),
 ('t04','Ordenar su cuarto','🧹','ingreso','tarea',2000,24),
 ('t05','Lavar los platos','🍽️','ingreso','tarea',2000,25),
 ('t06','Alimentar a la mascota toda la semana','🐾','ingreso','tarea',2000,26),
 ('t07','Ayudar con las compras','🛒','ingreso','tarea',2500,27),
 ('t08','Regar plantas y jardín','🌿','ingreso','tarea',3000,28),
 ('t09','Barrer y limpiar el patio','🧹','ingreso','tarea',3000,29),
 ('t10','Ordenar placard o juguetes (y separar lo que no usa)','🧸','ingreso','tarea',3000,30),
 ('t11','Lavar el auto','🚗','ingreso','tarea',5000,31),
 ('t12','Ayudar en el taller','🔧','ingreso','tarea',5000,32),
 ('t13','Cortar el pasto','🌱','ingreso','tarea',7000,33),
 ('t14','Limpieza a fondo (garaje, galpón)','🧽','ingreso','tarea',10000,34),
 ('kiosco','Kiosco/golosinas','🍬','egreso','gasto',0,41),
 ('juguete','Juguete','🧸','egreso','gasto',0,42),
 ('salida','Salida con amigos','🎟️','egreso','gasto',0,43),
 ('juego','Videojuego/apps','🎮','egreso','gasto',0,44),
 ('ropa','Ropa/zapatillas','👟','egreso','gasto',0,45),
 ('utiles','Útiles/colegio','✏️','egreso','gasto',0,46),
 ('regalar','Regalo para alguien','🎁','egreso','gasto',0,47),
 ('comida','Comida','🍎','egreso','gasto',0,48),
 ('otro','Otro','📝','egreso','gasto',0,49),
 ('compra','Compra por pedido del usuario','🛍️','egreso','admin',0,51),
 ('donacion','Donación','🤝','egreso','admin',0,52),
 ('correccion','Corrección','📝','egreso','admin',0,53);
