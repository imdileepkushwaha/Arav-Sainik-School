<?php
$page_title = "Add New Teacher";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/teacher_helpers.php';
require_once 'includes/class_helpers.php';

ensureTeacherSchema($pdo);
handleClassApiRequest($pdo);

$generated_emp_id = generateEmployeeId($pdo);
$class_options = getClassOptions($pdo);
$form_data = getDefaultTeacherFormData();
$mode = 'add';
$photo_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys(getDefaultTeacherFormData()) as $key) {
        $form_data[$key] = trim($_POST[$key] ?? '');
    }
    $errors = validateTeacherForm($form_data);

    if (empty($errors)) {
        try {
            $empId = generateEmployeeId($pdo);
            $photo = null;
            if (!empty($_FILES['photo']['name'])) {
                $photo = uploadTeacherPhoto($_FILES['photo'], $empId);
                if ($photo === false) {
                    $_SESSION['error_msg'] = 'Invalid photo. Use JPG/PNG under 2MB.';
                    header('Location: teacher_add.php');
                    exit;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO teachers (
                employee_id, name, email, phone, gender, dob, join_date, subject, qualification, experience_years,
                class_assigned, section_assigned, address, city, state, pincode, photo, salary,
                bank_name, bank_account, ifsc_code, status, description
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

            $stmt->execute([
                $empId, $form_data['name'], $form_data['email'], $form_data['phone'],
                $form_data['gender'], $form_data['dob'] ?: null, $form_data['join_date'] ?: null,
                $form_data['subject'], $form_data['qualification'], $form_data['experience_years'],
                $form_data['class_assigned'] ?: null, $form_data['section_assigned'] ?: null,
                $form_data['address'], $form_data['city'], $form_data['state'], $form_data['pincode'],
                $photo, $form_data['salary'] !== '' ? $form_data['salary'] : null,
                $form_data['bank_name'], $form_data['bank_account'], $form_data['ifsc_code'],
                $form_data['status'], $form_data['description'],
            ]);

            $newId = (int) $pdo->lastInsertId();
            enableTeacherPortal($pdo, $newId);

            $_SESSION['success_msg'] = 'Teacher added! Employee ID: ' . $empId . ' — Portal login password: ' . getTeacherPortalDefaultPassword() . ' (must change on first login)';
            header('Location: teachers.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = 'Failed to add teacher. Employee ID may already exist.';
        }
    } else {
        $_SESSION['error_msg'] = implode(' ', $errors);
    }
    $generated_emp_id = generateEmployeeId($pdo);
}

require_once 'includes/header.php';
?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-blue"><i class="fas fa-user-plus"></i></div>
        <div class="content-top-title">
            <h2>Add New Teacher</h2>
            <p class="content-top-breadcrumb">
                <a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i>
                <a href="teachers.php">Teachers</a><i class="fas fa-chevron-right"></i><span>Add New</span>
            </p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="teachers.php" class="btn-header-action btn-header-outline"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<form method="POST" class="student-form" enctype="multipart/form-data">
<?php include 'includes/teacher_form_sections.php'; ?>
    <div class="form-actions-bar">
        <a href="teachers.php" class="btn-header-action btn-header-outline"><i class="fas fa-times"></i> Cancel</a>
        <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-check"></i> Save Teacher</button>
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
