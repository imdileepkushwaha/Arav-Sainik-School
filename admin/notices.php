<?php
$page_title = "Notice Board";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
$adminUser = $_SESSION['admin_username'] ?? 'Admin';

$redirectUrl = 'notices.php';
$editId = (int) ($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
if ($editId) {
    $redirectUrl .= '?edit=' . $editId;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_notice') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if ($title === '' || $body === '') {
            $_SESSION['error_msg'] = 'Title and message are required.';
        } else {
            $pdo->prepare("INSERT INTO notices (title, body, audience, priority, publish_date, created_by) VALUES (?,?,?,?,?,?)")
                ->execute([
                    $title,
                    $body,
                    $_POST['audience'] ?? 'All',
                    $_POST['priority'] ?? 'Normal',
                    $_POST['publish_date'] ?? date('Y-m-d'),
                    $adminUser,
                ]);
            $_SESSION['success_msg'] = 'Notice published.';
            $redirectUrl = 'notices.php';
        }
    } elseif ($action === 'edit_notice') {
        $id = (int) ($_POST['edit_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        if ($id <= 0 || !getNoticeById($pdo, $id)) {
            $_SESSION['error_msg'] = 'Notice not found.';
            $redirectUrl = 'notices.php';
        } elseif ($title === '' || $body === '') {
            $_SESSION['error_msg'] = 'Title and message are required.';
            $redirectUrl = 'notices.php?edit=' . $id;
        } else {
            $pdo->prepare(
                "UPDATE notices SET title = ?, body = ?, audience = ?, priority = ?, publish_date = ? WHERE id = ?"
            )->execute([
                $title,
                $body,
                $_POST['audience'] ?? 'All',
                $_POST['priority'] ?? 'Normal',
                $_POST['publish_date'] ?? date('Y-m-d'),
                $id,
            ]);
            $_SESSION['success_msg'] = 'Notice updated successfully.';
            $redirectUrl = 'notices.php';
        }
    } elseif ($action === 'toggle_status') {
        $id = (int) $_POST['id'];
        $pdo->prepare("UPDATE notices SET status = IF(status='Active','Inactive','Active') WHERE id = ?")->execute([$id]);
        $_SESSION['success_msg'] = 'Notice visibility updated.';
        $redirectUrl = 'notices.php';
    } elseif ($action === 'delete_notice') {
        $pdo->prepare("DELETE FROM notices WHERE id = ?")->execute([(int) $_POST['id']]);
        $_SESSION['success_msg'] = 'Notice deleted.';
        $redirectUrl = 'notices.php';
    }

    header('Location: ' . $redirectUrl);
    exit;
}

require_once 'includes/header.php';

$notices = $pdo->query("SELECT * FROM notices ORDER BY publish_date DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$editNotice = $editId ? getNoticeById($pdo, $editId) : null;
if ($editId && !$editNotice) {
    $editId = 0;
}

$activeCount = count(array_filter($notices, fn($n) => ($n['status'] ?? '') === 'Active'));
$urgentCount = count(array_filter($notices, fn($n) => ($n['priority'] ?? '') === 'Urgent'));
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-orange"><i class="fas fa-bullhorn"></i></div>
        <div class="content-top-title">
            <h2>Notice Board</h2>
            <p class="content-top-breadcrumb"><a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i><span>Notices</span></p>
        </div>
    </div>
</div>

<div class="cls-stat-strip cols-4">
    <div class="cls-stat-card"><div class="cls-stat-icon cls-stat-blue"><i class="fas fa-list"></i></div><div><span>Total</span><strong><?php echo count($notices); ?></strong></div></div>
    <div class="cls-stat-card"><div class="cls-stat-icon cls-stat-green"><i class="fas fa-check-circle"></i></div><div><span>Active</span><strong><?php echo $activeCount; ?></strong></div></div>
    <div class="cls-stat-card"><div class="cls-stat-icon cls-stat-amber"><i class="fas fa-eye-slash"></i></div><div><span>Hidden</span><strong><?php echo count($notices) - $activeCount; ?></strong></div></div>
    <div class="cls-stat-card"><div class="cls-stat-icon" style="background:#fef2f2;color:#dc2626"><i class="fas fa-exclamation-triangle"></i></div><div><span>Urgent</span><strong><?php echo $urgentCount; ?></strong></div></div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-<?php echo $editNotice ? 'pen' : 'plus'; ?>"></i></div>
        <div>
            <h4><?php echo $editNotice ? 'Edit Notice' : 'Publish Notice'; ?></h4>
            <p><?php echo $editNotice ? 'Update title, message, audience, or publish date.' : 'Visible on dashboard and student/teacher portals'; ?></p>
        </div>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="<?php echo $editNotice ? 'edit_notice' : 'add_notice'; ?>">
        <?php if ($editNotice): ?>
        <input type="hidden" name="edit_id" value="<?php echo (int) $editNotice['id']; ?>">
        <?php endif; ?>
        <div class="form-grid form-grid-2 form-grid-spaced">
            <div class="form-field">
                <label>Title</label>
                <input type="text" name="title" class="form-input" required value="<?php echo $editNotice ? htmlspecialchars($editNotice['title']) : ''; ?>">
            </div>
            <div class="form-field">
                <label>Publish Date</label>
                <input type="date" name="publish_date" class="form-input" value="<?php echo $editNotice ? htmlspecialchars($editNotice['publish_date']) : date('Y-m-d'); ?>">
            </div>
            <div class="form-field">
                <label>Audience</label>
                <select name="audience" class="form-input form-select">
                    <?php foreach (['All', 'Students', 'Teachers', 'Staff'] as $aud): ?>
                    <option <?php echo ($editNotice && $editNotice['audience'] === $aud) || (!$editNotice && $aud === 'All') ? 'selected' : ''; ?>><?php echo $aud; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label>Priority</label>
                <select name="priority" class="form-input form-select">
                    <?php foreach (['Normal', 'Important', 'Urgent'] as $pri): ?>
                    <option <?php echo ($editNotice && ($editNotice['priority'] ?? '') === $pri) || (!$editNotice && $pri === 'Normal') ? 'selected' : ''; ?>><?php echo $pri; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field form-field-full">
                <label>Message</label>
                <textarea name="body" class="form-input form-textarea" rows="4" required><?php echo $editNotice ? htmlspecialchars($editNotice['body']) : ''; ?></textarea>
            </div>
        </div>
        <div class="form-actions-end">
            <?php if ($editNotice): ?>
            <a href="notices.php" class="btn-header-action btn-header-outline">Cancel Edit</a>
            <?php endif; ?>
            <button type="submit" class="btn-header-action btn-header-primary">
                <i class="fas fa-<?php echo $editNotice ? 'save' : 'paper-plane'; ?>"></i>
                <?php echo $editNotice ? 'Save Changes' : 'Publish'; ?>
            </button>
        </div>
    </form>
</div>

<div class="table-container">
    <div class="table-toolbar"><strong>All Notices (<?php echo count($notices); ?>)</strong></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Date</th><th>Title</th><th>Audience</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($notices as $n):
                $isActive = ($n['status'] ?? '') === 'Active';
                $preview = mb_strlen($n['body']) > 80 ? mb_substr($n['body'], 0, 80) . '…' : $n['body'];
            ?>
            <tr class="<?php echo !$isActive ? 'nb-row-hidden' : ''; ?><?php echo ($editId && (int) $n['id'] === $editId) ? ' nb-row-editing' : ''; ?>">
                <td><?php echo date('d M Y', strtotime($n['publish_date'])); ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                    <?php if ($preview): ?><br><small class="nb-preview"><?php echo htmlspecialchars($preview); ?></small><?php endif; ?>
                </td>
                <td><span class="nb-audience-badge"><i class="fas fa-users"></i> <?php echo htmlspecialchars($n['audience']); ?></span></td>
                <td><span class="status-badge <?php echo noticePriorityBadgeClass($n['priority'] ?? 'Normal'); ?>"><?php echo htmlspecialchars($n['priority']); ?></span></td>
                <td>
                    <?php if ($isActive): ?>
                    <span class="status-badge badge-active"><i class="fas fa-eye"></i> Active</span>
                    <?php else: ?>
                    <span class="status-badge badge-cancelled"><i class="fas fa-eye-slash"></i> Hidden</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="table-action-btns">
                        <a href="notices.php?edit=<?php echo (int) $n['id']; ?>" class="action-btn edit-btn" title="Edit notice"><i class="fas fa-pen"></i></a>
                        <form method="POST" class="nb-inline-form">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="id" value="<?php echo (int) $n['id']; ?>">
                            <button type="submit" class="action-btn <?php echo $isActive ? 'hide-btn' : 'show-btn'; ?>" title="<?php echo $isActive ? 'Hide notice' : 'Show notice'; ?>">
                                <i class="fas fa-<?php echo $isActive ? 'eye-slash' : 'eye'; ?>"></i>
                            </button>
                        </form>
                        <form method="POST" class="nb-inline-form" onsubmit="return confirm('Delete this notice permanently?');">
                            <input type="hidden" name="action" value="delete_notice">
                            <input type="hidden" name="id" value="<?php echo (int) $n['id']; ?>">
                            <button type="submit" class="action-btn delete-btn" title="Delete notice"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$notices): ?>
            <tr><td colspan="6" style="text-align:center;padding:32px;color:#64748b">No notices yet. Publish one using the form above.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
