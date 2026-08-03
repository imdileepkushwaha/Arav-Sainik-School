<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!isset($_SESSION['teacher_portal_id']) || !$_SESSION['teacher_portal_id']) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../admin/includes/teacher_helpers.php';
require_once __DIR__ . '/../../admin/includes/erp_helpers.php';
require_once __DIR__ . '/../../admin/includes/settings_helpers.php';

ensureTeacherSchema($pdo);
ensureErpSchema($pdo);
ensureSettingsSchema($pdo);

$teacherId = (int) $_SESSION['teacher_portal_id'];
$teacher = getTeacherById($pdo, $teacherId);

if (!$teacher || !isTeacherActive($teacher) || !isTeacherPortalEnabled($teacher)) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$tp_page = basename($_SERVER['PHP_SELF'], '.php');
$tp_name = htmlspecialchars($teacher['name']);
$tp_photo = getTeacherPortalPhotoUrl($teacher);
$tp_emp_id = htmlspecialchars($teacher['employee_id']);
$tp_school = getSchoolProfile($pdo);
$tp_logo_url = schoolSidebarLogoUrl($tp_school, 'teacher');
$tp_favicon_url = schoolBrandingUrl($tp_school['favicon'] ?? '', 'teacher');
$tp_flash_success = $_SESSION['tp_success'] ?? null;
$tp_flash_error = $_SESSION['tp_error'] ?? null;
unset($_SESSION['tp_success'], $_SESSION['tp_error']);

function tp_flash($url, $message, $type = 'success') {
    $_SESSION['tp_' . ($type === 'error' ? 'error' : 'success')] = $message;
    header('Location: ' . $url);
    exit;
}

function tp_teacherOwnsHomework($pdo, $teacherId, $homeworkId) {
    $stmt = $pdo->prepare("SELECT class_name, section_name FROM homework WHERE id = ?");
    $stmt->execute([(int) $homeworkId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && teacherCanAccessClass($pdo, $teacherId, $row['class_name'], $row['section_name']);
}

function tp_nav_active($page) {
    global $tp_page;
    return $tp_page === $page ? ' active' : '';
}

$tp_must_change = teacherMustChangePassword($teacher);
if ($tp_must_change && $tp_page !== 'change-password' && $tp_page !== 'logout') {
    header('Location: change-password.php?required=1');
    exit;
}
