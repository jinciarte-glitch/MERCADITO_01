-- sql/mensajes-eliminados.sql — "Mensaje eliminado" estilo WhatsApp.
--
-- NO hace falta correrlo a mano: includes/bootstrap.php crea la columna sola
-- en el primer request a la API. Este archivo es por si el usuario de la base
-- no tiene permiso de ALTER y preferís hacerlo en phpMyAdmin.
-- Correlo UNA sola vez.

ALTER TABLE messages ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL;
