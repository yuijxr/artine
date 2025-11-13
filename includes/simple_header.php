<?php
// Minimal header used for standalone auth pages (logo only)
require_once __DIR__ . '/session.php';
?>
<header class="header-fixed">
    <div class="header-container">
        <div class="header-center" style="width:100%;display:flex;justify-content:center;">
            <a href="/artine3/index.php" class="logo">
                <img src="/artine3/assets/img/logo.png" alt="artine3 Logo">
            </a>
        </div>
    </div>
</header>

<script src="/artine3/assets/js/lib/utils.js"></script>

<?php
// expose minimal globals
echo "<script>window.IS_LOGGED = " . (is_logged_in() ? 'true' : 'false') . ";</script>";
?>
