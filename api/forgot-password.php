<?php
// api/forgot-password.php — POST { email } → manda el correo de recuperación.
//
// La respuesta es SIEMPRE la misma exista o no esa cuenta, para que nadie
// pueda usar este formulario para averiguar qué correos están registrados.

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/recuperacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido.']));
}

$data  = json_input();
$email = trim($data['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Ingresá tu correo electrónico.']));
}

$resultado = recuperacion_solicitar($pdo, $email);

// Sólo bloqueamos por límite de reenvíos (eso sí lo mostramos: la persona ya
// demostró que tiene esa cuenta con el primer pedido). Cualquier otro caso
// -incluido "esa cuenta no existe"- responde igual que un envío exitoso.
if (!$resultado['ok'] && !empty($resultado['espera'])) {
    http_response_code(429);
    exit(json_encode([
        'error'  => 'Esperá ' . $resultado['espera'] . ' segundos antes de pedirlo de nuevo.',
        'espera' => $resultado['espera'],
    ]));
}
if (!$resultado['ok'] && empty($resultado['enviado']) && isset($resultado['link'])) {
    // Existe la cuenta pero el correo no pudo salir (SMTP caído, etc). Esto sí
    // es un error real del servidor, no algo que dependa de si el correo
    // existe, así que lo mostramos.
    http_response_code(502);
    $respuesta = ['error' => 'No pudimos enviar el correo. ' . $resultado['error']];
    if (RECUP_MOSTRAR_LINK_SI_FALLA) {
        $respuesta['linkDesarrollo'] = $resultado['link'];
    }
    exit(json_encode($respuesta));
}

echo json_encode([
    'ok'      => true,
    'mensaje' => 'Si ese correo tiene una cuenta, te llegó un mensaje para recuperar la contraseña.',
]);
