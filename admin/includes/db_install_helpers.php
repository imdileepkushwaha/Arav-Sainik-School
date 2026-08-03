<?php
// admin/includes/db_install_helpers.php — one-click database installer

require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/settings_helpers.php';
require_once __DIR__ . '/erp_helpers.php';

function isDatabaseInstalled(PDO $pdo): bool {
    try {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM `admin_users`')->fetchColumn();
        return $count > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function ensureAdminUsersTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $count = (int) $pdo->query('SELECT COUNT(*) FROM `admin_users`')->fetchColumn();
    if ($count === 0) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->prepare('INSERT INTO `admin_users` (`username`, `password`) VALUES (?, ?)')
            ->execute(['admin', $hash]);
    }
}

function ensureStudentsBaseTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `students` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `ad_no` varchar(40) NOT NULL,
        `name` varchar(100) NOT NULL,
        `roll` varchar(20) NOT NULL,
        `class` varchar(50) NOT NULL,
        `dob` varchar(20) NOT NULL,
        `gender` enum('Male','Female','Other') NOT NULL,
        `mobile` varchar(20) NOT NULL,
        `category` varchar(50) NOT NULL,
        `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
        `avatar_id` int(11) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function installFullDatabase(PDO $pdo): array {
    ensureAdminUsersTable($pdo);
    ensureStudentsBaseTable($pdo);
    ensureTeacherSchema($pdo);
    ensureStudentSchema($pdo);
    ensureErpSchema($pdo);
    ensureSettingsSchema($pdo);

    return [
        'ok' => true,
        'message' => 'All database tables created successfully.',
        'default_login' => [
            'username' => 'admin',
            'password' => 'admin123',
        ],
    ];
}

function getSetupProfileKey(): string {
    return isLocalEnvironment() ? 'offline' : 'online';
}

function runDatabaseSetup(): array {
    $config = getDbProfilesConfig();
    $profileKey = getSetupProfileKey();
    $profile = $config[$profileKey] ?? [];

    if (trim($profile['host'] ?? '') === '' || trim($profile['dbname'] ?? '') === '') {
        return [
            'ok' => false,
            'error' => ucfirst($profileKey) . ' database is not configured. Set host and database name first.',
            'profile' => $profileKey,
        ];
    }

    try {
        ensureDatabaseExists($profile);
        $pdo = createDbPdo($profile);

        if (isDatabaseInstalled($pdo)) {
            return [
                'ok' => true,
                'already_installed' => true,
                'message' => 'Database is already set up. You can sign in with your admin account.',
                'profile' => $profileKey,
            ];
        }

        // Tables may exist (e.g. partial import) but admin user missing — repair install.
        $result = installFullDatabase($pdo);
        $result['profile'] = $profileKey;
        $result['already_installed'] = false;
        return $result;
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => $e->getMessage(),
            'profile' => $profileKey,
        ];
    }
}
