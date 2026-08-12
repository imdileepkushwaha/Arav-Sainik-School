<?php
$page_title = "Collect Hostel Fee";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';
require_once 'includes/class_helpers.php';

ensureErpSchema($pdo);
handleClassApiRequest($pdo);
$session = getCurrentSession($pdo);
$sessionId = $session['id'] ?? null;
$student = null;
$feeSummary = null;
$studentId = (int) ($_GET['student_id'] ?? $_POST['student_id'] ?? 0);

if ($studentId) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student) {
        $feeSummary = getStudentHostelFeeSummary($pdo, $studentId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_hostel_plan']) && $student) {
    $planId = (int) ($_POST['plan_id'] ?? 0);
    if (!studentHasActiveHostel($pdo, $studentId)) {
        $_SESSION['error_msg'] = 'Student has no active hostel allotment.';
    } elseif ($planId <= 0) {
        $_SESSION['error_msg'] = 'Select a payment plan.';
    } else {
        $existingPayments = $feeSummary['payments'] ?? [];
        $currentPlan = $feeSummary['plan'] ?? null;
        if ($currentPlan && !empty($existingPayments) && (int) $currentPlan['id'] !== $planId) {
            $_SESSION['error_msg'] = 'Cannot change plan after payments are recorded.';
        } else {
            try {
                assignStudentHostelPlan($pdo, $studentId, $planId, $sessionId);
                $_SESSION['success_msg'] = 'Hostel payment plan assigned.';
            } catch (Throwable $e) {
                $_SESSION['error_msg'] = $e->getMessage();
            }
        }
    }
    header('Location: hostel_fee_collect.php?student_id=' . $studentId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_hostel_admission_fee']) && $student) {
    $amount = (float) ($_POST['amount'] ?? 0);
    $discount = max(0, (float) ($_POST['discount_amount'] ?? 0));
    $method = trim($_POST['payment_method'] ?? 'Cash');
    $remarks = trim($_POST['remarks'] ?? '');
    $credit = $amount + $discount;
    $feeSummary = getStudentHostelFeeSummary($pdo, $studentId);
    $admission = $feeSummary['admission_fee'] ?? getStudentHostelAdmissionFeeStatus($pdo, $studentId, true);

    if (!studentHasActiveHostel($pdo, $studentId)) {
        $_SESSION['error_msg'] = 'Student has no active hostel allotment.';
        header('Location: hostel_fee_collect.php?student_id=' . $studentId);
        exit;
    }
    if (($admission['status'] ?? '') === 'paid' || (float) ($admission['due'] ?? 0) <= 0) {
        $_SESSION['error_msg'] = 'Hostel admission fee is already cleared.';
        header('Location: hostel_fee_collect.php?student_id=' . $studentId);
        exit;
    }
    if ($credit <= 0) {
        $_SESSION['error_msg'] = 'Enter a valid amount or discount.';
        header('Location: hostel_fee_collect.php?student_id=' . $studentId);
        exit;
    }
    $maxBalance = (float) ($admission['balance'] ?? 0);
    if ($discount > $maxBalance + 0.009 || $credit > $maxBalance + 0.009) {
        $_SESSION['error_msg'] = 'Amount + discount cannot exceed admission fee balance.';
        header('Location: hostel_fee_collect.php?student_id=' . $studentId);
        exit;
    }

    $remarks = trim($remarks . ' [admission_fee]');
    $receipt = generateHostelReceiptNo($pdo);
    $planId = !empty($feeSummary['plan']['id']) ? (int) $feeSummary['plan']['id'] : null;
    $insert = $pdo->prepare(
        "INSERT INTO hostel_fee_payments
         (student_id, amount, discount_amount, payment_date, fee_month, installment_no, plan_id, fee_kind, payment_method, receipt_no, session_id, remarks)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $insert->execute([
        $studentId,
        $amount,
        $discount,
        date('Y-m-d'),
        null,
        null,
        $planId,
        'admission',
        $method,
        $receipt,
        $sessionId,
        $remarks,
    ]);
    $paymentId = (int) $pdo->lastInsertId();
    $_SESSION['success_msg'] = 'Hostel admission fee collected. Receipt: ' . $receipt
        . ($discount > 0 ? ' (Discount ₹' . number_format($discount, 0) . ')' : '');
    header('Location: hostel_fee_receipt.php?id=' . $paymentId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_hostel_fee']) && $student) {
    $amount = (float) ($_POST['amount'] ?? 0);
    $discount = max(0, (float) ($_POST['discount_amount'] ?? 0));
    $method = trim($_POST['payment_method'] ?? 'Cash');
    $remarks = trim($_POST['remarks'] ?? '');
    $feeMonth = (int) ($_POST['fee_month'] ?? 0);
    $installmentNo = (int) ($_POST['installment_no'] ?? 0);
    $feeSummary = getStudentHostelFeeSummary($pdo, $studentId);
    $plan = $feeSummary['plan'] ?? null;

    if (!studentHasActiveHostel($pdo, $studentId)) {
        $_SESSION['error_msg'] = 'Student has no active hostel allotment.';
        header('Location: hostel_fee_collect.php?student_id=' . $studentId);
        exit;
    }
    if (!$plan) {
        $_SESSION['error_msg'] = 'Assign a payment plan first.';
        header('Location: hostel_fee_collect.php?student_id=' . $studentId);
        exit;
    }

    $planId = (int) $plan['id'];
    $isInstallment = ($plan['plan_type'] ?? '') === 'installment';
    $credit = $amount + $discount;

    if ($credit <= 0) {
        $_SESSION['error_msg'] = 'Enter a valid amount or discount.';
        header('Location: hostel_fee_collect.php?student_id=' . $studentId);
        exit;
    }

    if ($isInstallment) {
        if ($installmentNo < 1) {
            $_SESSION['error_msg'] = 'Select an installment.';
            header('Location: hostel_fee_collect.php?student_id=' . $studentId);
            exit;
        }
        $statuses = getStudentHostelInstallmentFeeStatuses($pdo, $studentId);
        $selected = null;
        foreach ($statuses as $st) {
            if ((int) $st['installment_no'] === $installmentNo) {
                $selected = $st;
                break;
            }
        }
        if ($selected && ($selected['status'] ?? '') === 'paid') {
            $_SESSION['error_msg'] = 'This installment is already fully paid.';
            header('Location: hostel_fee_collect.php?student_id=' . $studentId);
            exit;
        }
        $maxBalance = $selected ? (float) $selected['balance'] : 0;
        if ($discount > $maxBalance + 0.009) {
            $_SESSION['error_msg'] = 'Discount cannot exceed the due amount.';
            header('Location: hostel_fee_collect.php?student_id=' . $studentId);
            exit;
        }
        if ($credit > $maxBalance + 0.009) {
            $_SESSION['error_msg'] = 'Amount + discount cannot exceed the due balance.';
            header('Location: hostel_fee_collect.php?student_id=' . $studentId);
            exit;
        }
        if ($credit > 0) {
            $remarks = appendHostelInstallmentToRemarks($installmentNo, $remarks);
            $receipt = generateHostelReceiptNo($pdo);
            $insert = $pdo->prepare(
                "INSERT INTO hostel_fee_payments
                 (student_id, amount, discount_amount, payment_date, fee_month, installment_no, plan_id, fee_kind, payment_method, receipt_no, session_id, remarks)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $insert->execute([
                $studentId,
                $amount,
                $discount,
                date('Y-m-d'),
                null,
                $installmentNo,
                $planId,
                'regular',
                $method,
                $receipt,
                $sessionId,
                $remarks,
            ]);
            $paymentId = (int) $pdo->lastInsertId();
            $_SESSION['success_msg'] = 'Hostel fee collected. Receipt: ' . $receipt
                . ($discount > 0 ? ' (Discount ₹' . number_format($discount, 0) . ')' : '');
            header('Location: hostel_fee_receipt.php?id=' . $paymentId);
            exit;
        }
    } else {
        if ($feeMonth < 1 || $feeMonth > 12) {
            $_SESSION['error_msg'] = 'Select a valid fee month.';
            header('Location: hostel_fee_collect.php?student_id=' . $studentId);
            exit;
        }
        $monthStatuses = getStudentHostelMonthlyFeeStatuses($pdo, $studentId);
        $selectedMonthStatus = null;
        foreach ($monthStatuses as $ms) {
            if ((int) $ms['month'] === $feeMonth) {
                $selectedMonthStatus = $ms;
                break;
            }
        }
        if ($selectedMonthStatus && ($selectedMonthStatus['status'] ?? '') === 'paid') {
            $_SESSION['error_msg'] = 'This month is already fully paid.';
            header('Location: hostel_fee_collect.php?student_id=' . $studentId);
            exit;
        }
        $maxBalance = $selectedMonthStatus ? (float) $selectedMonthStatus['balance'] : 0;
        if ($discount > $maxBalance + 0.009) {
            $_SESSION['error_msg'] = 'Discount cannot exceed the due amount.';
            header('Location: hostel_fee_collect.php?student_id=' . $studentId);
            exit;
        }
        if ($credit > $maxBalance + 0.009) {
            $_SESSION['error_msg'] = 'Amount + discount cannot exceed the due balance.';
            header('Location: hostel_fee_collect.php?student_id=' . $studentId);
            exit;
        }
        if ($credit > 0) {
            $remarks = appendFeeMonthToRemarks($feeMonth, $remarks);
            $receipt = generateHostelReceiptNo($pdo);
            $insert = $pdo->prepare(
                "INSERT INTO hostel_fee_payments
                 (student_id, amount, discount_amount, payment_date, fee_month, installment_no, plan_id, fee_kind, payment_method, receipt_no, session_id, remarks)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $insert->execute([
                $studentId,
                $amount,
                $discount,
                date('Y-m-d'),
                $feeMonth,
                null,
                $planId,
                'regular',
                $method,
                $receipt,
                $sessionId,
                $remarks,
            ]);
            $paymentId = (int) $pdo->lastInsertId();
            $_SESSION['success_msg'] = 'Hostel fee collected. Receipt: ' . $receipt
                . ($discount > 0 ? ' (Discount ₹' . number_format($discount, 0) . ')' : '');
            header('Location: hostel_fee_receipt.php?id=' . $paymentId);
            exit;
        }
    }

    $_SESSION['error_msg'] = 'Enter a valid amount or discount.';
    header('Location: hostel_fee_collect.php?student_id=' . $studentId);
    exit;
}

require_once 'includes/header.php';
$class_options = getClassOptions($pdo);
$allPlans = getHostelFeePlans($pdo, $sessionId, true);
$filterName = trim($_GET['name'] ?? $_GET['q'] ?? '');
$filterClass = trim($_GET['class'] ?? '');
$filterSection = trim($_GET['section'] ?? '');
$sectionOptions = $filterClass !== '' ? getSectionOptions($pdo, $filterClass) : ['A', 'B', 'C', 'D'];
$searchResults = [];
$searchLabel = '';
$hasSearch = ($filterName !== '' || $filterClass !== '' || $filterSection !== '');

if (!$student && $hasSearch) {
    $searchResults = findHostelStudents($pdo, $filterName, $filterClass, $filterSection);
    $bits = [];
    if ($filterName !== '') {
        $bits[] = $filterName;
    }
    if ($filterClass !== '') {
        $bits[] = $filterClass . ($filterSection !== '' ? '-' . $filterSection : '');
    } elseif ($filterSection !== '') {
        $bits[] = 'Sec ' . $filterSection;
    }
    $searchLabel = implode(' · ', $bits);
}

if ($student && $feeSummary) {
    $feeSummary = getStudentHostelFeeSummary($pdo, $studentId);
}
$plan = $feeSummary['plan'] ?? null;
$planType = $feeSummary['plan_type'] ?? 'monthly';
$isInstallmentPlan = $plan && $planType === 'installment';
$monthStatuses = ($student && $feeSummary && !$isInstallmentPlan) ? getStudentHostelMonthlyFeeStatuses($pdo, $studentId) : [];
$installmentStatuses = ($student && $feeSummary && $isInstallmentPlan) ? getStudentHostelInstallmentFeeStatuses($pdo, $studentId) : [];
$collectableMonths = array_values(array_filter($monthStatuses, static function ($ms) {
    return in_array($ms['status'] ?? '', ['pending', 'partial'], true);
}));
$collectableInstallments = array_values(array_filter($installmentStatuses, static function ($st) {
    return in_array($st['status'] ?? '', ['pending', 'partial'], true);
}));
$defaultCollectMonth = $collectableMonths ? (int) $collectableMonths[0]['month'] : (int) date('n');
$defaultInstallment = $collectableInstallments ? (int) $collectableInstallments[0]['installment_no'] : 1;
$hostelInfo = $feeSummary['hostel'] ?? null;
$canChangePlan = empty(array_filter($feeSummary['payments'] ?? [], static function ($p) {
    return !isHostelAdmissionPayment($p);
}));
$admissionFee = $feeSummary['admission_fee'] ?? ['due' => 0, 'paid' => 0, 'balance' => 0, 'status' => 'paid', 'label' => 'Admission Fee'];
$admissionCollectable = $feeSummary && !empty($feeSummary['has_hostel'])
    && (float) ($admissionFee['due'] ?? 0) > 0
    && in_array($admissionFee['status'] ?? '', ['pending', 'partial'], true);
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-purple"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="content-top-title">
            <h2>Collect Hostel Fee</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="hostel.php">Hostel</a>
                <i class="fas fa-chevron-right"></i>
                <span>Collect Fee</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="hostel_fees.php" class="btn-header-action btn-header-outline"><i class="fas fa-file-invoice-dollar"></i> Hostel Fee Structure</a>
    </div>
</div>

<?php if (!$student): ?>
<div class="form-section-card section-mb fc-search-card hfc-search-card">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-hostel"><i class="fas fa-search"></i></div>
        <div>
            <h4>Find Hostel Student</h4>
            <p>Search by name, class and section together — only active hostel allotments</p>
        </div>
    </div>
    <form method="GET" class="hfc-search-form" id="hfcSearchForm">
        <div class="form-field form-field-grow">
            <label for="hfcName">Name / Serial No.</label>
            <input type="text" id="hfcName" name="name" class="form-input" value="<?php echo htmlspecialchars($filterName); ?>" placeholder="Student name or serial no." autofocus>
        </div>
        <div class="form-field">
            <label for="hfcClass">Class</label>
            <select id="hfcClass" name="class" class="form-input form-select">
                <option value="">All classes</option>
                <?php foreach ($class_options as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filterClass === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label for="hfcSection">Section</label>
            <select id="hfcSection" name="section" class="form-input form-select">
                <option value="">All</option>
                <?php foreach ($sectionOptions as $sec): ?>
                <option value="<?php echo htmlspecialchars($sec); ?>" <?php echo $filterSection === $sec ? 'selected' : ''; ?>><?php echo htmlspecialchars($sec); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field hfc-search-btn-wrap">
            <label>&nbsp;</label>
            <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-search"></i> Search</button>
        </div>
    </form>
    <script>
    (function () {
        var cls = document.getElementById('hfcClass');
        var sec = document.getElementById('hfcSection');
        if (!cls || !sec) return;
        cls.addEventListener('change', function () {
            var c = cls.value;
            sec.innerHTML = '<option value="">All</option>';
            if (!c) return;
            fetch('hostel_fee_collect.php?action=sections&class=' + encodeURIComponent(c))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    (data.sections || []).forEach(function (s) {
                        var o = document.createElement('option');
                        o.value = s;
                        o.textContent = s;
                        sec.appendChild(o);
                    });
                });
        });
    })();
    </script>

    <?php if ($searchLabel !== ''): ?>
    <div class="student-search-results-head">
        <span><i class="fas fa-search"></i> <?php echo count($searchResults); ?> hostel student<?php echo count($searchResults) === 1 ? '' : 's'; ?> — <?php echo htmlspecialchars($searchLabel); ?></span>
        <?php if ($searchResults): ?><small>Tap a student to collect fee</small><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($searchResults): ?>
    <div class="erp-search-results student-search-results hfc-student-list">
        <?php foreach ($searchResults as $r):
            $initial = strtoupper(substr(trim($r['name']), 0, 1));
            $meta = htmlspecialchars($r['ad_no']) . ' · ' . htmlspecialchars($r['class']) . '-' . htmlspecialchars($r['section'] ?? 'A');
        ?>
        <a href="hostel_fee_collect.php?student_id=<?php echo (int) $r['id']; ?>" class="erp-search-item student-search-card student-search-link hfc-student-row">
            <div class="student-search-main hfc-student-main">
                <div class="student-search-avatar is-initials"><?php echo htmlspecialchars($initial); ?></div>
                <div class="student-search-info hfc-student-info">
                    <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                    <span><?php echo $meta; ?></span>
                </div>
            </div>
            <span class="student-search-go"><i class="fas fa-chevron-right"></i></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php elseif ($hasSearch): ?>
    <div class="empty-state empty-state-md">
        <div class="empty-state-icon"><i class="fas fa-bed"></i></div>
        <h3>No hostel students found</h3>
        <p>Try another name / class / section, or allot a room first.</p>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<?php
$balance = (float) ($feeSummary['balance'] ?? 0);
$totalDue = (float) ($feeSummary['total_due'] ?? 0);
$totalPaid = (float) ($feeSummary['total_paid'] ?? 0);
$gross = (float) ($feeSummary['gross_amount'] ?? 0);
$discount = (float) ($feeSummary['discount_amount'] ?? 0);
$isCleared = ($feeSummary['fee_status'] ?? '') === 'cleared';
$paidPct = $totalDue > 0 ? (int) min(100, round(($totalPaid / $totalDue) * 100)) : ($isCleared ? 100 : 0);
$initials = strtoupper(substr(trim($student['name']), 0, 1));
$paymentCount = count($feeSummary['payments'] ?? []);
$statusList = $isInstallmentPlan ? $installmentStatuses : $monthStatuses;
$paidCount = count(array_filter($statusList, static fn($m) => ($m['status'] ?? '') === 'paid' && (float) ($m['due'] ?? 0) > 0));
$partialCount = count(array_filter($statusList, static fn($m) => ($m['status'] ?? '') === 'partial' && (float) ($m['due'] ?? 0) > 0));
$pendingCount = count(array_filter($statusList, static fn($m) => ($m['status'] ?? '') === 'pending' && (float) ($m['due'] ?? 0) > 0));
?>
<div class="fc-student-hero hfc-student-hero">
    <div class="fc-student-hero-main">
        <div class="fc-student-avatar"><?php echo htmlspecialchars($initials); ?></div>
        <div>
            <p class="fc-student-hero-label"><i class="fas fa-bed"></i> Collecting hostel fee for</p>
            <h3><?php echo htmlspecialchars($student['name']); ?></h3>
            <div class="fc-student-hero-chips">
                <span class="fc-student-chip"><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($student['ad_no']); ?></span>
                <span class="fc-student-chip"><i class="fas fa-school"></i> Class <?php echo htmlspecialchars($student['class']); ?> (<?php echo htmlspecialchars($student['section'] ?? 'A'); ?>)</span>
                <?php if ($hostelInfo): ?>
                <span class="fc-student-chip"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($hostelInfo['hostel_name']); ?> · Room <?php echo htmlspecialchars($hostelInfo['room_no']); ?></span>
                <?php endif; ?>
                <?php if ($plan): ?>
                <span class="fc-student-chip"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($plan['name']); ?></span>
                <?php endif; ?>
                <?php if ((float) ($admissionFee['due'] ?? 0) > 0): ?>
                <span class="fc-student-chip<?php echo ($admissionFee['status'] ?? '') === 'paid' ? ' is-success' : ' is-warning'; ?>">
                    <i class="fas fa-<?php echo ($admissionFee['status'] ?? '') === 'paid' ? 'check-circle' : 'id-card'; ?>"></i>
                    Admission <?php echo ($admissionFee['status'] ?? '') === 'paid' ? 'paid' : ('₹' . number_format((float) $admissionFee['balance'], 0) . ' due'); ?>
                </span>
                <?php endif; ?>
                <?php if ($isCleared): ?>
                <span class="fc-student-chip is-success"><i class="fas fa-check-circle"></i> Fully paid</span>
                <?php elseif (!$feeSummary['has_hostel']): ?>
                <span class="fc-student-chip is-warning"><i class="fas fa-info-circle"></i> No hostel allotment</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="fc-student-hero-actions">
        <a href="hostel_fee_collect.php" class="fc-hero-btn"><i class="fas fa-search"></i> Change Student</a>
        <a href="student_view.php?id=<?php echo (int) $studentId; ?>" class="fc-hero-btn is-solid"><i class="fas fa-user"></i> View Profile</a>
    </div>
</div>

<div class="fc-fee-stat-strip">
    <?php if ($gross > 0 && $discount > 0): ?>
    <div class="fc-fee-stat is-history">
        <div class="fc-fee-stat-icon"><i class="fas fa-tags"></i></div>
        <div><span>Gross</span><strong>₹<?php echo number_format($gross, 0); ?></strong></div>
    </div>
    <div class="fc-fee-stat is-paid">
        <div class="fc-fee-stat-icon"><i class="fas fa-percent"></i></div>
        <div><span>Discount</span><strong>₹<?php echo number_format($discount, 0); ?></strong></div>
    </div>
    <?php endif; ?>
    <div class="fc-fee-stat is-due">
        <div class="fc-fee-stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <div><span>Total Due</span><strong>₹<?php echo number_format($totalDue, 0); ?></strong></div>
    </div>
    <div class="fc-fee-stat is-paid">
        <div class="fc-fee-stat-icon"><i class="fas fa-check-circle"></i></div>
        <div><span>Paid</span><strong>₹<?php echo number_format($totalPaid, 0); ?></strong></div>
    </div>
    <div class="fc-fee-stat is-balance<?php echo $isCleared ? ' is-clear' : ''; ?>">
        <div class="fc-fee-stat-icon"><i class="fas fa-<?php echo $isCleared ? 'smile' : 'wallet'; ?>"></i></div>
        <div><span>Balance</span><strong>₹<?php echo number_format($balance, 0); ?></strong></div>
    </div>
    <div class="fc-fee-stat is-history">
        <div class="fc-fee-stat-icon"><i class="fas fa-receipt"></i></div>
        <div><span>Payments</span><strong><?php echo $paymentCount; ?></strong></div>
    </div>
</div>

<div class="fc-collect-layout">
    <aside class="form-section-card fc-summary-panel">
        <div class="fc-summary-hero">
            <div class="fc-summary-hero-top">
                <div>
                    <span class="fc-summary-kicker"><i class="fas fa-chart-pie"></i> Hostel Overview</span>
                    <h4>Payment Summary</h4>
                </div>
                <div class="fc-summary-ring" style="--pct: <?php echo (int) $paidPct; ?>">
                    <div class="fc-summary-ring-inner">
                        <strong><?php echo $paidPct; ?>%</strong>
                        <span>Paid</span>
                    </div>
                </div>
            </div>
            <div class="fc-progress-wrap">
                <div class="fc-progress-bar"><div class="fc-progress-fill" style="width:<?php echo $paidPct; ?>%"></div></div>
                <div class="fc-progress-labels">
                    <span>Paid ₹<?php echo number_format($totalPaid, 0); ?></span>
                    <span>Due ₹<?php echo number_format($totalDue, 0); ?></span>
                </div>
            </div>
            <?php if ($isCleared): ?>
            <div class="fc-cleared-note"><i class="fas fa-check-circle"></i> Hostel fee fully cleared for this plan.</div>
            <?php elseif (!$feeSummary['has_hostel']): ?>
            <div class="fc-no-structure-note"><i class="fas fa-exclamation-triangle"></i> No active hostel allotment — fee cannot be collected.</div>
            <?php elseif (!$plan): ?>
            <div class="fc-balance-alert"><i class="fas fa-info-circle"></i> Assign a payment plan to start collecting.</div>
            <?php else: ?>
            <div class="fc-balance-alert"><i class="fas fa-exclamation-circle"></i> Outstanding: <strong>₹<?php echo number_format($balance, 0); ?></strong></div>
            <?php endif; ?>
            <?php if ($session): ?>
            <p class="fc-session-note"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($session['name']); ?></p>
            <?php endif; ?>
        </div>

        <div class="fc-summary-body">
            <?php if ($hostelInfo): ?>
            <div class="fc-fee-breakdown">
                <p class="fc-fee-breakdown-title"><i class="fas fa-bed"></i> Hostel Details</p>
                <div class="fc-fee-head-list">
                    <div class="fc-fee-head-item">
                        <span>Hostel</span>
                        <strong><?php echo htmlspecialchars($hostelInfo['hostel_name']); ?></strong>
                    </div>
                    <div class="fc-fee-head-item">
                        <span>Room</span>
                        <strong><?php echo htmlspecialchars($hostelInfo['room_no']); ?></strong>
                    </div>
                    <?php if ((float) ($admissionFee['due'] ?? 0) > 0): ?>
                    <div class="fc-fee-head-item">
                        <span>Admission Fee</span>
                        <strong>
                            <?php if (($admissionFee['status'] ?? '') === 'paid'): ?>
                            ₹<?php echo number_format((float) $admissionFee['paid'], 0); ?> paid
                            <?php else: ?>
                            ₹<?php echo number_format((float) $admissionFee['balance'], 0); ?> due
                            <?php endif; ?>
                        </strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($plan): ?>
                    <div class="fc-fee-head-item">
                        <span>Plan</span>
                        <strong><?php echo htmlspecialchars($plan['name']); ?></strong>
                    </div>
                    <?php if (!empty($plan['installment_label'])): ?>
                    <div class="fc-fee-head-item">
                        <span>Schedule</span>
                        <strong><?php echo htmlspecialchars($plan['installment_label']); ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($statusList): ?>
            <div class="fc-monthly-breakdown">
                <div class="fc-monthly-breakdown-head">
                    <p class="fc-fee-breakdown-title"><i class="fas fa-calendar-check"></i> <?php echo $isInstallmentPlan ? 'Installment Status' : 'Month-wise Status'; ?></p>
                    <div class="fc-month-legend">
                        <?php if ($paidCount): ?><span class="is-paid"><?php echo $paidCount; ?> Paid</span><?php endif; ?>
                        <?php if ($partialCount): ?><span class="is-partial"><?php echo $partialCount; ?> Partial</span><?php endif; ?>
                        <?php if ($pendingCount): ?><span class="is-pending"><?php echo $pendingCount; ?> Pending</span><?php endif; ?>
                    </div>
                </div>
                <div class="fc-monthly-grid fc-month-status-grid">
                    <?php foreach ($statusList as $st):
                        if ((float) ($st['due'] ?? 0) <= 0) continue;
                        $chipClass = 'is-pending';
                        $statusLabel = 'Pending';
                        if (($st['status'] ?? '') === 'paid') {
                            $chipClass = 'is-paid';
                            $statusLabel = 'Paid';
                        } elseif (($st['status'] ?? '') === 'partial') {
                            $chipClass = 'is-partial';
                            $statusLabel = 'Partial';
                        }
                    ?>
                    <div class="fc-monthly-chip fc-month-status-chip <?php echo $chipClass; ?>">
                        <div class="fc-month-chip-top">
                            <span><?php echo htmlspecialchars($st['label']); ?></span>
                            <em class="fc-month-status-badge"><?php echo $statusLabel; ?></em>
                        </div>
                        <strong>₹<?php echo number_format((float) $st['due'], 0); ?></strong>
                        <?php if (($st['status'] ?? '') === 'paid'): ?>
                        <small><i class="fas fa-check"></i> Cleared</small>
                        <?php elseif (($st['status'] ?? '') === 'partial'): ?>
                        <small>₹<?php echo number_format((float) $st['balance'], 0); ?> left</small>
                        <?php else: ?>
                        <small>₹<?php echo number_format((float) $st['balance'], 0); ?> due</small>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </aside>

    <div class="form-section-card fc-collect-form-card">
        <div class="fc-form-head">
            <div class="fc-form-head-icon"><i class="fas fa-bed"></i></div>
            <div>
                <h4>Record Hostel Payment</h4>
                <p>Separate hostel receipt (HRCP) will be generated</p>
            </div>
        </div>

        <?php if (!$feeSummary['has_hostel']): ?>
        <div class="empty-state empty-state-md">
            <h3>Allot hostel first</h3>
            <p><a href="hostel.php">Go to Hostel Allotment</a></p>
        </div>
        <?php else: ?>

        <?php if ($admissionCollectable): ?>
        <form method="POST" class="fc-collect-form section-mb" style="border-bottom:1px dashed #e2e8f0;margin-bottom:8px;padding-bottom:8px">
            <input type="hidden" name="collect_hostel_admission_fee" value="1">
            <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
            <div class="section-card-header" style="padding:0 0 12px;border:0">
                <div class="section-card-icon section-icon-hostel"><i class="fas fa-id-card"></i></div>
                <div>
                    <h4>Collect Admission Fee</h4>
                    <p>One-time hostel admission — ₹<?php echo number_format((float) $admissionFee['balance'], 0); ?> due</p>
                </div>
            </div>
            <div class="fc-collect-form-grid">
                <div class="form-field">
                    <label><i class="fas fa-rupee-sign"></i> Amount (₹)</label>
                    <input type="number" step="0.01" min="0" name="amount" id="hfAdmAmount" class="form-input"
                           value="<?php echo htmlspecialchars(number_format((float) $admissionFee['balance'], 2, '.', '')); ?>" required>
                </div>
                <div class="form-field">
                    <label><i class="fas fa-percent"></i> Discount (₹)</label>
                    <input type="number" step="0.01" min="0" name="discount_amount" id="hfAdmDiscount" class="form-input" value="0">
                </div>
                <div class="form-field">
                    <label><i class="fas fa-credit-card"></i> Payment Mode</label>
                    <select name="payment_method" class="form-input form-select">
                        <option>Cash</option>
                        <option>UPI</option>
                        <option>Card</option>
                        <option>Bank Transfer</option>
                        <option>Cheque</option>
                    </select>
                </div>
                <div class="form-field form-field-full">
                    <label><i class="fas fa-comment"></i> Remarks</label>
                    <input type="text" name="remarks" class="form-input" placeholder="Optional">
                </div>
            </div>
            <p class="fc-month-balance-note" id="hfAdmHint">One-time admission fee</p>
            <div class="settings-form-actions">
                <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-check"></i> Collect Admission Fee</button>
            </div>
        </form>
        <script>
        (function () {
            var due = <?php echo json_encode((float) $admissionFee['balance']); ?>;
            var amt = document.getElementById('hfAdmAmount');
            var disc = document.getElementById('hfAdmDiscount');
            var hint = document.getElementById('hfAdmHint');
            function sync() {
                if (!amt || !disc) return;
                var d = Math.max(0, parseFloat(disc.value || '0') || 0);
                if (d > due) { d = due; disc.value = d.toFixed(2); }
                amt.value = Math.max(0, due - d).toFixed(2);
                if (hint) hint.textContent = 'Cash ₹' + amt.value + ' + Discount ₹' + d.toFixed(2) + ' = Credit ₹' + (parseFloat(amt.value) + d).toFixed(2);
            }
            if (disc) disc.addEventListener('input', sync);
            sync();
        })();
        </script>
        <?php elseif ((float) ($admissionFee['due'] ?? 0) > 0 && ($admissionFee['status'] ?? '') === 'paid'): ?>
        <div class="fc-cleared-note" style="margin:16px 22px 0"><i class="fas fa-check-circle"></i> Hostel admission fee cleared (₹<?php echo number_format((float) $admissionFee['paid'], 0); ?>).</div>
        <?php endif; ?>

        <?php if (!$plan || $canChangePlan): ?>
        <form method="POST" class="fc-collect-form section-mb">
            <input type="hidden" name="assign_hostel_plan" value="1">
            <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
            <div class="section-card-header" style="padding:0 0 12px;border:0">
                <div class="section-card-icon section-icon-hostel"><i class="fas fa-layer-group"></i></div>
                <div>
                    <h4><?php echo $plan ? 'Change Payment Plan' : 'Select Payment Plan'; ?></h4>
                    <p>Choose how the parent will pay hostel fee this year</p>
                </div>
            </div>
            <div class="fs-class-grid" style="margin-bottom:16px">
                <?php foreach ($allPlans as $p):
                    $isSelected = $plan && (int) $plan['id'] === (int) $p['id'];
                ?>
                <label class="fs-class-pill<?php echo $isSelected ? ' is-active' : ''; ?>" style="cursor:pointer">
                    <input type="radio" name="plan_id" value="<?php echo (int) $p['id']; ?>" <?php echo $isSelected ? 'checked' : ''; ?> required style="position:absolute;opacity:0;pointer-events:none">
                    <div class="fs-class-pill-top">
                        <span class="fs-class-pill-icon"><i class="fas fa-<?php echo $p['plan_type'] === 'monthly' ? 'calendar-alt' : 'coins'; ?>"></i></span>
                        <?php if ((float) $p['discount_amount'] > 0): ?>
                        <span class="fs-class-pill-status is-set">Save ₹<?php echo number_format((float) $p['discount_amount'], 0); ?></span>
                        <?php elseif ((float) $p['discount_amount'] <= 0 && $p['plan_type'] === 'installment'): ?>
                        <span class="fs-class-pill-status is-pending">No discount</span>
                        <?php endif; ?>
                    </div>
                    <span class="fs-class-pill-name"><?php echo htmlspecialchars($p['name']); ?></span>
                    <span class="fs-class-pill-amount">₹<?php echo number_format((float) $p['net_amount'], 0); ?></span>
                    <span class="fs-class-pill-meta"><?php echo htmlspecialchars($p['installment_label'] ?: ''); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="settings-form-actions">
                <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-check"></i> <?php echo $plan ? 'Update Plan' : 'Assign Plan'; ?></button>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($plan): ?>
            <?php if ($feeSummary['fee_status'] === 'no_structure'): ?>
            <div class="empty-state empty-state-md">
                <h3>No monthly hostel fee structure</h3>
                <p>Set monthly fee for this class in <a href="hostel_fees.php?class=<?php echo urlencode($student['class']); ?>">Hostel Fee Structure</a>.</p>
            </div>
            <?php elseif ($feeSummary['fee_status'] === 'cleared' || ((float) ($feeSummary['plan_balance'] ?? 0) <= 0 && (float) ($feeSummary['plan_due'] ?? 0) > 0)): ?>
            <div class="empty-state empty-state-md">
                <h3>Plan fee cleared</h3>
                <p>Monthly / installment hostel fee for this plan is fully paid.</p>
            </div>
            <?php elseif ($isInstallmentPlan && $collectableInstallments): ?>
            <form method="POST" class="fc-collect-form">
                <input type="hidden" name="collect_hostel_fee" value="1">
                <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                <div class="fc-collect-form-grid">
                    <div class="form-field">
                        <label><i class="fas fa-layer-group"></i> Installment</label>
                        <select name="installment_no" id="hfInstallment" class="form-input form-select" required>
                            <?php foreach ($collectableInstallments as $st): ?>
                            <option value="<?php echo (int) $st['installment_no']; ?>"
                                    data-due="<?php echo htmlspecialchars(number_format($st['balance'], 2, '.', '')); ?>"
                                    <?php echo (int) $st['installment_no'] === $defaultInstallment ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($st['label']); ?> — ₹<?php echo number_format($st['balance'], 0); ?> due
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label><i class="fas fa-rupee-sign"></i> Amount (₹)</label>
                        <input type="number" step="0.01" min="0" name="amount" id="hfAmount" class="form-input" required>
                    </div>
                    <div class="form-field">
                        <label><i class="fas fa-percent"></i> Discount (₹)</label>
                        <input type="number" step="0.01" min="0" name="discount_amount" id="hfDiscount" class="form-input" value="0" placeholder="0">
                    </div>
                    <div class="form-field">
                        <label><i class="fas fa-credit-card"></i> Payment Mode</label>
                        <select name="payment_method" class="form-input form-select">
                            <option>Cash</option>
                            <option>UPI</option>
                            <option>Card</option>
                            <option>Bank Transfer</option>
                            <option>Cheque</option>
                        </select>
                    </div>
                    <div class="form-field form-field-full">
                        <label><i class="fas fa-comment"></i> Remarks</label>
                        <input type="text" name="remarks" class="form-input" placeholder="Optional">
                    </div>
                </div>
                <p class="fc-month-balance-note" id="hfPayHint">Payable = Due − Discount</p>
                <div class="settings-form-actions">
                    <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-check"></i> Collect &amp; Print Receipt</button>
                </div>
            </form>
            <script>
            (function () {
                var sel = document.getElementById('hfInstallment');
                var amt = document.getElementById('hfAmount');
                var disc = document.getElementById('hfDiscount');
                var hint = document.getElementById('hfPayHint');
                function due() {
                    var opt = sel && sel.options[sel.selectedIndex];
                    return opt ? parseFloat(opt.getAttribute('data-due') || '0') : 0;
                }
                function syncFromDue() {
                    if (!amt || !disc) return;
                    var d = Math.max(0, parseFloat(disc.value || '0') || 0);
                    var max = due();
                    if (d > max) { d = max; disc.value = d.toFixed(2); }
                    amt.value = Math.max(0, max - d).toFixed(2);
                    if (hint) hint.textContent = 'Cash ₹' + amt.value + ' + Discount ₹' + d.toFixed(2) + ' = Credit ₹' + (parseFloat(amt.value) + d).toFixed(2);
                }
                if (sel) sel.addEventListener('change', function () { if (disc) disc.value = '0'; syncFromDue(); });
                if (disc) disc.addEventListener('input', syncFromDue);
                syncFromDue();
            })();
            </script>
            <?php elseif (!$isInstallmentPlan && $collectableMonths): ?>
            <form method="POST" class="fc-collect-form">
                <input type="hidden" name="collect_hostel_fee" value="1">
                <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                <div class="fc-collect-form-grid">
                    <div class="form-field">
                        <label><i class="fas fa-calendar-alt"></i> Fee Month</label>
                        <select name="fee_month" id="hfFeeMonth" class="form-input form-select" required>
                            <?php foreach ($collectableMonths as $ms): ?>
                            <option value="<?php echo (int) $ms['month']; ?>"
                                    data-due="<?php echo htmlspecialchars(number_format($ms['balance'], 2, '.', '')); ?>"
                                    <?php echo (int) $ms['month'] === $defaultCollectMonth ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ms['label']); ?> — ₹<?php echo number_format($ms['balance'], 0); ?> due
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label><i class="fas fa-rupee-sign"></i> Amount (₹)</label>
                        <input type="number" step="0.01" min="0" name="amount" id="hfAmount" class="form-input" required>
                    </div>
                    <div class="form-field">
                        <label><i class="fas fa-percent"></i> Discount (₹)</label>
                        <input type="number" step="0.01" min="0" name="discount_amount" id="hfDiscount" class="form-input" value="0" placeholder="0">
                    </div>
                    <div class="form-field">
                        <label><i class="fas fa-credit-card"></i> Payment Mode</label>
                        <select name="payment_method" class="form-input form-select">
                            <option>Cash</option>
                            <option>UPI</option>
                            <option>Card</option>
                            <option>Bank Transfer</option>
                            <option>Cheque</option>
                        </select>
                    </div>
                    <div class="form-field form-field-full">
                        <label><i class="fas fa-comment"></i> Remarks</label>
                        <input type="text" name="remarks" class="form-input" placeholder="Optional">
                    </div>
                </div>
                <p class="fc-month-balance-note" id="hfPayHint">Payable = Due − Discount</p>
                <div class="settings-form-actions">
                    <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-check"></i> Collect &amp; Print Receipt</button>
                </div>
            </form>
            <script>
            (function () {
                var sel = document.getElementById('hfFeeMonth');
                var amt = document.getElementById('hfAmount');
                var disc = document.getElementById('hfDiscount');
                var hint = document.getElementById('hfPayHint');
                function due() {
                    var opt = sel && sel.options[sel.selectedIndex];
                    return opt ? parseFloat(opt.getAttribute('data-due') || '0') : 0;
                }
                function syncFromDue() {
                    if (!amt || !disc) return;
                    var d = Math.max(0, parseFloat(disc.value || '0') || 0);
                    var max = due();
                    if (d > max) { d = max; disc.value = d.toFixed(2); }
                    amt.value = Math.max(0, max - d).toFixed(2);
                    if (hint) hint.textContent = 'Cash ₹' + amt.value + ' + Discount ₹' + d.toFixed(2) + ' = Credit ₹' + (parseFloat(amt.value) + d).toFixed(2);
                }
                if (sel) sel.addEventListener('change', function () { if (disc) disc.value = '0'; syncFromDue(); });
                if (disc) disc.addEventListener('input', syncFromDue);
                syncFromDue();
            })();
            </script>
            <?php elseif ($plan && $feeSummary['fee_status'] !== 'no_structure'): ?>
            <div class="empty-state empty-state-md">
                <h3>Nothing due</h3>
                <p>No pending installment or month for this student.</p>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($feeSummary['payments'])): ?>
        <div class="table-container" style="margin:0 22px 22px">
            <div class="table-toolbar"><strong>Hostel Payment History</strong></div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Date</th><th>For</th><th>Amount</th><th>Discount</th><th>Mode</th><th>Receipt</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feeSummary['payments'] as $p):
                            if (isHostelAdmissionPayment($p)) {
                                $forLabel = 'Admission Fee';
                            } else {
                                $m = paymentRecordFeeMonth($p);
                                $inst = (int) ($p['installment_no'] ?? 0);
                                if ($inst < 1 && preg_match('/\[installment:(\d+)\]/', (string) ($p['remarks'] ?? ''), $mm)) {
                                    $inst = (int) $mm[1];
                                }
                                $forLabel = $inst > 0
                                    ? ('Installment ' . $inst)
                                    : ($m ? (getFeeMonthLabels()[$m] ?? $m) : '—');
                            }
                            $rowDiscount = hostelPaymentDiscountAmount($p);
                        ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                            <td><?php echo htmlspecialchars((string) $forLabel); ?></td>
                            <td>₹<?php echo number_format((float) $p['amount'], 0); ?></td>
                            <td><?php echo $rowDiscount > 0 ? ('₹' . number_format($rowDiscount, 0)) : '—'; ?></td>
                            <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
                            <td><a href="hostel_fee_receipt.php?id=<?php echo (int) $p['id']; ?>" target="_blank" class="fc-receipt-link"><i class="fas fa-print"></i> <?php echo htmlspecialchars($p['receipt_no']); ?></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
document.querySelectorAll('.fs-class-pill input[type=radio]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.fs-class-pill').forEach(function (pill) { pill.classList.remove('is-active'); });
        if (radio.checked) radio.closest('.fs-class-pill').classList.add('is-active');
    });
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
