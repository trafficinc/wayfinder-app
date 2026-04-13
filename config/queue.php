<?php

declare(strict_types=1);

return [
    'default' => 'file',
    'connections' => [
        'file' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../storage/framework/queue',
            'failed_path' => __DIR__ . '/../storage/framework/queue/failed',
        ],
    ],
];
