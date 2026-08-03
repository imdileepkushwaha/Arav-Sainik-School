<?php
$page_title = 'My Certificates';
require_once 'includes/init.php';

$stmt = $pdo->prepare("SELECT * FROM certificates WHERE student_id = ? ORDER BY id DESC");
$stmt->execute([(int) $student['id']]);
$certs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function sp_cert_meta($type) {
    switch ($type) {
        case 'TC':
            return ['icon' => 'fa-right-from-bracket', 'tone' => 'amber', 'label' => 'Transfer Certificate', 'sub' => 'School Leaving Certificate'];
        case 'Character':
            return ['icon' => 'fa-award', 'tone' => 'purple', 'label' => 'Character Certificate', 'sub' => 'Certificate of Good Conduct'];
        default:
            return ['icon' => 'fa-certificate', 'tone' => 'green', 'label' => 'Bonafide Certificate', 'sub' => 'Certificate of Enrollment'];
    }
}

require_once 'includes/layout_header.php';
?>
<?php if (empty($certs)): ?>
<div class="sp-card">
    <div class="sp-empty"><div class="sp-empty-icon"><i class="fas fa-certificate"></i></div><strong>No certificates issued</strong><p>Certificates issued to you by the school office will appear here for viewing and download.</p></div>
</div>
<?php else: ?>
<div class="sp-cert-grid">
    <?php foreach ($certs as $c): $m = sp_cert_meta($c['cert_type']); ?>
    <div class="sp-cert-card tone-<?php echo $m['tone']; ?>">
        <div class="sp-cert-top">
            <div class="sp-cert-ic"><i class="fas <?php echo $m['icon']; ?>"></i></div>
            <span class="sp-cert-badge">Issued</span>
        </div>
        <h3 class="sp-cert-title"><?php echo htmlspecialchars($m['label']); ?></h3>
        <p class="sp-cert-sub"><?php echo htmlspecialchars($m['sub']); ?></p>
        <div class="sp-cert-meta">
            <span><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($c['certificate_no']); ?></span>
            <span><i class="fas fa-calendar-day"></i> <?php echo date('d M Y', strtotime($c['issue_date'])); ?></span>
        </div>
        <?php if (!empty($c['purpose'])): ?><p class="sp-cert-purpose"><i class="fas fa-circle-info"></i> <?php echo htmlspecialchars($c['purpose']); ?></p><?php endif; ?>
        <a href="certificate_view.php?id=<?php echo (int) $c['id']; ?>" target="_blank" class="sp-cert-btn"><i class="fas fa-eye"></i> View &amp; Download</a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require_once 'includes/layout_footer.php'; ?>
