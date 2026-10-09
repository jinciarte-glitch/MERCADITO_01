<?php
// api/conversations.php — conversaciones privadas
//  GET            -> lista mis conversaciones (con el otro usuario, último
//                    mensaje y cantidad de no leídos)
//  GET ?id=X      -> una conversación: datos del otro + todos los mensajes
//                    (marca como leídos los mensajes que me mandaron)
//  POST           -> inicia (o reusa) una conversación. Body: { shopId }
//                    o { emprendedorId }. SÓLO un cliente puede iniciar.
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/moderacion-lib.php';
require_login();

$me     = current_user_id();
$myType = current_profile_type($pdo);

// Devuelve el rol ('cliente' | 'emprendedor') de un usuario por id, o null.
function role_of($pdo, $userId) {
    $stmt = $pdo->prepare(
        'SELECT r.nombre FROM users u JOIN roles r ON r.roles_id = u.roles_id WHERE u.id = ? LIMIT 1'
    );
    $stmt->execute([$userId]);
    $r = $stmt->fetchColumn();
    return $r !== false ? $r : null;
}

// Devuelve la fila de la conversación si el usuario logueado participa; si no, null.
function conversation_for_me($pdo, $convId, $me) {
    $stmt = $pdo->prepare(
        'SELECT * FROM conversations WHERE id = ? AND (cliente_id = ? OR emprendedor_id = ?) LIMIT 1'
    );
    $stmt->execute([$convId, $me, $me]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Perfil visible en el chat: si el usuario tiene emprendimiento (tienda),
// se muestra LA TIENDA (nombre + logo), no el nombre de la persona.
// Si no tiene tienda (un cliente), se muestra su usuario normal.
function display_profile($pdo, $userId) {
    $stmt = $pdo->prepare('SELECT id, full_name, avatar_url FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if (!$u) return null;
    $out = [
        'id'        => (int)$u['id'],
        'name'      => $u['full_name'],
        'avatarUrl' => $u['avatar_url'],
        'shopId'    => null,
        'isShop'    => false,
    ];
    try {
        $s = $pdo->prepare('SELECT id, name, avatar_url FROM shops WHERE user_id = ? LIMIT 1');
        $s->execute([$userId]);
        $shop = $s->fetch();
        if ($shop) {
            $out['name'] = $shop['name'];
            if (!empty($shop['avatar_url'])) $out['avatarUrl'] = $shop['avatar_url'];
            $out['shopId'] = (int)$shop['id'];
            $out['isShop'] = true;
        }
    } catch (PDOException $e) {
        // Si la tabla shops no existe/tiene otro esquema: perfil de usuario.
    }
    return $out;
}

function user_public($pdo, $userId) {
    return display_profile($pdo, $userId);
}

// ===========================================================================
//  GET
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // ---- Una conversación puntual + sus mensajes ----
    if (isset($_GET['id'])) {
        $conv = conversation_for_me($pdo, (int)$_GET['id'], $me);
        if (!$conv) {
            http_response_code(404);
            exit(json_encode(['error' => 'Conversación no encontrada.']));
        }

        $otherId = ((int)$conv['cliente_id'] === (int)$me)
            ? (int)$conv['emprendedor_id']
            : (int)$conv['cliente_id'];

        // Estado de bloqueo entre los dos usuarios.
        $chkA = $pdo->prepare('SELECT 1 FROM blocks WHERE blocker_id = ? AND blocked_id = ? LIMIT 1');
        $chkA->execute([$me, $otherId]);
        $blockedByMe = (bool)$chkA->fetchColumn();

        $chkB = $pdo->prepare('SELECT 1 FROM blocks WHERE blocker_id = ? AND blocked_id = ? LIMIT 1');
        $chkB->execute([$otherId, $me]);
        $blockedMe = (bool)$chkB->fetchColumn();

        // Marcar como leídos los mensajes que me mandó el otro.
        $upd = $pdo->prepare(
            'UPDATE messages SET read_at = NOW()
             WHERE conversation_id = ? AND sender_id <> ? AND read_at IS NULL'
        );
        $upd->execute([$conv['id'], $me]);

        $delSel = '';
        try {
            $chk = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'deleted_at'"
            )->fetchColumn();
            if ($chk) $delSel = ', deleted_at';
        } catch (PDOException $e) { /* sin borrado lógico */ }
        $msgStmt = $pdo->prepare(
            'SELECT id, sender_id, body, UNIX_TIMESTAMP(created_at) AS created_at_unix' . $delSel . ' FROM messages
             WHERE conversation_id = ? ORDER BY id ASC'
        );
        $msgStmt->execute([$conv['id']]);
        $messages = array_map(function ($m) use ($me) {
            $deleted = isset($m['deleted_at']) && $m['deleted_at'] !== null;
            return [
                'id'        => (int)$m['id'],
                'mine'      => ((int)$m['sender_id'] === (int)$me),
                'text'      => $deleted ? '' : $m['body'],
                'deleted'   => $deleted,
                'createdAt' => (int)$m['created_at_unix'] * 1000,
            ];
        }, $msgStmt->fetchAll());

        echo json_encode([
            'id'          => (int)$conv['id'],
            'other'       => user_public($pdo, $otherId),
            'messages'    => $messages,
            'blockedByMe' => $blockedByMe,
            'blockedMe'   => $blockedMe,
        ]);
        exit;
    }

    // ---- Lista de mis conversaciones ----
    // El "otro" se muestra como EMPRENDIMIENTO (tienda) si tiene uno.
    // Si la tabla shops no existe, se usa el perfil de usuario clásico.
    $hasShops = true;
    try {
        $pdo->query("SELECT 1 FROM shops LIMIT 1");
    } catch (PDOException $e) {
        $hasShops = false;
    }
    $shopJoin   = $hasShops ? 'LEFT JOIN shops s ON s.user_id = u.id' : '';
    $shopName   = $hasShops ? 'COALESCE(s.name, u.full_name)' : 'u.full_name';
    $shopAvatar = $hasShops ? 'COALESCE(s.avatar_url, u.avatar_url)' : 'u.avatar_url';
    $shopIdSel  = $hasShops ? 's.id AS shop_id,' : 'NULL AS shop_id,';

    $hasDeleted = false;
    try {
        $hasDeleted = (bool)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages' AND COLUMN_NAME = 'deleted_at'"
        )->fetchColumn();
    } catch (PDOException $e) { /* sin borrado lógico */ }
    $lastDelSel = $hasDeleted
        ? '(SELECT m.deleted_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_deleted,'
        : 'NULL AS last_deleted,';

    $sql = "
        SELECT c.id,
          (CASE WHEN c.cliente_id = :me THEN c.emprendedor_id ELSE c.cliente_id END) AS other_id,
          $shopName  AS other_name,
          $shopAvatar AS other_avatar,
          $shopIdSel
          (SELECT m.body                         FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_body,
          $lastDelSel
          (SELECT UNIX_TIMESTAMP(m.created_at)   FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_at_unix,
          (SELECT COUNT(*)     FROM messages m WHERE m.conversation_id = c.id AND m.sender_id <> :me AND m.read_at IS NULL) AS unread
        FROM conversations c
        JOIN users u ON u.id = (CASE WHEN c.cliente_id = :me THEN c.emprendedor_id ELSE c.cliente_id END)
        $shopJoin
        WHERE c.cliente_id = :me OR c.emprendedor_id = :me
        ORDER BY (last_at_unix IS NULL), last_at_unix DESC, c.updated_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':me' => $me]);

    $conversations = array_map(function ($row) {
        return [
            'id'          => (int)$row['id'],
            'otherId'     => (int)$row['other_id'],
            'otherName'   => $row['other_name'],
            'otherAvatar' => $row['other_avatar'],
            'shopId'      => $row['shop_id'] !== null ? (int)$row['shop_id'] : null,
            'isShop'      => $row['shop_id'] !== null,
            'lastText'    => $row['last_body'],
            'lastDeleted' => !empty($row['last_deleted']),
            'lastAt'      => $row['last_at_unix'] !== null ? (int)$row['last_at_unix'] * 1000 : null,
            'unread'      => (int)$row['unread'],
        ];
    }, $stmt->fetchAll());

    echo json_encode(['conversations' => $conversations]);
    exit;
}

// ===========================================================================
//  POST — iniciar (o reusar) una conversación. Sólo clientes.
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($myType !== 'cliente') {
        http_response_code(403);
        exit(json_encode(['error' => 'Sólo los clientes pueden iniciar una conversación. Los emprendedores pueden responder, pero no escribir primero.']));
    }

    exigir_no_suspendido($pdo, (int)$me); // un suspendido no puede escribir primero

    $data          = json_input();
    $emprendedorId = 0;

    if (!empty($data['shopId'])) {
        $stmt = $pdo->prepare('SELECT user_id FROM shops WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$data['shopId']]);
        $emprendedorId = (int)($stmt->fetchColumn() ?: 0);
    } elseif (!empty($data['emprendedorId'])) {
        $emprendedorId = (int)$data['emprendedorId'];
    }

    if ($emprendedorId <= 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'No se pudo identificar al emprendedor.']));
    }
    if ($emprendedorId === (int)$me) {
        http_response_code(400);
        exit(json_encode(['error' => 'No podés iniciar una conversación con vos misma.']));
    }
    if (role_of($pdo, $emprendedorId) !== 'emprendedor') {
        http_response_code(400);
        exit(json_encode(['error' => 'Sólo se puede escribir a cuentas de emprendedor.']));
    }

    // No se puede iniciar conversación si hay un bloqueo (en cualquier sentido).
    $chkBlock = $pdo->prepare(
        'SELECT 1 FROM blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?) LIMIT 1'
    );
    $chkBlock->execute([$me, $emprendedorId, $emprendedorId, $me]);
    if ($chkBlock->fetchColumn()) {
        http_response_code(403);
        exit(json_encode(['error' => 'No podés iniciar una conversación con este usuario.']));
    }

    // ¿Ya existe? (par único cliente+emprendedor)
    $find = $pdo->prepare(
        'SELECT id FROM conversations WHERE cliente_id = ? AND emprendedor_id = ? LIMIT 1'
    );
    $find->execute([$me, $emprendedorId]);
    $existing = $find->fetch();

    if ($existing) {
        echo json_encode(['id' => (int)$existing['id'], 'reused' => true]);
        exit;
    }

    $ins = $pdo->prepare(
        'INSERT INTO conversations (cliente_id, emprendedor_id) VALUES (?, ?)'
    );
    $ins->execute([$me, $emprendedorId]);

    echo json_encode(['id' => (int)$pdo->lastInsertId(), 'reused' => false]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido.']);