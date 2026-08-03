<?php
$page_title = 'Homework';
$page_subtitle = 'Class ' . ($student['class'] ?? '') . ' · Section ' . ($student['section'] ?? 'A');
require_once 'includes/init.php';
$hwStmt = $pdo->prepare("SELECT * FROM homework WHERE class_name = ? AND section_name = ? ORDER BY due_date DESC, id DESC");
$hwStmt->execute([$student['class'], $student['section'] ?? 'A']);
$homework = $hwStmt->fetchAll(PDO::FETCH_ASSOC);
$today = date('Y-m-d');
require_once 'includes/layout_header.php';
?>
<?php if ($homework): ?>
<div class="sp-list">
    <?php foreach ($homework as $h):
        $due = $h['due_date'] ?? '';
        $overdue = $due && $due < $today;
        $subject = trim($h['subject'] ?? '');
    ?>
    <div class="sp-card sp-hw-card">
        <div class="sp-hw-top">
            <div class="sp-list-ico"><i class="fas fa-book"></i></div>
            <div class="sp-hw-title">
                <strong><?php echo htmlspecialchars($h['title']); ?></strong>
                <?php if ($subject): ?><span class="sp-hw-subject"><?php echo htmlspecialchars($subject); ?></span><?php endif; ?>
            </div>
            <?php if ($due): ?>
            <span class="sp-badge <?php echo $overdue ? 'due' : 'method'; ?>"><i class="far fa-clock"></i> Due <?php echo date('d M', strtotime($due)); ?></span>
            <?php endif; ?>
        </div>
        <?php if (!empty($h['description'])): ?><p class="sp-hw-desc"><?php echo nl2br(htmlspecialchars($h['description'])); ?></p><?php endif; ?>
        <div class="sp-hw-foot"><i class="far fa-calendar"></i> Posted <?php echo date('d M Y', strtotime($h['created_at'])); ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="sp-card">
    <div class="sp-empty"><div class="sp-empty-icon"><i class="fas fa-book-open"></i></div><strong>No homework assigned yet</strong><p>New assignments from your teachers will show up here.</p></div>
</div>
<?php endif; ?>
<?php require_once 'includes/layout_footer.php'; ?>
