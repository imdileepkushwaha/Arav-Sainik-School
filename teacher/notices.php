<?php
$page_title = 'Notices';
$page_subtitle = 'Announcements from school administration';
require_once 'includes/init.php';

$notices = getActiveNotices($pdo, 30, 'Teachers');
$urgentCount = 0;
$importantCount = 0;
foreach ($notices as $n) {
    $pri = $n['priority'] ?? 'Normal';
    if ($pri === 'Urgent') {
        $urgentCount++;
    } elseif ($pri === 'Important') {
        $importantCount++;
    }
}

require_once 'includes/layout_header.php';
?>

<div class="tp-tt-hero is-orange">
    <div>
        <h2><i class="fas fa-bullhorn"></i> School Notices</h2>
        <p>Announcements from <?php echo htmlspecialchars($tp_school['name']); ?> administration for teachers.</p>
        <div class="tp-tt-hero-chips">
            <span class="tp-tt-hero-chip"><i class="fas fa-newspaper"></i> <?php echo count($notices); ?> active</span>
            <?php if ($urgentCount): ?><span class="tp-tt-hero-chip"><i class="fas fa-exclamation-triangle"></i> <?php echo $urgentCount; ?> urgent</span><?php endif; ?>
            <?php if ($importantCount): ?><span class="tp-tt-hero-chip"><i class="fas fa-star"></i> <?php echo $importantCount; ?> important</span><?php endif; ?>
        </div>
    </div>
    <div class="tp-tt-hero-actions">
        <a href="dashboard.php" class="tp-tt-hero-btn"><i class="fas fa-home"></i> Dashboard</a>
    </div>
</div>

<div class="tp-stat-grid cols-3">
    <div class="tp-stat-card">
        <div class="tp-stat-icon blue"><i class="fas fa-bullhorn"></i></div>
        <div><span>Active Notices</span><strong><?php echo count($notices); ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
        <div><span>Urgent</span><strong><?php echo $urgentCount; ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon green"><i class="fas fa-school"></i></div>
        <div><span>School</span><strong style="font-size:0.95rem"><?php echo htmlspecialchars($tp_school['name']); ?></strong></div>
    </div>
</div>

<div class="tp-card">
    <div class="tp-card-head">
        <h3><i class="fas fa-bullhorn"></i> School Notices</h3>
        <span class="tp-card-badge"><i class="fas fa-users"></i> For Teachers</span>
    </div>
    <?php if ($notices): ?>
    <div class="tp-notice-list">
        <?php foreach ($notices as $n):
            $priority = $n['priority'] ?? 'Normal';
            $priClass = $priority === 'Urgent' ? 'is-urgent' : ($priority === 'Important' ? 'is-important' : '');
        ?>
        <article class="tp-notice-card <?php echo $priClass; ?>">
            <div class="tp-notice-card-head">
                <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                <?php if ($priority !== 'Normal'): ?>
                <span class="tp-notice-priority"><?php echo htmlspecialchars($priority); ?></span>
                <?php endif; ?>
            </div>
            <div class="tp-notice-body"><?php echo nl2br(htmlspecialchars($n['body'])); ?></div>
            <div class="tp-notice-foot">
                <i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($n['publish_date'])); ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="tp-empty tp-notice-empty-state">
        <div class="tp-empty-icon-wrap"><i class="fas fa-bullhorn"></i></div>
        <h4>No notices right now</h4>
        <p>Check back later for school announcements and updates.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/layout_footer.php'; ?>
