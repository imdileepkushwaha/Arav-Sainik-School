<?php
$page_title = 'Timetable';
$page_subtitle = 'Your weekly teaching schedule';
require_once 'includes/init.php';

$grid = getTeacherTimetable($pdo, $teacherId);
$days = getWeekDays();
$periods = range(1, 8);
$periodDefaults = defaultPeriodTimes();
$filledCount = countTeacherWeeklyPeriods($pdo, $teacherId);
$todayName = date('l');
$todaySlots = getTeacherTodaySchedule($pdo, $teacherId);

$currentPeriod = null;
$now = date('H:i');
foreach ($periodDefaults as $p => $times) {
    if ($now >= $times[0] && $now < $times[1]) {
        $currentPeriod = $p;
        break;
    }
}

$todayFilled = 0;
$activeDays = 0;
$classesSet = [];
foreach ($days as $day) {
    $dayFilled = 0;
    foreach ($periods as $p) {
        $slot = $grid[$day][$p] ?? null;
        $filled = $slot && (trim($slot['class_name'] ?? '') !== '' || trim($slot['subject_name'] ?? '') !== '');
        if ($filled) {
            $dayFilled++;
            $key = ($slot['class_name'] ?? '') . '|' . ($slot['section_name'] ?? '');
            if ($key !== '|') {
                $classesSet[$key] = true;
            }
        }
        if ($day === $todayName && $filled) {
            $todayFilled++;
        }
    }
    if ($dayFilled > 0) {
        $activeDays++;
    }
}
$freeCount = (count($days) * count($periods)) - $filledCount;

require_once 'includes/layout_header.php';
?>

<?php if ($filledCount): ?>
<div class="tp-tt-hero">
    <div>
        <h2><i class="fas fa-calendar-alt"></i> Weekly Timetable</h2>
        <p><?php echo htmlspecialchars($teacher['subject']); ?> · <?php echo $filledCount; ?> periods across <?php echo $activeDays; ?> day<?php echo $activeDays === 1 ? '' : 's'; ?></p>
        <div class="tp-tt-hero-chips">
            <span class="tp-tt-hero-chip"><i class="fas fa-calendar-day"></i> Today: <?php echo $todayName; ?></span>
            <span class="tp-tt-hero-chip"><i class="fas fa-chalkboard"></i> <?php echo $todayFilled; ?> class<?php echo $todayFilled === 1 ? '' : 'es'; ?> today</span>
            <?php if ($currentPeriod): ?><span class="tp-tt-hero-chip"><i class="fas fa-circle" style="font-size:6px"></i> Period <?php echo $currentPeriod; ?> now</span><?php endif; ?>
        </div>
    </div>
    <a href="dashboard.php" class="tp-btn tp-btn-outline" style="background:rgba(255,255,255,0.12);border-color:rgba(255,255,255,0.35);color:#fff"><i class="fas fa-home"></i> Dashboard</a>
</div>

<div class="tp-stat-grid cols-3">
    <div class="tp-stat-card">
        <div class="tp-stat-icon blue"><i class="fas fa-calendar-check"></i></div>
        <div><span>Scheduled</span><strong><?php echo $filledCount; ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon green"><i class="fas fa-users"></i></div>
        <div><span>Unique Classes</span><strong><?php echo count($classesSet); ?></strong></div>
    </div>
    <div class="tp-stat-card">
        <div class="tp-stat-icon orange"><i class="fas fa-mug-hot"></i></div>
        <div><span>Free Periods</span><strong><?php echo $freeCount; ?></strong></div>
    </div>
</div>

<div class="tp-card tp-tt-today-panel">
    <div class="tp-card-head">
        <h3><i class="fas fa-sun"></i> Today — <?php echo date('l, d M Y'); ?></h3>
        <?php if ($currentPeriod): ?><span class="tp-card-badge"><i class="fas fa-broadcast-tower"></i> P<?php echo $currentPeriod; ?> in progress</span><?php endif; ?>
    </div>
    <div class="tp-tt-today-scroll">
        <?php for ($p = 1; $p <= 8; $p++):
            $slot = $todaySlots[$p] ?? null;
            $def = $periodDefaults[$p];
            $filled = $slot && (trim($slot['class_name'] ?? '') !== '' || trim($slot['subject_name'] ?? '') !== '');
            $isNow = ($currentPeriod === $p);
        ?>
        <div class="tp-tt-today-card <?php echo $filled ? 'is-class' : 'is-free'; ?><?php echo $isNow ? ' is-now' : ''; ?>">
            <div class="tp-tt-today-top">
                <span class="tp-tt-today-period">P<?php echo $p; ?></span>
                <span class="tp-tt-today-time"><?php echo $def[0]; ?> – <?php echo $def[1]; ?></span>
            </div>
            <?php if ($filled): ?>
            <strong><?php echo htmlspecialchars($slot['subject_name'] ?: $teacher['subject']); ?><?php if ($isNow): ?> <span class="tp-schedule-now-badge">Now</span><?php endif; ?></strong>
            <span><?php echo htmlspecialchars($slot['class_name']); ?><?php echo $slot['section_name'] ? ' · Sec ' . htmlspecialchars($slot['section_name']) : ''; ?></span>
            <?php if ($slot['room_no']): ?><span class="tp-tt-today-room"><i class="fas fa-door-open"></i> Room <?php echo htmlspecialchars($slot['room_no']); ?></span><?php endif; ?>
            <?php else: ?>
            <strong style="color:#94a3b8">Free Period</strong>
            <span>No class scheduled</span>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<div class="tp-card">
    <div class="tp-card-head">
        <h3><i class="fas fa-table"></i> Full Schedule</h3>
        <span class="tp-card-badge"><i class="fas fa-clock"></i> Mon – Sat · 8 periods</span>
    </div>

    <div class="tp-tt-legend">
        <div class="tp-tt-legend-item"><span class="tp-tt-legend-dot filled"></span> Scheduled class</div>
        <div class="tp-tt-legend-item"><span class="tp-tt-legend-dot free"></span> Free period</div>
        <div class="tp-tt-legend-item"><span class="tp-tt-legend-dot today"></span> Today (<?php echo $todayName; ?>)</div>
    </div>

    <div class="tp-tt-view-tabs" role="tablist">
        <button type="button" class="tp-tt-view-tab is-active" data-view="grid"><i class="fas fa-th"></i> Week Grid</button>
        <button type="button" class="tp-tt-view-tab" data-view="list"><i class="fas fa-list"></i> By Day</button>
    </div>

    <div class="tp-tt-view-panel is-active" id="ttViewGrid">
        <div class="tp-tt-grid">
            <div class="tp-tt-row">
                <div class="tp-tt-day">Day</div>
                <?php foreach ($periods as $p):
                    $def = $periodDefaults[$p];
                ?>
                <div class="tp-tt-day">
                    P<?php echo $p; ?>
                    <div class="tp-tt-period-head"><?php echo $def[0]; ?>–<?php echo $def[1]; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php foreach ($days as $day):
                $isToday = ($day === $todayName);
            ?>
            <div class="tp-tt-row<?php echo $isToday ? ' is-today' : ''; ?>">
                <div class="tp-tt-day"><?php echo substr($day, 0, 3); ?><?php if ($isToday): ?><div class="tp-tt-period-head">Today</div><?php endif; ?></div>
                <?php foreach ($periods as $p):
                    $slot = $grid[$day][$p] ?? null;
                    $filled = $slot && (trim($slot['class_name'] ?? '') !== '' || trim($slot['subject_name'] ?? '') !== '');
                    $isNow = $isToday && ($currentPeriod === $p);
                ?>
                <div class="tp-tt-slot <?php echo $filled ? 'filled' : 'free'; ?><?php echo $isNow ? ' is-now' : ''; ?>">
                    <?php if ($filled): ?>
                    <span class="tp-tt-slot-subject"><?php echo htmlspecialchars($slot['subject_name'] ?: $teacher['subject']); ?></span>
                    <span class="tp-tt-slot-class"><?php echo htmlspecialchars($slot['class_name']); ?><?php echo $slot['section_name'] ? ' · ' . htmlspecialchars($slot['section_name']) : ''; ?></span>
                    <?php if ($slot['room_no']): ?><span class="tp-tt-slot-room"><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($slot['room_no']); ?></span><?php endif; ?>
                    <?php else: ?><i class="fas fa-mug-hot"></i><span>Free</span><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tp-tt-view-panel" id="ttViewList">
        <div class="tp-tt-day-groups">
            <?php foreach ($days as $day):
                $isToday = ($day === $todayName);
                $daySlots = [];
                foreach ($periods as $p) {
                    $slot = $grid[$day][$p] ?? null;
                    if ($slot && (trim($slot['class_name'] ?? '') !== '' || trim($slot['subject_name'] ?? '') !== '')) {
                        $daySlots[] = $slot;
                    }
                }
            ?>
            <div class="tp-tt-day-group<?php echo $isToday ? ' is-today' : ''; ?>">
                <div class="tp-tt-day-group-head">
                    <strong>
                        <?php echo $day; ?>
                        <?php if ($isToday): ?><span class="tp-schedule-now-badge">Today</span><?php endif; ?>
                    </strong>
                    <span class="tp-tt-day-count"><?php echo count($daySlots); ?> period<?php echo count($daySlots) === 1 ? '' : 's'; ?></span>
                </div>
                <?php if ($daySlots): ?>
                <div class="tp-tt-period-list">
                    <?php foreach ($daySlots as $slot):
                        $p = (int) $slot['period_no'];
                        $def = $periodDefaults[$p] ?? ['—', '—'];
                        $timeStr = $slot['start_time']
                            ? substr($slot['start_time'], 0, 5) . ' – ' . substr($slot['end_time'], 0, 5)
                            : $def[0] . ' – ' . $def[1];
                        $isNow = $isToday && ($currentPeriod === $p);
                    ?>
                    <div class="tp-tt-period-card<?php echo $isNow ? ' is-now' : ''; ?>">
                        <div class="tp-tt-period-badge">P<?php echo $p; ?></div>
                        <div class="tp-tt-period-body">
                            <strong><?php echo htmlspecialchars($slot['subject_name'] ?: $teacher['subject']); ?><?php if ($isNow): ?> <span class="tp-schedule-now-badge">Now</span><?php endif; ?></strong>
                            <span><?php echo htmlspecialchars($slot['class_name']); ?><?php echo $slot['section_name'] ? ' (Sec ' . htmlspecialchars($slot['section_name']) . ')' : ''; ?></span>
                            <div class="tp-tt-period-meta">
                                <span class="tp-tt-period-pill"><i class="fas fa-clock"></i> <?php echo $timeStr; ?></span>
                                <?php if ($slot['room_no']): ?><span class="tp-tt-period-pill"><i class="fas fa-door-open"></i> Room <?php echo htmlspecialchars($slot['room_no']); ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="tp-tt-empty-day"><i class="fas fa-mug-hot"></i> No classes scheduled</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var tabs = document.querySelectorAll('.tp-tt-view-tab');
    var panels = {
        grid: document.getElementById('ttViewGrid'),
        list: document.getElementById('ttViewList')
    };
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var view = this.getAttribute('data-view');
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            this.classList.add('is-active');
            Object.keys(panels).forEach(function (k) {
                if (panels[k]) panels[k].classList.toggle('is-active', k === view);
            });
        });
    });
})();
</script>

<?php else: ?>
<div class="tp-card">
    <div class="tp-empty" style="padding:60px 20px">
        <i class="fas fa-calendar-alt"></i>
        <h3 style="margin:12px 0 8px;color:var(--tp-text)">No Timetable Yet</h3>
        <p>Your weekly schedule hasn't been set up by the admin.<br>Please contact the school office to configure your timetable.</p>
        <a href="dashboard.php" class="tp-btn tp-btn-primary" style="margin-top:20px"><i class="fas fa-home"></i> Back to Dashboard</a>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/layout_footer.php'; ?>
