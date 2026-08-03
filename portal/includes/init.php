<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
if (!isset($_SESSION['student_portal_id']) || !$_SESSION['student_portal_id']) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../admin/includes/student_helpers.php';
require_once __DIR__ . '/../../admin/includes/erp_helpers.php';
require_once __DIR__ . '/../../admin/includes/settings_helpers.php';

ensureStudentSchema($pdo);
ensureErpSchema($pdo);
ensureSettingsSchema($pdo);

$id = (int) $_SESSION['student_portal_id'];
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ? AND portal_enabled = 1");
$stmt->execute([$id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student || $student['status'] !== 'Active') {
    session_destroy();
    header('Location: index.php');
    exit;
}

$sp_page = basename($_SERVER['PHP_SELF'], '.php');
$sp_name = htmlspecialchars($student['name']);
$sp_photo = getStudentPhotoUrl($student);
if (strpos($sp_photo, 'http') !== 0) {
    $sp_photo = '../admin/' . ltrim($sp_photo, '/');
}
$sp_ad_no = htmlspecialchars($student['ad_no']);
$school = getSchoolProfile($pdo);
$sp_logo_url = schoolSidebarLogoUrl($school, 'portal');
$sp_favicon_url = schoolBrandingUrl($school['favicon'] ?? '', 'portal');

function sp_nav_active($page) {
    global $sp_page;
    return $sp_page === $page ? ' active' : '';
}
