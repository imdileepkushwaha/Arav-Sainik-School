<?php
$page_title = "Teacher Timetable";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/teacher_helpers.php';
require_once 'includes/class_helpers.php';

ensureTeacherSchema($pdo);
handleClassApiRequest($pdo);

$teacherId = (int) ($_GET['id'] ?? $_POST['teacher_id'] ?? 0);
$teachers = getAllTeachers($pdo, true);
$class_options = getClassOptions($pdo);
$periodDefaults = defaultPeriodTimes();
$days = getWeekDays();
$periods = range(1, 8);
$totalSlots = count($days) * count($periods);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_timetable'])) {
    $teacherId = (int) $_POST['teacher_id'];
    if ($teacherId) {
        foreach ($days as $day) {
            foreach ($periods as $p) {
                $prefix = $day . '_' . $p . '_';
                saveTeacherTimetableSlot($pdo, $teacherId, $day, $p, [
                    'start_time'   => $_POST[$prefix . 'start'] ?? '',
                    'end_time'     => $_POST[$prefix . 'end'] ?? '',
                    'class_name'   => $_POST[$prefix . 'class'] ?? '',
                    'section_name' => $_POST[$prefix . 'section'] ?? '',
                    'subject_name' => $_POST[$prefix . 'subject'] ?? '',
                    'room_no'      => $_POST[$prefix . 'room'] ?? '',
                ]);
            }
        }
        $_SESSION['success_msg'] = 'Timetable saved successfully.';
        header('Location: teacher_timetable.php?id=' . $teacherId);
        exit;
    }
}

require_once 'includes/header.php';

$teacher = $teacherId ? getTeacherById($pdo, $teacherId) : null;
$grid = $teacher ? getTeacherTimetable($pdo, $teacherId) : [];
$filledCount = 0;
$activeDays = [];
if ($teacher) {
    foreach ($days as $day) {
        foreach ($periods as $p) {
            $slot = $grid[$day][$p] ?? null;
            if ($slot && (trim($slot['class_name'] ?? '') !== '' || trim($slot['subject_name'] ?? '') !== '')) {
                $filledCount++;
                $activeDays[$day] = true;
            }
        }
    }
}
$freeCount = $totalSlots - $filledCount;
$teacherSubject = $teacher ? htmlspecialchars($teacher['subject']) : '';
?>

<?php if (!$teacher): ?>
<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-purple"><i class="fas fa-calendar-alt"></i></div>
        <div class="content-top-title">
            <h2>Teacher Timetable</h2>
            <p class="content-top-breadcrumb">
                <a href="teachers.php">Teachers</a><i class="fas fa-chevron-right"></i><span>Timetable</span>
            </p>
        </div>
    </div>
</div>

<div class="form-section-card section-mb tt-picker-card">
    <div class="section-card-header">
        <div class="section-card-icon section-icon-school"><i class="fas fa-user-clock"></i></div>
        <div><h4>Select a teacher</h4><p>Choose who you want to schedule — search by name or subject</p></div>
    </div>
    <form method="GET" id="ttPickerForm">
        <div class="tt-picker-row">
            <div class="form-field form-field-grow">
                <label for="ttTeacherSearch"><i class="fas fa-search"></i> Search</label>
                <input type="text" id="ttTeacherSearch" class="form-input" placeholder="Type to filter teachers..." autocomplete="off">
            </div>
            <div class="form-field form-field-grow">
                <label for="ttTeacherSelect"><i class="fas fa-chalkboard-teacher"></i> Teacher <span class="required">*</span></label>
                <select name="id" id="ttTeacherSelect" class="form-input form-select" required onchange="if(this.value) this.form.submit()">
                    <option value="">Choose teacher...</option>
                    <?php foreach ($teachers as $t): ?>
                    <option value="<?php echo $t['id']; ?>" <?php echo $teacherId === (int) $t['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['name'] . ' — ' . $t['subject'] . ' (' . $t['employee_id'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field tt-picker-action">
                <label>&nbsp;</label>
                <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-arrow-right"></i> Open Timetable</button>
            </div>
        </div>
    </form>
</div>

<?php if ($teacherId && !$teacher): ?>
<div class="tab-empty-state"><div class="tab-empty-icon"><i class="fas fa-user-slash"></i></div><h3>Teacher not found</h3><p>The selected teacher may have been removed.</p></div>
<?php else: ?>
<div class="tab-empty-state tt-empty-picker">
    <div class="tab-empty-icon"><i class="fas fa-calendar-week"></i></div>
    <h3>Build a weekly schedule</h3>
    <p>Select a teacher above to assign classes, subjects, and rooms for each period (Mon–Sat).</p>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var search = document.getElementById('ttTeacherSearch');
    var select = document.getElementById('ttTeacherSelect');
    if (search && select) {
        var options = Array.prototype.slice.call(select.options);
        search.addEventListener('input', function () {
            var q = this.value.toLowerCase();
            options.forEach(function (opt, idx) {
                if (idx === 0) return;
                var show = !q || opt.text.toLowerCase().indexOf(q) >= 0;
                opt.hidden = !show;
                opt.disabled = !show;
            });
        });
    }
});
</script>

<?php endif; ?>

<?php if ($teacher):
$photo_url = getTeacherPhotoUrl($teacher);
$teacher_name = htmlspecialchars($teacher['name']);
?>

<div class="tt-page">

<div class="student-view-header teacher-view-header tt-page-header">
    <div class="student-view-header-card teacher-view-header-card">
        <div class="student-view-header-main">
            <a href="teachers.php" class="student-back-btn" aria-label="Back to teachers"><i class="fas fa-arrow-left"></i></a>
            <div class="student-header-avatar">
                <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="<?php echo $teacher_name; ?>">
            </div>
            <div class="student-header-info">
                <div class="student-header-title-row">
                    <h1><?php echo $teacher_name; ?></h1>
                    <span class="status-badge badge-active"><i class="fas fa-calendar-check"></i> Timetable Editor</span>
                </div>
                <p class="student-view-breadcrumb">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                    <i class="fas fa-chevron-right"></i>
                    <a href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> Teachers</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Timetable</span>
                </p>
                <div class="student-header-meta">
                    <span class="header-meta-chip"><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($teacher['employee_id']); ?></span>
                    <span class="header-meta-chip"><i class="fas fa-book"></i> <?php echo htmlspecialchars($teacher['subject']); ?></span>
                    <span class="header-meta-chip"><i class="fas fa-clock"></i> <?php echo $filledCount; ?> / <?php echo $totalSlots; ?> periods</span>
                </div>
            </div>
        </div>
        <div class="student-view-header-actions">
            <a href="teacher_view.php?id=<?php echo $teacherId; ?>" class="btn-header-action btn-header-outline"><i class="fas fa-eye"></i> Profile</a>
            <button type="submit" form="ttEditorForm" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save</button>
        </div>
    </div>
</div>

<div class="teacher-stat-strip tt-stat-strip">
    <div class="teacher-stat-item">
        <div class="teacher-stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div><span>Scheduled</span><strong><?php echo $filledCount; ?></strong></div>
    </div>
    <div class="teacher-stat-item">
        <div class="teacher-stat-icon"><i class="fas fa-mug-hot"></i></div>
        <div><span>Free Periods</span><strong><?php echo $freeCount; ?></strong></div>
    </div>
    <div class="teacher-stat-item">
        <div class="teacher-stat-icon"><i class="fas fa-calendar-day"></i></div>
        <div><span>Active Days</span><strong><?php echo count($activeDays); ?> / <?php echo count($days); ?></strong></div>
    </div>
    <div class="teacher-stat-item">
        <div class="teacher-stat-icon"><i class="fas fa-book-open"></i></div>
        <div><span>Default Subject</span><strong class="tt-stat-subject"><?php echo $teacherSubject; ?></strong></div>
    </div>
</div>

<form method="POST" id="ttEditorForm" class="tt-editor-form">
    <input type="hidden" name="save_timetable" value="1">
    <input type="hidden" name="teacher_id" value="<?php echo $teacherId; ?>">

    <div class="form-section-card tt-toolbar-card">
        <div class="tt-toolbar">
            <div class="tt-toolbar-left">
                <div class="section-card-icon section-icon-school"><i class="fas fa-sliders"></i></div>
                <div>
                    <h4>Weekly schedule</h4>
                    <p>Mon–Sat · 8 periods per day · Leave fields empty for free periods</p>
                </div>
            </div>
            <div class="tt-toolbar-actions">
                <div class="tt-view-toggle" role="group" aria-label="View mode">
                    <button type="button" class="tt-view-btn active" data-view="day"><i class="fas fa-list"></i> Day view</button>
                    <button type="button" class="tt-view-btn" data-view="grid"><i class="fas fa-table"></i> Grid</button>
                </div>
            </div>
        </div>

        <div class="tt-quick-actions">
            <span class="tt-quick-label"><i class="fas fa-bolt"></i> Quick actions</span>
            <button type="button" class="btn-header-action btn-header-outline btn-sm" id="ttFillSubject" data-subject="<?php echo $teacherSubject; ?>"><i class="fas fa-fill-drip"></i> Fill default subject</button>
            <button type="button" class="btn-header-action btn-header-outline btn-sm" id="ttCopyMonday"><i class="fas fa-copy"></i> Copy Monday → all days</button>
            <button type="button" class="btn-header-action btn-header-outline btn-sm" id="ttClearDay"><i class="fas fa-eraser"></i> Clear this day</button>
            <button type="button" class="btn-header-action btn-header-outline btn-sm tt-danger-outline" id="ttClearAll"><i class="fas fa-trash-alt"></i> Clear all</button>
        </div>

        <div class="tt-week-preview">
            <?php foreach ($days as $day):
                $dayFilled = 0;
                foreach ($periods as $p) {
                    $slot = $grid[$day][$p] ?? null;
                    if ($slot && (trim($slot['class_name'] ?? '') !== '' || trim($slot['subject_name'] ?? '') !== '')) {
                        $dayFilled++;
                    }
                }
            ?>
            <button type="button" class="tt-week-dot <?php echo $dayFilled ? 'has-slots' : ''; ?>" data-goto-day="<?php echo $day; ?>" title="<?php echo $day; ?>: <?php echo $dayFilled; ?> period(s)">
                <span><?php echo substr($day, 0, 3); ?></span>
                <em><?php echo $dayFilled; ?></em>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Day view -->
    <div class="tt-day-view" id="ttDayView">
        <div class="tt-day-tabs" role="tablist">
            <?php foreach ($days as $i => $day):
                $dayFilled = 0;
                foreach ($periods as $p) {
                    $slot = $grid[$day][$p] ?? null;
                    if ($slot && (trim($slot['class_name'] ?? '') !== '' || trim($slot['subject_name'] ?? '') !== '')) {
                        $dayFilled++;
                    }
                }
            ?>
            <button type="button" class="tt-day-tab <?php echo $i === 0 ? 'active' : ''; ?>" data-day="<?php echo $day; ?>" role="tab" aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>">
                <?php echo $day; ?>
                <?php if ($dayFilled): ?><span class="tt-tab-badge"><?php echo $dayFilled; ?></span><?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($days as $i => $day): ?>
        <div class="tt-day-panel <?php echo $i === 0 ? 'active' : ''; ?>" data-day-panel="<?php echo $day; ?>">
            <div class="tt-period-list">
                <?php foreach ($periods as $p):
                    $slot = $grid[$day][$p] ?? null;
                    $pre = $day . '_' . $p . '_';
                    $def = $periodDefaults[$p];
                    $hasData = $slot && (trim($slot['class_name'] ?? '') !== '' || trim($slot['subject_name'] ?? '') !== '');
                ?>
                <div class="tt-period-card <?php echo $hasData ? 'is-filled' : 'is-empty'; ?>" data-day="<?php echo $day; ?>" data-period="<?php echo $p; ?>">
                    <div class="tt-period-card-head">
                        <div class="tt-period-badge">P<?php echo $p; ?></div>
                        <div class="tt-period-time"><i class="fas fa-clock"></i> <?php echo $def[0]; ?> – <?php echo $def[1]; ?></div>
                        <button type="button" class="tt-slot-clear" title="Clear this period"><i class="fas fa-times"></i></button>
                    </div>
                    <input type="hidden" name="<?php echo $pre; ?>start" value="<?php echo htmlspecialchars($slot['start_time'] ?? $def[0]); ?>">
                    <input type="hidden" name="<?php echo $pre; ?>end" value="<?php echo htmlspecialchars($slot['end_time'] ?? $def[1]); ?>">
                    <div class="tt-period-fields">
                        <div class="form-field">
                            <label>Subject</label>
                            <input type="text" name="<?php echo $pre; ?>subject" class="form-input tt-field-subject" placeholder="e.g. Mathematics" value="<?php echo htmlspecialchars($slot['subject_name'] ?? ''); ?>">
                        </div>
                        <div class="form-field">
                            <label>Class</label>
                            <select name="<?php echo $pre; ?>class" class="form-input form-select tt-field-class" data-day="<?php echo $day; ?>" data-period="<?php echo $p; ?>">
                                <option value="">— Free period —</option>
                                <?php foreach ($class_options as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($slot['class_name'] ?? '') === $c ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Section</label>
                            <select name="<?php echo $pre; ?>section" class="form-input form-select tt-field-section" data-selected="<?php echo htmlspecialchars($slot['section_name'] ?? ''); ?>">
                                <option value="">—</option>
                                <?php if (!empty($slot['section_name'])): ?>
                                <option value="<?php echo htmlspecialchars($slot['section_name']); ?>" selected><?php echo htmlspecialchars($slot['section_name']); ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Room</label>
                            <input type="text" name="<?php echo $pre; ?>room" class="form-input tt-field-room" placeholder="e.g. 101" value="<?php echo htmlspecialchars($slot['room_no'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Grid view (compact) -->
    <div class="tt-grid-view tt-grid-view-hidden" id="ttGridView">
        <div class="table-container">
            <div class="table-wrapper table-scroll-x">
                <table class="timetable-grid-table tt-grid-table">
                    <thead>
                        <tr>
                            <th class="timetable-day-col">Day</th>
                            <?php foreach ($periods as $p): ?>
                            <th>P<?php echo $p; ?><br><small><?php echo $periodDefaults[$p][0]; ?>–<?php echo $periodDefaults[$p][1]; ?></small></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($days as $day): ?>
                    <tr>
                        <td class="timetable-day-col"><strong><?php echo substr($day, 0, 3); ?></strong></td>
                        <?php foreach ($periods as $p):
                            $slot = $grid[$day][$p] ?? null;
                            $hasData = $slot && (trim($slot['class_name'] ?? '') !== '' || trim($slot['subject_name'] ?? '') !== '');
                        ?>
                        <td class="timetable-cell tt-grid-cell <?php echo $hasData ? 'has-data' : ''; ?>" data-goto="<?php echo $day; ?>" data-period="<?php echo $p; ?>" title="Click to edit in day view">
                            <?php if ($hasData): ?>
                            <strong><?php echo htmlspecialchars($slot['subject_name'] ?: $teacher['subject']); ?></strong>
                            <span><?php echo htmlspecialchars($slot['class_name'] ?? ''); ?><?php echo $slot['section_name'] ? ' · ' . htmlspecialchars($slot['section_name']) : ''; ?></span>
                            <?php if ($slot['room_no']): ?><em>R<?php echo htmlspecialchars($slot['room_no']); ?></em><?php endif; ?>
                            <?php else: ?>
                            <span class="tt-grid-free">Free</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="tt-grid-hint"><i class="fas fa-info-circle"></i> Grid is read-only preview. Switch to <strong>Day view</strong> to edit periods.</p>
    </div>

    <div class="tt-save-bar">
        <div class="tt-save-bar-inner">
            <div class="tt-save-bar-info">
                <i class="fas fa-info-circle"></i>
                <span><strong><?php echo $filledCount; ?></strong> periods scheduled · Changes save for <strong><?php echo $teacher_name; ?></strong></span>
            </div>
            <div class="tt-save-bar-actions">
                <a href="teacher_timetable.php" class="btn-header-action btn-header-outline"><i class="fas fa-exchange-alt"></i> Change teacher</a>
                <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save Timetable</button>
            </div>
        </div>
    </div>
</form>

</div><!-- .tt-page -->

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('ttEditorForm');
    if (!form) return;

    var dayTabs = document.querySelectorAll('.tt-day-tab');
    var dayPanels = document.querySelectorAll('.tt-day-panel');
    var activeDay = dayTabs.length ? dayTabs[0].getAttribute('data-day') : 'Monday';
    var sectionsApi = 'teacher_timetable.php?action=sections';

    function setActiveDay(day) {
        activeDay = day;
        dayTabs.forEach(function (tab) {
            var on = tab.getAttribute('data-day') === day;
            tab.classList.toggle('active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        dayPanels.forEach(function (panel) {
            panel.classList.toggle('active', panel.getAttribute('data-day-panel') === day);
        });
    }

    dayTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            setActiveDay(this.getAttribute('data-day'));
        });
    });

    document.querySelectorAll('.tt-week-dot[data-goto-day]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setActiveDay(this.getAttribute('data-goto-day'));
            document.getElementById('ttDayView').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    document.querySelectorAll('.tt-view-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.tt-view-btn').forEach(function (b) { b.classList.remove('active'); });
            this.classList.add('active');
            var isGrid = this.getAttribute('data-view') === 'grid';
            document.getElementById('ttDayView').classList.toggle('tt-day-view-hidden', isGrid);
            document.getElementById('ttGridView').classList.toggle('tt-grid-view-hidden', !isGrid);
        });
    });

    document.querySelectorAll('.tt-grid-cell[data-goto]').forEach(function (cell) {
        cell.addEventListener('click', function () {
            var day = this.getAttribute('data-goto');
            document.querySelector('.tt-view-btn[data-view="day"]').click();
            setActiveDay(day);
            var period = this.getAttribute('data-period');
            var card = document.querySelector('.tt-period-card[data-day="' + day + '"][data-period="' + period + '"]');
            if (card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    function updateCardState(card) {
        var subject = (card.querySelector('.tt-field-subject') || {}).value || '';
        var cls = (card.querySelector('.tt-field-class') || {}).value || '';
        var filled = subject.trim() !== '' || cls.trim() !== '';
        card.classList.toggle('is-filled', filled);
        card.classList.toggle('is-empty', !filled);
    }

    function clearCard(card) {
        ['.tt-field-subject', '.tt-field-room'].forEach(function (sel) {
            var el = card.querySelector(sel);
            if (el) el.value = '';
        });
        var cls = card.querySelector('.tt-field-class');
        if (cls) cls.value = '';
        var sec = card.querySelector('.tt-field-section');
        if (sec) {
            sec.innerHTML = '<option value="">—</option>';
        }
        updateCardState(card);
    }

    form.querySelectorAll('.tt-period-card').forEach(function (card) {
        card.querySelectorAll('input, select').forEach(function (el) {
            el.addEventListener('input', function () { updateCardState(card); });
            el.addEventListener('change', function () { updateCardState(card); });
        });
        var clearBtn = card.querySelector('.tt-slot-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () { clearCard(card); });
        }
    });

    function loadSections(selectEl, className, selected) {
        if (!className) {
            selectEl.innerHTML = '<option value="">—</option>';
            return;
        }
        fetch(sectionsApi + '&class=' + encodeURIComponent(className))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var html = '<option value="">—</option>';
                (data.sections || []).forEach(function (s) {
                    html += '<option value="' + s + '"' + (selected === s ? ' selected' : '') + '>' + s + '</option>';
                });
                selectEl.innerHTML = html;
            })
            .catch(function () {
                selectEl.innerHTML = '<option value="">—</option>';
                if (selected) {
                    selectEl.innerHTML += '<option value="' + selected + '" selected>' + selected + '</option>';
                }
            });
    }

    form.querySelectorAll('.tt-field-class').forEach(function (clsSelect) {
        var card = clsSelect.closest('.tt-period-card');
        var secSelect = card.querySelector('.tt-field-section');
        var initial = secSelect ? secSelect.getAttribute('data-selected') : '';
        if (clsSelect.value && secSelect) {
            loadSections(secSelect, clsSelect.value, initial);
        }
        clsSelect.addEventListener('change', function () {
            if (secSelect) loadSections(secSelect, this.value, '');
            updateCardState(card);
        });
    });

    var fillBtn = document.getElementById('ttFillSubject');
    if (fillBtn) {
        fillBtn.addEventListener('click', function () {
            var subj = this.getAttribute('data-subject') || '';
            form.querySelectorAll('.tt-field-subject').forEach(function (input) {
                if (!input.value.trim()) input.value = subj;
                updateCardState(input.closest('.tt-period-card'));
            });
        });
    }

    document.getElementById('ttCopyMonday') && document.getElementById('ttCopyMonday').addEventListener('click', function () {
        if (!confirm('Copy Monday schedule to Tuesday–Saturday? Existing slots on those days will be overwritten.')) return;
        var days = ['Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        for (var p = 1; p <= 8; p++) {
            var monCard = form.querySelector('.tt-period-card[data-day="Monday"][data-period="' + p + '"]');
            if (!monCard) continue;
            var subj = (monCard.querySelector('.tt-field-subject') || {}).value || '';
            var cls = (monCard.querySelector('.tt-field-class') || {}).value || '';
            var sec = (monCard.querySelector('.tt-field-section') || {}).value || '';
            var room = (monCard.querySelector('.tt-field-room') || {}).value || '';
            days.forEach(function (day) {
                var card = form.querySelector('.tt-period-card[data-day="' + day + '"][data-period="' + p + '"]');
                if (!card) return;
                var s = card.querySelector('.tt-field-subject'); if (s) s.value = subj;
                var c = card.querySelector('.tt-field-class'); if (c) { c.value = cls; c.dispatchEvent(new Event('change')); }
                setTimeout(function () {
                    var sc = card.querySelector('.tt-field-section'); if (sc) sc.value = sec;
                    var r = card.querySelector('.tt-field-room'); if (r) r.value = room;
                    updateCardState(card);
                }, 200);
            });
        }
    });

    document.getElementById('ttClearDay') && document.getElementById('ttClearDay').addEventListener('click', function () {
        if (!confirm('Clear all periods for ' + activeDay + '?')) return;
        form.querySelectorAll('.tt-period-card[data-day="' + activeDay + '"]').forEach(clearCard);
    });

    document.getElementById('ttClearAll') && document.getElementById('ttClearAll').addEventListener('click', function () {
        if (!confirm('Clear the entire weekly timetable? This cannot be undone until you save.')) return;
        form.querySelectorAll('.tt-period-card').forEach(clearCard);
    });
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
