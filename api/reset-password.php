<?php
// api/reset-password.php — POST { token, password } → guarda la contraseña nueva.

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/recuperacion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido.']));
}

$data     = json_input();
$token    = trim($data['token'] ?? '');
$password = (string)($data['password'] ?? '');

if ($token === '') {
    http_response_code(400);
    exit(json_encode(['error' => 'Falta el link de recuperación.']));
}

$resultado = recuperacion_cambiar_password($pdo, $token, $password);

if (!$resultado['ok']) {
    http_response_code(400);
    exit(json_encode(['error' => $resultado['error']]));
}

echo json_encode(['ok' => true, 'mensaje' => 'Tu contraseña se cambió. Ya podés iniciar sesión.']);
