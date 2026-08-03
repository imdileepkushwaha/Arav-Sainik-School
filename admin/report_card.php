<?php
session_start();
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';
require_once 'includes/settings_helpers.php';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}
ensureErpSchema($pdo);
ensureSettingsSchema($pdo);
$school = getSchoolProfile($pdo);
$studentId = (int) ($_GET['student_id'] ?? 0);
$examId = (int) ($_GET['exam_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->execute([$examId]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student || !$exam) die('Invalid report card request.');
$marks = getStudentMarksForExam($pdo, $studentId, $examId);
$totalObt = 0; $totalMax = 0;
foreach ($marks as $m) {
    $totalObt += (float)($m['marks_obtained'] ?? 0);
    $totalMax += (int)$m['max_marks'];
}
$pct = $totalMax ? round($totalObt / $totalMax * 100, 1) : 0;
?><!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Report Card — <?php echo htmlspecialchars($student['name']); ?></title>
<link rel="stylesheet" href="assets/css/admin.css">
<style>@media print{.no-print{display:none}}body{padding:24px;max-width:800px;margin:0 auto}.rc{border:2px solid #334155;border-radius:12px;padding:28px}</style>
</head><body>
<div class="no-print" style="margin-bottom:16px"><button onclick="window.print()" class="btn-header-action btn-header-primary">Print / Save PDF</button></div>
<div class="rc">
    <h2 style="text-align:center;margin:0"><?php echo htmlspecialchars($school['name']); ?></h2>
    <p style="text-align:center"><?php echo htmlspecialchars($school['tagline']); ?></p>
    <p style="text-align:center">Report Card — <?php echo htmlspecialchars($exam['name']); ?></p>
    <hr>
    <p><strong>Name:</strong> <?php echo htmlspecialchars($student['name']); ?> &nbsp; <strong>Adm No:</strong> <?php echo htmlspecialchars($student['ad_no']); ?></p>
    <p><strong>Class:</strong> <?php echo htmlspecialchars($student['class']); ?> (<?php echo htmlspecialchars($student['section'] ?? 'A'); ?>) &nbsp; <strong>Roll:</strong> <?php echo htmlspecialchars($student['roll']); ?></p>
    <table style="width:100%;border-collapse:collapse;margin:20px 0">
        <thead><tr style="background:#f1f5f9"><th style="border:1px solid #ccc;padding:8px">Subject</th><th style="border:1px solid #ccc;padding:8px">Max</th><th style="border:1px solid #ccc;padding:8px">Obtained</th><th style="border:1px solid #ccc;padding:8px">Grade</th></tr></thead>
        <tbody>
        <?php foreach ($marks as $m): ?>
        <tr>
            <td style="border:1px solid #ccc;padding:8px"><?php echo htmlspecialchars($m['subject_name']); ?></td>
            <td style="border:1px solid #ccc;padding:8px;text-align:center"><?php echo $m['max_marks']; ?></td>
            <td style="border:1px solid #ccc;padding:8px;text-align:center"><?php echo displayVal($m['marks_obtained']); ?></td>
            <td style="border:1px solid #ccc;padding:8px;text-align:center"><?php echo displayVal($m['grade']); ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="font-weight:bold"><td style="border:1px solid #ccc;padding:8px">Total</td><td style="border:1px solid #ccc;padding:8px;text-align:center"><?php echo $totalMax; ?></td><td style="border:1px solid #ccc;padding:8px;text-align:center"><?php echo $totalObt; ?></td><td style="border:1px solid #ccc;padding:8px;text-align:center"><?php echo $pct; ?>%</td></tr>
        </tbody>
    </table>
    <p style="margin-top:40px">Class Teacher Signature &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Principal Signature</p>
</div>
</body></html>
