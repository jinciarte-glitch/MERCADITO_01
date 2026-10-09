<?php
// api/shares.php — compartir / dejar de compartir una publicación
//  POST { postId } -> alterna compartir. Devuelve { shared, shareCount }
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/moderacion-lib.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido.']));
}

$me     = current_user_id();
exigir_no_suspendido($pdo, (int)$me); // suspendido: no puede compartir
$data   = json_input();
$postId = (int)($data['postId'] ?? 0);

if ($postId <= 0) { http_response_code(400); exit(json_encode(['error' => 'Falta la publicación.'])); }

// La publicación tiene que existir.
$exists = $pdo->prepare('SELECT 1 FROM posts WHERE id = ? LIMIT 1');
$exists->execute([$postId]);
if (!$exists->fetchColumn()) { http_response_code(404); exit(json_encode(['error' => 'La publicación no existe.'])); }

$chk = $pdo->prepare('SELECT id FROM shares WHERE user_id = ? AND post_id = ? LIMIT 1');
$chk->execute([$me, $postId]);
$existing = $chk->fetch();

if ($existing) {
    $pdo->prepare('DELETE FROM shares WHERE id = ?')->execute([$existing['id']]);
    $shared = false;
} else {
    $pdo->prepare('INSERT INTO shares (user_id, post_id) VALUES (?, ?)')->execute([$me, $postId]);
    $shared = true;
}

$count = $pdo->prepare('SELECT COUNT(*) FROM shares WHERE post_id = ?');
$count->execute([$postId]);

echo json_encode(['ok' => true, 'shared' => $shared, 'shareCount' => (int)$count->fetchColumn()]);