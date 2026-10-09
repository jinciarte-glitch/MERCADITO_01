<?php
// api/register.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json'); // 👈 Agrega esta línea

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/verificacion.php';

verificacion_migrar($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido.']));
}

$data     = json_input();
$fullname = trim($data['fullname'] ?? '');
$age      = (int)($data['age'] ?? 0);
$username = trim($data['username'] ?? '');
$email    = trim($data['email'] ?? '');
$password = (string)($data['password'] ?? '');

if ($fullname === '' || $username === '' || $email === '' || $password === '') {
    http_response_code(400);
    exit(json_encode(['error' => 'Faltan campos obligatorios.']));
}
if ($age < 13) {
    http_response_code(400);
    exit(json_encode(['error' => 'Tenés que tener al menos 13 años.']));
}
if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    http_response_code(400);
    exit(json_encode(['error' => 'El usuario debe tener 3-20 caracteres (letras, números o _).']));
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit(json_encode(['error' => 'El correo no es válido.']));
}
if (strlen($password) < 8) {
    http_response_code(400);
    exit(json_encode(['error' => 'La contraseña debe tener al menos 8 caracteres.']));
}
if (!preg_match('/[A-Z]/', $password)) {
    http_response_code(400);
    exit(json_encode(['error' => 'La contraseña debe tener al menos una letra mayúscula.']));
}
if (!preg_match('/[0-9]/', $password)) {
    http_response_code(400);
    exit(json_encode(['error' => 'La contraseña debe tener al menos un número.']));
}
if (!preg_match('/[^A-Za-z0-9]/', $password)) {
    http_response_code(400);
    exit(json_encode(['error' => 'La contraseña debe tener al menos un carácter especial (por ejemplo: ! @ # $ %).']));
}

// Si ya existe una cuenta con ese correo pero nunca se activó, no bloqueamos:
// mandamos el correo de nuevo y la persona sigue desde la pantalla de activación.
$pendiente = verificacion_usuario_pendiente($pdo, $email);
if ($pendiente) {
    $envio = verificacion_enviar($pdo, (int)$pendiente['id'], $email, $pendiente['full_name'] ?? '');
    $_SESSION['verificacion_email'] = $email;
    http_response_code(409);
    exit(json_encode([
        'error'              => 'Ese correo ya tiene una cuenta sin activar. Te reenviamos el mensaje de activación.',
        'requiereVerificacion' => true,
        'email'              => $email,
        'correoEnviado'      => (bool)$envio['ok'],
    ]));
}

$check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
$check->execute([$username, $email]);
if ($check->fetch()) {
    http_response_code(409);
    exit(json_encode(['error' => 'Ese usuario o correo ya está registrado.']));
}

$hash = password_hash($password, PASSWORD_DEFAULT);

// La cuenta nace SIN verificar: email_verified_at queda en NULL y, hasta que
// no se confirme el correo, api/login.php no la deja entrar.
$insert = $pdo->prepare(
    'INSERT INTO users (full_name, username, email, password_hash, age, roles_id) VALUES (?, ?, ?, ?, ?, 
    (SELECT roles_id FROM roles WHERE nombre = "cliente" LIMIT 1))');
$insert->execute([$fullname, $username, $email, $hash, $age]);

$userId = (int)$pdo->lastInsertId();

// Ojo: NO se inicia sesión acá. Eso pasa recién al activar la cuenta.
$envio = verificacion_enviar($pdo, $userId, $email, $fullname);

// Guardamos el correo en la sesión para que la pantalla de activación lo
// muestre sin tener que pasarlo por la URL.
$_SESSION['verificacion_email'] = $email;

// Modo desarrollo: si el hosting no pudo mandar el mail, dejamos el link y el
// código a mano para poder seguir probando (se apaga en mail_config.php).
unset($_SESSION['verificacion_debug']);
if (!$envio['ok'] && VERIF_MOSTRAR_CODIGO_SI_FALLA) {
    $_SESSION['verificacion_debug'] = ['link' => $envio['link'], 'codigo' => $envio['codigo']];
}

echo json_encode([
    'ok'                   => true,
    'requiereVerificacion' => true,
    'email'                => $email,
    'correoEnviado'        => (bool)$envio['ok'],
    'mensaje'              => $envio['ok']
        ? 'Te mandamos un correo para activar la cuenta.'
        : 'Creamos tu cuenta, pero no pudimos enviar el correo de activación. Probá reenviarlo.',
]);
