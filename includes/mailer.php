<?php
// includes/mailer.php — envío de correos.
//
// Cliente SMTP mínimo escrito a mano (sin librerías ni Composer, porque en el
// hosting gratuito no se puede instalar nada). Si no hay SMTP configurado en
// mail_config.php, cae automáticamente a la función mail() de PHP.
//
// Uso:  enviar_mail('ana@correo.com', 'Ana', 'Asunto', $html, $texto, $error);
// Devuelve true/false y, si falla, deja el motivo en $error.

require_once __DIR__ . '/mail_config.php';

/**
 * Envía un correo en formato HTML (con alternativa en texto plano).
 */
function enviar_mail($destino, $destinoNombre, $asunto, $html, $texto, &$error = null)
{
    $error = null;

    if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        $error = 'La dirección de destino no es válida.';
        return false;
    }

    $limite = '=_mercadito_' . bin2hex(random_bytes(12));
    $cuerpo = mail_armar_cuerpo($html, $texto, $limite);

    if (MAIL_SMTP_HOST !== '') {
        return mail_por_smtp($destino, $destinoNombre, $asunto, $cuerpo, $limite, $error);
    }
    return mail_por_php($destino, $destinoNombre, $asunto, $cuerpo, $limite, $error);
}

// ---------------------------------------------------------------------------
// Armado del mensaje (MIME multipart/alternative)
// ---------------------------------------------------------------------------

function mail_armar_cuerpo($html, $texto, $limite)
{
    $lineas = [];
    $lineas[] = 'Este mensaje está en formato MIME.';
    $lineas[] = '';
    $lineas[] = '--' . $limite;
    $lineas[] = 'Content-Type: text/plain; charset=UTF-8';
    $lineas[] = 'Content-Transfer-Encoding: base64';
    $lineas[] = '';
    $lineas[] = rtrim(chunk_split(base64_encode($texto), 76, "\r\n"));
    $lineas[] = '--' . $limite;
    $lineas[] = 'Content-Type: text/html; charset=UTF-8';
    $lineas[] = 'Content-Transfer-Encoding: base64';
    $lineas[] = '';
    $lineas[] = rtrim(chunk_split(base64_encode($html), 76, "\r\n"));
    $lineas[] = '--' . $limite . '--';
    $lineas[] = '';

    return implode("\r\n", $lineas);
}

// Codifica acentos y ñ en asuntos y nombres (RFC 2047).
function mail_encabezado_utf8($texto)
{
    if (preg_match('/^[\x20-\x7E]*$/', $texto)) {
        return $texto;
    }
    return '=?UTF-8?B?' . base64_encode($texto) . '?=';
}

function mail_direccion($email, $nombre)
{
    $nombre = trim((string)$nombre);
    if ($nombre === '') {
        return $email;
    }
    return mail_encabezado_utf8($nombre) . ' <' . $email . '>';
}

function mail_encabezados($destino, $destinoNombre, $asunto, $limite)
{
    $dominio = MAIL_FROM_EMAIL;
    $dominio = substr($dominio, strpos($dominio, '@') + 1) ?: 'mercadito.local';

    return [
        'Date'                      => date('r'),
        'From'                      => mail_direccion(MAIL_FROM_EMAIL, MAIL_FROM_NOMBRE),
        'To'                        => mail_direccion($destino, $destinoNombre),
        'Subject'                   => mail_encabezado_utf8($asunto),
        'Message-ID'                => '<' . bin2hex(random_bytes(12)) . '@' . $dominio . '>',
        'MIME-Version'              => '1.0',
        'Content-Type'              => 'multipart/alternative; boundary="' . $limite . '"',
        'X-Mailer'                  => 'Mercadito',
        'Auto-Submitted'            => 'auto-generated',
    ];
}

// ---------------------------------------------------------------------------
// Camino 1: mail() de PHP
// ---------------------------------------------------------------------------

function mail_por_php($destino, $destinoNombre, $asunto, $cuerpo, $limite, &$error)
{
    if (!function_exists('mail')) {
        $error = 'La función mail() está desactivada en el servidor. Configurá SMTP en includes/mail_config.php.';
        return false;
    }

    $cabeceras = mail_encabezados($destino, $destinoNombre, $asunto, $limite);
    unset($cabeceras['To'], $cabeceras['Subject']); // mail() los recibe aparte

    $texto = '';
    foreach ($cabeceras as $clave => $valor) {
        $texto .= $clave . ': ' . $valor . "\r\n";
    }

    $ok = @mail($destino, mail_encabezado_utf8($asunto), $cuerpo, rtrim($texto));
    if (!$ok) {
        $error = 'El servidor rechazó el envío con mail(). Configurá SMTP en includes/mail_config.php.';
    }
    return $ok;
}

// ---------------------------------------------------------------------------
// Camino 2: SMTP
// ---------------------------------------------------------------------------

function mail_por_smtp($destino, $destinoNombre, $asunto, $cuerpo, $limite, &$error)
{
    $seguridad = strtolower(MAIL_SMTP_SEGURIDAD);
    $host      = ($seguridad === 'ssl' ? 'ssl://' : '') . MAIL_SMTP_HOST . ':' . MAIL_SMTP_PUERTO;

    $contexto = stream_context_create([
        'ssl' => ['SNI_enabled' => true, 'peer_name' => MAIL_SMTP_HOST],
    ]);

    $conexion = @stream_socket_client(
        $host, $errNo, $errStr, MAIL_SMTP_TIMEOUT,
        STREAM_CLIENT_CONNECT, $contexto
    );

    if (!$conexion) {
        $error = 'No se pudo conectar al servidor SMTP (' . MAIL_SMTP_HOST . ':' . MAIL_SMTP_PUERTO . '). '
               . trim($errStr ?: 'puerto bloqueado por el hosting') . '.';
        return false;
    }
    stream_set_timeout($conexion, MAIL_SMTP_TIMEOUT);

    $yo = smtp_nombre_cliente();

    try {
        smtp_esperar($conexion, [220]);
        smtp_comando($conexion, 'EHLO ' . $yo, [250]);

        if ($seguridad === 'tls') {
            smtp_comando($conexion, 'STARTTLS', [220]);
            $cripto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cripto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (!@stream_socket_enable_crypto($conexion, true, $cripto)) {
                throw new RuntimeException('No se pudo activar el cifrado TLS.');
            }
            smtp_comando($conexion, 'EHLO ' . $yo, [250]);
        }

        if (MAIL_SMTP_USUARIO !== '') {
            smtp_comando($conexion, 'AUTH LOGIN', [334]);
            smtp_comando($conexion, base64_encode(MAIL_SMTP_USUARIO), [334]);
            smtp_comando($conexion, base64_encode(MAIL_SMTP_PASSWORD), [235]);
        }

        smtp_comando($conexion, 'MAIL FROM:<' . MAIL_FROM_EMAIL . '>', [250]);
        smtp_comando($conexion, 'RCPT TO:<' . $destino . '>', [250, 251]);
        smtp_comando($conexion, 'DATA', [354]);

        $cabeceras = mail_encabezados($destino, $destinoNombre, $asunto, $limite);
        $mensaje = '';
        foreach ($cabeceras as $clave => $valor) {
            $mensaje .= $clave . ': ' . $valor . "\r\n";
        }
        $mensaje .= "\r\n" . $cuerpo;

        // Un punto solo al principio de una línea termina el DATA: hay que
        // duplicarlo (dot-stuffing) para no cortar el mensaje a la mitad.
        $mensaje = preg_replace('/^\./m', '..', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $mensaje)));

        fwrite($conexion, $mensaje . "\r\n.\r\n");
        smtp_esperar($conexion, [250]);

        @fwrite($conexion, "QUIT\r\n");
        fclose($conexion);
        return true;
    } catch (Throwable $e) {
        $error = 'SMTP: ' . $e->getMessage();
        @fclose($conexion);
        return false;
    }
}

function smtp_nombre_cliente()
{
    $host = $_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/[^A-Za-z0-9.\-]/', '', explode(':', $host)[0]);
    return $host !== '' ? $host : 'localhost';
}

// Lee la respuesta del servidor (soporta respuestas de varias líneas) y
// verifica que el código sea uno de los esperados.
function smtp_esperar($conexion, array $esperados)
{
    $respuesta = '';
    while (($linea = fgets($conexion, 515)) !== false) {
        $respuesta .= $linea;
        // Última línea: "250 OK". Intermedias: "250-OK".
        if (strlen($linea) < 4 || $linea[3] !== '-') {
            break;
        }
    }
    if ($respuesta === '') {
        throw new RuntimeException('El servidor cortó la conexión sin responder.');
    }
    $codigo = (int)substr($respuesta, 0, 3);
    if (!in_array($codigo, $esperados, true)) {
        throw new RuntimeException(trim($respuesta));
    }
    return $respuesta;
}

function smtp_comando($conexion, $comando, array $esperados)
{
    if (fwrite($conexion, $comando . "\r\n") === false) {
        throw new RuntimeException('No se pudo escribir en la conexión.');
    }
    return smtp_esperar($conexion, $esperados);
}
