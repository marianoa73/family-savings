-- MariaDB. Sustituir {{P}} por DB_PREFIJO validado. No cambia elecciones ya guardadas.
ALTER TABLE {{P}}usuarios ADD COLUMN IF NOT EXISTS tema ENUM('claro','oscuro') NOT NULL DEFAULT 'claro';
