<?php
$page_title = "Student Documents";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
$studentId = (int) ($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
$student = null;
if ($studentId) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc']) && $student) {
    $docType = trim($_POST['doc_type'] ?? 'Other');
    $upload = uploadStudentDocument($_FILES['document'] ?? [], $studentId, $docType);
    if ($upload === false) {
        $_SESSION['error_msg'] = 'Invalid file. Max 5MB.';
    } elseif ($upload) {
        $pdo->prepare("INSERT INTO student_documents (student_id, doc_type, file_path, original_name) VALUES (?,?,?,?)")
            ->execute([$studentId, $docType, $upload['path'], $upload['original']]);
        $_SESSION['success_msg'] = 'Document uploaded.';
    }
    header('Location: student_documents.php?student_id=' . $studentId);
    exit;
}

if (isset($_GET['delete']) && $studentId) {
    $docId = (int) $_GET['delete'];
    $stmt = $pdo->prepare("SELECT * FROM student_documents WHERE id = ? AND student_id = ?");
    $stmt->execute([$docId, $studentId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($doc) {
        $full = __DIR__ . '/' . $doc['file_path'];
        if (file_exists($full)) @unlink($full);
        $pdo->prepare("DELETE FROM student_documents WHERE id = ?")->execute([$docId]);
        $_SESSION['success_msg'] = 'Document deleted.';
    }
    header('Location: student_documents.php?student_id=' . $studentId);
    exit;
}

require_once 'includes/header.php';
$class_options = getClassOptions($pdo);
$filterName = trim($_GET['name'] ?? '');
$filterAdNo = trim($_GET['ad_no'] ?? '');
$filterClass = trim($_GET['class'] ?? '');
$filterSection = trim($_GET['section'] ?? '');
$sectionOptions = $filterClass !== '' ? getSectionOptions($pdo, $filterClass) : ['A', 'B', 'C', 'D'];
$searched = $filterName !== '' || $filterAdNo !== '' || $filterClass !== '' || $filterSection !== '';
$results = [];
if ($searched) {
    $sql = "SELECT id, ad_no, name, class, section FROM students WHERE 1=1";
    $params = [];
    if ($filterName !== '') {
        $sql .= " AND name LIKE ?";
        $params[] = '%' . $filterName . '%';
    }
    if ($filterAdNo !== '') {
        $sql .= " AND ad_no LIKE ?";
        $params[] = '%' . $filterAdNo . '%';
    }
    if ($filterClass !== '') {
        $sql .= " AND class = ?";
        $params[] = $filterClass;
    }
    if ($filterSection !== '') {
        $sql .= " AND section = ?";
        $params[] = $filterSection;
    }
    $sql .= " ORDER BY class, section, name LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$docs = $student ? getStudentDocuments($pdo, $studentId) : [];
$docTypes = ['Birth Certificate', 'Aadhaar', 'Transfer Certificate', 'Previous Marksheet', 'Other'];
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-purple"><i class="fas fa-folder-open"></i></div>
        <div class="content-top-title">
            <h2>Student Documents</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="students.php">Students</a>
                <i class="fas fa-chevron-right"></i>
                <span>Documents</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="students.php" class="btn-header-action btn-header-outline"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-search"></i></div>
        <div>
            <h4>Find Student</h4>
            <p>Search by name, admission no, class or section</p>
        </div>
    </div>
    <form method="GET" class="category-add-row erp-filter-row-4">
        <div class="form-field">
            <label>Name</label>
            <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($filterName); ?>" placeholder="Student name">
        </div>
        <div class="form-field">
            <label>Admission No</label>
            <input type="text" name="ad_no" class="form-input" value="<?php echo htmlspecialchars($filterAdNo); ?>" placeholder="e.g. AD2026-C1-0001">
        </div>
        <div class="form-field">
            <label>Class</label>
            <select name="class" class="form-input form-select" onchange="this.form.section.value=''; this.form.submit()">
                <option value="">All classes</option>
                <?php foreach ($class_options as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filterClass === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label>Section</label>
            <select name="section" class="form-input form-select">
                <option value="">All sections</option>
                <?php foreach ($sectionOptions as $sec): ?>
                <option value="<?php echo htmlspecialchars($sec); ?>" <?php echo $filterSection === $sec ? 'selected' : ''; ?>><?php echo htmlspecialchars($sec); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field category-add-btn-wrap">
            <label>&nbsp;</label>
            <button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-search"></i> Search</button>
        </div>
    </form>
    <?php if ($searched): ?>
    <div class="erp-search-results student-search-results">
        <?php if ($results): foreach ($results as $r): ?>
        <a href="student_documents.php?student_id=<?php echo $r['id']; ?>" class="erp-search-item student-search-card student-search-link">
            <div class="student-search-main">
                <div class="student-search-avatar"><i class="fas fa-user-graduate"></i></div>
                <div class="student-search-info">
                    <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                    <span><?php echo htmlspecialchars($r['ad_no']); ?></span>
                    <div class="student-search-meta">
                        <span class="student-search-class-pill"><i class="fas fa-school"></i> Class <?php echo htmlspecialchars($r['class']); ?><?php if (!empty($r['section'])): ?> - <?php echo htmlspecialchars($r['section']); ?><?php endif; ?></span>
                    </div>
                </div>
            </div>
            <span class="student-search-go"><i class="fas fa-chevron-right"></i></span>
        </a>
        <?php endforeach; else: ?>
        <div class="tab-empty-state tab-empty-pad-sm">
            <div class="tab-empty-icon"><i class="fas fa-search"></i></div>
            <h3>No students found</h3>
            <p>Try a different name, class or section.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($student): ?>
<div class="student-doc-banner section-mb">
    <div class="student-doc-banner-avatar"><i class="fas fa-user-graduate"></i></div>
    <div class="student-doc-banner-info">
        <h3><?php echo htmlspecialchars($student['name']); ?></h3>
        <p>
            <span><i class="fas fa-id-card"></i> <?php echo htmlspecialchars($student['ad_no']); ?></span>
            <span><i class="fas fa-school"></i> Class <?php echo htmlspecialchars($student['class']); ?><?php if (!empty($student['section'])): ?> - <?php echo htmlspecialchars($student['section']); ?><?php endif; ?></span>
            <span><i class="fas fa-file-alt"></i> <?php echo count($docs); ?> document(s)</span>
        </p>
    </div>
    <a href="student_view.php?id=<?php echo $studentId; ?>" class="btn-header-action btn-header-outline btn-sm"><i class="fas fa-eye"></i> View Profile</a>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-cloud-upload-alt"></i></div>
        <div>
            <h4>Upload Document</h4>
            <p>PDF, JPG, PNG or WebP — maximum 5 MB</p>
        </div>
    </div>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="upload_doc" value="1">
        <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
        <div class="form-grid form-grid-2 form-grid-spaced doc-upload-grid">
            <div class="form-field">
                <label>Document Type</label>
                <select name="doc_type" class="form-input form-select">
                    <?php foreach ($docTypes as $t): ?>
                    <option><?php echo htmlspecialchars($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label>Select File</label>
                <div class="doc-file-input-wrap">
                    <input type="file" name="document" id="doc-file" class="doc-file-input" required accept=".pdf,.jpg,.jpeg,.png,.webp">
                    <label for="doc-file" class="doc-file-label">
                        <i class="fas fa-paperclip"></i>
                        <span id="doc-file-name">Choose file...</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="form-actions-end">
            <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-upload"></i> Upload Document</button>
        </div>
    </form>
</div>

<div class="form-section-card section-mb">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-folder"></i></div>
        <div>
            <h4>Uploaded Documents</h4>
            <p>Click a file to open or download</p>
        </div>
    </div>
    <?php if (empty($docs)): ?>
    <div class="tab-empty-state tab-empty-pad-sm">
        <div class="tab-empty-icon"><i class="fas fa-folder-open"></i></div>
        <h3>No documents yet</h3>
        <p>Upload birth certificate, Aadhaar, TC or other files above.</p>
    </div>
    <?php else: ?>
    <div class="doc-card-grid">
        <?php foreach ($docs as $d):
            $ext = strtolower(pathinfo($d['file_path'], PATHINFO_EXTENSION));
            $isPdf = $ext === 'pdf';
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
        ?>
        <div class="doc-card">
            <div class="doc-card-icon <?php echo $isPdf ? 'doc-icon-pdf' : ($isImage ? 'doc-icon-image' : 'doc-icon-file'); ?>">
                <i class="fas fa-<?php echo $isPdf ? 'file-pdf' : ($isImage ? 'file-image' : 'file'); ?>"></i>
            </div>
            <div class="doc-card-body">
                <span class="doc-card-type"><?php echo htmlspecialchars($d['doc_type']); ?></span>
                <strong class="doc-card-name" title="<?php echo htmlspecialchars($d['original_name'] ?? basename($d['file_path'])); ?>">
                    <?php echo htmlspecialchars($d['original_name'] ?? basename($d['file_path'])); ?>
                </strong>
                <span class="doc-card-date"><i class="fas fa-clock"></i> <?php echo htmlspecialchars($d['uploaded_at']); ?></span>
            </div>
            <div class="doc-card-actions">
                <a href="<?php echo htmlspecialchars($d['file_path']); ?>" target="_blank" class="doc-card-btn doc-card-btn-view" title="Open file"><i class="fas fa-external-link-alt"></i></a>
                <a href="student_documents.php?student_id=<?php echo $studentId; ?>&delete=<?php echo $d['id']; ?>" class="doc-card-btn doc-card-btn-delete btn-delete-confirm" title="Delete" onclick="return confirm('Delete this document?');"><i class="fas fa-trash-alt"></i></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php elseif (!$searched): ?>
<div class="tab-empty-state">
    <div class="tab-empty-icon"><i class="fas fa-folder-open"></i></div>
    <h3>Select a student</h3>
    <p>Search for a student above to upload and manage their documents.</p>
</div>
<?php endif; ?>

<script>
var docFile = document.getElementById('doc-file');
if (docFile) {
    docFile.addEventListener('change', function () {
        var label = document.getElementById('doc-file-name');
        if (label) {
            label.textContent = this.files.length ? this.files[0].name : 'Choose file...';
        }
    });
}
</script>
<?php require_once 'includes/footer.php'; ?>
