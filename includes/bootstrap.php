<?php
// includes/bootstrap.php — se incluye al principio de cada endpoint de la API.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/migraciones.php';
header('Content-Type: application/json; charset=utf-8');

// Crea automáticamente las tablas y columnas que todavía no existan, así no
// hace falta correr SQL a mano en el hosting. Corre UNA sola vez: al terminar
// bien deja una marca (ver includes/migraciones.php) y los demás requests
// la saltean.
// Versión de las migraciones de este archivo: si agregás una tabla o columna
// nueva acá abajo, subí este número para que se vuelva a correr una vez.
if (!migracion_hecha('base', 1)) {
$migracionOk = true;
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS blocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        blocker_id INT NOT NULL,
        blocked_id INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_block (blocker_id, blocked_id),
        KEY idx_blocked (blocked_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS solicitudes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        nombre_emprendimiento VARCHAR(150) NOT NULL,
        categoria VARCHAR(100) DEFAULT NULL,
        descripcion TEXT DEFAULT NULL,
        motivo TEXT NOT NULL,
        tipo_documento ENUM('DNI','CUIT/CUIL') DEFAULT NULL,
        numero_documento VARCHAR(20) DEFAULT NULL,
        fecha_nacimiento DATE DEFAULT NULL,
        declaracion_licitud TINYINT(1) NOT NULL DEFAULT 0,
        estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
        comentario_admin TEXT DEFAULT NULL,
        reviewed_by INT DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_solicitudes_user (user_id),
        KEY idx_solicitudes_estado (estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS reclamos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shop_id INT NOT NULL,
        cliente_id INT NOT NULL,
        mensaje TEXT NOT NULL,
        estado ENUM('pendiente','resuelto') NOT NULL DEFAULT 'pendiente',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_reclamos_shop (shop_id),
        KEY idx_reclamos_cliente (cliente_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // --- Avisos en pantalla (carteles con "Entendido"): leida_at NULL = se muestra ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS notificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        tipo VARCHAR(50) NOT NULL,
        titulo VARCHAR(150) NOT NULL,
        mensaje TEXT NOT NULL,
        url VARCHAR(255) DEFAULT NULL,
        leida_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_notif_user (user_id, leida_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // --- Filtro por zona (estilo Marketplace): ubicación en posts ---
    // lat/lng del lugar donde se creó el post + nombre legible del lugar.
    // Se crean solas si no existen, para no tener que correr SQL a mano.
    try {
        $cols = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts'"
        )->fetchAll(PDO::FETCH_COLUMN);
        if ($cols) {
            if (!in_array('lat', $cols, true)) {
                $pdo->exec("ALTER TABLE posts ADD COLUMN lat DECIMAL(10,7) NULL DEFAULT NULL AFTER image_url");
            }
            if (!in_array('lng', $cols, true)) {
                $pdo->exec("ALTER TABLE posts ADD COLUMN lng DECIMAL(10,7) NULL DEFAULT NULL AFTER lat");
            }
            if (!in_array('place_name', $cols, true)) {
                $pdo->exec("ALTER TABLE posts ADD COLUMN place_name VARCHAR(255) NULL DEFAULT NULL AFTER lng");
            }
            // Índice para acelerar el filtro por cercanía.
            $idx = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'posts' AND INDEX_NAME = 'idx_posts_geo'"
            )->fetchColumn();
            if (!$idx) {
                $pdo->exec("ALTER TABLE posts ADD INDEX idx_posts_geo (lat, lng)");
            }
        }
    } catch (PDOException $e) {
        // Si falla la migración de geo, el resto sigue funcionando.
        $migracionOk = false;
    }

    // --- Mensajes eliminados estilo WhatsApp (borrado lógico) ---
    // deleted_at NULL = visible; con fecha = muestra "Mensaje eliminado".
    try {
        $msgCols = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'"
        )->fetchAll(PDO::FETCH_COLUMN);
        if ($msgCols && !in_array('deleted_at', $msgCols, true)) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
        }
    } catch (PDOException $e) {
        // Si falla, el borrado sigue funcionando como antes (DELETE físico).
        $migracionOk = false;
    }

    // Migración para instalaciones donde la tabla ya existía sin estas
    // columnas (paquetes anteriores). Se fija con information_schema para
    // no romper si ya están, y no depender de "ADD COLUMN IF NOT EXISTS"
    // (no disponible en todas las versiones de MySQL del hosting).
    $existentes = $pdo->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'solicitudes'"
    )->fetchAll(PDO::FETCH_COLUMN);

    $columnasNuevas = [
        'tipo_documento'      => "ALTER TABLE solicitudes ADD COLUMN tipo_documento ENUM('DNI','CUIT/CUIL') DEFAULT NULL AFTER motivo",
        'numero_documento'    => "ALTER TABLE solicitudes ADD COLUMN numero_documento VARCHAR(20) DEFAULT NULL AFTER tipo_documento",
        'fecha_nacimiento'    => "ALTER TABLE solicitudes ADD COLUMN fecha_nacimiento DATE DEFAULT NULL AFTER numero_documento",
        'declaracion_licitud' => "ALTER TABLE solicitudes ADD COLUMN declaracion_licitud TINYINT(1) NOT NULL DEFAULT 0 AFTER fecha_nacimiento",
    ];
    foreach ($columnasNuevas as $col => $ddl) {
        if (!in_array($col, $existentes, true)) {
            $pdo->exec($ddl);
        }
    }
} catch (PDOException $e) {
    // Si no se pudo crear (p.ej. permisos del usuario de la base), seguimos:
    // los endpoints que la usan van a fallar con su propio error más claro.
    $migracionOk = false;
}
// Solo se marca como hecha si no hubo ningún error; si falló algo, reintenta.
if ($migracionOk) migracion_marcar('base', 1);
}

// Edad mínima exigida para poder solicitar ser emprendedor.
const EDAD_MINIMA_EMPRENDEDOR = 18;