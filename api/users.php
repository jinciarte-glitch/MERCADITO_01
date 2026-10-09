<?php
// api/users.php — usuarios para la barra derecha
//  GET ?suggest=1   -> "A quién seguir": usuarios que no sigo (ni yo mismo)
//  GET ?q=texto     -> búsqueda por nombre o @usuario
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$me = current_user_id();

function user_row($u) {
    return [
        'id'        => (int)$u['id'],
        'name'      => $u['full_name'],
        'username'  => $u['username'],
        'avatarUrl' => $u['avatar_url'],
        'type'      => $u['nombre'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido.']));
}

// Búsqueda
if (isset($_GET['q'])) {
    $q = trim($_GET['q']);
    if ($q === '') { echo json_encode(['users' => []]); exit; }
    $stmt = $pdo->prepare(
        'SELECT u.id, u.full_name, u.username, u.avatar_url, r.nombre
         FROM users u JOIN roles r ON r.roles_id = u.roles_id
         WHERE u.id <> :me AND (u.full_name LIKE :q OR u.username LIKE :q)
         ORDER BY u.full_name ASC LIMIT 8');
    $stmt->bindValue(':me', $me, PDO::PARAM_INT);
    $stmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
    $stmt->execute();
    echo json_encode(['users' => array_map('user_row', $stmt->fetchAll())]);
    exit;
}

// Sugerencias ("A quién seguir")
$stmt = $pdo->prepare(
    'SELECT u.id, u.full_name, u.username, u.avatar_url, r.nombre
     FROM users u JOIN roles r ON r.roles_id = u.roles_id
     WHERE u.id <> :me
       AND u.id NOT IN (SELECT followed_id FROM follows WHERE follower_id = :me)
     ORDER BY (SELECT COUNT(*) FROM follows f WHERE f.followed_id = u.id) DESC, u.id DESC
     LIMIT 3');
$stmt->bindValue(':me', $me, PDO::PARAM_INT);
$stmt->execute();
echo json_encode(['users' => array_map('user_row', $stmt->fetchAll())]);
