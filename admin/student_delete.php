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
    $id = (int) $_GET['id'];

    try {
        $pdo->beginTransaction();

        // Clear hostel allotment so rooms/hostels can be deleted later
        $pdo->prepare(
            "UPDATE hostel_allotments
             SET status = 'Vacated', allotted_to = CURDATE()
             WHERE student_id = ? AND status = 'Active'"
        )->execute([$id]);

        // Related assignment rows (safe if tables missing)
        foreach ([
            'student_transport',
            'hostel_student_plans',
            'student_guardians',
            'student_documents',
        ] as $table) {
            try {
                $pdo->prepare("DELETE FROM `$table` WHERE student_id = ?")->execute([$id]);
            } catch (PDOException $e) {
                // Table may not exist in older installs
            }
        }

        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        $_SESSION['success_msg'] = "Student deleted successfully!";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error_msg'] = "Failed to delete student. Please try again.";
    }
}

// Redirect back to students list
header('Location: students.php');
exit;
