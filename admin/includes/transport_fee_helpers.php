<?php
// admin/includes/transport_fee_helpers.php — separate transport fee structure & payments

function ensureTransportFeeSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `transport_fee_structures` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `class_name` varchar(50) NOT NULL,
        `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `month` tinyint NOT NULL DEFAULT 1,
        `session_id` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `class_transport_month` (`class_name`,`session_id`,`month`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `transport_fee_payments` (
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

function generateTransportReceiptNo(PDO $pdo): string {
    $prefix = 'TRCP' . date('Ym');
    $stmt = $pdo->prepare(
        "SELECT receipt_no FROM transport_fee_payments WHERE receipt_no LIKE ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = ($last && strlen($last) > strlen($prefix)) ? (int) substr($last, strlen($prefix)) + 1 : 1;
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function getTransportFeeAmountMap(PDO $pdo, string $className, $sessionId = null): array {
    ensureTransportFeeSchema($pdo);
    $sessionId = $sessionId ?: (getCurrentSession($pdo)['id'] ?? null);
    $stmt = $pdo->prepare(
        "SELECT month, amount FROM transport_fee_structures
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

function saveTransportFeeStructure(PDO $pdo, string $className, array $monthAmounts, $sessionId = null): void {
    ensureTransportFeeSchema($pdo);
    $sessionId = $sessionId ?: (getCurrentSession($pdo)['id'] ?? null);
    $stmt = $pdo->prepare(
        "INSERT INTO transport_fee_structures (class_name, amount, month, session_id) VALUES (?,?,?,?)
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

function getTransportClassFeeSummaries(PDO $pdo, $sessionId = null): array {
    ensureTransportFeeSchema($pdo);
    $sessionId = $sessionId ?: (getCurrentSession($pdo)['id'] ?? null);
    $stmt = $pdo->prepare(
        "SELECT class_name, SUM(amount) AS total, COUNT(*) AS cnt
         FROM transport_fee_structures
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

function getStudentTransportMonthlyPaymentsMap(PDO $pdo, int $studentId): array {
    ensureTransportFeeSchema($pdo);
    $stmt = $pdo->prepare(
        "SELECT fee_month, amount, remarks FROM transport_fee_payments WHERE student_id = ?"
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

function getStudentTransportFeeSummary(PDO $pdo, int $studentId): ?array {
    ensureTransportFeeSchema($pdo);
    $student = $pdo->prepare('SELECT * FROM students WHERE id = ?');
    $student->execute([$studentId]);
    $student = $student->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return null;
    }

    $hasTransport = studentHasTransportAssignment($pdo, $studentId);
    $transportInfo = $hasTransport ? getStudentTransportDetails($pdo, $studentId) : null;
    $session = getCurrentSession($pdo);
    $amountMap = getTransportFeeAmountMap($pdo, (string) $student['class'], $session['id'] ?? null);

    // Prefer route fare for monthly due when set; else class structure
    $routeFare = $transportInfo ? (float) ($transportInfo['fare'] ?? 0) : 0.0;

    $monthlyBreakdown = [];
    $totalDue = 0.0;
    foreach (getFeeMonthOrder() as $month) {
        if (!$hasTransport) {
            $amt = 0.0;
        } elseif ($routeFare > 0) {
            $amt = $routeFare;
        } else {
            $amt = (float) ($amountMap[$month] ?? 0);
        }
        $totalDue += $amt;
        $monthlyBreakdown[] = [
            'month' => $month,
            'label' => getFeeMonthLabels()[$month] ?? (string) $month,
            'total' => $amt,
        ];
    }

    $paidByMonth = getStudentTransportMonthlyPaymentsMap($pdo, $studentId);
    $totalPaid = array_sum($paidByMonth);
    $balance = max(0, $totalDue - $totalPaid);

    $payStmt = $pdo->prepare(
        "SELECT * FROM transport_fee_payments WHERE student_id = ? ORDER BY payment_date DESC, id DESC"
    );
    $payStmt->execute([$studentId]);
    $payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$hasTransport) {
        $feeStatus = 'not_assigned';
    } elseif ($totalDue <= 0) {
        $feeStatus = 'no_structure';
    } elseif ($balance <= 0) {
        $feeStatus = 'cleared';
    } else {
        $feeStatus = 'pending';
    }

    return [
        'student' => $student,
        'has_transport' => $hasTransport,
        'transport' => $transportInfo,
        'total_due' => $totalDue,
        'total_paid' => $totalPaid,
        'balance' => $balance,
        'fee_status' => $feeStatus,
        'monthly_breakdown' => $monthlyBreakdown,
        'amount_map' => $amountMap,
        'route_fare' => $routeFare,
        'payments' => $payments,
    ];
}

function getStudentTransportMonthlyFeeStatuses(PDO $pdo, int $studentId): array {
    $summary = getStudentTransportFeeSummary($pdo, $studentId);
    if (!$summary) {
        return [];
    }
    $paidByMonth = getStudentTransportMonthlyPaymentsMap($pdo, $studentId);
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

function searchTransportStudents(PDO $pdo, string $type, string $q, int $limit = 40): array {
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $sql = "SELECT s.* FROM students s
            INNER JOIN student_transport st ON st.student_id = s.id
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

function getTransportStudentsByClass(PDO $pdo, string $class, string $section = ''): array {
    if ($section !== '') {
        $stmt = $pdo->prepare(
            "SELECT s.* FROM students s
             INNER JOIN student_transport st ON st.student_id = s.id
             WHERE s.class = ? AND s.section = ? AND s.status = 'Active'
             ORDER BY s.roll ASC, s.name ASC"
        );
        $stmt->execute([$class, $section]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT s.* FROM students s
             INNER JOIN student_transport st ON st.student_id = s.id
             WHERE s.class = ? AND s.status = 'Active'
             ORDER BY s.section ASC, s.roll ASC, s.name ASC"
        );
        $stmt->execute([$class]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
