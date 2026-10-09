<?php
// includes/moderacion-lib.php — suspensiones, eliminaciones y detección automática de spam.
// Se incluye DESPUÉS de bootstrap.php:  require_once __DIR__ . '/../includes/moderacion-lib.php';

// ---------------------------------------------------------------------------
//  Reglas del spam automático (ajustá estos números a gusto)
// ---------------------------------------------------------------------------
// Suspensión automática por denuncias: cuando 'min' cuentas DISTINTAS denuncian lo mismo
// por ese motivo, se suspende a quien corresponda durante 'dias' días.
const DENUNCIAS_AUTO = [
    'spam'                  => ['min' => 3, 'dias' => 3, 'texto' => 'spam'],
    'contenido_inapropiado' => ['min' => 3, 'dias' => 1, 'texto' => 'contenido inapropiado'],
];

// Una vez gestionada una denuncia (resuelta o descartada), la misma persona puede volver a
// denunciar lo mismo recién pasados estos días.
const DENUNCIA_ESPERA_DIAS = 2;
const SPAM_ACTIVIDAD_MAX  = 10;   // publicaciones + comentarios + mensajes permitidos...
const SPAM_ACTIVIDAD_SEG  = 120;  // ...dentro de esta ventana de tiempo (en segundos)
const SPAM_ACTIVIDAD_DIAS = 1;    // días de suspensión automática por actividad

// ---------------------------------------------------------------------------
//  Utilidades
// ---------------------------------------------------------------------------
function mod_tabla(string $tipo): ?string {
    return ['usuario' => 'users', 'emprendimiento' => 'shops'][$tipo] ?? null;
}

function mod_rol_de(PDO $pdo, int $userId): ?string {
    $s = $pdo->prepare('SELECT r.nombre FROM users u JOIN roles r ON r.roles_id = u.roles_id WHERE u.id = ? LIMIT 1');
    $s->execute([$userId]);
    $r = $s->fetchColumn();
    return $r !== false ? $r : null;
}

function mod_nombre(PDO $pdo, string $tipo, int $id): ?string {
    $tabla = mod_tabla($tipo);
    if (!$tabla) return null;
    $col = $tipo === 'usuario' ? 'full_name' : 'name';
    $s = $pdo->prepare("SELECT $col FROM $tabla WHERE id = ? LIMIT 1");
    $s->execute([$id]);
    $r = $s->fetchColumn();
    return $r !== false ? (string)$r : null;
}

// Ejecuta un DELETE/UPDATE auxiliar e ignora SOLO el error "la tabla no existe"
// (algunas tablas se crean recién cuando se usa la función que las necesita).
function mod_exec_opt(PDO $pdo, string $sql, array $params = []): void {
    try {
        $pdo->prepare($sql)->execute($params);
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S02') throw $e;
    }
}

function mod_ids(PDO $pdo, string $sql, array $params): array {
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

function mod_log(PDO $pdo, ?int $adminId, string $accion, string $tipo, int $id, ?string $nombre, ?int $dias, ?string $motivo): void {
    try {
        $pdo->prepare(
            'INSERT INTO moderacion_log (admin_id, accion, tipo, objetivo_id, objetivo_nombre, dias, motivo)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$adminId, $accion, $tipo, $id, $nombre, $dias, $motivo !== '' ? $motivo : null]);
    } catch (PDOException $e) {
        // El historial es un extra: si la tabla todavía no existe, no rompemos la acción.
    }
}

// ---------------------------------------------------------------------------
//  Suspensión
// ---------------------------------------------------------------------------
function mod_suspender(PDO $pdo, string $tipo, int $id, int $dias, string $motivo, ?int $adminId): bool {
    $tabla = mod_tabla($tipo);
    if (!$tabla) return false;
    $motivo = mb_substr(trim($motivo), 0, 255);
    $pdo->prepare("UPDATE $tabla SET suspendido_hasta = DATE_ADD(NOW(), INTERVAL ? DAY), suspension_motivo = ? WHERE id = ?")
        ->execute([$dias, $motivo !== '' ? $motivo : null, $id]);
    mod_log($pdo, $adminId, 'suspender', $tipo, $id, mod_nombre($pdo, $tipo, $id), $dias, $motivo);
    // Se le avisa por mail a la persona (también cuando la suspensión es automática).
    $avisado = mod_notificar_suspension($pdo, $tipo, $id, $dias, $motivo);
    // Si fue automática, se les avisa también a los administradores para que evalúen
    // si hace falta tomar otras medidas.
    if ($adminId === null) {
        mod_avisar_admins_suspension_auto($pdo, $tipo, $id, $dias, $motivo);
    }
    return $avisado;
}

function mod_levantar(PDO $pdo, string $tipo, int $id, ?int $adminId): void {
    $tabla = mod_tabla($tipo);
    if (!$tabla) return;
    $pdo->prepare("UPDATE $tabla SET suspendido_hasta = NULL, suspension_motivo = NULL WHERE id = ?")->execute([$id]);
    mod_log($pdo, $adminId, 'levantar', $tipo, $id, mod_nombre($pdo, $tipo, $id), null, null);
}

function mod_ya_suspendido(PDO $pdo, string $tipo, int $id): bool {
    $tabla = mod_tabla($tipo);
    if (!$tabla) return false;
    try {
        $s = $pdo->prepare("SELECT 1 FROM $tabla WHERE id = ? AND suspendido_hasta IS NOT NULL AND suspendido_hasta > NOW() LIMIT 1");
        $s->execute([$id]);
        return (bool)$s->fetchColumn();
    } catch (PDOException $e) {
        return false; // las columnas todavía no existen (falta correr moderacion.sql)
    }
}

function mod_suspension_usuario(PDO $pdo, int $userId): ?array {
    try {
        $s = $pdo->prepare(
            'SELECT UNIX_TIMESTAMP(suspendido_hasta) AS hasta, suspension_motivo AS motivo
             FROM users WHERE id = ? AND suspendido_hasta IS NOT NULL AND suspendido_hasta > NOW() LIMIT 1'
        );
        $s->execute([$userId]);
        $row = $s->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

// Corta la request con 403 si el usuario está suspendido (puede mirar, no escribir).
function exigir_no_suspendido(PDO $pdo, int $userId): void {
    $s = mod_suspension_usuario($pdo, $userId);
    if (!$s) return;
    $hasta = date('d/m/Y H:i', (int)$s['hasta']);
    $msg = "Tu cuenta está suspendida hasta el $hasta. Podés mirar, pero no publicar, comentar ni escribir.";
    if (!empty($s['motivo'])) $msg .= ' Motivo: ' . $s['motivo'];
    http_response_code(403);
    exit(json_encode(['error' => $msg, 'suspendidoHasta' => (int)$s['hasta'] * 1000]));
}

// ---------------------------------------------------------------------------
//  Avisos por mail (suspensión / eliminación)
// ---------------------------------------------------------------------------
// Envía un mail simple con párrafos y, si hay, el motivo. Devuelve true si salió.
// Si el hosting no deja mandar mails, devuelve false y NO rompe la acción.
function mod_enviar_aviso(?string $email, ?string $nombre, string $asunto, array $parrafos, string $motivo = ''): bool {
    if (!$email) return false;
    try {
        require_once __DIR__ . '/mailer.php';
        $nombre = trim((string)$nombre);
        $saludo = $nombre !== '' ? "Hola $nombre," : 'Hola,';

        $texto = $saludo . "\n\n" . implode("\n\n", $parrafos);
        if ($motivo !== '') $texto .= "\n\nMotivo: $motivo";
        $texto .= "\n\n— El equipo de Mercadito";

        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.5;color:#222">'
              . '<p>' . htmlspecialchars($saludo) . '</p>';
        foreach ($parrafos as $par) {
            $html .= '<p>' . htmlspecialchars($par) . '</p>';
        }
        if ($motivo !== '') {
            $html .= '<p><strong>Motivo:</strong> ' . htmlspecialchars($motivo) . '</p>';
        }
        $html .= '<p>— El equipo de Mercadito</p></div>';

        $error = null;
        return (bool)enviar_mail($email, $nombre, $asunto, $html, $texto, $error);
    } catch (Throwable $e) {
        return false;
    }
}

function mod_notificar_suspension(PDO $pdo, string $tipo, int $id, int $dias, string $motivo): bool {
    try {
        if ($tipo === 'usuario') {
            $q = $pdo->prepare('SELECT full_name, email, UNIX_TIMESTAMP(suspendido_hasta) AS hasta FROM users WHERE id = ?');
        } else {
            $q = $pdo->prepare(
                'SELECT u.full_name, u.email, UNIX_TIMESTAMP(s.suspendido_hasta) AS hasta, s.name AS tienda
                 FROM shops s JOIN users u ON u.id = s.user_id WHERE s.id = ?'
            );
        }
        $q->execute([$id]);
        $r = $q->fetch();
        if (!$r || !$r['hasta']) return false;

        $hasta = date('d/m/Y H:i', (int)$r['hasta']);
        $dur   = $dias === 1 ? '1 día' : "$dias días";

        if ($tipo === 'usuario') {
            return mod_enviar_aviso($r['email'], $r['full_name'], 'Tu cuenta de Mercadito fue suspendida', [
                "Tu cuenta fue suspendida por $dur, hasta el $hasta.",
                'Durante ese tiempo podés entrar y mirar la página, pero no podés publicar, comentar, escribir mensajes ni modificar tu perfil o tu emprendimiento.',
                'Cuando termine la suspensión vas a poder usar tu cuenta con normalidad.',
            ], $motivo);
        }
        return mod_enviar_aviso($r['email'], $r['full_name'], 'Tu emprendimiento de Mercadito fue suspendido', [
            'Tu emprendimiento «' . $r['tienda'] . "» fue suspendido por $dur, hasta el $hasta.",
            'Mientras dure la suspensión no aparece en el listado de Emprendimientos y no podés modificarlo ni cargar productos.',
            'Cuando termine, vuelve a mostrarse con normalidad.',
        ], $motivo);
    } catch (Throwable $e) {
        return false;
    }
}

// Suspensión vigente del emprendimiento de un usuario (para avisarle en pantalla y
// frenar la carga de productos), o null.
function mod_suspension_tienda(PDO $pdo, int $userId): ?array {
    try {
        $q = $pdo->prepare(
            'SELECT name, UNIX_TIMESTAMP(suspendido_hasta) AS hasta, suspension_motivo AS motivo
             FROM shops WHERE user_id = ? AND suspendido_hasta IS NOT NULL AND suspendido_hasta > NOW() LIMIT 1'
        );
        $q->execute([$userId]);
        $row = $q->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

// Corta la request con 403 si el emprendimiento del usuario está suspendido.
function exigir_tienda_no_suspendida(PDO $pdo, int $userId): void {
    $s = mod_suspension_tienda($pdo, $userId);
    if (!$s) return;
    $hasta = date('d/m/Y H:i', (int)$s['hasta']);
    $msg = "Tu emprendimiento está suspendido hasta el $hasta. No podés modificarlo ni cargar productos mientras tanto.";
    if (!empty($s['motivo'])) $msg .= ' Motivo: ' . $s['motivo'];
    http_response_code(403);
    exit(json_encode(['error' => $msg, 'suspendidoHasta' => (int)$s['hasta'] * 1000]));
}

// ---------------------------------------------------------------------------
//  Rol de emprendedor retirado de forma permanente (al eliminar su emprendimiento)
// ---------------------------------------------------------------------------
function mod_emprendedor_bloqueado(PDO $pdo, int $userId): bool {
    try {
        $s = $pdo->prepare('SELECT emprendedor_bloqueado FROM users WHERE id = ? LIMIT 1');
        $s->execute([$userId]);
        return (int)$s->fetchColumn() === 1;
    } catch (PDOException $e) {
        return false; // falta correr emprendedor-bloqueado.sql
    }
}

// Corta la request con 403 si a la persona se le retiró el rol de emprendedor.
// También corrige el rol guardado en su sesión (que si no seguiría diciendo "emprendedor").
function exigir_emprendedor_no_bloqueado(PDO $pdo, int $userId): void {
    if (!mod_emprendedor_bloqueado($pdo, $userId)) return;
    if (isset($_SESSION) && ($_SESSION['profile_type'] ?? '') === 'emprendedor') {
        $_SESSION['profile_type'] = 'cliente';
    }
    http_response_code(403);
    exit(json_encode(['error' => 'Se te retiró de forma permanente el rol de emprendedor, por eso no podés crear ni modificar un emprendimiento ni volver a pedir ese rol.']));
}

// Pasa a la persona a cliente y la marca para que no pueda volver a ser emprendedor.
// Devuelve: 'permanente' (rol quitado y bloqueado), 'parcial' (rol quitado pero falta
// correr el SQL del bloqueo) o 'nada' (no era emprendedor: administradores y clientes no se tocan).
function mod_quitar_rol_emprendedor(PDO $pdo, int $userId): string {
    if (mod_rol_de($pdo, $userId) !== 'emprendedor') return 'nada';
    $subrol = "(SELECT roles_id FROM roles WHERE nombre = 'cliente' LIMIT 1)";
    try {
        $pdo->prepare("UPDATE users SET roles_id = $subrol, emprendedor_bloqueado = 1 WHERE id = ?")->execute([$userId]);
        return 'permanente';
    } catch (PDOException $e) {
        $pdo->prepare("UPDATE users SET roles_id = $subrol WHERE id = ?")->execute([$userId]);
        return 'parcial';
    }
}

// ---------------------------------------------------------------------------
//  Estado de las denuncias (guarda cuándo se gestionaron)
// ---------------------------------------------------------------------------
// Cambia el estado de las denuncias que cumplan $where. Al resolverlas o descartarlas anota
// la fecha (gestionada_at), que se usa para dejar volver a denunciar a los DENUNCIA_ESPERA_DIAS.
function mod_actualizar_estado_denuncias(PDO $pdo, string $estado, string $where, array $params, ?string $resolucion = null): void {
    $gest = $estado === 'pendiente' ? 'NULL' : 'NOW()';
    // Se prueba con todas las columnas nuevas y, si alguna todavía no existe (falta correr un
    // .sql), se va probando con menos hasta que el cambio de estado quede guardado igual.
    $intentos = [
        ["estado = ?, gestionada_at = $gest, resolucion = ?", [$estado, $resolucion]],
        ["estado = ?, gestionada_at = $gest",                 [$estado]],
        ['estado = ?',                                        [$estado]],
    ];
    foreach ($intentos as $i => [$set, $vals]) {
        try {
            $pdo->prepare("UPDATE denuncias SET $set WHERE $where")->execute(array_merge($vals, $params));
            return;
        } catch (PDOException $e) {
            if ($i === count($intentos) - 1) throw $e;
        }
    }
}

// Primera letra en mayúscula (para mostrar el resultado como oración).
function mod_capitalizar(string $t): string {
    return mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
}

// ---------------------------------------------------------------------------
//  Avisos en pantalla (se ven al iniciar sesión) y avisos a los administradores
// ---------------------------------------------------------------------------
function mod_notificar(PDO $pdo, int $userId, string $tipo, string $titulo, string $mensaje, ?string $url = null): void {
    try {
        $pdo->prepare('INSERT INTO notificaciones (user_id, tipo, titulo, mensaje, url) VALUES (?, ?, ?, ?, ?)')
            ->execute([$userId, $tipo, mb_substr($titulo, 0, 150), $mensaje, $url]);
    } catch (PDOException $e) {
        // tabla todavía no creada (falta correr notificaciones.sql): el mail igual sale
    }
}

function mod_notificaciones_pendientes(PDO $pdo, int $userId): array {
    try {
        $s = $pdo->prepare(
            'SELECT id, tipo, titulo, mensaje, url FROM notificaciones
             WHERE user_id = ? AND leida_at IS NULL ORDER BY created_at DESC, id DESC LIMIT 5'
        );
        $s->execute([$userId]);
        return $s->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function mod_admins(PDO $pdo): array {
    return $pdo->query(
        "SELECT u.id, u.email, u.full_name FROM users u
         JOIN roles r ON r.roles_id = u.roles_id WHERE r.nombre = 'administrador'"
    )->fetchAll();
}

// Texto corto para nombrar lo denunciado en los avisos.
function mod_describir_objetivo(PDO $pdo, string $tipo, int $id): string {
    if ($tipo === 'usuario' || $tipo === 'emprendimiento') {
        $n = mod_nombre($pdo, $tipo, $id);
        if ($n !== null) return ($tipo === 'usuario' ? 'el usuario «' : 'el emprendimiento «') . $n . '»';
    }
    return [
        'usuario' => 'un usuario', 'emprendimiento' => 'un emprendimiento',
        'publicacion' => 'una publicación', 'comentario' => 'un comentario',
    ][$tipo] ?? 'un contenido';
}

// Suspensión automática => aviso a TODOS los administradores, solo en la página (sin mail).
function mod_avisar_admins_suspension_auto(PDO $pdo, string $tipo, int $id, int $dias, string $motivo): void {
    try {
        $nombre = mod_nombre($pdo, $tipo, $id) ?? '(sin nombre)';
        $queQuien = ($tipo === 'usuario' ? 'al usuario «' : 'al emprendimiento «') . $nombre . '»';
        $dur = $dias === 1 ? '1 día' : "$dias días";
        $mensaje = "Se suspendió automáticamente $queQuien por $dur. "
                 . "Revisalo en Moderación para decidir si hace falta tomar otras medidas (suspender por más tiempo o eliminar).";
        foreach (mod_admins($pdo) as $a) {
            mod_notificar($pdo, (int)$a['id'], 'suspension_automatica', 'Suspensión automática', $mensaje . ($motivo !== '' ? " Motivo: $motivo" : ''), 'admin-solicitudes.php#moderacion');
        }
    } catch (Throwable $e) {
        // el aviso a los admins es un extra: no debe romper la suspensión
    }
}

// Le cuenta a quien denunció cómo terminó su denuncia (pantalla + mail).
function mod_avisar_resultado_denuncia(PDO $pdo, int $userId, ?string $email, ?string $nombre, string $descripcion, string $resultado, string $mensajeAdmin = ''): void {
    $frase  = "Tu denuncia sobre $descripcion fue revisada: $resultado";
    $espera = 'Si el problema continúa, podés volver a denunciar pasados ' . DENUNCIA_ESPERA_DIAS . ' días.';
    $pantalla = $frase . ($mensajeAdmin !== '' ? "\nMensaje del equipo: $mensajeAdmin" : '') . "\n" . $espera;
    mod_notificar($pdo, $userId, 'denuncia_resultado', 'Resultado de tu denuncia', $pantalla);

    $parrafos = [$frase];
    if ($mensajeAdmin !== '') $parrafos[] = "Mensaje del equipo: $mensajeAdmin";
    $parrafos[] = $espera;
    $parrafos[] = 'Gracias por ayudarnos a cuidar Mercadito.';
    mod_enviar_aviso($email, $nombre, 'Resultado de tu denuncia en Mercadito', $parrafos);
}

// Marca como resueltas las denuncias pendientes de esos objetivos y avisa el resultado
// a cada persona que denunció (una vez por persona). $motivo = null => todas.
function mod_resolver_denuncias(PDO $pdo, string $tipo, array $objetivoIds, ?string $motivo, string $descripcion, string $resultado): void {
    $objetivoIds = array_values(array_filter(array_map('intval', $objetivoIds)));
    if (!$objetivoIds) return;

    $in = implode(',', array_fill(0, count($objetivoIds), '?'));
    $params = array_merge([$tipo], $objetivoIds);
    if ($motivo !== null) $params[] = $motivo;

    $q = $pdo->prepare(
        "SELECT d.denunciante_id AS uid, u.email, u.full_name
         FROM denuncias d JOIN users u ON u.id = d.denunciante_id
         WHERE d.tipo = ? AND d.objetivo_id IN ($in) AND d.estado = 'pendiente'"
         . ($motivo !== null ? ' AND d.motivo = ?' : '')
    );
    $q->execute($params);
    $quienes = $q->fetchAll();
    if (!$quienes) return;

    mod_actualizar_estado_denuncias(
        $pdo, 'resuelta',
        "tipo = ? AND objetivo_id IN ($in) AND estado = 'pendiente'" . ($motivo !== null ? ' AND motivo = ?' : ''),
        $params,
        mod_capitalizar($resultado)   // lo que va a ver la persona en "Mis denuncias"
    );

    $avisados = [];
    foreach ($quienes as $r) {
        if (isset($avisados[$r['uid']])) continue;
        $avisados[$r['uid']] = true;
        mod_avisar_resultado_denuncia($pdo, (int)$r['uid'], $r['email'], $r['full_name'], $descripcion, $resultado);
    }
}

// ---------------------------------------------------------------------------
//  Spam por actividad: demasiadas acciones en poco tiempo => suspensión automática
// ---------------------------------------------------------------------------
function mod_chequear_actividad(PDO $pdo, int $userId): void {
    if (mod_rol_de($pdo, $userId) === 'administrador') return;

    $seg = (int)SPAM_ACTIVIDAD_SEG;
    $s = $pdo->prepare(
        "SELECT
           (SELECT COUNT(*) FROM posts    WHERE user_id   = ? AND created_at >= DATE_SUB(NOW(), INTERVAL $seg SECOND)) +
           (SELECT COUNT(*) FROM comments WHERE user_id   = ? AND created_at >= DATE_SUB(NOW(), INTERVAL $seg SECOND)) +
           (SELECT COUNT(*) FROM messages WHERE sender_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL $seg SECOND))"
    );
    $s->execute([$userId, $userId, $userId]);

    if ((int)$s->fetchColumn() >= SPAM_ACTIVIDAD_MAX) {
        mod_suspender(
            $pdo, 'usuario', $userId, SPAM_ACTIVIDAD_DIAS,
            'Suspensión automática: actividad de spam (demasiadas acciones en poco tiempo).', null
        );
        exigir_no_suspendido($pdo, $userId); // responde 403 con el aviso
    }
}

// Se llama antes de publicar / comentar / mandar un mensaje.
function mod_exigir_puede_escribir(PDO $pdo, int $userId): void {
    exigir_no_suspendido($pdo, $userId);
    mod_chequear_actividad($pdo, $userId);
}

// ---------------------------------------------------------------------------
//  Suspensión automática por denuncias (spam / contenido inapropiado)
// ---------------------------------------------------------------------------
// A quién se castiga según lo denunciado: usuario/emprendimiento => ellos mismos;
// publicación/comentario => el autor.
function mod_responsable(PDO $pdo, string $tipo, int $objetivoId): array {
    if ($tipo === 'usuario' || $tipo === 'emprendimiento') return [$tipo, $objetivoId];
    $tabla = $tipo === 'publicacion' ? 'posts' : 'comments';
    $s = $pdo->prepare("SELECT user_id FROM $tabla WHERE id = ? LIMIT 1");
    $s->execute([$objetivoId]);
    $autor = (int)$s->fetchColumn();
    return $autor > 0 ? ['usuario', $autor] : ['usuario', 0];
}

function mod_denuncias_auto(PDO $pdo, string $tipo, int $objetivoId, string $motivo): void {
    $regla = DENUNCIAS_AUTO[$motivo] ?? null;
    if (!$regla) return; // los demás motivos los revisa el administrador a mano

    $c = $pdo->prepare(
        "SELECT COUNT(DISTINCT denunciante_id) FROM denuncias
         WHERE tipo = ? AND objetivo_id = ? AND motivo = ? AND estado = 'pendiente'"
    );
    $c->execute([$tipo, $objetivoId, $motivo]);
    $n = (int)$c->fetchColumn();
    if ($n < $regla['min']) return;

    [$tipoObj, $idObj] = mod_responsable($pdo, $tipo, $objetivoId);
    if ($idObj <= 0) return;

    // Nunca se suspende automáticamente a un administrador (ni a su tienda de pruebas).
    $duenoId = $tipoObj === 'usuario' ? $idObj : (function () use ($pdo, $idObj) {
        $s = $pdo->prepare('SELECT user_id FROM shops WHERE id = ? LIMIT 1');
        $s->execute([$idObj]);
        return (int)$s->fetchColumn();
    })();
    if ($duenoId > 0 && mod_rol_de($pdo, $duenoId) === 'administrador') return;

    if (mod_ya_suspendido($pdo, $tipoObj, $idObj)) return;

    mod_suspender(
        $pdo, $tipoObj, $idObj, (int)$regla['dias'],
        "Suspensión automática: $n personas denunciaron " . $regla['texto'] . '.', null
    );

    // Esas denuncias ya cumplieron su efecto: se marcan como resueltas (para que, al
    // terminar la suspensión, no cuenten de nuevo) y se le avisa el resultado a cada
    // persona que denunció.
    $dias = (int)$regla['dias'];
    mod_resolver_denuncias(
        $pdo, $tipo, [$objetivoId], $motivo,
        mod_describir_objetivo($pdo, $tipo, $objetivoId),
        'tras varias denuncias de distintas cuentas, se suspendió automáticamente por ' . ($dias === 1 ? '1 día' : "$dias días") . '.'
    );
}

// ---------------------------------------------------------------------------
//  Eliminación (definitiva)
// ---------------------------------------------------------------------------
function mod_borrar_tienda_datos(PDO $pdo, int $shopId): void {
    mod_exec_opt($pdo, 'DELETE FROM products WHERE shop_id = ?', [$shopId]);
    mod_exec_opt($pdo, 'DELETE FROM reclamos WHERE shop_id = ?', [$shopId]);
    mod_exec_opt($pdo, 'DELETE FROM shops WHERE id = ?', [$shopId]);
    mod_exec_opt($pdo, "UPDATE denuncias SET estado = 'resuelta' WHERE tipo = 'emprendimiento' AND objetivo_id = ?", [$shopId]);
}

function mod_eliminar_emprendimiento(PDO $pdo, int $shopId, ?int $adminId): void {
    $nombre = mod_nombre($pdo, 'emprendimiento', $shopId);
    $pdo->beginTransaction();
    try {
        mod_borrar_tienda_datos($pdo, $shopId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    mod_log($pdo, $adminId, 'eliminar', 'emprendimiento', $shopId, $nombre, null, null);
}

function mod_eliminar_usuario(PDO $pdo, int $id, ?int $adminId): void {
    $nombre = mod_nombre($pdo, 'usuario', $id);
    $pdo->beginTransaction();
    try {
        // Sus tiendas (con productos y reclamos)
        foreach (mod_ids($pdo, 'SELECT id FROM shops WHERE user_id = ?', [$id]) as $shopId) {
            mod_borrar_tienda_datos($pdo, $shopId);
        }

        // Sus publicaciones y todo lo que cuelga de ellas
        $postIds = mod_ids($pdo, 'SELECT id FROM posts WHERE user_id = ?', [$id]);
        if ($postIds) {
            $in = implode(',', array_fill(0, count($postIds), '?'));
            mod_exec_opt($pdo, "DELETE FROM likes    WHERE post_id IN ($in)", $postIds);
            mod_exec_opt($pdo, "DELETE FROM shares   WHERE post_id IN ($in)", $postIds);
            mod_exec_opt($pdo, "DELETE FROM comments WHERE post_id IN ($in)", $postIds);
        }
        mod_exec_opt($pdo, 'DELETE FROM posts    WHERE user_id = ?', [$id]);
        mod_exec_opt($pdo, 'DELETE FROM likes    WHERE user_id = ?', [$id]);
        mod_exec_opt($pdo, 'DELETE FROM shares   WHERE user_id = ?', [$id]);
        mod_exec_opt($pdo, 'DELETE FROM comments WHERE user_id = ?', [$id]);

        // Sus conversaciones y mensajes
        $convIds = mod_ids($pdo, 'SELECT id FROM conversations WHERE cliente_id = ? OR emprendedor_id = ?', [$id, $id]);
        if ($convIds) {
            $in = implode(',', array_fill(0, count($convIds), '?'));
            mod_exec_opt($pdo, "DELETE FROM messages WHERE conversation_id IN ($in)", $convIds);
        }
        mod_exec_opt($pdo, 'DELETE FROM conversations WHERE cliente_id = ? OR emprendedor_id = ?', [$id, $id]);
        mod_exec_opt($pdo, 'DELETE FROM messages WHERE sender_id = ?', [$id]);

        // Relaciones, trámites y reclamos
        mod_exec_opt($pdo, 'DELETE FROM follows WHERE follower_id = ? OR followed_id = ?', [$id, $id]);
        mod_exec_opt($pdo, 'DELETE FROM blocks  WHERE blocker_id = ?  OR blocked_id = ?',  [$id, $id]);
        mod_exec_opt($pdo, 'DELETE FROM solicitudes         WHERE user_id = ?', [$id]);
        mod_exec_opt($pdo, 'DELETE FROM email_verifications WHERE user_id = ?', [$id]);
        mod_exec_opt($pdo, 'DELETE FROM password_resets     WHERE user_id = ?', [$id]);
        mod_exec_opt($pdo, 'DELETE FROM reclamos            WHERE cliente_id = ?', [$id]);
        mod_exec_opt($pdo, 'DELETE FROM reclamos_generales  WHERE user_id = ?', [$id]);
        mod_exec_opt($pdo, 'DELETE FROM denuncias           WHERE denunciante_id = ?', [$id]);
        mod_exec_opt($pdo, "UPDATE denuncias SET estado = 'resuelta' WHERE tipo = 'usuario' AND objetivo_id = ?", [$id]);

        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    mod_log($pdo, $adminId, 'eliminar', 'usuario', $id, $nombre, null, null);
}