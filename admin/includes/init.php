<?php
// admin/includes/init.php — session & auth only (no HTML output)
@date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}
