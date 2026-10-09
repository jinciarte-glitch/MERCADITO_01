<?php
// api/notificaciones.php — POST { id } — marca como leído un aviso en pantalla
// (el botón "Entendido" del cartel). Una vez leído, no vuelve a aparecer.
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido.']));
}

$userId = current_user_id();
$data   = json_input();
$id     = (int)($data['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    exit(json_encode(['error' => 'Falta el ID del aviso.']));
}

// Solo puede marcar avisos propios.
$stmt = $pdo->prepare(
    'UPDATE notificaciones SET leida_at = NOW()
     WHERE id = ? AND user_id = ? AND leida_at IS NULL'
);
$stmt->execute([$id, $userId]);

echo json_encode(['ok' => true]);
