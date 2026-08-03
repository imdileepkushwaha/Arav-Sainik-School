<?php
$page_title = "Transport Fee Structure";
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
$classSummaries = getTransportClassFeeSummaries($pdo, $sessionId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_structure') {
    $className = trim($_POST['class_name'] ?? '');
    $amounts = $_POST['amount'] ?? [];
    if ($className !== '') {
        saveTransportFeeStructure($pdo, $className, $amounts, $sessionId);
        $_SESSION['success_msg'] = 'Transport fee structure saved for ' . $className;
        $selectedClass = $className;
    }
    header('Location: transport_fees.php' . ($selectedClass !== '' ? '?class=' . urlencode($selectedClass) : ''));
    exit;
}

function formatTransportFeeInputAmount($amount) {
    $amount = (float) $amount;
    if ($amount <= 0) {
        return '';
    }
    if (abs($amount - round($amount)) < 0.001) {
        return (string) (int) round($amount);
    }
    return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
}

$amountMap = $selectedClass !== '' ? getTransportFeeAmountMap($pdo, $selectedClass, $sessionId) : [];
$structureTotal = array_sum($amountMap);
$currentMonth = (int) date('n');

require_once 'includes/header.php';
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-teal"><i class="fas fa-bus"></i></div>
        <div class="content-top-title">
            <h2>Transport Fee Structure</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="transport.php">Transport</a>
                <i class="fas fa-chevron-right"></i>
                <span>Fee Structure</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="transport_fee_collect.php" class="btn-header-action btn-header-primary"><i class="fas fa-hand-holding-usd"></i> Collect Transport Fee</a>
        <a href="transport.php" class="btn-header-action btn-header-outline"><i class="fas fa-bus"></i> Routes &amp; Assign</a>
    </div>
</div>

<div class="fs-quick-links">
    <a href="transport.php" class="fs-quick-link"><i class="fas fa-bus"></i><span>Routes &amp; Assign</span></a>
    <a href="transport_fee_collect.php" class="fs-quick-link"><i class="fas fa-hand-holding-usd"></i><span>Collect Transport Fee</span></a>
</div>

<div class="form-section-card section-mb">
    <div class="fs-card-head">
        <div class="fs-card-head-icon"><i class="fas fa-school"></i></div>
        <div class="fs-card-head-text">
            <h4>Select Class</h4>
            <p>Set monthly transport fee for each class (only assigned students are charged). Route fare overrides class amount when set.</p>
        </div>
        <span class="fs-class-count"><?php echo count($class_options); ?> class<?php echo count($class_options) === 1 ? '' : 'es'; ?></span>
    </div>
    <div class="fs-class-grid">
        <?php foreach ($class_options as $c):
            $summary = $classSummaries[$c] ?? null;
            $isActive = $selectedClass === $c;
            $isConfigured = (bool) $summary;
        ?>
        <a href="transport_fees.php?class=<?php echo urlencode($c); ?>" class="fs-class-pill<?php echo $isActive ? ' is-active' : ''; ?><?php echo $isConfigured ? ' is-configured' : ' is-empty'; ?>">
            <div class="fs-class-pill-top">
                <span class="fs-class-pill-icon"><i class="fas fa-bus"></i></span>
                <?php if ($isConfigured): ?>
                <span class="fs-class-pill-status is-set"><i class="fas fa-check-circle"></i> Set</span>
                <?php else: ?>
                <span class="fs-class-pill-status is-pending"><i class="fas fa-circle"></i> Pending</span>
                <?php endif; ?>
            </div>
            <span class="fs-class-pill-name"><?php echo htmlspecialchars($c); ?></span>
            <?php if ($isConfigured): ?>
            <span class="fs-class-pill-amount">₹<?php echo number_format($summary['total'], 0); ?></span>
            <span class="fs-class-pill-meta">annual transport</span>
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
    <input type="hidden" name="action" value="save_structure">
    <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($selectedClass); ?>">
    <div class="fs-card-head">
        <div class="fs-card-head-icon is-blue"><i class="fas fa-calendar-alt"></i></div>
        <div class="fs-card-head-text">
            <h4>Monthly Transport Fee — <?php echo htmlspecialchars($selectedClass); ?></h4>
            <p>Enter amount for each month (Apr → Mar). Use fill buttons to copy amounts.</p>
        </div>
        <span class="fs-total-pill">₹<?php echo number_format($structureTotal, 0); ?> / year</span>
        <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save</button>
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
                            <div class="fs-head-cell-icon tone-purple"><i class="fas fa-bus"></i></div>
                            <div><strong>Transport Fee</strong></div>
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
                               value="<?php echo htmlspecialchars(formatTransportFeeInputAmount($amount)); ?>"
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
            Only students with a <strong>transport route assignment</strong> are charged. If route fare is set, it is used monthly; otherwise class structure amounts apply. Receipts use <strong>TRCP</strong> series.
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
    <p>Pick a class to set monthly transport fee amounts.</p>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
