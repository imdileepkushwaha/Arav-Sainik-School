<?php
$page_title = 'My Documents';
require_once 'includes/init.php';

$docs = getStudentDocuments($pdo, (int) $student['id']);

function sp_doc_icon($ext) {
    $ext = strtolower($ext);
    if ($ext === 'pdf') return ['fa-file-pdf', 'pdf'];
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) return ['fa-file-image', 'img'];
    if (in_array($ext, ['doc', 'docx'])) return ['fa-file-word', 'doc'];
    if (in_array($ext, ['xls', 'xlsx', 'csv'])) return ['fa-file-excel', 'xls'];
    return ['fa-file-lines', 'other'];
}

require_once 'includes/layout_header.php';
?>
<?php if (empty($docs)): ?>
<div class="sp-card">
    <div class="sp-empty"><div class="sp-empty-icon"><i class="fas fa-folder-open"></i></div><strong>No documents uploaded</strong><p>Documents uploaded by the school office (such as Aadhaar, birth certificate, marksheets) will appear here.</p></div>
</div>
<?php else: ?>
<div class="sp-doc-grid">
    <?php foreach ($docs as $d):
        $ext = pathinfo($d['file_path'], PATHINFO_EXTENSION);
        [$icon, $tone] = sp_doc_icon($ext);
        $fileUrl = '../admin/' . ltrim($d['file_path'], '/');
        $name = $d['original_name'] ?? basename($d['file_path']);
    ?>
    <div class="sp-doc-card">
        <div class="sp-doc-ic tone-<?php echo $tone; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
        <div class="sp-doc-body">
            <span class="sp-doc-type"><?php echo htmlspecialchars($d['doc_type'] ?: 'Document'); ?></span>
            <strong class="sp-doc-name" title="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?></strong>
            <small class="sp-doc-meta"><i class="fas fa-clock"></i> <?php echo !empty($d['uploaded_at']) ? date('d M Y', strtotime($d['uploaded_at'])) : '—'; ?> &middot; <?php echo strtoupper($ext ?: 'FILE'); ?></small>
        </div>
        <div class="sp-doc-actions">
            <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank" class="sp-doc-btn view" title="View"><i class="fas fa-eye"></i></a>
            <a href="<?php echo htmlspecialchars($fileUrl); ?>" download class="sp-doc-btn dl" title="Download"><i class="fas fa-download"></i></a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require_once 'includes/layout_footer.php'; ?>
