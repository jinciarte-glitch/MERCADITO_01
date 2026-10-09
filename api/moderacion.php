<?php
// api/moderacion.php — Acciones del administrador sobre usuarios y emprendimientos
//  GET   -> lista lo suspendido ahora + historial reciente
//  POST  -> { accion: suspender|levantar|eliminar, tipo: usuario|emprendimiento, id, dias?, motivo? }
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/moderacion-lib.php';
require_login();

$me = (int)current_user_id();

if (!is_administrador($pdo)) {
    http_response_code(403);
    exit(json_encode(['error' => 'Solo el administrador puede hacer esto.']));
}

// ---------------------------------------------------------------------------
//  GET — suspendidos ahora + historial
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Historial con búsqueda: ?q=texto (nombre o motivo) y/o ?fecha=AAAA-MM-DD.
    // Sin filtros se devuelven las últimas 15 acciones; con filtros, hasta 100.
    $q     = trim((string)($_GET['q'] ?? ''));
    $fecha = trim((string)($_GET['fecha'] ?? ''));
    $where = [];
    $params = [];
    if ($q !== '') {
        $like = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
        $where[] = '(l.objetivo_nombre LIKE ? OR l.motivo LIKE ?)';
        $params[] = $like;
        $params[] = $like;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $where[] = 'DATE(l.created_at) = ?';
        $params[] = $fecha;
    }
    $limite = $where ? 100 : 15;

    $log = [];
    try {
        $st = $pdo->prepare(
            'SELECT l.accion, l.tipo, l.objetivo_nombre, l.dias, l.motivo, l.admin_id,
                    UNIX_TIMESTAMP(l.created_at) AS created_at_unix, a.full_name AS admin_nombre
             FROM moderacion_log l LEFT JOIN users a ON a.id = l.admin_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . '
             ORDER BY l.created_at DESC, l.id DESC LIMIT ' . $limite
        );
        $st->execute($params);
        $log = $st->fetchAll();
    } catch (PDOException $e) { /* tabla aún no creada */ }

    $historialJson = array_map(fn($r) => [
        'accion' => $r['accion'], 'tipo' => $r['tipo'], 'nombre' => $r['objetivo_nombre'],
        'dias' => $r['dias'] !== null ? (int)$r['dias'] : null, 'motivo' => $r['motivo'],
        'automatica' => $r['admin_id'] === null, 'admin' => $r['admin_nombre'], 'fecha' => (int)$r['created_at_unix'] * 1000,
    ], $log);

    // Mientras se escribe en el buscador solo se pide el historial.
    if (isset($_GET['solo_historial'])) {
        echo json_encode(['historial' => $historialJson]);
        exit;
    }

    $usuarios = $pdo->query(
        'SELECT u.id, u.full_name, u.email, UNIX_TIMESTAMP(u.suspendido_hasta) AS hasta, u.suspension_motivo AS motivo
         FROM users u WHERE u.suspendido_hasta IS NOT NULL AND u.suspendido_hasta > NOW()
         ORDER BY u.suspendido_hasta ASC'
    )->fetchAll();

    $tiendas = $pdo->query(
        'SELECT s.id, s.name, u.full_name AS owner_name, UNIX_TIMESTAMP(s.suspendido_hasta) AS hasta, s.suspension_motivo AS motivo
         FROM shops s JOIN users u ON u.id = s.user_id
         WHERE s.suspendido_hasta IS NOT NULL AND s.suspendido_hasta > NOW()
         ORDER BY s.suspendido_hasta ASC'
    )->fetchAll();

    // (el historial ya se calculó arriba)
    echo json_encode([
        'usuarios' => array_map(fn($r) => [
            'id' => (int)$r['id'], 'nombre' => $r['full_name'], 'email' => $r['email'],
            'hasta' => (int)$r['hasta'] * 1000, 'motivo' => $r['motivo'],
        ], $usuarios),
        'emprendimientos' => array_map(fn($r) => [
            'id' => (int)$r['id'], 'nombre' => $r['name'], 'dueno' => $r['owner_name'],
            'hasta' => (int)$r['hasta'] * 1000, 'motivo' => $r['motivo'],
        ], $tiendas),
        'historial' => $historialJson,
    ]);
    exit;
}

// ---------------------------------------------------------------------------
//  POST — suspender / levantar / eliminar
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $data   = json_input();
    $accion = $data['accion'] ?? '';
    $tipo   = $data['tipo'] ?? '';
    $id     = (int)($data['id'] ?? 0);

    if (!in_array($accion, ['suspender', 'levantar', 'eliminar'], true)
        || !in_array($tipo, ['usuario', 'emprendimiento'], true) || $id <= 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'Pedido inválido.']));
    }

    $nombre = mod_nombre($pdo, $tipo, $id);
    if ($nombre === null) {
        http_response_code(404);
        exit(json_encode(['error' => 'Eso ya no existe.']));
    }

    // Protecciones: ni a vos mismo, ni a otro administrador (ni a su tienda de pruebas).
    if ($tipo === 'usuario') {
        $duenoId = $id;
    } else {
        $s = $pdo->prepare('SELECT user_id FROM shops WHERE id = ? LIMIT 1');
        $s->execute([$id]);
        $duenoId = (int)$s->fetchColumn();
    }
    if ($duenoId === $me || mod_rol_de($pdo, $duenoId) === 'administrador') {
        http_response_code(400);
        exit(json_encode(['error' => 'No se puede aplicar esta acción sobre una cuenta de administrador.']));
    }

    if ($accion === 'suspender') {
        $dias   = (int)($data['dias'] ?? 0);
        $motivo = trim((string)($data['motivo'] ?? ''));
        if ($dias < 1 || $dias > 365) {
            http_response_code(400);
            exit(json_encode(['error' => 'Los días de suspensión tienen que ser entre 1 y 365.']));
        }
        $avisado = mod_suspender($pdo, $tipo, $id, $dias, $motivo, $me);
        echo json_encode(['ok' => true, 'mensaje' => "«$nombre» quedó suspendido por $dias día" . ($dias === 1 ? '' : 's') . '.'
            . ($avisado ? ' Se le avisó por mail.' : ' No se pudo enviar el mail de aviso.')]);
        exit;
    }

    if ($accion === 'levantar') {
        mod_levantar($pdo, $tipo, $id, $me);
        echo json_encode(['ok' => true, 'mensaje' => "Se levantó la suspensión de «$nombre»."]);
        exit;
    }

    // eliminar — se guardan antes los datos de contacto, porque después ya no existen
    $motivo = mb_substr(trim((string)($data['motivo'] ?? '')), 0, 255);

    if ($tipo === 'usuario') {
        $c = $pdo->prepare('SELECT email, full_name FROM users WHERE id = ?');
        $c->execute([$id]);
        $contacto = $c->fetch();
        $t = $pdo->prepare('SELECT name FROM shops WHERE user_id = ?');
        $t->execute([$id]);
        $tiendas = $t->fetchAll(PDO::FETCH_COLUMN);

        // Quienes habían denunciado a esta persona (o lo suyo) reciben el resultado.
        $shopIds = mod_ids($pdo, 'SELECT id FROM shops WHERE user_id = ?', [$id]);
        $postIds = mod_ids($pdo, 'SELECT id FROM posts WHERE user_id = ?', [$id]);
        $comIds  = mod_ids($pdo, 'SELECT id FROM comments WHERE user_id = ?', [$id]);
        mod_resolver_denuncias($pdo, 'usuario', [$id], null, "el usuario «$nombre»", 'se eliminó la cuenta.');
        mod_resolver_denuncias($pdo, 'emprendimiento', $shopIds, null, "un emprendimiento de «$nombre»", 'se eliminó la cuenta de su dueño y, con ella, el emprendimiento.');
        mod_resolver_denuncias($pdo, 'publicacion', $postIds, null, "una publicación de «$nombre»", 'se eliminó la cuenta de su autor.');
        mod_resolver_denuncias($pdo, 'comentario', $comIds, null, "un comentario de «$nombre»", 'se eliminó la cuenta de su autor.');

        mod_eliminar_usuario($pdo, $id, $me);   // borra la cuenta y también sus emprendimientos

        $parrafos = ['Tu cuenta de Mercadito fue eliminada por el equipo de la página.'];
        if ($tiendas) {
            $parrafos[] = (count($tiendas) === 1 ? 'También se eliminó tu emprendimiento «' : 'También se eliminaron tus emprendimientos «')
                        . implode('», «', $tiendas) . '».';
        }
        $parrafos[] = 'Con la cuenta se borraron tus publicaciones, comentarios y mensajes.';
        $avisado = mod_enviar_aviso($contacto['email'] ?? null, $contacto['full_name'] ?? null,
                                    'Tu cuenta de Mercadito fue eliminada', $parrafos, $motivo);
        $extra = $tiendas ? ' Su emprendimiento también se eliminó.' : '';
    } else {
        $c = $pdo->prepare('SELECT u.id AS uid, u.email, u.full_name FROM shops s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
        $c->execute([$id]);
        $contacto = $c->fetch();

        mod_resolver_denuncias($pdo, 'emprendimiento', [$id], null, "el emprendimiento «$nombre»", 'se eliminó el emprendimiento.');

        // Se elimina el emprendimiento. La cuenta de su creador NO se borra, pero pierde el rol
        // de emprendedor de forma permanente (no puede crear otro ni volver a pedirlo).
        mod_eliminar_emprendimiento($pdo, $id, $me);
        $rolEstado = !empty($contacto['uid']) ? mod_quitar_rol_emprendedor($pdo, (int)$contacto['uid']) : 'nada';

        $avisoRol = $rolEstado !== 'nada'
            ? 'Tu cuenta sigue activa, pero se te retiró de forma permanente el rol de emprendedor: no vas a poder crear otro emprendimiento ni volver a pedir ese rol.'
            : 'Tu cuenta sigue activa.';

        // Aviso en pantalla para cuando el dueño vuelva a entrar.
        if (!empty($contacto['uid'])) {
            mod_notificar($pdo, (int)$contacto['uid'], 'emprendimiento_eliminado', 'Tu emprendimiento fue eliminado',
                "Tu emprendimiento «$nombre» fue eliminado por el equipo de la página. $avisoRol"
                . ($motivo !== '' ? " Motivo: $motivo" : ''));
        }

        $avisado = mod_enviar_aviso($contacto['email'] ?? null, $contacto['full_name'] ?? null,
            'Tu emprendimiento de Mercadito fue eliminado',
            ["Tu emprendimiento «$nombre» fue eliminado por el equipo de la página.", $avisoRol], $motivo);

        $extra = $rolEstado === 'permanente' ? ' Se le retiró el rol de emprendedor de forma permanente.'
               : ($rolEstado === 'parcial' ? ' Se le bajó el rol de emprendedor, pero para que sea permanente falta correr emprendedor-bloqueado.sql.' : '');
    }

    echo json_encode(['ok' => true, 'mensaje' => "«$nombre» fue eliminado." . $extra
        . ($avisado ? ' Se le avisó por mail.' : ' No se pudo enviar el mail de aviso.')]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido.']);