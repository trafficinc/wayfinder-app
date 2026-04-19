# Stackmint PHP App Skeleton

Default starter application for Stackmint, built on Wayfinder.

Wayfinder is a next-generation PHP framework built for clarity. It stays explicit and simple so both developers and AI tools can reason about the codebase quickly. Instead of hiding behavior behind large amounts of framework magic, Wayfinder keeps the application surface small and visible, which makes building and evolving features easier.

For published package names, GitHub repo layout, and the recommended local Composer override workflow while developing `wayfinder/core`, see `docs/local-development.md`.

This directory is the canonical starter app that should live as the `trafficinc/stackmint` repository. It is the minimal shape for a new Stackmint project. It includes:

- the default landing page at `/`
- a health route at `/health`
- bootstrap and container wiring
- config and env loading
- sessions and CSRF middleware
- database config and migrations for `users` and `sessions`
- PHPUnit bootstrap

It intentionally does not include sample domain features like tasks, projects, blog pages, queue demos, or mail demos.

## More Framework Documentation
More information about the framework is here: [wayfinder/core](https://github.com/trafficinc/wayfinder-core)

A sample application is here: [wayfinder-task-app](https://github.com/trafficinc/wayfinder-task-app)

## Using the Framework

Wayfinder is designed to keep the request lifecycle explicit. The front controller should bootstrap an `AppKernel` and call `run()`:

```php
<?php

use Wayfinder\Foundation\AppKernel;

$app = require __DIR__ . '/../bootstrap/app.php';
$app->run();
```

`AppKernel` creates a request from PHP globals, hands it to the router, and sends the returned response. Uncaught exceptions become `500 Internal Server Error` responses. With debug enabled, the response includes exception details and a stack trace. Validation exceptions become `422` JSON responses with an `errors` payload.

Typical bootstrap services include:

- `Wayfinder\Support\Config` for `config/*.php`
- `Wayfinder\Support\Env` for `.env`
- `Wayfinder\Support\Container` for bindings and singletons
- `Wayfinder\Support\EventDispatcher` for in-process events

## Routing, Controllers, and Responses

Routes are registered explicitly in userland, usually in `routes/web.php`. The router supports static and parameterized routes, named routes, controller actions, middleware aliases and groups, nested groups, and URL generation with `urlFor()`.

```php
<?php

use Wayfinder\Http\Request;
use Wayfinder\Http\Response;

$router->get('/', static function (Request $request): Response {
    return Response::text('Hello from Wayfinder');
});

$router->get('/hello/{name}', static function (Request $request, string $name): Response {
    return Response::text("Hello, {$name}");
}, 'hello.show');

$router->aliasMiddleware('auth', App\Middleware\Authenticate::class);
$router->middlewareGroup('web', ['auth']);
```

Responses stay explicit. You can return JSON, redirects, and attach cookies or flash data:

```php
<?php

use Wayfinder\Http\Cookie;
use Wayfinder\Http\Response;

return Response::json(['ok' => true])
    ->withCookie(Cookie::make('theme', 'light'));

return Response::redirect('/')
    ->withFlash($request->session(), 'status', 'Saved successfully.');
```

Views are plain PHP templates rendered through `Wayfinder\View\View`:

```php
<?php

return $view->response('home.index', [
    'title' => 'Wayfinder',
]);
```

## Requests and Validation

`Wayfinder\Http\Request` includes helpers such as `all()`, `input()`, `string()`, `integer()`, `boolean()`, `old()`, `errors()`, and `validate()`.

```php
<?php

$data = $request->validate([
    'name' => 'required|string|max:100',
    'email' => 'required|email',
    'age' => 'nullable|integer',
]);
```

Supported rules include `required`, `nullable`, `string`, `integer`, `numeric`, `boolean`, `array`, `email`, `url`, `date`, `min`, `max`, `confirmed`, `same`, `exists`, and `unique`.

Supported validation rules:

| Rule | Example | Notes |
|---|---|---|
| `required` | `required` | Fails if the field is absent or empty |
| `nullable` | `nullable` | Allows null or empty values and stores `null` in validated output |
| `string` | `string` | Must be a PHP string |
| `integer` | `integer` | Must pass `FILTER_VALIDATE_INT` |
| `numeric` | `numeric` | Must pass `is_numeric()` and may be a float |
| `boolean` | `boolean` | Must be coercible to a boolean |
| `array` | `array` | Must be a PHP array |
| `email` | `email` | Must be a valid email address |
| `url` | `url` | Must be a valid URL |
| `date` | `date` | Must be parseable by `strtotime()` |
| `min` | `min:3` | Strings: minimum characters; numerics: minimum value; arrays: minimum item count |
| `max` | `max:255` | Strings: maximum characters; numerics: maximum value; arrays: maximum item count |
| `confirmed` | `confirmed` | Requires a matching `{field}_confirmation` field |
| `same` | `same:other_field` | Must equal another field's value |
| `exists` | `exists:table,column` | Value must exist in the given table and column |
| `unique` | `unique:table,column` | Value must not already exist in the given table and column |

`unique` also supports an ignore value and id column for update flows:

```php
'email' => 'required|email|unique:users,email,{$id},id'
```

Placeholders like `{$id}` are resolved from route parameters first, then request input.

For reusable validation, extend `Wayfinder\Http\FormRequest` and type-hint it in a controller action. The router resolves it from the current request and validates before the controller runs:

```php
<?php

use Wayfinder\Http\FormRequest;

final class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
```

Override `messages()` to provide custom error text keyed as `field.rule`:

```php
public function messages(): array
{
    return [
        'name.required' => 'Please enter your name.',
        'password.min' => 'Password must be at least 8 characters.',
        'password.confirmed' => 'Passwords do not match.',
    ];
}
```

For browser form posts inside the session middleware, validation failures redirect back and flash `_errors` and `_old_input` into the session. When a view receives a `request` entry, templates can use the built-in `$form` helper:

```php
<?= $form->csrfField() ?>
<input name="email" value="<?= htmlspecialchars((string) $form->old('email', ''), ENT_QUOTES, 'UTF-8') ?>">
```

Validation can also target a named error bag:

```php
$request->validate([
    'email' => 'required|email',
], [], 'login');

$errors = $request->errors('login');
$email = $request->old('email', '', 'login');
```

Wayfinder also autoloads plain PHP template helpers:

- `e($value)` for HTML escaping
- `attrs([...])` for rendering HTML attributes
- `checked($current, $expected = true, $strict = false)`
- `selected($current, $expected = true, $strict = false)`
- `disabled($condition = true)`

## Database and Migrations

`Wayfinder\Database\Database` is a thin fluent query builder on top of PDO with support for `mysql`, `pgsql`, and `sqlite`. The preferred application-facing entry point is `Wayfinder\Database\DB`.

```php
<?php

use Wayfinder\Database\DB;

$users = DB::table('users')
    ->where('status', 'active')
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->get();
```

Common operations include `table`, `select`, `insert`, `update`, `delete`, `where`, `join`, `orderBy`, `limit`, `count`, `exists`, `value`, `pluck`, and `transaction()`.

Schema changes use `Wayfinder\Database\Schema` in migration files:

```php
<?php

use Wayfinder\Database\Blueprint;
use Wayfinder\Database\Database;
use Wayfinder\Database\Migration;
use Wayfinder\Database\Schema;

return new class implements Migration
{
    public function up(Database $database): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('body');
            $table->timestamps();
        });
    }

    public function down(Database $database): void
    {
        Schema::dropIfExists('posts');
    }
};
```

Useful CLI helpers:

```bash
php wayfinder make:migration create_posts_table
php wayfinder migrate
php wayfinder migrate:rollback
php wayfinder migrate:status
```

## Sessions, CSRF, and Auth

Wayfinder provides explicit HTTP state primitives instead of relying directly on PHP session globals. Core pieces include `Wayfinder\Http\Cookie`, `Wayfinder\Http\VerifyCsrfToken`, `Wayfinder\Session\StartSession`, `Wayfinder\Session\FileSessionStore`, `Wayfinder\Session\DatabaseSessionStore`, `Wayfinder\Auth\AuthManager`, `Wayfinder\Auth\Authenticate`, `Wayfinder\Auth\Gate`, and `Wayfinder\Auth\Can`.

The intended flow is:

1. Bind a session store and `StartSession` middleware in the container.
2. Attach the session middleware to a route group such as `web`.
3. Attach `VerifyCsrfToken` where browser-backed state-changing requests should be protected.
4. Access the active session from the request with `$request->session()`.
5. Use `AuthManager` for `login()`, `logout()`, `check()`, `id()`, and `user()`.

`AuthManager` rotates the session ID on both `login()` and `logout()` to reduce session fixation risk.

Session drivers supported today are `file` and `database`. To use database-backed sessions, set `SESSION_DRIVER=database` and create the sessions table:

```bash
php wayfinder make:session-table
php wayfinder migrate
```

Session length is configured through `session.lifetime` or `SESSION_LIFETIME`. Database-backed sessions use a `sessions` table with `id`, `payload`, and `last_activity` columns.

For authorization, define abilities on `Wayfinder\Auth\Gate` and protect routes with `can:ability` middleware. The built-in `Authenticate` middleware returns `401` when no authenticated user is present, and `Can` returns `403` when the ability check fails.

```php
<?php

$gate->define('admin.reports', static function (?array $user): bool {
    return (bool) ($user['is_admin'] ?? false);
});

$router->get('/admin/reports', ReportsController::class, 'admin.reports', [
    'auth',
    'can:admin.reports',
]);
```

## Modules

The starter app is module-ready. First-party packaged modules can be installed with:

```bash
php wayfinder module:install auth
```

That command:

1. runs Composer for the package
2. creates a symlink in `Modules/`

Installer aliases like `auth` live in the app's `config/modules.php`. They are app-level convenience mappings, not something the module package itself should define.

Generic packaged modules and local custom modules are also supported:

```bash
php wayfinder module:install vendor/package --module=Blog
php wayfinder module:install vendor/package --module=Blog --repository=https://github.com/acme/wayfinder-blog
php wayfinder module:install /absolute/path/to/MyModule --module=MyModule
php wayfinder module:uninstall auth
php wayfinder module:uninstall MyModule
```

If you install `wayfinder/auth`, set the signed-in destination in your app config:

```php
return [
    'home_route' => '/dashboard',
];
```

The auth module redirects:

- login: first to any intended protected URL, otherwise to `auth.home_route`
- registration: to `auth.home_route`

So the host app chooses the post-login and post-registration landing page.

Modules can expose:

- `module.php`
- `config/*.php`
- `routes/web.php`
- `resources/views`
- `database/migrations`
- `ModuleServiceProvider.php`

Minimal module metadata:

```php
<?php

return [
    'provider' => Modules\Blog\ModuleServiceProvider::class,
];
```

Views are namespaced by module key, so a `Blog` module view can be rendered as:

```php
$view->response('blog::index');
```

Module service providers extend `Wayfinder\Module\ServiceProvider`:

```php
<?php

use Wayfinder\Module\Module;
use Wayfinder\Module\ServiceProvider;
use Wayfinder\Routing\Router;
use Wayfinder\Support\Config;
use Wayfinder\Support\Container;

final class ModuleServiceProvider extends ServiceProvider
{
    public function register(Container $container, Config $config, Module $module): void
    {
        // Bind module services.
    }

    public function boot(Container $container, Router $router, Config $config, Module $module): void
    {
        // Register middleware groups, listeners, and other module wiring.
    }
}
```

Module service providers use `register()` and `boot()` to bind services, routes, listeners, and other module wiring.

For distribution, package a module as its own Composer library instead of copying folders between apps.

Recommended structure for a distributable module package:

```text
wayfinder-auth/
  composer.json
  module.php
  ModuleServiceProvider.php
  Controllers/
  Requests/
  Middleware/
  Support/
  config/
  routes/
  resources/views/
  database/migrations/
  README.md
```

Keep foundational app schema out of the module package. For example, the `users` table should stay in the starter app or host application, while a module like `wayfinder/auth` should only own auth-specific tables if it truly needs them.

## CLI and Testing

Wayfinder includes a small console app plus an in-process HTTP testing layer.

Common CLI commands:

```bash
php wayfinder serve
php wayfinder test
php wayfinder route:list
php wayfinder config:cache
php wayfinder config:clear
php wayfinder make:controller Admin/ReportsController
php wayfinder make:middleware EnsureAdmin
php wayfinder make:request Api/StoreUserRequest
php wayfinder make:view admin/reports/index
```

`Wayfinder\Testing\TestClient` can make in-process HTTP requests, persist cookies between requests, attach headers, and seed authenticated sessions with `actingAs()`:

```php
<?php

use Wayfinder\Testing\TestClient;

$client = new TestClient($kernel, $container);

$client->get('/health')
    ->assertStatus(200);

$client->actingAs(1)
    ->get('/admin/reports')
    ->assertStatus(200);
```

## Bootstrap and Events

A typical bootstrap wires config, container, events, database, and router explicitly before creating the kernel:

```php
<?php

use Wayfinder\Database\Database;
use Wayfinder\Foundation\AppKernel;
use Wayfinder\Routing\Router;
use Wayfinder\Support\Config;
use Wayfinder\Support\Container;
use Wayfinder\Support\EventDispatcher;
use Wayfinder\Support\Events;

$config = Config::fromDirectory(__DIR__ . '/../config');
$container = new Container();
$events = new EventDispatcher();
Events::setDispatcher($events);

$container->instance(Config::class, $config);
$container->singleton(Database::class, fn () => new Database($config->get('database.default')));

$router = new Router($container, $events, 'App\\Controllers\\');

return new AppKernel($router);
```

Once the dispatcher is registered, application and module code can emit domain events with the global helpers:

```php
event('order.created', $order);

listen('order.created', function (array $order): void {
    // send mail, write audit log, enqueue follow-up work
});
```

## Run locally

```bash
composer install
cp .env.example .env
php wayfinder key:generate
php wayfinder migrate
php wayfinder serve
```

Set `QUEUE_CONNECTION=sync` in `.env` for immediate local job execution. You can also use `QUEUE_CONNECTION=file`, `QUEUE_CONNECTION=database`, or `QUEUE_CONNECTION=redis`. If you switch to `QUEUE_CONNECTION=database`, generate the queue migration first:

```bash
php wayfinder make:queue-table
php wayfinder migrate
```

You can also scaffold a new app from the starter with:

```bash
wayfinder new my-app
cd my-app
cp .env.example .env
composer install
php wayfinder key:generate
php wayfinder migrate
php wayfinder serve
```

The starter app owns foundational schema like the `users` table. Modules such as `wayfinder/auth` should stay focused on auth behavior and only bring along module-specific schema when necessary.
