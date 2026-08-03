<?php
// admin/includes/hostel_fee_helpers.php — separate hostel fee structure & payments

function ensureHostelFeeSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `hostel_fee_structures` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `class_name` varchar(50) NOT NULL,
        `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `month` tinyint NOT NULL DEFAULT 1,
        `session_id` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `class_hostel_month` (`class_name`,`session_id`,`month`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `hostel_fee_payments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
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
}

function generateHostelReceiptNo(PDO $pdo): string {
    $prefix = 'HRCP' . date('Ym');
    $stmt = $pdo->prepare(
        "SELECT receipt_no FROM hostel_fee_payments WHERE receipt_no LIKE ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = ($last && strlen($last) > strlen($prefix)) ? (int) substr($last, strlen($prefix)) + 1 : 1;
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function getHostelFeeAmountMap(PDO $pdo, string $className, $sessionId = null): array {
    ensureHostelFeeSchema($pdo);
    $sessionId = $sessionId ?: (getCurrentSession($pdo)['id'] ?? null);
    $stmt = $pdo->prepare(
        "SELECT month, amount FROM hostel_fee_structures
         WHERE class_name = ? AND (session_id = ? OR session_id IS NULL)
         ORDER BY month"
    );
    $stmt->execute([$className, $sessionId]);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int) $row['month']] = (float) $row['amount'];
    }
    return $map;
}

function saveHostelFeeStructure(PDO $pdo, string $className, array $monthAmounts, $sessionId = null): void {
    ensureHostelFeeSchema($pdo);
    $sessionId = $sessionId ?: (getCurrentSession($pdo)['id'] ?? null);
    $stmt = $pdo->prepare(
        "INSERT INTO hostel_fee_structures (class_name, amount, month, session_id) VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE amount = VALUES(amount)"
    );
    foreach ($monthAmounts as $month => $amt) {
        $month = (int) $month;
        if ($month < 1 || $month > 12) {
            continue;
        }
        $stmt->execute([$className, (float) $amt, $month, $sessionId]);
    }
}

function getHostelClassFeeSummaries(PDO $pdo, $sessionId = null): array {
    ensureHostelFeeSchema($pdo);
    $sessionId = $sessionId ?: (getCurrentSession($pdo)['id'] ?? null);
    $stmt = $pdo->prepare(
        "SELECT class_name, SUM(amount) AS total, COUNT(*) AS cnt
         FROM hostel_fee_structures
         WHERE (session_id = ? OR session_id IS NULL) AND amount > 0
         GROUP BY class_name"
    );
    $stmt->execute([$sessionId]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $out[$row['class_name']] = [
            'total' => (float) $row['total'],
            'cnt' => (int) $row['cnt'],
        ];
    }
    return $out;
}

function getStudentHostelMonthlyPaymentsMap(PDO $pdo, int $studentId): array {
    ensureHostelFeeSchema($pdo);
    $stmt = $pdo->prepare(
        "SELECT fee_month, amount, remarks FROM hostel_fee_payments WHERE student_id = ?"
    );
    $stmt->execute([$studentId]);
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

function getStudentHostelFeeSummary(PDO $pdo, int $studentId): ?array {
    ensureHostelFeeSchema($pdo);
    $student = $pdo->prepare('SELECT * FROM students WHERE id = ?');
    $student->execute([$studentId]);
    $student = $student->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return null;
    }

    $hasHostel = studentHasActiveHostel($pdo, $studentId);
    $hostelInfo = $hasHostel ? getStudentHostelDetails($pdo, $studentId) : null;
    $session = getCurrentSession($pdo);
    $amountMap = getHostelFeeAmountMap($pdo, (string) $student['class'], $session['id'] ?? null);

    $monthlyBreakdown = [];
    $totalDue = 0.0;
    foreach (getFeeMonthOrder() as $month) {
        $amt = $hasHostel ? (float) ($amountMap[$month] ?? 0) : 0.0;
        $totalDue += $amt;
        $monthlyBreakdown[] = [
            'month' => $month,
            'label' => getFeeMonthLabels()[$month] ?? (string) $month,
            'total' => $amt,
        ];
    }

    $paidByMonth = getStudentHostelMonthlyPaymentsMap($pdo, $studentId);
    $totalPaid = array_sum($paidByMonth);
    $balance = max(0, $totalDue - $totalPaid);

    $payStmt = $pdo->prepare(
        "SELECT * FROM hostel_fee_payments WHERE student_id = ? ORDER BY payment_date DESC, id DESC"
    );
    $payStmt->execute([$studentId]);
    $payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$hasHostel) {
        $feeStatus = 'not_allotted';
    } elseif ($totalDue <= 0) {
        $feeStatus = 'no_structure';
    } elseif ($balance <= 0) {
        $feeStatus = 'cleared';
    } else {
        $feeStatus = 'pending';
    }

    return [
        'student' => $student,
        'has_hostel' => $hasHostel,
        'hostel' => $hostelInfo,
        'total_due' => $totalDue,
        'total_paid' => $totalPaid,
        'balance' => $balance,
        'fee_status' => $feeStatus,
        'monthly_breakdown' => $monthlyBreakdown,
        'amount_map' => $amountMap,
        'payments' => $payments,
    ];
}

function getStudentHostelMonthlyFeeStatuses(PDO $pdo, int $studentId): array {
    $summary = getStudentHostelFeeSummary($pdo, $studentId);
    if (!$summary) {
        return [];
    }
    $paidByMonth = getStudentHostelMonthlyPaymentsMap($pdo, $studentId);
    $statuses = [];
    foreach ($summary['monthly_breakdown'] as $mb) {
        $month = (int) $mb['month'];
        $due = (float) ($mb['total'] ?? 0);
        $paid = (float) ($paidByMonth[$month] ?? 0);
        $statuses[] = [
            'month' => $month,
            'label' => $mb['label'],
            'due' => $due,
            'paid' => $paid,
            'balance' => feeMonthPaymentBalance($due, $paid),
            'status' => feeMonthPaymentStatus($due, $paid),
        ];
    }
    return $statuses;
}

function searchHostelStudents(PDO $pdo, string $type, string $q, int $limit = 40): array {
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $sql = "SELECT s.* FROM students s
            INNER JOIN hostel_allotments ha ON ha.student_id = s.id AND ha.status = 'Active'
            WHERE s.status = 'Active' AND ";
    if ($type === 'name') {
        $sql .= 's.name LIKE ?';
        $param = '%' . $q . '%';
    } elseif ($type === 'roll') {
        $sql .= 's.roll = ?';
        $param = $q;
    } else {
        $sql .= 's.ad_no LIKE ?';
        $param = '%' . $q . '%';
    }
    $sql .= ' ORDER BY s.name ASC LIMIT ' . (int) $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$param]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getHostelStudentsByClass(PDO $pdo, string $class, string $section = ''): array {
    if ($section !== '') {
        $stmt = $pdo->prepare(
            "SELECT s.* FROM students s
             INNER JOIN hostel_allotments ha ON ha.student_id = s.id AND ha.status = 'Active'
             WHERE s.class = ? AND s.section = ? AND s.status = 'Active'
             ORDER BY s.roll ASC, s.name ASC"
        );
        $stmt->execute([$class, $section]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT s.* FROM students s
             INNER JOIN hostel_allotments ha ON ha.student_id = s.id AND ha.status = 'Active'
             WHERE s.class = ? AND s.status = 'Active'
             ORDER BY s.section ASC, s.roll ASC, s.name ASC"
        );
        $stmt->execute([$class]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
