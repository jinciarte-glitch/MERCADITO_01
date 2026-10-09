<?php
// index.php — punto de entrada. Si ya iniciaste sesión te manda al
// feed, si no, te manda al login.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';

header('Location: ' . (current_user_id() ? '/feed.php' : '/api/login.php'));
exit;
?>