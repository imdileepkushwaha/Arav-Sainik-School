<?php
$page_title = 'Notices';
$page_subtitle = 'Announcements from your school';
require_once 'includes/init.php';
$notices = getActiveNotices($pdo, 30, 'Students');
require_once 'includes/layout_header.php';

function sp_notice_meta($priority) {
    switch ($priority) {
        case 'Urgent': return ['urgent', 'fa-triangle-exclamation', '#dc2626'];
        case 'Important': return ['important', 'fa-star', '#d97706'];
        default: return ['normal', 'fa-bullhorn', '#7c3aed'];
    }
}
?>
<?php if ($notices): ?>
<div class="sp-list">
    <?php foreach ($notices as $n):
        [$pcls, $picon, $pcolor] = sp_notice_meta($n['priority'] ?? 'Normal');
    ?>
    <div class="sp-card sp-notice-card" style="border-left:4px solid <?php echo $pcolor; ?>">
        <div class="sp-notice-top">
            <div class="sp-list-ico" style="background:<?php echo $pcolor; ?>1a;color:<?php echo $pcolor; ?>"><i class="fas <?php echo $picon; ?>"></i></div>
            <div class="sp-notice-title">
                <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                <small><i class="far fa-calendar"></i> <?php echo date('d M Y', strtotime($n['publish_date'])); ?></small>
            </div>
            <?php if (($n['priority'] ?? 'Normal') !== 'Normal'): ?>
            <span class="sp-badge <?php echo $pcls; ?>"><?php echo htmlspecialchars($n['priority']); ?></span>
            <?php endif; ?>
        </div>
        <p class="sp-notice-body"><?php echo nl2br(htmlspecialchars($n['body'])); ?></p>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="sp-card">
    <div class="sp-empty"><div class="sp-empty-icon"><i class="fas fa-bullhorn"></i></div><strong>No notices at this time</strong><p>School announcements will appear here.</p></div>
</div>
<?php endif; ?>
<?php require_once 'includes/layout_footer.php'; ?>
