<?php
$page_title = "Certificates";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_cert'])) {
    $studentId = (int) $_POST['student_id'];
    $type = $_POST['cert_type'] ?? 'Bonafide';
    if (!in_array($type, ['TC', 'Bonafide', 'Character'], true)) {
        $type = 'Bonafide';
    }
    $purpose = trim($_POST['purpose'] ?? '');
    $certNo = generateCertificateNo($pdo, $type);
    $pdo->prepare("INSERT INTO certificates (student_id, cert_type, certificate_no, issue_date, purpose) VALUES (?,?,?,CURDATE(),?)")
        ->execute([$studentId, $type, $certNo, $purpose]);
    $_SESSION['success_msg'] = $type . ' certificate issued: ' . $certNo;
    header('Location: certificate_print.php?id=' . $pdo->lastInsertId());
    exit;
}

function certTypeMeta($type) {
    switch ($type) {
        case 'TC':
            return ['icon' => 'fa-file-export', 'tone' => 'orange', 'label' => 'Transfer Certificate'];
        case 'Character':
            return ['icon' => 'fa-award', 'tone' => 'purple', 'label' => 'Character Certificate'];
        default:
            return ['icon' => 'fa-file-alt', 'tone' => 'teal', 'label' => 'Bonafide Certificate'];
    }
}

require_once 'includes/header.php';
$search = trim($_GET['q'] ?? '');
$results = [];
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT id, ad_no, name, class, section, roll FROM students WHERE status='Active' AND (name LIKE ? OR ad_no LIKE ?) LIMIT 15");
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$issued = $pdo->query(
    "SELECT c.*, s.name, s.ad_no, s.class, s.section FROM certificates c INNER JOIN students s ON s.id = c.student_id ORDER BY c.id DESC LIMIT 50"
)->fetchAll(PDO::FETCH_ASSOC);

$certStats = ['TC' => 0, 'Bonafide' => 0, 'Character' => 0];
foreach ($issued as $c) {
    if (isset($certStats[$c['cert_type']])) {
        $certStats[$c['cert_type']]++;
    }
}
?>

<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-teal"><i class="fas fa-certificate"></i></div>
        <div class="content-top-title">
            <h2>Certificates</h2>
            <p class="content-top-breadcrumb"><a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i><span>Certificates</span></p>
        </div>
    </div>
</div>

<div class="cert-hero">
    <div class="cert-hero-main">
        <p class="cert-hero-label"><i class="fas fa-stamp"></i> Certificate desk</p>
        <h3>Issue &amp; print student certificates</h3>
        <p>Search a student, choose certificate type, and generate an official printable document.</p>
    </div>
    <div class="cert-hero-stats">
        <div class="cert-hero-stat"><span>Total issued</span><strong><?php echo count($issued); ?></strong></div>
        <div class="cert-hero-stat"><span>Bonafide</span><strong><?php echo $certStats['Bonafide']; ?></strong></div>
        <div class="cert-hero-stat"><span>TC</span><strong><?php echo $certStats['TC']; ?></strong></div>
        <div class="cert-hero-stat"><span>Character</span><strong><?php echo $certStats['Character']; ?></strong></div>
    </div>
</div>

<div class="cert-type-strip">
    <div class="cert-type-card tone-teal"><i class="fas fa-file-alt"></i><strong>Bonafide</strong><span>Proof of enrollment</span></div>
    <div class="cert-type-card tone-orange"><i class="fas fa-file-export"></i><strong>Transfer (TC)</strong><span>School leaving certificate</span></div>
    <div class="cert-type-card tone-purple"><i class="fas fa-award"></i><strong>Character</strong><span>Conduct certificate</span></div>
</div>

<div class="form-section-card cert-search-card section-mb">
    <div class="cert-card-head">
        <div class="cert-card-head-icon"><i class="fas fa-search"></i></div>
        <div>
            <h4>Find Student</h4>
            <p>Search by name or admission number to issue a certificate</p>
        </div>
    </div>
    <form method="GET" class="cert-search-form">
        <div class="form-field form-field-grow">
            <label>Student</label>
            <input type="text" name="q" class="form-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name or Admission No">
        </div>
        <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-search"></i> Search</button>
    </form>

    <?php if ($search !== '' && !$results): ?>
    <div class="cert-search-empty">
        <i class="fas fa-user-slash"></i>
        <p>No active students found for &ldquo;<?php echo htmlspecialchars($search); ?>&rdquo;</p>
    </div>
    <?php endif; ?>

    <?php if ($results): ?>
    <div class="cert-results-head">
        <span><i class="fas fa-users"></i> <?php echo count($results); ?> student<?php echo count($results) === 1 ? '' : 's'; ?> found</span>
    </div>
    <div class="cert-issue-list">
        <?php foreach ($results as $r):
            $initials = '';
            foreach (preg_split('/\s+/', trim($r['name'])) as $part) {
                if ($part !== '') {
                    $initials .= strtoupper($part[0]);
                }
            }
            $initials = substr($initials, 0, 2) ?: 'S';
        ?>
        <form method="POST" class="cert-issue-row">
            <input type="hidden" name="issue_cert" value="1">
            <input type="hidden" name="student_id" value="<?php echo (int) $r['id']; ?>">
            <div class="cert-issue-student">
                <span class="cert-issue-avatar"><?php echo htmlspecialchars($initials); ?></span>
                <div>
                    <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                    <small><?php echo htmlspecialchars($r['ad_no']); ?> · Class <?php echo htmlspecialchars($r['class']); ?> (<?php echo htmlspecialchars($r['section'] ?? 'A'); ?>)</small>
                </div>
            </div>
            <div class="cert-issue-fields">
                <select name="cert_type" class="form-input form-select cert-type-select">
                    <option value="Bonafide">Bonafide Certificate</option>
                    <option value="TC">Transfer Certificate (TC)</option>
                    <option value="Character">Character Certificate</option>
                </select>
                <input type="text" name="purpose" class="form-input" placeholder="Purpose (optional)">
            </div>
            <button type="submit" class="btn-header-action btn-header-primary cert-issue-btn"><i class="fas fa-stamp"></i> Issue</button>
        </form>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="form-section-card cert-recent-card section-mb">
    <div class="cert-recent-head">
        <div>
            <h4><i class="fas fa-history"></i> Recently Issued</h4>
            <p>Last <?php echo count($issued); ?> certificates generated</p>
        </div>
        <?php if ($issued): ?>
        <span class="cert-recent-count"><?php echo count($issued); ?> records</span>
        <?php endif; ?>
    </div>

    <?php if ($issued): ?>
    <div class="cert-recent-list">
        <?php foreach ($issued as $c):
            $meta = certTypeMeta($c['cert_type']);
            $initials = '';
            foreach (preg_split('/\s+/', trim($c['name'])) as $part) {
                if ($part !== '') {
                    $initials .= strtoupper($part[0]);
                }
            }
            $initials = substr($initials, 0, 2) ?: 'S';
        ?>
        <div class="cert-recent-item">
            <div class="cert-recent-icon tone-<?php echo $meta['tone']; ?>"><i class="fas <?php echo $meta['icon']; ?>"></i></div>
            <div class="cert-recent-body">
                <div class="cert-recent-top">
                    <strong><?php echo htmlspecialchars($c['name']); ?></strong>
                    <span class="cert-type-badge tone-<?php echo $meta['tone']; ?>"><?php echo htmlspecialchars($c['cert_type']); ?></span>
                </div>
                <div class="cert-recent-meta">
                    <span><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($c['certificate_no']); ?></span>
                    <span><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($c['ad_no']); ?></span>
                    <span><i class="fas fa-school"></i> <?php echo htmlspecialchars($c['class']); ?></span>
                    <span><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($c['issue_date'])); ?></span>
                </div>
                <?php if (!empty($c['purpose'])): ?>
                <p class="cert-recent-purpose"><?php echo htmlspecialchars($c['purpose']); ?></p>
                <?php endif; ?>
            </div>
            <div class="cert-recent-actions">
                <span class="cert-recent-avatar"><?php echo htmlspecialchars($initials); ?></span>
                <a href="certificate_print.php?id=<?php echo (int) $c['id']; ?>" target="_blank" class="cert-print-btn"><i class="fas fa-print"></i> Print</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="cert-recent-empty">
        <div class="cert-empty-icon"><i class="fas fa-certificate"></i></div>
        <h4>No certificates issued yet</h4>
        <p>Search for a student above to issue the first certificate.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
