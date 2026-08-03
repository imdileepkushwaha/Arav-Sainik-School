<?php
/**
 * Default database profiles — copied to db_profiles.local.php on first save.
 * mode: online | offline | auto
 * auto = local machine → offline DB first; live server → online DB first
 */
return [
    'mode' => 'auto',
    'online' => [
        'host' => '',
        'port' => 3306,
        'dbname' => '',
        'username' => '',
        'password' => '',
        'label' => 'Cloud / Online Server',
    ],
    'offline' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'schoolerp_db',
        'username' => 'root',
        'password' => '',
        'label' => 'Local / Offline (XAMPP)',
    ],
];
