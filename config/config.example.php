<?php
declare(strict_types=1);
/**
 * Copy to config.php and edit. Never commit real credentials.
 */
return [
    'app' => [
        'name' => 'FundBoard',
        'url' => 'https://example.com',
        'timezone' => 'Asia/Tehran',
        'debug' => false,
    ],
    'database' => [
        'enabled' => false,
        'host' => 'localhost',
        'name' => '',
        'user' => '',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'cache' => ['ttl' => 90],
    'sources' => [
        'tindex_base_url' => 'https://tindex.app',
        'tindex_token' => '',
        // Optional server-side HTTP bridge URLs. Do not put MCP stdio commands here.
        'tsetmc_bridge_url' => '',
        'codal_bridge_url' => '',
        'crypto_bridge_url' => '',
        'bridge_bearer_token' => '',
    ],
];
