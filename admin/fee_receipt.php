<?php
session_start();
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';
require_once 'includes/settings_helpers.php';
require_once 'includes/fee_receipt_breakdown.php';
require_once 'includes/fee_receipt_view.php';
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}
ensureErpSchema($pdo);
ensureSettingsSchema($pdo);
migrateFeeMonthBackfillCleanup($pdo);

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT fp.id, fp.student_id, fp.fee_head_id, fp.amount, fp.payment_date,
            fp.fee_month, fp.fee_month AS payment_fee_month, fp.payment_method, fp.receipt_no,
            fp.session_id, fp.remarks,
            s.name, s.ad_no, s.class, s.section, s.roll, fh.name AS head_name
     FROM fee_payments fp
     INNER JOIN students s ON s.id = fp.student_id
     LEFT JOIN fee_heads fh ON fh.id = fp.fee_head_id
     WHERE fp.id = ?"
);
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) {
    die('Receipt not found.');
}

$school = getSchoolProfile($pdo);
$logoUrl = schoolBrandingUrl($school['logo'] ?? '', 'admin');
$brandName = $school['name'] ?: 'School';
$sig = getDefaultAuthoritySignature($pdo);
$sigUrl = schoolBrandingUrl($sig['signature'] ?? '', 'admin');

$section = trim($p['section'] ?? '') ?: 'A';
$autoPrint = isset($_GET['print']);
$resolvedFeeMonth = paymentRecordFeeMonth($p);
if ($resolvedFeeMonth >= 1 && $resolvedFeeMonth <= 12) {
    $p['fee_month'] = $resolvedFeeMonth;
    $p['payment_fee_month'] = $resolvedFeeMonth;
}
$receiptCtx = getFeeReceiptContext($pdo, $p);
$feeMonthLabel = $receiptCtx['fee_month_label'] ?: ($resolvedFeeMonth ? getFeeMonthFullLabel($resolvedFeeMonth) : '');
$paymentDateLabel = $receiptCtx['payment_date_label'];
$paidOnMonthLabel = $receiptCtx['payment_calendar_month_label'];
$feeBreakdown = $receiptCtx['breakdown'];
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?php echo htmlspecialchars($p['receipt_no']); ?> — <?php echo htmlspecialchars($brandName); ?></title>
    <?php if (!empty($school['favicon'])): ?><link rel="icon" href="<?php echo htmlspecialchars(schoolBrandingUrl($school['favicon'], 'admin')); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style><?php echo feeReceiptStyles(); ?></style>
</head>
<body>
    <div class="rc-toolbar no-print">
        <h1><i class="fas fa-receipt"></i> Fee Receipt</h1>
        <div class="rc-actions">
            <a href="fee_collect.php?student_id=<?php echo (int) $p['student_id']; ?>" class="rc-btn rc-btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
            <button type="button" class="rc-btn rc-btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print A5</button>
        </div>
    </div>

    <?php renderFeeReceiptContent([
        'payment' => $p,
        'school' => $school,
        'logo_url' => $logoUrl,
        'brand_name' => $brandName,
        'signature' => $sig,
        'signature_url' => $sigUrl,
        'section' => $section,
        'receipt_ctx' => $receiptCtx,
        'fee_month_label' => $feeMonthLabel,
        'payment_date_label' => $paymentDateLabel,
        'paid_on_month_label' => $paidOnMonthLabel,
        'breakdown' => $feeBreakdown,
    ]); ?>

    <?php if ($autoPrint): ?>
    <script>window.addEventListener('load', function () { window.print(); });</script>
    <?php endif; ?>
</body>
</html>
