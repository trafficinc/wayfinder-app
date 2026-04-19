<?php

declare(strict_types=1);

return [
    'default' => $_ENV['QUEUE_CONNECTION'] ?? 'sync',
    'max_attempts' => (int) ($_ENV['QUEUE_MAX_ATTEMPTS'] ?? 3),
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        'file' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../' . ltrim($_ENV['QUEUE_FILE_PATH'] ?? 'storage/framework/queue', '/'),
            'failed_path' => __DIR__ . '/../storage/framework/queue/failed',
        ],
        'database' => [
            'driver' => 'database',
            'table' => $_ENV['QUEUE_DATABASE_TABLE'] ?? 'jobs',
        ],
        'redis' => [
            'driver' => 'redis',
            'host' => $_ENV['QUEUE_REDIS_HOST'] ?? '127.0.0.1',
            'port' => (int) ($_ENV['QUEUE_REDIS_PORT'] ?? 6379),
            'database' => (int) ($_ENV['QUEUE_REDIS_DATABASE'] ?? 0),
            'password' => $_ENV['QUEUE_REDIS_PASSWORD'] ?? null,
            'prefix' => $_ENV['QUEUE_REDIS_PREFIX'] ?? 'wayfinder_queue',
            'timeout' => (float) ($_ENV['QUEUE_REDIS_TIMEOUT'] ?? 1.5),
        ],
    ],
];
