<?php
// includes/verificacion.php — verificación de correo electrónico.
//
// Al registrarse, la cuenta queda INACTIVA hasta que la persona confirma su
// correo. Se le manda un mail con dos formas de activarla (las dos sirven):
//   1. un link directo   → /verificar.php?token=...
//   2. un código de 6 dígitos para escribir a mano
//
// Mientras users.email_verified_at sea NULL, api/login.php rechaza el login.

require_once __DIR__ . '/mailer.php';

require_once __DIR__ . '/migraciones.php';

/**
 * Crea la tabla y la columna que hace falta, si todavía no existen.
 * Es barato (se fija en information_schema) y evita tener que correr SQL a
 * mano en el hosting, igual que hace bootstrap.php con las otras tablas.
 */
function verificacion_migrar($pdo)
{
    static $listo = false;
    if ($listo) return true;
    if (migracion_hecha('verificacion', 1)) { $listo = true; return true; }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS email_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            email VARCHAR(191) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            code_hash CHAR(64) NOT NULL,
            intentos TINYINT NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_token (token_hash),
            KEY idx_verif_user (user_id),
            KEY idx_verif_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $columnas = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('email_verified_at', $columnas, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN email_verified_at DATETIME DEFAULT NULL");
            // Importante: las cuentas que YA existían se dan por verificadas,
            // así nadie que hoy puede entrar se queda afuera por esta mejora.
            $pdo->exec("UPDATE users SET email_verified_at = NOW() WHERE email_verified_at IS NULL");
        }

        migracion_marcar('verificacion', 1);
        $listo = true;
        return true;
    } catch (PDOException $e) {
        // Si el usuario de la base no tiene permiso para ALTER, los endpoints
        // que la usan van a fallar con un error más claro.
        return false;
    }
}

/** ¿La columna email_verified_at existe? (por si la migración no pudo correr) */
function verificacion_disponible($pdo)
{
    try {
        $pdo->query('SELECT email_verified_at FROM users LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/** URL pública del sitio, sin barra final. */
function sitio_url()
{
    if (defined('SITIO_URL') && SITIO_URL !== '') {
        return rtrim(SITIO_URL, '/');
    }
    $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443;
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($esHttps ? 'https://' : 'http://') . $host;
}

/**
 * Genera un token + código nuevos para el usuario, invalida los anteriores y
 * manda el mail.
 *
 * Devuelve un array:
 *   ['ok' => bool, 'error' => string|null, 'espera' => int, 'link' => ..., 'codigo' => ...]
 * 'espera' viene con los segundos que faltan cuando se pide reenviar
 * demasiado seguido.
 */
function verificacion_enviar($pdo, $userId, $email, $nombre = '')
{
    verificacion_migrar($pdo);

    // --- Límites de reenvío (anti spam / anti abuso) ---
    $ultimo = $pdo->prepare(
        'SELECT created_at, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS segundos
         FROM email_verifications WHERE user_id = ? ORDER BY id DESC LIMIT 1'
    );
    $ultimo->execute([$userId]);
    $fila = $ultimo->fetch();
    if ($fila && (int)$fila['segundos'] < VERIF_SEGUNDOS_ENTRE_ENVIOS) {
        return [
            'ok'     => false,
            'espera' => VERIF_SEGUNDOS_ENTRE_ENVIOS - (int)$fila['segundos'],
            'error'  => 'Esperá unos segundos antes de pedir otro correo.',
        ];
    }

    $porHora = $pdo->prepare(
        'SELECT COUNT(*) FROM email_verifications
         WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );
    $porHora->execute([$userId]);
    if ((int)$porHora->fetchColumn() >= VERIF_ENVIOS_MAXIMOS_POR_HORA) {
        return [
            'ok'     => false,
            'espera' => 0,
            'error'  => 'Pediste el correo demasiadas veces. Probá de nuevo en una hora.',
        ];
    }

    // --- Token y código nuevos ---
    $token  = bin2hex(random_bytes(32));           // va en el link
    $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // Los anteriores dejan de servir apenas se manda uno nuevo.
    $pdo->prepare('UPDATE email_verifications SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
        ->execute([$userId]);

    $insertar = $pdo->prepare(
        'INSERT INTO email_verifications (user_id, email, token_hash, code_hash, expires_at)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
    );
    $insertar->execute([
        $userId,
        $email,
        hash('sha256', $token),
        hash('sha256', $codigo),
        VERIF_MINUTOS_VALIDEZ,
    ]);

    $link = sitio_url() . '/verificar.php?token=' . $token;

    $enviado = enviar_mail(
        $email,
        $nombre,
        'Activá tu cuenta de Mercadito',
        verificacion_html($nombre, $link, $codigo),
        verificacion_texto($nombre, $link, $codigo),
        $errorMail
    );

    return [
        'ok'     => $enviado,
        'espera' => 0,
        'error'  => $enviado ? null : ($errorMail ?: 'No se pudo enviar el correo.'),
        // Sólo se muestran en pantalla si VERIF_MOSTRAR_CODIGO_SI_FALLA está
        // en true y además el envío falló (modo desarrollo).
        'link'   => $link,
        'codigo' => $codigo,
    ];
}

/**
 * Marca la cuenta como verificada a partir del token del link.
 * Devuelve ['ok'=>bool, 'error'=>string|null, 'userId'=>int|null].
 */
function verificacion_confirmar_token($pdo, $token)
{
    verificacion_migrar($pdo);

    if (!preg_match('/^[a-f0-9]{64}$/', (string)$token)) {
        return ['ok' => false, 'error' => 'El link de activación no es válido.', 'userId' => null];
    }

    $stmt = $pdo->prepare(
        'SELECT id, user_id, used_at, (expires_at < NOW()) AS vencido
         FROM email_verifications WHERE token_hash = ? LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $fila = $stmt->fetch();

    if (!$fila) {
        return ['ok' => false, 'error' => 'El link de activación no es válido.', 'userId' => null];
    }
    return verificacion_cerrar($pdo, $fila);
}

/**
 * Marca la cuenta como verificada a partir del código de 6 dígitos.
 * $email identifica la cuenta (es lo que la persona tiene a mano).
 */
function verificacion_confirmar_codigo($pdo, $email, $codigo)
{
    verificacion_migrar($pdo);

    $codigo = preg_replace('/\D/', '', (string)$codigo);
    if (strlen($codigo) !== 6) {
        return ['ok' => false, 'error' => 'El código tiene 6 dígitos.', 'userId' => null];
    }

    $stmt = $pdo->prepare(
        'SELECT id, user_id, code_hash, intentos, used_at, (expires_at < NOW()) AS vencido
         FROM email_verifications WHERE email = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$email]);
    $fila = $stmt->fetch();

    // Mensaje igual en todos los casos para no revelar si el correo existe.
    $generico = ['ok' => false, 'error' => 'El código no es correcto o ya venció.', 'userId' => null];

    if (!$fila || $fila['used_at'] !== null) {
        return $generico;
    }
    if ((int)$fila['intentos'] >= VERIF_INTENTOS_MAXIMOS) {
        return ['ok' => false, 'error' => 'Demasiados intentos. Pedí un código nuevo.', 'userId' => null];
    }

    $pdo->prepare('UPDATE email_verifications SET intentos = intentos + 1 WHERE id = ?')
        ->execute([$fila['id']]);

    if (!hash_equals($fila['code_hash'], hash('sha256', $codigo))) {
        return $generico;
    }
    return verificacion_cerrar($pdo, $fila);
}

/** Paso final compartido: chequea vencimiento y activa la cuenta. */
function verificacion_cerrar($pdo, array $fila)
{
    if ($fila['used_at'] !== null) {
        return ['ok' => false, 'error' => 'Ese código ya se usó. Pedí uno nuevo.', 'userId' => null];
    }
    if ($fila['vencido']) {
        return ['ok' => false, 'error' => 'El código venció. Pedí uno nuevo.', 'userId' => null];
    }

    $pdo->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = ?')->execute([$fila['id']]);
    $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ? AND email_verified_at IS NULL')
        ->execute([$fila['user_id']]);

    return ['ok' => true, 'error' => null, 'userId' => (int)$fila['user_id']];
}

/** Busca una cuenta sin verificar por correo. Devuelve null si no hay. */
function verificacion_usuario_pendiente($pdo, $email)
{
    verificacion_migrar($pdo);
    $stmt = $pdo->prepare(
        'SELECT id, full_name, email FROM users
         WHERE email = ? AND email_verified_at IS NULL LIMIT 1'
    );
    $stmt->execute([$email]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

// ---------------------------------------------------------------------------
// Contenido del correo
// ---------------------------------------------------------------------------

function verificacion_texto($nombre, $link, $codigo)
{
    $saludo = $nombre !== '' ? "Hola $nombre," : 'Hola,';
    $horas  = VERIF_MINUTOS_VALIDEZ;
    return "$saludo\n\n"
        . "Creaste una cuenta en Mercadito. Para poder entrar, activá tu correo:\n\n"
        . "$link\n\n"
        . "O escribí este código en la pantalla de activación: $codigo\n\n"
        . "El link y el código vencen en $horas minutos.\n"
        . "Si no fuiste vos, ignorá este mensaje: la cuenta no se activa sola.\n\n"
        . "— Mercadito";
}

function verificacion_html($nombre, $link, $codigo)
{
    $saludo    = $nombre !== '' ? 'Hola ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . ',' : 'Hola,';
    $linkSeguro = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    $codigoHtml = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');
    $minutos   = VERIF_MINUTOS_VALIDEZ;

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Activá tu cuenta</title></head>
<body style="margin:0;padding:24px;background:#fbf7f1;font-family:'Work Sans',Helvetica,Arial,sans-serif;color:#3b2a1e;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e7dccd;">
    <tr>
      <td style="background:#6b4226;padding:24px 28px;">
        <span style="color:#fbf7f1;font-size:22px;font-weight:700;letter-spacing:.3px;">Mercadito</span>
      </td>
    </tr>
    <tr>
      <td style="padding:28px;">
        <p style="margin:0 0 14px;font-size:16px;">$saludo</p>
        <p style="margin:0 0 22px;font-size:15px;line-height:1.6;">
          Creaste una cuenta en Mercadito. Falta un paso: confirmar que este correo es tuyo.
        </p>
        <p style="margin:0 0 26px;">
          <a href="$linkSeguro" style="display:inline-block;background:#1e9a85;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:13px 26px;border-radius:10px;">Activar mi cuenta</a>
        </p>
        <p style="margin:0 0 10px;font-size:14px;color:#6f5a48;">O escribí este código en la pantalla de activación:</p>
        <p style="margin:0 0 24px;font-size:30px;font-weight:700;letter-spacing:7px;color:#6b4226;">$codigoHtml</p>
        <p style="margin:0 0 6px;font-size:13px;color:#6f5a48;line-height:1.6;">
          El link y el código vencen en $minutos minutos. Si no fuiste vos, ignorá este mensaje: sin confirmar, la cuenta no se activa.
        </p>
        <p style="margin:18px 0 0;font-size:12px;color:#9a8877;word-break:break-all;">
          ¿No funciona el botón? Copiá esta dirección: $linkSeguro
        </p>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}
