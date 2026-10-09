<?php
// api/profile.php
//  GET  -> perfil del usuario logueado
//  POST -> actualiza { name, bio, avatarUrl, type }
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/moderacion-lib.php';
require_login();

$userId = current_user_id();

function fetch_profile($pdo, $userId) {
    $stmt = $pdo->prepare(
    'SELECT u.id, u.full_name, u.bio, u.avatar_url, r.nombre AS profile_type FROM users u JOIN roles r ON r.roles_id = u.roles_id WHERE u.id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return [
        'id'        => (int)$row['id'],
        'name'      => $row['full_name'],
        'bio'       => $row['bio'],
        'avatarUrl' => $row['avatar_url'],
        'type'      => $row['profile_type'],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Perfil PÚBLICO de otro usuario: /api/profile.php?id=X
    // Devuelve datos visibles + contadores (seguidores, seguidos, me gusta
    // recibidos, publicaciones) e info de la relación con el usuario logueado.
    if (isset($_GET['id'])) {
        $otherId = (int)$_GET['id'];
        $stmt = $pdo->prepare(
            'SELECT u.id, u.full_name, u.username, u.bio, u.avatar_url, UNIX_TIMESTAMP(u.created_at) AS created_at_unix, r.nombre AS profile_type
             FROM users u JOIN roles r ON r.roles_id = u.roles_id WHERE u.id = ? LIMIT 1');
        $stmt->execute([$otherId]);
        $u = $stmt->fetch();
        if (!$u) { http_response_code(404); exit(json_encode(['error' => 'Usuario no encontrado.'])); }

        $one = function ($sql, $args) use ($pdo) {
            $s = $pdo->prepare($sql); $s->execute($args); return (int)$s->fetchColumn();
        };
        $followerCount  = $one('SELECT COUNT(*) FROM follows WHERE followed_id = ?', [$otherId]);
        $followingCount = $one('SELECT COUNT(*) FROM follows WHERE follower_id = ?', [$otherId]);
        $postCount      = $one('SELECT COUNT(*) FROM posts WHERE user_id = ?', [$otherId]);
        $likesReceived  = $one('SELECT COUNT(*) FROM likes l JOIN posts p ON p.id = l.post_id WHERE p.user_id = ?', [$otherId]);
        $isFollowing    = $one('SELECT COUNT(*) FROM follows WHERE follower_id = ? AND followed_id = ?', [$userId, $otherId]) > 0;

        echo json_encode(['profile' => [
            'id'             => (int)$u['id'],
            'name'           => $u['full_name'],
            'username'       => $u['username'],
            'bio'            => $u['bio'],
            'avatarUrl'      => $u['avatar_url'],
            'type'           => $u['profile_type'],
            'followerCount'  => $followerCount,
            'followingCount' => $followingCount,
            'postCount'      => $postCount,
            'likesReceived'  => $likesReceived,
            'isFollowing'    => $isFollowing,
            'isMe'           => ((int)$u['id'] === (int)$userId),
            'joinedAt'       => !empty($u['created_at_unix']) ? (int)$u['created_at_unix'] * 1000 : null,
        ]]);
        exit;
    }

    $profile = fetch_profile($pdo, $userId);
    if (!$profile) {
        http_response_code(404);
        exit(json_encode(['error' => 'Perfil no encontrado.']));
    }
    echo json_encode(['profile' => $profile]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_no_suspendido($pdo, (int)$userId); // suspendido: no puede modificar su perfil
    $data = json_input();
    $name = trim($data['name'] ?? '');
    $bio  = trim($data['bio'] ?? '');
    $avatarUrl = trim($data['avatarUrl'] ?? '');
    $requestedType = ($data['type'] ?? 'cliente') === 'emprendedor' ? 'emprendedor' : 'cliente';

    if ($name === '') {
        http_response_code(400);
        exit(json_encode(['error' => 'El nombre no puede estar vacío.']));
    }
    if ($avatarUrl !== '' && !preg_match('#^https?://#i', $avatarUrl)) {
        http_response_code(400);
        exit(json_encode(['error' => 'La URL de la foto debe empezar con http:// o https://']));
    }

    // El rol "emprendedor" sólo se otorga cuando un admin aprueba una
    // solicitud (ver api/solicitudes.php). Este endpoint no puede dárselo
    // a un cliente, y tampoco puede sacarle el rol a un administrador.
    $rolStmt = $pdo->prepare(
        'SELECT r.nombre FROM users u JOIN roles r ON r.roles_id = u.roles_id WHERE u.id = ? LIMIT 1'
    );
    $rolStmt->execute([$userId]);
    $rolActual = $rolStmt->fetchColumn();

    if ($rolActual === 'administrador') {
        $type = 'administrador';
    } elseif ($requestedType === 'emprendedor' && $rolActual !== 'emprendedor') {
        http_response_code(400);
        exit(json_encode(['error' => 'Para tener cuenta de emprendedor primero tenés que pedir la certificación.']));
    } else {
        $type = $requestedType;
    }

	$stmt = $pdo->prepare(
    'UPDATE users SET full_name = ?, bio = ?, avatar_url = ?, roles_id = (SELECT roles_id FROM roles WHERE nombre = ? LIMIT 1) WHERE id = ?');
	$stmt->execute([$name, $bio ?: null, $avatarUrl ?: null, $type, $userId]);
    
    // Mantené la sesión al día para que aparezca/desaparezca "Mi espacio".
    $_SESSION['profile_type'] = $type;

    echo json_encode(['profile' => fetch_profile($pdo, $userId)]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido.']);