<?php
// admin/student_export.php
session_start();
require_once '../includes/db_connect.php';

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Set headers to force download as native Excel (.xls) file format
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Students_List_" . date('Y-m-d') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Build HTML table which Excel can natively parse
echo '<table border="1">';
echo '<tr style="background-color: #f1f5f9; font-weight: bold;">
        <th>S.L</th>
        <th>Admission No</th>
        <th>Full Name</th>
        <th>Roll No</th>
        <th>Class</th>
        <th>Date of Birth</th>
        <th>Gender</th>
        <th>Mobile Number</th>
        <th>Category</th>
        <th>Status</th>
      </tr>';

// Fetch the data
try {
    $stmt = $pdo->query("SELECT * FROM students ORDER BY id ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo '<tr>';
        echo '<td>' . $row['id'] . '</td>';
        echo '<td>' . $row['ad_no'] . '</td>';
        echo '<td>' . $row['name'] . '</td>';
        echo '<td>' . $row['roll'] . '</td>';
        echo '<td>' . $row['class'] . '</td>';
        echo '<td>' . $row['dob'] . '</td>';
        echo '<td>' . $row['gender'] . '</td>';
        echo '<td>' . $row['mobile'] . '</td>';
        echo '<td>' . $row['category'] . '</td>';
        echo '<td>' . $row['status'] . '</td>';
        echo '</tr>';
    }
} catch (PDOException $e) {
    // Silently fail if DB error
}

echo '</table>';
exit;
?>
