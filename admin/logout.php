<?php
require_once __DIR__ . '/../includes/session.php';
// clear admin session only
if (session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['admin_id'], $_SESSION['admin_username']);
// Do not clear regular user session here to avoid logging out site users if separate
header('Location: login.php');
exit;
