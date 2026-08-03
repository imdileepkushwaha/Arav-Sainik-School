<?php
// admin/db_setup.php — one-click database installer (JSON API)

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

require_once __DIR__ . '/includes/db_install_helpers.php';

echo json_encode(runDatabaseSetup());
