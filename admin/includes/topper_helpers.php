<?php
// admin/includes/topper_helpers.php — website toppers management

function ensureTopperSchema(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS `school_toppers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) DEFAULT NULL,
        `name` varchar(100) NOT NULL,
        `class_name` varchar(50) NOT NULL,
        `percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
        `photo` varchar(255) DEFAULT NULL,
        `title` varchar(100) DEFAULT 'Topper',
        `exam_label` varchar(120) DEFAULT NULL,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        `show_on_website` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `status_sort` (`status`,`show_on_website`,`sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function getManagedToppers(PDO $pdo, bool $websiteOnly = false): array {
    ensureTopperSchema($pdo);
    $sql = "SELECT * FROM school_toppers";
    if ($websiteOnly) {
        $sql .= " WHERE status = 'Active' AND show_on_website = 1";
    }
    $sql .= " ORDER BY sort_order ASC, percentage DESC, id DESC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function getTopperById(PDO $pdo, int $id): ?array {
    ensureTopperSchema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM school_toppers WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function getHomepageToppers(PDO $pdo, int $limit = 2): array {
    ensureTopperSchema($pdo);
    $limit = max(1, min(12, $limit));
    $stmt = $pdo->prepare(
        "SELECT * FROM school_toppers
         WHERE status = 'Active' AND show_on_website = 1
         ORDER BY sort_order ASC, percentage DESC, id DESC
         LIMIT ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        $out = [];
        foreach ($rows as $t) {
            $photo = resolveTopperPhotoUrl($t['photo'] ?? '', $t['student_id'] ?? null, $pdo);
            $out[] = [
                'name' => $t['name'],
                'class' => $t['class_name'],
                'percentage' => rtrim(rtrim(number_format((float) $t['percentage'], 2, '.', ''), '0'), '.') ?: '0',
                'photo' => $photo,
                'title' => $t['title'] ?: 'Topper',
                'exam_label' => $t['exam_label'] ?? '',
            ];
        }
        return $out;
    }

    // Fallback: latest exam analytics (legacy behaviour)
    try {
        $exam = $pdo->query("SELECT id, class_name, name FROM exams WHERE status = 'Active' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$exam || !function_exists('getExamClassAnalytics')) {
            return [];
        }
        $analytics = getExamClassAnalytics($pdo, (int) $exam['id']);
        if (!$analytics || empty($analytics['results'])) {
            return [];
        }
        $out = [];
        foreach (array_slice($analytics['results'], 0, $limit) as $row) {
            $st = $row['student'];
            $photo = '';
            if (!empty($st['photo'])) {
                $rel = ltrim((string) $st['photo'], '/');
                if (is_file(__DIR__ . '/../../admin/' . $rel) || is_file(__DIR__ . '/../' . $rel)) {
                    $photo = (strpos($rel, 'admin/') === 0) ? $rel : 'admin/' . $rel;
                }
            }
            $out[] = [
                'name' => $st['name'] ?? 'Student',
                'class' => $exam['class_name'],
                'percentage' => $row['percentage'],
                'photo' => $photo,
                'title' => 'Topper',
                'exam_label' => $exam['name'] ?? '',
            ];
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

function resolveTopperPhotoUrl(?string $photo, $studentId, PDO $pdo = null): string {
    $photo = trim((string) $photo);
    if ($photo !== '') {
        $rel = ltrim($photo, '/');
        if (strpos($rel, 'admin/') === 0) {
            return $rel;
        }
        // From website root (index.php): admin/uploads/...
        if (is_file(__DIR__ . '/../' . $rel) || is_file(__DIR__ . '/../../admin/' . $rel)) {
            return (strpos($rel, 'uploads/') === 0 || strpos($rel, 'assets/') === 0) ? 'admin/' . $rel : $rel;
        }
        return 'admin/' . $rel;
    }
    if ($studentId && $pdo) {
        $stmt = $pdo->prepare("SELECT photo FROM students WHERE id = ?");
        $stmt->execute([(int) $studentId]);
        $stPhoto = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($stPhoto !== '') {
            return 'admin/' . ltrim($stPhoto, '/');
        }
    }
    return '';
}

function uploadTopperPhoto(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return false;
    }
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return false;
    }
    $dir = __DIR__ . '/../uploads/toppers';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $name = 'topper_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return false;
    }
    return 'uploads/toppers/' . $name;
}

function searchStudentsForTopper(PDO $pdo, string $q, int $limit = 20): array {
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare(
        "SELECT id, ad_no, name, class, section, photo
         FROM students
         WHERE status = 'Active' AND (name LIKE ? OR ad_no LIKE ? OR class LIKE ?)
         ORDER BY name ASC
         LIMIT " . (int) $limit
    );
    $stmt->execute([$like, $like, $like]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
