<?php
$page_title = 'Fees';
require_once 'includes/init.php';
$id = (int) $_SESSION['student_portal_id'];
$fee = getStudentFeeSummary($pdo, $id);
require_once 'includes/layout_header.php';

$totalDue = (float) ($fee['total_due'] ?? 0);
$totalPaid = (float) ($fee['total_paid'] ?? 0);
$balance = (float) ($fee['balance'] ?? 0);
$paidPct = $totalDue > 0 ? min(100, round($totalPaid / $totalDue * 100)) : ($totalPaid > 0 ? 100 : 0);
$feeStatus = $fee['fee_status'] ?? '';
$feeItems = $fee['fee_items'] ?? [];
$monthlyBreakdown = $fee['monthly_breakdown'] ?? [];
$monthLabels = getFeeMonthLabels();
$currentMonthLabel = $monthLabels[(int) date('n')] ?? date('M');
$currentMonthDue = (float) ($fee['current_month_due'] ?? 0);
$monthlyFeeStatuses = getStudentMonthlyFeeStatuses($pdo, $id);
$monthsWithDue = array_values(array_filter($monthlyFeeStatuses, static fn($m) => (float) ($m['due'] ?? 0) > 0));
$paidMonthCount = count(array_filter($monthsWithDue, static fn($m) => ($m['status'] ?? '') === 'paid'));
$partialMonthCount = count(array_filter($monthsWithDue, static fn($m) => ($m['status'] ?? '') === 'partial'));
$pendingMonthCount = count(array_filter($monthsWithDue, static fn($m) => ($m['status'] ?? '') === 'pending'));
?>
<div class="sp-stat-grid">
    <div class="sp-stat tone-purple"><div class="sp-stat-icon"><i class="fas fa-file-invoice"></i></div><div class="sp-stat-body"><span>Total Due</span><strong>₹<?php echo number_format($totalDue, 0); ?></strong><small>for this session</small></div></div>
    <div class="sp-stat tone-green"><div class="sp-stat-icon"><i class="fas fa-circle-check"></i></div><div class="sp-stat-body"><span>Paid</span><strong>₹<?php echo number_format($totalPaid, 0); ?></strong><small><?php echo $paidPct; ?>% cleared</small></div></div>
    <div class="sp-stat <?php echo $balance > 0 ? 'tone-red' : 'tone-green'; ?>"><div class="sp-stat-icon"><i class="fas fa-<?php echo $balance > 0 ? 'triangle-exclamation' : 'check-double'; ?>"></i></div><div class="sp-stat-body"><span>Balance</span><strong>₹<?php echo number_format($balance, 0); ?></strong><small><?php echo $balance > 0 ? 'pending payment' : 'all clear'; ?></small></div></div>
    <?php if ($currentMonthDue > 0): ?>
    <div class="sp-stat tone-orange"><div class="sp-stat-icon"><i class="fas fa-calendar-day"></i></div><div class="sp-stat-body"><span><?php echo htmlspecialchars($currentMonthLabel); ?> Due</span><strong>₹<?php echo number_format($currentMonthDue, 0); ?></strong><small>this month</small></div></div>
    <?php endif; ?>
</div>

<section class="sp-fee-overview">
    <div class="sp-fee-overview-hero">
        <div class="sp-fee-overview-hero-main">
            <div class="sp-fee-overview-title">
                <span class="sp-fee-overview-kicker"><i class="fas fa-chart-pie"></i> Fee Overview</span>
                <h2>Session Fee Status</h2>
                <p>
                    <?php if ($balance > 0): ?>
                    ₹<?php echo number_format($balance, 0); ?> pending · clear fees at the school office
                    <?php elseif ($feeStatus === 'no_structure'): ?>
                    Fee structure is not assigned to your class yet
                    <?php else: ?>
                    All session fees are fully cleared. Thank you!
                    <?php endif; ?>
                </p>
            </div>
            <div class="sp-fee-overview-ring" style="--pct: <?php echo (int) $paidPct; ?>">
                <div class="sp-fee-overview-ring-inner">
                    <strong><?php echo $paidPct; ?>%</strong>
                    <span>Paid</span>
                </div>
            </div>
        </div>
        <div class="sp-fee-overview-track">
            <div class="sp-fee-overview-track-fill" style="width:<?php echo $paidPct; ?>%"></div>
        </div>
        <div class="sp-fee-overview-metrics">
            <div class="sp-fee-overview-metric">
                <span>Total Due</span>
                <strong>₹<?php echo number_format($totalDue, 0); ?></strong>
            </div>
            <div class="sp-fee-overview-metric is-green">
                <span>Paid</span>
                <strong>₹<?php echo number_format($totalPaid, 0); ?></strong>
            </div>
            <div class="sp-fee-overview-metric <?php echo $balance > 0 ? 'is-red' : 'is-green'; ?>">
                <span>Balance</span>
                <strong>₹<?php echo number_format($balance, 0); ?></strong>
            </div>
            <?php if ($monthsWithDue): ?>
            <div class="sp-fee-overview-metric">
                <span>Months</span>
                <strong><?php echo $paidMonthCount; ?>/<?php echo count($monthsWithDue); ?> paid</strong>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="sp-fee-overview-body">
        <?php if (!empty($feeItems)): ?>
        <div class="sp-fee-overview-panel">
            <div class="sp-fee-overview-panel-head">
                <h3><i class="fas fa-list-ul"></i> Annual Breakdown</h3>
                <span><?php echo count($feeItems); ?> fee heads</span>
            </div>
            <div class="sp-fee-head-grid">
                <?php foreach ($feeItems as $item): ?>
                <div class="sp-fee-head-chip">
                    <div class="sp-fee-head-chip-ico"><i class="fas fa-tag"></i></div>
                    <div class="sp-fee-head-chip-body">
                        <strong><?php echo htmlspecialchars($item['head_name'] ?? 'Fee'); ?></strong>
                        <span>₹<?php echo number_format((float) $item['amount'], 0); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($monthlyFeeStatuses)): ?>
        <div class="sp-fee-overview-panel sp-fee-overview-panel-wide<?php echo empty($feeItems) ? ' is-full' : ''; ?>">
            <div class="sp-fee-overview-panel-head">
                <h3><i class="fas fa-calendar-alt"></i> Monthly Schedule</h3>
                <div class="sp-fee-month-legend">
                    <?php if ($paidMonthCount): ?><span class="is-paid"><i class="fas fa-circle"></i> <?php echo $paidMonthCount; ?> Paid</span><?php endif; ?>
                    <?php if ($partialMonthCount): ?><span class="is-partial"><i class="fas fa-circle"></i> <?php echo $partialMonthCount; ?> Partial</span><?php endif; ?>
                    <?php if ($pendingMonthCount): ?><span class="is-pending"><i class="fas fa-circle"></i> <?php echo $pendingMonthCount; ?> Pending</span><?php endif; ?>
                </div>
            </div>
            <div class="sp-month-grid">
                <?php foreach ($monthlyFeeStatuses as $ms):
                    if ((float) ($ms['due'] ?? 0) <= 0) {
                        continue;
                    }
                    $isCurrent = (int) $ms['month'] === (int) date('n');
                    $status = $ms['status'] ?? 'pending';
                    $rowClass = 'is-pending';
                    $badgeLabel = 'Pending';
                    if ($status === 'paid') {
                        $rowClass = 'is-paid';
                        $badgeLabel = 'Paid';
                    } elseif ($status === 'partial') {
                        $rowClass = 'is-partial';
                        $badgeLabel = 'Partial';
                    }
                ?>
                <div class="sp-month-card <?php echo $rowClass; ?><?php echo $isCurrent ? ' is-current' : ''; ?>">
                    <div class="sp-month-card-top">
                        <strong><?php echo htmlspecialchars($ms['label']); ?></strong>
                        <span class="sp-badge <?php echo $status === 'paid' ? 'paid' : ($status === 'partial' ? 'important' : 'due'); ?>"><?php echo $badgeLabel; ?></span>
                    </div>
                    <div class="sp-month-card-amt">₹<?php echo number_format((float) $ms['due'], 0); ?></div>
                    <?php if ($status === 'partial'): ?>
                    <small>₹<?php echo number_format((float) $ms['balance'], 0); ?> left</small>
                    <?php elseif ($status === 'paid'): ?>
                    <small><i class="fas fa-check"></i> Cleared</small>
                    <?php else: ?>
                    <small>Due ₹<?php echo number_format((float) $ms['balance'], 0); ?></small>
                    <?php endif; ?>
                    <?php if ($isCurrent): ?><em class="sp-month-card-tag">Current</em><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php elseif (!empty($monthlyBreakdown)): ?>
        <div class="sp-fee-overview-panel sp-fee-overview-panel-wide<?php echo empty($feeItems) ? ' is-full' : ''; ?>">
            <div class="sp-fee-overview-panel-head">
                <h3><i class="fas fa-calendar-alt"></i> Monthly Schedule</h3>
            </div>
            <div class="sp-month-grid">
                <?php foreach ($monthlyBreakdown as $mb):
                    if ($mb['total'] <= 0) continue;
                    $isCurrent = (int) $mb['month'] === (int) date('n');
                ?>
                <div class="sp-month-card is-pending<?php echo $isCurrent ? ' is-current' : ''; ?>">
                    <div class="sp-month-card-top"><strong><?php echo htmlspecialchars($mb['label']); ?></strong></div>
                    <div class="sp-month-card-amt">₹<?php echo number_format($mb['total'], 0); ?></div>
                    <?php if ($isCurrent): ?><em class="sp-month-card-tag">Current</em><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<div class="sp-card sp-fee-history-card">
    <div class="sp-card-head"><h3><i class="fas fa-receipt"></i> Payment History</h3></div>
    <?php if (!empty($fee['payments'])): ?>
    <div class="sp-table-wrap">
            <table class="sp-table">
                <thead><tr><th>Date</th><th>Month</th><th>Receipt</th><th>Head</th><th class="ta-r">Amount</th><th class="ta-c">Method</th><th class="ta-c">Receipt</th></tr></thead>
                <tbody>
                <?php foreach ($fee['payments'] as $p):
                    $payMonth = paymentRecordFeeMonth($p);
                    $payMonthLabel = $payMonth ? ($monthLabels[$payMonth] ?? $payMonth) : '—';
                ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($p['payment_date'])); ?></td>
                    <td><?php echo htmlspecialchars($payMonthLabel); ?></td>
                    <td><strong><?php echo htmlspecialchars($p['receipt_no']); ?></strong></td>
                    <td><?php echo htmlspecialchars($p['head_name'] ?? 'General'); ?></td>
                    <td class="ta-r"><strong>₹<?php echo number_format($p['amount'], 0); ?></strong></td>
                    <td class="ta-c"><span class="sp-badge method"><?php echo htmlspecialchars($p['payment_method']); ?></span></td>
                    <td class="ta-c">
                        <a class="sp-receipt-btn" href="fee_receipt.php?id=<?php echo (int) $p['id']; ?>" target="_blank" title="View / Download receipt"><i class="fas fa-download"></i> Receipt</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
    </div>
    <?php else: ?>
    <div class="sp-empty"><div class="sp-empty-icon"><i class="fas fa-receipt"></i></div><strong>No payments yet</strong><p>Your payment receipts will appear here.</p></div>
    <?php endif; ?>
</div>
<?php require_once 'includes/layout_footer.php'; ?>
