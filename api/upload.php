<?php
// api/upload.php
//  POST -> recibe un archivo en el campo "imagen", lo valida,
//          lo guarda en /uploads y devuelve su URL pública.
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/moderacion-lib.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Método no permitido.']));
}

exigir_no_suspendido($pdo, (int)current_user_id()); // suspendido: no puede subir imágenes

if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit(json_encode(['error' => 'No se pudo subir la imagen.']));
}

$file = $_FILES['imagen'];

// Límite de tamaño: 5 MB
$maxBytes = 5 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    http_response_code(400);
    exit(json_encode(['error' => 'La imagen no puede pesar más de 5 MB.']));
}

// No confiamos en la extensión ni en el mime-type que manda el navegador:
// usamos getimagesize() para chequear que el contenido sea realmente una imagen.
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    http_response_code(400);
    exit(json_encode(['error' => 'El archivo no es una imagen válida.']));
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
$mime = $imageInfo['mime'];
if (!isset($allowed[$mime])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Formato no permitido. Usá JPG, PNG, GIF o WEBP.']));
}

$ext = $allowed[$mime];
// Nombre de archivo aleatorio: evita colisiones y evita que el usuario
// controle el nombre/extensión del archivo guardado.
$filename = bin2hex(random_bytes(16)) . '.' . $ext;

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destPath = $uploadDir . $filename;
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    http_response_code(500);
    exit(json_encode(['error' => 'No se pudo guardar la imagen.']));
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$url    = $scheme . '://' . $host . '/uploads/' . $filename;

echo json_encode(['ok' => true, 'imageUrl' => $url]);