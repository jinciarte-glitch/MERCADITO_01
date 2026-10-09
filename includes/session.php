<?php
// includes/session.php — manejo de sesión de usuario (login).

// Configura la cookie para que sea válida en TODO el sitio web ( / )
   ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
session_set_cookie_params([
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

// Devuelve el tipo de perfil del usuario logueado ('usuario' | 'emprendedor').
// Lo guarda en la sesión para no consultar la base en cada request. Si la
// sesión todavía no lo tiene (por ej. un login viejo), lo lee de la base una
// sola vez. Recibe $pdo porque la sesión no tiene acceso a la conexión.
function current_profile_type($pdo) {
    if (isset($_SESSION['profile_type'])) {
        return $_SESSION['profile_type'];
    }
    $uid = current_user_id();
    if (!$uid) return null;
    $stmt = $pdo->prepare('SELECT r.nombre FROM users u JOIN roles r ON r.roles_id = u.roles_id WHERE u.id = ? LIMIT 1');
    $stmt->execute([$uid]);
    $type = $stmt->fetchColumn();
    if ($type !== false) {
        $_SESSION['profile_type'] = $type;
        return $type;
    }
    return null;
}

// Un administrador también cuenta como emprendedor, así puede probar
// "Mi espacio", el catálogo y todo lo relacionado sin necesitar otra cuenta.
function is_emprendedor($pdo) {
    $tipo = current_profile_type($pdo);
    return $tipo === 'emprendedor' || $tipo === 'administrador';
}

// Chequeo ESTRICTO: solo true si el rol real (en la base) es 'emprendedor'.
// No incluye a los administradores. Se usa específicamente en el flujo de
// "solicitud para ser emprendedor" (formulario + API) para que un admin
// pueda ver y probar ese flujo tal cual lo ve un cliente, sin que el bypass
// de is_emprendedor() se lo tape.
function is_emprendedor_real($pdo) {
    return current_profile_type($pdo) === 'emprendedor';
}

function is_administrador($pdo) {
    return current_profile_type($pdo) === 'administrador';
}

function login_user($userId) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    unset($_SESSION['csrf_token']); // token nuevo para la sesión recién iniciada
}

function logout_user() {
    $_SESSION = [];
    session_destroy();
}

// Corta la ejecución con 401 si no hay sesión activa. Se usa al
// principio de cada endpoint de la API que requiere estar logueado.
function require_login() {
    if (!current_user_id()) {
        http_response_code(401);
        echo json_encode(['error' => 'Tenés que iniciar sesión.']);
        exit;
    }
}

// Lee el body JSON de la petición (fetch envía JSON, no form-data).
function json_input() {
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}


// ---------------------------------------------------------------------------
//  CSRF — token por sesión para las acciones que modifican datos como admin.
// ---------------------------------------------------------------------------

// Devuelve el token de la sesión (lo crea la primera vez).
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Corta con 403 si el pedido no trae el token correcto. Lo manda api.js en
// el header X-CSRF-Token; también se acepta un campo "csrf_token" en el body.
function csrf_check() {
    $esperado = $_SESSION['csrf_token'] ?? '';
    $enviado  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if ($esperado === '' || !is_string($enviado) || !hash_equals($esperado, $enviado)) {
        http_response_code(403);
        exit(json_encode(['error' => 'La sesión del panel venció. Recargá la página e intentá de nuevo.']));
    }
}
