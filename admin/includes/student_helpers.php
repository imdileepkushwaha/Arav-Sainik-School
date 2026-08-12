<?php
// admin/includes/student_helpers.php

require_once __DIR__ . '/class_helpers.php';

function ensureStudentSchema($pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $columns = [
        'email'             => "VARCHAR(100) DEFAULT NULL",
        'photo'             => "VARCHAR(255) DEFAULT NULL",
        'section'           => "VARCHAR(10) DEFAULT 'A'",
        'suspend_reason'    => "TEXT DEFAULT NULL",
        'suspended_at'      => "DATETIME DEFAULT NULL",
        'current_address'   => "TEXT DEFAULT NULL",
        'permanent_address' => "TEXT DEFAULT NULL",
        'current_address_line' => "VARCHAR(255) DEFAULT NULL",
        'current_city'         => "VARCHAR(100) DEFAULT NULL",
        'current_state'        => "VARCHAR(100) DEFAULT NULL",
        'current_country'      => "VARCHAR(100) DEFAULT NULL",
        'current_pincode'      => "VARCHAR(20) DEFAULT NULL",
        'permanent_address_line' => "VARCHAR(255) DEFAULT NULL",
        'permanent_city'         => "VARCHAR(100) DEFAULT NULL",
        'permanent_state'        => "VARCHAR(100) DEFAULT NULL",
        'permanent_country'      => "VARCHAR(100) DEFAULT NULL",
        'permanent_pincode'      => "VARCHAR(20) DEFAULT NULL",
        'previous_school'   => "VARCHAR(150) DEFAULT NULL",
        'previous_class'    => "VARCHAR(50) DEFAULT NULL",
        'previous_year'     => "VARCHAR(30) DEFAULT NULL",
        'previous_tc_no'    => "VARCHAR(50) DEFAULT NULL",
        'bank_name'         => "VARCHAR(100) DEFAULT NULL",
        'bank_branch'       => "VARCHAR(100) DEFAULT NULL",
        'ifsc_code'         => "VARCHAR(30) DEFAULT NULL",
        'blood_group'       => "VARCHAR(10) DEFAULT NULL",
        'height'            => "VARCHAR(20) DEFAULT NULL",
        'weight'            => "VARCHAR(20) DEFAULT NULL",
        'hostel_name'       => "VARCHAR(100) DEFAULT NULL",
        'room_no'           => "VARCHAR(20) DEFAULT NULL",
        'room_type'         => "VARCHAR(50) DEFAULT NULL",
        'description'       => "TEXT DEFAULT NULL",
        'aadhar'            => "VARCHAR(255) DEFAULT NULL",
        'created_at'        => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
    ];

    foreach ($columns as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE `students` ADD COLUMN `$col` $def");
        } catch (PDOException $e) {
            // Column already exists
        }
    }

    try {
        $pdo->exec("ALTER TABLE `students` MODIFY COLUMN `ad_no` varchar(40) NOT NULL");
    } catch (PDOException $e) {
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `student_categories` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(50) NOT NULL,
        `description` varchar(255) DEFAULT NULL,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `student_guardians` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `name` varchar(100) NOT NULL,
        `relation` enum('Father','Mother','Guardian') NOT NULL,
        `phone` varchar(20) DEFAULT NULL,
        `email` varchar(100) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `student_id` (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $count = (int) $pdo->query("SELECT COUNT(*) FROM student_categories")->fetchColumn();
    if ($count === 0) {
        $defaults = ['General', 'OBC', 'SC', 'ST', 'Special'];
        $stmt = $pdo->prepare("INSERT INTO student_categories (name) VALUES (?)");
        foreach ($defaults as $name) {
            $stmt->execute([$name]);
        }
    }

    ensureClassSchema($pdo);
}

function admissionClassCode($className) {
    $className = trim((string) $className);
    if ($className === '') {
        return '';
    }
    if (preg_match('/(?:^|[^\d])(?:class\s*)?(\d{1,2})(?:\D|$)/i', $className, $m)
        || preg_match('/^(\d{1,2})$/', $className, $m)) {
        return (string) (int) $m[1];
    }
    $slug = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $className));
    return $slug !== '' ? substr($slug, 0, 6) : 'GEN';
}

/** Serial code = class number + section letter, e.g. Class 1 + A → 1A */
function serialClassSectionCode($className, $section = 'A') {
    $classCode = admissionClassCode($className);
    if ($classCode === '') {
        return '';
    }
    $section = strtoupper(trim((string) $section));
    if ($section === '') {
        $section = 'A';
    }
    $section = preg_replace('/[^A-Z0-9]/', '', $section) ?: 'A';
    return $classCode . $section;
}

function generateAdmissionNo($pdo, $className = '', $section = 'A') {
    $code = serialClassSectionCode($className, $section);
    if ($code === '') {
        return '';
    }
    $year = date('Y');
    $prefix = 'SN' . $year . '-' . $code . '-';
    $seq = 1;
    try {
        $stmt = $pdo->prepare("SELECT ad_no FROM students WHERE ad_no LIKE ?");
        $stmt->execute([$prefix . '%']);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $ad) {
            if (preg_match('/-(\d{1,6})$/', (string) $ad, $m)) {
                $seq = max($seq, (int) $m[1] + 1);
            }
        }
    } catch (PDOException $e) {
        $seq = 1;
    }
    return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

function parseRollNumeric($roll) {
    $roll = trim((string) $roll);
    if ($roll === '') {
        return 0;
    }
    if (ctype_digit($roll)) {
        return (int) $roll;
    }
    if (preg_match('/^(\d+)/', $roll, $m)) {
        return (int) $m[1];
    }
    return 0;
}

/** Next roll for a class + section (e.g. 01, 02, 03). */
function getNextRollNumber($pdo, $class, $section = 'A') {
    if ($class === '') {
        return '';
    }
    $section = $section ?: 'A';
    $stmt = $pdo->prepare("SELECT roll FROM students WHERE class = ? AND section = ?");
    $stmt->execute([$class, $section]);
    $max = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $roll) {
        $max = max($max, parseRollNumeric($roll));
    }
    return str_pad($max + 1, 2, '0', STR_PAD_LEFT);
}

/** Roll is unique per class + section. */
function isRollNumberTaken($pdo, $roll, $class, $section, $excludeId = null) {
    $roll = trim((string) $roll);
    if ($roll === '' || $class === '') {
        return false;
    }
    $section = $section ?: 'A';
    $sql = "SELECT id FROM students WHERE class = ? AND section = ? AND roll = ?";
    $params = [$class, $section, $roll];
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = (int) $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetch();
}

function validateStudentRoll($pdo, $roll, $class, $section, $excludeId = null) {
    $errors = [];
    $roll = trim((string) $roll);
    if ($roll === '') {
        $errors[] = 'Roll number is required.';
        return $errors;
    }
    if ($class !== '' && isRollNumberTaken($pdo, $roll, $class, $section, $excludeId)) {
        $errors[] = 'Roll number "' . $roll . '" is already used in this class and section.';
    }
    return $errors;
}

function handleRollApiRequest($pdo) {
    if (!isset($_GET['action'])) {
        return false;
    }
    $action = $_GET['action'];
    if ($action === 'next_roll') {
        header('Content-Type: application/json');
        $class = trim($_GET['class'] ?? '');
        $section = trim($_GET['section'] ?? 'A') ?: 'A';
        echo json_encode(['roll' => $class ? getNextRollNumber($pdo, $class, $section) : '']);
        exit;
    }
    if ($action === 'next_ad_no') {
        header('Content-Type: application/json');
        $class = trim($_GET['class'] ?? '');
        $section = trim($_GET['section'] ?? 'A') ?: 'A';
        echo json_encode(['ad_no' => $class !== '' ? generateAdmissionNo($pdo, $class, $section) : '']);
        exit;
    }
    if ($action === 'check_roll') {
        header('Content-Type: application/json');
        $roll = trim($_GET['roll'] ?? '');
        $class = trim($_GET['class'] ?? '');
        $section = trim($_GET['section'] ?? 'A') ?: 'A';
        $excludeId = (int) ($_GET['exclude_id'] ?? 0);
        $taken = $class !== '' && $roll !== '' && isRollNumberTaken($pdo, $roll, $class, $section, $excludeId ?: null);
        echo json_encode(['taken' => $taken]);
        exit;
    }
    return false;
}

function getCategoryOptions($pdo) {
    ensureStudentSchema($pdo);
    $rows = $pdo->query("SELECT name FROM student_categories WHERE status = 'Active' ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
    return $rows ?: ['General', 'OBC', 'SC', 'ST', 'Special'];
}

function getStudentPhotoUrl($student) {
    if (!empty($student['photo']) && file_exists(__DIR__ . '/../' . $student['photo'])) {
        return $student['photo'];
    }
    $name = urlencode($student['name'] ?? 'Student');
    return 'https://ui-avatars.com/api/?name=' . $name . '&background=059669&color=fff&bold=true';
}

function uploadStudentPhoto($file, $ad_no) {
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
    $dir = __DIR__ . '/../uploads/students/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $ad_no) . '_' . time() . '.' . strtolower($ext);
    $path = $dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return 'uploads/students/' . $filename;
    }
    return false;
}

function getStudentAadharUrl($student) {
    if (!empty($student['aadhar']) && file_exists(__DIR__ . '/../' . $student['aadhar'])) {
        return $student['aadhar'];
    }
    return '';
}

function uploadStudentAadhar($file, $ad_no) {
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed, true)) {
        return false;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return false;
    }
    $dir = __DIR__ . '/../uploads/students/aadhar/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: ($mime === 'application/pdf' ? 'pdf' : 'jpg');
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $ad_no) . '_aadhar_' . time() . '.' . strtolower($ext);
    $path = $dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return 'uploads/students/aadhar/' . $filename;
    }
    return false;
}

function saveStudentGuardians($pdo, $student_id, $guardians) {
    $pdo->prepare("DELETE FROM student_guardians WHERE student_id = ?")->execute([$student_id]);
    $stmt = $pdo->prepare("INSERT INTO student_guardians (student_id, name, relation, phone, email) VALUES (?, ?, ?, ?, ?)");
    foreach ($guardians as $g) {
        if (trim($g['name'] ?? '') === '') continue;
        $stmt->execute([
            $student_id,
            trim($g['name']),
            $g['relation'],
            trim($g['phone'] ?? ''),
            trim($g['email'] ?? ''),
        ]);
    }
}

function getStudentGuardians($pdo, $student_id) {
    $stmt = $pdo->prepare("SELECT * FROM student_guardians WHERE student_id = ? ORDER BY FIELD(relation, 'Father', 'Mother', 'Guardian')");
    $stmt->execute([$student_id]);
    return $stmt->fetchAll();
}

function parseDobForInput($dob) {
    if (empty($dob)) return '';
    $ts = strtotime($dob);
    if ($ts) return date('Y-m-d', $ts);
    return $dob;
}

function formatDobDisplay($dob) {
    if (empty($dob)) return '-';
    $ts = strtotime($dob);
    if ($ts) return date('d M Y', $ts);
    return $dob;
}

function getDefaultStudentFormData() {
    return [
        'name'              => '',
        'roll'              => '',
        'class'             => '',
        'section'           => 'A',
        'dob'               => '',
        'gender'            => 'Male',
        'mobile'            => '',
        'email'             => '',
        'category'          => 'General',
        'status'            => 'Active',
        'current_address'   => '',
        'permanent_address' => '',
        'current_address_line' => '',
        'current_city'         => '',
        'current_state'        => '',
        'current_country'      => 'India',
        'current_pincode'      => '',
        'permanent_address_line' => '',
        'permanent_city'         => '',
        'permanent_state'        => '',
        'permanent_country'      => 'India',
        'permanent_pincode'      => '',
        'previous_school'   => '',
        'previous_class'    => '',
        'previous_year'     => '',
        'previous_tc_no'    => '',
        'bank_name'         => '',
        'bank_branch'       => '',
        'ifsc_code'         => '',
        'blood_group'       => '',
        'height'            => '',
        'weight'            => '',
        'hostel_name'       => '',
        'room_no'           => '',
        'room_type'         => '',
        'description'       => '',
        'father_name'       => '',
        'father_phone'      => '',
        'father_email'      => '',
        'mother_name'       => '',
        'mother_phone'      => '',
        'mother_email'      => '',
        'guardian_name'     => '',
        'guardian_phone'    => '',
        'guardian_email'    => '',
    ];
}

function formatStudentAddressParts(array $parts) {
    $bits = [
        trim((string) ($parts['line'] ?? '')),
        trim((string) ($parts['city'] ?? '')),
        trim((string) ($parts['state'] ?? '')),
        trim((string) ($parts['country'] ?? '')),
        trim((string) ($parts['pincode'] ?? '')),
    ];
    $bits = array_values(array_filter($bits, static fn($v) => $v !== ''));
    return implode(', ', $bits);
}

function getIndianStates() {
    return [
        'Andhra Pradesh',
        'Arunachal Pradesh',
        'Assam',
        'Bihar',
        'Chhattisgarh',
        'Goa',
        'Gujarat',
        'Haryana',
        'Himachal Pradesh',
        'Jharkhand',
        'Karnataka',
        'Kerala',
        'Madhya Pradesh',
        'Maharashtra',
        'Manipur',
        'Meghalaya',
        'Mizoram',
        'Nagaland',
        'Odisha',
        'Punjab',
        'Rajasthan',
        'Sikkim',
        'Tamil Nadu',
        'Telangana',
        'Tripura',
        'Uttar Pradesh',
        'Uttarakhand',
        'West Bengal',
        'Andaman and Nicobar Islands',
        'Chandigarh',
        'Dadra and Nagar Haveli and Daman and Diu',
        'Delhi',
        'Jammu and Kashmir',
        'Ladakh',
        'Lakshadweep',
        'Puducherry',
    ];
}

function applyStudentAddressFromPost(array &$form_data, array $post = []) {
    // Single address form — keep permanent columns in sync for legacy reads
    $form_data['permanent_address_line'] = $form_data['current_address_line'];
    $form_data['permanent_city'] = $form_data['current_city'];
    $form_data['permanent_state'] = $form_data['current_state'];
    $form_data['permanent_country'] = $form_data['current_country'];
    $form_data['permanent_pincode'] = $form_data['current_pincode'];

    $form_data['current_address'] = formatStudentAddressParts([
        'line' => $form_data['current_address_line'],
        'city' => $form_data['current_city'],
        'state' => $form_data['current_state'],
        'country' => $form_data['current_country'],
        'pincode' => $form_data['current_pincode'],
    ]);
    $form_data['permanent_address'] = $form_data['current_address'];
}

function hydrateStudentAddressFields(array &$data, array $student = []) {
    $hasStructuredCurrent = trim(($student['current_address_line'] ?? '') . ($student['current_city'] ?? '') . ($student['current_state'] ?? '') . ($student['current_country'] ?? '') . ($student['current_pincode'] ?? '')) !== '';
    if (!$hasStructuredCurrent && !empty($student['current_address'])) {
        $data['current_address_line'] = $student['current_address'];
    }

    $hasStructuredPermanent = trim(($student['permanent_address_line'] ?? '') . ($student['permanent_city'] ?? '') . ($student['permanent_state'] ?? '') . ($student['permanent_country'] ?? '') . ($student['permanent_pincode'] ?? '')) !== '';
    if (!$hasStructuredPermanent && !empty($student['permanent_address'])) {
        $data['permanent_address_line'] = $student['permanent_address'];
    }

    if ($data['current_country'] === '') {
        $data['current_country'] = 'India';
    }
    if ($data['permanent_country'] === '') {
        $data['permanent_country'] = 'India';
    }
}

function studentFromRow($student, $guardians = []) {
    $data = getDefaultStudentFormData();
    foreach ($data as $key => $val) {
        if (isset($student[$key])) {
            $data[$key] = $student[$key];
        }
    }
    if (!empty($student['name'])) $data['name'] = $student['name'];
    if (!empty($student['dob'])) $data['dob'] = parseDobForInput($student['dob']);
    foreach ($guardians as $g) {
        $rel = strtolower($g['relation']);
        if ($rel === 'father') {
            $data['father_name'] = $g['name'];
            $data['father_phone'] = $g['phone'];
            $data['father_email'] = $g['email'];
        } elseif ($rel === 'mother') {
            $data['mother_name'] = $g['name'];
            $data['mother_phone'] = $g['phone'];
            $data['mother_email'] = $g['email'];
        } else {
            $data['guardian_name'] = $g['name'];
            $data['guardian_phone'] = $g['phone'];
            $data['guardian_email'] = $g['email'];
        }
    }
    hydrateStudentAddressFields($data, $student);
    return $data;
}

function guardiansFromForm($post) {
    $contact = trim($post['mobile'] ?? '');
    return [
        ['name' => $post['father_name'] ?? '', 'relation' => 'Father', 'phone' => $contact, 'email' => ''],
        ['name' => $post['mother_name'] ?? '', 'relation' => 'Mother', 'phone' => $contact, 'email' => ''],
    ];
}

if (!function_exists('displayVal')) {
    function displayVal($val, $default = '-') {
        if ($val === null || trim((string) $val) === '') {
            return $default;
        }
        return htmlspecialchars($val);
    }
}

function guardianRoleClass($relation) {
    $map = ['Father' => 'role-father', 'Mother' => 'role-mother', 'Guardian' => 'role-guardian'];
    return $map[$relation] ?? 'role-guardian';
}

function guardianRoleIcon($relation) {
    $map = ['Father' => 'fa-male', 'Mother' => 'fa-female', 'Guardian' => 'fa-user-shield'];
    return $map[$relation] ?? 'fa-user';
}
