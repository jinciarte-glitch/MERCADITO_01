-- sql/zona.sql — filtro por zona en el feed (estilo Marketplace).
--
-- NO hace falta correrlo a mano: includes/bootstrap.php crea estas columnas
-- e índice solo en el primer request a la API. Este archivo es por si el
-- usuario de la base no tiene permiso de ALTER y preferís hacerlo en phpMyAdmin.
-- Correlo UNA sola vez.

ALTER TABLE posts ADD COLUMN lat DECIMAL(10,7) NULL DEFAULT NULL AFTER image_url;
ALTER TABLE posts ADD COLUMN lng DECIMAL(10,7) NULL DEFAULT NULL AFTER lat;
ALTER TABLE posts ADD COLUMN place_name VARCHAR(255) NULL DEFAULT NULL AFTER lng;
ALTER TABLE posts ADD INDEX idx_posts_geo (lat, lng);
