<?php
// api/comments.php — POST { postId, text } — agrega un comentario.
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/moderacion-lib.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido.']));
}

$userId = current_user_id();
mod_exigir_puede_escribir($pdo, (int)$userId); // suspendidos y spam
$data   = json_input();
$postId = (int)($data['postId'] ?? 0);
$text   = trim($data['text'] ?? '');

if ($postId <= 0) {
    http_response_code(400);
    exit(json_encode(['error' => 'Falta el ID de la publicación.']));
}
if ($text === '' || mb_strlen($text) > 240) {
    http_response_code(400);
    exit(json_encode(['error' => 'El comentario debe tener entre 1 y 240 caracteres.']));
}

$stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, body) VALUES (?, ?, ?)');
$stmt->execute([$postId, $userId, $text]);
$commentId = (int)$pdo->lastInsertId();

$nameStmt = $pdo->prepare('SELECT full_name FROM users WHERE id = ?');
$nameStmt->execute([$userId]);
$authorName = $nameStmt->fetch()['full_name'];

// Traemos la hora que MySQL le puso al comentario (no time() de PHP), para
// que sea consistente con lo que devuelve api/posts.php al listar comentarios.
$creado = $pdo->prepare('SELECT UNIX_TIMESTAMP(created_at) FROM comments WHERE id = ?');
$creado->execute([$commentId]);
$createdAtUnix = (int)$creado->fetchColumn();

echo json_encode([
    'ok'      => true,
    'comment' => [
        'authorName' => $authorName,
        'text'       => $text,
        'createdAt'  => $createdAtUnix * 1000,
    ],
]);