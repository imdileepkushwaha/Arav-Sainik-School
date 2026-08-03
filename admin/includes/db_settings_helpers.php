<?php
// admin/includes/db_settings_helpers.php

require_once __DIR__ . '/../../includes/db_connection.php';

function getDatabaseSettingsForm(): array {
    return getDbProfilesConfig();
}

function buildDatabaseSettingsFromPost(array $post): array {
    $config = getDbProfilesConfig();
    $mode = $post['db_mode'] ?? $config['mode'];
    if (!in_array($mode, ['online', 'offline', 'auto'], true)) {
        $mode = 'auto';
    }

    $buildProfile = function (string $key) use ($post, $config) {
        $prefix = 'db_' . $key . '_';
        $current = $config[$key];
        $password = $post[$prefix . 'password'] ?? null;
        return [
            'host' => trim($post[$prefix . 'host'] ?? $current['host']),
            'port' => (int) ($post[$prefix . 'port'] ?? $current['port']),
            'dbname' => trim($post[$prefix . 'dbname'] ?? $current['dbname']),
            'username' => trim($post[$prefix . 'username'] ?? $current['username']),
            'password' => ($password !== null && $password !== '') ? $password : $current['password'],
            'label' => trim($post[$prefix . 'label'] ?? $current['label']),
        ];
    };

    return [
        'mode' => $mode,
        'online' => $buildProfile('online'),
        'offline' => $buildProfile('offline'),
    ];
}

function databaseProfileFromPost(array $post, string $profileKey): array {
    $config = getDbProfilesConfig();
    $prefix = 'db_' . $profileKey . '_';
    $current = $config[$profileKey];
    $password = $post[$prefix . 'password'] ?? null;

    return [
        'host' => trim($post[$prefix . 'host'] ?? $current['host']),
        'port' => (int) ($post[$prefix . 'port'] ?? $current['port']),
        'dbname' => trim($post[$prefix . 'dbname'] ?? $current['dbname']),
        'username' => trim($post[$prefix . 'username'] ?? $current['username']),
        'password' => ($password !== null && $password !== '') ? $password : $current['password'],
        'label' => trim($post[$prefix . 'label'] ?? $current['label']),
    ];
}
