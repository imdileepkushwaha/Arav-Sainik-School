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

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT hfp.*, s.name, s.ad_no, s.class, s.section, s.roll
     FROM hostel_fee_payments hfp
     INNER JOIN students s ON s.id = hfp.student_id
     WHERE hfp.id = ?"
);
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) {
    die('Hostel receipt not found.');
}

$school = getSchoolProfile($pdo);
$logoUrl = schoolBrandingUrl($school['logo'] ?? '', 'admin');
$brandName = $school['name'] ?: 'School';
$sig = getDefaultAuthoritySignature($pdo);
$sigUrl = schoolBrandingUrl($sig['signature'] ?? '', 'admin');
$section = trim($p['section'] ?? '') ?: 'A';
$autoPrint = isset($_GET['print']);

$feeMonth = paymentRecordFeeMonth($p);
$feeMonthLabel = $feeMonth ? getFeeMonthFullLabel($feeMonth) : '';
$installmentNo = (int) ($p['installment_no'] ?? 0);
if ($installmentNo < 1 && preg_match('/\[installment:(\d+)\]/', (string) ($p['remarks'] ?? ''), $mInst)) {
    $installmentNo = (int) $mInst[1];
}
$feeForLabel = $installmentNo > 0
    ? ('Installment ' . $installmentNo)
    : ($feeMonthLabel ?: '—');
$planLabel = '';
if (!empty($p['plan_id'])) {
    $planRow = getHostelFeePlanById($pdo, (int) $p['plan_id']);
    if ($planRow) {
        $planLabel = $planRow['name'] . (!empty($planRow['installment_label']) ? ' (' . $planRow['installment_label'] . ')' : '');
    }
}
$paymentDateLabel = !empty($p['payment_date']) ? date('d M Y', strtotime($p['payment_date'])) : '—';
$hostelInfo = getStudentHostelDetails($pdo, (int) $p['student_id']);
$displayRemarks = formatPaymentRemarksForDisplay($p['remarks'] ?? '');
$amountWords = feeReceiptAmountInWords((float) $p['amount']);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Receipt <?php echo htmlspecialchars($p['receipt_no']); ?> — <?php echo htmlspecialchars($brandName); ?></title>
    <?php if (!empty($school['favicon'])): ?><link rel="icon" href="<?php echo htmlspecialchars(schoolBrandingUrl($school['favicon'], 'admin')); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        <?php echo feeReceiptStyles(); ?>
        .rc-toolbar h1 i { color: #0891b2; }
        .rc-btn-primary { background: #0891b2; }
        .rc-accent { background: linear-gradient(90deg, #0e7490, #0891b2); }
        .rc-logo { background: #ecfeff; color: #0891b2; }
        .rc-doc-label .lbl { color: #0e7490; background: #ecfeff; border-color: #a5f3fc; }
        .rc-meta { background: #f0fdfa; border-bottom-color: #ccfbf1; }
        .rc-fee-month-badge { color: #0e7490; }
        .rc-hostel-banner {
            display: flex; gap: 12px; flex-wrap: wrap;
            padding: 8px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
            font-size: 0.68rem;
        }
        .rc-hostel-banner span { color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; display: block; font-size: 0.52rem; }
        .rc-hostel-banner strong { color: #0f172a; font-weight: 700; }
    </style>
</head>
<body>
    <div class="rc-toolbar no-print">
        <h1><i class="fas fa-bed"></i> Hostel Fee Receipt</h1>
        <div class="rc-actions">
            <a href="hostel_fee_collect.php?student_id=<?php echo (int) $p['student_id']; ?>" class="rc-btn rc-btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
            <button type="button" class="rc-btn rc-btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print A5</button>
        </div>
    </div>

    <div class="rc-receipt">
        <div class="rc-accent"></div>
        <div class="rc-watermark">PAID</div>

        <div class="rc-header">
            <div class="rc-logo">
                <?php if ($logoUrl): ?>
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo">
                <?php else: ?>
                <i class="fas fa-bed"></i>
                <?php endif; ?>
            </div>
            <div class="rc-school">
                <h2><?php echo htmlspecialchars($brandName); ?></h2>
                <?php if (!empty($school['tagline'])): ?>
                <p><?php echo htmlspecialchars($school['tagline']); ?></p>
                <?php endif; ?>
                <?php if (!empty($school['address']) || !empty($school['phone'])): ?>
                <div class="rc-contact">
                    <?php if (!empty($school['address'])): ?><span><?php echo htmlspecialchars($school['address']); ?></span><?php endif; ?>
                    <?php if (!empty($school['phone'])): ?><span><?php echo htmlspecialchars($school['phone']); ?></span><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="rc-doc-label">
                <span class="lbl">Hostel Fee Receipt</span>
            </div>
        </div>

        <div class="rc-meta">
            <div class="rc-meta-item"><span>Receipt No</span><strong><?php echo htmlspecialchars($p['receipt_no']); ?></strong></div>
            <div class="rc-meta-item"><span>Fee For</span><strong class="rc-fee-month-badge"><?php echo htmlspecialchars($feeForLabel); ?></strong></div>
            <div class="rc-meta-item"><span>Paid On</span><strong><?php echo htmlspecialchars($paymentDateLabel); ?></strong></div>
            <div class="rc-meta-item"><span>Status</span><strong class="rc-status">Paid</strong></div>
        </div>

        <?php if ($hostelInfo): ?>
        <div class="rc-hostel-banner">
            <div><span>Hostel</span><strong><?php echo htmlspecialchars($hostelInfo['hostel_name']); ?></strong></div>
            <div><span>Room</span><strong><?php echo htmlspecialchars($hostelInfo['room_no']); ?><?php echo !empty($hostelInfo['room_type']) ? ' · ' . htmlspecialchars($hostelInfo['room_type']) : ''; ?></strong></div>
        </div>
        <?php endif; ?>

        <div class="rc-body">
            <p class="rc-section-title">Student</p>
            <div class="rc-kv-grid">
                <div class="rc-field"><span>Name</span><strong><?php echo htmlspecialchars($p['name']); ?></strong></div>
                <div class="rc-field"><span>Adm No</span><strong><?php echo htmlspecialchars($p['ad_no']); ?></strong></div>
                <div class="rc-field"><span>Class</span><strong><?php echo htmlspecialchars($p['class']); ?>-<?php echo htmlspecialchars($section); ?></strong></div>
                <div class="rc-field"><span>Roll</span><strong><?php echo htmlspecialchars($p['roll']); ?></strong></div>
            </div>

            <p class="rc-section-title">Payment</p>
            <table class="rc-table">
                <thead>
                    <tr><th>Description</th><th>Mode</th><th class="ta-r">Amount</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            Hostel Fee<?php echo $feeForLabel !== '—' ? ' · ' . htmlspecialchars($feeForLabel) : ''; ?>
                            <?php if ($planLabel !== ''): ?>
                            <div style="font-size:0.58rem;color:#64748b;margin-top:2px"><?php echo htmlspecialchars($planLabel); ?></div>
                            <?php endif; ?>
                            <?php if ($displayRemarks !== ''): ?>
                            <div style="font-size:0.58rem;color:#64748b;margin-top:2px"><?php echo htmlspecialchars($displayRemarks); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['payment_method'] ?: 'Cash'); ?></td>
                        <td class="ta-r">₹<?php echo number_format((float) $p['amount'], 2); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2"><strong>Total Paid</strong></td>
                        <td class="ta-r"><strong>₹<?php echo number_format((float) $p['amount'], 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
            <p class="rc-words"><strong>In words:</strong> Rupees <?php echo htmlspecialchars($amountWords); ?> Only</p>
        </div>

        <div class="rc-footer">
            <p class="rc-note">Computer-generated hostel fee receipt. Please retain for your records.</p>
            <div class="rc-sign">
                <?php if ($sigUrl): ?><img class="rc-sign-img" src="<?php echo htmlspecialchars($sigUrl); ?>" alt="Signature"><?php endif; ?>
                <?php if (!empty($sig['name'])): ?><div class="rc-sign-name"><?php echo htmlspecialchars($sig['name']); ?></div><?php endif; ?>
                <div class="rc-sign-line"></div>
                <span><?php echo !empty($sig['designation']) ? htmlspecialchars($sig['designation']) : 'Authorised Signatory'; ?></span>
            </div>
        </div>
    </div>

    <?php if ($autoPrint): ?>
    <script>window.addEventListener('load', function () { window.print(); });</script>
    <?php endif; ?>
</body>
</html>
