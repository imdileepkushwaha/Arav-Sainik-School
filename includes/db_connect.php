<?php
// includes/db_connect.php

require_once __DIR__ . '/db_connection.php';

$dbConnection = connectDatabase();
$pdo = $dbConnection['pdo'];
$db_active_profile = $dbConnection['profile'];
$db_connection_mode = $dbConnection['mode'];
$db_active_profile_label = $dbConnection['label'];

date_default_timezone_set('Asia/Kolkata');
