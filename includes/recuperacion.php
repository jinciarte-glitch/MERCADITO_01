<?php
// includes/recuperacion.php — "Olvidé mi contraseña".
//
// Funciona igual que la verificación de correo (mismo mailer, mismo patrón
// de token con hash), pero más simple: un solo link, sin código de 6 dígitos,
// porque acá nadie necesita "activar" nada, sólo probar que puede acceder al
// correo para poner una contraseña nueva.

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/verificacion.php'; // reutiliza sitio_url()

require_once __DIR__ . '/migraciones.php';

/** Crea la tabla si todavía no existe. */
function recuperacion_migrar($pdo)
{
    static $listo = false;
    if ($listo) return true;
    if (migracion_hecha('recuperacion', 1)) { $listo = true; return true; }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_reset_token (token_hash),
            KEY idx_reset_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        migracion_marcar('recuperacion', 1);
        $listo = true;
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Pide el correo de recuperación para la cuenta con ese email (si existe).
 *
 * OJO con la privacidad: la respuesta de esta función es interna, para que
 * api/forgot-password.php decida qué mostrar. De cara a la persona que usa el
 * formulario, la respuesta pública tiene que ser SIEMPRE la misma exista o no
 * exista esa cuenta — si no, cualquiera podría usar el formulario para
 * averiguar qué correos están registrados.
 */
function recuperacion_solicitar($pdo, $email)
{
    recuperacion_migrar($pdo);

    $stmt = $pdo->prepare('SELECT id, full_name FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    // No existe esa cuenta: no hay nada que enviar, pero no lo decimos afuera.
    if (!$usuario) {
        return ['ok' => true, 'enviado' => false, 'error' => null, 'espera' => 0];
    }

    $userId = (int)$usuario['id'];

    // --- Límites de reenvío (los mismos criterios que la verificación) ---
    $ultimo = $pdo->prepare(
        'SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS segundos
         FROM password_resets WHERE user_id = ? ORDER BY id DESC LIMIT 1'
    );
    $ultimo->execute([$userId]);
    $segundos = $ultimo->fetchColumn();
    if ($segundos !== false && (int)$segundos < RECUP_SEGUNDOS_ENTRE_ENVIOS) {
        return [
            'ok'      => false,
            'enviado' => false,
            'espera'  => RECUP_SEGUNDOS_ENTRE_ENVIOS - (int)$segundos,
            'error'   => 'Esperá unos segundos antes de pedirlo de nuevo.',
        ];
    }

    $porHora = $pdo->prepare(
        'SELECT COUNT(*) FROM password_resets
         WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );
    $porHora->execute([$userId]);
    if ((int)$porHora->fetchColumn() >= RECUP_ENVIOS_MAXIMOS_POR_HORA) {
        return [
            'ok'      => false,
            'enviado' => false,
            'espera'  => 0,
            'error'   => 'Lo pediste demasiadas veces. Probá de nuevo en una hora.',
        ];
    }

    // Los links anteriores dejan de servir apenas se pide uno nuevo.
    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
        ->execute([$userId]);

    $token = bin2hex(random_bytes(32));
    $pdo->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
    )->execute([$userId, hash('sha256', $token), RECUP_MINUTOS_VALIDEZ]);

    $link = sitio_url() . '/restablecer.php?token=' . $token;

    $enviado = enviar_mail(
        $email,
        $usuario['full_name'] ?? '',
        'Recuperá tu contraseña de Mercadito',
        recuperacion_html($usuario['full_name'] ?? '', $link),
        recuperacion_texto($usuario['full_name'] ?? '', $link),
        $errorMail
    );

    return [
        'ok'      => $enviado,
        'enviado' => $enviado,
        'espera'  => 0,
        'error'   => $enviado ? null : ($errorMail ?: 'No se pudo enviar el correo.'),
        'link'    => $link, // sólo se muestra en pantalla en modo desarrollo
    ];
}

/**
 * Valida un token sin gastarlo todavía (para mostrar el formulario de
 * contraseña nueva sólo si el link es válido).
 * Devuelve ['ok'=>bool, 'error'=>string|null].
 */
function recuperacion_validar_token($pdo, $token)
{
    recuperacion_migrar($pdo);

    if (!preg_match('/^[a-f0-9]{64}$/', (string)$token)) {
        return ['ok' => false, 'error' => 'El link no es válido.'];
    }

    $stmt = $pdo->prepare(
        'SELECT used_at, (expires_at < NOW()) AS vencido
         FROM password_resets WHERE token_hash = ? LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $fila = $stmt->fetch();

    if (!$fila) {
        return ['ok' => false, 'error' => 'El link no es válido.'];
    }
    if ($fila['used_at'] !== null) {
        return ['ok' => false, 'error' => 'Ese link ya se usó. Pedí uno nuevo.'];
    }
    if ($fila['vencido']) {
        return ['ok' => false, 'error' => 'El link venció. Pedí uno nuevo.'];
    }
    return ['ok' => true, 'error' => null];
}

/**
 * Cambia la contraseña usando el token del link. No hace login automático:
 * la persona vuelve a entrar con su contraseña nueva, así se asegura de que
 * la recuerda bien.
 * Devuelve ['ok'=>bool, 'error'=>string|null].
 */
function recuperacion_cambiar_password($pdo, $token, $passwordNueva)
{
    recuperacion_migrar($pdo);

    if (!preg_match('/^[a-f0-9]{64}$/', (string)$token)) {
        return ['ok' => false, 'error' => 'El link no es válido.'];
    }

    if (strlen($passwordNueva) < 8
        || !preg_match('/[A-Z]/', $passwordNueva)
        || !preg_match('/[0-9]/', $passwordNueva)
        || !preg_match('/[^A-Za-z0-9]/', $passwordNueva)
    ) {
        return ['ok' => false, 'error' => 'La contraseña debe tener 8+ caracteres, una mayúscula, un número y un carácter especial.'];
    }

    $stmt = $pdo->prepare(
        'SELECT id, user_id, used_at, (expires_at < NOW()) AS vencido
         FROM password_resets WHERE token_hash = ? LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $fila = $stmt->fetch();

    if (!$fila) {
        return ['ok' => false, 'error' => 'El link no es válido.'];
    }
    if ($fila['used_at'] !== null) {
        return ['ok' => false, 'error' => 'Ese link ya se usó. Pedí uno nuevo.'];
    }
    if ($fila['vencido']) {
        return ['ok' => false, 'error' => 'El link venció. Pedí uno nuevo.'];
    }

    $hash = password_hash($passwordNueva, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([$hash, $fila['user_id']]);

    // Este link ya no sirve, y tampoco ningún otro pendiente de esta cuenta
    // (por si se pidieron varios y quedaron colgados).
    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
        ->execute([$fila['user_id']]);

    return ['ok' => true, 'error' => null];
}

// ---------------------------------------------------------------------------
// Contenido del correo
// ---------------------------------------------------------------------------

function recuperacion_texto($nombre, $link)
{
    $saludo = $nombre !== '' ? "Hola $nombre," : 'Hola,';
    $minutos = RECUP_MINUTOS_VALIDEZ;
    return "$saludo\n\n"
        . "Pediste recuperar tu contraseña de Mercadito. Para elegir una nueva, entrá acá:\n\n"
        . "$link\n\n"
        . "Este link vence en $minutos minutos.\n"
        . "Si no fuiste vos, ignorá este mensaje: tu contraseña actual sigue funcionando.\n\n"
        . "— Mercadito";
}

function recuperacion_html($nombre, $link)
{
    $saludo     = $nombre !== '' ? 'Hola ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . ',' : 'Hola,';
    $linkSeguro = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    $minutos    = RECUP_MINUTOS_VALIDEZ;

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Recuperá tu contraseña</title></head>
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
          Pediste recuperar tu contraseña. Tocá el botón para elegir una nueva.
        </p>
        <p style="margin:0 0 26px;">
          <a href="$linkSeguro" style="display:inline-block;background:#1e9a85;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:13px 26px;border-radius:10px;">Elegir contraseña nueva</a>
        </p>
        <p style="margin:0 0 6px;font-size:13px;color:#6f5a48;line-height:1.6;">
          El link vence en $minutos minutos. Si no fuiste vos, ignorá este mensaje: tu contraseña actual sigue funcionando.
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
