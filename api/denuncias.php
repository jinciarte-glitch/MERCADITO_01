<?php
// api/denuncias.php — Denuncias hacia publicaciones, comentarios, usuarios y emprendimientos
//  POST          -> (usuario logueado) crea una denuncia.
//                   Body: { tipo, objetivoId, motivo, detalle? }
//  GET           -> (admin) lista las denuncias. ?estado=pendiente|resuelta|descartada|todas
//  POST ?id=X    -> (admin) cambia el estado. Body: { estado: pendiente|resuelta|descartada }
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/moderacion-lib.php';
require_login();

$me = current_user_id();

const DENUNCIA_TIPOS   = ['publicacion', 'comentario', 'usuario', 'emprendimiento'];
const DENUNCIA_MOTIVOS = ['spam', 'contenido_inapropiado', 'acoso', 'estafa', 'otro'];
const DENUNCIA_ESTADOS = ['pendiente', 'resuelta', 'descartada'];

// Tabla donde vive cada tipo de objetivo denunciable.
const DENUNCIA_TABLAS = [
    'publicacion'    => 'posts',
    'comentario'     => 'comments',
    'usuario'        => 'users',
    'emprendimiento' => 'shops',
];

// Texto corto + link para que el admin vea de qué se trata la denuncia.
function denuncia_objetivo(PDO $pdo, string $tipo, int $id): array {
    $info = ['label' => '(ya no existe)', 'url' => null, 'autorId' => null];

    if ($tipo === 'usuario') {
        $s = $pdo->prepare('SELECT full_name FROM users WHERE id = ?');
        $s->execute([$id]);
        if (($v = $s->fetchColumn()) !== false) {
            $info = ['label' => $v, 'url' => 'usuario.php?id=' . $id];
        }
    } elseif ($tipo === 'emprendimiento') {
        $s = $pdo->prepare('SELECT name FROM shops WHERE id = ?');
        $s->execute([$id]);
        if (($v = $s->fetchColumn()) !== false) {
            $info = ['label' => $v, 'url' => 'tienda.php?id=' . $id];
        }
    } elseif ($tipo === 'publicacion') {
        $s = $pdo->prepare('SELECT body, user_id FROM posts WHERE id = ?');
        $s->execute([$id]);
        if ($row = $s->fetch()) {
            $info = ['label' => mb_substr((string)$row['body'], 0, 160), 'url' => 'post.php?id=' . $id, 'autorId' => (int)$row['user_id']];
        }
    } elseif ($tipo === 'comentario') {
        $s = $pdo->prepare('SELECT body, post_id, user_id FROM comments WHERE id = ?');
        $s->execute([$id]);
        if ($row = $s->fetch()) {
            $info = ['label' => mb_substr((string)$row['body'], 0, 160), 'url' => 'post.php?id=' . (int)$row['post_id'], 'autorId' => (int)$row['user_id']];
        }
    }
    return $info;
}

// ---------------------------------------------------------------------------
//  GET — sólo el administrador
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!is_administrador($pdo)) {
        http_response_code(403);
        exit(json_encode(['error' => 'No tenés permiso para ver las denuncias.']));
    }

    $estado = $_GET['estado'] ?? 'pendiente';

    $sql = 'SELECT d.*, UNIX_TIMESTAMP(d.created_at) AS created_at_unix,
                   u.full_name AS user_name, u.email AS user_email
            FROM denuncias d
            JOIN users u ON u.id = d.denunciante_id';
    $params = [];
    if (in_array($estado, DENUNCIA_ESTADOS, true)) {
        $sql .= ' WHERE d.estado = ?';
        $params[] = $estado;
    }
    $sql .= ' ORDER BY d.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $denuncias = array_map(function ($row) use ($pdo) {
        $obj = denuncia_objetivo($pdo, $row['tipo'], (int)$row['objetivo_id']);
        return [
            'id'         => (int)$row['id'],
            'tipo'       => $row['tipo'],
            'objetivoId' => (int)$row['objetivo_id'],
            'objetivo'   => $obj['label'],
            'url'        => $obj['url'],
            'autorId'    => $obj['autorId'],
            'motivo'     => $row['motivo'],
            'detalle'    => $row['detalle'],
            'estado'     => $row['estado'],
            'createdAt'  => (int)$row['created_at_unix'] * 1000,
            'denunciante' => [
                'id'    => (int)$row['denunciante_id'],
                'name'  => $row['user_name'],
                'email' => $row['user_email'],
            ],
        ];
    }, $stmt->fetchAll());

    $counts = $pdo->query('SELECT estado, COUNT(*) FROM denuncias GROUP BY estado')
                  ->fetchAll(PDO::FETCH_KEY_PAIR);

    echo json_encode([
        'denuncias' => $denuncias,
        'counts'    => [
            'pendiente'  => (int)($counts['pendiente'] ?? 0),
            'resuelta'   => (int)($counts['resuelta'] ?? 0),
            'descartada' => (int)($counts['descartada'] ?? 0),
        ],
    ]);
    exit;
}

// ---------------------------------------------------------------------------
//  POST
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_input();

    // ---- Admin: cambiar el estado de una denuncia ----
    if (isset($_GET['id'])) {
        if (!is_administrador($pdo)) {
            http_response_code(403);
            exit(json_encode(['error' => 'No tenés permiso para resolver denuncias.']));
        }
        csrf_check();
        $id     = (int)$_GET['id'];
        $estado = $data['estado'] ?? '';
        if (!in_array($estado, DENUNCIA_ESTADOS, true)) {
            http_response_code(400);
            exit(json_encode(['error' => 'Estado inválido.']));
        }

        $prev = $pdo->prepare(
            'SELECT d.tipo, d.objetivo_id, d.denunciante_id, d.estado, u.email, u.full_name
             FROM denuncias d JOIN users u ON u.id = d.denunciante_id WHERE d.id = ?'
        );
        $prev->execute([$id]);
        $d = $prev->fetch();
        if (!$d) {
            http_response_code(404);
            exit(json_encode(['error' => 'Esa denuncia ya no existe.']));
        }

        $mensaje   = mb_substr(trim((string)($data['mensaje'] ?? '')), 0, 300);
        $resultado = $estado === 'resuelta'
            ? 'el equipo la marcó como resuelta.'
            : 'el equipo decidió no tomar medidas por ahora.';
        // Texto que verá la persona en "Mis denuncias" (nada si se reabre).
        $resolucion = $estado === 'pendiente'
            ? null
            : mod_capitalizar($resultado) . ($mensaje !== '' ? " Mensaje del equipo: $mensaje" : '');

        mod_actualizar_estado_denuncias($pdo, $estado, 'id = ?', [$id], $resolucion); // anota cuándo se gestionó

        // Al resolverla o descartarla, se le cuenta el resultado a quien denunció.
        if ($d['estado'] !== $estado && in_array($estado, ['resuelta', 'descartada'], true)) {
            try {
                mod_avisar_resultado_denuncia(
                    $pdo, (int)$d['denunciante_id'], $d['email'], $d['full_name'],
                    mod_describir_objetivo($pdo, $d['tipo'], (int)$d['objetivo_id']),
                    $resultado, $mensaje
                );
            } catch (Throwable $e) {
                // el estado ya se guardó; el aviso es un extra
            }
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    // ---- Usuario logueado: crear una denuncia ----
    $tipo       = trim($data['tipo'] ?? '');
    $objetivoId = (int)($data['objetivoId'] ?? 0);
    $motivo     = trim($data['motivo'] ?? '');
    $detalle    = trim($data['detalle'] ?? '');

    if (!in_array($tipo, DENUNCIA_TIPOS, true) || $objetivoId <= 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'No se pudo identificar lo que querés denunciar.']));
    }
    if (!in_array($motivo, DENUNCIA_MOTIVOS, true)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Elegí un motivo para la denuncia.']));
    }
    if (mb_strlen($detalle) > 500) {
        http_response_code(400);
        exit(json_encode(['error' => 'El detalle es demasiado largo (máx. 500 caracteres).']));
    }

    // El objetivo tiene que existir. (El nombre de la tabla sale de una lista
    // fija, nunca del request, así que es seguro armarlo en el SQL.)
    $tabla = DENUNCIA_TABLAS[$tipo];
    $existe = $pdo->prepare("SELECT id FROM $tabla WHERE id = ? LIMIT 1");
    $existe->execute([$objetivoId]);
    if (!$existe->fetchColumn()) {
        http_response_code(404);
        exit(json_encode(['error' => 'Lo que querés denunciar ya no existe.']));
    }

    // No se puede denunciar a uno mismo ni al propio emprendimiento.
    if ($tipo === 'usuario' && $objetivoId === (int)$me) {
        http_response_code(400);
        exit(json_encode(['error' => 'No podés denunciarte a vos mismo.']));
    }
    if ($tipo === 'emprendimiento') {
        $dueno = $pdo->prepare('SELECT user_id FROM shops WHERE id = ? LIMIT 1');
        $dueno->execute([$objetivoId]);
        if ((int)$dueno->fetchColumn() === (int)$me) {
            http_response_code(400);
            exit(json_encode(['error' => 'No podés denunciar tu propio emprendimiento.']));
        }
    }

    // ¿Ya denunció esto antes? Si la anterior sigue pendiente, no. Si ya se gestionó, puede
    // volver a denunciar recién pasados DENUNCIA_ESPERA_DIAS días desde que se gestionó.
    $sqlPrev = 'SELECT estado, UNIX_TIMESTAMP(%s) AS ref FROM denuncias
                WHERE denunciante_id = ? AND tipo = ? AND objetivo_id = ? ORDER BY id DESC LIMIT 1';
    try {
        $prev = $pdo->prepare(sprintf($sqlPrev, 'COALESCE(gestionada_at, created_at)'));
        $prev->execute([$me, $tipo, $objetivoId]);
    } catch (PDOException $e) {
        // todavía no existe gestionada_at (falta denuncias-recurrentes.sql)
        $prev = $pdo->prepare(sprintf($sqlPrev, 'created_at'));
        $prev->execute([$me, $tipo, $objetivoId]);
    }
    if ($ultima = $prev->fetch()) {
        if ($ultima['estado'] === 'pendiente') {
            http_response_code(409);
            exit(json_encode(['error' => 'Ya enviaste una denuncia sobre esto. El equipo la va a revisar.']));
        }
        $puedeDesde = (int)$ultima['ref'] + DENUNCIA_ESPERA_DIAS * 86400;
        if (time() < $puedeDesde) {
            http_response_code(409);
            exit(json_encode(['error' => 'Tu denuncia anterior sobre esto se gestionó hace poco. Vas a poder volver a denunciar a partir del ' . date('d/m/Y H:i', $puedeDesde) . '.']));
        }
    }

    try {
        $ins = $pdo->prepare(
            'INSERT INTO denuncias (denunciante_id, tipo, objetivo_id, motivo, detalle)
             VALUES (?, ?, ?, ?, ?)'
        );
        $ins->execute([$me, $tipo, $objetivoId, $motivo, $detalle ?: null]);
        $nuevoId = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // clave UNIQUE: ya la había denunciado
            http_response_code(409);
            exit(json_encode(['error' => 'Ya enviaste una denuncia sobre esto. El equipo la va a revisar.']));
        }
        throw $e;
    }

    // Si ya hay suficientes cuentas distintas denunciando lo mismo por spam o contenido
    // inapropiado, se suspende solo (reglas en DENUNCIAS_AUTO, includes/moderacion-lib.php).
    try {
        mod_denuncias_auto($pdo, $tipo, $objetivoId, $motivo);
    } catch (Throwable $e) {
        // la denuncia ya quedó guardada; la suspensión automática es un extra
    }

    echo json_encode(['ok' => true, 'id' => $nuevoId]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido.']);