-- sql/verificacion.sql — verificación de correo electrónico.
--
-- NO hace falta correr esto a mano: el sistema crea la tabla y la columna solo
-- (includes/verificacion.php lo hace en el primer request). Este archivo está
-- por si el usuario de la base no tiene permiso para CREATE/ALTER y preferís
-- hacerlo desde phpMyAdmin. Correlo UNA sola vez.

CREATE TABLE IF NOT EXISTS email_verifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  email VARCHAR(191) NOT NULL,
  token_hash CHAR(64) NOT NULL,   -- sha256 del token del link
  code_hash CHAR(64) NOT NULL,    -- sha256 del código de 6 dígitos
  intentos TINYINT NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_token (token_hash),
  KEY idx_verif_user (user_id),
  KEY idx_verif_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Columna que marca si la cuenta está activada (NULL = sin verificar).
ALTER TABLE users ADD COLUMN email_verified_at DATETIME DEFAULT NULL;

-- Las cuentas que YA existían se dan por verificadas, así nadie que hoy
-- puede entrar se queda afuera.
UPDATE users SET email_verified_at = NOW() WHERE email_verified_at IS NULL;
