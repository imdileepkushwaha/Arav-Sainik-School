<?php

function feeReceiptStyles(): string {
    return feeReceiptBreakdownStyles() . <<<'CSS'
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: #e8ecf2;
            color: #0f172a;
            font-size: 10px;
            line-height: 1.35;
            padding: 16px 12px 24px;
        }

        .rc-toolbar {
            width: 148mm;
            max-width: 100%;
            margin: 0 auto 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .rc-toolbar h1 { font-size: 0.95rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 6px; }
        .rc-toolbar h1 i { color: #7c3aed; font-size: 0.9rem; }
        .rc-actions { display: flex; gap: 6px; }
        .rc-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 7px 12px; border-radius: 8px; font-size: 0.72rem; font-weight: 700;
            cursor: pointer; border: 1px solid transparent; text-decoration: none; font-family: inherit;
        }
        .rc-btn-primary { background: #7c3aed; color: #fff; }
        .rc-btn-ghost { background: #fff; border-color: #e2e8f0; color: #334155; }

        .rc-receipt {
            width: 148mm;
            max-width: 100%;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #dbe3ef;
            box-shadow: 0 8px 28px rgba(15, 23, 42, 0.1);
            position: relative;
            overflow: hidden;
        }

        .rc-accent { height: 3px; background: linear-gradient(90deg, #7c3aed, #6366f1); }

        .rc-header {
            display: grid;
            grid-template-columns: 38px 1fr auto;
            gap: 8px;
            align-items: center;
            padding: 10px 12px 8px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .rc-logo {
            width: 38px; height: 38px; border-radius: 8px;
            background: #f3e8ff; color: #7c3aed;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; overflow: hidden; flex-shrink: 0;
        }
        .rc-logo img { width: 100%; height: 100%; object-fit: contain; padding: 3px; background: #fff; }
        .rc-school h2 { font-size: 0.95rem; font-weight: 800; line-height: 1.2; color: #0f172a; }
        .rc-school p { font-size: 0.62rem; color: #64748b; margin-top: 1px; }
        .rc-contact { margin-top: 3px; font-size: 0.58rem; color: #64748b; line-height: 1.4; }
        .rc-contact span { display: inline; }
        .rc-contact span + span::before { content: ' · '; color: #cbd5e1; }
        .rc-doc-label .lbl {
            display: inline-block; font-size: 0.55rem; font-weight: 800;
            letter-spacing: 0.06em; text-transform: uppercase;
            color: #7c3aed; background: #f5f3ff; padding: 4px 7px; border-radius: 5px;
            border: 1px solid #ddd6fe; white-space: nowrap;
        }

        .rc-meta {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
            padding: 8px 12px;
            background: #faf9ff;
            border-bottom: 1px solid #eef2ff;
        }
        .rc-meta-item span {
            display: block; font-size: 0.52rem; text-transform: uppercase;
            letter-spacing: 0.04em; color: #94a3b8; font-weight: 700; margin-bottom: 2px;
        }
        .rc-meta-item strong { font-size: 0.68rem; color: #0f172a; font-weight: 700; line-height: 1.25; }
        .rc-fee-month-badge {
            display: inline; padding: 0; border: 0; background: none;
            color: #7c3aed; font-weight: 800; font-size: 0.68rem;
        }
        .rc-fee-month-badge.is-paid-on { color: #334155; font-weight: 700; }
        .rc-fee-month-badge i { display: none; }
        .rc-status {
            display: inline; padding: 0; border: 0; background: none;
            color: #059669; font-weight: 800; font-size: 0.68rem;
        }
        .rc-status i { display: none; }

        .rc-fee-month-note {
            margin: 0; padding: 5px 12px;
            background: #fffbeb; border-bottom: 1px solid #fde68a;
            color: #92400e; font-size: 0.6rem;
        }
        .rc-fee-month-note i { margin-right: 4px; }

        .rc-body { padding: 8px 12px 10px; }

        .rc-section-title {
            font-size: 0.55rem; text-transform: uppercase; letter-spacing: 0.05em;
            color: #94a3b8; font-weight: 800; margin-bottom: 4px;
        }
        .rc-section-gap { margin-top: 10px; }

        .rc-kv-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 4px 10px;
            margin-bottom: 8px;
            padding: 6px 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .rc-field { display: flex; gap: 4px; align-items: baseline; min-width: 0; }
        .rc-field span { font-size: 0.58rem; color: #94a3b8; font-weight: 600; white-space: nowrap; }
        .rc-field span::after { content: ':'; }
        .rc-field strong { font-size: 0.68rem; color: #0f172a; font-weight: 700; overflow: hidden; text-overflow: ellipsis; }

        .rc-table {
            width: 100%; border-collapse: collapse;
            border: 1px solid #e2e8f0; font-size: 0.65rem;
        }
        .rc-table thead th {
            background: #1e1b4b; color: #fff;
            padding: 5px 7px; text-align: left;
            font-size: 0.55rem; font-weight: 700; text-transform: uppercase;
        }
        .rc-table thead th.ta-r { text-align: right; }
        .rc-table tbody td { padding: 5px 7px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .rc-table tbody td.ta-r { text-align: right; font-weight: 700; white-space: nowrap; }
        .rc-table tfoot td {
            padding: 5px 7px; font-weight: 800; font-size: 0.7rem;
            background: #faf9ff; border-top: 1px solid #e2e8f0;
        }
        .rc-table tfoot td.ta-r { text-align: right; color: #7c3aed; }

        .rc-remarks { color: #64748b; font-size: 0.6rem; font-style: italic; }

        .rc-words {
            margin: 6px 0 0; padding: 5px 7px;
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px;
            font-size: 0.6rem; color: #475569;
        }
        .rc-words strong { color: #0f172a; font-weight: 700; }

        .rc-footer {
            display: flex; justify-content: space-between; align-items: flex-end; gap: 10px;
            padding: 8px 12px 10px; border-top: 1px dashed #e2e8f0;
        }
        .rc-note { font-size: 0.55rem; color: #94a3b8; line-height: 1.4; flex: 1; }
        .rc-note i { display: none; }
        .rc-sign { text-align: center; flex-shrink: 0; }
        .rc-sign-img { display: block; max-height: 28px; max-width: 90px; object-fit: contain; margin: 0 auto; }
        .rc-sign-name { font-size: 0.62rem; color: #0f172a; font-weight: 700; }
        .rc-sign-line { width: 90px; border-top: 1px solid #cbd5e1; margin: 3px auto 2px; }
        .rc-sign span { font-size: 0.58rem; color: #64748b; font-weight: 600; }

        .rc-watermark {
            position: absolute; top: 42%; left: 50%;
            transform: translate(-50%, -50%) rotate(-18deg);
            font-size: 3.2rem; font-weight: 800;
            color: rgba(16, 185, 129, 0.05); pointer-events: none; z-index: 0;
        }
        .rc-header, .rc-meta, .rc-body, .rc-footer, .rc-fee-month-note { position: relative; z-index: 1; }

        .rc-breakdown-table tbody td.is-clear,
        .rc-breakdown-table tfoot td.is-clear { color: #059669; }
        .rc-breakdown-table tbody td.is-due,
        .rc-breakdown-table tfoot td.is-due { color: #dc2626; }
        .rc-fee-breakup-table { margin-bottom: 6px; }
        .rc-fee-breakup-table thead th { padding: 4px 7px; font-size: 0.52rem; }
        .rc-fee-breakup-table tbody td { padding: 4px 7px; }
        .rc-fee-breakup-table tfoot td { padding: 4px 7px; font-size: 0.65rem; }
        .rc-monthly-receipt-table tbody td:first-child { color: #64748b; font-weight: 600; font-size: 0.62rem; }
        .rc-monthly-receipt-table .rc-paid-highlight td { background: #f0fdf4; font-size: 0.65rem; }

        @media (max-width: 560px) {
            .rc-meta { grid-template-columns: repeat(2, 1fr); }
            .rc-kv-grid { grid-template-columns: 1fr; }
            .rc-header { grid-template-columns: 34px 1fr; }
            .rc-doc-label { grid-column: 1 / -1; justify-self: start; }
        }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .rc-receipt {
                width: 100%; max-width: none;
                box-shadow: none; border: none;
            }
            @page { size: A5 portrait; margin: 6mm; }
        }
CSS;
}

function renderFeeReceiptContent(array $ctx): void {
    $p = $ctx['payment'];
    $school = $ctx['school'];
    $logoUrl = $ctx['logo_url'];
    $brandName = $ctx['brand_name'];
    $sig = $ctx['signature'];
    $sigUrl = $ctx['signature_url'];
    $section = $ctx['section'];
    $receiptCtx = $ctx['receipt_ctx'];
    $feeMonthLabel = $ctx['fee_month_label'];
    $paymentDateLabel = $ctx['payment_date_label'];
    $paidOnMonthLabel = $ctx['paid_on_month_label'];
    $feeBreakdown = $ctx['breakdown'];
    $displayRemarks = formatPaymentRemarksForDisplay($p['remarks'] ?? '');
    ?>
    <div class="rc-receipt">
        <div class="rc-accent"></div>
        <div class="rc-watermark">PAID</div>

        <div class="rc-header">
            <div class="rc-logo">
                <?php if ($logoUrl): ?>
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo">
                <?php else: ?>
                <i class="fas fa-graduation-cap"></i>
                <?php endif; ?>
            </div>
            <div class="rc-school">
                <h2><?php echo htmlspecialchars($brandName); ?></h2>
                <?php if (!empty($school['tagline'])): ?>
                <p><?php echo htmlspecialchars($school['tagline']); ?></p>
                <?php endif; ?>
                <?php if (!empty($school['address']) || !empty($school['phone']) || !empty($school['email'])): ?>
                <div class="rc-contact">
                    <?php if (!empty($school['address'])): ?><span><?php echo htmlspecialchars($school['address']); ?></span><?php endif; ?>
                    <?php if (!empty($school['phone'])): ?><span><?php echo htmlspecialchars($school['phone']); ?></span><?php endif; ?>
                    <?php if (!empty($school['email'])): ?><span><?php echo htmlspecialchars($school['email']); ?></span><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="rc-doc-label">
                <span class="lbl"><?php echo $feeMonthLabel ? 'Fee Receipt' : 'Receipt'; ?></span>
            </div>
        </div>

        <div class="rc-meta">
            <div class="rc-meta-item"><span>Receipt No</span><strong><?php echo htmlspecialchars($p['receipt_no']); ?></strong></div>
            <div class="rc-meta-item"><span>Fee For</span><strong class="rc-fee-month-badge"><?php echo htmlspecialchars($feeMonthLabel ?: '—'); ?></strong></div>
            <div class="rc-meta-item"><span>Paid On</span><strong class="rc-fee-month-badge is-paid-on"><?php echo htmlspecialchars($paymentDateLabel); ?></strong></div>
            <div class="rc-meta-item"><span>Status</span><strong class="rc-status">Paid</strong></div>
        </div>

        <?php if ($feeMonthLabel && !$receiptCtx['is_same_month'] && $paidOnMonthLabel): ?>
        <p class="rc-fee-month-note"><?php echo htmlspecialchars($feeMonthLabel); ?> fee received on <?php echo htmlspecialchars($paymentDateLabel); ?>.</p>
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
                            <?php echo htmlspecialchars($feeMonthLabel ?: 'Monthly Fee'); ?>
                            <?php if ($p['head_name'] && $p['head_name'] !== 'Monthly Fee'): ?>
                            · <?php echo htmlspecialchars($p['head_name']); ?>
                            <?php endif; ?>
                            <?php if ($displayRemarks !== ''): ?>
                            <br><span class="rc-remarks"><?php echo htmlspecialchars($displayRemarks); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
                        <td class="ta-r">₹<?php echo number_format((float) $p['amount'], 2); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr><td colspan="2">Total Paid</td><td class="ta-r">₹<?php echo number_format((float) $p['amount'], 2); ?></td></tr>
                </tfoot>
            </table>

            <p class="rc-words"><strong>In words:</strong> Rupees <?php echo htmlspecialchars(feeReceiptAmountInWords((float) $p['amount'])); ?> Only</p>

            <?php renderFeeReceiptBreakdownHtml($feeBreakdown); ?>
        </div>

        <div class="rc-footer">
            <p class="rc-note">Computer-generated receipt. No signature required. Please retain for your records.</p>
            <div class="rc-sign">
                <?php if ($sigUrl): ?><img class="rc-sign-img" src="<?php echo htmlspecialchars($sigUrl); ?>" alt="Signature"><?php endif; ?>
                <?php if (!empty($sig['name'])): ?><div class="rc-sign-name"><?php echo htmlspecialchars($sig['name']); ?></div><?php endif; ?>
                <div class="rc-sign-line"></div>
                <span><?php echo !empty($sig['designation']) ? htmlspecialchars($sig['designation']) : 'Authorised Signatory'; ?></span>
            </div>
        </div>
    </div>
    <?php
}

function feeReceiptAmountInWords($number) {
    $number = (int) round($number);
    if ($number === 0) {
        return 'Zero';
    }
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $words = function ($n) use (&$words, $ones, $tens) {
        if ($n < 20) {
            return $ones[$n];
        }
        if ($n < 100) {
            return $tens[intval($n / 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
        }
        if ($n < 1000) {
            return $ones[intval($n / 100)] . ' Hundred' . ($n % 100 ? ' ' . $words($n % 100) : '');
        }
        return '';
    };
    $out = '';
    $crore = intval($number / 10000000);
    $number %= 10000000;
    $lakh = intval($number / 100000);
    $number %= 100000;
    $thousand = intval($number / 1000);
    $number %= 1000;
    $rest = $number;
    if ($crore) {
        $out .= $words($crore) . ' Crore ';
    }
    if ($lakh) {
        $out .= $words($lakh) . ' Lakh ';
    }
    if ($thousand) {
        $out .= $words($thousand) . ' Thousand ';
    }
    if ($rest) {
        $out .= $words($rest);
    }
    return trim($out);
}
