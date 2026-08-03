<?php
$page_title = "Collect Hostel Fee";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
$session = getCurrentSession($pdo);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_hostel_fee']) && $student) {
    $amount = (float) ($_POST['amount'] ?? 0);
    $method = trim($_POST['payment_method'] ?? 'Cash');
    $remarks = trim($_POST['remarks'] ?? '');
    $feeMonth = (int) ($_POST['fee_month'] ?? 0);

    if (!studentHasActiveHostel($pdo, $studentId)) {
        $_SESSION['error_msg'] = 'Student has no active hostel allotment.';
        header('Location: hostel_fee_collect.php?student_id=' . $studentId);
        exit;
    }
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
    if ($amount > 0) {
        $remarks = appendFeeMonthToRemarks($feeMonth, $remarks);
        $receipt = generateHostelReceiptNo($pdo);
        $insert = $pdo->prepare(
            "INSERT INTO hostel_fee_payments (student_id, amount, payment_date, fee_month, payment_method, receipt_no, session_id, remarks)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $insert->execute([
            $studentId,
            $amount,
            date('Y-m-d'),
            $feeMonth,
            $method,
            $receipt,
            $session['id'] ?? null,
            $remarks,
        ]);
        $paymentId = (int) $pdo->lastInsertId();
        $_SESSION['success_msg'] = 'Hostel fee collected. Receipt: ' . $receipt;
        header('Location: hostel_fee_receipt.php?id=' . $paymentId);
        exit;
    }
    $_SESSION['error_msg'] = 'Enter a valid amount.';
    header('Location: hostel_fee_collect.php?student_id=' . $studentId);
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
$filterSection = trim($_GET['section'] ?? '');
$sectionOptions = $filterClass !== '' ? getSectionOptions($pdo, $filterClass) : ['A', 'B', 'C', 'D'];
$searchResults = [];
$searchLabel = '';

if (!$student) {
    if ($searchMode === 'quick' && $search !== '') {
        $searchResults = searchHostelStudents($pdo, $searchType, $search);
        $searchLabel = $searchType === 'name' ? 'Name: ' . $search : ($searchType === 'roll' ? 'Roll: ' . $search : 'Adm: ' . $search);
    } elseif ($searchMode === 'class' && $filterClass !== '') {
        $searchResults = getHostelStudentsByClass($pdo, $filterClass, $filterSection);
        $searchLabel = $filterClass . ($filterSection !== '' ? ' · Sec ' . $filterSection : '');
    }
}

$monthStatuses = $student && $feeSummary ? getStudentHostelMonthlyFeeStatuses($pdo, $studentId) : [];
$collectableMonths = array_values(array_filter($monthStatuses, function ($ms) {
    return in_array($ms['status'] ?? '', ['pending', 'partial'], true);
}));
$defaultCollectMonth = $collectableMonths ? (int) $collectableMonths[0]['month'] : (int) date('n');
$hostelInfo = $feeSummary['hostel'] ?? null;
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
<div class="form-section-card section-mb fc-search-card">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-hostel"><i class="fas fa-search"></i></div>
        <div>
            <h4>Find Hostel Student</h4>
            <p>Only students with active hostel allotment appear here</p>
        </div>
    </div>
    <div class="fc-search-tabs" role="tablist">
        <a href="hostel_fee_collect.php?mode=quick" class="fc-search-tab<?php echo $searchMode === 'quick' ? ' is-active' : ''; ?>"><i class="fas fa-bolt"></i> Quick Find</a>
        <a href="hostel_fee_collect.php?mode=class" class="fc-search-tab<?php echo $searchMode === 'class' ? ' is-active' : ''; ?>"><i class="fas fa-school"></i> Browse by Class</a>
    </div>
    <?php if ($searchMode === 'quick'): ?>
    <form method="GET" class="category-add-row fc-search-form">
        <input type="hidden" name="mode" value="quick">
        <div class="form-field fc-search-type-field">
            <label>Search by</label>
            <select name="type" class="form-input form-select">
                <option value="ad_no" <?php echo $searchType === 'ad_no' ? 'selected' : ''; ?>>Admission No.</option>
                <option value="name" <?php echo $searchType === 'name' ? 'selected' : ''; ?>>Student Name</option>
                <option value="roll" <?php echo $searchType === 'roll' ? 'selected' : ''; ?>>Roll No.</option>
            </select>
        </div>
        <div class="form-field form-field-grow">
            <label>Query</label>
            <input type="text" name="q" class="form-input" value="<?php echo htmlspecialchars($search); ?>" autofocus>
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
                <option value="">All</option>
                <?php foreach ($sectionOptions as $sec): ?>
                <option value="<?php echo htmlspecialchars($sec); ?>" <?php echo $filterSection === $sec ? 'selected' : ''; ?>><?php echo htmlspecialchars($sec); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field category-add-btn-wrap">
            <label>&nbsp;</label>
            <button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-users"></i> Load</button>
        </div>
    </form>
    <?php endif; ?>

    <?php if ($searchLabel !== ''): ?>
    <div class="student-search-results-head">
        <span><i class="fas fa-search"></i> <?php echo count($searchResults); ?> hostel student<?php echo count($searchResults) === 1 ? '' : 's'; ?> — <?php echo htmlspecialchars($searchLabel); ?></span>
    </div>
    <?php endif; ?>

    <?php if ($searchResults): ?>
    <div class="erp-search-results student-search-results">
        <?php foreach ($searchResults as $r): ?>
        <a href="hostel_fee_collect.php?student_id=<?php echo (int) $r['id']; ?>" class="erp-search-item student-search-card student-search-link">
            <div class="student-search-avatar"><?php echo strtoupper(substr($r['name'], 0, 1)); ?></div>
            <div class="student-search-body">
                <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                <span><?php echo htmlspecialchars($r['ad_no']); ?> · <?php echo htmlspecialchars($r['class']); ?>-<?php echo htmlspecialchars($r['section'] ?? 'A'); ?></span>
            </div>
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endforeach; ?>
    </div>
    <?php elseif ($searchLabel !== ''): ?>
    <div class="empty-state empty-state-md">
        <div class="empty-state-icon"><i class="fas fa-bed"></i></div>
        <h3>No hostel students found</h3>
        <p>Allot a room first from Hostel → Allotment.</p>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<?php
$balance = (float) ($feeSummary['balance'] ?? 0);
$totalDue = (float) ($feeSummary['total_due'] ?? 0);
$totalPaid = (float) ($feeSummary['total_paid'] ?? 0);
?>
<div class="fc-layout">
    <aside class="fc-student-panel">
        <div class="fc-hero">
            <div class="fc-hero-avatar"><i class="fas fa-user-graduate"></i></div>
            <div>
                <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                <p><?php echo htmlspecialchars($student['ad_no']); ?> · <?php echo htmlspecialchars($student['class']); ?>-<?php echo htmlspecialchars($student['section'] ?? 'A'); ?></p>
            </div>
            <a href="hostel_fee_collect.php" class="fc-hero-btn"><i class="fas fa-search"></i> Change</a>
        </div>

        <?php if ($hostelInfo): ?>
        <div class="form-section-card section-mb" style="margin-top:12px">
            <div class="section-card-header">
                <div class="section-card-icon section-icon-hostel"><i class="fas fa-bed"></i></div>
                <div><h4>Hostel</h4><p><?php echo htmlspecialchars($hostelInfo['hostel_name']); ?> · Room <?php echo htmlspecialchars($hostelInfo['room_no']); ?></p></div>
            </div>
        </div>
        <?php else: ?>
        <p class="fc-optional-fee-note"><i class="fas fa-info-circle"></i> No active hostel allotment — hostel fee cannot be collected.</p>
        <?php endif; ?>

        <div class="fc-summary-grid">
            <div class="fc-summary-card"><span>Total Due</span><strong>₹<?php echo number_format($totalDue, 0); ?></strong></div>
            <div class="fc-summary-card"><span>Paid</span><strong>₹<?php echo number_format($totalPaid, 0); ?></strong></div>
            <div class="fc-summary-card"><span>Balance</span><strong>₹<?php echo number_format($balance, 0); ?></strong></div>
        </div>

        <?php if ($monthStatuses): ?>
        <div class="fc-month-strip">
            <?php foreach ($monthStatuses as $ms): ?>
            <div class="fc-month-chip is-<?php echo htmlspecialchars($ms['status']); ?>">
                <span><?php echo htmlspecialchars($ms['label']); ?></span>
                <strong>₹<?php echo number_format($ms['due'], 0); ?></strong>
                <small><?php echo $ms['status'] === 'paid' ? 'Cleared' : ('₹' . number_format($ms['balance'], 0) . ' due'); ?></small>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
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
        <?php elseif ($feeSummary['fee_status'] === 'no_structure'): ?>
        <div class="empty-state empty-state-md">
            <h3>No hostel fee structure</h3>
            <p>Set amounts for this class in <a href="hostel_fees.php?class=<?php echo urlencode($student['class']); ?>">Hostel Fee Structure</a>.</p>
        </div>
        <?php elseif (!$collectableMonths): ?>
        <div class="empty-state empty-state-md">
            <h3>All months cleared</h3>
            <p>Hostel fee for this session is fully paid.</p>
        </div>
        <?php else: ?>
        <form method="POST" class="fc-collect-form">
            <input type="hidden" name="collect_hostel_fee" value="1">
            <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
            <div class="fc-collect-form-grid">
                <div class="form-field">
                    <label>Fee Month</label>
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
                    <label>Amount (₹)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="hfAmount" class="form-input" required>
                </div>
                <div class="form-field">
                    <label>Payment Mode</label>
                    <select name="payment_method" class="form-input form-select">
                        <option>Cash</option>
                        <option>UPI</option>
                        <option>Card</option>
                        <option>Bank Transfer</option>
                        <option>Cheque</option>
                    </select>
                </div>
                <div class="form-field form-field-full">
                    <label>Remarks</label>
                    <input type="text" name="remarks" class="form-input" placeholder="Optional">
                </div>
            </div>
            <div class="settings-form-actions">
                <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-check"></i> Collect &amp; Print Receipt</button>
            </div>
        </form>
        <script>
        (function () {
            var sel = document.getElementById('hfFeeMonth');
            var amt = document.getElementById('hfAmount');
            function sync() {
                var opt = sel.options[sel.selectedIndex];
                if (opt && amt) amt.value = opt.getAttribute('data-due') || '';
            }
            if (sel) { sel.addEventListener('change', sync); sync(); }
        })();
        </script>
        <?php endif; ?>

        <?php if (!empty($feeSummary['payments'])): ?>
        <div class="table-container" style="margin-top:24px">
            <div class="table-toolbar"><strong>Hostel Payment History</strong></div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Date</th><th>Month</th><th>Amount</th><th>Mode</th><th>Receipt</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feeSummary['payments'] as $p):
                            $m = paymentRecordFeeMonth($p);
                        ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                            <td><?php echo $m ? htmlspecialchars(getFeeMonthLabels()[$m] ?? $m) : '—'; ?></td>
                            <td>₹<?php echo number_format((float) $p['amount'], 0); ?></td>
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
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
