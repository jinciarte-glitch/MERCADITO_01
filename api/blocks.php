<?php
// api/blocks.php — bloquear / desbloquear usuarios (mensajería)
//  GET  ?userId=X   -> { blocked, blockedMe }
//                       blocked   = yo bloqueé a userId
//                       blockedMe = userId me bloqueó a mí
//  POST { userId }  -> alterna bloquear/desbloquear. Devuelve { ok, blocked }
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$me = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = (int)($_GET['userId'] ?? 0);
    if ($userId <= 0) { http_response_code(400); exit(json_encode(['error' => 'Falta el usuario.'])); }

    $a = $pdo->prepare('SELECT 1 FROM blocks WHERE blocker_id = ? AND blocked_id = ? LIMIT 1');
    $a->execute([$me, $userId]);
    $b = $pdo->prepare('SELECT 1 FROM blocks WHERE blocker_id = ? AND blocked_id = ? LIMIT 1');
    $b->execute([$userId, $me]);

    echo json_encode([
        'blocked'   => (bool)$a->fetchColumn(),
        'blockedMe' => (bool)$b->fetchColumn(),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data   = json_input();
    $userId = (int)($data['userId'] ?? 0);

    if ($userId <= 0) { http_response_code(400); exit(json_encode(['error' => 'Falta el usuario.'])); }
    if ($userId === (int)$me) { http_response_code(400); exit(json_encode(['error' => 'No podés bloquearte a vos misma.'])); }

    $chk = $pdo->prepare('SELECT id FROM blocks WHERE blocker_id = ? AND blocked_id = ? LIMIT 1');
    $chk->execute([$me, $userId]);
    $existing = $chk->fetch();

    if ($existing) {
        $pdo->prepare('DELETE FROM blocks WHERE id = ?')->execute([$existing['id']]);
        $blocked = false;
    } else {
        $pdo->prepare('INSERT INTO blocks (blocker_id, blocked_id) VALUES (?, ?)')->execute([$me, $userId]);
        $blocked = true;
    }

    echo json_encode(['ok' => true, 'blocked' => $blocked]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido.']);
