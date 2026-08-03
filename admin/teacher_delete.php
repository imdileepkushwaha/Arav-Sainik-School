<?php
session_start();
require_once '../includes/db_connect.php';
require_once 'includes/teacher_helpers.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int) $_GET['id'];
    try {
        $pdo->prepare("DELETE FROM teacher_timetable WHERE teacher_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM teachers WHERE id = ?")->execute([$id]);
        $_SESSION['success_msg'] = 'Teacher deleted successfully.';
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = 'Failed to delete teacher.';
    }
}

header('Location: teachers.php');
exit;
