<?php
$page_title = "Edit Teacher";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/teacher_helpers.php';

ensureTeacherSchema($pdo);
handleClassApiRequest($pdo);

$id = (int) ($_GET['id'] ?? $_POST['teacher_id'] ?? 0);
$teacher = $id ? getTeacherById($pdo, $id) : null;
$class_options = getClassOptions($pdo);
$search = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_teacher']) && $teacher) {
    $form_data = [];
    foreach (array_keys(getDefaultTeacherFormData()) as $key) {
        $form_data[$key] = trim($_POST[$key] ?? '');
    }
    $errors = validateTeacherForm($form_data);

    if (empty($errors)) {
        $photo = $teacher['photo'];
        if (!empty($_FILES['photo']['name'])) {
            $uploaded = uploadTeacherPhoto($_FILES['photo'], $teacher['employee_id']);
            if ($uploaded === false) {
                $_SESSION['error_msg'] = 'Invalid photo.';
                header('Location: teacher_edit.php?id=' . $id);
                exit;
            }
            if ($uploaded) $photo = $uploaded;
        }

        $stmt = $pdo->prepare("UPDATE teachers SET
            name=?, email=?, phone=?, gender=?, dob=?, join_date=?, subject=?, qualification=?, experience_years=?,
            class_assigned=?, section_assigned=?, address=?, city=?, state=?, pincode=?, photo=?, salary=?,
            bank_name=?, bank_account=?, ifsc_code=?, status=?, description=? WHERE id=?");
        $stmt->execute([
            $form_data['name'], $form_data['email'], $form_data['phone'], $form_data['gender'],
            $form_data['dob'] ?: null, $form_data['join_date'] ?: null,
            $form_data['subject'], $form_data['qualification'], $form_data['experience_years'],
            $form_data['class_assigned'] ?: null, $form_data['section_assigned'] ?: null,
            $form_data['address'], $form_data['city'], $form_data['state'], $form_data['pincode'],
            $photo, $form_data['salary'] !== '' ? $form_data['salary'] : null,
            $form_data['bank_name'], $form_data['bank_account'], $form_data['ifsc_code'],
            $form_data['status'], $form_data['description'], $id,
        ]);
        $_SESSION['success_msg'] = 'Teacher updated successfully.';
        header('Location: teacher_view.php?id=' . $id);
        exit;
    }
    $_SESSION['error_msg'] = implode(' ', $errors);
}

require_once 'includes/header.php';

if (!$teacher) {
    $results = $search !== '' ? searchTeachers($pdo, $search) : [];
    ?>
    <div class="content-top-bar">
        <div class="content-top-main">
            <div class="content-top-icon icon-blue"><i class="fas fa-pen"></i></div>
            <div class="content-top-title">
                <h2>Edit Teacher</h2>
                <p class="content-top-breadcrumb"><a href="teachers.php">Teachers</a><i class="fas fa-chevron-right"></i><span>Select Teacher</span></p>
            </div>
        </div>
    </div>
    <div class="form-section-card section-mb">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-school"><i class="fas fa-search"></i></div>
            <div><h4>Select a teacher to edit</h4><p>Search by name, employee ID, or mobile</p></div>
        </div>
        <form method="GET" class="category-add-row">
            <div class="form-field form-field-grow"><input type="text" name="q" class="form-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search teacher..." autofocus></div>
            <button type="submit" class="btn-header-action btn-header-primary category-add-btn"><i class="fas fa-search"></i> Search</button>
        </form>
        <?php if ($search !== ''): ?>
        <div class="erp-search-results teacher-search-results">
            <?php if ($results): foreach ($results as $r): ?>
            <a href="teacher_edit.php?id=<?php echo $r['id']; ?>" class="erp-search-item teacher-search-card teacher-search-link">
                <div class="teacher-search-main">
                    <div class="teacher-search-avatar"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="teacher-search-info">
                        <strong><?php echo htmlspecialchars($r['name']); ?></strong>
                        <span><?php echo htmlspecialchars($r['employee_id']); ?></span>
                        <div class="teacher-search-meta">
                            <span class="teacher-search-subject-pill"><i class="fas fa-book"></i> <?php echo htmlspecialchars($r['subject'] ?: 'No subject'); ?></span>
                        </div>
                    </div>
                </div>
                <span class="teacher-search-go"><i class="fas fa-arrow-right"></i></span>
            </a>
            <?php endforeach; else: ?>
            <div class="tab-empty-state tab-empty-pad-sm"><div class="tab-empty-icon"><i class="fas fa-search"></i></div><h3>No teachers found</h3></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php require_once 'includes/footer.php'; exit;
}

$form_data = teacherFromRow($teacher);
$mode = 'edit';
$employee_id = $teacher['employee_id'];
$photo_url = getTeacherPhotoUrl($teacher);
$sections_api = 'teacher_edit.php?id=' . $id;
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-blue"><i class="fas fa-pen"></i></div>
        <div class="content-top-title">
            <h2>Edit Teacher</h2>
            <p class="content-top-breadcrumb">
                <a href="teachers.php">Teachers</a><i class="fas fa-chevron-right"></i>
                <span><?php echo htmlspecialchars($teacher['name']); ?></span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="teacher_view.php?id=<?php echo $id; ?>" class="btn-header-action btn-header-outline"><i class="fas fa-eye"></i> View</a>
    </div>
</div>

<form method="POST" class="student-form" enctype="multipart/form-data">
    <input type="hidden" name="update_teacher" value="1">
    <input type="hidden" name="teacher_id" value="<?php echo $id; ?>">
<?php include 'includes/teacher_form_sections.php'; ?>
    <div class="form-actions-bar">
        <a href="teachers.php" class="btn-header-action btn-header-outline"><i class="fas fa-times"></i> Cancel</a>
        <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Update Teacher</button>
    </div>
</form>
<script>
document.getElementById('photo')?.addEventListener('change', function (e) {
    var file = e.target.files[0], preview = document.getElementById('photoPreview');
    if (!file || !preview) return;
    var reader = new FileReader();
    reader.onload = function (ev) { preview.innerHTML = '<img src="' + ev.target.result + '" alt="Preview">'; };
    reader.readAsDataURL(file);
});
</script>
<?php require_once 'includes/footer.php'; ?>
