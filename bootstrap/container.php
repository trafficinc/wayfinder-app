<?php

declare(strict_types=1);

use Wayfinder\Auth\AuthManager;
use Wayfinder\Auth\Can;
use Wayfinder\Auth\Gate;
use Wayfinder\Cache\Cache;
use Wayfinder\Cache\CacheFactory;
use Wayfinder\Console\Application as ConsoleApplication;
use Wayfinder\Console\ConfigCacheCommand;
use Wayfinder\Console\ConfigClearCommand;
use Wayfinder\Console\KeyGenerateCommand;
use Wayfinder\Console\MakeControllerCommand;
use Wayfinder\Console\MakeMiddlewareCommand;
use Wayfinder\Console\MakeMigrationCommand;
use Wayfinder\Console\MakeQueueTableCommand;
use Wayfinder\Console\MakeRequestCommand;
use Wayfinder\Console\MakeSessionTableCommand;
use Wayfinder\Console\MakeViewCommand;
use Wayfinder\Console\MigrateCommand;
use Wayfinder\Console\MigrateRefreshCommand;
use Wayfinder\Console\MigrateResetCommand;
use Wayfinder\Console\MigrateRollbackCommand;
use Wayfinder\Console\MigrateStatusCommand;
use Wayfinder\Console\ModuleInstallCommand;
use Wayfinder\Console\ModuleUninstallCommand;
use Wayfinder\Console\RouteCacheCommand;
use Wayfinder\Console\RouteClearCommand;
use Wayfinder\Console\RouteListCommand;
use Wayfinder\Console\ServeCommand;
use Wayfinder\Console\TestCommand;
use Wayfinder\Contracts\Container as ContainerContract;
use Wayfinder\Contracts\EventDispatcher as EventDispatcherContract;
use Wayfinder\Database\Database;
use Wayfinder\Database\DB;
use Wayfinder\Database\MigrationRepository;
use Wayfinder\Database\Migrator;
use Wayfinder\Foundation\AppKernel;
use Wayfinder\Http\CsrfTokenManager;
use Wayfinder\Http\VerifyCsrfToken;
use Wayfinder\Logging\FileLogger;
use Wayfinder\Logging\Logger;
use Wayfinder\Logging\NullLogger;
use Wayfinder\Mail\MailFactory;
use Wayfinder\Mail\Mailer;
use Wayfinder\Module\ModuleManager;
use Wayfinder\Queue\JobDispatcher;
use Wayfinder\Queue\Queue;
use Wayfinder\Queue\QueueFactory;
use Wayfinder\Queue\Worker;
use Wayfinder\Routing\Router;
use Wayfinder\Security\Encrypter;
use Wayfinder\Security\UrlSigner;
use Wayfinder\Security\ValidateSignature;
use Wayfinder\Session\DatabaseSessionStore;
use Wayfinder\Session\FileSessionStore;
use Wayfinder\Session\SessionManager;
use Wayfinder\Session\SessionStore;
use Wayfinder\Session\StartSession;
use Wayfinder\Support\Config;
use Wayfinder\Support\Container;
use Wayfinder\Support\Env;
use Wayfinder\Support\EventDispatcher;
use Wayfinder\View\View;

require_once __DIR__ . '/../vendor/autoload.php';

Env::load(__DIR__ . '/../.env');

$configCachePath = __DIR__ . '/cache/config.php';
$config = is_file($configCachePath)
    ? new Config(require $configCachePath)
    : Config::fromDirectory(__DIR__ . '/../config');

$moduleManager = new ModuleManager(
    (string) $config->get('modules.path', __DIR__ . '/../Modules'),
    (string) $config->get('modules.cache_path', __DIR__ . '/../bootstrap/cache/modules.php'),
    (bool) $config->get('modules.cache', false),
);
$moduleManager->mergeConfig($config);

$container = new Container();
$events = new EventDispatcher();

$container->instance(Config::class, $config);
$container->instance(Container::class, $container);
$container->instance(ContainerContract::class, $container);
$container->instance(EventDispatcher::class, $events);
$container->instance(EventDispatcherContract::class, $events);
$container->instance(ModuleManager::class, $moduleManager);

$container->singleton(Encrypter::class, static fn () => new Encrypter((string) $config->get('app.key', '')));
$container->singleton(UrlSigner::class, static fn () => new UrlSigner(
    (string) $config->get('app.key', ''),
    (string) $config->get('app.url', ''),
));
$container->singleton(ValidateSignature::class, static fn (Container $container): ValidateSignature => new ValidateSignature(
    $container->get(UrlSigner::class),
));

$container->singleton(CacheFactory::class);
$container->singleton(Cache::class, static fn (Container $container): Cache => (static function () use ($config, $container): Cache {
    $default = (string) $config->get('cache.default', 'file');
    $store = $config->get("cache.stores.{$default}", []);

    return $container->get(CacheFactory::class)->make(is_array($store) ? $store : []);
})());

$container->singleton(Logger::class, static fn (): Logger => (static function () use ($config): Logger {
    $default = (string) $config->get('logging.default', 'file');
    $channel = $config->get("logging.channels.{$default}", []);

    if (! is_array($channel) || ($channel['driver'] ?? null) !== 'file') {
        return new NullLogger();
    }

    return new FileLogger(
        (string) ($channel['path'] ?? __DIR__ . '/../storage/logs/stackmint.log'),
        (string) ($channel['level'] ?? 'debug'),
    );
})());

$container->singleton(MailFactory::class);
$container->singleton(Mailer::class, static fn (Container $container): Mailer => (static function () use ($config, $container): Mailer {
    $default = (string) $config->get('mail.default', 'file');
    $mailer = $config->get("mail.mailers.{$default}", []);

    return $container->get(MailFactory::class)->make(is_array($mailer) ? $mailer : []);
})());

$container->singleton(QueueFactory::class, static fn (Container $container): QueueFactory => new QueueFactory(
    is_array($config->get('database.default')) ? $container->get(Database::class) : null,
));
$container->singleton(Queue::class, static fn (Container $container): Queue => (static function () use ($config, $container): Queue {
    $default = (string) $config->get('queue.default', 'file');
    $connection = $config->get("queue.connections.{$default}", []);

    return $container->get(QueueFactory::class)->make(is_array($connection) ? $connection : []);
})());
$container->singleton(JobDispatcher::class, static fn (Container $container): JobDispatcher => new JobDispatcher(
    $container->get(Queue::class),
    $container,
    $container->get(Logger::class),
    (string) $config->get('queue.default', 'file') === 'sync',
));
$container->singleton(Worker::class, static fn (Container $container): Worker => new Worker(
    $container->get(Queue::class),
    $container,
    $container->get(Logger::class),
    (int) $config->get('queue.max_attempts', 3),
));

$databaseConfig = $config->get('database.default');

if (is_array($databaseConfig)) {
    $container->singleton(Database::class, static fn (): Database => new Database($databaseConfig));
    DB::setResolver(static fn (): Database => $container->get(Database::class));
    $container->singleton(MigrationRepository::class, static fn (Container $container): MigrationRepository => new MigrationRepository(
        $container->get(Database::class),
        (string) $config->get('database.migrations_table', 'migrations'),
    ));
    $container->singleton(Migrator::class, static fn (Container $container): Migrator => new Migrator(
        $container->get(Database::class),
        $container->get(MigrationRepository::class),
        array_merge(
            [(string) $config->get('database.migrations_path', __DIR__ . '/../database/migrations')],
            $moduleManager->migrationPaths($config),
        ),
    ));
}

$container->singleton(FileSessionStore::class, static fn (): FileSessionStore => new FileSessionStore(
    (string) $config->get('session.files_path', __DIR__ . '/../storage/framework/sessions'),
));
$container->singleton(DatabaseSessionStore::class, static fn (Container $container): DatabaseSessionStore => new DatabaseSessionStore(
    $container->get(Database::class),
    (string) $config->get('session.table', 'sessions'),
));
$container->singleton(SessionStore::class, static fn (Container $container): SessionStore => match ((string) $config->get('session.driver', 'file')) {
    'database' => $container->get(DatabaseSessionStore::class),
    default => $container->get(FileSessionStore::class),
});
$container->singleton(SessionManager::class);
$container->singleton(CsrfTokenManager::class, static fn (): CsrfTokenManager => new CsrfTokenManager(
    (string) $config->get('session.csrf_key', '_csrf_token'),
));
$container->singleton(StartSession::class, static fn (Container $container): StartSession => new StartSession(
    $container->get(SessionStore::class),
    $container->get(SessionManager::class),
    (string) $config->get('session.cookie', 'stackmint_session'),
    (int) $config->get('session.lifetime', 7200),
    (string) $config->get('session.path', '/'),
    (string) $config->get('session.domain', ''),
    (bool) $config->get('session.secure', false),
    (bool) $config->get('session.http_only', true),
    (string) $config->get('session.same_site', 'Lax'),
));
$container->singleton(VerifyCsrfToken::class, static fn (Container $container): VerifyCsrfToken => new VerifyCsrfToken(
    $container->get(CsrfTokenManager::class),
));
$container->singleton(AuthManager::class, static fn (Container $container): AuthManager => new AuthManager(
    $container->get(SessionManager::class),
    (string) $config->get('auth.session_key', 'auth.user_id'),
    (string) $config->get('auth.table', 'users'),
    (string) $config->get('auth.primary_key', 'id'),
));
$container->singleton(Gate::class);
$container->singleton(Can::class);

$container->singleton(View::class, static function () use ($config, $moduleManager): View {
    $view = new View(
        (string) $config->get('app.views_path', __DIR__ . '/../app/Views'),
        (string) $config->get('app.views_extension', 'php'),
    );
    $moduleManager->registerViews($view, $config);

    return $view;
});

$moduleManager->registerProviders($container, $config);

$router = new Router(
    container: $container,
    events: $events,
    controllerNamespace: (string) $config->get('app.controllers_namespace', 'App\\Controllers\\'),
);

$routeCachePath = (string) $config->get('app.routes_cache_path', __DIR__ . '/cache/routes.php');

$middlewareAliases = $config->get('app.middleware_aliases', []);
if (is_array($middlewareAliases)) {
    foreach ($middlewareAliases as $alias => $middleware) {
        if (is_string($alias) && (is_string($middleware) || is_callable($middleware))) {
            $router->aliasMiddleware($alias, $middleware);
        }
    }
}

$middlewareGroups = $config->get('app.middleware_groups', []);
if (is_array($middlewareGroups)) {
    foreach ($middlewareGroups as $name => $middleware) {
        if (is_string($name) && is_array($middleware)) {
            $router->middlewareGroup($name, $middleware);
        }
    }
}

$moduleManager->bootProviders($container, $router, $config);

$container->instance(Router::class, $router);
$container->instance(AppKernel::class, new AppKernel(
    $router,
    (bool) $config->get('app.debug', false),
    $container->get(Logger::class),
));

$frameworkVersion = \Wayfinder\Foundation\Version::VALUE;

$container->singleton(ConsoleApplication::class, static fn (Container $container): ConsoleApplication => (new ConsoleApplication($frameworkVersion))
    ->add(new MakeControllerCommand(
        __DIR__ . '/../app/Controllers',
        (string) $config->get('app.controllers_namespace', 'App\\Controllers'),
    ))
    ->add(new MakeMiddlewareCommand(
        __DIR__ . '/../app/Middleware',
        'App\\Middleware',
    ))
    ->add(new MakeRequestCommand(
        __DIR__ . '/../app/Requests',
        'App\\Requests',
    ))
    ->add(new MakeViewCommand(
        (string) $config->get('app.views_path', __DIR__ . '/../app/Views'),
        (string) $config->get('app.views_extension', 'php'),
    ))
    ->add(new MakeMigrationCommand((string) $config->get('database.migrations_path', __DIR__ . '/../database/migrations')))
    ->add(new MakeQueueTableCommand((string) $config->get('database.migrations_path', __DIR__ . '/../database/migrations')))
    ->add(new MakeSessionTableCommand((string) $config->get('database.migrations_path', __DIR__ . '/../database/migrations')))
    ->add(new ModuleInstallCommand(
        __DIR__ . '/..',
        (string) $config->get('modules.path', __DIR__ . '/../Modules'),
        is_array($config->get('modules.packages', [])) ? $config->get('modules.packages', []) : [],
    ))
    ->add(new ModuleUninstallCommand(
        __DIR__ . '/..',
        (string) $config->get('modules.path', __DIR__ . '/../Modules'),
        is_array($config->get('modules.packages', [])) ? $config->get('modules.packages', []) : [],
    ))
    ->add(new KeyGenerateCommand(__DIR__ . '/../.env'))
    ->add(new ConfigCacheCommand($config, (string) $config->get('app.config_cache_path', __DIR__ . '/cache/config.php')))
    ->add(new ConfigClearCommand((string) $config->get('app.config_cache_path', __DIR__ . '/cache/config.php')))
    ->add(new RouteCacheCommand($container->get(Router::class), $config, $routeCachePath))
    ->add(new RouteClearCommand($routeCachePath))
    ->add(new RouteListCommand($container->get(Router::class)))
    ->add(new MigrateCommand($container->get(Migrator::class)))
    ->add(new MigrateRefreshCommand($container->get(Migrator::class)))
    ->add(new MigrateResetCommand($container->get(Migrator::class)))
    ->add(new MigrateRollbackCommand($container->get(Migrator::class)))
    ->add(new MigrateStatusCommand($container->get(Migrator::class)))
    ->add(new ServeCommand(__DIR__ . '/../public'))
    ->add(new TestCommand(__DIR__ . '/..')));

(static function (Router $router, Container $container, Config $config, EventDispatcher $events): void {
    $command = $_SERVER['WAYFINDER_CONSOLE_COMMAND'] ?? null;
    $routeCachePath = (string) $config->get('app.routes_cache_path', __DIR__ . '/../bootstrap/cache/routes.php');
    $shouldBypassCache = in_array($command, ['route:cache'], true);

    if (! $shouldBypassCache && is_file($routeCachePath)) {
        $manifest = require $routeCachePath;
        $router->loadCachedRoutes($manifest);

        return;
    }

    require __DIR__ . '/../routes/web.php';

    foreach ($container->get(ModuleManager::class)->routeFiles($config) as $routeFile) {
        (static function (string $routeFile, Router $router, Container $container, Config $config, EventDispatcher $events): void {
            $module = basename(dirname(dirname($routeFile)));
            require $routeFile;
        })($routeFile, $router, $container, $config, $events);
    }
})($router, $container, $config, $events);

return $container;
