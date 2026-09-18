<?php
return [
    'driver' => getenv('DB_DRIVER') ?: 'mysql',
    'host' => getenv('DB_HOST') ?: 'localhost',
    'name' => getenv('DB_NAME') ?: 'booking_system',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
    'prefix' => getenv('DB_PREFIX') ?: 'bbs_',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'connections' => [
        'saas' => [
            'driver' => 'mysql',
            'host' => getenv('SAAS_DB_HOST') ?: 'localhost',
            'name' => getenv('SAAS_DB_NAME') ?: 'booking_saas',
            'user' => getenv('SAAS_DB_USER') ?: 'root',
            'pass' => getenv('SAAS_DB_PASS') ?: '',
            'prefix' => '',
        ],
    ],
];
