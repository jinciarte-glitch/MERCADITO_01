<?php
// api/solicitudes.php — Solicitudes de certificación de emprendedor
//  GET  ?mine=1                          -> la solicitud más reciente del usuario logueado
//  GET  ?estado=pendiente|aprobada|rechazada|todas&page= -> listado para el admin (con filtro)
//  POST                                   -> el usuario logueado crea una nueva solicitud
//  POST ?action=aprobar&id=X              -> admin aprueba (body: { comentario? })
//  POST ?action=rechazar&id=X             -> admin rechaza (body: { comentario? })
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/moderacion-lib.php';
require_login();

$userId = current_user_id();

// Calcula la edad en años a partir de una fecha de nacimiento (Y-m-d).
function calcular_edad(?string $fechaNacimiento): ?int {
    if (!$fechaNacimiento) return null;
    try {
        $nacimiento = new DateTime($fechaNacimiento);
        $hoy = new DateTime('today');
        if ($nacimiento > $hoy) return null;
        return $nacimiento->diff($hoy)->y;
    } catch (Exception $e) {
        return null;
    }
}

// Valida el FORMATO de un DNI argentino: solo dígitos, 7 u 8 números
// (los DNI actuales tienen 8; los más viejos pueden tener 7).
// Esto NO confirma que el DNI exista ni que pertenezca a la persona: eso
// requeriría consultar RENAPER u otro servicio oficial, algo fuera del
// alcance de este formulario.
function dni_formato_valido(string $numero): bool {
    return (bool)preg_match('/^\d{7,8}$/', $numero);
}

// Valida el FORMATO y el dígito verificador de un CUIT/CUIL argentino
// (11 dígitos, algoritmo módulo 11 usado por AFIP). Tampoco confirma que
// el CUIT esté activo o le pertenezca a la persona.
function cuit_formato_valido(string $numero): bool {
    if (!preg_match('/^\d{11}$/', $numero)) return false;

    $multiplicadores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    for ($i = 0; $i < 10; $i++) {
        $suma += (int)$numero[$i] * $multiplicadores[$i];
    }
    $resto = $suma % 11;
    $verificador = 11 - $resto;
    if ($verificador === 11) $verificador = 0;
    if ($verificador === 10) return false; // combinación inválida para persona/empresa

    return $verificador === (int)$numero[10];
}

function solicitud_public(array $row): array {
    return [
        'id'                    => (int)$row['id'],
        'user_id'               => (int)$row['user_id'],
        'nombre_usuario'        => $row['nombre_usuario'] ?? null,
        'email_usuario'         => $row['email_usuario'] ?? null,
        'nombre_emprendimiento' => $row['nombre_emprendimiento'],
        'categoria'             => $row['categoria'],
        'descripcion'           => $row['descripcion'],
        'motivo'                => $row['motivo'],
        'tipo_documento'        => $row['tipo_documento'] ?? null,
        'numero_documento'      => $row['numero_documento'] ?? null,
        'fecha_nacimiento'      => $row['fecha_nacimiento'] ?? null,
        'edad'                  => calcular_edad($row['fecha_nacimiento'] ?? null),
        'declaracion_licitud'   => !empty($row['declaracion_licitud']),
        'estado'                => $row['estado'],
        'comentario_admin'      => $row['comentario_admin'],
        'created_at'            => $row['created_at'],
        'reviewed_at'           => $row['reviewed_at'],
    ];
}

// ===========================================================================
//  GET
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // ---- Mi propia solicitud (la más reciente) ----
    if (isset($_GET['mine'])) {
        $stmt = $pdo->prepare(
            'SELECT * FROM solicitudes WHERE user_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        echo json_encode(['solicitud' => $row ? solicitud_public($row) : null]);
        exit;
    }

    // ---- Listado para el panel de administrador, con filtro por estado ----
    if (!is_administrador($pdo)) {
        http_response_code(403);
        exit(json_encode(['error' => 'No tenés permiso para ver las solicitudes.']));
    }

    $estadosValidos = ['pendiente', 'aprobada', 'rechazada'];
    $estado  = trim($_GET['estado'] ?? 'pendiente');
    $perPage = 20;
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if (in_array($estado, $estadosValidos, true)) {
        $where[] = 's.estado = :estado';
        $params[':estado'] = $estado;
    }
    // Si $estado es "todas" (o cualquier otro valor) no se filtra por estado.

    $sql = 'SELECT s.*, u.full_name AS nombre_usuario, u.email AS email_usuario
            FROM solicitudes s
            JOIN users u ON u.id = s.user_id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY s.created_at DESC LIMIT :lim OFFSET :off';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $solicitudes = array_map('solicitud_public', $stmt->fetchAll());

    // Conteos por estado, para las pestañas de filtro del admin.
    $counts = $pdo->query('SELECT estado, COUNT(*) AS total FROM solicitudes GROUP BY estado')
                  ->fetchAll(PDO::FETCH_KEY_PAIR);

    echo json_encode([
        'solicitudes' => $solicitudes,
        'page'        => $page,
        'counts'      => [
            'pendiente' => (int)($counts['pendiente'] ?? 0),
            'aprobada'  => (int)($counts['aprobada'] ?? 0),
            'rechazada' => (int)($counts['rechazada'] ?? 0),
        ],
    ]);
    exit;
}

// ===========================================================================
//  POST
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = trim($_GET['action'] ?? '');

    // ---- Admin: aprobar / rechazar una solicitud ----
    if ($action === 'aprobar' || $action === 'rechazar') {
        if (!is_administrador($pdo)) {
            http_response_code(403);
            exit(json_encode(['error' => 'No tenés permiso para resolver solicitudes.']));
        }
        csrf_check();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            exit(json_encode(['error' => 'Falta el id de la solicitud.']));
        }

        $find = $pdo->prepare('SELECT * FROM solicitudes WHERE id = ? LIMIT 1');
        $find->execute([$id]);
        $solicitud = $find->fetch();

        if (!$solicitud) {
            http_response_code(404);
            exit(json_encode(['error' => 'Esa solicitud no existe.']));
        }
        if ($solicitud['estado'] !== 'pendiente') {
            http_response_code(400);
            exit(json_encode(['error' => 'Esa solicitud ya fue resuelta.']));
        }

        // No se puede aprobar a quien perdió el rol de emprendedor de forma permanente.
        if ($action === 'aprobar' && mod_emprendedor_bloqueado($pdo, (int)$solicitud['user_id'])) {
            http_response_code(400);
            exit(json_encode(['error' => 'A esta persona se le retiró el rol de emprendedor de forma permanente: no se puede aprobar. Rechazá la solicitud.']));
        }

        $data        = json_input();
        $comentario  = trim($data['comentario'] ?? '') ?: null;
        $nuevoEstado = $action === 'aprobar' ? 'aprobada' : 'rechazada';

        $pdo->beginTransaction();
        try {
            $upd = $pdo->prepare(
                'UPDATE solicitudes SET estado = ?, comentario_admin = ?, reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ?'
            );
            $upd->execute([$nuevoEstado, $comentario, $userId, $id]);

            if ($nuevoEstado === 'aprobada') {
                // Si quien envió la solicitud ya es administrador (por ej. el
                // propio admin probando el flujo de punta a punta), NO le
                // tocamos el rol: quedaría degradado a "emprendedor" y
                // perdería el acceso de administrador. Se aprueba la
                // solicitud igual, solo que sin cambiar el rol.
                $rolActual = $pdo->prepare(
                    'SELECT r.nombre FROM users u JOIN roles r ON r.roles_id = u.roles_id WHERE u.id = ? LIMIT 1'
                );
                $rolActual->execute([$solicitud['user_id']]);
                $nombreRolActual = $rolActual->fetchColumn();

                if ($nombreRolActual !== 'administrador') {
                    // Le asigna el rol "emprendedor" al dueño de la solicitud.
                    $rol = $pdo->prepare('SELECT roles_id FROM roles WHERE nombre = ? LIMIT 1');
                    $rol->execute(['emprendedor']);
                    $rolesId = $rol->fetchColumn();

                    if (!$rolesId) {
                        throw new RuntimeException('No existe el rol "emprendedor" en la tabla roles.');
                    }

                    $updUser = $pdo->prepare('UPDATE users SET roles_id = ? WHERE id = ?');
                    $updUser->execute([$rolesId, $solicitud['user_id']]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            exit(json_encode(['error' => 'No se pudo resolver la solicitud.']));
        }

        $stmt = $pdo->prepare('SELECT * FROM solicitudes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        echo json_encode(['ok' => true, 'solicitud' => solicitud_public($stmt->fetch())]);
        exit;
    }

    // ---- Usuario: crear una nueva solicitud ----
    // Chequeo ESTRICTO (no cuenta a los administradores como ya-emprendedores)
    // para que un admin pueda probar este flujo igual que un cliente real.
    exigir_emprendedor_no_bloqueado($pdo, (int)$userId); // rol retirado: no puede volver a pedirlo

    if (is_emprendedor_real($pdo)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Tu cuenta ya es de emprendedor.']));
    }

    $pending = $pdo->prepare(
        "SELECT id FROM solicitudes WHERE user_id = ? AND estado = 'pendiente' LIMIT 1"
    );
    $pending->execute([$userId]);
    if ($pending->fetch()) {
        http_response_code(400);
        exit(json_encode(['error' => 'Ya tenés una solicitud pendiente de revisión.']));
    }

    $data                 = json_input();
    $nombreEmprendimiento = trim($data['nombreEmprendimiento'] ?? '');
    $categoria            = trim($data['categoria'] ?? '');
    $descripcion          = trim($data['descripcion'] ?? '');
    $motivo               = trim($data['motivo'] ?? '');
    $tipoDocumento        = trim($data['tipoDocumento'] ?? '');
    $numeroDocumento      = trim($data['numeroDocumento'] ?? '');
    $fechaNacimiento      = trim($data['fechaNacimiento'] ?? '');
    $declaracionLicitud   = !empty($data['declaracionLicitud']);

    if ($nombreEmprendimiento === '') {
        http_response_code(400);
        exit(json_encode(['error' => 'El nombre del emprendimiento no puede estar vacío.']));
    }
    if ($motivo === '') {
        http_response_code(400);
        exit(json_encode(['error' => 'Contanos brevemente por qué querés certificarte como emprendedor.']));
    }

    // ---- Validaciones de identidad / regulación de edad ----
    if (!in_array($tipoDocumento, ['DNI', 'CUIT/CUIL'], true)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Elegí un tipo de documento válido (DNI o CUIT/CUIL).']));
    }

    if ($tipoDocumento === 'DNI' && !dni_formato_valido($numeroDocumento)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Ese DNI no es válido: debe tener 7 u 8 números, sin puntos ni letras.']));
    }
    if ($tipoDocumento === 'CUIT/CUIL' && !cuit_formato_valido($numeroDocumento)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Ese CUIT/CUIL no es válido: deben ser 11 números y el dígito verificador no coincide. Revisalo sin guiones ni espacios.']));
    }

    $fechaNacimientoObj = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
    $hoy = new DateTime('today');
    if (!$fechaNacimientoObj || $fechaNacimientoObj > $hoy) {
        http_response_code(400);
        exit(json_encode(['error' => 'Ingresá una fecha de nacimiento válida.']));
    }
    $edad = $fechaNacimientoObj->diff($hoy)->y;
    if ($edad < EDAD_MINIMA_EMPRENDEDOR) {
        http_response_code(400);
        exit(json_encode(['error' => 'Tenés que ser mayor de ' . EDAD_MINIMA_EMPRENDEDOR . ' años para certificarte como emprendedor.']));
    }

    if (!$declaracionLicitud) {
        http_response_code(400);
        exit(json_encode(['error' => 'Tenés que aceptar la declaración sobre la veracidad de tus datos y la licitud de tu actividad.']));
    }

    $ins = $pdo->prepare(
        "INSERT INTO solicitudes
            (user_id, nombre_emprendimiento, categoria, descripcion, motivo,
             tipo_documento, numero_documento, fecha_nacimiento, declaracion_licitud, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'pendiente')"
    );
    $ins->execute([
        $userId, $nombreEmprendimiento, $categoria ?: null, $descripcion ?: null, $motivo,
        $tipoDocumento, $numeroDocumento, $fechaNacimientoObj->format('Y-m-d'),
    ]);

    $id   = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM solicitudes WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    echo json_encode(['ok' => true, 'solicitud' => solicitud_public($stmt->fetch())]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);