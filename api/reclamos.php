<?php
// api/reclamos.php — Reclamos de clientes hacia un emprendimiento
//  GET             -> lista los reclamos de MI tienda (sólo emprendedor dueño)
//  POST            -> crea un reclamo. Body: { shopId, mensaje }. Sólo un cliente puede reclamar.
//  POST ?id=X       -> marca un reclamo mío (de mi tienda) como resuelto/pendiente. Body: { estado }
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$me     = current_user_id();
$myType = current_profile_type($pdo);

function reclamo_public(array $row): array {
    return [
        'id'        => (int)$row['id'],
        'mensaje'   => $row['mensaje'],
        'estado'    => $row['estado'],
        'createdAt' => (int)$row['created_at_unix'] * 1000,
        'cliente'   => [
            'id'        => (int)$row['cliente_id'],
            'name'      => $row['cliente_name'],
            'avatarUrl' => $row['cliente_avatar'],
        ],
    ];
}

// ===========================================================================
//  GET — lista los reclamos de mi propia tienda (sólo el emprendedor dueño)
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $shopStmt = $pdo->prepare('SELECT id FROM shops WHERE user_id = ? LIMIT 1');
    $shopStmt->execute([$me]);
    $shopId = $shopStmt->fetchColumn();

    if (!$shopId) {
        echo json_encode(['reclamos' => []]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT r.*, UNIX_TIMESTAMP(r.created_at) AS created_at_unix, u.full_name AS cliente_name, u.avatar_url AS cliente_avatar
         FROM reclamos r JOIN users u ON u.id = r.cliente_id
         WHERE r.shop_id = ?
         ORDER BY (r.estado = "pendiente") DESC, r.created_at DESC'
    );
    $stmt->execute([$shopId]);

    $reclamos = array_map('reclamo_public', $stmt->fetchAll());
    echo json_encode(['reclamos' => $reclamos]);
    exit;
}

// ===========================================================================
//  POST
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_input();

    // ---- Marcar un reclamo existente (de mi tienda) como resuelto/pendiente ----
    if (isset($_GET['id'])) {
        $reclamoId = (int)$_GET['id'];
        $estado    = ($data['estado'] ?? '') === 'resuelto' ? 'resuelto' : 'pendiente';

        $chk = $pdo->prepare(
            'SELECT r.id FROM reclamos r JOIN shops s ON s.id = r.shop_id
             WHERE r.id = ? AND s.user_id = ? LIMIT 1'
        );
        $chk->execute([$reclamoId, $me]);
        if (!$chk->fetchColumn()) {
            http_response_code(404);
            exit(json_encode(['error' => 'Reclamo no encontrado.']));
        }

        $upd = $pdo->prepare('UPDATE reclamos SET estado = ? WHERE id = ?');
        $upd->execute([$estado, $reclamoId]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // ---- Crear un reclamo nuevo. Sólo clientes. ----
    if ($myType !== 'cliente') {
        http_response_code(403);
        exit(json_encode(['error' => 'Sólo los clientes pueden hacer un reclamo a un emprendimiento.']));
    }

    $shopId  = (int)($data['shopId'] ?? 0);
    $mensaje = trim($data['mensaje'] ?? '');

    if ($shopId <= 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'No se pudo identificar el emprendimiento.']));
    }
    if ($mensaje === '') {
        http_response_code(400);
        exit(json_encode(['error' => 'Contanos qué pasó antes de enviar el reclamo.']));
    }
    if (mb_strlen($mensaje) > 1000) {
        http_response_code(400);
        exit(json_encode(['error' => 'El reclamo es demasiado largo (máx. 1000 caracteres).']));
    }

    $shopStmt = $pdo->prepare('SELECT id FROM shops WHERE id = ? LIMIT 1');
    $shopStmt->execute([$shopId]);
    if (!$shopStmt->fetchColumn()) {
        http_response_code(404);
        exit(json_encode(['error' => 'Ese emprendimiento no existe.']));
    }

    $ins = $pdo->prepare(
        'INSERT INTO reclamos (shop_id, cliente_id, mensaje) VALUES (?, ?, ?)'
    );
    $ins->execute([$shopId, $me, $mensaje]);

    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido.']);
