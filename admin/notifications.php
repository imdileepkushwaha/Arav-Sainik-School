<?php
$page_title = "SMS & WhatsApp Alerts";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';
require_once 'includes/settings_helpers.php';

ensureErpSchema($pdo);
ensureSettingsSchema($pdo);
$class_options = getClassOptions($pdo);
$smsCfg = getSmsSettings($pdo);
$waCfg = getWhatsAppSettings($pdo);
$smtpCfg = getSmtpSettings($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'fee_reminder') {
        $sent = sendFeeReminders($pdo, trim($_POST['class'] ?? ''));
        $_SESSION['success_msg'] = "Fee reminders queued/sent for $sent student(s).";
    } elseif ($action === 'attendance_alert') {
        $date = $_POST['alert_date'] ?? date('Y-m-d');
        $sent = sendAttendanceAlerts($pdo, $date, trim($_POST['class'] ?? ''));
        $_SESSION['success_msg'] = "Attendance alerts sent for $sent student(s).";
    } elseif ($action === 'custom_message') {
        $channel = in_array($_POST['channel'] ?? '', ['SMS', 'WhatsApp', 'Email'], true) ? $_POST['channel'] : 'SMS';
        $mobile = trim($_POST['recipient'] ?? '');
        $msg = trim($_POST['message'] ?? '');
        if ($mobile && $msg) {
            queueNotification($pdo, $channel, $mobile, $msg, null, 'custom');
            $_SESSION['success_msg'] = 'Message sent via ' . $channel . '.';
        } else {
            $_SESSION['error_msg'] = 'Recipient and message are required.';
        }
    }
    header('Location: notifications.php');
    exit;
}

require_once 'includes/header.php';

$logs = $pdo->query("SELECT * FROM notification_logs ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

$statTotal = count($logs);
$statSent = 0;
$statFailed = 0;
$statSms = 0;
$statWa = 0;
foreach ($logs as $row) {
    if ($row['status'] === 'Sent') {
        $statSent++;
    } elseif ($row['status'] === 'Failed') {
        $statFailed++;
    }
    if ($row['channel'] === 'SMS') {
        $statSms++;
    } elseif ($row['channel'] === 'WhatsApp') {
        $statWa++;
    }
}

function notifyTemplateLabel($type) {
    $map = [
        'fee_reminder'       => 'Fee Reminder',
        'attendance_alert'   => 'Attendance Alert',
        'custom'             => 'Custom',
    ];
    return $map[$type] ?? ($type ? ucfirst(str_replace('_', ' ', $type)) : 'General');
}

function notifyChannelClass($channel) {
    $map = ['SMS' => 'notify-badge-sms', 'WhatsApp' => 'notify-badge-wa', 'Email' => 'notify-badge-email'];
    return $map[$channel] ?? 'notify-badge-default';
}

function notifyStatusClass($status) {
    $map = ['Sent' => 'badge-active', 'Failed' => 'badge-inactive', 'Queued' => 'notify-badge-queued'];
    return $map[$status] ?? 'notify-badge-default';
}
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-teal"><i class="fas fa-bell"></i></div>
        <div class="content-top-title">
            <h2>Notifications</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>SMS &amp; WhatsApp</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="settings.php?tab=sms" class="btn-header-action btn-header-outline"><i class="fas fa-cog"></i> Gateway Settings</a>
    </div>
</div>

<div class="notify-stats-grid">
    <div class="notify-stat-card">
        <div class="notify-stat-icon notify-stat-icon-total"><i class="fas fa-paper-plane"></i></div>
        <div class="notify-stat-body">
            <span>Recent Messages</span>
            <strong><?php echo $statTotal; ?></strong>
            <small>Last 100 logs</small>
        </div>
    </div>
    <div class="notify-stat-card">
        <div class="notify-stat-icon notify-stat-icon-sent"><i class="fas fa-check-circle"></i></div>
        <div class="notify-stat-body">
            <span>Delivered</span>
            <strong><?php echo $statSent; ?></strong>
            <small>Status: Sent</small>
        </div>
    </div>
    <div class="notify-stat-card">
        <div class="notify-stat-icon notify-stat-icon-sms"><i class="fas fa-sms"></i></div>
        <div class="notify-stat-body">
            <span>SMS</span>
            <strong><?php echo $statSms; ?></strong>
            <small><?php echo $smsCfg['enabled'] === '1' ? 'Gateway active' : 'Not configured'; ?></small>
        </div>
    </div>
    <div class="notify-stat-card">
        <div class="notify-stat-icon notify-stat-icon-wa"><i class="fab fa-whatsapp"></i></div>
        <div class="notify-stat-body">
            <span>WhatsApp</span>
            <strong><?php echo $statWa; ?></strong>
            <small><?php echo $waCfg['enabled'] === '1' ? 'Gateway active' : 'Not configured'; ?></small>
        </div>
    </div>
</div>

<div class="notify-info-banner">
    <div class="notify-info-icon"><i class="fas fa-info-circle"></i></div>
    <div class="notify-info-text">
        <strong>Before sending bulk alerts</strong>
        <p>Configure SMS, WhatsApp, and Email in <a href="settings.php" class="teal-link">Settings</a>. Enabled gateways will send live messages; otherwise entries are logged only.</p>
    </div>
    <div class="notify-gateway-chips">
        <span class="notify-gateway-chip <?php echo $smsCfg['enabled'] === '1' ? 'is-on' : 'is-off'; ?>"><i class="fas fa-sms"></i> SMS</span>
        <span class="notify-gateway-chip <?php echo $waCfg['enabled'] === '1' ? 'is-on' : 'is-off'; ?>"><i class="fab fa-whatsapp"></i> WhatsApp</span>
        <span class="notify-gateway-chip <?php echo $smtpCfg['enabled'] === '1' ? 'is-on' : 'is-off'; ?>"><i class="fas fa-envelope"></i> Email</span>
    </div>
</div>

<div class="notify-actions-grid">
    <div class="form-section-card notify-action-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-bank"><i class="fas fa-file-invoice-dollar"></i></div>
            <div>
                <h4>Fee Due Reminders</h4>
                <p>Notify parents about outstanding fee balance</p>
            </div>
        </div>
        <form method="POST" class="notify-action-form">
            <input type="hidden" name="action" value="fee_reminder">
            <div class="form-field">
                <label><i class="fas fa-school"></i> Class filter</label>
                <select name="class" class="form-input form-select">
                    <option value="">All classes</option>
                    <?php foreach ($class_options as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <ul class="notify-action-notes">
                <li><i class="fas fa-check"></i> Sends via SMS &amp; WhatsApp</li>
                <li><i class="fas fa-check"></i> Only students with due balance</li>
            </ul>
            <button type="submit" class="btn-header-action btn-header-primary notify-action-btn" onclick="return confirm('Send fee reminders to eligible parents?');">
                <i class="fas fa-paper-plane"></i> Send Fee Reminders
            </button>
        </form>
    </div>

    <div class="form-section-card notify-action-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-medical"><i class="far fa-calendar-times"></i></div>
            <div>
                <h4>Attendance Alerts</h4>
                <p>Alert parents when student is absent or late</p>
            </div>
        </div>
        <form method="POST" class="notify-action-form">
            <input type="hidden" name="action" value="attendance_alert">
            <div class="form-field">
                <label><i class="fas fa-calendar-day"></i> Attendance date</label>
                <input type="date" name="alert_date" class="form-input" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-field">
                <label><i class="fas fa-school"></i> Class filter</label>
                <select name="class" class="form-input form-select">
                    <option value="">All classes</option>
                    <?php foreach ($class_options as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <ul class="notify-action-notes">
                <li><i class="fas fa-check"></i> Absent &amp; Late status included</li>
                <li><i class="fas fa-check"></i> Uses marked attendance records</li>
            </ul>
            <button type="submit" class="btn-header-action btn-header-primary notify-action-btn" onclick="return confirm('Send attendance alerts for this date?');">
                <i class="fas fa-bell"></i> Send Attendance Alerts
            </button>
        </form>
    </div>

    <div class="form-section-card notify-action-card notify-action-card-wide">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-desc"><i class="fas fa-comment-dots"></i></div>
            <div>
                <h4>Custom Message</h4>
                <p>Send a one-off SMS, WhatsApp, or email to any recipient</p>
            </div>
        </div>
        <form method="POST" class="notify-custom-form">
            <input type="hidden" name="action" value="custom_message">
            <div class="notify-custom-grid">
                <div class="form-field">
                    <label><i class="fas fa-broadcast-tower"></i> Channel</label>
                    <select name="channel" class="form-input form-select" id="notifyChannel">
                        <option value="SMS">SMS</option>
                        <option value="WhatsApp">WhatsApp</option>
                        <option value="Email">Email</option>
                    </select>
                </div>
                <div class="form-field">
                    <label><i class="fas fa-phone"></i> <span id="notifyRecipientLabel">Mobile number</span></label>
                    <input type="text" name="recipient" class="form-input" placeholder="9876543210" required>
                </div>
                <div class="form-field notify-custom-message">
                    <label><i class="fas fa-align-left"></i> Message</label>
                    <textarea name="message" class="form-input form-textarea" rows="4" placeholder="Type your message here..." required maxlength="500"></textarea>
                    <span class="field-hint">Max 500 characters recommended for SMS</span>
                </div>
            </div>
            <div class="notify-custom-keys">
                <span class="notify-key" data-text="Dear Parent, ">Dear Parent</span>
                <span class="notify-key" data-text="Fee reminder: ">Fee reminder</span>
                <span class="notify-key" data-text="Attendance update: ">Attendance</span>
                <span class="notify-key" data-text=" - EduDash School">Sign-off</span>
            </div>
            <button type="submit" class="btn-header-action btn-header-outline notify-action-btn">
                <i class="fas fa-paper-plane"></i> Send Message
            </button>
        </form>
    </div>
</div>

<div class="table-container notify-log-table">
    <div class="table-toolbar">
        <div class="toolbar-left">
            <strong><i class="fas fa-history"></i> Notification Log</strong>
            <?php if ($statFailed > 0): ?>
            <span class="notify-failed-pill"><?php echo $statFailed; ?> failed</span>
            <?php endif; ?>
        </div>
        <div class="toolbar-right">
            <div class="toolbar-search notify-log-search">
                <i class="fas fa-search"></i>
                <input type="text" id="notifyLogSearch" placeholder="Search recipient or message...">
            </div>
        </div>
    </div>
    <div class="table-wrapper">
        <?php if ($logs): ?>
        <table>
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>Channel</th>
                    <th>Recipient</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody id="notifyLogBody">
                <?php foreach ($logs as $l):
                    $ts = strtotime($l['created_at']);
                ?>
                <tr class="notify-log-row">
                    <td>
                        <span class="notify-log-date"><?php echo $ts ? date('d M Y', $ts) : '-'; ?></span>
                        <span class="notify-log-time"><?php echo $ts ? date('h:i A', $ts) : ''; ?></span>
                    </td>
                    <td>
                        <span class="notify-channel-badge <?php echo notifyChannelClass($l['channel']); ?>">
                            <?php if ($l['channel'] === 'WhatsApp'): ?><i class="fab fa-whatsapp"></i><?php elseif ($l['channel'] === 'Email'): ?><i class="fas fa-envelope"></i><?php else: ?><i class="fas fa-sms"></i><?php endif; ?>
                            <?php echo htmlspecialchars($l['channel']); ?>
                        </span>
                    </td>
                    <td><code class="notify-recipient"><?php echo htmlspecialchars($l['recipient']); ?></code></td>
                    <td><?php echo htmlspecialchars(notifyTemplateLabel($l['template_type'])); ?></td>
                    <td><span class="status-badge <?php echo notifyStatusClass($l['status']); ?>"><?php echo htmlspecialchars($l['status']); ?></span></td>
                    <td class="notify-msg-cell" title="<?php echo htmlspecialchars($l['message']); ?>"><?php echo htmlspecialchars(mb_strimwidth($l['message'], 0, 90, '…')); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state empty-state-md">
            <i class="fas fa-bell-slash empty-state-icon"></i>
            <h3>No notifications yet</h3>
            <p>Send your first alert using the cards above.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var channel = document.getElementById('notifyChannel');
    var label = document.getElementById('notifyRecipientLabel');
    if (channel && label) {
        channel.addEventListener('change', function () {
            label.textContent = this.value === 'Email' ? 'Email address' : 'Mobile number';
        });
    }

    document.querySelectorAll('.notify-key').forEach(function (key) {
        key.addEventListener('click', function () {
            var ta = document.querySelector('.notify-custom-form textarea');
            if (ta) {
                ta.value += this.getAttribute('data-text') || '';
                ta.focus();
            }
        });
    });

    var search = document.getElementById('notifyLogSearch');
    if (search) {
        search.addEventListener('input', function () {
            var q = this.value.toLowerCase();
            document.querySelectorAll('.notify-log-row').forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none';
            });
        });
    }
})();
</script>
<?php require_once 'includes/footer.php'; ?>
