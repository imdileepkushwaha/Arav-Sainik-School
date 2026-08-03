<?php
$page_title = "Examinations";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
$session = getCurrentSession($pdo);
$class_options = getClassOptions($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_exam') {
        $pdo->prepare("INSERT INTO exams (name, exam_type, class_name, session_id, start_date, end_date) VALUES (?,?,?,?,?,?)")
            ->execute([
                trim($_POST['name']),
                trim($_POST['exam_type'] ?? 'Term'),
                trim($_POST['class_name']),
                $session['id'] ?? null,
                $_POST['start_date'] ?: null,
                $_POST['end_date'] ?: null,
            ]);
        $_SESSION['success_msg'] = 'Exam created.';
    } elseif ($action === 'add_subject' && isset($_POST['exam_id'])) {
        $pdo->prepare("INSERT INTO exam_subjects (exam_id, subject_name, max_marks) VALUES (?,?,?)")
            ->execute([(int) $_POST['exam_id'], trim($_POST['subject_name']), (int) ($_POST['max_marks'] ?? 100)]);
        $_SESSION['success_msg'] = 'Subject added.';
    }
    header('Location: exams.php');
    exit;
}

function examTypeMeta($type) {
    switch ($type) {
        case 'Unit Test':
            return ['icon' => 'fa-clipboard-list', 'tone' => 'blue', 'label' => 'Unit Test'];
        case 'Annual':
            return ['icon' => 'fa-graduation-cap', 'tone' => 'purple', 'label' => 'Annual'];
        case 'Pre-Board':
            return ['icon' => 'fa-school', 'tone' => 'orange', 'label' => 'Pre-Board'];
        default:
            return ['icon' => 'fa-file-alt', 'tone' => 'teal', 'label' => 'Term Exam'];
    }
}

require_once 'includes/header.php';
$exams = $pdo->query(
    "SELECT e.*, (SELECT COUNT(*) FROM exam_subjects es WHERE es.exam_id = e.id) AS subject_count
     FROM exams e ORDER BY e.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$subjectsByExam = [];
foreach ($pdo->query("SELECT * FROM exam_subjects ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC) as $sub) {
    $subjectsByExam[$sub['exam_id']][] = $sub;
}

$totalSubjects = array_sum(array_column($exams, 'subject_count'));
$classesUsed = count(array_unique(array_column($exams, 'class_name')));
$typeCounts = [];
foreach ($exams as $e) {
    $type = $e['exam_type'] ?: 'Term';
    $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
}
?>

<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-purple"><i class="far fa-edit"></i></div>
        <div class="content-top-title">
            <h2>Examinations</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i><span>Exams</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="exam_analytics.php" class="btn-header-action btn-header-outline"><i class="fas fa-chart-pie"></i> Analytics</a>
        <a href="marks.php" class="btn-header-action btn-header-primary"><i class="fas fa-pen"></i> Enter Marks</a>
    </div>
</div>

<div class="exm-hero">
    <div class="exm-hero-main">
        <p class="exm-hero-label"><i class="fas fa-clipboard-check"></i> Exam management</p>
        <h3>Create exams &amp; configure subjects</h3>
        <p>Session <?php echo htmlspecialchars($session['name'] ?? '—'); ?> · Set up papers, max marks, then enter results.</p>
    </div>
    <div class="exm-hero-stats">
        <div class="exm-hero-stat"><span>Exams</span><strong><?php echo count($exams); ?></strong></div>
        <div class="exm-hero-stat"><span>Subjects</span><strong><?php echo $totalSubjects; ?></strong></div>
        <div class="exm-hero-stat"><span>Classes</span><strong><?php echo $classesUsed; ?></strong></div>
        <div class="exm-hero-stat"><span>Session</span><strong style="font-size:0.88rem"><?php echo htmlspecialchars($session['name'] ?? '—'); ?></strong></div>
    </div>
</div>

<div class="exm-type-strip">
    <div class="exm-type-card tone-teal"><i class="fas fa-file-alt"></i><strong>Term</strong><span>Mid / half-yearly exams</span></div>
    <div class="exm-type-card tone-blue"><i class="fas fa-clipboard-list"></i><strong>Unit Test</strong><span>Short assessments</span></div>
    <div class="exm-type-card tone-purple"><i class="fas fa-graduation-cap"></i><strong>Annual</strong><span>Final board-style exams</span></div>
    <div class="exm-type-card tone-orange"><i class="fas fa-school"></i><strong>Pre-Board</strong><span>Practice before boards</span></div>
</div>

<div class="form-section-card exm-create-card section-mb">
    <div class="exm-card-head">
        <div class="exm-card-head-icon"><i class="fas fa-plus-circle"></i></div>
        <div>
            <h4>Create New Exam</h4>
            <p>Add an exam for a class, then attach subjects with max marks</p>
        </div>
    </div>
    <form method="POST" class="exm-create-form">
        <input type="hidden" name="action" value="add_exam">
        <div class="form-grid form-grid-2 form-grid-spaced">
            <div class="form-field">
                <label><i class="fas fa-pen"></i> Exam Name</label>
                <input type="text" name="name" class="form-input" placeholder="Half Yearly 2026" required>
            </div>
            <div class="form-field">
                <label><i class="fas fa-tag"></i> Exam Type</label>
                <select name="exam_type" class="form-input form-select">
                    <option>Term</option>
                    <option>Unit Test</option>
                    <option>Annual</option>
                    <option>Pre-Board</option>
                </select>
            </div>
            <div class="form-field">
                <label><i class="fas fa-school"></i> Class</label>
                <select name="class_name" class="form-input form-select" required>
                    <?php foreach ($class_options as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label><i class="fas fa-calendar-alt"></i> Start Date</label>
                <input type="date" name="start_date" class="form-input">
            </div>
            <div class="form-field">
                <label><i class="fas fa-calendar-check"></i> End Date</label>
                <input type="date" name="end_date" class="form-input">
            </div>
        </div>
        <div class="exm-create-actions">
            <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-plus"></i> Create Exam</button>
        </div>
    </form>
</div>

<?php if ($exams): ?>
<div class="exm-list-head">
    <h4><i class="fas fa-list"></i> All Examinations</h4>
    <span><?php echo count($exams); ?> exam<?php echo count($exams) === 1 ? '' : 's'; ?></span>
</div>
<div class="exm-list">
    <?php foreach ($exams as $e):
        $meta = examTypeMeta($e['exam_type']);
        $subjects = $subjectsByExam[$e['id']] ?? [];
        $dateRange = '';
        if ($e['start_date']) {
            $dateRange = date('d M Y', strtotime($e['start_date']));
            if ($e['end_date'] && $e['end_date'] !== $e['start_date']) {
                $dateRange .= ' → ' . date('d M Y', strtotime($e['end_date']));
            }
        }
    ?>
    <div class="form-section-card exm-exam-card">
        <div class="exm-exam-head">
            <div class="exm-exam-icon tone-<?php echo $meta['tone']; ?>"><i class="fas <?php echo $meta['icon']; ?>"></i></div>
            <div class="exm-exam-title">
                <div class="exm-exam-top">
                    <strong><?php echo htmlspecialchars($e['name']); ?></strong>
                    <span class="exm-type-badge tone-<?php echo $meta['tone']; ?>"><?php echo htmlspecialchars($e['exam_type']); ?></span>
                    <span class="exm-class-pill"><i class="fas fa-school"></i> <?php echo htmlspecialchars($e['class_name']); ?></span>
                </div>
                <div class="exm-exam-meta">
                    <?php if ($dateRange): ?><span><i class="fas fa-calendar"></i> <?php echo $dateRange; ?></span><?php endif; ?>
                    <span><i class="fas fa-book"></i> <?php echo (int) $e['subject_count']; ?> subject<?php echo (int) $e['subject_count'] === 1 ? '' : 's'; ?></span>
                    <?php if ($e['status'] === 'Inactive'): ?><span class="exm-inactive-badge">Inactive</span><?php endif; ?>
                </div>
            </div>
            <div class="exm-exam-actions">
                <a href="marks.php?exam_id=<?php echo (int) $e['id']; ?>" class="exm-action-btn is-primary"><i class="fas fa-pen"></i> Enter Marks</a>
                <a href="exam_analytics.php?exam_id=<?php echo (int) $e['id']; ?>" class="exm-action-btn"><i class="fas fa-chart-bar"></i> Analytics</a>
            </div>
        </div>

        <div class="exm-subjects-section">
            <p class="exm-subjects-label">Subjects &amp; max marks</p>
            <?php if ($subjects): ?>
            <div class="exm-subject-chips">
                <?php foreach ($subjects as $sub): ?>
                <span class="exm-subject-chip">
                    <?php echo htmlspecialchars($sub['subject_name']); ?>
                    <em>/ <?php echo (int) $sub['max_marks']; ?></em>
                </span>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="exm-no-subjects"><i class="fas fa-info-circle"></i> No subjects yet — add at least one to enter marks.</p>
            <?php endif; ?>

            <form method="POST" class="exm-add-subject-form">
                <input type="hidden" name="action" value="add_subject">
                <input type="hidden" name="exam_id" value="<?php echo (int) $e['id']; ?>">
                <input type="text" name="subject_name" class="form-input" placeholder="Subject name, e.g. Mathematics" required>
                <input type="number" name="max_marks" class="form-input exm-marks-input" value="100" min="1" max="1000" title="Max marks">
                <button type="submit" class="btn-header-action btn-header-outline"><i class="fas fa-plus"></i> Add Subject</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="form-section-card exm-empty section-mb">
    <div class="exm-empty-icon"><i class="far fa-edit"></i></div>
    <h4>No examinations yet</h4>
    <p>Create your first exam using the form above, then add subjects for each paper.</p>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
