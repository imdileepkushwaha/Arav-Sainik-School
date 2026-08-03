<?php
$page_title = "Fee Structure";
require_once 'includes/init.php';
require_once '../includes/db_connect.php';
require_once 'includes/erp_helpers.php';

ensureErpSchema($pdo);
$session = getCurrentSession($pdo);
$class_options = getClassOptions($pdo);
$sessionId = $session['id'] ?? null;
$feeMonthOrder = getFeeMonthOrder();
$feeMonthLabels = getFeeMonthLabels();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirectClass = trim($_POST['class_name'] ?? $_GET['class'] ?? '');
    if ($action === 'add_head') {
        $name = trim($_POST['head_name'] ?? '');
        $isOptional = !empty($_POST['is_optional']);
        $isOneTime = !empty($_POST['is_one_time']);
        if ($name !== '') {
            try {
                addFeeHead($pdo, $name, $isOptional, $isOneTime);
                $_SESSION['success_msg'] = 'Fee head added.';
            } catch (InvalidArgumentException $e) {
                $_SESSION['error_msg'] = $e->getMessage();
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'Fee head already exists.';
            }
        }
    } elseif ($action === 'update_head') {
        $headId = (int) ($_POST['head_id'] ?? 0);
        $name = trim($_POST['head_name'] ?? '');
        $isOptional = !empty($_POST['is_optional']);
        $isOneTime = !empty($_POST['is_one_time']);
        if ($headId > 0 && $name !== '') {
            try {
                if (updateFeeHead($pdo, $headId, $name, $isOptional, $isOneTime)) {
                    $_SESSION['success_msg'] = 'Fee head updated.';
                } else {
                    $_SESSION['error_msg'] = 'Fee head not found.';
                }
            } catch (InvalidArgumentException $e) {
                $_SESSION['error_msg'] = $e->getMessage();
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'Another fee head with this name already exists.';
            }
        }
    } elseif ($action === 'delete_head') {
        $headId = (int) ($_POST['head_id'] ?? 0);
        $result = deleteFeeHead($pdo, $headId);
        if ($result['ok']) {
            $_SESSION['success_msg'] = $result['message'];
        } else {
            $_SESSION['error_msg'] = $result['message'];
        }
    } elseif ($action === 'save_structure') {
        $className = trim($_POST['class_name'] ?? '');
        $amounts = $_POST['amount'] ?? [];
        if ($className !== '') {
            saveClassFeeStructure($pdo, $className, $amounts, $sessionId);
            $_SESSION['success_msg'] = 'Monthly fee structure saved for ' . $className;
            $redirectClass = $className;
        }
    }
    header('Location: fees.php' . ($redirectClass !== '' ? '?class=' . urlencode($redirectClass) : ''));
    exit;
}

function feeHeadPresentation($head) {
    $name = is_array($head) ? (string) ($head['name'] ?? '') : (string) $head;
    $isOptional = is_array($head) ? !empty($head['is_optional']) : false;
    $isOneTime = is_array($head) ? !empty($head['is_one_time']) : false;
    $meta = ['icon' => 'fa-tags', 'tone' => 'slate'];
    if (stripos($name, 'tuition') !== false) {
        $meta = ['icon' => 'fa-book-open', 'tone' => 'blue'];
    } elseif (stripos($name, 'admission') !== false) {
        $meta = ['icon' => 'fa-user-plus', 'tone' => 'orange'];
    } elseif (stripos($name, 'exam') !== false) {
        $meta = ['icon' => 'fa-file-alt', 'tone' => 'indigo'];
    }
    $hints = [];
    if ($isOptional) {
        $hints[] = 'Optional — not included in default student dues';
    }
    if ($isOneTime) {
        $hints[] = 'One-time charge per session (set amount in one month only)';
    } elseif (stripos($name, 'exam') !== false) {
        $hints[] = 'Not monthly — set amount only in exam months (e.g. Sep & Feb), leave other months empty';
    }
    if (!$hints) {
        $hints[] = 'Standard fee head for this class';
    }
    return array_merge($meta, [
        'optional' => $isOptional,
        'one_time' => $isOneTime,
        'hint' => implode('. ', $hints),
    ]);
}

function formatFeeInputAmount($amount) {
    $amount = (float) $amount;
    if ($amount <= 0) {
        return '';
    }
    if (abs($amount - round($amount)) < 0.001) {
        return (string) (int) round($amount);
    }
    return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
}

function feeHeadStructureTotal(array $head, array $amountMap, array $feeMonthOrder) {
    $amounts = [];
    foreach ($feeMonthOrder as $m) {
        $amounts[] = (float) ($amountMap[$head['id']][$m] ?? 0);
    }
    if (!empty($head['is_one_time'])) {
        return $amounts ? max($amounts) : 0.0;
    }
    return array_sum($amounts);
}

$selectedClass = trim($_GET['class'] ?? '');
require_once 'includes/header.php';
$heads = getFeeHeads($pdo);
$amountMap = $selectedClass ? getClassFeeAmountMap($pdo, $selectedClass) : [];

$classSummaries = [];
$summaryStmt = $pdo->prepare(
    "SELECT fs.class_name, SUM(fs.amount) AS total, COUNT(DISTINCT fs.fee_head_id) AS cnt
     FROM fee_structures fs
     WHERE (fs.session_id = ? OR fs.session_id IS NULL) AND fs.amount > 0
     GROUP BY fs.class_name"
);
$summaryStmt->execute([$sessionId]);
foreach ($summaryStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $classSummaries[$row['class_name']] = $row;
}

$structureTotal = 0;
$activeHeadCount = 0;
$monthTotals = array_fill_keys($feeMonthOrder, 0.0);
foreach ($heads as $h) {
    $headTotal = feeHeadStructureTotal($h, $amountMap, $feeMonthOrder);
    foreach ($feeMonthOrder as $m) {
        $monthTotals[$m] += (float) ($amountMap[$h['id']][$m] ?? 0);
    }
    $structureTotal += $headTotal;
    if ($headTotal > 0) {
        $activeHeadCount++;
    }
}
$configuredClassCount = count($classSummaries);
$currentMonth = (int) date('n');
$currentMonthIndex = array_search($currentMonth, $feeMonthOrder, true);
$prevMonthForCurrent = ($currentMonthIndex !== false && $currentMonthIndex > 0)
    ? $feeMonthOrder[$currentMonthIndex - 1]
    : null;
$prevMonthByMonth = [];
foreach ($feeMonthOrder as $idx => $m) {
    if ($idx > 0) {
        $prevMonthByMonth[$m] = $feeMonthOrder[$idx - 1];
    }
}
?>

<div class="content-top-bar">
    <div class="content-top-main">
        <div class="content-top-icon icon-orange"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="content-top-title">
            <h2>Fee Structure</h2>
            <p class="content-top-breadcrumb"><a href="dashboard.php">Dashboard</a><i class="fas fa-chevron-right"></i><span>Fees</span></p>
        </div>
    </div>
    <div class="content-top-actions">
        <a href="fee_reports.php" class="btn-header-action btn-header-outline"><i class="fas fa-chart-line"></i> Reports</a>
        <a href="fee_collect.php" class="btn-header-action btn-header-primary"><i class="fas fa-money-bill-wave"></i> Collect Fee</a>
    </div>
</div>

<div class="fs-hero">
    <div class="fs-hero-main">
        <p class="fs-hero-label"><i class="fas fa-sliders-h"></i> Fee configuration</p>
        <h3>Set class-wise monthly fee amounts</h3>
        <p>Define fee heads once, then assign amounts per class for each month (Apr–Mar) in session <?php echo htmlspecialchars($session['name'] ?? '-'); ?>.</p>
    </div>
    <div class="fs-hero-stats">
        <div class="fs-hero-stat"><span>Fee heads</span><strong><?php echo count($heads); ?></strong></div>
        <div class="fs-hero-stat"><span>Classes set</span><strong><?php echo $configuredClassCount; ?></strong></div>
        <?php if ($selectedClass): ?>
        <div class="fs-hero-stat is-highlight"><span><?php echo htmlspecialchars($selectedClass); ?> annual</span><strong>₹<?php echo number_format($structureTotal, 0); ?></strong></div>
        <?php else: ?>
        <div class="fs-hero-stat"><span>Session</span><strong><?php echo htmlspecialchars($session['name'] ?? '-'); ?></strong></div>
        <?php endif; ?>
    </div>
</div>

<div class="fs-quick-links">
    <a href="fee_collect.php" class="fs-quick-link"><i class="fas fa-hand-holding-usd"></i><span>Collect Fee</span></a>
    <a href="fee_reports.php" class="fs-quick-link"><i class="fas fa-chart-bar"></i><span>View Reports</span></a>
    <a href="hostel_fees.php" class="fs-quick-link"><i class="fas fa-bed"></i><span>Hostel Fees</span></a>
    <a href="transport_fees.php" class="fs-quick-link"><i class="fas fa-bus"></i><span>Transport Fees</span></a>
</div>

<div class="fs-layout">
    <div class="form-section-card fs-class-card">
        <div class="fs-card-head">
            <div class="fs-card-head-icon"><i class="fas fa-school"></i></div>
            <div class="fs-card-head-text">
                <h4>Select Class</h4>
                <p>Choose a class to view or edit its monthly fee structure</p>
            </div>
            <span class="fs-class-count"><?php echo count($class_options); ?> class<?php echo count($class_options) === 1 ? '' : 'es'; ?></span>
        </div>
        <div class="fs-class-grid">
            <?php foreach ($class_options as $c):
                $summary = $classSummaries[$c] ?? null;
                $isActive = $selectedClass === $c;
                $isConfigured = (bool) $summary;
            ?>
            <a href="fees.php?class=<?php echo urlencode($c); ?>" class="fs-class-pill<?php echo $isActive ? ' is-active' : ''; ?><?php echo $isConfigured ? ' is-configured' : ' is-empty'; ?>">
                <div class="fs-class-pill-top">
                    <span class="fs-class-pill-icon"><i class="fas fa-graduation-cap"></i></span>
                    <?php if ($isConfigured): ?>
                    <span class="fs-class-pill-status is-set"><i class="fas fa-check-circle"></i> Set</span>
                    <?php else: ?>
                    <span class="fs-class-pill-status is-pending"><i class="fas fa-circle"></i> Pending</span>
                    <?php endif; ?>
                </div>
                <span class="fs-class-pill-name"><?php echo htmlspecialchars($c); ?></span>
                <?php if ($isConfigured): ?>
                <span class="fs-class-pill-amount">₹<?php echo number_format($summary['total'], 0); ?></span>
                <span class="fs-class-pill-meta"><?php echo (int) $summary['cnt']; ?> head<?php echo (int) $summary['cnt'] === 1 ? '' : 's'; ?> · annual</span>
                <?php else: ?>
                <span class="fs-class-pill-empty">Fee not configured</span>
                <span class="fs-class-pill-meta">Click to set up</span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if (!$class_options): ?>
        <div class="fs-empty-note"><i class="fas fa-info-circle"></i> No classes found. Add students or classes first.</div>
        <?php endif; ?>
    </div>

    <div class="form-section-card fs-heads-card">
        <div class="fs-card-head">
            <div class="fs-card-head-icon is-blue"><i class="fas fa-tags"></i></div>
            <div class="fs-card-head-text">
                <h4>Fee Heads</h4>
                <p>Manage categories used across all classes</p>
            </div>
            <span class="fs-head-count"><?php echo count($heads); ?> head<?php echo count($heads) === 1 ? '' : 's'; ?></span>
            <button type="button" class="btn-header-action btn-header-primary fs-add-head-btn" id="fsOpenAddHeadModal">
                <i class="fas fa-plus"></i> Add Head
            </button>
        </div>

        <?php if ($heads): ?>
        <div class="fs-head-list">
            <?php foreach ($heads as $h):
                $meta = feeHeadPresentation($h);
            ?>
            <div class="fs-head-row tone-<?php echo $meta['tone']; ?>"
                 data-head-id="<?php echo (int) $h['id']; ?>"
                 data-head-name="<?php echo htmlspecialchars($h['name'], ENT_QUOTES); ?>"
                 data-head-optional="<?php echo !empty($h['is_optional']) ? '1' : '0'; ?>"
                 data-head-onetime="<?php echo !empty($h['is_one_time']) ? '1' : '0'; ?>">
                <div class="fs-head-row-icon"><i class="fas <?php echo $meta['icon']; ?>"></i></div>
                <div class="fs-head-row-body">
                    <strong class="fs-head-row-name"><?php echo htmlspecialchars($h['name']); ?></strong>
                    <div class="fs-head-row-badges">
                        <?php if ($meta['optional']): ?>
                        <span class="fs-head-badge is-optional"><i class="fas fa-user-check"></i> Optional</span>
                        <?php endif; ?>
                        <?php if ($meta['one_time']): ?>
                        <span class="fs-head-badge is-onetime"><i class="fas fa-star"></i> One-time</span>
                        <?php endif; ?>
                        <?php if (!$meta['optional'] && !$meta['one_time']): ?>
                        <span class="fs-head-badge is-standard"><i class="fas fa-circle"></i> Standard</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="fs-head-row-actions">
                    <button type="button" class="fs-head-btn is-edit" title="Edit fee head" data-fs-edit-head>
                        <i class="fas fa-pen"></i>
                    </button>
                    <form method="POST" class="fs-head-delete-form" onsubmit="return confirm('Delete this fee head? If it has payments, it will be deactivated instead.');">
                        <input type="hidden" name="action" value="delete_head">
                        <input type="hidden" name="head_id" value="<?php echo (int) $h['id']; ?>">
                        <?php if ($selectedClass): ?>
                        <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($selectedClass); ?>">
                        <?php endif; ?>
                        <button type="submit" class="fs-head-btn is-delete" title="Delete fee head">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="fs-head-empty">
            <div class="fs-head-empty-icon"><i class="fas fa-tags"></i></div>
            <h5>No fee heads yet</h5>
            <p>Create fee categories like Tuition or Exam to assign amounts per class.</p>
            <button type="button" class="btn-header-action btn-header-primary fs-add-head-btn" onclick="document.getElementById('fsOpenAddHeadModal').click();">
                <i class="fas fa-plus"></i> Add First Head
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="fs-modal" id="fsEditHeadModal" aria-hidden="true">
    <div class="fs-modal-backdrop" data-fs-modal-close="edit"></div>
    <div class="fs-modal-panel" role="dialog" aria-modal="true" aria-labelledby="fsEditHeadModalTitle">
        <div class="fs-modal-header">
            <div class="fs-modal-header-icon is-edit"><i class="fas fa-pen"></i></div>
            <div>
                <h3 id="fsEditHeadModalTitle">Edit Fee Head</h3>
                <p>Update fee category name and type</p>
            </div>
            <button type="button" class="fs-modal-close" data-fs-modal-close="edit" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="fs-modal-form" id="fsEditHeadForm">
            <input type="hidden" name="action" value="update_head">
            <input type="hidden" name="head_id" id="fsEditHeadId" value="">
            <?php if ($selectedClass): ?>
            <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($selectedClass); ?>">
            <?php endif; ?>
            <div class="fs-modal-body">
                <div class="form-field">
                    <label for="fsEditHeadName">Fee head name</label>
                    <input type="text" name="head_name" id="fsEditHeadName" class="form-input" required>
                </div>
                <div class="form-field">
                    <label>Fee type</label>
                    <div class="fs-head-toggle-group is-modal">
                        <label class="fs-head-toggle is-optional">
                            <input type="checkbox" name="is_optional" id="fsEditHeadOptional" value="1">
                            <span class="fs-head-toggle-ui"><i class="fas fa-user-check"></i> Optional</span>
                        </label>
                        <label class="fs-head-toggle is-onetime">
                            <input type="checkbox" name="is_one_time" id="fsEditHeadOneTime" value="1">
                            <span class="fs-head-toggle-ui"><i class="fas fa-star"></i> One-time</span>
                        </label>
                    </div>
                    <p class="fs-modal-field-hint"><i class="fas fa-lightbulb"></i> <strong>Optional</strong> heads are not charged by default. <strong>One-time</strong> for admission charges. Hostel fees are under Hostel menu.</p>
                </div>
            </div>
            <div class="fs-modal-footer">
                <button type="button" class="btn-header-action btn-header-outline" data-fs-modal-close="edit">Cancel</button>
                <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-check"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="fs-modal" id="fsAddHeadModal" aria-hidden="true">
    <div class="fs-modal-backdrop" data-fs-modal-close="add"></div>
    <div class="fs-modal-panel" role="dialog" aria-modal="true" aria-labelledby="fsAddHeadModalTitle">
        <div class="fs-modal-header">
            <div class="fs-modal-header-icon"><i class="fas fa-plus"></i></div>
            <div>
                <h3 id="fsAddHeadModalTitle">Add Fee Head</h3>
                <p>Create a new fee category for all classes</p>
            </div>
            <button type="button" class="fs-modal-close" data-fs-modal-close="add" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="fs-modal-form">
            <input type="hidden" name="action" value="add_head">
            <?php if ($selectedClass): ?>
            <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($selectedClass); ?>">
            <?php endif; ?>
            <div class="fs-modal-body">
                <div class="form-field">
                    <label>Fee head name</label>
                    <input type="text" name="head_name" class="form-input" placeholder="e.g. Lab Fee, Sports Fee, Library Fee" required>
                </div>
                <div class="form-field">
                    <label>Fee type</label>
                    <div class="fs-head-toggle-group is-modal">
                        <label class="fs-head-toggle is-optional">
                            <input type="checkbox" name="is_optional" value="1">
                            <span class="fs-head-toggle-ui"><i class="fas fa-user-check"></i> Optional</span>
                        </label>
                        <label class="fs-head-toggle is-onetime">
                            <input type="checkbox" name="is_one_time" value="1">
                            <span class="fs-head-toggle-ui"><i class="fas fa-star"></i> One-time</span>
                        </label>
                    </div>
                    <p class="fs-modal-field-hint"><i class="fas fa-lightbulb"></i> <strong>Optional</strong> heads are not charged by default. <strong>One-time</strong> for admission charges. Hostel fees are under Hostel menu.</p>
                </div>
            </div>
            <div class="fs-modal-footer">
                <button type="button" class="btn-header-action btn-header-outline" data-fs-modal-close="add">Cancel</button>
                <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-plus"></i> Add Fee Head</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedClass): ?>
<form method="POST" class="fs-structure-form">
    <input type="hidden" name="action" value="save_structure">
    <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($selectedClass); ?>">
    <div class="form-section-card fs-structure-card section-mb">
        <div class="fs-structure-head">
            <div class="fs-structure-title">
                <div class="fs-structure-icon"><i class="fas fa-calendar-alt"></i></div>
                <div>
                    <h4><?php echo htmlspecialchars($selectedClass); ?> — Monthly Fee Structure</h4>
                    <p><?php echo $activeHeadCount; ?> of <?php echo count($heads); ?> heads configured · Apr–Mar · Session <?php echo htmlspecialchars($session['name'] ?? '-'); ?></p>
                </div>
            </div>
            <div class="fs-structure-actions">
                <span class="fs-total-pill">Annual ₹<?php echo number_format($structureTotal, 0); ?></span>
                <?php if ($prevMonthForCurrent): ?>
                <button type="button" class="btn-header-action btn-header-outline fs-copy-last-month-btn"
                        data-from="<?php echo $prevMonthForCurrent; ?>"
                        data-to="<?php echo $currentMonth; ?>"
                        title="Copy <?php echo htmlspecialchars($feeMonthLabels[$prevMonthForCurrent]); ?> amounts to <?php echo htmlspecialchars($feeMonthLabels[$currentMonth]); ?>">
                    <i class="fas fa-clone"></i> Copy Last Month
                </button>
                <?php endif; ?>
                <button type="button" class="btn-header-action btn-header-outline fs-fill-all-btn" title="Copy April amount to all months for every head"><i class="fas fa-copy"></i> Fill All Months</button>
                <button type="submit" class="btn-header-action btn-header-primary"><i class="fas fa-save"></i> Save Structure</button>
            </div>
        </div>

        <div class="fs-month-table-wrap">
            <table class="fs-month-table">
                <thead>
                    <tr class="fs-month-header-row">
                        <th class="fs-month-head-col">Fee Head</th>
                        <?php foreach ($feeMonthOrder as $m): ?>
                        <th class="fs-month-col<?php echo $m === $currentMonth ? ' is-current' : ''; ?>" data-month="<?php echo $m; ?>">
                            <span class="fs-month-label"><?php echo htmlspecialchars($feeMonthLabels[$m]); ?></span>
                            <?php if ($m === $currentMonth): ?><em class="fs-month-now">Now</em><?php endif; ?>
                            <?php if (!empty($prevMonthByMonth[$m])): ?>
                            <button type="button" class="fs-col-copy-btn"
                                    data-from="<?php echo $prevMonthByMonth[$m]; ?>"
                                    data-to="<?php echo $m; ?>"
                                    title="Copy from <?php echo htmlspecialchars($feeMonthLabels[$prevMonthByMonth[$m]]); ?>">
                                <i class="fas fa-arrow-left"></i>
                            </button>
                            <?php endif; ?>
                        </th>
                        <?php endforeach; ?>
                        <th class="fs-month-row-total-col">Annual</th>
                        <th class="fs-month-actions-col"></th>
                    </tr>
                </thead>
                <?php if ($structureTotal > 0): ?>
                <tbody class="fs-month-subtotal-body">
                    <tr class="fs-month-subtotal-row">
                        <td class="fs-month-head-col"><strong>Month total</strong></td>
                        <?php foreach ($feeMonthOrder as $m): ?>
                        <td class="fs-month-col fs-month-subtotal<?php echo $m === $currentMonth ? ' is-current' : ''; ?>" data-month="<?php echo $m; ?>">₹<?php echo number_format($monthTotals[$m], 0); ?></td>
                        <?php endforeach; ?>
                        <td class="fs-month-row-total-col fs-month-grand-total"><strong>₹<?php echo number_format($structureTotal, 0); ?></strong></td>
                        <td class="fs-month-actions-col"></td>
                    </tr>
                </tbody>
                <?php endif; ?>
                <tbody class="fs-month-body">
                    <?php foreach ($heads as $h):
                        $meta = feeHeadPresentation($h);
                        $headTotal = feeHeadStructureTotal($h, $amountMap, $feeMonthOrder);
                    ?>
                    <tr class="fs-month-row tone-<?php echo $meta['tone']; ?><?php echo !empty($h['is_one_time']) ? ' is-one-time' : ''; ?>"
                        data-head-id="<?php echo (int) $h['id']; ?>"
                        data-one-time="<?php echo !empty($h['is_one_time']) ? '1' : '0'; ?>">
                        <td class="fs-month-head-col">
                            <div class="fs-month-head-cell">
                                <span class="fs-month-head-icon"><i class="fas <?php echo $meta['icon']; ?>"></i></span>
                                <div>
                                    <strong><?php echo htmlspecialchars($h['name']); ?></strong>
                                    <div class="fs-head-badge-row">
                                        <?php if ($meta['optional']): ?>
                                        <span class="fs-optional-badge"><i class="fas fa-user-check"></i> Optional</span>
                                        <?php endif; ?>
                                        <?php if ($meta['one_time']): ?>
                                        <span class="fs-onetime-badge"><i class="fas fa-star"></i> One-time</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <?php foreach ($feeMonthOrder as $m):
                            $amount = (float) ($amountMap[$h['id']][$m] ?? 0);
                        ?>
                        <td class="fs-month-col<?php echo $m === $currentMonth ? ' is-current' : ''; ?>">
                            <div class="fs-month-cell">
                                <input type="number" step="0.01" min="0"
                                       name="amount[<?php echo (int) $h['id']; ?>][<?php echo $m; ?>]"
                                       class="fs-month-input"
                                       data-month="<?php echo $m; ?>"
                                       value="<?php echo htmlspecialchars(formatFeeInputAmount($amount)); ?>"
                                       placeholder="0"
                                       inputmode="decimal"
                                       aria-label="<?php echo htmlspecialchars($h['name'] . ' ' . $feeMonthLabels[$m]); ?>">
                            </div>
                        </td>
                        <?php endforeach; ?>
                        <td class="fs-month-row-total-col">
                            <span class="fs-row-total">₹<?php echo number_format($headTotal, 0); ?></span>
                        </td>
                        <td class="fs-month-actions-col">
                            <?php if (empty($h['is_one_time'])): ?>
                            <div class="fs-row-action-btns">
                                <button type="button" class="fs-row-fill-btn" title="Copy April amount to all months"><i class="fas fa-arrows-alt-h"></i></button>
                                <button type="button" class="fs-row-chain-btn" title="Copy each month from previous month (Apr → May → Jun…)"><i class="fas fa-angle-double-right"></i></button>
                            </div>
                            <?php else: ?>
                            <span class="fs-onetime-note" title="One-time: set amount in one month only"><i class="fas fa-star"></i></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="fs-structure-foot">
            <div class="fs-foot-note">
                <i class="fas fa-info-circle"></i>
                Set different amounts per month. <strong>Exam / term fees:</strong> amount sirf un 2 months mein daalo jab exam ho (baaki months khali). <strong>One-time</strong> sirf ek baar wale charges ke liye (admission).
            </div>
            <div class="fs-foot-total">
                <span>Annual total</span>
                <strong>₹<?php echo number_format($structureTotal, 0); ?></strong>
            </div>
        </div>
    </div>
</form>
<?php else: ?>
<div class="form-section-card fs-pick-class section-mb">
    <div class="fs-pick-class-icon"><i class="fas fa-hand-pointer"></i></div>
    <h4>Select a class above</h4>
    <p>Pick a class from the grid to configure monthly tuition, exam and other school fees.</p>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.fs-structure-form');
    if (!form) return;

    var totalEl = form.querySelector('.fs-total-pill');
    var footTotal = form.querySelector('.fs-foot-total strong');
    var grandTotalEl = form.querySelector('.fs-month-grand-total strong');
    var monthSubtotals = form.querySelectorAll('.fs-month-subtotal');
    var monthOrder = <?php echo json_encode(array_values($feeMonthOrder)); ?>;
    var monthLabels = <?php echo json_encode($feeMonthLabels); ?>;

    function parseVal(input) {
        return parseFloat(input.value) || 0;
    }

    function formatInputVal(val) {
        if (!val || val <= 0) return '';
        if (Math.abs(val - Math.round(val)) < 0.001) return String(Math.round(val));
        return val.toFixed(2);
    }

    function copyMonthColumn(fromMonth, toMonth, skipOneTime) {
        form.querySelectorAll('.fs-month-row').forEach(function (row) {
            if (skipOneTime && row.getAttribute('data-one-time') === '1') return;
            var fromInput = row.querySelector('.fs-month-input[data-month="' + fromMonth + '"]');
            var toInput = row.querySelector('.fs-month-input[data-month="' + toMonth + '"]');
            if (fromInput && toInput) {
                toInput.value = fromInput.value;
            }
        });
        updateTotals();
    }

    function confirmCopy(fromMonth, toMonth) {
        var fromLabel = monthLabels[fromMonth] || fromMonth;
        var toLabel = monthLabels[toMonth] || toMonth;
        return confirm('Copy all fee amounts from ' + fromLabel + ' to ' + toLabel + '? Unsaved changes in ' + toLabel + ' will be replaced.');
    }

    function formatInr(n) {
        return '₹' + Math.round(n).toLocaleString('en-IN');
    }

    function updateTotals() {
        var annual = 0;
        var monthSums = {};
        monthOrder.forEach(function (m) { monthSums[m] = 0; });

        form.querySelectorAll('.fs-month-row').forEach(function (row) {
            var rowSum = 0;
            var isOneTime = row.getAttribute('data-one-time') === '1';
            var values = [];
            row.querySelectorAll('.fs-month-input').forEach(function (input) {
                var v = parseVal(input);
                values.push(v);
                var m = input.getAttribute('data-month');
                if (monthSums[m] !== undefined) monthSums[m] += v;
            });
            if (isOneTime) {
                rowSum = values.length ? Math.max.apply(null, values) : 0;
            } else {
                values.forEach(function (v) { rowSum += v; });
            }
            var rowTotalEl = row.querySelector('.fs-row-total');
            if (rowTotalEl) rowTotalEl.textContent = formatInr(rowSum);
            annual += rowSum;
        });

        monthSubtotals.forEach(function (cell) {
            var m = cell.getAttribute('data-month');
            cell.textContent = formatInr(monthSums[m] || 0);
        });

        var formatted = formatInr(annual);
        if (totalEl) totalEl.textContent = 'Annual ' + formatted;
        if (footTotal) footTotal.textContent = formatted;
        if (grandTotalEl) grandTotalEl.textContent = formatted;
    }

    function fillRow(row) {
        var inputs = row.querySelectorAll('.fs-month-input');
        if (!inputs.length) return;
        var val = parseVal(inputs[0]) || parseVal(inputs[1]) || 0;
        inputs.forEach(function (input) {
            input.value = formatInputVal(val);
        });
        updateTotals();
    }

    function chainRow(row) {
        var inputs = Array.prototype.slice.call(row.querySelectorAll('.fs-month-input'));
        if (inputs.length < 2) return;
        for (var i = 1; i < inputs.length; i++) {
            inputs[i].value = inputs[i - 1].value;
        }
        updateTotals();
    }

    form.querySelectorAll('.fs-month-input').forEach(function (input) {
        input.addEventListener('input', updateTotals);
    });

    form.querySelectorAll('.fs-row-fill-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillRow(btn.closest('.fs-month-row'));
        });
    });

    form.querySelectorAll('.fs-row-chain-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            chainRow(btn.closest('.fs-month-row'));
        });
    });

    form.querySelectorAll('.fs-col-copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var fromMonth = parseInt(btn.getAttribute('data-from'), 10);
            var toMonth = parseInt(btn.getAttribute('data-to'), 10);
            if (!fromMonth || !toMonth) return;
            if (!confirmCopy(fromMonth, toMonth)) return;
            copyMonthColumn(fromMonth, toMonth, true);
        });
    });

    var copyLastMonthBtn = form.querySelector('.fs-copy-last-month-btn');
    if (copyLastMonthBtn) {
        copyLastMonthBtn.addEventListener('click', function () {
            var fromMonth = parseInt(copyLastMonthBtn.getAttribute('data-from'), 10);
            var toMonth = parseInt(copyLastMonthBtn.getAttribute('data-to'), 10);
            if (!fromMonth || !toMonth) return;
            if (!confirmCopy(fromMonth, toMonth)) return;
            copyMonthColumn(fromMonth, toMonth, true);
        });
    }

    var fillAllBtn = form.querySelector('.fs-fill-all-btn');
    if (fillAllBtn) {
        fillAllBtn.addEventListener('click', function () {
            form.querySelectorAll('.fs-month-row:not(.is-one-time)').forEach(fillRow);
        });
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var addModal = document.getElementById('fsAddHeadModal');
    var editModal = document.getElementById('fsEditHeadModal');
    var openAddBtn = document.getElementById('fsOpenAddHeadModal');
    var editHeadId = document.getElementById('fsEditHeadId');
    var editHeadName = document.getElementById('fsEditHeadName');
    var editHeadOptional = document.getElementById('fsEditHeadOptional');
    var editHeadOneTime = document.getElementById('fsEditHeadOneTime');

    function openModal(modal, focusEl) {
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('fs-modal-open');
        if (focusEl) {
            setTimeout(function () { focusEl.focus(); focusEl.select && focusEl.select(); }, 120);
        }
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.fs-modal.is-open')) {
            document.body.classList.remove('fs-modal-open');
        }
    }

    if (openAddBtn && addModal) {
        openAddBtn.addEventListener('click', function () {
            openModal(addModal, addModal.querySelector('input[name="head_name"]'));
        });
    }

    document.querySelectorAll('[data-fs-edit-head]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.fs-head-row');
            if (!row || !editModal) return;
            if (editHeadId) editHeadId.value = row.getAttribute('data-head-id') || '';
            if (editHeadName) editHeadName.value = row.getAttribute('data-head-name') || '';
            if (editHeadOptional) editHeadOptional.checked = row.getAttribute('data-head-optional') === '1';
            if (editHeadOneTime) editHeadOneTime.checked = row.getAttribute('data-head-onetime') === '1';
            openModal(editModal, editHeadName);
        });
    });

    document.querySelectorAll('[data-fs-modal-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            var target = el.getAttribute('data-fs-modal-close');
            if (target === 'add') closeModal(addModal);
            else if (target === 'edit') closeModal(editModal);
            else {
                closeModal(addModal);
                closeModal(editModal);
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        closeModal(addModal);
        closeModal(editModal);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
