<?php
// JSON endpoint — keep the output clean so a stray notice can never corrupt the response.
ini_set('display_errors', '0');
require_once 'includes/init.php';

// Release the session lock immediately. This endpoint is hit on every keystroke,
// and the PHP built-in dev server (php -S) is single-threaded — holding the session
// open here can serialise/deadlock concurrent requests and make search appear to "hang".
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

require_once '../includes/db_connect.php';
require_once 'includes/student_helpers.php';
require_once 'includes/teacher_helpers.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}
$like = '%' . $q . '%';
$results = [];

try {
    $stmt = $pdo->prepare("SELECT id, ad_no, name, class FROM students WHERE status='Active' AND (name LIKE ? OR ad_no LIKE ?) LIMIT 5");
    $stmt->execute([$like, $like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $results[] = ['type' => 'Student', 'title' => $r['name'], 'meta' => $r['ad_no'] . ' · ' . $r['class'], 'url' => 'student_view.php?id=' . $r['id'], 'icon' => 'fa-user-graduate'];
    }
} catch (Throwable $e) { /* skip students on error */ }

try {
    $stmt = $pdo->prepare("SELECT id, employee_id, name, subject FROM teachers WHERE status='Active' AND (name LIKE ? OR employee_id LIKE ?) LIMIT 5");
    $stmt->execute([$like, $like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $results[] = ['type' => 'Teacher', 'title' => $r['name'], 'meta' => $r['employee_id'] . ' · ' . $r['subject'], 'url' => 'teacher_view.php?id=' . $r['id'], 'icon' => 'fa-chalkboard-teacher'];
    }
} catch (Throwable $e) { /* skip teachers on error */ }

try {
    $stmt = $pdo->prepare("SELECT name FROM school_classes WHERE name LIKE ? AND status='Active' LIMIT 3");
    $stmt->execute([$like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $results[] = ['type' => 'Class', 'title' => $r['name'], 'meta' => 'Classes & Sections', 'url' => 'classes.php', 'icon' => 'fa-school'];
    }
} catch (Throwable $e) { /* classes table may be missing on a fresh install; ignore */ }

// Modules / pages — so typing "fees", "attendance", "exam", "settings" etc. jumps to the page.
$pages = [
    ['title' => 'Dashboard', 'url' => 'dashboard.php', 'icon' => 'fa-home', 'meta' => 'Overview', 'kw' => 'dashboard home overview'],
    ['title' => 'Student List', 'url' => 'students.php', 'icon' => 'fa-user-graduate', 'meta' => 'Students', 'kw' => 'students pupils student list'],
    ['title' => 'Add New Student', 'url' => 'student_add.php', 'icon' => 'fa-user-plus', 'meta' => 'Students', 'kw' => 'add student new student admission register'],
    ['title' => 'Classes & Sections', 'url' => 'classes.php', 'icon' => 'fa-school', 'meta' => 'Students', 'kw' => 'classes sections class'],
    ['title' => 'Student Documents', 'url' => 'student_documents.php', 'icon' => 'fa-folder-open', 'meta' => 'Students', 'kw' => 'documents files student documents'],
    ['title' => 'Promote Students', 'url' => 'student_promote.php', 'icon' => 'fa-arrow-up', 'meta' => 'Students', 'kw' => 'promote promotion upgrade'],
    ['title' => 'Student ID Cards', 'url' => 'student_id_card.php', 'icon' => 'fa-id-card', 'meta' => 'Students', 'kw' => 'id card identity card'],
    ['title' => 'Student Portal Accounts', 'url' => 'portal_accounts.php', 'icon' => 'fa-key', 'meta' => 'Students', 'kw' => 'portal accounts login credentials'],
    ['title' => 'Topper Manage', 'url' => 'toppers.php', 'icon' => 'fa-trophy', 'meta' => 'Students', 'kw' => 'topper toppers results website pride rank'],
    ['title' => 'Admission Enquiries', 'url' => 'admission_enquiries.php', 'icon' => 'fa-clipboard-list', 'meta' => 'Students', 'kw' => 'admission enquiry enquiries lead'],
    ['title' => 'Academic Sessions', 'url' => 'academic_sessions.php', 'icon' => 'fa-calendar-alt', 'meta' => 'Academic', 'kw' => 'session academic year'],
    ['title' => 'Subjects', 'url' => 'subjects.php', 'icon' => 'fa-book', 'meta' => 'Academic', 'kw' => 'subjects subject'],
    ['title' => 'Class Timetable', 'url' => 'class_timetable.php', 'icon' => 'fa-table', 'meta' => 'Academic', 'kw' => 'timetable schedule periods'],
    ['title' => 'Notice Board', 'url' => 'notices.php', 'icon' => 'fa-bullhorn', 'meta' => 'Academic', 'kw' => 'notice notices announcement circular'],
    ['title' => 'Mark Attendance', 'url' => 'attendance.php', 'icon' => 'fa-calendar-check', 'meta' => 'Attendance', 'kw' => 'attendance mark attendance present absent'],
    ['title' => 'Attendance Report', 'url' => 'attendance_report.php', 'icon' => 'fa-chart-bar', 'meta' => 'Attendance', 'kw' => 'attendance report monthly'],
    ['title' => 'Fee Structure', 'url' => 'fees.php', 'icon' => 'fa-file-invoice-dollar', 'meta' => 'Fees', 'kw' => 'fees fee structure amount'],
    ['title' => 'Collect Fee', 'url' => 'fee_collect.php', 'icon' => 'fa-hand-holding-usd', 'meta' => 'Fees', 'kw' => 'collect fee payment pay dues'],
    ['title' => 'Fee Reports', 'url' => 'fee_reports.php', 'icon' => 'fa-chart-line', 'meta' => 'Fees', 'kw' => 'fee report collection income'],
    ['title' => 'Manage Exams', 'url' => 'exams.php', 'icon' => 'fa-edit', 'meta' => 'Examinations', 'kw' => 'exam exams examination test'],
    ['title' => 'Enter Marks', 'url' => 'marks.php', 'icon' => 'fa-pen', 'meta' => 'Examinations', 'kw' => 'marks grades scores enter marks'],
    ['title' => 'Result Analytics', 'url' => 'exam_analytics.php', 'icon' => 'fa-chart-pie', 'meta' => 'Examinations', 'kw' => 'result analytics results performance'],
    ['title' => 'Certificates', 'url' => 'certificates.php', 'icon' => 'fa-certificate', 'meta' => 'Records', 'kw' => 'certificate certificates tc transfer bonafide character'],
    ['title' => 'Hostel', 'url' => 'hostel.php', 'icon' => 'fa-bed', 'meta' => 'Facilities', 'kw' => 'hostel room boarding allotment'],
    ['title' => 'Hostel Fee Structure', 'url' => 'hostel_fees.php', 'icon' => 'fa-file-invoice-dollar', 'meta' => 'Hostel', 'kw' => 'hostel fee structure amount'],
    ['title' => 'Collect Hostel Fee', 'url' => 'hostel_fee_collect.php', 'icon' => 'fa-hand-holding-usd', 'meta' => 'Hostel', 'kw' => 'hostel fee collect payment receipt'],
    ['title' => 'Transport', 'url' => 'transport.php', 'icon' => 'fa-bus', 'meta' => 'Facilities', 'kw' => 'transport bus vehicle route stop'],
    ['title' => 'Transport Fee Structure', 'url' => 'transport_fees.php', 'icon' => 'fa-file-invoice-dollar', 'meta' => 'Transport', 'kw' => 'transport fee structure amount'],
    ['title' => 'Collect Transport Fee', 'url' => 'transport_fee_collect.php', 'icon' => 'fa-hand-holding-usd', 'meta' => 'Transport', 'kw' => 'transport fee collect payment receipt'],
    ['title' => 'SMS / WhatsApp', 'url' => 'notifications.php', 'icon' => 'fa-bell', 'meta' => 'Communication', 'kw' => 'sms whatsapp notification message'],
    ['title' => 'Teacher List', 'url' => 'teachers.php', 'icon' => 'fa-chalkboard-teacher', 'meta' => 'Teachers', 'kw' => 'teachers staff teacher list'],
    ['title' => 'Add New Teacher', 'url' => 'teacher_add.php', 'icon' => 'fa-user-plus', 'meta' => 'Teachers', 'kw' => 'add teacher new teacher staff'],
    ['title' => 'Teacher Attendance', 'url' => 'teacher_attendance.php', 'icon' => 'fa-user-check', 'meta' => 'Teachers', 'kw' => 'teacher attendance staff attendance'],
    ['title' => 'Leave Requests', 'url' => 'leave_requests.php', 'icon' => 'fa-calendar-minus', 'meta' => 'Teachers', 'kw' => 'leave requests leave'],
    ['title' => 'Teacher Timetable', 'url' => 'teacher_timetable.php', 'icon' => 'fa-table', 'meta' => 'Teachers', 'kw' => 'teacher timetable schedule'],
    ['title' => 'Teacher Portal Accounts', 'url' => 'teacher_portal_accounts.php', 'icon' => 'fa-key', 'meta' => 'Teachers', 'kw' => 'teacher portal accounts login'],
    ['title' => 'Settings', 'url' => 'settings.php', 'icon' => 'fa-cog', 'meta' => 'System', 'kw' => 'settings logo favicon signature school profile configuration'],
];
$qLower = strtolower($q);
$pageHits = 0;
foreach ($pages as $pg) {
    if (strpos(strtolower($pg['title']), $qLower) !== false || strpos($pg['kw'], $qLower) !== false) {
        $results[] = ['type' => 'Page', 'title' => $pg['title'], 'meta' => $pg['meta'] . ' module', 'url' => $pg['url'], 'icon' => $pg['icon']];
        if (++$pageHits >= 6) {
            break;
        }
    }
}

echo json_encode(['results' => $results]);
