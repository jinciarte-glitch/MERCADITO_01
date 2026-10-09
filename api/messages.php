<?php
// api/messages.php — mensajes dentro de una conversación
//  POST                              -> envía { conversationId, text }
//                                       (rechaza si hay un bloqueo entre los dos)
//  GET ?conversationId=X&after=Y     -> mensajes con id > Y (para el "polling"
//                                       que refresca la conversación sin recargar).
//                                       Marca como leídos los del otro.
//  DELETE ?id=X                      -> elimina un mensaje mío
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/moderacion-lib.php';
require_login();

$me = current_user_id();

// ¿La tabla messages ya tiene la columna de borrado lógico?
// (Si el ALTER de bootstrap todavía no corrió o falló por permisos,
// se sigue con borrado físico para no romper nada.)
function has_deleted_col($pdo) {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $cols = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $has = in_array('deleted_at', $cols ?: [], true);
    } catch (PDOException $e) {
        $has = false;
    }
    return $has;
}

// Mapea una fila de mensaje al formato de la API.
// Eliminados: no se expone el texto, solo la marca (estilo WhatsApp).
function map_message($m, $me) {
    $deleted = isset($m['deleted_at']) && $m['deleted_at'] !== null;
    return [
        'id'        => (int)$m['id'],
        'mine'      => ((int)$m['sender_id'] === (int)$me),
        'text'      => $deleted ? '' : $m['body'],
        'deleted'   => $deleted,
        'createdAt' => (int)$m['created_at_unix'] * 1000,
    ];
}

// Ids de mensajes eliminados de una conversación (para que el polling
// actualice burbujas viejas sin recargar la página).
function deleted_ids($pdo, $convId) {
    if (!has_deleted_col($pdo)) return [];
    $s = $pdo->prepare('SELECT id FROM messages WHERE conversation_id = ? AND deleted_at IS NOT NULL');
    $s->execute([$convId]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

// Devuelve la conversación si el usuario logueado participa; si no, null.
function my_conversation($pdo, $convId, $me) {
    $stmt = $pdo->prepare(
        'SELECT * FROM conversations WHERE id = ? AND (cliente_id = ? OR emprendedor_id = ?) LIMIT 1'
    );
    $stmt->execute([$convId, $me, $me]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ===========================================================================
//  GET — traer mensajes nuevos (polling)
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $convId = (int)($_GET['conversationId'] ?? 0);
    $after  = (int)($_GET['after'] ?? 0);

    $conv = my_conversation($pdo, $convId, $me);
    if (!$conv) {
        http_response_code(404);
        exit(json_encode(['error' => 'Conversación no encontrada.']));
    }

    // Marcar como leídos los que me mandaron.
    $upd = $pdo->prepare(
        'UPDATE messages SET read_at = NOW()
         WHERE conversation_id = ? AND sender_id <> ? AND read_at IS NULL'
    );
    $upd->execute([$convId, $me]);

    $delSel = has_deleted_col($pdo) ? ', deleted_at' : ', NULL AS deleted_at';
    $stmt = $pdo->prepare(
        'SELECT id, sender_id, body, UNIX_TIMESTAMP(created_at) AS created_at_unix' . $delSel . ' FROM messages
         WHERE conversation_id = ? AND id > ? ORDER BY id ASC'
    );
    $stmt->execute([$convId, $after]);

    $messages = array_map(function ($m) use ($me) {
        return map_message($m, $me);
    }, $stmt->fetchAll());

    echo json_encode(['messages' => $messages, 'deletedIds' => deleted_ids($pdo, $convId)]);
    exit;
}

// ===========================================================================
//  POST — enviar un mensaje (cliente o emprendedor: los dos pueden responder)
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mod_exigir_puede_escribir($pdo, (int)$me); // suspendidos y spam
    $data   = json_input();
    $convId = (int)($data['conversationId'] ?? 0);
    $text   = trim($data['text'] ?? '');

    if ($text === '' || mb_strlen($text) > 1000) {
        http_response_code(400);
        exit(json_encode(['error' => 'El mensaje debe tener entre 1 y 1000 caracteres.']));
    }

    $conv = my_conversation($pdo, $convId, $me);
    if (!$conv) {
        http_response_code(404);
        exit(json_encode(['error' => 'Conversación no encontrada.']));
    }

    // No se puede enviar si hay un bloqueo (en cualquier sentido) entre los dos.
    $otherId = ((int)$conv['cliente_id'] === (int)$me) ? (int)$conv['emprendedor_id'] : (int)$conv['cliente_id'];
    $chkBlock = $pdo->prepare(
        'SELECT 1 FROM blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?) LIMIT 1'
    );
    $chkBlock->execute([$me, $otherId, $otherId, $me]);
    if ($chkBlock->fetchColumn()) {
        http_response_code(403);
        exit(json_encode(['error' => 'No podés enviar mensajes a este usuario.']));
    }

    $ins = $pdo->prepare(
        'INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)'
    );
    $ins->execute([$convId, $me, $text]);
    $id = (int)$pdo->lastInsertId();

    // Bump del updated_at para que la conversación suba en la lista.
    $pdo->prepare('UPDATE conversations SET updated_at = NOW() WHERE id = ?')->execute([$convId]);

    // Traemos la hora que MySQL le puso al mensaje (no time() de PHP), para
    // que sea consistente con lo que después devuelve el polling del GET.
    $creado = $pdo->prepare('SELECT UNIX_TIMESTAMP(created_at) FROM messages WHERE id = ?');
    $creado->execute([$id]);
    $createdAtUnix = (int)$creado->fetchColumn();

    echo json_encode([
        'message' => [
            'id'        => $id,
            'mine'      => true,
            'text'      => $text,
            'deleted'   => false,
            'createdAt' => $createdAtUnix * 1000,
        ],
    ]);
    exit;
}

// ===========================================================================
//  DELETE ?id=X — "eliminar" un mensaje mío (borrado lógico estilo WhatsApp:
//  queda la marca "Mensaje eliminado" visible para los dos)
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'Falta el id del mensaje.']));
    }

    // Sólo puedo borrar mensajes que yo mismo envié.
    if (has_deleted_col($pdo)) {
        $stmt = $pdo->prepare('UPDATE messages SET deleted_at = NOW() WHERE id = ? AND sender_id = ? AND deleted_at IS NULL');
    } else {
        // Sin la columna (hosting sin permiso ALTER): borrado físico clásico.
        $stmt = $pdo->prepare('DELETE FROM messages WHERE id = ? AND sender_id = ?');
    }
    $stmt->execute([$id, $me]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        exit(json_encode(['error' => 'No se pudo eliminar el mensaje.']));
    }

    echo json_encode(['ok' => true, 'deleted' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido.']);