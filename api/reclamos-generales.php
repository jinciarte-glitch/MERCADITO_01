<?php
// api/reclamos-generales.php — Reclamos sobre la página en sí (no sobre un emprendimiento)
//  GET           -> (admin) lista los reclamos. ?estado=pendiente|resuelto|todos
//  POST          -> (cliente o emprendedor logueado) crea un reclamo. Body: { mensaje }
//  POST ?id=X    -> (admin) marca un reclamo como resuelto/pendiente. Body: { estado }
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$me = current_user_id();

function reclamo_general_public(array $row): array {
    return [
        'id'        => (int)$row['id'],
        'mensaje'   => $row['mensaje'],
        'estado'    => $row['estado'],
        'createdAt' => (int)$row['created_at_unix'] * 1000,
        'usuario'   => [
            'id'    => (int)$row['user_id'],
            'name'  => $row['user_name'],
            'email' => $row['user_email'],
        ],
    ];
}

// ---------------------------------------------------------------------------
//  GET — sólo el administrador
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!is_administrador($pdo)) {
        http_response_code(403);
        exit(json_encode(['error' => 'No tenés permiso para ver los reclamos.']));
    }

    $estado = $_GET['estado'] ?? 'pendiente';

    $sql = 'SELECT r.*, UNIX_TIMESTAMP(r.created_at) AS created_at_unix,
                   u.full_name AS user_name, u.email AS user_email
            FROM reclamos_generales r
            JOIN users u ON u.id = r.user_id';
    $params = [];
    if (in_array($estado, ['pendiente', 'resuelto'], true)) {
        $sql .= ' WHERE r.estado = ?';
        $params[] = $estado;
    }
    $sql .= ' ORDER BY r.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $counts = $pdo->query('SELECT estado, COUNT(*) FROM reclamos_generales GROUP BY estado')
                  ->fetchAll(PDO::FETCH_KEY_PAIR);

    echo json_encode([
        'reclamos' => array_map('reclamo_general_public', $stmt->fetchAll()),
        'counts'   => [
            'pendiente' => (int)($counts['pendiente'] ?? 0),
            'resuelto'  => (int)($counts['resuelto'] ?? 0),
        ],
    ]);
    exit;
}

// ---------------------------------------------------------------------------
//  POST
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_input();

    // ---- Admin: marcar como resuelto / pendiente ----
    if (isset($_GET['id'])) {
        if (!is_administrador($pdo)) {
            http_response_code(403);
            exit(json_encode(['error' => 'No tenés permiso para resolver reclamos.']));
        }
        csrf_check();
        $id     = (int)$_GET['id'];
        $estado = ($data['estado'] ?? '') === 'resuelto' ? 'resuelto' : 'pendiente';

        $upd = $pdo->prepare('UPDATE reclamos_generales SET estado = ? WHERE id = ?');
        $upd->execute([$estado, $id]);

        echo json_encode(['ok' => true]);
        exit;
    }

    // ---- Cliente o emprendedor logueado: crear un reclamo ----
    if (!in_array(current_profile_type($pdo), ['cliente', 'emprendedor'], true)) {
        http_response_code(403);
        exit(json_encode(['error' => 'Sólo los clientes y emprendedores pueden enviar un reclamo.']));
    }

    $mensaje = trim($data['mensaje'] ?? '');

    if ($mensaje === '') {
        http_response_code(400);
        exit(json_encode(['error' => 'Contanos qué pasó antes de enviar el reclamo.']));
    }
    if (mb_strlen($mensaje) > 1000) {
        http_response_code(400);
        exit(json_encode(['error' => 'El reclamo es demasiado largo (máx. 1000 caracteres).']));
    }

    $ins = $pdo->prepare('INSERT INTO reclamos_generales (user_id, mensaje) VALUES (?, ?)');
    $ins->execute([$me, $mensaje]);

    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido.']);