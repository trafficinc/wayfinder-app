# Wayfinder PHP Framework

Default starter application for Wayfinder.

Wayfinder is a next-generation PHP framework built for clarity. It stays explicit and simple so both developers and AI tools can reason about the codebase quickly. Instead of hiding behavior behind large amounts of framework magic, Wayfinder keeps the application surface small and visible, which makes building and evolving features easier.

For published package names, GitHub repo layout, and the recommended local Composer override workflow while developing `wayfinder/core`, see `docs/local-development.md`.

This directory is the canonical starter app that should live as the `trafficinc/wayfinder-app` repository. It is the minimal shape for a new Wayfinder project. It includes:

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

For browser form posts inside the session middleware, validation failures redirect back and flash `_errors` and `_old_input` into the session. When a view receives a `request` entry, templates can use the built-in `$form` helper:

```php
<?= $form->csrfField() ?>
<input name="email" value="<?= htmlspecialchars((string) $form->old('email', ''), ENT_QUOTES, 'UTF-8') ?>">
```

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

Wayfinder provides explicit HTTP state primitives instead of relying directly on PHP session globals. Core pieces include `Wayfinder\Session\StartSession`, `Wayfinder\Http\VerifyCsrfToken`, and `Wayfinder\Auth\AuthManager`.

The intended flow is:

1. Bind a session store and `StartSession` middleware in the container.
2. Attach the session middleware to a route group such as `web`.
3. Attach `VerifyCsrfToken` where browser-backed state-changing requests should be protected.
4. Access the active session from the request with `$request->session()`.
5. Use `AuthManager` for `login()`, `logout()`, `check()`, `id()`, and `user()`.

Session drivers supported today are `file` and `database`. To use database-backed sessions, set `SESSION_DRIVER=database` and create the sessions table:

```bash
php wayfinder make:session-table
php wayfinder migrate
```

For authorization, define abilities on `Wayfinder\Auth\Gate` and protect routes with `can:ability` middleware. The built-in `Authenticate` middleware returns `401` when no authenticated user is present, and `Can` returns `403` when the ability check fails.

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
php wayfinder module:install /absolute/path/to/MyModule --module=MyModule
php wayfinder module:uninstall auth
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

Module service providers extend `Wayfinder\Module\ServiceProvider` and use `register()` and `boot()` to bind services, routes, listeners, and other module wiring.

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

## Run locally

```bash
composer install
cp .env.example .env
php wayfinder key:generate
php wayfinder migrate
php wayfinder serve
```

The starter app owns foundational schema like the `users` table. Modules such as `wayfinder/auth` should stay focused on auth behavior and only bring along module-specific schema when necessary.
