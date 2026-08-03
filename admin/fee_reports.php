<?php
$page_title = "Fee Reports";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
$classFilter = trim($_GET['class'] ?? '');
$class_options = getClassOptions($pdo);
$year = (int) ($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

require_once 'includes/header.php';
$defaulters = getFeeDefaulters($pdo, $classFilter);
$monthlyReport = getFeeCollectionMonthlyBreakdown($pdo, $year);
$totalCollected = array_sum(array_column($monthlyReport, 'total'));
$totalReceipts = array_sum(array_column($monthlyReport, 'cnt'));
$maxMonthTotal = max(array_column($monthlyReport, 'total') ?: [0]);
$todayTotal = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE payment_date = CURDATE()")->fetchColumn();
$monthTotal = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE payment_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn();
$defaulterTotal = array_sum(array_column($defaulters, 'balance'));
$recentPayments = getRecentFeePayments($pdo, 10, $year);
$currentMonth = (int) date('n');
$currentYear = (int) date('Y');
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-green"><i class="fas fa-chart-line"></i></div>
        <div class="content-top-title">
            <h2>Fee Reports</h2>
            <p class="content-top-breadcrumb"><a href="fees.php">Fees</a><i class="fas fa-chevron-right"></i><span>Reports</span></p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="fees.php" class="btn-header-action btn-header-outline"><i class="fas fa-cog"></i> Fee Structure</a>
        <a href="fee_collect.php" class="btn-header-action btn-header-primary"><i class="fas fa-rupee-sign"></i> Collect Fee</a>
    </div>
</div>

<div class="fr-hero">
    <div class="fr-hero-main">
        <p class="fr-hero-label"><i class="fas fa-chart-pie"></i> Fee analytics</p>
        <h3>Collection overview — <?php echo $year; ?></h3>
        <p>Track monthly collections, outstanding dues, and recent receipts.</p>
    </div>
    <div class="fr-hero-stats">
        <div class="fr-hero-stat"><span>Today</span><strong>₹<?php echo number_format($todayTotal, 0); ?></strong></div>
        <div class="fr-hero-stat"><span>This month</span><strong>₹<?php echo number_format($monthTotal, 0); ?></strong></div>
        <div class="fr-hero-stat"><span><?php echo $year; ?> total</span><strong>₹<?php echo number_format($totalCollected, 0); ?></strong></div>
        <div class="fr-hero-stat is-danger"><span>Outstanding</span><strong>₹<?php echo number_format($defaulterTotal, 0); ?></strong></div>
    </div>
</div>

<div class="fr-layout">
    <div class="form-section-card fr-collection-card">
        <div class="fr-card-head">
            <div class="fr-card-head-icon"><i class="fas fa-chart-bar"></i></div>
            <div>
                <h4>Monthly collection — <?php echo $year; ?></h4>
                <p><?php echo $totalReceipts; ?> receipt<?php echo $totalReceipts === 1 ? '' : 's'; ?> · ₹<?php echo number_format($totalCollected, 0); ?> collected</p>
            </div>
        </div>
        <?php if ($totalCollected > 0): ?>
        <div class="fr-month-list">
            <?php foreach ($monthlyReport as $row):
                $barPct = $maxMonthTotal > 0 ? round(($row['total'] / $maxMonthTotal) * 100) : 0;
                $isCurrent = ($year === $currentYear && $row['month'] === $currentMonth);
            ?>
            <div class="fr-month-row<?php echo $isCurrent ? ' is-current' : ''; ?><?php echo $row['total'] > 0 ? ' has-data' : ''; ?>">
                <div class="fr-month-label">
                    <span><?php echo $row['label']; ?></span>
                    <?php if ($isCurrent): ?><em>Current</em><?php endif; ?>
                </div>
                <div class="fr-month-bar-wrap">
                    <div class="fr-month-bar"><div class="fr-month-bar-fill" style="width:<?php echo $barPct; ?>%"></div></div>
                </div>
                <div class="fr-month-meta">
                    <strong>₹<?php echo number_format($row['total'], 0); ?></strong>
                    <small><?php echo $row['cnt']; ?> rcpt</small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="fr-empty-collection">
            <div class="fr-empty-icon"><i class="fas fa-receipt"></i></div>
            <h4>No collections in <?php echo $year; ?></h4>
            <p>Fee payments recorded via Collect Fee will appear here month-wise.</p>
            <a href="fee_collect.php" class="btn-header-action btn-header-primary"><i class="fas fa-money-bill-wave"></i> Collect Fee</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="fr-side-stack">
        <div class="form-section-card fr-filter-card">
            <div class="fr-card-head compact">
                <div class="fr-card-head-icon is-teal"><i class="fas fa-filter"></i></div>
                <div><h4>Filters</h4></div>
            </div>
            <form method="GET" class="fr-filter-form">
                <div class="form-field">
                    <label><i class="fas fa-school"></i> Class (defaulters)</label>
                    <select name="class" class="form-input form-select">
                        <option value="">All Classes</option>
                        <?php foreach ($class_options as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $classFilter === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label><i class="fas fa-calendar"></i> Collection year</label>
                    <input type="number" name="year" class="form-input" min="2000" max="2100" value="<?php echo $year; ?>">
                </div>
                <button type="submit" class="btn-header-action btn-header-primary fr-filter-btn"><i class="fas fa-sync-alt"></i> Apply Filters</button>
            </form>
        </div>

        <div class="form-section-card fr-recent-card">
            <div class="fr-card-head compact">
                <div class="fr-card-head-icon is-purple"><i class="fas fa-history"></i></div>
                <div>
                    <h4>Recent payments</h4>
                    <p><?php echo $year === $currentYear ? 'This year' : $year; ?></p>
                </div>
            </div>
            <?php if ($recentPayments): ?>
            <div class="fr-recent-list">
                <?php foreach ($recentPayments as $p):
                    $initials = '';
                    foreach (preg_split('/\s+/', trim($p['name'])) as $part) {
                        if ($part !== '') {
                            $initials .= strtoupper($part[0]);
                        }
                    }
                    $initials = substr($initials, 0, 2) ?: 'S';
                ?>
                <a href="fee_collect.php?student_id=<?php echo (int) $p['student_id']; ?>" class="fr-recent-item">
                    <div class="fr-recent-avatar"><?php echo htmlspecialchars($initials); ?></div>
                    <div class="fr-recent-info">
                        <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                        <span><?php echo htmlspecialchars($p['receipt_no']); ?> · <?php echo date('d M Y', strtotime($p['payment_date'])); ?></span>
                    </div>
                    <div class="fr-recent-amount">₹<?php echo number_format($p['amount'], 0); ?></div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="fr-recent-empty">
                <i class="fas fa-inbox"></i>
                <p>No payments recorded<?php echo $year !== $currentYear ? ' in ' . $year : ' yet'; ?>.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="form-section-card fr-defaulters-card section-mb">
    <div class="fr-defaulters-head">
        <div>
            <h4><i class="fas fa-exclamation-triangle"></i> Fee defaulters</h4>
            <p><?php echo count($defaulters); ?> student<?php echo count($defaulters) === 1 ? '' : 's'; ?> with outstanding balance<?php echo $classFilter !== '' ? ' · ' . htmlspecialchars($classFilter) : ''; ?></p>
        </div>
        <?php if ($defaulterTotal > 0): ?>
        <span class="fr-dues-pill">Total dues ₹<?php echo number_format($defaulterTotal, 0); ?></span>
        <?php endif; ?>
    </div>
    <?php if ($defaulters): ?>
    <div class="table-wrapper">
        <table class="fr-defaulters-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Class</th>
                    <th>Due</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($defaulters as $d):
                $initials = '';
                foreach (preg_split('/\s+/', trim($d['name'])) as $part) {
                    if ($part !== '') {
                        $initials .= strtoupper($part[0]);
                    }
                }
                $initials = substr($initials, 0, 2) ?: 'S';
                $paidPct = $d['total_due'] > 0 ? min(100, (int) round(($d['total_paid'] / $d['total_due']) * 100)) : 0;
            ?>
            <tr>
                <td>
                    <div class="fr-student-cell">
                        <span class="fr-student-avatar"><?php echo htmlspecialchars($initials); ?></span>
                        <div>
                            <strong><?php echo htmlspecialchars($d['name']); ?></strong>
                            <small><?php echo htmlspecialchars($d['ad_no']); ?></small>
                        </div>
                    </div>
                </td>
                <td><span class="fr-class-pill"><?php echo htmlspecialchars($d['class']); ?></span></td>
                <td>₹<?php echo number_format($d['total_due'], 0); ?></td>
                <td>
                    <div class="fr-paid-cell">
                        <span>₹<?php echo number_format($d['total_paid'], 0); ?></span>
                        <div class="fr-mini-bar"><div style="width:<?php echo $paidPct; ?>%"></div></div>
                    </div>
                </td>
                <td><strong class="fr-balance-cell">₹<?php echo number_format($d['balance'], 0); ?></strong></td>
                <td><a href="fee_collect.php?student_id=<?php echo (int) $d['id']; ?>" class="fr-collect-btn"><i class="fas fa-money-bill-wave"></i> Collect</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="fr-defaulters-empty">
        <div class="fr-empty-icon is-success"><i class="fas fa-check-circle"></i></div>
        <h4>No defaulters<?php echo $classFilter !== '' ? ' in ' . htmlspecialchars($classFilter) : ''; ?></h4>
        <p>All students with assigned fees are up to date.</p>
    </div>
    <?php endif; ?>
</div>
<?php require_once 'includes/footer.php'; ?>
