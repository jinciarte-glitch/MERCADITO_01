<?php
// api/likes.php — POST { postId } — da o saca el like del usuario logueado.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido.']));
}

$userId = current_user_id();
$data   = json_input();
$postId = (int)($data['postId'] ?? 0);

if ($postId <= 0) {
    http_response_code(400);
    exit(json_encode(['error' => 'Falta el ID de la publicación.']));
}

$check = $pdo->prepare('SELECT id FROM likes WHERE post_id = ? AND user_id = ? LIMIT 1');
$check->execute([$postId, $userId]);
$existing = $check->fetch();

if ($existing) {
    $pdo->prepare('DELETE FROM likes WHERE id = ?')->execute([$existing['id']]);
    $liked = false;
} else {
    $pdo->prepare('INSERT INTO likes (post_id, user_id) VALUES (?, ?)')->execute([$postId, $userId]);
    $liked = true;
}

$count = $pdo->prepare('SELECT COUNT(*) AS c FROM likes WHERE post_id = ?');
$count->execute([$postId]);
$likeCount = (int)$count->fetch()['c'];

echo json_encode(['ok' => true, 'likedByMe' => $liked, 'likeCount' => $likeCount]);
