<?php
// api/products.php — catálogo del emprendedor logueado
//  GET          -> lista de productos de mi tienda
//  POST         -> crea un producto { name, price, imageUrl }
//  DELETE ?id=X -> elimina un producto mío
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/moderacion-lib.php';
require_login();

$userId = current_user_id();

// Devuelve el id de la tienda del usuario. Si todavía no tiene una y es
// emprendedor, la crea "vacía" con su nombre para no bloquear la carga
// de productos. Devuelve null si no corresponde.
function get_or_create_shop_id($pdo, $userId) {
    $find = $pdo->prepare('SELECT id FROM shops WHERE user_id = ? LIMIT 1');
    $find->execute([$userId]);
    $row = $find->fetch();
    if ($row) return (int)$row['id'];

    if (!is_emprendedor($pdo)) return null;

    $nameStmt = $pdo->prepare('SELECT full_name FROM users WHERE id = ? LIMIT 1');
    $nameStmt->execute([$userId]);
    $fullName = $nameStmt->fetchColumn() ?: 'Mi emprendimiento';

    $ins = $pdo->prepare('INSERT INTO shops (user_id, name) VALUES (?, ?)');
    $ins->execute([$userId, $fullName]);
    return (int)$pdo->lastInsertId();
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
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT p.* FROM products p
         JOIN shops s ON s.id = p.shop_id
         WHERE s.user_id = ?
         ORDER BY p.created_at DESC'
    );
    $stmt->execute([$userId]);
    echo json_encode(['products' => array_map('product_public', $stmt->fetchAll())]);
    exit;
}

// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_no_suspendido($pdo, (int)$userId);        // cuenta suspendida
    exigir_tienda_no_suspendida($pdo, (int)$userId); // emprendimiento suspendido
    exigir_emprendedor_no_bloqueado($pdo, (int)$userId); // rol de emprendedor retirado
    if (!is_emprendedor($pdo)) {
        http_response_code(403);
        exit(json_encode(['error' => 'Sólo los emprendedores pueden cargar productos.']));
    }

    $data     = json_input();
    $name     = trim($data['name'] ?? '');
    $imageUrl = trim($data['imageUrl'] ?? '');
    $priceRaw = $data['price'] ?? null;

    if ($name === '') {
        http_response_code(400);
        exit(json_encode(['error' => 'El producto necesita un nombre.']));
    }
    if ($imageUrl !== '' && !preg_match('#^https?://#i', $imageUrl)) {
        http_response_code(400);
        exit(json_encode(['error' => 'La URL de la imagen debe empezar con http:// o https://']));
    }

    // Precio: aceptamos número o texto tipo "$4.500,50" y lo normalizamos.
    $price = null;
    if ($priceRaw !== null && $priceRaw !== '') {
        $clean = preg_replace('/[^\d,.\-]/', '', (string)$priceRaw);
        // Formato AR "4.500,50" -> "4500.50"
        if (strpos($clean, ',') !== false) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }
        if (is_numeric($clean)) {
            $price = round((float)$clean, 2);
            if ($price < 0) $price = 0;
        }
    }

    $shopId = get_or_create_shop_id($pdo, $userId);
    if (!$shopId) {
        http_response_code(400);
        exit(json_encode(['error' => 'No se pudo encontrar tu tienda.']));
    }

    $ins = $pdo->prepare(
        'INSERT INTO products (shop_id, name, price, image_url) VALUES (?, ?, ?, ?)'
    );
    $ins->execute([$shopId, $name, $price, $imageUrl ?: null]);

    $id   = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    echo json_encode(product_public($stmt->fetch()));
    exit;
}

// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        exit(json_encode(['error' => 'Falta el id del producto.']));
    }
    // Sólo puede borrar productos de su propia tienda.
    $stmt = $pdo->prepare(
        'DELETE FROM products
         WHERE id = ? AND shop_id IN (SELECT id FROM shops WHERE user_id = ?)'
    );
    $stmt->execute([$id, $userId]);
    echo json_encode(['ok' => true, 'deleted' => $stmt->rowCount()]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);