<?php
// api/follows.php — seguir / dejar de seguir usuarios
//  POST { userId }   -> alterna seguir/no seguir. Devuelve { following, followerCount }
//  GET  ?userId=X    -> { following, followerCount, followingCount }
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$me = current_user_id();

function follower_count($pdo, $userId) {
    $s = $pdo->prepare('SELECT COUNT(*) FROM follows WHERE followed_id = ?');
    $s->execute([$userId]);
    return (int)$s->fetchColumn();
}
function following_count($pdo, $userId) {
    $s = $pdo->prepare('SELECT COUNT(*) FROM follows WHERE follower_id = ?');
    $s->execute([$userId]);
    return (int)$s->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = (int)($_GET['userId'] ?? 0);
    if ($userId <= 0) { http_response_code(400); exit(json_encode(['error' => 'Falta el usuario.'])); }

    // Listado de seguidores o seguidos: ?userId=X&list=followers|following
    if (isset($_GET['list'])) {
        $type = $_GET['list'] === 'following' ? 'following' : 'followers';
        if ($type === 'followers') {
            // Personas que siguen a userId
            $sql = 'SELECT u.id, u.full_name, u.username, u.avatar_url, r.nombre AS type
                    FROM follows f
                    JOIN users u ON u.id = f.follower_id
                    JOIN roles r ON r.roles_id = u.roles_id
                    WHERE f.followed_id = ? ORDER BY f.id DESC LIMIT 200';
        } else {
            // Personas que userId sigue
            $sql = 'SELECT u.id, u.full_name, u.username, u.avatar_url, r.nombre AS type
                    FROM follows f
                    JOIN users u ON u.id = f.followed_id
                    JOIN roles r ON r.roles_id = u.roles_id
                    WHERE f.follower_id = ? ORDER BY f.id DESC LIMIT 200';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $users = array_map(function ($u) {
            return [
                'id'        => (int)$u['id'],
                'name'      => $u['full_name'],
                'username'  => $u['username'],
                'avatarUrl' => $u['avatar_url'],
                'type'      => $u['type'],
            ];
        }, $stmt->fetchAll());
        echo json_encode(['users' => $users]);
        exit;
    }

    $chk = $pdo->prepare('SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ? LIMIT 1');
    $chk->execute([$me, $userId]);
    echo json_encode([
        'following'      => (bool)$chk->fetchColumn(),
        'followerCount'  => follower_count($pdo, $userId),
        'followingCount' => following_count($pdo, $userId),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data   = json_input();
    $userId = (int)($data['userId'] ?? 0);

    if ($userId <= 0) { http_response_code(400); exit(json_encode(['error' => 'Falta el usuario.'])); }
    if ($userId === (int)$me) { http_response_code(400); exit(json_encode(['error' => 'No podés seguirte a vos misma.'])); }

    $chk = $pdo->prepare('SELECT id FROM follows WHERE follower_id = ? AND followed_id = ? LIMIT 1');
    $chk->execute([$me, $userId]);
    $existing = $chk->fetch();

    if ($existing) {
        $pdo->prepare('DELETE FROM follows WHERE id = ?')->execute([$existing['id']]);
        $following = false;
    } else {
        $pdo->prepare('INSERT INTO follows (follower_id, followed_id) VALUES (?, ?)')->execute([$me, $userId]);
        $following = true;
    }

    echo json_encode([
        'ok'            => true,
        'following'     => $following,
        'followerCount' => follower_count($pdo, $userId),
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido.']);
