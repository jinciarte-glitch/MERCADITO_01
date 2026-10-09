<?php
// api/mis-denuncias.php — Las denuncias que hizo la persona logueada y cómo van
//  GET -> lista (las más nuevas primero)
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/moderacion-lib.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido.']));
}

$me = (int)current_user_id();

// Texto corto para reconocer lo que se denunció.
function mis_denuncias_objetivo(PDO $pdo, string $tipo, int $id): string {
    if ($tipo === 'usuario' || $tipo === 'emprendimiento') {
        $etq = $tipo === 'usuario' ? 'Usuario' : 'Emprendimiento';
        $n = mod_nombre($pdo, $tipo, $id);
        return $n !== null ? "$etq: $n" : "$etq (ya no existe)";
    }
    $etq   = $tipo === 'publicacion' ? 'Publicación' : 'Comentario';
    $tabla = $tipo === 'publicacion' ? 'posts' : 'comments';
    $s = $pdo->prepare("SELECT body FROM $tabla WHERE id = ? LIMIT 1");
    $s->execute([$id]);
    $b = $s->fetchColumn();
    if ($b === false) return "$etq (ya no existe)";
    $b = (string)$b;
    return "$etq: «" . mb_substr($b, 0, 80) . (mb_strlen($b) > 80 ? '…' : '') . '»';
}

$sql = 'SELECT d.*, UNIX_TIMESTAMP(d.created_at) AS created_unix%s
        FROM denuncias d WHERE d.denunciante_id = ? ORDER BY d.id DESC LIMIT 50';
try {
    $st = $pdo->prepare(sprintf($sql, ', UNIX_TIMESTAMP(d.gestionada_at) AS gest_unix'));
    $st->execute([$me]);
} catch (PDOException $e) {
    // todavía no existe gestionada_at (falta denuncias-recurrentes.sql)
    $st = $pdo->prepare(sprintf($sql, ''));
    $st->execute([$me]);
}

$lista = array_map(function ($d) use ($pdo) {
    $ref       = (int)($d['gest_unix'] ?? 0) ?: (int)$d['created_unix'];
    $pendiente = $d['estado'] === 'pendiente';
    return [
        'id'         => (int)$d['id'],
        'objetivo'   => mis_denuncias_objetivo($pdo, $d['tipo'], (int)$d['objetivo_id']),
        'motivo'     => $d['motivo'],
        'detalle'    => $d['detalle'],
        'estado'     => $d['estado'],
        'resolucion' => $d['resolucion'] ?? null,
        'createdAt'  => (int)$d['created_unix'] * 1000,
        // desde cuándo puede volver a denunciar lo mismo (solo si ya se gestionó)
        'puedeDenunciarDesde' => $pendiente ? null : ($ref + DENUNCIA_ESPERA_DIAS * 86400) * 1000,
    ];
}, $st->fetchAll());

echo json_encode(['denuncias' => $lista, 'esperaDias' => DENUNCIA_ESPERA_DIAS]);
