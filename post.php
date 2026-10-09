<?php
// post.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';
if (!current_user_id()) {
 header('Location: /api/login.php');
 exit;
}


$postId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>

<script>
(function () {
    try {
        var theme = localStorage.getItem("mercadito-theme");

        if (!theme) {
            theme = window.matchMedia("(prefers-color-scheme: dark)").matches
                ? "dark"
                : "light";
        }

        if (theme === "dark") {
            document.documentElement.setAttribute("data-theme", "dark");
        }
    } catch (e) {}
})();
</script>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="shortcut icon" type="image/x-icon" href="icon.ico">

<title>Publicación — Mercadito</title>

<link rel="preconnect" href="https://fonts.googleapis.com">

<link
    href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Work+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet"
>

<link rel="stylesheet" href="base.css">
<link rel="stylesheet" href="feed.css">

</head>

<body class="app-body">

<?php require __DIR__ . '/includes/navbar.php'; ?>

<div class="app-layout">

    <?php require __DIR__ . '/includes/sidebar-left.php'; ?>

    <main class="app-center">

        <div id="post-loading">
            Cargando publicación…
        </div>

        <div id="post-content" style="display:none;">

            <article class="card post-card" id="post">

                <div class="post-header">

                    <a href="#" id="post-avatar-link" class="post-author-link">
                        <img
                            id="post-avatar"
                            class="avatar avatar-md"
                            src=""
                            alt=""
                        >
                    </a>

                    <div class="post-header-meta">
                        <a href="#" id="post-name-link" class="post-author-link">
                            <span class="post-author">
                                <strong id="post-name"></strong>
                                <span id="post-badge"></span>
                                <span id="post-username" class="post-username"></span>
                            </span>
                        </a>
                        <span id="post-date" class="post-time"></span>
                        <span id="post-loc" class="post-time post-loc" style="display:none;"></span>
                    </div>

                </div>

                <p id="post-text" class="post-text"></p>

                <img
                    id="post-image"
                    class="post-image"
                    src=""
                    alt=""
                    style="display:none;"
                >

                <div class="post-actions" id="post-actions">
                    <!-- Botones de like / comentarios / compartir: los arma post.js (misma lógica que feed.js) -->
                </div>

                <div class="comments-section" id="post-comments-section" hidden>
                    <div class="comments-list" id="post-comments-list"></div>
                    <form class="comment-form" id="post-comment-form">
                        <input type="text" id="post-comment-input" name="comment" placeholder="Escribí un comentario…" maxlength="240" required>
                        <button type="submit" class="btn-secondary">Comentar</button>
                    </form>
                </div>

            </article>

        </div>

        <div
            id="post-error"
            style="display:none;"
        >
            No se pudo encontrar la publicación.
        </div>

    </main>

    <?php require __DIR__ . '/includes/sidebar-right.php'; ?>

</div>

<div class="toast" id="toast"></div>

<script src="javascript/ui.js"></script>
<script src="javascript/theme.js"></script>
<script src="javascript/api.js"></script>
<script src="javascript/geo.js"></script>
<script src="javascript/nav.js"></script>
<script src="javascript/sidebar.js"></script>

<script
    src="javascript/post.js"
    data-post-id="<?= htmlspecialchars($postId ?? '') ?>"
></script>


</body>
</html>