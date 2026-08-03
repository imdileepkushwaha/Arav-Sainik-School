<?php
// admin/includes/teacher_form_sections.php
// Expects: $form_data, $class_options, $mode ('add'|'edit'), $generated_emp_id (add), $employee_id (edit), $photo_url (optional), $sections_api (optional)
$is_edit = ($mode ?? 'add') === 'edit';
$sections_api = $sections_api ?? 'teacher_add.php';
$section_options = [];
if (!empty($form_data['class_assigned']) && isset($pdo)) {
    $section_options = getSectionOptions($pdo, $form_data['class_assigned']);
}
?>
    <div class="form-section-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-school"><i class="fas fa-chalkboard-teacher"></i></div>
            <div>
                <h4>Basic Information</h4>
                <p>Teacher identity and employment details</p>
            </div>
        </div>
        <div class="form-grid">
            <div class="form-field">
                <label><i class="fas fa-id-badge"></i> Employee ID</label>
                <?php if ($is_edit): ?>
                <div class="form-input-readonly"><span class="ad-no-display"><?php echo htmlspecialchars($employee_id); ?></span></div>
                <?php else: ?>
                <div class="form-input-readonly">
                    <span class="ad-no-display"><?php echo htmlspecialchars($generated_emp_id); ?></span>
                    <span class="auto-gen-tag"><i class="fas fa-magic"></i> Auto generated</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-field">
                <label for="name"><i class="fas fa-user"></i> Full Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-input" value="<?php echo htmlspecialchars($form_data['name']); ?>" required>
            </div>
            <div class="form-field">
                <label for="subject"><i class="fas fa-book"></i> Primary Subject <span class="required">*</span></label>
                <input type="text" id="subject" name="subject" class="form-input" value="<?php echo htmlspecialchars($form_data['subject']); ?>" placeholder="e.g. Mathematics" required>
            </div>
            <div class="form-field">
                <label for="qualification"><i class="fas fa-graduation-cap"></i> Qualification</label>
                <input type="text" id="qualification" name="qualification" class="form-input" value="<?php echo htmlspecialchars($form_data['qualification']); ?>" placeholder="e.g. M.Sc, B.Ed">
            </div>
            <div class="form-field">
                <label for="experience_years"><i class="fas fa-briefcase"></i> Experience</label>
                <input type="text" id="experience_years" name="experience_years" class="form-input" value="<?php echo htmlspecialchars($form_data['experience_years']); ?>" placeholder="e.g. 5 years">
            </div>
            <div class="form-field">
                <label for="join_date"><i class="fas fa-calendar-plus"></i> Join Date</label>
                <input type="date" id="join_date" name="join_date" class="form-input" value="<?php echo htmlspecialchars($form_data['join_date']); ?>">
            </div>
            <div class="form-field">
                <label for="gender"><i class="fas fa-venus-mars"></i> Gender</label>
                <select id="gender" name="gender" class="form-input form-select">
                    <option value="Male" <?php echo $form_data['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $form_data['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo $form_data['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-field">
                <label for="dob"><i class="fas fa-cake-candles"></i> Date of Birth</label>
                <input type="date" id="dob" name="dob" class="form-input" value="<?php echo htmlspecialchars($form_data['dob']); ?>">
            </div>
            <div class="form-field">
                <label for="status"><i class="fas fa-circle-check"></i> Status</label>
                <select id="status" name="status" class="form-input form-select">
                    <option value="Active" <?php echo $form_data['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $form_data['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="form-section-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-parent"><i class="fas fa-school"></i></div>
            <div><h4>Class Assignment</h4><p>Homeroom / class teacher (optional)</p></div>
        </div>
        <div class="form-grid form-grid-2">
            <div class="form-field">
                <label for="class_assigned"><i class="fas fa-chalkboard"></i> Assigned Class</label>
                <select id="class_assigned" name="class_assigned" class="form-input form-select">
                    <option value="">Not assigned</option>
                    <?php foreach ($class_options as $opt): ?>
                    <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $form_data['class_assigned'] === $opt ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="section_assigned"><i class="fas fa-table-columns"></i> Section</label>
                <select id="section_assigned" name="section_assigned" class="form-input form-select">
                    <option value="">—</option>
                    <?php foreach ($section_options ?: ['A','B','C','D'] as $sec): ?>
                    <option value="<?php echo htmlspecialchars($sec); ?>" <?php echo ($form_data['section_assigned'] ?? '') === $sec ? 'selected' : ''; ?>><?php echo htmlspecialchars($sec); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="form-section-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-address"><i class="fas fa-phone"></i></div>
            <div><h4>Contact</h4></div>
        </div>
        <div class="form-grid form-grid-2">
            <div class="form-field">
                <label for="phone"><i class="fas fa-mobile-alt"></i> Mobile <span class="required">*</span></label>
                <input type="tel" id="phone" name="phone" class="form-input" value="<?php echo htmlspecialchars($form_data['phone']); ?>" required>
            </div>
            <div class="form-field">
                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($form_data['email']); ?>">
            </div>
        </div>
    </div>

    <div class="form-section-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-address"><i class="fas fa-map-marker-alt"></i></div>
            <div><h4>Address</h4></div>
        </div>
        <div class="form-grid">
            <div class="form-field form-field-full">
                <label for="address">Full Address</label>
                <textarea id="address" name="address" class="form-input form-textarea" rows="2"><?php echo htmlspecialchars($form_data['address']); ?></textarea>
            </div>
            <div class="form-field"><label for="city">City</label><input type="text" id="city" name="city" class="form-input" value="<?php echo htmlspecialchars($form_data['city']); ?>"></div>
            <div class="form-field"><label for="state">State</label><input type="text" id="state" name="state" class="form-input" value="<?php echo htmlspecialchars($form_data['state']); ?>"></div>
            <div class="form-field"><label for="pincode">Pincode</label><input type="text" id="pincode" name="pincode" class="form-input" value="<?php echo htmlspecialchars($form_data['pincode']); ?>"></div>
        </div>
    </div>

    <div class="details-grid">
        <div class="form-section-card form-section-flush">
            <div class="section-card-header">
                <div class="section-card-icon section-icon-bank"><i class="fas fa-university"></i></div>
                <div><h4>Bank &amp; Salary</h4></div>
            </div>
            <div class="form-grid form-grid-1">
                <div class="form-field"><label>Monthly Salary (Rs.)</label><input type="number" step="0.01" name="salary" class="form-input" value="<?php echo htmlspecialchars($form_data['salary']); ?>"></div>
                <div class="form-field"><label>Bank Name</label><input type="text" name="bank_name" class="form-input" value="<?php echo htmlspecialchars($form_data['bank_name']); ?>"></div>
                <div class="form-field"><label>Account No.</label><input type="text" name="bank_account" class="form-input" value="<?php echo htmlspecialchars($form_data['bank_account']); ?>"></div>
                <div class="form-field"><label>IFSC</label><input type="text" name="ifsc_code" class="form-input" value="<?php echo htmlspecialchars($form_data['ifsc_code']); ?>"></div>
            </div>
        </div>
        <div class="form-section-card form-section-flush">
            <div class="section-card-header">
                <div class="section-card-icon section-icon-docs"><i class="fas fa-camera"></i></div>
                <div><h4>Photo</h4><p>JPG, PNG — Max 2MB</p></div>
            </div>
            <div class="photo-upload-area">
                <div class="photo-upload-preview" id="photoPreview">
                    <?php if (!empty($photo_url) && strpos($photo_url, 'ui-avatars') === false): ?>
                    <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="Photo">
                    <?php else: ?>
                    <i class="fas fa-user"></i><span>No photo</span>
                    <?php endif; ?>
                </div>
                <div class="photo-upload-content">
                    <label class="photo-upload-btn"><i class="fas fa-upload"></i> Choose File
                        <input type="file" name="photo" id="photo" accept="image/*" hidden>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="form-section-card">
        <div class="section-card-header">
            <div class="section-card-icon section-icon-desc"><i class="fas fa-align-left"></i></div>
            <div><h4>Remarks</h4></div>
        </div>
        <textarea name="description" class="form-input form-textarea" rows="3" placeholder="Optional notes..."><?php echo htmlspecialchars($form_data['description']); ?></textarea>
    </div>

<script>
(function () {
    var classSelect = document.getElementById('class_assigned');
    var sectionSelect = document.getElementById('section_assigned');
    if (!classSelect || !sectionSelect) return;
    classSelect.addEventListener('change', function () {
        var cls = this.value;
        if (!cls) {
            sectionSelect.innerHTML = '<option value="">—</option>';
            return;
        }
        fetch('<?php echo htmlspecialchars($sections_api); ?>' + (('<?php echo htmlspecialchars($sections_api); ?>').indexOf('?') >= 0 ? '&' : '?') + 'action=sections&class=' + encodeURIComponent(cls))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                sectionSelect.innerHTML = '<option value="">—</option>';
                (data.sections || []).forEach(function (s) {
                    var o = document.createElement('option');
                    o.value = s; o.textContent = s;
                    sectionSelect.appendChild(o);
                });
            });
    });
})();
</script>
