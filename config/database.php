<?php

declare(strict_types=1);

$defaultSqlitePath = __DIR__ . '/../database/database.sqlite';
$configuredPath = $_ENV['DB_PATH'] ?? $defaultSqlitePath;

if (is_string($configuredPath) && $configuredPath !== ':memory:' && ! str_starts_with($configuredPath, '/')) {
    $configuredPath = __DIR__ . '/../' . ltrim($configuredPath, '/');
}

return [
    'default' => [
        'driver' => $_ENV['DB_DRIVER'] ?? 'sqlite',
        'path' => $configuredPath,
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'dbname' => $_ENV['DB_NAME'] ?? 'wayfinder_app',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
    ],
    'migrations_table' => 'migrations',
    'migrations_path' => __DIR__ . '/../database/migrations',
];
