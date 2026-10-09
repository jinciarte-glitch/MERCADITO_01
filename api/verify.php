<?php
// api/verify.php — confirma el código de 6 dígitos y reenvía el correo.
//
// POST { email, code }        → activa la cuenta y deja la sesión iniciada.
// POST { email, reenviar:true } → manda un correo nuevo con otro link/código.
//
// El link del correo NO pasa por acá: lo maneja /verificar.php.

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/verificacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido.']));
}

verificacion_migrar($pdo);

$data     = json_input();
$email    = trim($data['email'] ?? ($_SESSION['verificacion_email'] ?? ''));
$codigo   = trim((string)($data['code'] ?? ''));
$reenviar = !empty($data['reenviar']);

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Escribí el correo con el que te registraste.']));
}

// ---------------------------------------------------------------------------
// Reenviar el correo de activación
// ---------------------------------------------------------------------------
if ($reenviar) {
    $usuario = verificacion_usuario_pendiente($pdo, $email);

    // Si el correo no existe o ya está activo, respondemos lo mismo que si
    // hubiera salido bien: así nadie puede usar esto para averiguar qué
    // direcciones están registradas.
    if (!$usuario) {
        exit(json_encode([
            'ok'      => true,
            'mensaje' => 'Si ese correo tiene una cuenta sin activar, ya te llegó un mensaje nuevo.',
        ]));
    }

    $envio = verificacion_enviar($pdo, (int)$usuario['id'], $email, $usuario['full_name'] ?? '');

    if (!$envio['ok'] && !empty($envio['espera'])) {
        http_response_code(429);
        exit(json_encode([
            'error'  => 'Esperá ' . $envio['espera'] . ' segundos antes de pedir otro correo.',
            'espera' => $envio['espera'],
        ]));
    }
    if (!$envio['ok']) {
        http_response_code(502);
        $respuesta = ['error' => 'No pudimos enviar el correo. ' . $envio['error']];
        if (VERIF_MOSTRAR_CODIGO_SI_FALLA) {
            $respuesta['linkDesarrollo']   = $envio['link'];
            $respuesta['codigoDesarrollo'] = $envio['codigo'];
        }
        exit(json_encode($respuesta));
    }

    $_SESSION['verificacion_email'] = $email;
    exit(json_encode(['ok' => true, 'mensaje' => 'Te mandamos un correo nuevo. Revisá también el correo no deseado.']));
}

// ---------------------------------------------------------------------------
// Confirmar el código
// ---------------------------------------------------------------------------
if ($codigo === '') {
    http_response_code(400);
    exit(json_encode(['error' => 'Escribí el código de 6 dígitos que te llegó por correo.']));
}

$resultado = verificacion_confirmar_codigo($pdo, $email, $codigo);

if (!$resultado['ok']) {
    http_response_code(400);
    exit(json_encode(['error' => $resultado['error']]));
}

// Cuenta activada: la dejamos entrar directamente.
$stmt = $pdo->prepare(
    'SELECT r.nombre AS profile_type FROM users u JOIN roles r ON r.roles_id = u.roles_id WHERE u.id = ? LIMIT 1'
);
$stmt->execute([$resultado['userId']]);
$rol = $stmt->fetchColumn();

login_user((int)$resultado['userId']);
$_SESSION['profile_type'] = $rol !== false ? $rol : 'cliente';
unset($_SESSION['verificacion_email']);

echo json_encode(['ok' => true, 'redirect' => '/feed.php']);
