<?php
// admin/includes/erp_helpers.php — School ERP modules schema & helpers

require_once __DIR__ . '/student_helpers.php';
require_once __DIR__ . '/teacher_helpers.php';
require_once __DIR__ . '/hostel_fee_helpers.php';
require_once __DIR__ . '/transport_fee_helpers.php';
require_once __DIR__ . '/topper_helpers.php';

function ensureErpSchema($pdo) {
    static $ensuring = false;
    static $done = false;
    if ($done || $ensuring) {
        return;
    }
    $ensuring = true;

    ensureStudentSchema($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS `academic_sessions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(50) NOT NULL,
        `start_date` date DEFAULT NULL,
        `end_date` date DEFAULT NULL,
        `is_current` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `attendance_records` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `attendance_date` date NOT NULL,
        `status` enum('Present','Absent','Late','Half Day') NOT NULL DEFAULT 'Present',
        `class_name` varchar(50) DEFAULT NULL,
        `section_name` varchar(10) DEFAULT NULL,
        `session_id` int(11) DEFAULT NULL,
        `remarks` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `student_date` (`student_id`,`attendance_date`),
        KEY `attendance_date` (`attendance_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `fee_heads` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `description` varchar(255) DEFAULT NULL,
        `is_optional` tinyint(1) NOT NULL DEFAULT 0,
        `is_one_time` tinyint(1) NOT NULL DEFAULT 0,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        $pdo->exec("ALTER TABLE `fee_heads` ADD COLUMN `is_optional` tinyint(1) NOT NULL DEFAULT 0 AFTER `description`");
    } catch (PDOException $e) {
    }
    try {
        $pdo->exec("ALTER TABLE `fee_heads` ADD COLUMN `is_one_time` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_optional`");
    } catch (PDOException $e) {
    }
    migrateLegacyFeeHeadFlags($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS `fee_structures` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `class_name` varchar(50) NOT NULL,
        `fee_head_id` int(11) NOT NULL,
        `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `month` tinyint NOT NULL DEFAULT 1,
        `session_id` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `class_fee_month` (`class_name`,`fee_head_id`,`session_id`,`month`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        $pdo->exec("ALTER TABLE `fee_structures` ADD COLUMN `month` tinyint NOT NULL DEFAULT 1 AFTER `amount`");
    } catch (PDOException $e) {
    }
    try {
        $pdo->exec("ALTER TABLE `fee_structures` DROP INDEX `class_fee`");
    } catch (PDOException $e) {
    }
    try {
        $pdo->exec("ALTER TABLE `fee_structures` ADD UNIQUE KEY `class_fee_month` (`class_name`,`fee_head_id`,`session_id`,`month`)");
    } catch (PDOException $e) {
    }
    migrateLegacyFeeStructuresToMonthly($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS `fee_payments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `fee_head_id` int(11) DEFAULT NULL,
        `amount` decimal(10,2) NOT NULL,
        `payment_date` date NOT NULL,
        `fee_month` tinyint(2) unsigned DEFAULT NULL,
        `payment_method` varchar(30) DEFAULT 'Cash',
        `receipt_no` varchar(30) NOT NULL,
        `session_id` int(11) DEFAULT NULL,
        `remarks` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `receipt_no` (`receipt_no`),
        KEY `student_id` (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ensureFeePaymentsFeeMonthColumn($pdo);
    migrateFeeMonthFromPaymentRemarks($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS `exams` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `exam_type` varchar(50) DEFAULT 'Term',
        `class_name` varchar(50) NOT NULL,
        `session_id` int(11) DEFAULT NULL,
        `start_date` date DEFAULT NULL,
        `end_date` date DEFAULT NULL,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `exam_subjects` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `exam_id` int(11) NOT NULL,
        `subject_name` varchar(100) NOT NULL,
        `max_marks` int(11) NOT NULL DEFAULT 100,
        PRIMARY KEY (`id`),
        KEY `exam_id` (`exam_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `student_marks` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `exam_subject_id` int(11) NOT NULL,
        `marks_obtained` decimal(5,2) DEFAULT NULL,
        `grade` varchar(5) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `student_subject` (`student_id`,`exam_subject_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `certificates` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `cert_type` enum('TC','Bonafide','Character') NOT NULL,
        `certificate_no` varchar(30) NOT NULL,
        `issue_date` date NOT NULL,
        `purpose` varchar(255) DEFAULT NULL,
        `extra_data` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `certificate_no` (`certificate_no`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `promotion_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `from_class` varchar(50) NOT NULL,
        `from_section` varchar(10) DEFAULT NULL,
        `to_class` varchar(50) NOT NULL,
        `to_section` varchar(10) DEFAULT NULL,
        `session_id` int(11) DEFAULT NULL,
        `promoted_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `student_documents` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `doc_type` varchar(50) NOT NULL,
        `file_path` varchar(255) NOT NULL,
        `original_name` varchar(255) DEFAULT NULL,
        `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `student_id` (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Transport module
    $pdo->exec("CREATE TABLE IF NOT EXISTS `transport_vehicles` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `vehicle_no` varchar(30) NOT NULL,
        `model` varchar(100) DEFAULT NULL,
        `capacity` int(11) DEFAULT 40,
        `driver_name` varchar(100) DEFAULT NULL,
        `driver_phone` varchar(20) DEFAULT NULL,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `transport_routes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `vehicle_id` int(11) DEFAULT NULL,
        `fare` decimal(10,2) DEFAULT 0.00,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `transport_stops` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `route_id` int(11) NOT NULL,
        `stop_name` varchar(100) NOT NULL,
        `pickup_time` time DEFAULT NULL,
        `sort_order` int(11) DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `route_id` (`route_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `student_transport` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `route_id` int(11) NOT NULL,
        `stop_id` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `student_id` (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `hostels` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `address` varchar(255) DEFAULT NULL,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `hostel_rooms` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `hostel_id` int(11) NOT NULL,
        `room_no` varchar(20) NOT NULL,
        `room_type` varchar(50) DEFAULT 'Standard',
        `capacity` int(11) NOT NULL DEFAULT 2,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        PRIMARY KEY (`id`),
        KEY `hostel_id` (`hostel_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `hostel_allotments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `room_id` int(11) NOT NULL,
        `allotted_from` date DEFAULT NULL,
        `allotted_to` date DEFAULT NULL,
        `status` enum('Active','Vacated') NOT NULL DEFAULT 'Active',
        PRIMARY KEY (`id`),
        UNIQUE KEY `student_id` (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `homework` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `class_name` varchar(50) NOT NULL,
        `section_name` varchar(10) DEFAULT 'A',
        `title` varchar(150) NOT NULL,
        `description` text DEFAULT NULL,
        `due_date` date DEFAULT NULL,
        `session_id` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `notification_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `channel` enum('SMS','WhatsApp','Email') NOT NULL,
        `recipient` varchar(100) NOT NULL,
        `message` text NOT NULL,
        `template_type` varchar(50) DEFAULT NULL,
        `student_id` int(11) DEFAULT NULL,
        `status` enum('Sent','Failed','Queued') NOT NULL DEFAULT 'Queued',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $portalCols = [
        'portal_enabled'  => "TINYINT(1) NOT NULL DEFAULT 0",
        'portal_password' => "VARCHAR(255) DEFAULT NULL",
    ];
    foreach ($portalCols as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE `students` ADD COLUMN `$col` $def");
        } catch (PDOException $e) {
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `notices` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(200) NOT NULL,
        `body` text NOT NULL,
        `audience` enum('All','Students','Teachers','Staff') NOT NULL DEFAULT 'All',
        `priority` enum('Normal','Important','Urgent') NOT NULL DEFAULT 'Normal',
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        `publish_date` date NOT NULL,
        `created_by` varchar(50) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `subjects` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `code` varchar(20) DEFAULT NULL,
        `class_name` varchar(50) DEFAULT NULL,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        PRIMARY KEY (`id`),
        UNIQUE KEY `name_class` (`name`,`class_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `teacher_attendance` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `teacher_id` int(11) NOT NULL,
        `attendance_date` date NOT NULL,
        `status` enum('Present','Absent','Late','Half Day','Leave') NOT NULL DEFAULT 'Present',
        `remarks` varchar(255) DEFAULT NULL,
        `check_in_time` time DEFAULT NULL,
        `check_out_time` time DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `teacher_date` (`teacher_id`,`attendance_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $teacherAttCols = [
        'check_in_time'  => 'TIME DEFAULT NULL',
        'check_out_time' => 'TIME DEFAULT NULL',
    ];
    foreach ($teacherAttCols as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE `teacher_attendance` ADD COLUMN `$col` $def");
        } catch (PDOException $e) {
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `leave_requests` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `person_type` enum('Teacher','Student') NOT NULL DEFAULT 'Teacher',
        `person_id` int(11) NOT NULL,
        `from_date` date NOT NULL,
        `to_date` date NOT NULL,
        `reason` varchar(255) DEFAULT NULL,
        `status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
        `added_by` enum('Teacher','Admin') NOT NULL DEFAULT 'Teacher',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        $pdo->exec("ALTER TABLE `leave_requests` MODIFY COLUMN `status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending'");
    } catch (PDOException $e) {
    }

    try {
        $pdo->exec("ALTER TABLE `leave_requests` ADD COLUMN `added_by` enum('Teacher','Admin') NOT NULL DEFAULT 'Teacher' AFTER `status`");
    } catch (PDOException $e) {
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `admission_enquiries` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_name` varchar(100) NOT NULL,
        `parent_name` varchar(100) DEFAULT NULL,
        `mobile` varchar(20) NOT NULL,
        `email` varchar(100) DEFAULT NULL,
        `class_sought` varchar(50) DEFAULT NULL,
        `message` text DEFAULT NULL,
        `status` enum('New','Contacted','Converted','Closed') NOT NULL DEFAULT 'New',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    ensureHostelFeeSchema($pdo);
    ensureTransportFeeSchema($pdo);
    ensureTopperSchema($pdo);
    erpSeedDefaults($pdo);

    $done = true;
    $ensuring = false;
}

function erpSeedDefaults($pdo) {
    $sessionCount = (int) $pdo->query("SELECT COUNT(*) FROM academic_sessions")->fetchColumn();
    if ($sessionCount === 0) {
        $y = (int) date('Y');
        $name = $y . '-' . substr((string) ($y + 1), 2);
        $pdo->prepare("INSERT INTO academic_sessions (name, start_date, end_date, is_current) VALUES (?,?,?,1)")
            ->execute([$name, $y . '-04-01', ($y + 1) . '-03-31']);
    }

    $feeCount = (int) $pdo->query("SELECT COUNT(*) FROM fee_heads")->fetchColumn();
    if ($feeCount === 0) {
        $defaults = [
            ['Tuition Fee', 0, 0],
            ['Admission Fee', 0, 1],
            ['Exam Fee', 0, 0],
        ];
        $stmt = $pdo->prepare("INSERT INTO fee_heads (name, is_optional, is_one_time) VALUES (?,?,?)");
        foreach ($defaults as $row) {
            $stmt->execute($row);
        }
    }

    $hostelCount = (int) $pdo->query("SELECT COUNT(*) FROM hostels")->fetchColumn();
    if ($hostelCount === 0) {
        $pdo->exec("INSERT INTO hostels (name, address) VALUES ('Boys Hostel', 'Campus Block A'), ('Girls Hostel', 'Campus Block B')");
    }

    $subjectCount = (int) $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    if ($subjectCount === 0) {
        $defaults = ['English', 'Hindi', 'Mathematics', 'Science', 'Social Studies', 'Computer'];
        $stmt = $pdo->prepare("INSERT INTO subjects (name, class_name) VALUES (?, NULL)");
        foreach ($defaults as $s) {
            try {
                $stmt->execute([$s]);
            } catch (PDOException $e) {
            }
        }
    }
}

function getCurrentSession($pdo) {
    ensureErpSchema($pdo);
    $row = $pdo->query("SELECT * FROM academic_sessions WHERE is_current = 1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }
    return $pdo->query("SELECT * FROM academic_sessions ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

function getAllSessions($pdo) {
    ensureErpSchema($pdo);
    return $pdo->query("SELECT * FROM academic_sessions ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
}

function calculateGrade($obtained, $max) {
    if ($max <= 0 || $obtained === null || $obtained === '') {
        return '-';
    }
    $pct = ($obtained / $max) * 100;
    if ($pct >= 90) return 'A+';
    if ($pct >= 80) return 'A';
    if ($pct >= 70) return 'B+';
    if ($pct >= 60) return 'B';
    if ($pct >= 50) return 'C';
    if ($pct >= 33) return 'D';
    return 'F';
}

function ensureFeePaymentsFeeMonthColumn($pdo) {
    if (feePaymentsHasFeeMonthColumn($pdo)) {
        return true;
    }
    try {
        $pdo->exec("ALTER TABLE `fee_payments` ADD COLUMN `fee_month` tinyint(2) unsigned DEFAULT NULL AFTER `payment_date`");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE `fee_payments` ADD COLUMN `fee_month` tinyint(2) unsigned DEFAULT NULL");
        } catch (PDOException $e2) {
            return false;
        }
    }
    return feePaymentsHasFeeMonthColumn($pdo);
}

function feePaymentsHasFeeMonthColumn($pdo) {
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM `fee_payments`')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            if (strcasecmp((string) ($col['Field'] ?? ''), 'fee_month') === 0) {
                return true;
            }
        }
    } catch (PDOException $e) {
    }
    return false;
}

function feeMonthTagForRemarks(int $feeMonth): string {
    return '[fee_month:' . $feeMonth . ']';
}

function feeMonthFromRemarks($remarks): int {
    $remarks = (string) $remarks;
    if ($remarks === '') {
        return 0;
    }
    if (preg_match('/\[fee_month:(\d{1,2})\]/i', $remarks, $match)) {
        $month = (int) $match[1];
        if ($month >= 1 && $month <= 12) {
            return $month;
        }
    }
    return 0;
}

function appendFeeMonthToRemarks(int $feeMonth, string $remarks = ''): string {
    $remarks = trim(preg_replace('/\[fee_month:\d{1,2}\]\s*/i', '', (string) $remarks));
    $tag = feeMonthTagForRemarks($feeMonth);
    return $remarks === '' ? $tag : $tag . ' ' . $remarks;
}

function formatPaymentRemarksForDisplay($remarks): string {
    $remarks = trim((string) $remarks);
    if ($remarks === '') {
        return '';
    }
    $remarks = preg_replace('/\[fee_month:\d{1,2}\]\s*/i', '', $remarks);
    $remarks = preg_replace('/\[installment:\d{1,2}\]\s*/i', '', $remarks);
    $remarks = preg_replace('/\[discount:[0-9.]+\]\s*/i', '', $remarks);
    return trim($remarks);
}

function migrateFeeMonthBackfillCleanup($pdo) {
    if (!function_exists('getSetting')) {
        return;
    }
    if (getSetting($pdo, 'fee_month_backfill_cleanup_v1', '') === '1') {
        return;
    }
    ensureFeePaymentsFeeMonthColumn($pdo);
    try {
        $pdo->exec(
            "UPDATE fee_payments
             SET fee_month = NULL
             WHERE fee_month IS NOT NULL
               AND fee_month = MONTH(payment_date)
               AND (remarks IS NULL OR remarks NOT LIKE '%[fee_month:%')"
        );
        setSetting($pdo, 'fee_month_backfill_cleanup_v1', '1');
    } catch (PDOException $e) {
    }
}

function migrateFeeMonthFromPaymentRemarks($pdo) {
    ensureFeePaymentsFeeMonthColumn($pdo);
    try {
        $stmt = $pdo->query(
            "SELECT id, fee_month, remarks FROM fee_payments WHERE remarks LIKE '%[fee_month:%'"
        );
        $update = $pdo->prepare('UPDATE fee_payments SET fee_month = ? WHERE id = ?');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $month = feeMonthFromRemarks($row['remarks'] ?? '');
            if ($month >= 1 && $month <= 12 && (int) ($row['fee_month'] ?? 0) !== $month) {
                $update->execute([$month, (int) $row['id']]);
            }
        }
    } catch (PDOException $e) {
    }
}

function persistPaymentFeeMonth($pdo, int $paymentId, int $feeMonth): bool {
    $studentStmt = $pdo->prepare('SELECT student_id FROM fee_payments WHERE id = ? LIMIT 1');
    $studentStmt->execute([$paymentId]);
    $studentId = (int) $studentStmt->fetchColumn();
    if ($studentId <= 0) {
        return false;
    }
    return assignPaymentFeeMonth($pdo, $paymentId, $studentId, $feeMonth);
}

function assignPaymentFeeMonth($pdo, int $paymentId, int $studentId, int $feeMonth): bool {
    if ($paymentId <= 0 || $studentId <= 0 || $feeMonth < 1 || $feeMonth > 12) {
        return false;
    }

    $own = $pdo->prepare('SELECT id, remarks, fee_month FROM fee_payments WHERE id = ? AND student_id = ? LIMIT 1');
    $own->execute([$paymentId, $studentId]);
    $ownRow = $own->fetch(PDO::FETCH_ASSOC);
    if (!$ownRow) {
        return false;
    }

    $newRemarks = appendFeeMonthToRemarks($feeMonth, $ownRow['remarks'] ?? '');
    $hasColumn = ensureFeePaymentsFeeMonthColumn($pdo);

    try {
        if ($hasColumn) {
            $stmt = $pdo->prepare('UPDATE fee_payments SET fee_month = ?, remarks = ? WHERE id = ? AND student_id = ?');
            $stmt->bindValue(1, $feeMonth, PDO::PARAM_INT);
            $stmt->bindValue(2, $newRemarks);
            $stmt->bindValue(3, $paymentId, PDO::PARAM_INT);
            $stmt->bindValue(4, $studentId, PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare('UPDATE fee_payments SET remarks = ? WHERE id = ? AND student_id = ?');
            $stmt->bindValue(1, $newRemarks);
            $stmt->bindValue(2, $paymentId, PDO::PARAM_INT);
            $stmt->bindValue(3, $studentId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $verify = $pdo->prepare('SELECT fee_month, remarks FROM fee_payments WHERE id = ? AND student_id = ? LIMIT 1');
        $verify->execute([$paymentId, $studentId]);
        $savedRow = $verify->fetch(PDO::FETCH_ASSOC) ?: [];
        $savedMonth = (int) ($savedRow['fee_month'] ?? 0);
        $remarksMonth = feeMonthFromRemarks($savedRow['remarks'] ?? '');

        if ($hasColumn && $savedMonth === $feeMonth) {
            return true;
        }
        return $remarksMonth === $feeMonth;
    } catch (PDOException $e) {
        return false;
    }
}

function generateReceiptNo($pdo) {
    $prefix = 'RCP' . date('Ym');
    $stmt = $pdo->prepare("SELECT receipt_no FROM fee_payments WHERE receipt_no LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = ($last && strlen($last) > strlen($prefix)) ? (int) substr($last, strlen($prefix)) + 1 : 1;
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function getStudentMonthlyPaymentsMap($pdo, $studentId) {
    $stmt = $pdo->prepare(
        "SELECT fee_month, amount, remarks
         FROM fee_payments
         WHERE student_id = ?"
    );
    $stmt->execute([(int) $studentId]);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $month = (int) ($row['fee_month'] ?? 0);
        if ($month < 1 || $month > 12) {
            $month = feeMonthFromRemarks($row['remarks'] ?? '');
        }
        if ($month >= 1 && $month <= 12) {
            $map[$month] = ($map[$month] ?? 0.0) + (float) ($row['amount'] ?? 0);
        }
    }
    return $map;
}

function paymentRecordFeeMonth(array $payment): int {
    foreach (['fee_month', 'payment_fee_month'] as $key) {
        if (!array_key_exists($key, $payment) || $payment[$key] === null || $payment[$key] === '') {
            continue;
        }
        $month = (int) $payment[$key];
        if ($month >= 1 && $month <= 12) {
            return $month;
        }
    }
    return feeMonthFromRemarks($payment['remarks'] ?? '');
}

function feeMonthPaymentBalance($due, $paid) {
    $due = (float) $due;
    $paid = (float) $paid;
    if ($due <= 0) {
        return 0.0;
    }
    if ($paid >= ($due - 0.009)) {
        return 0.0;
    }
    return max(0, $due - $paid);
}

function feeMonthPaymentStatus($due, $paid) {
    $due = (float) $due;
    $paid = (float) $paid;
    if ($due <= 0) {
        return 'none';
    }
    if ($paid >= ($due - 0.009)) {
        return 'paid';
    }
    if ($paid > 0.009) {
        return 'partial';
    }
    return 'pending';
}

function getStudentMonthlyFeeStatuses($pdo, $studentId) {
    $summary = getStudentFeeSummary($pdo, (int) $studentId);
    if (!$summary) {
        return [];
    }

    $paidByMonth = getStudentMonthlyPaymentsMap($pdo, (int) $studentId);

    $monthlyBreakdown = $summary['monthly_breakdown'] ?? [];
    $dueByMonth = [];
    foreach ($monthlyBreakdown as $mb) {
        $dueByMonth[(int) $mb['month']] = (float) ($mb['total'] ?? 0);
    }

    $statuses = [];
    foreach (getFeeMonthOrder() as $month) {
        $due = (float) ($dueByMonth[$month] ?? 0);
        $paid = (float) ($paidByMonth[$month] ?? 0);
        $balance = feeMonthPaymentBalance($due, $paid);
        $status = feeMonthPaymentStatus($due, $paid);
        $statuses[] = [
            'month' => $month,
            'label' => getFeeMonthLabels()[$month] ?? (string) $month,
            'due' => $due,
            'paid' => $paid,
            'balance' => $balance,
            'is_cleared' => $status === 'paid',
            'is_partial' => $status === 'partial',
            'status' => $status,
        ];
    }
    return $statuses;
}

function getStudentMonthFeeBreakdown($pdo, $studentId, $feeMonth) {
    $feeMonth = (int) $feeMonth;
    if ($feeMonth < 1 || $feeMonth > 12) {
        return null;
    }

    $summary = getStudentFeeSummary($pdo, (int) $studentId);
    if (!$summary) {
        return null;
    }

    $headLines = [];
    foreach ($summary['fee_items'] as $item) {
        $due = (float) ($item['months'][$feeMonth] ?? 0);
        if ($due <= 0) {
            continue;
        }
        $headLines[] = [
            'fee_head_id' => (int) $item['fee_head_id'],
            'head_name' => $item['head_name'],
            'due' => $due,
            'is_optional' => !empty($item['is_optional']),
            'is_one_time' => !empty($item['is_one_time']),
        ];
    }

    $paidByMonth = getStudentMonthlyPaymentsMap($pdo, (int) $studentId);
    $monthPaid = (float) ($paidByMonth[$feeMonth] ?? 0);
    $monthDue = array_sum(array_column($headLines, 'due'));
    $monthBalance = feeMonthPaymentBalance($monthDue, $monthPaid);

    return [
        'month' => $feeMonth,
        'label' => getFeeMonthLabels()[$feeMonth] ?? (string) $feeMonth,
        'head_lines' => $headLines,
        'month_due' => $monthDue,
        'month_paid' => $monthPaid,
        'month_balance' => $monthBalance,
        'is_cleared' => feeMonthPaymentStatus($monthDue, $monthPaid) === 'paid',
        'status' => feeMonthPaymentStatus($monthDue, $monthPaid),
        'session' => getCurrentSession($pdo),
    ];
}

function generateCertificateNo($pdo, $type) {
    $prefix = strtoupper(substr($type, 0, 2)) . date('Y');
    $stmt = $pdo->prepare("SELECT certificate_no FROM certificates WHERE certificate_no LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = ($last && strlen($last) > strlen($prefix)) ? (int) substr($last, strlen($prefix)) + 1 : 1;
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function getStudentsByClassSection($pdo, $class, $section = '') {
    if ($section !== '') {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE class = ? AND section = ? AND status = 'Active' ORDER BY roll ASC, name ASC");
        $stmt->execute([$class, $section]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE class = ? AND status = 'Active' ORDER BY section ASC, roll ASC, name ASC");
        $stmt->execute([$class]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function saveAttendance($pdo, $date, $class, $section, $statuses, $sessionId = null) {
    $stmt = $pdo->prepare(
        "INSERT INTO attendance_records (student_id, attendance_date, status, class_name, section_name, session_id)
         VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), class_name = VALUES(class_name), section_name = VALUES(section_name)"
    );
    foreach ($statuses as $studentId => $status) {
        $stmt->execute([(int) $studentId, $date, $status, $class, $section, $sessionId]);
    }
}

function getAttendanceMonthlyReport($pdo, $class, $section, $year, $month) {
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-t', strtotime($start));
    $students = getStudentsByClassSection($pdo, $class, $section);
    $stmt = $pdo->prepare(
        "SELECT student_id, attendance_date, status FROM attendance_records
         WHERE class_name = ? AND section_name = ? AND attendance_date BETWEEN ? AND ?"
    );
    $stmt->execute([$class, $section, $start, $end]);
    $records = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $records[$r['student_id']][$r['attendance_date']] = $r['status'];
    }
    return ['students' => $students, 'records' => $records, 'start' => $start, 'end' => $end];
}

function getStudentAttendanceSummary($pdo, $studentId, $year, $month) {
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-t', strtotime($start));
    $stmt = $pdo->prepare(
        "SELECT status, COUNT(*) AS cnt FROM attendance_records
         WHERE student_id = ? AND attendance_date BETWEEN ? AND ? GROUP BY status"
    );
    $stmt->execute([(int) $studentId, $start, $end]);
    $summary = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Half Day' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $summary[$row['status']] = (int) $row['cnt'];
    }
    $stmt = $pdo->prepare(
        "SELECT * FROM attendance_records WHERE student_id = ? AND attendance_date BETWEEN ? AND ? ORDER BY attendance_date DESC"
    );
    $stmt->execute([(int) $studentId, $start, $end]);
    return ['summary' => $summary, 'records' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function getFeeHeads($pdo) {
    return $pdo->query(
        "SELECT * FROM fee_heads
         WHERE status = 'Active' AND name NOT LIKE '%hostel%' AND name NOT LIKE '%transport%'
         ORDER BY name"
    )->fetchAll(PDO::FETCH_ASSOC);
}

function getFeeHeadById($pdo, $headId) {
    $stmt = $pdo->prepare("SELECT * FROM fee_heads WHERE id = ? AND status = 'Active'");
    $stmt->execute([(int) $headId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function addFeeHead($pdo, $name, $isOptional = false, $isOneTime = false) {
    $name = trim($name);
    if (stripos($name, 'hostel') !== false) {
        throw new InvalidArgumentException('Hostel fees are managed under the Hostel menu.');
    }
    if (stripos($name, 'transport') !== false) {
        throw new InvalidArgumentException('Transport fees are managed under the Transport menu.');
    }
    $stmt = $pdo->prepare("INSERT INTO fee_heads (name, is_optional, is_one_time) VALUES (?,?,?)");
    $stmt->execute([$name, $isOptional ? 1 : 0, $isOneTime ? 1 : 0]);
    return (int) $pdo->lastInsertId();
}

function updateFeeHead($pdo, $headId, $name, $isOptional = false, $isOneTime = false) {
    $name = trim($name);
    if (stripos($name, 'hostel') !== false) {
        throw new InvalidArgumentException('Hostel fees are managed under the Hostel menu.');
    }
    if (stripos($name, 'transport') !== false) {
        throw new InvalidArgumentException('Transport fees are managed under the Transport menu.');
    }
    $stmt = $pdo->prepare(
        "UPDATE fee_heads SET name = ?, is_optional = ?, is_one_time = ? WHERE id = ? AND status = 'Active'"
    );
    $stmt->execute([$name, $isOptional ? 1 : 0, $isOneTime ? 1 : 0, (int) $headId]);
    return $stmt->rowCount() > 0;
}

function deleteFeeHead($pdo, $headId) {
    $headId = (int) $headId;
    if ($headId <= 0) {
        return ['ok' => false, 'message' => 'Invalid fee head.'];
    }
    $head = getFeeHeadById($pdo, $headId);
    if (!$head) {
        return ['ok' => false, 'message' => 'Fee head not found.'];
    }
    $payStmt = $pdo->prepare("SELECT COUNT(*) FROM fee_payments WHERE fee_head_id = ?");
    $payStmt->execute([$headId]);
    $paymentCount = (int) $payStmt->fetchColumn();
    if ($paymentCount > 0) {
        $pdo->prepare("UPDATE fee_heads SET status = 'Inactive' WHERE id = ?")->execute([$headId]);
        return [
            'ok' => true,
            'soft' => true,
            'message' => 'Fee head deactivated (used in ' . $paymentCount . ' payment(s)). History preserved.',
        ];
    }
    $pdo->prepare("DELETE FROM fee_structures WHERE fee_head_id = ?")->execute([$headId]);
    $pdo->prepare("DELETE FROM fee_heads WHERE id = ?")->execute([$headId]);
    return ['ok' => true, 'soft' => false, 'message' => 'Fee head deleted.'];
}

function migrateLegacyFeeHeadFlags($pdo) {
    $pdo->exec(
        "UPDATE fee_heads SET is_one_time = 1
         WHERE is_one_time = 0 AND name LIKE '%admission%'"
    );
    // Hostel & transport fees moved out of main fee structure
    try {
        $pdo->exec(
            "UPDATE fee_heads SET status = 'Inactive'
             WHERE status = 'Active' AND (name LIKE '%hostel%' OR name LIKE '%transport%')"
        );
    } catch (PDOException $e) {
    }
}

function getFeeMonthLabels() {
    return [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];
}

/** Academic session order: April through March */
function getFeeMonthOrder() {
    return [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
}

function migrateLegacyFeeStructuresToMonthly($pdo) {
    $rows = $pdo->query(
        "SELECT fs.* FROM fee_structures fs
         WHERE (SELECT COUNT(*) FROM fee_structures x
                WHERE x.class_name = fs.class_name AND x.fee_head_id = fs.fee_head_id
                AND (x.session_id <=> fs.session_id)) = 1"
    )->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return;
    }
    $insert = $pdo->prepare(
        "INSERT INTO fee_structures (class_name, fee_head_id, amount, month, session_id) VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE amount = VALUES(amount)"
    );
    foreach ($rows as $row) {
        $month = (int) ($row['month'] ?? 1);
        for ($m = 1; $m <= 12; $m++) {
            if ($m === $month) {
                continue;
            }
            $insert->execute([
                $row['class_name'],
                (int) $row['fee_head_id'],
                (float) $row['amount'],
                $m,
                $row['session_id'],
            ]);
        }
    }
}

function getClassFeeStructure($pdo, $className, $sessionId = null) {
    $sessionId = $sessionId ?: (getCurrentSession($pdo)['id'] ?? null);
    $stmt = $pdo->prepare(
        "SELECT fs.*, fh.name AS head_name, fh.is_optional, fh.is_one_time FROM fee_structures fs
         INNER JOIN fee_heads fh ON fh.id = fs.fee_head_id
         WHERE fs.class_name = ? AND (fs.session_id = ? OR fs.session_id IS NULL)
           AND fh.status = 'Active' AND fh.name NOT LIKE '%hostel%' AND fh.name NOT LIKE '%transport%'
         ORDER BY fh.name, fs.month"
    );
    $stmt->execute([$className, $sessionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getClassFeeAmountMap($pdo, $className, $sessionId = null) {
    $structure = getClassFeeStructure($pdo, $className, $sessionId);
    $map = [];
    foreach ($structure as $row) {
        $headId = (int) $row['fee_head_id'];
        $month = (int) ($row['month'] ?? 1);
        if (!isset($map[$headId])) {
            $map[$headId] = [];
        }
        $map[$headId][$month] = (float) $row['amount'];
    }
    return $map;
}

function saveClassFeeStructure($pdo, $className, array $amounts, $sessionId = null) {
    $sessionId = $sessionId ?: (getCurrentSession($pdo)['id'] ?? null);
    $stmt = $pdo->prepare(
        "INSERT INTO fee_structures (class_name, fee_head_id, amount, month, session_id) VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE amount = VALUES(amount)"
    );
    foreach ($amounts as $headId => $monthAmounts) {
        if (!is_array($monthAmounts)) {
            continue;
        }
        $headId = (int) $headId;
        if ($headId <= 0) {
            continue;
        }
        foreach ($monthAmounts as $month => $amt) {
            $month = (int) $month;
            if ($month < 1 || $month > 12) {
                continue;
            }
            $stmt->execute([$className, $headId, (float) $amt, $month, $sessionId]);
        }
    }
}

function aggregateFeeStructureByHead(array $structure) {
    $byHead = [];
    foreach ($structure as $row) {
        $headId = (int) $row['fee_head_id'];
        if (!isset($byHead[$headId])) {
            $byHead[$headId] = [
                'fee_head_id' => $headId,
                'head_name' => $row['head_name'] ?? '',
                'is_optional' => !empty($row['is_optional']),
                'is_one_time' => !empty($row['is_one_time']),
                'amount' => 0.0,
                'months' => [],
            ];
        }
        $month = (int) ($row['month'] ?? 1);
        $amt = (float) $row['amount'];
        $byHead[$headId]['months'][$month] = $amt;
    }
    foreach ($byHead as &$head) {
        if (!empty($head['is_one_time'])) {
            $head['amount'] = $head['months'] ? max($head['months']) : 0.0;
        } else {
            $head['amount'] = array_sum($head['months']);
        }
    }
    unset($head);
    return array_values($byHead);
}

function buildMonthlyFeeBreakdown(array $structure) {
    $labels = getFeeMonthLabels();
    $monthTotals = [];
    foreach (getFeeMonthOrder() as $month) {
        $monthTotals[$month] = 0.0;
    }
    foreach ($structure as $row) {
        $month = (int) ($row['month'] ?? 1);
        if (isset($monthTotals[$month])) {
            $monthTotals[$month] += (float) $row['amount'];
        }
    }
    $breakdown = [];
    foreach (getFeeMonthOrder() as $month) {
        $breakdown[] = [
            'month' => $month,
            'label' => $labels[$month],
            'total' => $monthTotals[$month],
        ];
    }
    return $breakdown;
}

function isHostelFeeHeadName($headName) {
    return stripos($headName, 'hostel') !== false;
}

function isTransportFeeHeadName($headName) {
    return stripos($headName, 'transport') !== false;
}

function studentHasActiveHostel($pdo, $studentId) {
    ensureErpSchema($pdo);
    $stmt = $pdo->prepare("SELECT 1 FROM hostel_allotments WHERE student_id = ? AND status = 'Active' LIMIT 1");
    $stmt->execute([(int) $studentId]);
    return (bool) $stmt->fetchColumn();
}

function studentHasTransportAssignment($pdo, $studentId) {
    ensureErpSchema($pdo);
    $stmt = $pdo->prepare("SELECT 1 FROM student_transport WHERE student_id = ? LIMIT 1");
    $stmt->execute([(int) $studentId]);
    return (bool) $stmt->fetchColumn();
}

function feeStructureRowAppliesToStudent($pdo, $studentId, array $row) {
    if (empty($row['is_optional'])) {
        return true;
    }
    // Optional heads (legacy) are not auto-applied; hostel fees use the Hostel module
    return false;
}

function filterFeeStructureForStudent($pdo, $studentId, array $structure) {
    return array_values(array_filter($structure, function ($row) use ($pdo, $studentId) {
        return feeStructureRowAppliesToStudent($pdo, $studentId, $row);
    }));
}

function feeHeadAppliesToStudent($pdo, $studentId, $headIdOrName) {
    if (is_numeric($headIdOrName)) {
        $head = getFeeHeadById($pdo, (int) $headIdOrName);
        if (!$head) {
            return false;
        }
        if (isHostelFeeHeadName($head['name'] ?? '') || isTransportFeeHeadName($head['name'] ?? '')) {
            return false;
        }
        return feeStructureRowAppliesToStudent($pdo, $studentId, [
            'head_name' => $head['name'],
            'is_optional' => $head['is_optional'],
        ]);
    }
    $headName = (string) $headIdOrName;
    if (isHostelFeeHeadName($headName) || isTransportFeeHeadName($headName)) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT * FROM fee_heads WHERE name = ? AND status = 'Active' LIMIT 1");
    $stmt->execute([$headName]);
    $head = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($head) {
        return feeStructureRowAppliesToStudent($pdo, $studentId, [
            'head_name' => $head['name'],
            'is_optional' => $head['is_optional'],
        ]);
    }
    return true;
}

function getStudentHostelDetails($pdo, $studentId) {
    ensureErpSchema($pdo);
    $stmt = $pdo->prepare(
        "SELECT ha.*, hr.room_no, hr.room_type, hr.capacity,
                h.name AS hostel_name, h.address AS hostel_address,
                (SELECT COUNT(*) FROM hostel_allotments x WHERE x.room_id = ha.room_id AND x.status = 'Active') AS occupied
         FROM hostel_allotments ha
         INNER JOIN hostel_rooms hr ON hr.id = ha.room_id
         INNER JOIN hostels h ON h.id = hr.hostel_id
         WHERE ha.student_id = ? AND ha.status = 'Active'
         LIMIT 1"
    );
    $stmt->execute([(int) $studentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getStudentTransportDetails($pdo, $studentId) {
    ensureErpSchema($pdo);
    $stmt = $pdo->prepare(
        "SELECT st.*, r.name AS route_name, r.fare,
                v.vehicle_no, v.model AS vehicle_model, v.driver_name, v.driver_phone,
                ts.stop_name, ts.pickup_time
         FROM student_transport st
         INNER JOIN transport_routes r ON r.id = st.route_id
         LEFT JOIN transport_vehicles v ON v.id = r.vehicle_id
         LEFT JOIN transport_stops ts ON ts.id = st.stop_id
         WHERE st.student_id = ?
         LIMIT 1"
    );
    $stmt->execute([(int) $studentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getStudentFeeSummary($pdo, $studentId) {
    $student = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $student->execute([(int) $studentId]);
    $student = $student->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return null;
    }
    $session = getCurrentSession($pdo);
    $structure = getClassFeeStructure($pdo, $student['class'], $session['id'] ?? null);
    $hasHostel = studentHasActiveHostel($pdo, (int) $studentId);
    $applicableStructure = filterFeeStructureForStudent($pdo, (int) $studentId, $structure);
    $feeItems = aggregateFeeStructureByHead($applicableStructure);
    $totalDue = 0.0;
    foreach ($feeItems as $item) {
        $totalDue += (float) $item['amount'];
    }
    $monthlyBreakdown = buildMonthlyFeeBreakdown($applicableStructure);
    $currentMonth = (int) date('n');
    $currentMonthDue = 0.0;
    foreach ($applicableStructure as $row) {
        if ((int) ($row['month'] ?? 0) === $currentMonth) {
            $currentMonthDue += (float) $row['amount'];
        }
    }
    $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE student_id = ?");
    $paidStmt->execute([(int) $studentId]);
    $totalPaid = (float) $paidStmt->fetchColumn();
    $payments = $pdo->prepare(
        "SELECT fp.id, fp.student_id, fp.fee_head_id, fp.amount, fp.payment_date,
                fp.fee_month, fp.payment_method, fp.receipt_no, fp.session_id, fp.remarks, fp.created_at,
                fh.name AS head_name
         FROM fee_payments fp
         LEFT JOIN fee_heads fh ON fh.id = fp.fee_head_id
         WHERE fp.student_id = ?
         ORDER BY fp.payment_date DESC, fp.id DESC"
    );
    $payments->execute([(int) $studentId]);
    $balance = max(0, $totalDue - $totalPaid);
    if ($totalDue <= 0) {
        $feeStatus = 'no_structure';
    } elseif ($balance <= 0) {
        $feeStatus = 'cleared';
    } else {
        $feeStatus = 'pending';
    }
    return [
        'student' => $student,
        'total_due' => $totalDue,
        'total_paid' => $totalPaid,
        'balance' => $balance,
        'fee_status' => $feeStatus,
        'fee_items' => $feeItems,
        'monthly_breakdown' => $monthlyBreakdown,
        'current_month' => $currentMonth,
        'current_month_due' => $currentMonthDue,
        'has_hostel' => $hasHostel,
        'payments' => $payments->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function examGradeFromPercent($pct) {
    if ($pct >= 90) return 'A1';
    if ($pct >= 80) return 'A2';
    if ($pct >= 70) return 'B1';
    if ($pct >= 60) return 'B2';
    if ($pct >= 50) return 'C1';
    if ($pct >= 40) return 'C2';
    if ($pct >= 33) return 'D';
    return 'E';
}

function getExamsForClass($pdo, $className) {
    ensureErpSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE class_name = ? AND status = 'Active' ORDER BY COALESCE(start_date, '1900-01-01') DESC, id DESC");
    $stmt->execute([$className]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStudentExamResult($pdo, $studentId, $examId) {
    $marks = getStudentMarksForExam($pdo, $studentId, $examId);
    $totalObt = 0;
    $totalMax = 0;
    $entered = 0;
    $failCount = 0;
    foreach ($marks as $m) {
        $totalMax += (int) $m['max_marks'];
        if ($m['marks_obtained'] !== null && $m['marks_obtained'] !== '') {
            $entered++;
            $obt = (float) $m['marks_obtained'];
            $totalObt += $obt;
            if ((int) $m['max_marks'] > 0 && ($obt / (int) $m['max_marks'] * 100) < 33) {
                $failCount++;
            }
        }
    }
    $pct = $totalMax ? round($totalObt / $totalMax * 100, 2) : 0;
    return [
        'marks' => $marks,
        'subject_count' => count($marks),
        'entered' => $entered,
        'published' => $entered > 0,
        'total_obtained' => $totalObt,
        'total_max' => $totalMax,
        'percentage' => $pct,
        'grade' => examGradeFromPercent($pct),
        'result' => ($entered > 0 && $failCount === 0) ? 'Pass' : ($entered > 0 ? 'Fail' : '—'),
        'fail_count' => $failCount,
    ];
}

function getStudentMarksForExam($pdo, $studentId, $examId) {
    $stmt = $pdo->prepare(
        "SELECT es.subject_name, es.max_marks, sm.marks_obtained, sm.grade
         FROM exam_subjects es
         LEFT JOIN student_marks sm ON sm.exam_subject_id = es.id AND sm.student_id = ?
         WHERE es.exam_id = ? ORDER BY es.subject_name"
    );
    $stmt->execute([(int) $studentId, (int) $examId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStudentDocuments($pdo, $studentId) {
    $stmt = $pdo->prepare("SELECT * FROM student_documents WHERE student_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([(int) $studentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function uploadStudentDocument($file, $studentId, $docType) {
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return false;
    }
    $dir = __DIR__ . '/../uploads/documents/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'pdf';
    $filename = 'doc_' . (int) $studentId . '_' . time() . '.' . strtolower($ext);
    $path = $dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $path)) {
        return ['path' => 'uploads/documents/' . $filename, 'original' => $file['name']];
    }
    return false;
}

function queueNotification($pdo, $channel, $recipient, $message, $studentId = null, $templateType = null) {
    $status = 'Queued';
    $errorNote = null;

    if (file_exists(__DIR__ . '/settings_helpers.php')) {
        require_once __DIR__ . '/settings_helpers.php';
        if ($channel === 'SMS') {
            $result = dispatchSms($pdo, $recipient, $message);
            $status = $result['ok'] ? 'Sent' : 'Failed';
            $errorNote = $result['error'] ?? null;
        } elseif ($channel === 'WhatsApp') {
            $result = dispatchWhatsApp($pdo, $recipient, $message);
            $status = $result['ok'] ? 'Sent' : 'Failed';
            $errorNote = $result['error'] ?? null;
        } elseif ($channel === 'Email') {
            $err = '';
            $ok = sendEmailViaSettings($pdo, $recipient, 'EduDash Notification', '<p>' . nl2br(htmlspecialchars($message)) . '</p>', $err);
            $status = $ok ? 'Sent' : 'Failed';
            $errorNote = $err ?: null;
        }
    }

    if ($status === 'Queued') {
        $status = 'Sent';
    }

    $stmt = $pdo->prepare(
        "INSERT INTO notification_logs (channel, recipient, message, template_type, student_id, status) VALUES (?,?,?,?,?,?)"
    );
    $logMessage = $errorNote ? $message . ' [' . $errorNote . ']' : $message;
    $stmt->execute([$channel, $recipient, $logMessage, $templateType, $studentId, $status]);
    return (int) $pdo->lastInsertId();
}

function sendFeeReminders($pdo, $className = '') {
    $sql = "SELECT id, name, mobile, class FROM students WHERE status = 'Active'";
    $params = [];
    if ($className !== '') {
        $sql .= " AND class = ?";
        $params[] = $className;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sent = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $fee = getStudentFeeSummary($pdo, $s['id']);
        if ($fee && $fee['balance'] > 0 && !empty($s['mobile'])) {
            $msg = "Dear Parent, fee balance of Rs.{$fee['balance']} is due for {$s['name']} ({$s['class']}). - EduDash";
            queueNotification($pdo, 'SMS', $s['mobile'], $msg, $s['id'], 'fee_reminder');
            queueNotification($pdo, 'WhatsApp', $s['mobile'], $msg, $s['id'], 'fee_reminder');
            $sent++;
        }
    }
    return $sent;
}

function sendAttendanceAlerts($pdo, $date, $className = '') {
    $sql = "SELECT s.id, s.name, s.mobile, ar.status FROM students s
            INNER JOIN attendance_records ar ON ar.student_id = s.id AND ar.attendance_date = ?
            WHERE ar.status IN ('Absent','Late')";
    $params = [$date];
    if ($className !== '') {
        $sql .= " AND s.class = ?";
        $params[] = $className;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sent = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (empty($row['mobile'])) {
            continue;
        }
        $msg = "Attendance Alert: {$row['name']} was marked {$row['status']} on {$date}. - EduDash";
        queueNotification($pdo, 'SMS', $row['mobile'], $msg, $row['id'], 'attendance_alert');
        queueNotification($pdo, 'WhatsApp', $row['mobile'], $msg, $row['id'], 'attendance_alert');
        $sent++;
    }
    return $sent;
}

function enableStudentPortal($pdo, $studentId, $password = null) {
    if ($password === null || $password === '') {
        $password = substr(md5(uniqid((string) $studentId, true)), 0, 8);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE students SET portal_enabled = 1, portal_password = ? WHERE id = ?")->execute([$hash, (int) $studentId]);
    return $password;
}

function authenticateStudentPortal($pdo, $adNo, $password) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE ad_no = ? AND portal_enabled = 1");
    $stmt->execute([trim($adNo)]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student && !empty($student['portal_password']) && password_verify($password, $student['portal_password'])) {
        return $student;
    }
    return null;
}

function updateStudentPortalPassword($pdo, $studentId, $currentPassword, $newPassword) {
    $stmt = $pdo->prepare("SELECT portal_password FROM students WHERE id = ? AND portal_enabled = 1");
    $stmt->execute([(int) $studentId]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($currentPassword, $hash)) {
        return 'Current password is incorrect.';
    }
    if (strlen($newPassword) < 6) {
        return 'New password must be at least 6 characters.';
    }
    $pdo->prepare("UPDATE students SET portal_password = ? WHERE id = ?")
        ->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $studentId]);
    return true;
}

function getActiveNotices($pdo, $limit = 10, $audience = 'All') {
    ensureErpSchema($pdo);
    $stmt = $pdo->prepare(
        "SELECT * FROM notices WHERE status = 'Active' AND (audience = ? OR audience = 'All')
         ORDER BY FIELD(priority,'Urgent','Important','Normal'), publish_date DESC LIMIT ?"
    );
    $stmt->bindValue(1, $audience);
    $stmt->bindValue(2, (int) $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNoticeById($pdo, $id) {
    ensureErpSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM notices WHERE id = ?");
    $stmt->execute([(int) $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function noticePriorityBadgeClass($priority) {
    switch ($priority) {
        case 'Urgent':
            return 'badge-inactive';
        case 'Important':
            return 'notify-badge-queued';
        default:
            return 'badge-active';
    }
}

function getAllSubjects($pdo, $className = null) {
    ensureErpSchema($pdo);
    if ($className) {
        $stmt = $pdo->prepare("SELECT * FROM subjects WHERE status='Active' AND (class_name IS NULL OR class_name = ?) ORDER BY name");
        $stmt->execute([$className]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $pdo->query("SELECT * FROM subjects WHERE status='Active' ORDER BY class_name, name")->fetchAll(PDO::FETCH_ASSOC);
}

function getClassTimetableFromTeachers($pdo, $className, $sectionName = 'A') {
    ensureTeacherSchema($pdo);
    $stmt = $pdo->prepare(
        "SELECT tt.*, t.name AS teacher_name, t.employee_id
         FROM teacher_timetable tt
         INNER JOIN teachers t ON t.id = tt.teacher_id
         WHERE tt.class_name = ? AND tt.section_name = ?
         ORDER BY FIELD(tt.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), tt.period_no"
    );
    $stmt->execute([$className, $sectionName]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFeeCollectionReport($pdo, $year = null, $month = null) {
    ensureErpSchema($pdo);
    $year = $year ?: (int) date('Y');
    $sql = "SELECT DATE_FORMAT(payment_date,'%Y-%m') AS ym, SUM(amount) AS total, COUNT(*) AS cnt
            FROM fee_payments WHERE YEAR(payment_date) = ?";
    $params = [$year];
    if ($month) {
        $sql .= " AND MONTH(payment_date) = ?";
        $params[] = (int) $month;
    }
    $sql .= " GROUP BY ym ORDER BY ym";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFeeCollectionMonthlyBreakdown($pdo, $year = null) {
    ensureErpSchema($pdo);
    $year = $year ?: (int) date('Y');
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $indexed = [];
    foreach (getFeeCollectionReport($pdo, $year) as $row) {
        $indexed[(int) substr($row['ym'], 5, 2)] = $row;
    }
    $months = [];
    for ($m = 1; $m <= 12; $m++) {
        $months[] = [
            'month' => $m,
            'label' => $monthNames[$m - 1],
            'ym' => sprintf('%04d-%02d', $year, $m),
            'total' => (float) ($indexed[$m]['total'] ?? 0),
            'cnt' => (int) ($indexed[$m]['cnt'] ?? 0),
        ];
    }
    return $months;
}

function getRecentFeePayments($pdo, $limit = 10, $year = null) {
    ensureErpSchema($pdo);
    $limit = max(1, min(50, (int) $limit));
    $sql = "SELECT fp.id, fp.amount, fp.payment_date, fp.payment_method, fp.receipt_no,
                   s.id AS student_id, s.name, s.ad_no, s.class,
                   fh.name AS head_name
            FROM fee_payments fp
            INNER JOIN students s ON s.id = fp.student_id
            LEFT JOIN fee_heads fh ON fh.id = fp.fee_head_id";
    $params = [];
    if ($year) {
        $sql .= " WHERE YEAR(fp.payment_date) = ?";
        $params[] = (int) $year;
    }
    $sql .= " ORDER BY fp.payment_date DESC, fp.id DESC LIMIT " . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFeeDefaulters($pdo, $classFilter = '') {
    ensureErpSchema($pdo);
    $session = getCurrentSession($pdo);
    $students = $pdo->query("SELECT id, ad_no, name, class, section, mobile FROM students WHERE status='Active' ORDER BY class, name")->fetchAll(PDO::FETCH_ASSOC);
    $defaulters = [];
    foreach ($students as $s) {
        if ($classFilter !== '' && $s['class'] !== $classFilter) {
            continue;
        }
        $summary = getStudentFeeSummary($pdo, $s['id']);
        if ($summary && $summary['balance'] > 0) {
            $defaulters[] = array_merge($s, [
                'total_due' => $summary['total_due'],
                'total_paid' => $summary['total_paid'],
                'balance' => $summary['balance'],
            ]);
        }
    }
    return $defaulters;
}

function getExamClassAnalytics($pdo, $examId) {
    ensureErpSchema($pdo);
    $exam = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
    $exam->execute([(int) $examId]);
    $exam = $exam->fetch(PDO::FETCH_ASSOC);
    if (!$exam) {
        return null;
    }
    $students = getStudentsByClassSection($pdo, $exam['class_name']);
    $subjects = $pdo->prepare("SELECT * FROM exam_subjects WHERE exam_id = ?");
    $subjects->execute([(int) $examId]);
    $subjects = $subjects->fetchAll(PDO::FETCH_ASSOC);
    $results = [];
    foreach ($students as $st) {
        $totalObt = 0;
        $totalMax = 0;
        foreach ($subjects as $sub) {
            $m = $pdo->prepare("SELECT marks_obtained FROM student_marks WHERE student_id = ? AND exam_subject_id = ?");
            $m->execute([$st['id'], $sub['id']]);
            $obt = (float) ($m->fetchColumn() ?: 0);
            $totalObt += $obt;
            $totalMax += (int) $sub['max_marks'];
        }
        if ($totalMax <= 0) {
            continue;
        }
        $pct = round($totalObt / $totalMax * 100, 1);
        $results[] = [
            'student' => $st,
            'total_obt' => $totalObt,
            'total_max' => $totalMax,
            'percentage' => $pct,
            'grade' => calculateGrade($totalObt, $totalMax),
        ];
    }
    usort($results, fn($a, $b) => $b['percentage'] <=> $a['percentage']);
    $passCount = count(array_filter($results, fn($r) => $r['percentage'] >= 33));
    return [
        'exam' => $exam,
        'results' => $results,
        'pass_count' => $passCount,
        'fail_count' => count($results) - $passCount,
        'avg_pct' => count($results) ? round(array_sum(array_column($results, 'percentage')) / count($results), 1) : 0,
    ];
}

function getDashboardStats($pdo) {
    ensureErpSchema($pdo);
    ensureTeacherSchema($pdo);
    ensureStudentSchema($pdo);
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');

    $totalStudents = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
    $totalTeachers = (int) $pdo->query("SELECT COUNT(*) FROM teachers WHERE status='Active'")->fetchColumn();

    $newStudentsMonth = 0;
    try {
        $newStudentsMonth = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE created_at >= '$monthStart'")->fetchColumn();
    } catch (PDOException $e) {
        // created_at missing on legacy DB — count by admission no year prefix e.g. AD2026
        $prefix = 'AD' . date('Y');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE ad_no LIKE ?");
        $stmt->execute([$prefix . '%']);
        $newStudentsMonth = (int) $stmt->fetchColumn();
    }

    $feeToday = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE payment_date = '$today'")->fetchColumn();
    $feeMonth = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE payment_date >= '$monthStart'")->fetchColumn();

    $attToday = $pdo->query("SELECT status, COUNT(*) AS cnt FROM attendance_records WHERE attendance_date = '$today' GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $presentToday = (int) ($attToday['Present'] ?? 0);
    $absentToday = (int) ($attToday['Absent'] ?? 0);

    $pendingLeaves = (int) $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='Pending'")->fetchColumn();
    $newEnquiries = (int) $pdo->query("SELECT COUNT(*) FROM admission_enquiries WHERE status='New'")->fetchColumn();
    $portalEnabled = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE portal_enabled=1")->fetchColumn();

    $recentStudents = $pdo->query("SELECT id, ad_no, name, class FROM students ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $recentPayments = $pdo->query(
        "SELECT fp.amount, fp.payment_date, fp.receipt_no, s.name, s.ad_no
         FROM fee_payments fp INNER JOIN students s ON s.id = fp.student_id
         ORDER BY fp.id DESC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);

    $feeChart = getFeeCollectionReport($pdo, (int) date('Y'));
    $chartMonths = array_fill(1, 12, 0);
    foreach ($feeChart as $row) {
        $m = (int) substr($row['ym'], 5, 2);
        $chartMonths[$m] = (float) $row['total'];
    }

    return compact(
        'totalStudents', 'totalTeachers', 'newStudentsMonth', 'feeToday', 'feeMonth',
        'presentToday', 'absentToday', 'pendingLeaves', 'newEnquiries', 'portalEnabled',
        'recentStudents', 'recentPayments', 'chartMonths'
    );
}

function getWebsiteEnquiries($pdo, $limit = 10) {
    ensureErpSchema($pdo);
    $stmt = $pdo->prepare(
        "SELECT * FROM admission_enquiries WHERE class_sought = 'Website Contact' ORDER BY created_at DESC LIMIT ?"
    );
    $stmt->bindValue(1, (int) $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getHostelRoomOccupancy($pdo, $roomId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM hostel_allotments WHERE room_id = ? AND status = 'Active'");
    $stmt->execute([(int) $roomId]);
    return (int) $stmt->fetchColumn();
}

function promoteStudentsAdvanced($pdo, $studentIds, $toClass, $toSection, $sessionId = null) {
    $log = $pdo->prepare(
        "INSERT INTO promotion_logs (student_id, from_class, from_section, to_class, to_section, session_id) VALUES (?,?,?,?,?,?)"
    );
    $update = $pdo->prepare("UPDATE students SET class = ?, section = ? WHERE id = ?");
    $get = $pdo->prepare("SELECT class, section FROM students WHERE id = ?");
    $count = 0;
    foreach ($studentIds as $id) {
        $id = (int) $id;
        $get->execute([$id]);
        $row = $get->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            continue;
        }
        $update->execute([$toClass, $toSection, $id]);
        $log->execute([$id, $row['class'], $row['section'] ?? 'A', $toClass, $toSection, $sessionId]);
        $count++;
    }
    return $count;
}

function findOverlappingLeaveRequest($pdo, $personType, $personId, $fromDate, $toDate, $excludeId = null) {
    ensureErpSchema($pdo);
    $sql = "SELECT * FROM leave_requests
            WHERE person_type = ? AND person_id = ?
            AND status IN ('Pending', 'Approved')
            AND from_date <= ? AND to_date >= ?";
    $params = [$personType, (int) $personId, $toDate, $fromDate];
    if ($excludeId !== null) {
        $sql .= " AND id != ?";
        $params[] = (int) $excludeId;
    }
    $sql .= " ORDER BY from_date ASC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function leaveOverlapMessage($conflict) {
    $from = date('d M Y', strtotime($conflict['from_date']));
    $to = date('d M Y', strtotime($conflict['to_date']));
    $status = $conflict['status'] ?? 'Pending';
    return "These dates overlap with an existing {$status} leave ({$from} – {$to}). Please choose different dates.";
}

function getLeaveRequestById($pdo, $id) {
    ensureErpSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE id = ?");
    $stmt->execute([(int) $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function teacherCanCancelLeave($leave, $today = null) {
    if (!$leave) {
        return ['ok' => false, 'error' => 'Leave request not found.'];
    }
    $today = $today ?? date('Y-m-d');
    $st = $leave['status'] ?? '';
    if ($st === 'Cancelled' || $st === 'Rejected') {
        return ['ok' => false, 'error' => 'This leave request is already closed.'];
    }
    if ($st === 'Pending') {
        return ['ok' => true];
    }
    if ($st === 'Approved') {
        if ($leave['from_date'] > $today) {
            return ['ok' => true];
        }
        if ($leave['from_date'] <= $today && $leave['to_date'] >= $today) {
            return ['ok' => false, 'error' => 'Leave in progress cannot be cancelled. Please contact admin.'];
        }
        return ['ok' => false, 'error' => 'Past leave cannot be cancelled.'];
    }
    return ['ok' => false, 'error' => 'This leave cannot be cancelled.'];
}

function cancelTeacherLeaveRequest($pdo, $teacherId, $leaveId) {
    ensureErpSchema($pdo);
    $leave = getLeaveRequestById($pdo, $leaveId);
    if (!$leave || $leave['person_type'] !== 'Teacher' || (int) $leave['person_id'] !== (int) $teacherId) {
        return ['ok' => false, 'error' => 'Leave request not found.'];
    }
    $check = teacherCanCancelLeave($leave);
    if (!$check['ok']) {
        return $check;
    }
    $pdo->prepare("UPDATE leave_requests SET status = 'Cancelled' WHERE id = ?")->execute([(int) $leaveId]);
    return ['ok' => true];
}

function adminCanEditLeave($leave) {
    if (!$leave) {
        return false;
    }
    if (($leave['added_by'] ?? 'Teacher') !== 'Admin') {
        return false;
    }
    return in_array($leave['status'] ?? '', ['Pending', 'Approved'], true);
}

function adminCanCancelLeave($leave) {
    if (!$leave) {
        return false;
    }
    return in_array($leave['status'] ?? '', ['Pending', 'Approved'], true);
}

function cancelAdminLeaveRequest($pdo, $leaveId) {
    ensureErpSchema($pdo);
    $leave = getLeaveRequestById($pdo, $leaveId);
    if (!adminCanCancelLeave($leave)) {
        return ['ok' => false, 'error' => 'This leave cannot be cancelled.'];
    }
    $pdo->prepare("UPDATE leave_requests SET status = 'Cancelled' WHERE id = ?")->execute([(int) $leaveId]);
    return ['ok' => true];
}

function updateAdminLeaveRequest($pdo, $leaveId, $personType, $personId, $fromDate, $toDate, $reason) {
    ensureErpSchema($pdo);
    $leave = getLeaveRequestById($pdo, $leaveId);
    if (!$leave || !adminCanEditLeave($leave)) {
        return ['ok' => false, 'error' => 'Only admin-added leave in pending or approved status can be edited.'];
    }
    if ($fromDate === '' || $toDate === '') {
        return ['ok' => false, 'error' => 'From and to dates are required.'];
    }
    if (strtotime($fromDate) > strtotime($toDate)) {
        return ['ok' => false, 'error' => 'From date cannot be after to date.'];
    }
    $conflict = findOverlappingLeaveRequest($pdo, $personType, $personId, $fromDate, $toDate, $leaveId);
    if ($conflict) {
        return ['ok' => false, 'error' => leaveOverlapMessage($conflict)];
    }
    $pdo->prepare(
        "UPDATE leave_requests SET person_type = ?, person_id = ?, from_date = ?, to_date = ?, reason = ? WHERE id = ?"
    )->execute([
        $personType,
        (int) $personId,
        $fromDate,
        $toDate,
        trim($reason) !== '' ? trim($reason) : null,
        (int) $leaveId,
    ]);
    return ['ok' => true];
}

function leaveRequestDays($fromDate, $toDate) {
    return max(1, (int) ((strtotime($toDate) - strtotime($fromDate)) / 86400) + 1);
}

function leaveStatusBadgeClass($status) {
    switch ($status) {
        case 'Approved':
            return 'badge-active';
        case 'Rejected':
            return 'badge-inactive';
        case 'Cancelled':
            return 'badge-cancelled';
        default:
            return 'notify-badge-queued';
    }
}
