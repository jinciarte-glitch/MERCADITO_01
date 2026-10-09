<?php
// api/shops.php — Emprendimientos (tiendas)
//  GET  ?id=X            -> detalle público de una tienda + su catálogo
//  GET  ?mine=1          -> la tienda del emprendedor logueado (para "Mi espacio")
//  GET  ?page&category&q -> listado paginado para el marketplace (scroll infinito)
//  POST                  -> crea/actualiza la tienda del emprendedor logueado
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/moderacion-lib.php';
require_login();

$userId = current_user_id();

// --- Helpers ---------------------------------------------------------------
function shop_public(array $row): array {
    return [
        'id'                => (int)$row['id'],
        'name'              => $row['name'],
        'category'          => $row['category'],
        'description'       => $row['description'],
        'avatar_url'        => $row['avatar_url'],
        'banner_url'        => $row['banner_url'],
        'logo_icon'         => $row['logo_icon'],
        'font'              => $row['font'],
        'color_fondo'       => $row['color_fondo'],
        'color_tarjeta'     => $row['color_tarjeta'],
        'color_acento'      => $row['color_acento'],
        'color_texto'       => $row['color_texto'],
        'banner_height'     => (int)$row['banner_height'],
        'tablon_estructura' => $row['tablon_estructura'],
        'estilo_tarjeta'    => $row['estilo_tarjeta'],
        'mostrar_buscador'  => (bool)$row['mostrar_buscador'],
        'verified'          => true, // toda tienda pertenece a un emprendedor verificado
    ];
}

function product_public(array $row): array {
    return [
        'id'        => (int)$row['id'],
        'name'      => $row['name'],
        'price'     => $row['price'] !== null ? (float)$row['price'] : null,
        'image_url' => $row['image_url'],
    ];
}

// ===========================================================================
//  GET
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // ---- Detalle de una tienda puntual + catálogo ----
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM shops WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_GET['id']]);
        $shop = $stmt->fetch();

        if (!$shop) {
            http_response_code(404);
            exit(json_encode(['error' => 'Ese emprendimiento no existe.']));
        }

        // La tienda de un administrador es solo para pruebas: no la ve nadie
        // más que él mismo (para poder previsualizarla desde "Mi espacio").
        $ownerRole = $pdo->prepare(
            'SELECT r.nombre FROM users u JOIN roles r ON r.roles_id = u.roles_id WHERE u.id = ? LIMIT 1'
        );
        $ownerRole->execute([$shop['user_id']]);
        if ($ownerRole->fetchColumn() === 'administrador' && (int)$shop['user_id'] !== (int)$userId) {
            http_response_code(404);
            exit(json_encode(['error' => 'Ese emprendimiento no existe.']));
        }

        // Tienda suspendida: solo la ven su dueño y el administrador.
        if (mod_ya_suspendido($pdo, 'emprendimiento', (int)$shop['id'])
            && (int)$shop['user_id'] !== (int)$userId && !is_administrador($pdo)) {
            http_response_code(403);
            exit(json_encode(['error' => 'Este emprendimiento está suspendido temporalmente.']));
        }

        $prod = $pdo->prepare('SELECT * FROM products WHERE shop_id = ? ORDER BY created_at DESC');
        $prod->execute([$shop['id']]);
        $products = array_map('product_public', $prod->fetchAll());

        $out = shop_public($shop);
        $out['products'] = $products;
        echo json_encode($out);
        exit;
    }

    // ---- Mi propia tienda (para prellenar el editor) ----
    if (isset($_GET['mine'])) {
        $stmt = $pdo->prepare('SELECT * FROM shops WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $shop = $stmt->fetch();
        echo json_encode(['shop' => $shop ? shop_public($shop) : null]);
        exit;
    }

    // ---- Listado paginado para el marketplace ----
    $perPage  = 12;
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $offset   = ($page - 1) * $perPage;
    $category = trim($_GET['category'] ?? 'todos');
    $q        = trim($_GET['q'] ?? '');

    $where  = [];
    $params = [];

    if ($category !== '' && $category !== 'todos') {
        $where[]  = 's.category = :cat';
        $params[':cat'] = $category;
    }
    if ($q !== '') {
        $where[] = '(s.name LIKE :q OR s.description LIKE :q OR EXISTS (
                        SELECT 1 FROM products p WHERE p.shop_id = s.id AND p.name LIKE :q))';
        $params[':q'] = '%' . $q . '%';
    }

    // Las tiendas de administrador son solo para pruebas: nunca aparecen
    // en el marketplace público.
    $where[] = "s.user_id NOT IN (
        SELECT u.id FROM users u JOIN roles r ON r.roles_id = u.roles_id WHERE r.nombre = 'administrador'
    )";

    // Las tiendas suspendidas no aparecen en el marketplace.
    $where[] = '(s.suspendido_hasta IS NULL OR s.suspendido_hasta <= NOW())';

    $sql = 'SELECT s.* FROM shops s';
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

    $shops = array_map('shop_public', $stmt->fetchAll());
    echo json_encode(['shops' => $shops, 'page' => $page]);
    exit;
}

// ===========================================================================
//  POST — crear / actualizar mi tienda
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    exigir_no_suspendido($pdo, (int)$userId);        // cuenta suspendida
    exigir_tienda_no_suspendida($pdo, (int)$userId); // emprendimiento suspendido
    exigir_emprendedor_no_bloqueado($pdo, (int)$userId); // rol de emprendedor retirado

    if (!is_emprendedor($pdo)) {
        http_response_code(403);
        exit(json_encode(['error' => 'Sólo las cuentas de emprendedor pueden tener un espacio.']));
    }

    $data        = json_input();
    $name        = trim($data['name'] ?? '');
    $category    = trim($data['category'] ?? '');
    $description = trim($data['description'] ?? '');
    $avatarUrl   = trim($data['avatarUrl'] ?? '');
    $bannerUrl   = trim($data['bannerUrl'] ?? '');

        if ($name === '') {
        http_response_code(400);
        exit(json_encode(['error' => 'El nombre del emprendimiento no puede estar vacío.']));
    }
    if ($avatarUrl !== '' && !preg_match('#^https?://#i', $avatarUrl)) {
        http_response_code(400);
        exit(json_encode(['error' => 'La URL de la foto debe empezar con http:// o https://']));
    }
    if ($bannerUrl !== '' && !preg_match('#^https?://#i', $bannerUrl)) {
        http_response_code(400);
        exit(json_encode(['error' => 'La URL del banner debe empezar con http:// o https://']));
    }

    // ¿Ya tiene tienda? -> UPDATE; si no -> INSERT
    $find = $pdo->prepare('SELECT * FROM shops WHERE user_id = ? LIMIT 1');
    $find->execute([$userId]);
    $existing = $find->fetch();

    // Si el campo de diseño no vino en este request, se mantiene el valor
    // que la tienda ya tenía guardado (o el default, si es una tienda nueva).
    $logoIcon         = trim($data['logoIcon'] ?? $existing['logo_icon'] ?? '');
    $logoIcon         = $logoIcon !== '' ? $logoIcon : null;
    $font             = trim($data['font'] ?? $existing['font'] ?? "'Work Sans', sans-serif");
    $colorFondo       = trim($data['colorFondo'] ?? $existing['color_fondo'] ?? '') ?: null;
    $colorTarjeta     = trim($data['colorTarjeta'] ?? $existing['color_tarjeta'] ?? '') ?: null;
    $colorAcento      = trim($data['colorAcento'] ?? $existing['color_acento'] ?? '') ?: null;
    $colorTexto       = trim($data['colorTexto'] ?? $existing['color_texto'] ?? '') ?: null;
    $bannerHeight     = (int)($data['bannerHeight'] ?? $existing['banner_height'] ?? 180);
    $tablonEstructura = trim($data['tablonEstructura'] ?? $existing['tablon_estructura'] ?? 'tablon-grid-3');
    $estiloTarjeta    = trim($data['estiloTarjeta'] ?? $existing['estilo_tarjeta'] ?? 'card-estilo-moderno');
    $mostrarBuscador  = array_key_exists('mostrarBuscador', $data)
        ? (!empty($data['mostrarBuscador']) ? 1 : 0)
        : (int)($existing['mostrar_buscador'] ?? 1);

    if ($existing) {
        $upd = $pdo->prepare(
            'UPDATE shops SET name = ?, category = ?, description = ?, avatar_url = ?, banner_url = ?,
             logo_icon = ?, font = ?, color_fondo = ?, color_tarjeta = ?, color_acento = ?, color_texto = ?,
             banner_height = ?, tablon_estructura = ?, estilo_tarjeta = ?, mostrar_buscador = ?
             WHERE id = ?'
        );
        $upd->execute([
            $name, $category ?: null, $description ?: null, $avatarUrl ?: null, $bannerUrl ?: null,
            $logoIcon, $font, $colorFondo, $colorTarjeta, $colorAcento, $colorTexto,
            $bannerHeight, $tablonEstructura, $estiloTarjeta, $mostrarBuscador,
            $existing['id'],
        ]);
        $shopId = (int)$existing['id'];
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO shops (user_id, name, category, description, avatar_url, banner_url,
             logo_icon, font, color_fondo, color_tarjeta, color_acento, color_texto,
             banner_height, tablon_estructura, estilo_tarjeta, mostrar_buscador)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $userId, $name, $category ?: null, $description ?: null, $avatarUrl ?: null, $bannerUrl ?: null,
            $logoIcon, $font, $colorFondo, $colorTarjeta, $colorAcento, $colorTexto,
            $bannerHeight, $tablonEstructura, $estiloTarjeta, $mostrarBuscador,
        ]);
        $shopId = (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare('SELECT * FROM shops WHERE id = ? LIMIT 1');
    $stmt->execute([$shopId]);
    echo json_encode(['ok' => true, 'shop' => shop_public($stmt->fetch())]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);