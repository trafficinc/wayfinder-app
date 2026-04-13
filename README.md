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

## Run locally

```bash
composer install
cp .env.example .env
php wayfinder key:generate
php wayfinder migrate
php -S localhost:8000 -t public
```

The starter app owns foundational schema like the `users` table. Modules such as `wayfinder/auth` should stay focused on auth behavior and only bring along module-specific schema when necessary.
