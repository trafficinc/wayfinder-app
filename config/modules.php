<?php

declare(strict_types=1);

return [
    'path' => __DIR__ . '/../Modules',
    'cache' => filter_var($_ENV['MODULES_CACHE'] ?? false, FILTER_VALIDATE_BOOL),
    'cache_path' => __DIR__ . '/../bootstrap/cache/modules.php',
    'packages' => [
        'auth' => [
            'package' => 'wayfinder/auth',
            'module' => 'Auth',
            'repository' => 'https://github.com/trafficinc/wayfinder-auth',
        ],
    ],
    'enabled' => [],
    'order' => [],
];
