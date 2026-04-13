# Wayfinder App

Default starter application for Wayfinder.

For published package names, GitHub repo layout, and the recommended local Composer override workflow while developing `wayfinder/core`, see [docs/local-development.md](/Users/ronbailey/Projects/wayfinder/docs/local-development.md:1).

This directory is the canonical starter app that should live as the `trafficinc/wayfinder-app` repository. It is the minimal shape for a new Wayfinder project. It includes:

- the default landing page at `/`
- a health route at `/health`
- bootstrap and container wiring
- config and env loading
- sessions and CSRF middleware
- database config and migrations for `users` and `sessions`
- PHPUnit bootstrap

It intentionally does not include sample domain features like tasks, projects, blog pages, queue demos, or mail demos.

## Run locally

```bash
composer install
cp .env.example .env
php wayfinder key:generate
php wayfinder migrate
php -S localhost:8000 -t public
```
