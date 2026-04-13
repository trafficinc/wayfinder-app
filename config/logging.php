<?php

declare(strict_types=1);

return [
    'default' => 'file',
    'channels' => [
        'file' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../storage/logs/wayfinder.log',
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
        ],
    ],
];
