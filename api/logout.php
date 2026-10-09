<?php
// api/logout.php — POST, cierra la sesión.
require_once __DIR__ . '/../includes/bootstrap.php';
logout_user();
echo json_encode(['ok' => true]);
