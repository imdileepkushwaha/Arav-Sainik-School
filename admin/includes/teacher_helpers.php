<?php
// admin/includes/teacher_helpers.php

require_once __DIR__ . '/class_helpers.php';

function ensureTeacherSchema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `teachers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `employee_id` varchar(30) NOT NULL,
        `name` varchar(100) NOT NULL,
        `email` varchar(100) DEFAULT NULL,
        `phone` varchar(20) NOT NULL,
        `gender` enum('Male','Female','Other') NOT NULL DEFAULT 'Male',
        `dob` date DEFAULT NULL,
        `join_date` date DEFAULT NULL,
        `subject` varchar(100) DEFAULT NULL,
        `qualification` varchar(150) DEFAULT NULL,
        `experience_years` varchar(20) DEFAULT NULL,
        `class_assigned` varchar(50) DEFAULT NULL,
        `section_assigned` varchar(10) DEFAULT NULL,
        `address` text DEFAULT NULL,
        `city` varchar(80) DEFAULT NULL,
        `state` varchar(80) DEFAULT NULL,
        `pincode` varchar(15) DEFAULT NULL,
        `photo` varchar(255) DEFAULT NULL,
        `salary` decimal(12,2) DEFAULT NULL,
        `bank_name` varchar(100) DEFAULT NULL,
        `bank_account` varchar(50) DEFAULT NULL,
        `ifsc_code` varchar(30) DEFAULT NULL,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        `description` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `employee_id` (`employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $columns = [
        'name'              => "VARCHAR(100) DEFAULT NULL",
        'gender'            => "ENUM('Male','Female','Other') NOT NULL DEFAULT 'Male'",
        'dob'               => "DATE DEFAULT NULL",
        'qualification'     => "VARCHAR(150) DEFAULT NULL",
        'experience_years'  => "VARCHAR(20) DEFAULT NULL",
        'class_assigned'    => "VARCHAR(50) DEFAULT NULL",
        'section_assigned'  => "VARCHAR(10) DEFAULT NULL",
        'address'           => "TEXT DEFAULT NULL",
        'city'              => "VARCHAR(80) DEFAULT NULL",
        'state'             => "VARCHAR(80) DEFAULT NULL",
        'pincode'           => "VARCHAR(15) DEFAULT NULL",
        'photo'             => "VARCHAR(255) DEFAULT NULL",
        'salary'            => "DECIMAL(12,2) DEFAULT NULL",
        'bank_name'         => "VARCHAR(100) DEFAULT NULL",
        'bank_account'      => "VARCHAR(50) DEFAULT NULL",
        'ifsc_code'         => "VARCHAR(30) DEFAULT NULL",
        'status'            => "ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'",
        'description'       => "TEXT DEFAULT NULL",
    ];
    foreach ($columns as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE `teachers` ADD COLUMN `$col` $def");
        } catch (PDOException $e) {
        }
    }

    try {
        $pdo->exec("UPDATE teachers SET name = TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) WHERE (name IS NULL OR name = '') AND first_name IS NOT NULL");
    } catch (PDOException $e) {
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `teacher_timetable` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `teacher_id` int(11) NOT NULL,
        `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
        `period_no` tinyint(4) NOT NULL,
        `start_time` time DEFAULT NULL,
        `end_time` time DEFAULT NULL,
        `class_name` varchar(50) DEFAULT NULL,
        `section_name` varchar(10) DEFAULT NULL,
        `subject_name` varchar(100) DEFAULT NULL,
        `room_no` varchar(30) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `teacher_slot` (`teacher_id`,`day_of_week`,`period_no`),
        KEY `teacher_id` (`teacher_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $portalCols = [
        'portal_enabled'       => "TINYINT(1) NOT NULL DEFAULT 0",
        'portal_password'      => "VARCHAR(255) DEFAULT NULL",
        'portal_must_change'   => "TINYINT(1) NOT NULL DEFAULT 0",
        'signature'            => "VARCHAR(255) DEFAULT NULL",
    ];
    foreach ($portalCols as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE `teachers` ADD COLUMN `$col` $def");
        } catch (PDOException $e) {
        }
    }
}

function getWeekDays() {
    return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
}

function generateEmployeeId($pdo) {
    $prefix = 'EMP' . date('Y');
    try {
        $stmt = $pdo->prepare("SELECT employee_id FROM teachers WHERE employee_id LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetchColumn();
        if ($last && strlen($last) > strlen($prefix)) {
            $seq = (int) substr($last, strlen($prefix)) + 1;
        } else {
            $seq = 1;
        }
    } catch (PDOException $e) {
        $seq = 1;
    }
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function getDefaultTeacherFormData() {
    return [
        'name'              => '',
        'phone'             => '',
        'email'             => '',
        'gender'            => 'Male',
        'dob'               => '',
        'join_date'         => date('Y-m-d'),
        'subject'           => '',
        'qualification'     => '',
        'experience_years'  => '',
        'class_assigned'    => '',
        'section_assigned'  => 'A',
        'address'           => '',
        'city'              => '',
        'state'             => '',
        'pincode'           => '',
        'salary'            => '',
        'bank_name'         => '',
        'bank_account'      => '',
        'ifsc_code'         => '',
        'status'            => 'Active',
        'description'       => '',
    ];
}

function teacherFromRow($row) {
    $data = getDefaultTeacherFormData();
    foreach ($data as $key => $val) {
        if (isset($row[$key]) && $row[$key] !== null && $row[$key] !== '') {
            $data[$key] = $row[$key];
        }
    }
    if (!empty($row['name'])) {
        $data['name'] = $row['name'];
    } elseif (!empty($row['first_name'])) {
        $data['name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    }
    if (!empty($row['dob'])) {
        $data['dob'] = date('Y-m-d', strtotime($row['dob']));
    }
    if (!empty($row['join_date'])) {
        $data['join_date'] = date('Y-m-d', strtotime($row['join_date']));
    }
    return $data;
}

function getTeacherPhotoUrl($teacher) {
    if (!empty($teacher['photo']) && file_exists(__DIR__ . '/../' . $teacher['photo'])) {
        return $teacher['photo'];
    }
    $name = urlencode($teacher['name'] ?? 'Teacher');
    return 'https://ui-avatars.com/api/?name=' . $name . '&background=2563eb&color=fff&bold=true';
}

function uploadTeacherPhoto($file, $employeeId) {
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed, true)) {
        return false;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return false;
    }
    $dir = __DIR__ . '/../uploads/teachers/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $employeeId) . '_' . time() . '.' . strtolower($ext);
    $path = $dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return 'uploads/teachers/' . $filename;
    }
    return false;
}

function getTeacherById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ?");
    $stmt->execute([(int) $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getClassTeacherForClass($pdo, $className, $sectionName = null) {
    ensureTeacherSchema($pdo);
    $className = trim((string) $className);
    if ($className === '') {
        return null;
    }
    $section = trim((string) $sectionName);
    if ($section !== '') {
        $stmt = $pdo->prepare(
            "SELECT * FROM teachers
             WHERE status = 'Active' AND class_assigned = ? AND (section_assigned = ? OR section_assigned IS NULL OR section_assigned = '')
             ORDER BY (section_assigned = ?) DESC, id ASC LIMIT 1"
        );
        $stmt->execute([$className, $section, $section]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE status = 'Active' AND class_assigned = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$className]);
    }
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function uploadTeacherSignature($file, $employeeId) {
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed, true)) {
        return false;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return false;
    }
    $dir = __DIR__ . '/../uploads/signatures/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext = $extMap[$mime] ?? 'png';
    $filename = 'tsign_' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $employeeId) . '_' . time() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return 'uploads/signatures/' . $filename;
    }
    return false;
}

function getAllTeachers($pdo, $activeOnly = false) {
    $sql = "SELECT * FROM teachers";
    if ($activeOnly) {
        $sql .= " WHERE status = 'Active'";
    }
    $sql .= " ORDER BY name ASC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getTeacherTimetable($pdo, $teacherId) {
    $stmt = $pdo->prepare("SELECT * FROM teacher_timetable WHERE teacher_id = ? ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), period_no ASC");
    $stmt->execute([(int) $teacherId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $grid = [];
    foreach ($rows as $row) {
        $grid[$row['day_of_week']][$row['period_no']] = $row;
    }
    return $grid;
}

function saveTeacherTimetableSlot($pdo, $teacherId, $day, $period, $data) {
    $class = trim($data['class_name'] ?? '');
    $subject = trim($data['subject_name'] ?? '');
    if ($class === '' && $subject === '') {
        $stmt = $pdo->prepare("DELETE FROM teacher_timetable WHERE teacher_id = ? AND day_of_week = ? AND period_no = ?");
        $stmt->execute([(int) $teacherId, $day, (int) $period]);
        return;
    }
    $pdo->prepare(
        "INSERT INTO teacher_timetable (teacher_id, day_of_week, period_no, start_time, end_time, class_name, section_name, subject_name, room_no)
         VALUES (?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE start_time=VALUES(start_time), end_time=VALUES(end_time),
         class_name=VALUES(class_name), section_name=VALUES(section_name), subject_name=VALUES(subject_name), room_no=VALUES(room_no)"
    )->execute([
        (int) $teacherId, $day, (int) $period,
        $data['start_time'] ?: null,
        $data['end_time'] ?: null,
        $class,
        trim($data['section_name'] ?? '') ?: null,
        $subject,
        trim($data['room_no'] ?? '') ?: null,
    ]);
}

function defaultPeriodTimes() {
    return [
        1 => ['08:00', '08:45'],
        2 => ['08:45', '09:30'],
        3 => ['09:45', '10:30'],
        4 => ['10:30', '11:15'],
        5 => ['11:30', '12:15'],
        6 => ['12:15', '13:00'],
        7 => ['13:30', '14:15'],
        8 => ['14:15', '15:00'],
    ];
}

function validateTeacherForm($data) {
    $errors = [];
    if (trim($data['name'] ?? '') === '') {
        $errors[] = 'Teacher name is required.';
    }
    if (trim($data['phone'] ?? '') === '') {
        $errors[] = 'Mobile number is required.';
    }
    if (trim($data['subject'] ?? '') === '') {
        $errors[] = 'Subject is required.';
    }
    return $errors;
}

function searchTeachers($pdo, $query, $limit = 15) {
    $like = '%' . $query . '%';
    $stmt = $pdo->prepare("SELECT id, employee_id, name, subject, phone, status, portal_enabled FROM teachers WHERE name LIKE ? OR employee_id LIKE ? OR phone LIKE ? OR subject LIKE ? ORDER BY name ASC LIMIT ?");
    $stmt->bindValue(1, $like);
    $stmt->bindValue(2, $like);
    $stmt->bindValue(3, $like);
    $stmt->bindValue(4, $like);
    $stmt->bindValue(5, (int) $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTeacherPortalDefaultPassword() {
    return 'Teacher@123';
}

function isTeacherActive($teacher) {
    return strcasecmp(trim((string) ($teacher['status'] ?? '')), 'Active') === 0;
}

function isTeacherPortalEnabled($teacher) {
    return isset($teacher['portal_enabled']) && (int) $teacher['portal_enabled'] === 1;
}

function ensureTeacherPortalRepair($pdo) {
    ensureTeacherSchema($pdo);
    try {
        $ids = $pdo->query(
            "SELECT id FROM teachers WHERE portal_enabled = 1 AND (portal_password IS NULL OR portal_password = '')"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            enableTeacherPortal($pdo, (int) $id, getTeacherPortalDefaultPassword());
        }
    } catch (PDOException $e) {
        // ignore repair errors
    }
}

function enableTeacherPortal($pdo, $teacherId, $password = null) {
    ensureTeacherSchema($pdo);
    $useDefault = ($password === null || $password === '');
    if ($useDefault) {
        $password = getTeacherPortalDefaultPassword();
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $mustChange = $useDefault ? 1 : 0;
    $pdo->prepare(
        "UPDATE teachers SET portal_enabled = 1, portal_password = ?, portal_must_change = ? WHERE id = ?"
    )->execute([$hash, $mustChange, (int) $teacherId]);
    return $password;
}

function enableAllTeachersPortal($pdo) {
    ensureTeacherSchema($pdo);
    $default = getTeacherPortalDefaultPassword();
    $ids = $pdo->query("SELECT id FROM teachers WHERE status = 'Active'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        enableTeacherPortal($pdo, (int) $id, $default);
    }
    return count($ids);
}

function teacherMustChangePassword($teacher) {
    return !empty($teacher['portal_must_change']);
}

function getTeacherLoginStatus($pdo, $employeeId, $password) {
    ensureTeacherPortalRepair($pdo);

    $employeeId = trim($employeeId);
    $password = trim($password);
    if ($employeeId === '' || $password === '') {
        return ['ok' => false, 'reason' => 'empty'];
    }

    $stmt = $pdo->prepare("SELECT * FROM teachers WHERE UPPER(TRIM(employee_id)) = UPPER(?) LIMIT 1");
    $stmt->execute([$employeeId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher) {
        return ['ok' => false, 'reason' => 'not_found'];
    }
    if (!isTeacherActive($teacher)) {
        return ['ok' => false, 'reason' => 'inactive'];
    }

    $defaultPass = getTeacherPortalDefaultPassword();

    // First login: auto-enable portal when default password is used
    if (!isTeacherPortalEnabled($teacher) && hash_equals($defaultPass, $password)) {
        enableTeacherPortal($pdo, (int) $teacher['id'], $defaultPass);
        $teacher = getTeacherById($pdo, (int) $teacher['id']);
    }

    if (!isTeacherPortalEnabled($teacher)) {
        return ['ok' => false, 'reason' => 'not_enabled'];
    }

    $hash = $teacher['portal_password'] ?? '';
    if ($hash === '' || !password_verify($password, $hash)) {
        // Allow reset: if admin never enabled but user has old data, try default once more after re-enable
        if (hash_equals($defaultPass, $password)) {
            enableTeacherPortal($pdo, (int) $teacher['id'], $defaultPass);
            $teacher = getTeacherById($pdo, (int) $teacher['id']);
            if (password_verify($password, $teacher['portal_password'] ?? '')) {
                return ['ok' => true, 'teacher' => $teacher];
            }
        }
        return ['ok' => false, 'reason' => 'wrong_password'];
    }

    return ['ok' => true, 'teacher' => $teacher];
}

function authenticateTeacherPortal($pdo, $employeeId, $password) {
    $result = getTeacherLoginStatus($pdo, $employeeId, $password);
    return $result['ok'] ? $result['teacher'] : null;
}

function teacherLoginErrorMessage($reason) {
    switch ($reason) {
        case 'not_found':
            return 'Employee ID not found. Check the ID shown in Admin → Teachers (e.g. EMP20250001).';
        case 'not_enabled':
            return 'Portal is not enabled. Use default password ' . getTeacherPortalDefaultPassword() . ' for first login, or ask admin to enable from Teacher Portal page.';
        case 'inactive':
            return 'This teacher account is inactive. Contact the administrator.';
        case 'wrong_password':
            return 'Incorrect password. Try default: ' . getTeacherPortalDefaultPassword() . ' or ask admin to reset from Admin → Teachers → Teacher Portal.';
        case 'empty':
            return 'Please enter both Employee ID and password.';
        default:
            return 'Login failed. Please try again or contact admin.';
    }
}

function getTeacherPortalPhotoUrl($teacher) {
    $url = getTeacherPhotoUrl($teacher);
    if (strpos($url, 'http') === 0) {
        return $url;
    }
    return '../admin/' . ltrim($url, '/');
}

function getTeacherClassesTaught($pdo, $teacherId) {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT class_name, section_name, subject_name
         FROM teacher_timetable
         WHERE teacher_id = ? AND class_name IS NOT NULL AND class_name != ''
         ORDER BY class_name ASC, section_name ASC"
    );
    $stmt->execute([(int) $teacherId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $classes = [];
    foreach ($rows as $row) {
        $key = $row['class_name'] . '|' . ($row['section_name'] ?: 'A');
        if (!isset($classes[$key])) {
            $classes[$key] = [
                'class_name'   => $row['class_name'],
                'section_name' => $row['section_name'] ?: 'A',
                'subject_name' => $row['subject_name'] ?: '',
            ];
        }
    }
    if (empty($classes)) {
        $teacher = getTeacherById($pdo, $teacherId);
        if ($teacher && !empty($teacher['class_assigned'])) {
            $classes[] = [
                'class_name'   => $teacher['class_assigned'],
                'section_name' => $teacher['section_assigned'] ?: 'A',
                'subject_name' => $teacher['subject'] ?? '',
            ];
        }
    }
    return array_values($classes);
}

function getTeacherTodaySchedule($pdo, $teacherId) {
    $day = date('l');
    $days = getWeekDays();
    if (!in_array($day, $days, true)) {
        return [];
    }
    $grid = getTeacherTimetable($pdo, $teacherId);
    $slots = $grid[$day] ?? [];
    ksort($slots);
    return $slots;
}

function countTeacherWeeklyPeriods($pdo, $teacherId) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM teacher_timetable
         WHERE teacher_id = ? AND (class_name IS NOT NULL AND class_name != '' OR subject_name IS NOT NULL AND subject_name != '')"
    );
    $stmt->execute([(int) $teacherId]);
    return (int) $stmt->fetchColumn();
}

function teacherCanAccessClass($pdo, $teacherId, $className, $sectionName) {
    $classes = getTeacherClassesTaught($pdo, $teacherId);
    foreach ($classes as $c) {
        if ($c['class_name'] === $className && ($c['section_name'] ?: 'A') === ($sectionName ?: 'A')) {
            return true;
        }
    }
    return false;
}

function updateTeacherPortalPassword($pdo, $teacherId, $newPassword) {
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->prepare(
        "UPDATE teachers SET portal_password = ?, portal_must_change = 0 WHERE id = ? AND portal_enabled = 1"
    )->execute([$hash, (int) $teacherId]);
}

function getTeacherAttendanceRecord($pdo, $teacherId, $date) {
    ensureErpSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM teacher_attendance WHERE teacher_id = ? AND attendance_date = ?");
    $stmt->execute([(int) $teacherId, $date]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getTeacherAttendanceForMonth($pdo, $teacherId, $year, $month) {
    ensureErpSchema($pdo);
    $from = sprintf('%04d-%02d-01', $year, $month);
    $to = date('Y-m-t', strtotime($from));
    $stmt = $pdo->prepare(
        "SELECT * FROM teacher_attendance WHERE teacher_id = ? AND attendance_date BETWEEN ? AND ? ORDER BY attendance_date DESC"
    );
    $stmt->execute([(int) $teacherId, $from, $to]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function teacherSelfCheckIn($pdo, $teacherId, $status = 'Present', $remarks = null) {
    ensureErpSchema($pdo);
    $today = date('Y-m-d');
    $now = date('H:i:s');
    if (!in_array($status, ['Present', 'Late'], true)) {
        return ['ok' => false, 'error' => 'Invalid check-in status.'];
    }
    $existing = getTeacherAttendanceRecord($pdo, $teacherId, $today);
    $note = trim((string) $remarks);
    if ($note === '') {
        $note = 'Self check-in via portal';
    }
    if ($existing) {
        if (!empty($existing['check_in_time'])) {
            return ['ok' => false, 'error' => 'You have already checked in today.'];
        }
        $pdo->prepare(
            "UPDATE teacher_attendance SET check_in_time = ?, status = ?, remarks = ? WHERE teacher_id = ? AND attendance_date = ?"
        )->execute([$now, $status, $note, (int) $teacherId, $today]);
        return ['ok' => true, 'time' => $now];
    }
    $pdo->prepare(
        "INSERT INTO teacher_attendance (teacher_id, attendance_date, status, remarks, check_in_time) VALUES (?,?,?,?,?)"
    )->execute([(int) $teacherId, $today, $status, $note, $now]);
    return ['ok' => true, 'time' => $now];
}

function teacherSelfCheckOut($pdo, $teacherId, $remarks = null) {
    ensureErpSchema($pdo);
    $today = date('Y-m-d');
    $now = date('H:i:s');
    $existing = getTeacherAttendanceRecord($pdo, $teacherId, $today);
    if (!$existing || empty($existing['check_in_time'])) {
        return ['ok' => false, 'error' => 'Please check in first before checking out.'];
    }
    if (!empty($existing['check_out_time'])) {
        return ['ok' => false, 'error' => 'You have already checked out today.'];
    }
    $note = trim((string) $remarks);
    $remarksFinal = $existing['remarks'] ?? '';
    if ($note !== '') {
        $remarksFinal = trim($remarksFinal . ($remarksFinal ? ' · ' : '') . 'Check-out: ' . $note);
    }
    $pdo->prepare(
        "UPDATE teacher_attendance SET check_out_time = ?, remarks = ? WHERE teacher_id = ? AND attendance_date = ?"
    )->execute([$now, $remarksFinal ?: null, (int) $teacherId, $today]);
    return ['ok' => true, 'time' => $now];
}

function parseTeacherAttTimeToSeconds($time) {
    if ($time === null || trim((string) $time) === '') {
        return null;
    }
    $time = trim((string) $time);
    if (preg_match('/(?:\d{4}-\d{2}-\d{2}\s+)?(\d{1,2}):(\d{2})(?::(\d{2}))?/', $time, $m)) {
        return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) ($m[3] ?? 0);
    }
    return null;
}

function formatTeacherAttTime($time) {
    $secs = parseTeacherAttTimeToSeconds($time);
    if ($secs === null) {
        return '—';
    }
    $h = (int) floor($secs / 3600) % 24;
    $min = (int) floor(($secs % 3600) / 60);
    $ampm = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12;
    if ($h12 === 0) {
        $h12 = 12;
    }
    return sprintf('%d:%02d %s', $h12, $min, $ampm);
}

function teacherAttendanceWorkDuration($checkIn, $checkOut) {
    $inSecs = parseTeacherAttTimeToSeconds($checkIn);
    $outSecs = parseTeacherAttTimeToSeconds($checkOut);
    if ($inSecs === null || $outSecs === null || $outSecs < $inSecs) {
        return null;
    }
    $secs = $outSecs - $inSecs;
    $h = (int) floor($secs / 3600);
    $m = (int) floor(($secs % 3600) / 60);
    if ($h > 0) {
        return $h . 'h ' . $m . 'm';
    }
    return $m . 'm';
}

if (!function_exists('displayVal')) {
    function displayVal($val, $default = '-') {
        if ($val === null || trim((string) $val) === '') {
            return $default;
        }
        return htmlspecialchars($val);
    }
}
