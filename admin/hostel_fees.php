<?php
$page_title = "Hostel Fee Structure";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
$session = getCurrentSession($pdo);
$sessionId = $session['id'] ?? null;
$class_options = getClassOptions($pdo);
$feeMonthOrder = getFeeMonthOrder();
$feeMonthLabels = getFeeMonthLabels();
$selectedClass = trim($_GET['class'] ?? '');
$classSummaries = getHostelClassFeeSummaries($pdo, $sessionId);
$admissionFeeAmount = getHostelAdmissionFeeAmount($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_admission_fee') {
        $amt = max(0, (float) ($_POST['hostel_admission_fee'] ?? 1000));
        if (!function_exists('setSetting')) {
            require_once 'includes/settings_helpers.php';
        }
        setSetting($pdo, 'hostel_admission_fee', (string) $amt);
        $_SESSION['success_msg'] = 'Hostel admission fee saved: ₹' . number_format($amt, 0);
        header('Location: hostel_fees.php' . ($selectedClass !== '' ? '?class=' . urlencode($selectedClass) : '') . '#admission');
        exit;
    }
    if ($action === 'save_structure') {
        $className = trim($_POST['class_name'] ?? '');
        $amounts = $_POST['amount'] ?? [];
        if ($className !== '') {
            saveHostelFeeStructure($pdo, $className, $amounts, $sessionId);
            $_SESSION['success_msg'] = 'Hostel fee structure saved for ' . $className;
            $selectedClass = $className;
        }
        header('Location: hostel_fees.php' . ($selectedClass !== '' ? '?class=' . urlencode($selectedClass) : '') . '#structure');
        exit;
    }
    if ($action === 'fill_monthly') {
        $className = trim($_POST['class_name'] ?? '');
        $monthly = (float) ($_POST['monthly_amount'] ?? 4500);
        if ($className !== '' && $monthly > 0) {
            fillHostelMonthlyFeeForClass($pdo, $className, $monthly, $sessionId);
            $_SESSION['success_msg'] = 'Filled ₹' . number_format($monthly, 0) . '/month for ' . $className . ' (₹' . number_format($monthly * 12, 0) . '/year).';
            $selectedClass = $className;
        }
        header('Location: hostel_fees.php' . ($selectedClass !== '' ? '?class=' . urlencode($selectedClass) : '') . '#structure');
        exit;
    }
    if ($action === 'save_plans') {
        $plansPost = $_POST['plans'] ?? [];
        $toSave = [];
        foreach ($plansPost as $id => $row) {
            $id = (int) $id;
            $count = max(1, (int) ($row['installment_count'] ?? 1));
            $amounts = [];
            for ($i = 1; $i <= $count; $i++) {
                $amounts[] = (float) ($row['amount'][$i] ?? 0);
            }
            $gross = (float) ($row['gross_amount'] ?? 0);
            $discount = (float) ($row['discount_amount'] ?? 0);
            $net = max(0, $gross - $discount);
            if (($row['plan_type'] ?? '') === 'installment' && array_sum($amounts) > 0) {
                $net = array_sum($amounts);
            }
            $toSave[] = [
                'id' => $id,
                'name' => $row['name'] ?? '',
                'installment_count' => $count,
                'gross_amount' => $gross,
                'discount_amount' => $discount,
                'net_amount' => $net,
                'installment_label' => $row['installment_label'] ?? '',
                'amounts' => $amounts,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }
        saveHostelFeePlans($pdo, $toSave, $sessionId);
        $_SESSION['success_msg'] = 'Hostel payment plans saved.';
        header('Location: hostel_fees.php' . ($selectedClass !== '' ? '?class=' . urlencode($selectedClass) : '') . '#plans');
        exit;
    }
}

$hostelPlans = getHostelFeePlans($pdo, $sessionId, false);

function formatHostelFeeInputAmount($amount) {
    $amount = (float) $amount;
    if ($amount <= 0) {
        return '';
    }
    if (abs($amount - round($amount)) < 0.001) {
        return (string) (int) round($amount);
    }
    return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
}

$amountMap = $selectedClass !== '' ? getHostelFeeAmountMap($pdo, $selectedClass, $sessionId) : [];
$structureTotal = array_sum($amountMap);
$currentMonth = (int) date('n');
$configuredClassCount = count($classSummaries);
$planCount = count($hostelPlans);

require_once 'includes/header.php';
?>
<div class="hfs-page">
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-cyan"><i class="fas fa-bed"></i></div>
        <div class="content-top-title">
            <h2>Hostel Fee Structure</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="hostel.php">Hostel</a>
                <i class="fas fa-chevron-right"></i>
                <span>Fee Structure</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="hostel_fee_collect.php" class="btn-header-action btn-header-primary"><i class="fas fa-hand-holding-usd"></i> Collect Hostel Fee</a>
        <a href="hostel.php" class="btn-header-action btn-header-outline"><i class="fas fa-door-open"></i> Allotment</a>
    </div>
</div>

<div class="fs-hero hfs-hero">
    <div class="fs-hero-main">
        <p class="fs-hero-label"><i class="fas fa-bed"></i> Hostel fees · session setup</p>
        <h3><?php echo htmlspecialchars($session['name'] ?? 'Current Session'); ?></h3>
        <p>Admission fee, payment plans, and class-wise monthly hostel charges.</p>
    </div>
    <div class="fs-hero-stats">
        <div class="fs-hero-stat is-highlight">
            <span>Admission Fee</span>
            <strong>₹<?php echo number_format($admissionFeeAmount, 0); ?></strong>
        </div>
        <div class="fs-hero-stat">
            <span>Plans</span>
            <strong><?php echo $planCount; ?></strong>
        </div>
        <div class="fs-hero-stat">
            <span>Classes Set</span>
            <strong><?php echo $configuredClassCount; ?>/<?php echo count($class_options); ?></strong>
        </div>
    </div>
</div>

<div class="fs-quick-links hfs-quick-links">
    <a href="#admission" class="fs-quick-link"><i class="fas fa-id-card"></i><span>Admission Fee</span></a>
    <a href="#plans" class="fs-quick-link"><i class="fas fa-layer-group"></i><span>Payment Plans</span></a>
    <a href="#classes" class="fs-quick-link"><i class="fas fa-school"></i><span>Class Monthly Fee</span></a>
    <a href="hostel_fee_collect.php" class="fs-quick-link"><i class="fas fa-hand-holding-usd"></i><span>Collect Fee</span></a>
</div>

<div class="form-section-card section-mb fs-heads-card" id="admission">
    <div class="fs-card-head">
        <div class="fs-card-head-icon hfs-icon"><i class="fas fa-id-card"></i></div>
        <div class="fs-card-head-text">
            <h4>One-time Hostel Admission Fee</h4>
            <p>Charged once when a student is allotted hostel — separate from monthly / installment fees</p>
        </div>
        <span class="fs-head-count hfs-count">₹<?php echo number_format($admissionFeeAmount, 0); ?></span>
    </div>
    <form method="POST" class="hfs-admission-form">
        <input type="hidden" name="action" value="save_admission_fee">
        <div class="hfs-admission-grid">
            <div class="hfs-admission-preview">
                <span class="hfs-admission-kicker">Current</span>
                <strong>₹<?php echo number_format($admissionFeeAmount, 0); ?></strong>
                <em>one-time per student</em>
            </div>
            <div class="form-field">
                <label for="hostel_admission_fee"><i class="fas fa-rupee-sign"></i> Admission Fee Amount</label>
                <input type="number" step="0.01" min="0" id="hostel_admission_fee" name="hostel_admission_fee" class="form-input"
                       value="<?php echo htmlspecialchars(formatHostelFeeInputAmount($admissionFeeAmount) ?: '1000'); ?>" required>
            </div>
            <div class="form-field hfs-admission-actions">
                <label>&nbsp;</label>
                <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save Admission Fee</button>
            </div>
        </div>
    </form>
</div>

<div class="form-section-card section-mb fs-heads-card" id="plans">
    <div class="fs-card-head">
        <div class="fs-card-head-icon hfs-icon"><i class="fas fa-layer-group"></i></div>
        <div class="fs-card-head-text">
            <h4>Hostel Payment Plans</h4>
            <p>Monthly ₹4,500 · Annual ₹54,000 · Installment options with discount</p>
        </div>
        <span class="fs-head-count hfs-count"><?php echo $planCount; ?> plans</span>
    </div>
    <form method="POST" class="hfs-plans-form">
        <input type="hidden" name="action" value="save_plans">
        <div class="hfs-plan-grid">
            <?php foreach ($hostelPlans as $plan):
                $pid = (int) $plan['id'];
                $count = (int) $plan['installment_count'];
                $amounts = $plan['amounts'] ?? [];
                $net = (float) $plan['net_amount'];
                $gross = (float) $plan['gross_amount'];
                $disc = (float) $plan['discount_amount'];
                $isMonthly = ($plan['plan_type'] ?? '') === 'monthly';
            ?>
            <div class="hfs-plan-card<?php echo $isMonthly ? ' is-monthly' : ''; ?>">
                <input type="hidden" name="plans[<?php echo $pid; ?>][plan_type]" value="<?php echo htmlspecialchars($plan['plan_type']); ?>">
                <input type="hidden" name="plans[<?php echo $pid; ?>][installment_count]" value="<?php echo $count; ?>">
                <input type="hidden" name="plans[<?php echo $pid; ?>][sort_order]" value="<?php echo (int) $plan['sort_order']; ?>">
                <input type="hidden" name="plans[<?php echo $pid; ?>][name]" value="<?php echo htmlspecialchars($plan['name']); ?>">

                <div class="hfs-plan-top">
                    <div class="hfs-plan-icon"><i class="fas fa-<?php echo $isMonthly ? 'calendar-alt' : 'coins'; ?>"></i></div>
                    <div>
                        <h5><?php echo htmlspecialchars($plan['name']); ?></h5>
                        <p><?php echo $isMonthly ? 'Month-wise collection' : ($count . ' installment' . ($count === 1 ? '' : 's')); ?></p>
                    </div>
                    <div class="hfs-plan-net">
                        <span>Pay</span>
                        <strong>₹<?php echo number_format($net, 0); ?></strong>
                    </div>
                </div>

                <div class="hfs-plan-fields">
                    <div class="form-field">
                        <label>Label</label>
                        <input type="text" name="plans[<?php echo $pid; ?>][installment_label]" class="form-input"
                               value="<?php echo htmlspecialchars($plan['installment_label'] ?? ''); ?>" placeholder="e.g. 18000×3">
                    </div>
                    <div class="form-field">
                        <label>Gross (₹)</label>
                        <input type="number" step="0.01" min="0" name="plans[<?php echo $pid; ?>][gross_amount]" class="form-input"
                               value="<?php echo htmlspecialchars(formatHostelFeeInputAmount($gross)); ?>">
                    </div>
                    <div class="form-field">
                        <label>Discount (₹)</label>
                        <input type="number" step="0.01" min="0" name="plans[<?php echo $pid; ?>][discount_amount]" class="form-input"
                               value="<?php
                                   $planCode = (string) ($plan['plan_code'] ?? '');
                                   if (in_array($planCode, ['monthly', 'inst_3'], true)) {
                                       echo '0';
                                   } else {
                                       echo htmlspecialchars($disc > 0 ? formatHostelFeeInputAmount($disc) : '0');
                                   }
                               ?>">
                    </div>
                </div>

                <?php if ($isMonthly): ?>
                <div class="hfs-plan-note"><i class="fas fa-info-circle"></i> Uses class monthly structure (₹4,500 × 12)</div>
                <?php else: ?>
                <div class="hfs-plan-installments">
                    <?php for ($i = 1; $i <= $count; $i++): ?>
                    <div class="form-field">
                        <label>Installment #<?php echo $i; ?></label>
                        <input type="number" step="0.01" min="0"
                               name="plans[<?php echo $pid; ?>][amount][<?php echo $i; ?>]"
                               class="form-input"
                               value="<?php echo htmlspecialchars(formatHostelFeeInputAmount($amounts[$i - 1] ?? 0)); ?>">
                    </div>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="fs-structure-foot">
            <div class="fs-foot-note">
                <i class="fas fa-info-circle"></i>
                <strong>3×</strong> ₹18,000 ·
                <strong>2×</strong> ₹27,000 (₹2,000 off) ·
                <strong>1×</strong> full (₹4,000 off)
            </div>
            <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save Plans</button>
        </div>
    </form>
</div>

<div class="form-section-card section-mb fs-class-card" id="classes">
    <div class="fs-card-head">
        <div class="fs-card-head-icon hfs-icon"><i class="fas fa-school"></i></div>
        <div class="fs-card-head-text">
            <h4>Select Class</h4>
            <p>Set monthly hostel fee for each class (only allotted students are charged)</p>
        </div>
        <span class="fs-class-count"><?php echo count($class_options); ?> class<?php echo count($class_options) === 1 ? '' : 'es'; ?></span>
    </div>
    <div class="fs-class-grid hfs-class-grid">
        <?php foreach ($class_options as $c):
            $summary = $classSummaries[$c] ?? null;
            $isActive = $selectedClass === $c;
            $isConfigured = (bool) $summary;
        ?>
        <a href="hostel_fees.php?class=<?php echo urlencode($c); ?>#structure" class="fs-class-pill hfs-class-pill<?php echo $isActive ? ' is-active' : ''; ?><?php echo $isConfigured ? ' is-configured' : ' is-empty'; ?>">
            <div class="fs-class-pill-top">
                <span class="fs-class-pill-icon"><i class="fas fa-bed"></i></span>
                <?php if ($isConfigured): ?>
                <span class="fs-class-pill-status is-set"><i class="fas fa-check-circle"></i> Set</span>
                <?php else: ?>
                <span class="fs-class-pill-status is-pending"><i class="fas fa-circle"></i> Pending</span>
                <?php endif; ?>
            </div>
            <span class="fs-class-pill-name"><?php echo htmlspecialchars($c); ?></span>
            <?php if ($isConfigured): ?>
            <span class="fs-class-pill-amount">₹<?php echo number_format($summary['total'], 0); ?></span>
            <span class="fs-class-pill-meta">annual hostel</span>
            <?php else: ?>
            <span class="fs-class-pill-empty">Not configured</span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php if (!$class_options): ?>
    <div class="fs-empty-note"><i class="fas fa-info-circle"></i> No classes found. Add classes first.</div>
    <?php endif; ?>
</div>

<?php if ($selectedClass !== ''): ?>
<form method="POST" class="form-section-card section-mb fs-structure-card fs-structure-form" id="structure">
    <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($selectedClass); ?>">
    <input type="hidden" name="monthly_amount" value="4500">
    <div class="fs-structure-head">
        <div class="fs-structure-title">
            <div class="fs-structure-icon hfs-icon"><i class="fas fa-calendar-alt"></i></div>
            <div>
                <h4>Monthly Hostel Fee — <?php echo htmlspecialchars($selectedClass); ?></h4>
                <p>Base rate ₹4,500/month = ₹54,000/year. Used for the Monthly plan.</p>
            </div>
        </div>
        <div class="fs-structure-actions">
            <span class="fs-total-pill hfs-total-pill">₹<?php echo number_format($structureTotal, 0); ?> / year</span>
            <button type="submit" name="action" value="fill_monthly" class="btn-header-action btn-header-outline"><i class="fas fa-magic"></i> Fill ₹4500</button>
            <button type="submit" name="action" value="save_structure" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save</button>
        </div>
    </div>

    <div class="fs-month-table-wrap">
        <table class="fs-month-table">
            <thead>
                <tr>
                    <th>Fee</th>
                    <?php foreach ($feeMonthOrder as $m): ?>
                    <th class="fs-month-col<?php echo $m === $currentMonth ? ' is-current' : ''; ?>"><?php echo htmlspecialchars($feeMonthLabels[$m]); ?></th>
                    <?php endforeach; ?>
                    <th>Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr data-head-row>
                    <td>
                        <div class="fs-head-cell">
                            <div class="fs-head-cell-icon tone-cyan"><i class="fas fa-bed"></i></div>
                            <div><strong>Hostel Fee</strong></div>
                        </div>
                    </td>
                    <?php foreach ($feeMonthOrder as $m):
                        $amount = (float) ($amountMap[$m] ?? 0);
                    ?>
                    <td class="fs-month-col<?php echo $m === $currentMonth ? ' is-current' : ''; ?>">
                        <input type="number" step="0.01" min="0"
                               name="amount[<?php echo $m; ?>]"
                               class="fs-month-input"
                               data-month="<?php echo $m; ?>"
                               value="<?php echo htmlspecialchars(formatHostelFeeInputAmount($amount)); ?>"
                               placeholder="0"
                               inputmode="decimal">
                    </td>
                    <?php endforeach; ?>
                    <td class="fs-month-row-total-col"><span class="fs-row-total">₹<?php echo number_format($structureTotal, 0); ?></span></td>
                    <td class="fs-month-actions-col">
                        <div class="fs-row-action-btns">
                            <button type="button" class="fs-row-fill-btn" title="Copy April to all months"><i class="fas fa-arrows-alt-h"></i></button>
                            <button type="button" class="fs-row-chain-btn" title="Copy each month from previous"><i class="fas fa-angle-double-right"></i></button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="fs-structure-foot">
        <div class="fs-foot-note">
            <i class="fas fa-info-circle"></i>
            Only students with an <strong>active hostel allotment</strong> are charged. Receipts use a separate <strong>HRCP</strong> series.
        </div>
        <div class="fs-foot-total">
            <span>Annual total</span>
            <strong>₹<?php echo number_format($structureTotal, 0); ?></strong>
        </div>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.fs-structure-form');
    if (!form) return;
    var monthOrder = <?php echo json_encode(array_values($feeMonthOrder)); ?>;
    var totalEl = form.querySelector('.fs-total-pill');
    var footTotal = form.querySelector('.fs-foot-total strong');
    var rowTotal = form.querySelector('.fs-row-total');

    function parseVal(input) {
        var v = parseFloat(String(input.value || '').replace(/,/g, ''));
        return isNaN(v) ? 0 : v;
    }
    function refresh() {
        var sum = 0;
        form.querySelectorAll('.fs-month-input').forEach(function (inp) { sum += parseVal(inp); });
        var label = '₹' + Math.round(sum).toLocaleString('en-IN');
        if (totalEl) totalEl.textContent = label + ' / year';
        if (footTotal) footTotal.textContent = label;
        if (rowTotal) rowTotal.textContent = label;
    }
    form.querySelectorAll('.fs-month-input').forEach(function (inp) {
        inp.addEventListener('input', refresh);
    });
    var fillBtn = form.querySelector('.fs-row-fill-btn');
    if (fillBtn) fillBtn.addEventListener('click', function () {
        var first = form.querySelector('.fs-month-input[data-month="' + monthOrder[0] + '"]');
        var val = first ? first.value : '';
        form.querySelectorAll('.fs-month-input').forEach(function (inp) { inp.value = val; });
        refresh();
    });
    var chainBtn = form.querySelector('.fs-row-chain-btn');
    if (chainBtn) chainBtn.addEventListener('click', function () {
        for (var i = 1; i < monthOrder.length; i++) {
            var prev = form.querySelector('.fs-month-input[data-month="' + monthOrder[i - 1] + '"]');
            var cur = form.querySelector('.fs-month-input[data-month="' + monthOrder[i] + '"]');
            if (prev && cur) cur.value = prev.value;
        }
        refresh();
    });
});
</script>
<?php else: ?>
<div class="form-section-card fs-pick-class section-mb">
    <div class="fs-pick-class-icon hfs-pick-icon"><i class="fas fa-hand-pointer"></i></div>
    <h4>Select a class above</h4>
    <p>Pick a class to set monthly hostel fee amounts (₹4,500 × 12).</p>
</div>
<?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
