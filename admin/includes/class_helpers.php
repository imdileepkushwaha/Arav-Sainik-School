<?php
// admin/includes/class_helpers.php

function ensureClassSchema($pdo) {
    $hadClassesTable = false;
    try {
        $pdo->query('SELECT 1 FROM school_classes LIMIT 1');
        $hadClassesTable = true;
    } catch (PDOException $e) {
        $hadClassesTable = false;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `school_classes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `class_sections` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `class_id` int(11) NOT NULL,
        `name` varchar(10) NOT NULL,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        PRIMARY KEY (`id`),
        UNIQUE KEY `class_section` (`class_id`, `name`),
        KEY `class_id` (`class_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Seed Class 1–12 only on first table creation — never re-insert after user deletes classes
    if (!$hadClassesTable) {
        seedDefaultClasses($pdo);
    }

    migrateLegacyStudentClasses($pdo);
}

function seedDefaultClasses($pdo) {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM school_classes')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $classStmt = $pdo->prepare('INSERT INTO school_classes (name, sort_order) VALUES (?, ?)');
    $sectionStmt = $pdo->prepare('INSERT INTO class_sections (class_id, name) VALUES (?, ?)');
    for ($i = 1; $i <= 12; $i++) {
        $classStmt->execute(["Class $i", $i]);
        $classId = (int) $pdo->lastInsertId();
        foreach (['A', 'B', 'C', 'D'] as $sec) {
            $sectionStmt->execute([$classId, $sec]);
        }
    }
}

function migrateLegacyStudentClasses($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, class, section FROM students WHERE class LIKE '%(%)'");
        $update = $pdo->prepare("UPDATE students SET class = ?, section = ? WHERE id = ?");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (preg_match('/^(.+?)\s*\(([A-Za-z0-9]+)\)\s*$/', $row['class'], $m)) {
                $sec = trim($row['section'] ?? '') ?: $m[2];
                $update->execute([trim($m[1]), $sec, $row['id']]);
            }
        }
    } catch (PDOException $e) {
        // students table may not exist yet
    }
}

function getClassOptions($pdo) {
    ensureClassSchema($pdo);
    return $pdo->query(
        "SELECT name FROM school_classes WHERE status = 'Active' ORDER BY sort_order ASC, name ASC"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function getAllClasses($pdo, $includeInactive = true) {
    ensureClassSchema($pdo);
    $sql = "SELECT * FROM school_classes";
    if (!$includeInactive) {
        $sql .= " WHERE status = 'Active'";
    }
    $sql .= " ORDER BY sort_order ASC, name ASC";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getClassById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM school_classes WHERE id = ?");
    $stmt->execute([(int) $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getClassIdByName($pdo, $name) {
    $stmt = $pdo->prepare("SELECT id FROM school_classes WHERE name = ?");
    $stmt->execute([trim($name)]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function getSectionOptions($pdo, $className) {
    ensureClassSchema($pdo);
    $className = trim((string) $className);
    if ($className === '') {
        return [];
    }
    $stmt = $pdo->prepare(
        "SELECT cs.name FROM class_sections cs
         INNER JOIN school_classes sc ON sc.id = cs.class_id
         WHERE sc.name = ? AND sc.status = 'Active' AND cs.status = 'Active'
         ORDER BY cs.name ASC"
    );
    $stmt->execute([$className]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $rows ?: ['A', 'B', 'C', 'D'];
}

function getSectionsForClassId($pdo, $classId) {
    $stmt = $pdo->prepare(
        "SELECT * FROM class_sections WHERE class_id = ? ORDER BY name ASC"
    );
    $stmt->execute([(int) $classId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNextClass($pdo, $current) {
    $classes = getClassOptions($pdo);
    $idx = array_search($current, $classes, true);
    if ($idx === false || $idx >= count($classes) - 1) {
        return null;
    }
    return $classes[$idx + 1];
}

function countStudentsInClass($pdo, $className) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class = ? AND status = 'Active'");
    $stmt->execute([$className]);
    return (int) $stmt->fetchColumn();
}

function validateClassAndSection($pdo, $className, $sectionName) {
    $errors = [];
    $className = trim((string) $className);
    $sectionName = trim((string) $sectionName) ?: 'A';

    if ($className === '') {
        $errors[] = 'Class is required.';
        return $errors;
    }

    ensureClassSchema($pdo);
    $classId = getClassIdByName($pdo, $className);
    if (!$classId) {
        $errors[] = 'Selected class is not valid. Please choose from the list.';
        return $errors;
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM class_sections WHERE class_id = ? AND name = ? AND status = 'Active'"
    );
    $stmt->execute([$classId, $sectionName]);
    if (!$stmt->fetch()) {
        $errors[] = 'Section "' . $sectionName . '" is not available for this class.';
    }

    return $errors;
}

function handleClassApiRequest($pdo) {
    if (!isset($_GET['action']) || $_GET['action'] !== 'sections') {
        return false;
    }
    header('Content-Type: application/json');
    $class = trim($_GET['class'] ?? '');
    echo json_encode(['sections' => $class ? getSectionOptions($pdo, $class) : []]);
    exit;
}

/** Parse CSV class value; supports "Class 1" or legacy "Class 1 (A)". */
function parseImportClassValue($classRaw, $sectionRaw = '') {
    $classRaw = trim((string) $classRaw);
    $section = strtoupper(trim((string) $sectionRaw));
    if (preg_match('/^(.+?)\s*\(([A-Za-z0-9]+)\)\s*$/', $classRaw, $m)) {
        $classRaw = trim($m[1]);
        if ($section === '') {
            $section = strtoupper($m[2]);
        }
    }
    if ($section === '') {
        $section = 'A';
    }
    return [$classRaw, $section];
}
