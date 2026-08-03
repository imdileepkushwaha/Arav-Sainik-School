<?php
// includes/db_connection.php — online / offline database connection flow

function dbProfilesPath(): string {
    return __DIR__ . '/db_profiles.local.php';
}

function dbProfilesDefaultPath(): string {
    return __DIR__ . '/db_profiles.default.php';
}

function getDbProfilesConfig(): array {
    $defaults = is_file(dbProfilesDefaultPath()) ? require dbProfilesDefaultPath() : [];
    $localPath = dbProfilesPath();
    $local = is_file($localPath) ? require $localPath : [];

    $config = array_replace_recursive($defaults, is_array($local) ? $local : []);
    $config['mode'] = in_array($config['mode'] ?? '', ['online', 'offline', 'auto'], true)
        ? $config['mode']
        : 'offline';

    foreach (['online', 'offline'] as $key) {
        $config[$key] = array_merge([
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => '',
            'username' => '',
            'password' => '',
            'label' => $key === 'online' ? 'Online Server' : 'Local Server',
        ], $config[$key] ?? []);
        $config[$key]['port'] = (int) ($config[$key]['port'] ?: 3306);
    }

    return $config;
}

function saveDbProfilesConfig(array $config): bool {
    $payload = getDbProfilesConfig();
    if (isset($config['mode']) && in_array($config['mode'], ['online', 'offline', 'auto'], true)) {
        $payload['mode'] = $config['mode'];
    }
    foreach (['online', 'offline'] as $key) {
        if (!isset($config[$key]) || !is_array($config[$key])) {
            continue;
        }
        foreach (['host', 'port', 'dbname', 'username', 'password', 'label'] as $field) {
            if (array_key_exists($field, $config[$key])) {
                $payload[$key][$field] = $config[$key][$field];
            }
        }
        $payload[$key]['port'] = (int) ($payload[$key]['port'] ?: 3306);
    }

    $export = "<?php\n// Auto-generated database profiles — Admin → Settings → Database\nreturn "
        . var_export($payload, true) . ";\n";

    return file_put_contents(dbProfilesPath(), $export) !== false;
}

function dbProfileDsn(array $profile): string {
    $host = trim($profile['host'] ?? 'localhost');
    $port = (int) ($profile['port'] ?? 3306);
    $dbname = trim($profile['dbname'] ?? '');
    return "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
}

function dbProfileServerDsn(array $profile): string {
    $host = trim($profile['host'] ?? 'localhost');
    $port = (int) ($profile['port'] ?? 3306);
    return "mysql:host={$host};port={$port};charset=utf8mb4";
}

function isLocalEnvironment(): bool {
    if (PHP_SAPI === 'cli') {
        return true;
    }

    $host = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
    $host = preg_replace('/:\d+$/', '', $host) ?: 'localhost';

    if (in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true)) {
        return true;
    }

    if (preg_match('/\.(local|test|localhost)$/', $host)) {
        return true;
    }

    $docRoot = strtolower($_SERVER['DOCUMENT_ROOT'] ?? '');
    foreach (['xampp', 'wamp', 'laragon', 'mamp'] as $marker) {
        if ($docRoot !== '' && strpos($docRoot, $marker) !== false) {
            return true;
        }
    }

    return false;
}

function getAutoConnectionOrder(): array {
    return isLocalEnvironment() ? ['offline', 'online'] : ['online', 'offline'];
}

function ensureDatabaseExists(array $profile): void {
    $dbname = trim($profile['dbname'] ?? '');
    if ($dbname === '') {
        throw new InvalidArgumentException('Database name is required.');
    }

    $pdo = new PDO(
        dbProfileServerDsn($profile),
        $profile['username'] ?? '',
        $profile['password'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $safeName = str_replace('`', '``', $dbname);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
}

function createDbPdo(array $profile, int $timeoutSeconds = 5): PDO {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
        $options[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = max(1, $timeoutSeconds);
    }

    return new PDO(
        dbProfileDsn($profile),
        $profile['username'] ?? '',
        $profile['password'] ?? '',
        $options
    );
}

function testDbProfile(array $profile, int $timeoutSeconds = 4): array {
    $started = microtime(true);
    if (trim($profile['host'] ?? '') === '' || trim($profile['dbname'] ?? '') === '') {
        return ['ok' => false, 'error' => 'Host and database name are required.', 'latency_ms' => 0];
    }
    try {
        $pdo = createDbPdo($profile, $timeoutSeconds);
        $pdo->query('SELECT 1');
        $latency = (int) round((microtime(true) - $started) * 1000);
        return ['ok' => true, 'error' => '', 'latency_ms' => $latency];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'latency_ms' => 0];
    }
}

function connectDatabase(array $options = []): array {
    $softFail = !empty($options['soft_fail']);
    $config = getDbProfilesConfig();
    $mode = $config['mode'];
    $order = [];

    if ($mode === 'online') {
        $order = ['online'];
    } elseif ($mode === 'offline') {
        $order = ['offline'];
    } else {
        $order = getAutoConnectionOrder();
    }

    $errors = [];
    foreach ($order as $profileKey) {
        $profile = $config[$profileKey];
        if (trim($profile['host'] ?? '') === '' || trim($profile['dbname'] ?? '') === '') {
            $errors[] = ucfirst($profileKey) . ': host or database name not configured.';
            continue;
        }
        try {
            $pdo = createDbPdo($profile, $profileKey === 'online' ? 4 : 3);
            $pdo->query('SELECT 1');
            return [
                'pdo' => $pdo,
                'profile' => $profileKey,
                'mode' => $mode,
                'label' => $profile['label'] ?? ucfirst($profileKey),
                'environment' => isLocalEnvironment() ? 'local' : 'server',
                'error' => '',
            ];
        } catch (Throwable $e) {
            $errors[] = ucfirst($profileKey) . ': ' . $e->getMessage();
        }
    }

    $message = "Database connection failed.\n\n" . implode("\n", $errors)
        . "\n\nConfigure connections in Admin → Settings → Database, or use Setup Database on the login page.";

    if ($softFail) {
        return [
            'pdo' => null,
            'profile' => null,
            'mode' => $mode,
            'label' => '',
            'environment' => isLocalEnvironment() ? 'local' : 'server',
            'error' => $message,
        ];
    }

    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    http_response_code(503);
    die(nl2br(htmlspecialchars($message)));
}

function getActiveDbProfileLabel(): string {
    global $db_active_profile, $db_active_profile_label;
    if (!empty($db_active_profile_label)) {
        return $db_active_profile_label;
    }
    return ($db_active_profile ?? 'offline') === 'online' ? 'Online DB' : 'Offline DB';
}

function isActiveDbOnline(): bool {
    global $db_active_profile;
    return ($db_active_profile ?? '') === 'online';
}
