<?php

declare(strict_types=1);

return [
    'default' => $_ENV['MAIL_DRIVER'] ?? 'file',
    'mailers' => [
        'file' => [
            'driver' => 'file',
            'path' => __DIR__ . '/../storage/mail',
        ],
    ],
    'from' => [
        'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.com',
        'name' => $_ENV['MAIL_FROM_NAME'] ?? ($_ENV['APP_NAME'] ?? 'Wayfinder App'),
    ],
];
