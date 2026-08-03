<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
if (!isset($_SESSION['student_portal_id']) || !$_SESSION['student_portal_id']) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../admin/includes/erp_helpers.php';
require_once __DIR__ . '/../admin/includes/settings_helpers.php';
require_once __DIR__ . '/../admin/includes/fee_receipt_breakdown.php';
require_once __DIR__ . '/../admin/includes/fee_receipt_view.php';

ensureErpSchema($pdo);
ensureSettingsSchema($pdo);

$studentId = (int) $_SESSION['student_portal_id'];
$paymentId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT fp.id, fp.student_id, fp.fee_head_id, fp.amount, fp.payment_date,
            fp.fee_month, fp.fee_month AS payment_fee_month, fp.payment_method, fp.receipt_no,
            fp.session_id, fp.remarks,
            s.name, s.ad_no, s.class, s.section, s.roll, fh.name AS head_name
     FROM fee_payments fp
     INNER JOIN students s ON s.id = fp.student_id
     LEFT JOIN fee_heads fh ON fh.id = fp.fee_head_id
     WHERE fp.id = ? AND fp.student_id = ?"
);
$stmt->execute([$paymentId, $studentId]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) {
    http_response_code(404);
    die('Receipt not found or access denied.');
}

$school = getSchoolProfile($pdo);
$logoUrl = schoolBrandingUrl($school['logo'] ?? '', 'portal');
$brandName = $school['name'] ?: 'School';
$sig = getDefaultAuthoritySignature($pdo);
$sigUrl = schoolBrandingUrl($sig['signature'] ?? '', 'portal');

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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style><?php echo feeReceiptStyles(); ?></style>
</head>
<body>
    <div class="rc-toolbar no-print">
        <h1><i class="fas fa-receipt"></i> Fee Receipt</h1>
        <div class="rc-actions">
            <a href="fees.php" class="rc-btn rc-btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
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
