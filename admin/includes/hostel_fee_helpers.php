<?php
// admin/includes/hostel_fee_helpers.php — hostel fee structure, plans & payments

function ensureHostelFeeSchema(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

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
        `installment_no` tinyint(2) unsigned DEFAULT NULL,
        `plan_id` int(11) DEFAULT NULL,
        `payment_method` varchar(30) DEFAULT 'Cash',
        `receipt_no` varchar(30) NOT NULL,
        `session_id` int(11) DEFAULT NULL,
        `remarks` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `receipt_no` (`receipt_no`),
        KEY `student_id` (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        $cols = $pdo->query('SHOW COLUMNS FROM `hostel_fee_payments`')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('installment_no', $cols, true)) {
            $pdo->exec("ALTER TABLE `hostel_fee_payments` ADD COLUMN `installment_no` tinyint(2) unsigned DEFAULT NULL AFTER `fee_month`");
        }
        if (!in_array('plan_id', $cols, true)) {
            $pdo->exec("ALTER TABLE `hostel_fee_payments` ADD COLUMN `plan_id` int(11) DEFAULT NULL AFTER `installment_no`");
        }
    } catch (PDOException $e) {
        // ignore
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `hostel_fee_plans` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `plan_code` varchar(30) NOT NULL,
        `name` varchar(120) NOT NULL,
        `plan_type` enum('monthly','installment') NOT NULL DEFAULT 'installment',
        `installment_count` tinyint NOT NULL DEFAULT 1,
        `gross_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `net_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `installment_label` varchar(120) DEFAULT NULL,
        `amounts_json` text DEFAULT NULL,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        `session_id` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `plan_code_session` (`plan_code`,`session_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `hostel_student_plans` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `plan_id` int(11) NOT NULL,
        `session_id` int(11) DEFAULT NULL,
        `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `student_session` (`student_id`,`session_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    seedDefaultHostelFeePlans($pdo);
}

function getDefaultHostelFeePlanDefinitions(): array {
    return [
        [
            'plan_code' => 'monthly',
            'name' => 'Monthly',
            'plan_type' => 'monthly',
            'installment_count' => 12,
            'gross_amount' => 54000,
            'discount_amount' => 0,
            'net_amount' => 54000,
            'installment_label' => '4500x12',
            'amounts' => [],
            'sort_order' => 1,
        ],
        [
            'plan_code' => 'inst_3',
            'name' => '3 Installments',
            'plan_type' => 'installment',
            'installment_count' => 3,
            'gross_amount' => 54000,
            'discount_amount' => 0,
            'net_amount' => 54000,
            'installment_label' => '18000x3',
            'amounts' => [18000, 18000, 18000],
            'sort_order' => 2,
        ],
        [
            'plan_code' => 'inst_2',
            'name' => '2 Installments',
            'plan_type' => 'installment',
            'installment_count' => 2,
            'gross_amount' => 54000,
            'discount_amount' => 2000,
            'net_amount' => 52000,
            'installment_label' => '27000x2',
            'amounts' => [27000, 25000],
            'sort_order' => 3,
        ],
        [
            'plan_code' => 'inst_1',
            'name' => '1 Installment (Full)',
            'plan_type' => 'installment',
            'installment_count' => 1,
            'gross_amount' => 54000,
            'discount_amount' => 4000,
            'net_amount' => 50000,
            'installment_label' => '54000x1',
            'amounts' => [50000],
            'sort_order' => 4,
        ],
    ];
}

function seedDefaultHostelFeePlans(PDO $pdo): void {
    // Avoid getCurrentSession() here — it calls ensureErpSchema and can recurse.
    $sessionId = null;
    try {
        $row = $pdo->query("SELECT id FROM academic_sessions WHERE is_current = 1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $row = $pdo->query("SELECT id FROM academic_sessions ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }
        $sessionId = $row['id'] ?? null;
    } catch (PDOException $e) {
        $sessionId = null;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM hostel_fee_plans WHERE (session_id = ? OR (? IS NULL AND session_id IS NULL))"
    );
    $stmt->execute([$sessionId, $sessionId]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $insert = $pdo->prepare(
        "INSERT INTO hostel_fee_plans
         (plan_code, name, plan_type, installment_count, gross_amount, discount_amount, net_amount, installment_label, amounts_json, sort_order, status, session_id)
         VALUES (?,?,?,?,?,?,?,?,?,?, 'Active', ?)"
    );
    foreach (getDefaultHostelFeePlanDefinitions() as $plan) {
        $insert->execute([
            $plan['plan_code'],
            $plan['name'],
            $plan['plan_type'],
            $plan['installment_count'],
            $plan['gross_amount'],
            $plan['discount_amount'],
            $plan['net_amount'],
            $plan['installment_label'],
            json_encode($plan['amounts']),
            $plan['sort_order'],
            $sessionId,
        ]);
    }
}

function normalizeHostelPlanAmounts(array $plan): array {
    $count = max(1, (int) ($plan['installment_count'] ?? 1));
    $net = (float) ($plan['net_amount'] ?? 0);
    $raw = $plan['amounts_json'] ?? ($plan['amounts'] ?? null);
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        $raw = [];
    }
    $amounts = [];
    foreach ($raw as $amt) {
        $amounts[] = round((float) $amt, 2);
    }
    if (count($amounts) === $count && array_sum($amounts) > 0) {
        return $amounts;
    }
    // Equal split of net with last installment adjusted
    $base = $count > 0 ? floor(($net / $count) * 100) / 100 : $net;
    $amounts = array_fill(0, $count, $base);
    $amounts[$count - 1] = round($net - ($base * ($count - 1)), 2);
    return $amounts;
}

function getHostelFeePlans(PDO $pdo, $sessionId = null, bool $activeOnly = true): array {
    ensureHostelFeeSchema($pdo);
    $sessionId = $sessionId ?: (getCurrentSession($pdo)['id'] ?? null);
    seedDefaultHostelFeePlans($pdo);
    $sql = "SELECT * FROM hostel_fee_plans WHERE (session_id = ? OR session_id IS NULL)";
    if ($activeOnly) {
        $sql .= " AND status = 'Active'";
    }
    $sql .= " ORDER BY sort_order ASC, id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sessionId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['amounts'] = normalizeHostelPlanAmounts($row);
    }
    unset($row);
    return $rows;
}

function getHostelFeePlanById(PDO $pdo, int $planId): ?array {
    ensureHostelFeeSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM hostel_fee_plans WHERE id = ?");
    $stmt->execute([$planId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['amounts'] = normalizeHostelPlanAmounts($row);
    return $row;
}

function saveHostelFeePlans(PDO $pdo, array $plans, $sessionId = null): void {
    ensureHostelFeeSchema($pdo);
    $sessionId = $sessionId ?: (getCurrentSession($pdo)['id'] ?? null);
    $upd = $pdo->prepare(
        "UPDATE hostel_fee_plans
         SET name = ?, installment_count = ?, gross_amount = ?, discount_amount = ?, net_amount = ?,
             installment_label = ?, amounts_json = ?, sort_order = ?
         WHERE id = ?"
    );
    foreach ($plans as $plan) {
        $id = (int) ($plan['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $gross = (float) ($plan['gross_amount'] ?? 0);
        $discount = (float) ($plan['discount_amount'] ?? 0);
        $net = isset($plan['net_amount']) ? (float) $plan['net_amount'] : max(0, $gross - $discount);
        $count = max(1, (int) ($plan['installment_count'] ?? 1));
        $amounts = $plan['amounts'] ?? [];
        if (!is_array($amounts)) {
            $amounts = [];
        }
        $amounts = array_map(static fn($a) => round((float) $a, 2), $amounts);
        while (count($amounts) < $count) {
            $amounts[] = 0.0;
        }
        $amounts = array_slice($amounts, 0, $count);
        $upd->execute([
            trim((string) ($plan['name'] ?? '')),
            $count,
            $gross,
            $discount,
            $net,
            trim((string) ($plan['installment_label'] ?? '')),
            json_encode($amounts),
            (int) ($plan['sort_order'] ?? 0),
            $id,
        ]);
    }
}

function getStudentHostelPlan(PDO $pdo, int $studentId, $sessionId = null): ?array {
    ensureHostelFeeSchema($pdo);
    $sessionId = $sessionId ?: (getCurrentSession($pdo)['id'] ?? null);
    $stmt = $pdo->prepare(
        "SELECT p.* FROM hostel_student_plans sp
         INNER JOIN hostel_fee_plans p ON p.id = sp.plan_id
         WHERE sp.student_id = ? AND (sp.session_id = ? OR (? IS NULL AND sp.session_id IS NULL))
         LIMIT 1"
    );
    $stmt->execute([$studentId, $sessionId, $sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['amounts'] = normalizeHostelPlanAmounts($row);
    return $row;
}

function assignStudentHostelPlan(PDO $pdo, int $studentId, int $planId, $sessionId = null): void {
    ensureHostelFeeSchema($pdo);
    $sessionId = $sessionId ?: (getCurrentSession($pdo)['id'] ?? null);
    $plan = getHostelFeePlanById($pdo, $planId);
    if (!$plan) {
        throw new InvalidArgumentException('Invalid hostel fee plan.');
    }
    $stmt = $pdo->prepare(
        "INSERT INTO hostel_student_plans (student_id, plan_id, session_id)
         VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE plan_id = VALUES(plan_id), assigned_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([$studentId, $planId, $sessionId]);
}

function getStudentHostelInstallmentPaymentsMap(PDO $pdo, int $studentId): array {
    ensureHostelFeeSchema($pdo);
    $stmt = $pdo->prepare(
        "SELECT installment_no, amount, remarks FROM hostel_fee_payments WHERE student_id = ?"
    );
    $stmt->execute([$studentId]);
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $no = (int) ($row['installment_no'] ?? 0);
        if ($no < 1) {
            if (preg_match('/\[installment:(\d+)\]/', (string) ($row['remarks'] ?? ''), $m)) {
                $no = (int) $m[1];
            }
        }
        if ($no >= 1) {
            $map[$no] = ($map[$no] ?? 0.0) + (float) ($row['amount'] ?? 0);
        }
    }
    return $map;
}

function appendHostelInstallmentToRemarks(int $installmentNo, string $remarks = ''): string {
    $tag = '[installment:' . $installmentNo . ']';
    $remarks = trim($remarks);
    if ($remarks === '') {
        return $tag;
    }
    if (strpos($remarks, '[installment:') !== false) {
        return $remarks;
    }
    return $remarks . ' ' . $tag;
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

function fillHostelMonthlyFeeForClass(PDO $pdo, string $className, float $monthlyAmount, $sessionId = null): void {
    $amounts = [];
    foreach (getFeeMonthOrder() as $m) {
        $amounts[$m] = $monthlyAmount;
    }
    saveHostelFeeStructure($pdo, $className, $amounts, $sessionId);
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
    $sessionId = $session['id'] ?? null;
    $amountMap = getHostelFeeAmountMap($pdo, (string) $student['class'], $sessionId);
    $plan = getStudentHostelPlan($pdo, $studentId, $sessionId);
    $planType = $plan['plan_type'] ?? 'monthly';

    $monthlyBreakdown = [];
    $structureAnnual = 0.0;
    foreach (getFeeMonthOrder() as $month) {
        $amt = $hasHostel ? (float) ($amountMap[$month] ?? 0) : 0.0;
        $structureAnnual += $amt;
        $monthlyBreakdown[] = [
            'month' => $month,
            'label' => getFeeMonthLabels()[$month] ?? (string) $month,
            'total' => $amt,
        ];
    }

    $installmentStatuses = [];
    if ($plan && $planType === 'installment') {
        $paidByInst = getStudentHostelInstallmentPaymentsMap($pdo, $studentId);
        $amounts = $plan['amounts'];
        $totalDue = (float) $plan['net_amount'];
        $totalPaid = 0.0;
        foreach ($amounts as $i => $dueAmt) {
            $no = $i + 1;
            $due = (float) $dueAmt;
            $paid = (float) ($paidByInst[$no] ?? 0);
            $totalPaid += $paid;
            $installmentStatuses[] = [
                'installment_no' => $no,
                'label' => 'Installment ' . $no,
                'due' => $due,
                'paid' => $paid,
                'balance' => feeMonthPaymentBalance($due, $paid),
                'status' => feeMonthPaymentStatus($due, $paid),
            ];
        }
        // Cap total paid display to plan net for balance clarity
        $balance = max(0, $totalDue - $totalPaid);
    } else {
        $paidByMonth = getStudentHostelMonthlyPaymentsMap($pdo, $studentId);
        $totalDue = $structureAnnual;
        $totalPaid = array_sum($paidByMonth);
        $balance = max(0, $totalDue - $totalPaid);
        if ($plan && $planType === 'monthly' && (float) $plan['net_amount'] > 0 && $structureAnnual <= 0) {
            // Fallback if monthly structure empty but plan net set
            $totalDue = (float) $plan['net_amount'];
            $balance = max(0, $totalDue - $totalPaid);
        }
    }

    $payStmt = $pdo->prepare(
        "SELECT * FROM hostel_fee_payments WHERE student_id = ? ORDER BY payment_date DESC, id DESC"
    );
    $payStmt->execute([$studentId]);
    $payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$hasHostel) {
        $feeStatus = 'not_allotted';
    } elseif (!$plan && $structureAnnual <= 0) {
        $feeStatus = 'no_structure';
    } elseif (!$plan) {
        $feeStatus = 'no_plan';
    } elseif ($planType === 'monthly' && $structureAnnual <= 0) {
        $feeStatus = 'no_structure';
    } elseif ($balance <= 0 && $totalDue > 0) {
        $feeStatus = 'cleared';
    } else {
        $feeStatus = 'pending';
    }

    return [
        'student' => $student,
        'has_hostel' => $hasHostel,
        'hostel' => $hostelInfo,
        'plan' => $plan,
        'plan_type' => $planType,
        'total_due' => $totalDue,
        'total_paid' => $totalPaid,
        'balance' => $balance,
        'gross_amount' => $plan ? (float) $plan['gross_amount'] : $structureAnnual,
        'discount_amount' => $plan ? (float) $plan['discount_amount'] : 0.0,
        'fee_status' => $feeStatus,
        'monthly_breakdown' => $monthlyBreakdown,
        'installment_statuses' => $installmentStatuses,
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

function getStudentHostelInstallmentFeeStatuses(PDO $pdo, int $studentId): array {
    $summary = getStudentHostelFeeSummary($pdo, $studentId);
    return $summary['installment_statuses'] ?? [];
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
