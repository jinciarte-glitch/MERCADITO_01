<?php
// api/posts.php
//  GET  -> lista de publicaciones (con autor, likes y comentarios)
//          Filtros: ?userId=X ?sharedBy=X ?likedBy=X
//          Zona (Marketplace): ?lat=..&lng=..&radius=25&zone=near|all&includeWithout=1
//  POST -> crea una publicación { text, imageUrl, lat, lng, place_name }
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/moderacion-lib.php';
require_login();

$userId = current_user_id();

// ---------- Helpers de zona ----------
function geo_float($v) {
    if ($v === null || $v === '') return null;
    if (!is_numeric($v)) return null;
    $f = (float)$v;
    return is_finite($f) ? $f : null;
}

// Distancia Haversine en km (expresión SQL reutilizable).
// Usa 3 placeholders: lat0, lng0, lat0.
function haversine_sql() {
    return "(6371 * ACOS(LEAST(1.0, GREATEST(-1.0, " .
        "COS(RADIANS(?)) * COS(RADIANS(p.lat)) * COS(RADIANS(p.lng) - RADIANS(?)) + " .
        "SIN(RADIANS(?)) * SIN(RADIANS(p.lat))))))";
}

function map_post_row($row, $comments) {
    return [
        'id'            => (int)$row['id'],
        'authorId'      => (int)$row['author_id'],
        'authorName'    => $row['author_name'],
        'authorType'    => $row['author_type'],
        'authorAvatar'  => $row['author_avatar'],
        'text'          => $row['body'],
        'imageUrl'      => $row['image_url'],
        'createdAt'     => (int)$row['created_at_unix'] * 1000,
        'likeCount'     => (int)$row['like_count'],
        'likedByMe'     => (bool)$row['liked_by_me'],
        'shareCount'    => (int)$row['share_count'],
        'sharedByMe'    => (bool)$row['shared_by_me'],
        'lat'           => isset($row['lat']) && $row['lat'] !== null ? (float)$row['lat'] : null,
        'lng'           => isset($row['lng']) && $row['lng'] !== null ? (float)$row['lng'] : null,
        'placeName'     => $row['place_name'] ?? null,
        'distanceKm'    => isset($row['distance_km']) && $row['distance_km'] !== null ? (float)$row['distance_km'] : null,
        'comments'      => $comments,
    ];
}

function fetch_comments($pdo, $postId) {
    static $stmt = null;
    if ($stmt === null) {
        $stmt = $pdo->prepare(
            'SELECT c.body, UNIX_TIMESTAMP(c.created_at) AS created_at_unix, u.full_name AS author_name
             FROM comments c JOIN users u ON u.id = c.user_id
             WHERE c.post_id = ? ORDER BY c.created_at ASC'
        );
    }
    $stmt->execute([$postId]);
    return array_map(function ($c) {
        return [
            'authorName' => $c['author_name'],
            'text'       => $c['body'],
            'createdAt'  => (int)$c['created_at_unix'] * 1000,
        ];
    }, $stmt->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // Parámetros de zona (opcionales; si no vienen no se filtra nada).
    $qLat = geo_float($_GET['lat'] ?? null);
    $qLng = geo_float($_GET['lng'] ?? null);
    $qRadius = geo_float($_GET['radius'] ?? 25);
    if ($qRadius === null || $qRadius < 1) $qRadius = 25;
    if ($qRadius > 500) $qRadius = 500;
    $qZone = ($_GET['zone'] ?? '') === 'all' ? 'all' : 'near';
    $qIncludeWithout = ($_GET['includeWithout'] ?? '1') !== '0';
    $geoActive = ($qLat !== null && $qLng !== null && $qLat >= -90 && $qLat <= 90 && $qLng >= -180 && $qLng <= 180 && $qZone === 'near');

    $postId = (int)($_GET['id'] ?? 0);

    if ($postId > 0) {
        $distSelect = $geoActive
            ? ", (CASE WHEN p.lat IS NULL OR p.lng IS NULL THEN NULL ELSE " . haversine_sql() . " END) AS distance_km"
            : ", NULL AS distance_km";

        $sql = "
            SELECT
              p.id,
              p.body,
              p.image_url,
              p.lat, p.lng, p.place_name,
              UNIX_TIMESTAMP(p.created_at) AS created_at_unix,
              u.id AS author_id,
              u.full_name AS author_name,
              u.avatar_url AS author_avatar,
              r.nombre AS author_type,

              (SELECT COUNT(*)
               FROM likes l
               WHERE l.post_id = p.id) AS like_count,

              (SELECT COUNT(*)
               FROM likes l
               WHERE l.post_id = p.id
               AND l.user_id = ?) AS liked_by_me,

              (SELECT COUNT(*)
               FROM shares s
               WHERE s.post_id = p.id) AS share_count,

              (SELECT COUNT(*)
               FROM shares s
               WHERE s.post_id = p.id
               AND s.user_id = ?) AS shared_by_me
              $distSelect

            FROM posts p
            JOIN users u ON u.id = p.user_id
            JOIN roles r ON r.roles_id = u.roles_id

            WHERE p.id = ?

            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $args = [$userId, $userId];
        if ($geoActive) { $args[] = $qLat; $args[] = $qLng; $args[] = $qLat; }
        $args[] = $postId;
        $stmt->execute($args);

        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            exit(json_encode([
                'error' => 'Publicación no encontrada.'
            ]));
        }

        $post = map_post_row($row, fetch_comments($pdo, $postId));

        echo json_encode([
            'post' => $post
        ]);

        exit;
    }
    //   ?userId=X   -> solo las publicaciones creadas por X
    //   ?sharedBy=X -> solo las publicaciones que X compartió

    $onlyAuthor = (int)($_GET['userId'] ?? 0);
    $sharedBy   = (int)($_GET['sharedBy'] ?? 0);
    $likedBy    = (int)($_GET['likedBy'] ?? 0);

    $wheres  = [];
    $params = [$userId, $userId]; // los dos primeros ? son de los subselect (like/share por mí)

    if ($likedBy > 0) {
        // Los "me gusta" son PRIVADOS: sólo el propio usuario puede verlos.
        if ($likedBy !== (int)$userId) {
            http_response_code(403);
            exit(json_encode(['error' => 'Solo podés ver tus propios me gusta.']));
        }
        $wheres[] = 'p.id IN (SELECT l.post_id FROM likes l WHERE l.user_id = ?)';
        $params[] = $likedBy;
    } elseif ($sharedBy > 0) {
        // Publicaciones que aparecen en la tabla shares para ese usuario.
        $wheres[] = 'p.id IN (SELECT s.post_id FROM shares s WHERE s.user_id = ?)';
        $params[] = $sharedBy;
    } elseif ($onlyAuthor > 0) {
        $wheres[] = 'p.user_id = ?';
        $params[] = $onlyAuthor;
    }

    // --- Filtro por zona cercana (Haversine) ---
    $distSelect = ", NULL AS distance_km";
    $orderBy = "ORDER BY p.created_at DESC";
    if ($geoActive) {
        $hav = haversine_sql();
        $distSelect = ", (CASE WHEN p.lat IS NULL OR p.lng IS NULL THEN NULL ELSE $hav END) AS distance_km";
        // Los 3 ? del SELECT van justo después de los 2 de like/share.
        array_splice($params, 2, 0, [$qLat, $qLng, $qLat]);
        if ($qIncludeWithout) {
            // Cercanos dentro del radio + posts viejos sin ubicación (para no
            // esconder todo el historial). Los sin ubicación van al final.
            $wheres[] = "(p.lat IS NULL OR p.lng IS NULL OR $hav <= ?)";
            $params[] = $qLat; $params[] = $qLng; $params[] = $qLat; $params[] = $qRadius;
        } else {
            // Estricto estilo Marketplace: solo cercanos con ubicación.
            $wheres[] = "(p.lat IS NOT NULL AND p.lng IS NOT NULL AND $hav <= ?)";
            $params[] = $qLat; $params[] = $qLng; $params[] = $qLat; $params[] = $qRadius;
        }
        $orderBy = "ORDER BY (p.lat IS NULL OR p.lng IS NULL) ASC, distance_km ASC, p.created_at DESC";
    }

    $where = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

    $sql = "
        SELECT
          p.id, p.body, p.image_url, p.lat, p.lng, p.place_name,
          UNIX_TIMESTAMP(p.created_at) AS created_at_unix,
          u.id AS author_id, u.full_name AS author_name, u.avatar_url AS author_avatar, r.nombre AS author_type,
          (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS like_count,
          (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id AND l.user_id = ?) AS liked_by_me,
          (SELECT COUNT(*) FROM shares s WHERE s.post_id = p.id) AS share_count,
          (SELECT COUNT(*) FROM shares s WHERE s.post_id = p.id AND s.user_id = ?) AS shared_by_me
          $distSelect
        FROM posts p
			JOIN users u ON u.id = p.user_id
			JOIN roles r ON r.roles_id = u.roles_id
        $where
        $orderBy
        LIMIT 100
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $posts = [];
    foreach ($rows as $row) {
        $posts[] = map_post_row($row, fetch_comments($pdo, $row['id']));
    }

    echo json_encode(['posts' => $posts]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mod_exigir_puede_escribir($pdo, (int)$userId); // suspendidos y spam
    $data = json_input();
    $text = trim($data['text'] ?? '');
    $imageUrl = trim($data['imageUrl'] ?? '');
    $lat = geo_float($data['lat'] ?? null);
    $lng = geo_float($data['lng'] ?? null);
    $placeName = trim((string)($data['place_name'] ?? $data['placeName'] ?? ''));

    if ($text === '' || mb_strlen($text) > 500) {
        http_response_code(400);
        exit(json_encode(['error' => 'El texto debe tener entre 1 y 500 caracteres.']));
    }
    if ($imageUrl !== '' && !preg_match('#^https?://#i', $imageUrl)) {
        http_response_code(400);
        exit(json_encode(['error' => 'La URL de la imagen debe empezar con http:// o https://']));
    }
    // Ubicación opcional pero validada: si viene a medias se ignora.
    $hasGeo = ($lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180);
    if (!$hasGeo) { $lat = null; $lng = null; }
    if ($placeName !== '' && mb_strlen($placeName) > 255) {
        $placeName = mb_substr($placeName, 0, 255);
    }
    if ($placeName === '') $placeName = null;
    if (!$hasGeo) $placeName = null; // sin coords no guardamos nombre suelto

    try {
        $stmt = $pdo->prepare('INSERT INTO posts (user_id, body, image_url, lat, lng, place_name) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $text, $imageUrl ?: null, $lat, $lng, $placeName]);
    } catch (PDOException $e) {
        // Tablas viejas sin las columnas de geo (usuario sin permiso ALTER):
        // reintentamos sin ubicación para no romper la publicación.
        if (stripos($e->getMessage(), 'Unknown column') !== false) {
            $stmt = $pdo->prepare('INSERT INTO posts (user_id, body, image_url) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $text, $imageUrl ?: null]);
        } else {
            throw $e;
        }
    }

    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'withGeo' => $hasGeo]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido.']);