<?php

declare(strict_types=1);

return [
    'driver' => $_ENV['SESSION_DRIVER'] ?? 'file',
    'cookie' => $_ENV['SESSION_COOKIE'] ?? 'stackmint_session',
    'lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
    'path' => '/',
    'domain' => $_ENV['SESSION_DOMAIN'] ?? '',
    'secure' => filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOL),
    'http_only' => filter_var($_ENV['SESSION_HTTP_ONLY'] ?? true, FILTER_VALIDATE_BOOL),
    'same_site' => $_ENV['SESSION_SAME_SITE'] ?? 'Lax',
    'csrf_key' => '_csrf_token',
    'table' => $_ENV['SESSION_TABLE'] ?? 'sessions',
    'files_path' => __DIR__ . '/../storage/framework/sessions',
];
