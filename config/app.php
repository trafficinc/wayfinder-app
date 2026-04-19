<?php

declare(strict_types=1);

use Wayfinder\Auth\Authenticate;
use Wayfinder\Auth\Can;
use Wayfinder\Http\VerifyCsrfToken;
use Wayfinder\Security\ValidateSignature;
use Wayfinder\Session\StartSession;

return [
    'name' => $_ENV['APP_NAME'] ?? 'Stackmint',
    'key' => $_ENV['APP_KEY'] ?? null,
    'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    'version' => $_ENV['APP_VER'] ?? '0.1.0',
    'environment' => $_ENV['APP_ENV'] ?? 'local',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOL),
    'controllers_namespace' => 'App\\Controllers\\',
    'views_path' => __DIR__ . '/../app/Views',
    'views_extension' => 'php',
    'config_cache_path' => __DIR__ . '/../bootstrap/cache/config.php',
    'routes_cache_path' => __DIR__ . '/../bootstrap/cache/routes.php',
    'logs_path' => __DIR__ . '/../storage/logs',
    'middleware_aliases' => [
        'session' => StartSession::class,
        'csrf' => VerifyCsrfToken::class,
        'auth' => Authenticate::class,
        'can' => Can::class,
        'signed' => ValidateSignature::class,
    ],
    'middleware_groups' => [
        'web' => ['session', 'csrf'],
        'api' => ['session'],
    ],
];
