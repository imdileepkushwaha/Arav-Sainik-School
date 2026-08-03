<?php
$page_title = "Academic Sessions";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_session') {
        $name = trim($_POST['name'] ?? '');
        $start = $_POST['start_date'] ?? null;
        $end = $_POST['end_date'] ?? null;
        if ($name !== '') {
            try {
                $pdo->prepare("INSERT INTO academic_sessions (name, start_date, end_date, is_current) VALUES (?,?,?,0)")
                    ->execute([$name, $start ?: null, $end ?: null]);
                $_SESSION['success_msg'] = 'Session added.';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'Session name already exists.';
            }
        }
    } elseif ($action === 'set_current') {
        $id = (int) $_POST['id'];
        $pdo->exec("UPDATE academic_sessions SET is_current = 0");
        $pdo->prepare("UPDATE academic_sessions SET is_current = 1 WHERE id = ?")->execute([$id]);
        $_SESSION['success_msg'] = 'Current session updated.';
    } elseif ($action === 'delete_session') {
        $id = (int) $_POST['id'];
        $cur = getCurrentSession($pdo);
        if ($cur && (int) $cur['id'] === $id) {
            $_SESSION['error_msg'] = 'Cannot delete the current session.';
        } else {
            $pdo->prepare("DELETE FROM academic_sessions WHERE id = ?")->execute([$id]);
            $_SESSION['success_msg'] = 'Session deleted.';
        }
    }
    header('Location: academic_sessions.php');
    exit;
}

require_once 'includes/header.php';
$sessions = getAllSessions($pdo);
$current = getCurrentSession($pdo);
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-teal"><i class="fas fa-calendar-alt"></i></div>
        <div class="content-top-title">
            <h2>Academic Sessions</h2>
            <p class="content-top-breadcrumb"><a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i><span>Sessions</span></p>
        </div>
    </div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-plus"></i></div>
        <div><h4>Add Session</h4><p>e.g. 2025-26 (April to March)</p></div>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="add_session">
        <div class="form-grid form-grid-3 form-grid-spaced">
            <div class="form-field"><label>Session Name</label><input type="text" name="name" class="form-input" placeholder="2025-26" required></div>
            <div class="form-field"><label>Start Date</label><input type="date" name="start_date" class="form-input"></div>
            <div class="form-field"><label>End Date</label><input type="date" name="end_date" class="form-input"></div>
        </div>
        <div class="form-actions-end"><button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Add Session</button></div>
    </form>
</div>

<div class="table-container">
    <div class="table-toolbar"><strong>All Sessions</strong><span class="toolbar-meta">Current: <?php echo htmlspecialchars($current['name'] ?? '—'); ?></span></div>
    <div class="table-wrapper">
        <table>
            <thead><tr><th>Session</th><th>Start</th><th>End</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($sessions as $s): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></td>
                <td><?php echo displayVal($s['start_date']); ?></td>
                <td><?php echo displayVal($s['end_date']); ?></td>
                <td><?php echo $s['is_current'] ? '<span class="status-badge badge-active">Current</span>' : '<span class="status-badge badge-inactive">Inactive</span>'; ?></td>
                <td>
                    <?php if (!$s['is_current']): ?>
                    <form method="POST" style="display:inline"><input type="hidden" name="action" value="set_current"><input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                    <button type="submit" class="btn-header-action btn-header-outline btn-sm">Set Current</button></form>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete session?');"><input type="hidden" name="action" value="delete_session"><input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                    <button type="submit" class="action-btn delete-btn"><i class="fas fa-trash"></i></button></form>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
