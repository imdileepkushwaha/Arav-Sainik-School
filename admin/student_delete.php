<?php
// admin/student_delete.php
session_start();
require_once '../includes/db_connect.php';

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Check if ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = $_GET['id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['success_msg'] = "Student deleted successfully!";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Failed to delete student. Please try again.";
    }
}

// Redirect back to students list
header('Location: students.php');
exit;
?>
