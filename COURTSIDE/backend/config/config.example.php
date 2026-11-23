<?php
/**
 * COURTSIDE configuration TEMPLATE
 * config.php is listed in .gitignore and must never be committed.
 */
return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'courtside',
        'user'    => 'root',
        'pass'    => '',            
        'charset' => 'utf8mb4',
    ],

    // This will be used in later milestones
    'jwt_secret'      => 'random_string',
    'maplark_api_key' => 'maplark-api-key',
];
