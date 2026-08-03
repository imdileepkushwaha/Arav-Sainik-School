<?php

function resolvePaymentFeeMonth(array $payment): int {
    return paymentRecordFeeMonth($payment);
}

function getFeeMonthFullLabel(int $month): string {
    $full = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];
    return $full[$month] ?? (getFeeMonthLabels()[$month] ?? (string) $month);
}

function getFeeReceiptContext($pdo, array $payment): array {
    $feeMonth = resolvePaymentFeeMonth($payment);
    $paymentDate = trim($payment['payment_date'] ?? '');
    $paymentDateLabel = $paymentDate !== '' ? date('d M Y', strtotime($paymentDate)) : '';
    $paymentCalendarMonth = $paymentDate !== '' ? (int) date('n', strtotime($paymentDate)) : 0;
    $monthLabels = getFeeMonthLabels();

    return [
        'fee_month' => $feeMonth,
        'fee_month_label' => $feeMonth ? getFeeMonthFullLabel($feeMonth) : '',
        'fee_month_short' => $feeMonth ? ($monthLabels[$feeMonth] ?? '') : '',
        'payment_date_label' => $paymentDateLabel,
        'payment_calendar_month_label' => $paymentCalendarMonth ? getFeeMonthFullLabel($paymentCalendarMonth) : '',
        'is_same_month' => $feeMonth > 0 && $feeMonth === $paymentCalendarMonth,
        'breakdown' => getFeeReceiptBreakdownData($pdo, (int) ($payment['student_id'] ?? 0), $payment),
    ];
}

function getFeeReceiptBreakdownData($pdo, $studentId, array $payment = []) {
    $feeMonth = resolvePaymentFeeMonth($payment);
    if ($feeMonth < 1 || $feeMonth > 12) {
        return null;
    }

    $monthBreakdown = getStudentMonthFeeBreakdown($pdo, (int) $studentId, $feeMonth);
    $monthPaid = $monthBreakdown ? (float) $monthBreakdown['month_paid'] : 0.0;
    if ($monthPaid <= 0 && !empty($payment['amount'])) {
        $paidMap = getStudentMonthlyPaymentsMap($pdo, (int) $studentId);
        $monthPaid = (float) ($paidMap[$feeMonth] ?? 0);
    }

    return [
        'session' => getCurrentSession($pdo),
        'fee_month' => $feeMonth,
        'month_label' => getFeeMonthFullLabel($feeMonth),
        'month_label_short' => getFeeMonthLabels()[$feeMonth] ?? (string) $feeMonth,
        'payment_amount' => (float) ($payment['amount'] ?? 0),
        'month_paid' => $monthPaid,
        'month_due' => $monthBreakdown ? (float) $monthBreakdown['month_due'] : 0.0,
        'month_balance' => $monthBreakdown ? (float) $monthBreakdown['month_balance'] : 0.0,
        'head_lines' => $monthBreakdown['head_lines'] ?? [],
        'head_name' => trim($payment['head_name'] ?? '') ?: 'Monthly Fee',
        'payment_method' => trim($payment['payment_method'] ?? ''),
        'payment_date_label' => !empty($payment['payment_date']) ? date('d M Y', strtotime($payment['payment_date'])) : '',
    ];
}

function renderMonthlyFeeReceiptHtml(array $breakdown) {
    if (!$breakdown || empty($breakdown['fee_month'])) {
        return;
    }

    $monthLabel = $breakdown['month_label'];
    $paymentAmount = (float) ($breakdown['payment_amount'] ?? 0);
    $monthPaid = (float) ($breakdown['month_paid'] ?? 0);
    $monthDue = (float) ($breakdown['month_due'] ?? 0);
    $monthBalance = (float) ($breakdown['month_balance'] ?? 0);
    $headLines = $breakdown['head_lines'] ?? [];
    $sessionName = $breakdown['session']['name'] ?? '';
    $pendingAfter = max(0, $monthBalance);
    ?>
    <p class="rc-section-title rc-section-gap"><?php echo htmlspecialchars($monthLabel); ?> Fee Breakup<?php if ($sessionName): ?> · <?php echo htmlspecialchars($sessionName); ?><?php endif; ?></p>

    <?php if ($headLines): ?>
    <table class="rc-table rc-breakdown-table rc-fee-breakup-table">
        <thead>
            <tr>
                <th>Fee Head</th>
                <th class="ta-r">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($headLines as $line): ?>
            <tr>
                <td><?php echo htmlspecialchars($line['head_name']); ?></td>
                <td class="ta-r">Rs. <?php echo number_format((float) $line['due'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td><strong><?php echo htmlspecialchars($monthLabel); ?> total</strong></td>
                <td class="ta-r"><strong>Rs. <?php echo number_format($monthDue, 2); ?></strong></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>

    <table class="rc-table rc-breakdown-table rc-monthly-receipt-table">
        <tbody>
            <tr>
                <td>Already paid (<?php echo htmlspecialchars($monthLabel); ?>)</td>
                <td class="ta-r">Rs. <?php echo number_format(max(0, $monthPaid - $paymentAmount), 2); ?></td>
            </tr>
            <tr class="rc-paid-highlight">
                <td><strong>Paid (This Receipt)</strong></td>
                <td class="ta-r is-clear"><strong>Rs. <?php echo number_format($paymentAmount, 2); ?></strong></td>
            </tr>
            <tr>
                <td>Total paid for <?php echo htmlspecialchars($monthLabel); ?></td>
                <td class="ta-r is-clear">Rs. <?php echo number_format($monthPaid, 2); ?></td>
            </tr>
            <tr>
                <td>Pending for <?php echo htmlspecialchars($monthLabel); ?></td>
                <td class="ta-r<?php echo $pendingAfter > 0 ? ' is-due' : ' is-clear'; ?>"><strong>Rs. <?php echo number_format($pendingAfter, 2); ?></strong></td>
            </tr>
        </tbody>
    </table>
    <?php
}

function renderFeeReceiptBreakdownHtml(?array $breakdown) {
    if (!$breakdown) {
        return;
    }
    renderMonthlyFeeReceiptHtml($breakdown);
}

function feeReceiptBreakdownStyles() {
    return <<<'CSS'
        .rc-breakdown-table tbody td.ta-r,
        .rc-breakdown-table tfoot td.ta-r { text-align: right; font-weight: 700; }
CSS;
}
