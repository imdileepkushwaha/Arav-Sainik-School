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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_structure') {
        $className = trim($_POST['class_name'] ?? '');
        $amounts = $_POST['amount'] ?? [];
        if ($className !== '') {
            saveHostelFeeStructure($pdo, $className, $amounts, $sessionId);
            $_SESSION['success_msg'] = 'Hostel fee structure saved for ' . $className;
            $selectedClass = $className;
        }
        header('Location: hostel_fees.php' . ($selectedClass !== '' ? '?class=' . urlencode($selectedClass) : ''));
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
        header('Location: hostel_fees.php' . ($selectedClass !== '' ? '?class=' . urlencode($selectedClass) : ''));
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

require_once 'includes/header.php';
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-purple"><i class="fas fa-bed"></i></div>
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

<div class="fs-quick-links">
    <a href="hostel.php" class="fs-quick-link"><i class="fas fa-bed"></i><span>Rooms &amp; Allotment</span></a>
    <a href="hostel_fee_collect.php" class="fs-quick-link"><i class="fas fa-hand-holding-usd"></i><span>Collect Hostel Fee</span></a>
    <a href="#plans" class="fs-quick-link"><i class="fas fa-layer-group"></i><span>Payment Plans</span></a>
</div>

<div class="form-section-card section-mb" id="plans">
    <div class="fs-card-head">
        <div class="fs-card-head-icon is-blue"><i class="fas fa-layer-group"></i></div>
        <div class="fs-card-head-text">
            <h4>Hostel Payment Plans</h4>
            <p>Monthly ₹4,500 · Annual ₹54,000 · Installment options with discount</p>
        </div>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="save_plans">
        <div class="table-wrapper">
            <table class="fs-month-table">
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th>Label</th>
                        <th>Gross</th>
                        <th>Discount</th>
                        <th>Pay (Net)</th>
                        <th>Installment amounts</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($hostelPlans as $plan):
                    $pid = (int) $plan['id'];
                    $count = (int) $plan['installment_count'];
                    $amounts = $plan['amounts'] ?? [];
                    $net = (float) $plan['net_amount'];
                ?>
                <tr>
                    <td>
                        <input type="hidden" name="plans[<?php echo $pid; ?>][plan_type]" value="<?php echo htmlspecialchars($plan['plan_type']); ?>">
                        <input type="hidden" name="plans[<?php echo $pid; ?>][installment_count]" value="<?php echo $count; ?>">
                        <input type="hidden" name="plans[<?php echo $pid; ?>][sort_order]" value="<?php echo (int) $plan['sort_order']; ?>">
                        <strong><?php echo htmlspecialchars($plan['name']); ?></strong>
                        <div style="font-size:0.78rem;color:#64748b;margin-top:4px">
                            <?php echo $plan['plan_type'] === 'monthly' ? 'Month-wise collection' : $count . ' installment' . ($count === 1 ? '' : 's'); ?>
                        </div>
                        <input type="hidden" name="plans[<?php echo $pid; ?>][name]" value="<?php echo htmlspecialchars($plan['name']); ?>">
                    </td>
                    <td>
                        <input type="text" name="plans[<?php echo $pid; ?>][installment_label]" class="form-input" style="min-width:110px"
                               value="<?php echo htmlspecialchars($plan['installment_label'] ?? ''); ?>" placeholder="e.g. 18000×3">
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" name="plans[<?php echo $pid; ?>][gross_amount]" class="form-input" style="width:110px"
                               value="<?php echo htmlspecialchars(formatHostelFeeInputAmount($plan['gross_amount'])); ?>">
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" name="plans[<?php echo $pid; ?>][discount_amount]" class="form-input" style="width:100px"
                               value="<?php echo htmlspecialchars(formatHostelFeeInputAmount($plan['discount_amount'])); ?>">
                    </td>
                    <td><strong>₹<?php echo number_format($net, 0); ?></strong></td>
                    <td>
                        <?php if ($plan['plan_type'] === 'monthly'): ?>
                        <span style="color:#64748b;font-size:0.85rem">Uses monthly structure (₹4,500 × 12)</span>
                        <?php else: ?>
                        <div style="display:flex;flex-wrap:wrap;gap:8px">
                            <?php for ($i = 1; $i <= $count; $i++): ?>
                            <div class="form-field" style="margin:0;min-width:90px">
                                <label style="font-size:0.72rem">#<?php echo $i; ?></label>
                                <input type="number" step="0.01" min="0"
                                       name="plans[<?php echo $pid; ?>][amount][<?php echo $i; ?>]"
                                       class="form-input"
                                       value="<?php echo htmlspecialchars(formatHostelFeeInputAmount($amounts[$i - 1] ?? 0)); ?>">
                            </div>
                            <?php endfor; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="fs-structure-foot">
            <div class="fs-foot-note">
                <i class="fas fa-info-circle"></i>
                <strong>3×</strong> ₹18,000 (no discount) ·
                <strong>2×</strong> ₹27,000 (₹2,000 off → pay ₹52,000) ·
                <strong>1×</strong> full (₹4,000 off → pay ₹50,000)
            </div>
            <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save Plans</button>
        </div>
    </form>
</div>

<div class="form-section-card section-mb">
    <div class="fs-card-head">
        <div class="fs-card-head-icon"><i class="fas fa-school"></i></div>
        <div class="fs-card-head-text">
            <h4>Select Class</h4>
            <p>Set monthly hostel fee for each class (only allotted students are charged)</p>
        </div>
        <span class="fs-class-count"><?php echo count($class_options); ?> class<?php echo count($class_options) === 1 ? '' : 'es'; ?></span>
    </div>
    <div class="fs-class-grid">
        <?php foreach ($class_options as $c):
            $summary = $classSummaries[$c] ?? null;
            $isActive = $selectedClass === $c;
            $isConfigured = (bool) $summary;
        ?>
        <a href="hostel_fees.php?class=<?php echo urlencode($c); ?>" class="fs-class-pill<?php echo $isActive ? ' is-active' : ''; ?><?php echo $isConfigured ? ' is-configured' : ' is-empty'; ?>">
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
<form method="POST" class="form-section-card section-mb fs-structure-form">
    <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($selectedClass); ?>">
    <input type="hidden" name="monthly_amount" value="4500">
    <div class="fs-card-head">
        <div class="fs-card-head-icon is-blue"><i class="fas fa-calendar-alt"></i></div>
        <div class="fs-card-head-text">
            <h4>Monthly Hostel Fee — <?php echo htmlspecialchars($selectedClass); ?></h4>
            <p>Base rate ₹4,500/month = ₹54,000/year. Used when student chooses the Monthly plan.</p>
        </div>
        <span class="fs-total-pill">₹<?php echo number_format($structureTotal, 0); ?> / year</span>
        <button type="submit" name="action" value="fill_monthly" class="btn-header-action btn-header-outline"><i class="fas fa-magic"></i> Fill ₹4500</button>
        <button type="submit" name="action" value="save_structure" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save</button>
    </div>

    <div class="table-wrapper">
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
                            <div class="fs-head-cell-icon tone-purple"><i class="fas fa-bed"></i></div>
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
    <div class="fs-pick-class-icon"><i class="fas fa-hand-pointer"></i></div>
    <h4>Select a class above</h4>
    <p>Pick a class to set monthly hostel fee amounts.</p>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
