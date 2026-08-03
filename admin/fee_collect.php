<?php
$page_title = "Collect Fee";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';
require_once 'includes/settings_helpers.php';

ensureErpSchema($pdo);
ensureSettingsSchema($pdo);
migrateFeeMonthBackfillCleanup($pdo);
$session = getCurrentSession($pdo);
$heads = getFeeHeads($pdo);
$student = null;
$feeSummary = null;
$studentId = (int) ($_GET['student_id'] ?? $_POST['student_id'] ?? 0);

if ($studentId) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student) {
        $feeSummary = getStudentFeeSummary($pdo, $studentId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_payment_month']) && $student) {
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $correctMonth = (int) ($_POST['correct_fee_month'] ?? 0);
    if ($paymentId > 0 && $correctMonth >= 1 && $correctMonth <= 12) {
        if (assignPaymentFeeMonth($pdo, $paymentId, $studentId, $correctMonth)) {
            $_SESSION['success_msg'] = 'Payment month updated to ' . (getFeeMonthLabels()[$correctMonth] ?? $correctMonth) . '.';
        } else {
            $_SESSION['error_msg'] = 'Could not update payment month. Please refresh and try again.';
        }
    } else {
        $_SESSION['error_msg'] = 'Select a valid month to update.';
    }
    header('Location: fee_collect.php?student_id=' . $studentId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_fee']) && $student) {
    $amount = (float) ($_POST['amount'] ?? 0);
    $headId = (int) ($_POST['fee_head_id'] ?? 0) ?: null;
    $method = trim($_POST['payment_method'] ?? 'Cash');
    $remarks = trim($_POST['remarks'] ?? '');
    $feeMonth = (int) ($_POST['fee_month'] ?? 0);
    if ($feeMonth < 1 || $feeMonth > 12) {
        $_SESSION['error_msg'] = 'Select a valid fee month.';
        header('Location: fee_collect.php?student_id=' . $studentId);
        exit;
    }
    $monthStatuses = getStudentMonthlyFeeStatuses($pdo, $studentId);
    $selectedMonthStatus = null;
    foreach ($monthStatuses as $ms) {
        if ((int) $ms['month'] === $feeMonth) {
            $selectedMonthStatus = $ms;
            break;
        }
    }
    if ($selectedMonthStatus && ($selectedMonthStatus['status'] ?? '') === 'paid') {
        $_SESSION['error_msg'] = 'This month is already fully paid.';
        header('Location: fee_collect.php?student_id=' . $studentId);
        exit;
    }
    if ($headId) {
        if (!feeHeadAppliesToStudent($pdo, $studentId, $headId)) {
            $_SESSION['error_msg'] = 'This fee head is not applicable for this student.';
            header('Location: fee_collect.php?student_id=' . $studentId);
            exit;
        }
    }
    if ($amount > 0) {
        ensureFeePaymentsFeeMonthColumn($pdo);
        $remarks = appendFeeMonthToRemarks($feeMonth, $remarks);
        $receipt = generateReceiptNo($pdo);
        $insert = $pdo->prepare(
            "INSERT INTO fee_payments (student_id, fee_head_id, amount, payment_date, fee_month, payment_method, receipt_no, session_id, remarks) VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $insert->bindValue(1, $studentId, PDO::PARAM_INT);
        $insert->bindValue(2, $headId, $headId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $insert->bindValue(3, $amount);
        $insert->bindValue(4, date('Y-m-d'));
        $insert->bindValue(5, $feeMonth, PDO::PARAM_INT);
        $insert->bindValue(6, $method);
        $insert->bindValue(7, $receipt);
        $insert->bindValue(8, $session['id'] ?? null, ($session['id'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $insert->bindValue(9, $remarks);
        $insert->execute();
        $paymentId = (int) $pdo->lastInsertId();
        if ($paymentId <= 0 || !persistPaymentFeeMonth($pdo, $paymentId, $feeMonth)) {
            $_SESSION['error_msg'] = 'Payment saved but fee month could not be recorded. Please try again.';
            header('Location: fee_collect.php?student_id=' . $studentId);
            exit;
        }
        $_SESSION['success_msg'] = 'Fee collected. Receipt: ' . $receipt;
        header('Location: fee_receipt.php?id=' . $paymentId);
        exit;
    }
    $_SESSION['error_msg'] = 'Enter a valid amount.';
    header('Location: fee_collect.php?student_id=' . $studentId);
    exit;
}

require_once 'includes/header.php';
$class_options = getClassOptions($pdo);
$searchMode = ($_GET['mode'] ?? 'quick') === 'class' ? 'class' : 'quick';
$searchType = $_GET['type'] ?? 'ad_no';
if (!in_array($searchType, ['ad_no', 'name', 'roll'], true)) {
    $searchType = 'ad_no';
}
$search = trim($_GET['q'] ?? '');
$filterClass = trim($_GET['class'] ?? '');
$filterSection = trim($_GET['section'] ?? 'A');
$searchResults = [];
$searchLabel = '';

if ($searchMode === 'class' && $filterClass !== '') {
    $rows = getStudentsByClassSection($pdo, $filterClass, $filterSection);
    $searchResults = array_map(static function ($r) {
        return [
            'id' => $r['id'],
            'ad_no' => $r['ad_no'],
            'name' => $r['name'],
            'class' => $r['class'],
            'section' => $r['section'],
            'roll' => $r['roll'],
        ];
    }, $rows);
    $searchLabel = 'Class ' . $filterClass . ' (' . ($filterSection ?: 'A') . ')';
} elseif ($searchMode === 'quick' && $search !== '') {
    $searchColumns = ['ad_no' => 'ad_no', 'name' => 'name', 'roll' => 'roll'];
    $column = $searchColumns[$searchType];
    $stmt = $pdo->prepare("SELECT id, ad_no, name, class, section, roll FROM students WHERE status='Active' AND {$column} LIKE ? ORDER BY name ASC LIMIT 20");
    $like = '%' . $search . '%';
    $stmt->execute([$like]);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $searchTypeLabels = ['ad_no' => 'admission no.', 'name' => 'name', 'roll' => 'roll no.'];
    $searchLabel = $searchTypeLabels[$searchType] . ': "' . $search . '"';
}

$sectionOptions = $filterClass !== '' ? getSectionOptions($pdo, $filterClass) : ['A'];
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-orange"><i class="fas fa-money-bill-wave"></i></div>
        <div class="content-top-title">
            <h2>Collect Fee</h2>
            <p class="content-top-breadcrumb"><a href="fees.php">Fees</a><i class="fas fa-chevron-right"></i><span>Collect</span></p>
        </div>
    </div>
    <div class="content-top-actions"><a href="fees.php" class="btn-header-action btn-header-outline"><i class="fas fa-arrow-left"></i> Fee Structure</a></div>
</div>

<?php if (!$student): ?>
<div class="form-section-card section-mb fc-search-card">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-search"></i></div>
        <div>
            <h4>Find Student</h4>
            <p>Quick search by admission no. or browse students class-wise</p>
        </div>
    </div>
    <div class="fc-search-tabs" role="tablist">
        <a href="fee_collect.php?mode=quick" class="fc-search-tab<?php echo $searchMode === 'quick' ? ' is-active' : ''; ?>"><i class="fas fa-bolt"></i> Quick Find</a>
        <a href="fee_collect.php?mode=class" class="fc-search-tab<?php echo $searchMode === 'class' ? ' is-active' : ''; ?>"><i class="fas fa-school"></i> Browse by Class</a>
    </div>
    <?php if ($searchMode === 'quick'): ?>
    <form method="GET" class="category-add-row fc-search-form">
        <input type="hidden" name="mode" value="quick">
        <div class="form-field fc-search-type-field">
            <label>Search by</label>
            <select name="type" id="fcSearchType" class="form-input form-select">
                <option value="ad_no" <?php echo $searchType === 'ad_no' ? 'selected' : ''; ?>>Admission No.</option>
                <option value="name" <?php echo $searchType === 'name' ? 'selected' : ''; ?>>Student Name</option>
                <option value="roll" <?php echo $searchType === 'roll' ? 'selected' : ''; ?>>Roll No.</option>
            </select>
        </div>
        <div class="form-field form-field-grow">
            <label id="fcSearchQueryLabel"><?php echo $searchType === 'ad_no' ? 'Admission number' : ($searchType === 'roll' ? 'Roll number' : 'Student name'); ?></label>
            <input type="text" name="q" id="fcSearchQueryInput" class="form-input" placeholder="<?php echo $searchType === 'ad_no' ? 'e.g. ADM0001' : ($searchType === 'roll' ? 'e.g. 12' : 'e.g. Rahul Kumar'); ?>" value="<?php echo htmlspecialchars($search); ?>" autofocus>
        </div>
        <div class="form-field category-add-btn-wrap">
            <label>&nbsp;</label>
            <button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-search"></i> Search</button>
        </div>
    </form>
    <?php else: ?>
    <form method="GET" class="category-add-row erp-filter-row">
        <input type="hidden" name="mode" value="class">
        <div class="form-field">
            <label>Class</label>
            <select name="class" class="form-input form-select" required>
                <option value="">Select class</option>
                <?php foreach ($class_options as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filterClass === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label>Section</label>
            <select name="section" class="form-input form-select">
                <?php foreach ($sectionOptions as $sec): ?>
                <option value="<?php echo htmlspecialchars($sec); ?>" <?php echo $filterSection === $sec ? 'selected' : ''; ?>><?php echo htmlspecialchars($sec); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field category-add-btn-wrap">
            <label>&nbsp;</label>
            <button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-users"></i> Load Students</button>
        </div>
    </form>
    <?php endif; ?>
    <?php if ($searchLabel !== ''): ?>
    <div class="student-search-results-head">
        <span><i class="fas fa-search"></i> <?php echo count($searchResults); ?> student<?php echo count($searchResults) === 1 ? '' : 's'; ?> — <?php echo htmlspecialchars($searchLabel); ?></span>
        <?php if ($searchResults): ?><small>Tap a student to collect fee</small><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($searchResults): ?>
    <div class="erp-search-results student-search-results">
        <?php foreach ($searchResults as $r):
            $initials = '';
            foreach (preg_split('/\s+/', trim($r['name'])) as $part) {
                if ($part !== '') {
                    $initials .= strtoupper($part[0]);
                }
            }
            $initials = substr($initials, 0, 2) ?: 'S';
            $section = trim($r['section'] ?? '') ?: 'A';
        ?>
        <a href="fee_collect.php?student_id=<?php echo $r['id']; ?>" class="erp-search-item student-search-card student-search-link student-search-fee">
            <div class="student-search-main">
                <div class="student-search-avatar is-initials"><?php echo htmlspecialchars($initials); ?></div>
                <div class="student-search-info">
                    <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                    <div class="student-search-id-row">
                        <span class="student-search-id-chip"><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($r['ad_no']); ?></span>
                        <span class="student-search-id-chip"><i class="fas fa-hashtag"></i> Roll <?php echo htmlspecialchars($r['roll']); ?></span>
                    </div>
                    <div class="student-search-meta">
                        <span class="student-search-class-pill"><i class="fas fa-school"></i> Class <?php echo htmlspecialchars($r['class']); ?> (<?php echo htmlspecialchars($section); ?>)</span>
                    </div>
                </div>
            </div>
            <div class="student-search-link-action">
                <span class="student-search-link-label">Collect Fee</span>
                <span class="student-search-go"><i class="fas fa-money-bill-wave"></i></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php elseif ($searchLabel !== ''): ?>
    <div class="student-search-empty">
        <div class="student-search-empty-icon"><i class="fas fa-user-slash"></i></div>
        <h4>No students found</h4>
        <p><?php echo $searchMode === 'class' ? 'No active students in this class and section.' : 'Try a different search term or switch the search type.'; ?></p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($student && $feeSummary):
    $initials = '';
    foreach (preg_split('/\s+/', trim($student['name'])) as $part) {
        if ($part !== '') {
            $initials .= strtoupper($part[0]);
        }
    }
    $initials = substr($initials, 0, 2) ?: 'S';
    $section = trim($student['section'] ?? '') ?: 'A';
    $paidPct = $feeSummary['total_due'] > 0
        ? min(100, (int) round(($feeSummary['total_paid'] / $feeSummary['total_due']) * 100))
        : ($feeSummary['total_paid'] > 0 ? 100 : 0);
    $isCleared = ($feeSummary['fee_status'] ?? '') === 'cleared';
    $noFeeStructure = ($feeSummary['fee_status'] ?? '') === 'no_structure';
    $paymentCount = count($feeSummary['payments']);
    $applicableHeads = $feeSummary['fee_items'] ?? [];
    $monthlyBreakdown = $feeSummary['monthly_breakdown'] ?? [];
    $currentMonthDue = (float) ($feeSummary['current_month_due'] ?? 0);
    $monthLabels = getFeeMonthLabels();
    $currentMonthLabel = $monthLabels[(int) date('n')] ?? date('M');
    $monthlyFeeStatuses = getStudentMonthlyFeeStatuses($pdo, $studentId);
    $collectableMonths = array_values(array_filter($monthlyFeeStatuses, static function ($ms) {
        return (float) ($ms['due'] ?? 0) > 0 && ($ms['status'] ?? '') !== 'paid';
    }));
    $currentMonthStatus = null;
    foreach ($monthlyFeeStatuses as $ms) {
        if ((int) $ms['month'] === (int) date('n')) {
            $currentMonthStatus = $ms;
            break;
        }
    }
    $collectMonth = (int) date('n');
    $collectMonthBalance = 0.0;
    $defaultCollectMonth = !empty($collectableMonths) ? (int) $collectableMonths[0]['month'] : $collectMonth;
    foreach ($collectableMonths as $ms) {
        if ((int) $ms['month'] === $collectMonth) {
            $defaultCollectMonth = $collectMonth;
            $collectMonthBalance = (float) $ms['balance'];
            break;
        }
    }
    if ($collectMonthBalance <= 0 && !empty($collectableMonths)) {
        $defaultCollectMonth = (int) $collectableMonths[0]['month'];
        $collectMonthBalance = (float) $collectableMonths[0]['balance'];
    }
    $defaultCollectMonthLabel = $monthLabels[$defaultCollectMonth] ?? '';
    $defaultMonthStatus = null;
    foreach ($monthlyFeeStatuses as $ms) {
        if ((int) $ms['month'] === $defaultCollectMonth) {
            $defaultMonthStatus = $ms;
            break;
        }
    }
    $defaultAmount = !$isCleared && $collectMonthBalance > 0 ? $collectMonthBalance : 0;
    if ($defaultAmount <= 0 && !$isCleared) {
        $defaultAmount = (float) $feeSummary['balance'];
    }
    $monthBreakdownMap = [];
    foreach ($collectableMonths as $ms) {
        if ($ms['due'] <= 0) {
            continue;
        }
        $headLines = [];
        foreach ($applicableHeads as $item) {
            $headDue = (float) ($item['months'][(int) $ms['month']] ?? 0);
            if ($headDue <= 0) {
                continue;
            }
            $headLines[] = [
                'head_name' => $item['head_name'],
                'due' => $headDue,
            ];
        }
        $monthBreakdownMap[(int) $ms['month']] = [
            'label' => $ms['label'],
            'head_lines' => $headLines,
            'month_due' => (float) $ms['due'],
            'month_paid' => (float) $ms['paid'],
            'month_balance' => (float) $ms['balance'],
        ];
    }
?>
<div class="fc-student-hero">
    <div class="fc-student-hero-main">
        <div class="fc-student-avatar"><?php echo htmlspecialchars($initials); ?></div>
        <div>
            <p class="fc-student-hero-label"><i class="fas fa-user-graduate"></i> Collecting fee for</p>
            <h3><?php echo htmlspecialchars($student['name']); ?></h3>
            <div class="fc-student-hero-chips">
                <span class="fc-student-chip"><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($student['ad_no']); ?></span>
                <span class="fc-student-chip"><i class="fas fa-hashtag"></i> Roll <?php echo htmlspecialchars($student['roll']); ?></span>
                <span class="fc-student-chip"><i class="fas fa-school"></i> Class <?php echo htmlspecialchars($student['class']); ?> (<?php echo htmlspecialchars($section); ?>)</span>
                <?php if ($isCleared): ?>
                <span class="fc-student-chip is-success"><i class="fas fa-check-circle"></i> Fully paid</span>
                <?php elseif ($noFeeStructure): ?>
                <span class="fc-student-chip is-warning"><i class="fas fa-info-circle"></i> No fee assigned</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="fc-student-hero-actions">
        <a href="fee_collect.php" class="fc-hero-btn"><i class="fas fa-search"></i> Change Student</a>
        <a href="student_view.php?id=<?php echo (int) $studentId; ?>" class="fc-hero-btn is-solid"><i class="fas fa-user"></i> View Profile</a>
    </div>
</div>

<div class="fc-fee-stat-strip">
    <div class="fc-fee-stat is-due">
        <div class="fc-fee-stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <div><span>Total Due</span><strong>₹<?php echo number_format($feeSummary['total_due'], 2); ?></strong></div>
    </div>
    <div class="fc-fee-stat is-paid">
        <div class="fc-fee-stat-icon"><i class="fas fa-check-circle"></i></div>
        <div><span>Paid</span><strong>₹<?php echo number_format($feeSummary['total_paid'], 2); ?></strong></div>
    </div>
    <div class="fc-fee-stat is-balance<?php echo $isCleared ? ' is-clear' : ''; ?>">
        <div class="fc-fee-stat-icon"><i class="fas fa-<?php echo $isCleared ? 'smile' : 'wallet'; ?>"></i></div>
        <div><span>Balance</span><strong>₹<?php echo number_format($feeSummary['balance'], 2); ?></strong></div>
    </div>
    <div class="fc-fee-stat is-history">
        <div class="fc-fee-stat-icon"><i class="fas fa-receipt"></i></div>
        <div><span>Payments</span><strong><?php echo $paymentCount; ?></strong></div>
    </div>
    <?php if ($currentMonthStatus && $currentMonthStatus['due'] > 0): ?>
    <div class="fc-fee-stat is-month<?php echo $currentMonthStatus['status'] === 'paid' ? ' is-clear' : ''; ?>">
        <div class="fc-fee-stat-icon"><i class="fas fa-calendar-day"></i></div>
        <div>
            <span><?php echo htmlspecialchars($currentMonthLabel); ?>
                <?php echo $currentMonthStatus['status'] === 'paid' ? 'paid' : ($currentMonthStatus['status'] === 'partial' ? 'partial' : 'pending'); ?>
            </span>
            <strong>₹<?php echo number_format($currentMonthStatus['status'] === 'paid' ? $currentMonthStatus['paid'] : $currentMonthStatus['balance'], 2); ?></strong>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="fc-collect-layout">
    <aside class="form-section-card fc-summary-panel">
        <div class="fc-summary-hero">
            <div class="fc-summary-hero-top">
                <div>
                    <span class="fc-summary-kicker"><i class="fas fa-chart-pie"></i> Fee Overview</span>
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
                    <span>Paid ₹<?php echo number_format($feeSummary['total_paid'], 0); ?></span>
                    <span>Due ₹<?php echo number_format($feeSummary['total_due'], 0); ?></span>
                </div>
            </div>
            <?php if ($isCleared): ?>
            <div class="fc-cleared-note"><i class="fas fa-check-circle"></i> All fees cleared for this student.</div>
            <?php elseif ($noFeeStructure): ?>
            <div class="fc-no-structure-note"><i class="fas fa-exclamation-triangle"></i> No fee structure for <strong><?php echo htmlspecialchars($student['class']); ?></strong>. <a href="fees.php">Set up fees</a> or collect an advance payment.</div>
            <?php else: ?>
            <div class="fc-balance-alert"><i class="fas fa-exclamation-circle"></i> Outstanding: <strong>₹<?php echo number_format($feeSummary['balance'], 2); ?></strong></div>
            <?php endif; ?>
            <?php if ($session): ?>
            <p class="fc-session-note"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($session['name']); ?></p>
            <?php endif; ?>
        </div>

        <div class="fc-summary-body">
        <?php if ($applicableHeads): ?>
        <div class="fc-fee-breakdown">
            <p class="fc-fee-breakdown-title"><i class="fas fa-tags"></i> Annual Fee Heads</p>
            <div class="fc-fee-head-list">
            <?php foreach ($applicableHeads as $item): ?>
            <div class="fc-fee-head-item">
                <span><?php echo htmlspecialchars($item['head_name']); ?></span>
                <strong>₹<?php echo number_format($item['amount'], 0); ?></strong>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($monthlyFeeStatuses):
            $fcPaidMonths = count(array_filter($monthlyFeeStatuses, fn($m) => $m['due'] > 0 && ($m['status'] ?? '') === 'paid'));
            $fcPartialMonths = count(array_filter($monthlyFeeStatuses, fn($m) => $m['due'] > 0 && ($m['status'] ?? '') === 'partial'));
            $fcPendingMonths = count(array_filter($monthlyFeeStatuses, fn($m) => $m['due'] > 0 && ($m['status'] ?? '') === 'pending'));
        ?>
        <div class="fc-monthly-breakdown">
            <div class="fc-monthly-breakdown-head">
                <p class="fc-fee-breakdown-title"><i class="fas fa-calendar-check"></i> Month-wise Status</p>
                <div class="fc-month-legend">
                    <?php if ($fcPaidMonths): ?><span class="is-paid"><?php echo $fcPaidMonths; ?> Paid</span><?php endif; ?>
                    <?php if ($fcPartialMonths): ?><span class="is-partial"><?php echo $fcPartialMonths; ?> Partial</span><?php endif; ?>
                    <?php if ($fcPendingMonths): ?><span class="is-pending"><?php echo $fcPendingMonths; ?> Pending</span><?php endif; ?>
                </div>
            </div>
            <div class="fc-monthly-grid fc-month-status-grid">
                <?php foreach ($monthlyFeeStatuses as $ms):
                    if ($ms['due'] <= 0) continue;
                    $chipClass = 'is-pending';
                    $statusLabel = 'Pending';
                    if ($ms['status'] === 'paid') {
                        $chipClass = 'is-paid';
                        $statusLabel = 'Paid';
                    } elseif ($ms['status'] === 'partial') {
                        $chipClass = 'is-partial';
                        $statusLabel = 'Partial';
                    }
                    $isSelectedMonth = (int) $ms['month'] === $defaultCollectMonth;
                ?>
                <div class="fc-monthly-chip fc-month-status-chip <?php echo $chipClass; ?><?php echo $isSelectedMonth ? ' is-current' : ''; ?>" data-month="<?php echo (int) $ms['month']; ?>" data-status="<?php echo htmlspecialchars($ms['status']); ?>">
                    <div class="fc-month-chip-top">
                        <span><?php echo htmlspecialchars($ms['label']); ?></span>
                        <em class="fc-month-status-badge"><?php echo $statusLabel; ?></em>
                    </div>
                    <strong>₹<?php echo number_format($ms['due'], 0); ?></strong>
                    <?php if ($ms['status'] === 'partial'): ?>
                    <small>₹<?php echo number_format($ms['balance'], 0); ?> left</small>
                    <?php elseif ($ms['status'] === 'paid'): ?>
                    <small><i class="fas fa-check"></i> Cleared</small>
                    <?php else: ?>
                    <small>₹<?php echo number_format($ms['balance'], 0); ?> due</small>
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
            <div class="fc-form-head-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <h4>Record Payment</h4>
                <p>Select fee month and amount — monthly receipt will be generated</p>
            </div>
        </div>
        <form method="POST" class="fc-collect-form" id="fcCollectForm">
            <input type="hidden" name="collect_fee" value="1">
            <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
            <div class="fc-collect-form-grid">
                <div class="form-field">
                    <label><i class="fas fa-calendar-alt"></i> Fee Month</label>
                    <?php if ($collectableMonths): ?>
                    <select name="fee_month" id="fcFeeMonth" class="form-input form-select" required>
                        <?php foreach ($collectableMonths as $ms):
                            $isDefault = (int) $ms['month'] === $defaultCollectMonth;
                            $statusText = $ms['status'] === 'partial' ? 'Partial · ₹' . number_format($ms['balance'], 0) . ' left' : 'Pending · ₹' . number_format($ms['balance'], 0) . ' due';
                        ?>
                        <option value="<?php echo (int) $ms['month']; ?>"
                                data-due="<?php echo htmlspecialchars(number_format($ms['due'], 2, '.', '')); ?>"
                                data-paid="<?php echo htmlspecialchars(number_format($ms['paid'], 2, '.', '')); ?>"
                                data-balance="<?php echo htmlspecialchars(number_format($ms['balance'], 2, '.', '')); ?>"
                                data-status="<?php echo htmlspecialchars($ms['status']); ?>"
                                <?php echo $isDefault ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ms['label']); ?> — ₹<?php echo number_format($ms['due'], 0); ?> (<?php echo $statusText; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php elseif (!array_filter($monthlyFeeStatuses, static fn($m) => (float) ($m['due'] ?? 0) > 0)): ?>
                    <select name="fee_month" id="fcFeeMonth" class="form-input form-select" required>
                        <?php foreach (getFeeMonthOrder() as $m): ?>
                        <option value="<?php echo $m; ?>" <?php echo $m === $defaultCollectMonth ? 'selected' : ''; ?>><?php echo htmlspecialchars($monthLabels[$m]); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <p class="fc-all-paid-note"><i class="fas fa-circle-check"></i> All monthly fees are fully paid.</p>
                    <?php endif; ?>
                </div>
                <div class="form-field">
                    <label><i class="fas fa-tags"></i> Fee Head</label>
                    <select name="fee_head_id" class="form-input form-select">
                        <option value="">General / Mixed</option>
                        <?php foreach ($applicableHeads as $item): ?>
                        <option value="<?php echo (int) $item['fee_head_id']; ?>"><?php echo htmlspecialchars($item['head_name']); ?> (₹<?php echo number_format($item['amount'], 0); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field fc-field-amount">
                    <label><i class="fas fa-rupee-sign"></i> Payment Amount</label>
                    <div class="fc-pay-type-toggle" role="group" aria-label="Payment amount type">
                        <label class="fc-pay-type-opt is-active">
                            <input type="radio" name="fc_pay_type" value="full" checked>
                            <span><i class="fas fa-check-circle"></i> Full balance</span>
                        </label>
                        <label class="fc-pay-type-opt">
                            <input type="radio" name="fc_pay_type" value="other">
                            <span><i class="fas fa-pen"></i> Other amount</span>
                        </label>
                    </div>
                    <input type="number" step="0.01" min="0.01" name="amount" id="fcAmountInput" class="form-input fc-amount-input" placeholder="0.00" <?php echo $defaultAmount > 0 ? 'value="' . htmlspecialchars(number_format($defaultAmount, 2, '.', '')) . '"' : ''; ?> required>
                    <div class="fc-pay-preview" id="fcPayPreview">
                        <p class="fc-pay-breakup-title"><i class="fas fa-list-ul"></i> Fee breakup — <span id="fcBreakupMonthLabel"><?php echo htmlspecialchars($defaultCollectMonthLabel); ?></span></p>
                        <div class="fc-pay-breakup" id="fcPayBreakup"></div>
                        <div class="fc-pay-summary" id="fcPaySummary">
                            <?php if ($defaultMonthStatus && $defaultMonthStatus['due'] > 0): ?>
                            <div class="fc-pay-preview-row"><span>Month due</span><strong id="fcPreviewDue">₹<?php echo number_format($defaultMonthStatus['due'], 2); ?></strong></div>
                            <div class="fc-pay-preview-row"><span>Already paid</span><strong id="fcPreviewPaid">₹<?php echo number_format($defaultMonthStatus['paid'], 2); ?></strong></div>
                            <div class="fc-pay-preview-row"><span>Paying now</span><strong id="fcPreviewPaying">₹<?php echo number_format($defaultAmount, 2); ?></strong></div>
                            <div class="fc-pay-preview-row is-pending"><span>Pending after</span><strong id="fcPreviewPending">₹<?php echo number_format(max(0, (float) $defaultMonthStatus['balance'] - $defaultAmount), 2); ?></strong></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="form-field">
                    <label><i class="fas fa-credit-card"></i> Payment Method</label>
                    <select name="payment_method" class="form-input form-select">
                        <option>Cash</option>
                        <option>UPI</option>
                        <option>Card</option>
                        <option>Bank Transfer</option>
                        <option>Cheque</option>
                    </select>
                </div>
                <div class="form-field">
                    <label><i class="fas fa-comment"></i> Remarks</label>
                    <input type="text" name="remarks" class="form-input" placeholder="Optional note">
                </div>
            </div>
            <div class="fc-form-actions">
                <button type="submit" class="btn-header-action btn-header-primary fc-submit-btn" <?php echo empty($collectableMonths) && array_filter($monthlyFeeStatuses, static fn($m) => (float) ($m['due'] ?? 0) > 0) ? 'disabled' : ''; ?>><i class="fas fa-receipt"></i> Collect &amp; Print Receipt</button>
            </div>
        </form>
    </div>
</div>

<?php if ($feeSummary['payments']): ?>
<div class="form-section-card fc-history-card section-mb">
    <div class="fc-history-head">
        <h4><i class="fas fa-history"></i> Payment History</h4>
        <span class="fc-history-count"><?php echo $paymentCount; ?> transaction<?php echo $paymentCount === 1 ? '' : 's'; ?></span>
    </div>
    <div class="table-wrapper">
        <table class="fc-history-table">
            <thead><tr><th>Date</th><th>Month</th><th>Receipt</th><th>Fee Head</th><th>Amount</th><th>Method</th><th></th><th>Fix Month</th></tr></thead>
            <tbody>
            <?php foreach ($feeSummary['payments'] as $p):
                $payMonth = paymentRecordFeeMonth($p);
                $payMonthLabel = $payMonth ? ($monthLabels[$payMonth] ?? $payMonth) : '—';
            ?>
            <tr>
                <td><span class="fc-date-cell"><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($p['payment_date'])); ?></span></td>
                <td><span class="fc-month-cell"><?php echo htmlspecialchars($payMonthLabel); ?></span></td>
                <td><code class="fc-receipt-code"><?php echo htmlspecialchars($p['receipt_no']); ?></code></td>
                <td><?php echo displayVal($p['head_name'], 'General'); ?></td>
                <td><strong class="fc-amount-cell">₹<?php echo number_format($p['amount'], 2); ?></strong></td>
                <td><span class="fc-method-badge fc-method-<?php echo strtolower(preg_replace('/\s+/', '-', $p['payment_method'])); ?>"><?php echo htmlspecialchars($p['payment_method']); ?></span></td>
                <td><a href="fee_receipt.php?id=<?php echo $p['id']; ?>" class="fc-receipt-link" target="_blank"><i class="fas fa-print"></i> Receipt</a></td>
                <td class="fc-fix-month-cell">
                    <form method="POST" class="fc-fix-month-form" action="fee_collect.php?student_id=<?php echo (int) $studentId; ?>">
                        <input type="hidden" name="fix_payment_month" value="1">
                        <input type="hidden" name="student_id" value="<?php echo (int) $studentId; ?>">
                        <input type="hidden" name="payment_id" value="<?php echo (int) $p['id']; ?>">
                        <select name="correct_fee_month" class="form-input form-select" aria-label="Correct fee month">
                            <?php foreach (getFeeMonthOrder() as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo $payMonth === $m ? 'selected' : ''; ?>><?php echo htmlspecialchars($monthLabels[$m]); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="fc-fix-month-btn" title="Update fee month"><i class="fas fa-check"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="fc-no-payments section-mb">
    <i class="fas fa-receipt"></i>
    <p>No payments recorded yet. Collect the first fee using the form above.</p>
</div>
<?php endif; ?>
<?php endif; ?>
<?php if (!$student): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var typeSelect = document.getElementById('fcSearchType');
    var queryLabel = document.getElementById('fcSearchQueryLabel');
    var queryInput = document.getElementById('fcSearchQueryInput');
    var searchFields = {
        ad_no: { label: 'Admission number', placeholder: 'e.g. ADM0001' },
        name: { label: 'Student name', placeholder: 'e.g. Rahul Kumar' },
        roll: { label: 'Roll number', placeholder: 'e.g. 12' }
    };

    function updateSearchField() {
        if (!typeSelect || !queryLabel || !queryInput) return;
        var cfg = searchFields[typeSelect.value] || searchFields.ad_no;
        queryLabel.textContent = cfg.label;
        queryInput.placeholder = cfg.placeholder;
    }

    typeSelect?.addEventListener('change', updateSearchField);
});
</script>
<?php endif; ?>
<?php if ($student && $feeSummary): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var monthSelect = document.getElementById('fcFeeMonth');
    var amountInput = document.getElementById('fcAmountInput');
    var payTypeRadios = document.querySelectorAll('input[name="fc_pay_type"]');
    var payTypeLabels = document.querySelectorAll('.fc-pay-type-opt');
    var previewDue = document.getElementById('fcPreviewDue');
    var previewPaid = document.getElementById('fcPreviewPaid');
    var previewPaying = document.getElementById('fcPreviewPaying');
    var previewPending = document.getElementById('fcPreviewPending');
    var previewBox = document.getElementById('fcPayPreview');
    var breakupEl = document.getElementById('fcPayBreakup');
    var breakupMonthLabel = document.getElementById('fcBreakupMonthLabel');
    var monthLabels = <?php echo json_encode($monthLabels); ?>;
    var monthBreakdownMap = <?php echo json_encode($monthBreakdownMap); ?>;

    function formatInr(n) {
        return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderBreakup(monthKey) {
        if (!breakupEl) return;
        var monthData = monthBreakdownMap[monthKey] || monthBreakdownMap[String(monthKey)];
        if (!monthData || !monthData.head_lines || !monthData.head_lines.length) {
            breakupEl.innerHTML = '<p class="fc-pay-breakup-empty">No fee heads for this month.</p>';
            return;
        }
        var rows = monthData.head_lines.map(function (line) {
            return '<tr><td>' + escapeHtml(line.head_name) + '</td><td>' + formatInr(line.due) + '</td></tr>';
        }).join('');
        breakupEl.innerHTML =
            '<table class="fc-pay-breakup-table">' +
            '<thead><tr><th>Fee Head</th><th>Amount</th></tr></thead>' +
            '<tbody>' + rows + '</tbody>' +
            '<tfoot><tr><td><strong>Month total</strong></td><td><strong>' + formatInr(monthData.month_due) + '</strong></td></tr></tfoot>' +
            '</table>';
        if (breakupMonthLabel) {
            breakupMonthLabel.textContent = monthData.label || (monthLabels[monthKey] || monthKey);
        }
    }

    function getSelectedMonthData() {
        if (!monthSelect) return null;
        var opt = monthSelect.options[monthSelect.selectedIndex];
        if (!opt || opt.disabled) return null;
        return {
            due: parseFloat(opt.getAttribute('data-due') || '0') || 0,
            paid: parseFloat(opt.getAttribute('data-paid') || '0') || 0,
            balance: parseFloat(opt.getAttribute('data-balance') || '0') || 0,
            status: opt.getAttribute('data-status') || 'pending',
            label: monthLabels[parseInt(monthSelect.value, 10)] || monthSelect.value
        };
    }

    function isOtherAmountMode() {
        var checked = document.querySelector('input[name="fc_pay_type"]:checked');
        return checked && checked.value === 'other';
    }

    function highlightOverviewMonth(monthKey) {
        document.querySelectorAll('.fc-month-status-chip').forEach(function (chip) {
            chip.classList.toggle('is-current', chip.getAttribute('data-month') === String(monthKey));
        });
    }

    function updatePreview() {
        var data = getSelectedMonthData();
        if (!data || !previewBox) return;
        var paying = parseFloat(amountInput?.value || '0') || 0;
        var pending = Math.max(0, data.balance - paying);
        if (monthSelect) {
            renderBreakup(monthSelect.value);
            highlightOverviewMonth(monthSelect.value);
        }
        if (previewDue) previewDue.textContent = formatInr(data.due);
        if (previewPaid) previewPaid.textContent = formatInr(data.paid);
        if (previewPaying) previewPaying.textContent = formatInr(paying);
        if (previewPending) {
            previewPending.textContent = formatInr(pending);
            previewPending.closest('.fc-pay-preview-row')?.classList.toggle('is-clear', pending <= 0.001);
            previewPending.closest('.fc-pay-preview-row')?.classList.toggle('is-pending', pending > 0.001);
        }
        previewBox.style.display = data.due > 0 ? '' : 'none';
    }

    function applyPayType() {
        var data = getSelectedMonthData();
        if (!amountInput || !data) return;
        if (isOtherAmountMode()) {
            amountInput.readOnly = false;
            amountInput.focus();
        } else {
            amountInput.readOnly = false;
            if (data.balance > 0) {
                amountInput.value = data.balance.toFixed(2);
            }
        }
        updatePreview();
    }

    function updateMonthSelection() {
        var data = getSelectedMonthData();
        if (!data) return;
        if (!isOtherAmountMode() && data.balance > 0 && amountInput) {
            amountInput.value = data.balance.toFixed(2);
        } else if (data.balance <= 0 && amountInput) {
            amountInput.value = '';
        }
        updatePreview();
    }

    payTypeRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            payTypeLabels.forEach(function (label) {
                label.classList.toggle('is-active', label.querySelector('input') === radio && radio.checked);
            });
            applyPayType();
        });
    });

    monthSelect?.addEventListener('change', updateMonthSelection);
    document.getElementById('fcCollectForm')?.addEventListener('submit', function (e) {
        if (!monthSelect || !monthSelect.value) {
            e.preventDefault();
            alert('Please select a fee month.');
        }
    });
    amountInput?.addEventListener('input', function () {
        if (isOtherAmountMode()) {
            updatePreview();
        }
    });

    updatePreview();
});
</script>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
