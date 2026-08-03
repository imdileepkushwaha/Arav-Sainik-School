<?php
$page_title = "Import Students";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/student_helpers.php';

ensureStudentSchema($pdo);

$imported = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_file']['name'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    if (($handle = fopen($file, 'r')) !== false) {
        $header = fgetcsv($handle);
        $expected = ['name', 'roll', 'class', 'section', 'dob', 'gender', 'mobile', 'category'];
        $required = ['name', 'roll', 'class', 'dob', 'gender', 'mobile'];
        $header_lower = array_map('strtolower', array_map('trim', $header ?: []));
        $map = [];
        foreach ($expected as $col) {
            $idx = array_search($col, $header_lower, true);
            if ($idx === false && in_array($col, $required, true)) {
                $errors[] = "Missing column: $col";
            } else {
                $map[$col] = $idx;
            }
        }
        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO students (ad_no, name, roll, class, section, dob, gender, mobile, category, status, avatar_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            while (($row = fgetcsv($handle)) !== false) {
                $name = trim($row[$map['name']] ?? '');
                if ($name === '') continue;
                $category = ($map['category'] !== false && isset($row[$map['category']])) ? trim($row[$map['category']]) : 'General';
                $sectionCol = ($map['section'] !== false && isset($row[$map['section']])) ? $row[$map['section']] : '';
                list($className, $section) = parseImportClassValue($row[$map['class']] ?? '', $sectionCol);
                $rowErrors = validateClassAndSection($pdo, $className, $section);
                if (!empty($rowErrors)) {
                    $errors[] = "$name: " . implode(' ', $rowErrors);
                    continue;
                }
                $ad_no = generateAdmissionNo($pdo, $className);
                if ($ad_no === '') {
                    $errors[] = "$name: Could not generate admission number for class \"$className\".";
                    continue;
                }
                try {
                    $stmt->execute([
                        $ad_no, $name,
                        trim($row[$map['roll']] ?? ''),
                        $className,
                        $section,
                        trim($row[$map['dob']] ?? ''),
                        trim($row[$map['gender']] ?? 'Male'),
                        trim($row[$map['mobile']] ?? ''),
                        $category, 'Active', rand(1, 10),
                    ]);
                    $imported++;
                } catch (PDOException $e) {
                    $errors[] = "Failed to import: $name";
                }
            }
        }
        fclose($handle);
        if ($imported > 0) {
            $_SESSION['success_msg'] = "$imported student(s) imported successfully.";
            header('Location: students.php');
            exit;
        }
    }
}

require_once 'includes/header.php';
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-orange"><i class="fas fa-file-import"></i></div>
        <div class="content-top-title">
            <h2>Import Students</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="students.php">Students</a>
                <i class="fas fa-chevron-right"></i>
                <span>Import</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="students.php" class="btn-header-action btn-header-outline"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert-box-error">
    <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
</div>
<?php endif; ?>

<div class="form-section-card">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-docs"><i class="fas fa-file-csv"></i></div>
        <div>
            <h4>Upload CSV File</h4>
            <p>Columns: name, roll, class, section (optional), dob, gender, mobile, category (optional)</p>
        </div>
    </div>
    <form method="POST" enctype="multipart/form-data">
        <div class="photo-upload-area photo-upload-full">
            <div class="photo-upload-content photo-upload-content-full">
                <p>Select a CSV file to import students in bulk</p>
                <label class="photo-upload-btn"><i class="fas fa-upload"></i> Choose CSV
                    <input type="file" name="csv_file" accept=".csv,text/csv" required hidden>
                </label>
            </div>
        </div>
        <div class="form-actions-end">
            <a href="student_import_sample.php" class="btn-header-action btn-header-outline"><i class="fas fa-download"></i> Download Sample</a>
            <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-file-import"></i> Import Students</button>
        </div>
    </form>
</div>
<?php require_once 'includes/footer.php'; ?>
