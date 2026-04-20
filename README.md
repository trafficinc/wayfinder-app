# Stackmint PHP Framework

Default starter application for the Stackmint framework.

Stackmint is a next-generation PHP framework built for clarity. It stays explicit and simple so both developers and AI tools can reason about the codebase quickly. Instead of hiding behavior behind large amounts of framework magic, Stackmint keeps the application surface small and visible, which makes building and evolving features easier.

The runtime package is currently published as `wayfinder/core`, and the framework namespaces remain `Wayfinder\\...`. For package layout, GitHub repo distribution, and local Composer override workflow, see `docs/local-development.md`.

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

A sample application is here: [stackmint-task-app](https://github.com/trafficinc/stackmint-task-app)

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

> Do these DB commands return objects or arrays?

  For example:

  - DB::raw(...) returns list<array<string, mixed>>
  - DB::query(...) returns list<array<string, mixed>>
  - DB::firstResult(...) returns array<string, mixed>|false
  - DB::statement(...) returns int
  - DB::select('users') returns a QueryBuilder

  So this:

  ```$rows = DB::raw('SELECT * FROM tasks WHERE status = ?', ['open']);```

gives you:

```
  [
      ['id' => 1, 'title' => '...', 'status' => 'open'],
      ['id' => 2, 'title' => '...', 'status' => 'open'],
  ]
```

  If you want Task (or any) objects, you map the rows into the model yourself:

```
  $tasks = array_map(
      static fn (array $row): Task => Task::fromDatabaseRow($row),
      $rows
  );
```

Or use the model API directly:

```
  $tasks = Task::where('status', 'open')->get();
```

  That returns Task objects.

> namespace Modules\Tasks\Models; (Example for a Task Module Model) or namespace app\Models; (Example for a framework Model)

Use a simple data model:

```php
<?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\DB;
  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';

      /**
       * @return list<self>
       */
      public static function openTasks(): array
      {
          $rows = DB::raw(
              '
                  SELECT *
                  FROM tasks
                  WHERE status = ?
                  ORDER BY id DESC
              ',
              ['open']
          );

          return array_map(
              static fn (array $row): self => self::fromDatabaseRow($row),
              $rows
          );
      }
  }
```

```php
  <?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\DB;
  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';

      public static function findBySlug(string $slug): ?self
      {
          $rows = DB::raw(
              '
                  SELECT *
                  FROM tasks
                  WHERE slug = ?
                  LIMIT 1
              ',
              [$slug]
          );

          if ($rows === []) {
              return null;
          }

          return self::fromDatabaseRow($rows[0]);
      }
  }
```

```php
  <?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\DB;
  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';

      public static function countCompletedForProject(int $projectId): int
      {
          $rows = DB::raw(
              '
                  SELECT COUNT(*) AS aggregate_count
                  FROM tasks
                  WHERE project_id = ?
                    AND status = ?
              ',
              [$projectId, 'done']
          );

          return (int) ($rows[0]['aggregate_count'] ?? 0);
      }
  }
```

```php
  <?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\DB;
  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';

      /**
       * @return list<self>
       */
      public static function recentlyUpdated(int $limit = 10): array
      {
          $rows = DB::raw(
              '
                  SELECT *
                  FROM tasks
                  WHERE archived_at IS NULL
                  ORDER BY updated_at DESC
                  LIMIT ?
              ',
              [$limit]
          );

          return array_map(
              static fn (array $row): self => self::fromDatabaseRow($row),
              $rows
          );
      }
  }
```

```php
  <?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\DB;
  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';

      /**
       * @return list<self>
       */
      public static function overdueTasks(string $today): array
      {
          $rows = DB::raw(
              '
                  SELECT *
                  FROM tasks
                  WHERE due_date < ?
                    AND status != ?
                  ORDER BY due_date ASC, id ASC
              ',
              [$today, 'done']
          );

          return array_map(
              static fn (array $row): self => self::fromDatabaseRow($row),
              $rows
          );
      }
  }
```

  And one example of what to avoid in a Model:
```php
  // Avoid in Task model if this becomes a multi-table read/report:
  $rows = DB::raw(
      '
          SELECT t.*, p.name AS project_name
          FROM tasks t
          INNER JOIN projects p ON p.id = t.project_id
      '
  );
```

> That should usually move to a Query + DTO, because it is no longer just tasks entity behavior.

```php
<?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\DB;
  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';

      public static function createTask(
          string $title,
          string $status = 'open',
          ?int $projectId = null,
      ): self {
          DB::statement(
              '
                  INSERT INTO tasks (title, status, project_id, created_at, updated_at)
                  VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
              ',
              [$title, $status, $projectId]
          );

          $id = DB::connection()->lastInsertId();

          return self::find((int) $id);
      }
  }
```

```php
  <?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\DB;
  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';

      public function rename(string $title): void
      {
          DB::statement(
              '
                  UPDATE tasks
                  SET title = ?, updated_at = CURRENT_TIMESTAMP
                  WHERE id = ?
              ',
              [$title, $this->getKey()]
          );

          $this->title = $title;
      }
  }
```

```php
  <?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\DB;
  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';

      public function markDone(): void
      {
          DB::statement(
              '
                  UPDATE tasks
                  SET status = ?, updated_at = CURRENT_TIMESTAMP
                  WHERE id = ?
              ',
              ['done', $this->getKey()]
          );

          $this->status = 'done';
      }
  }
```

```php
  <?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\DB;
  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';

      public static function archiveCompletedForProject(int $projectId): int
      {
          return DB::statement(
              '
                  UPDATE tasks
                  SET archived_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                  WHERE project_id = ?
                    AND status = ?
                    AND archived_at IS NULL
              ',
              [$projectId, 'done']
          );
      }
  }
```

```php
  <?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\DB;
  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';

      public function remove(): bool
      {
          $deleted = DB::statement(
              '
                  DELETE FROM tasks
                  WHERE id = ?
              ',
              [$this->getKey()]
          );

          return $deleted > 0;
      }
  }
```

```php
  <?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\DB;
  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';

      public static function purgeArchivedBefore(string $cutoff): int
      {
          return DB::statement(
              '
                  DELETE FROM tasks
                  WHERE archived_at IS NOT NULL
                    AND archived_at < ?
              ',
              [$cutoff]
          );
      }
  }
```

```php
  <?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\DB;
  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';

      /**
       * @return list<self>
       */
      public static function page(int $page = 1, int $perPage = 20): array
      {
          $page = max(1, $page);
          $perPage = max(1, $perPage);
          $offset = ($page - 1) * $perPage;

          $rows = DB::raw(
              '
                  SELECT *
                  FROM tasks
                  ORDER BY id DESC
                  LIMIT ?
                  OFFSET ?
              ',
              [$perPage, $offset]
          );

          return array_map(
              static fn (array $row): self => self::fromDatabaseRow($row),
              $rows
          );
      }
  }
```

```php
  <?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\DB;
  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';

      /**
       * @return array{
       *     items: list<self>,
       *     total: int,
       *     page: int,
       *     per_page: int
       * }
       */
      public static function paginate(int $page = 1, int $perPage = 20): array
      {
          $page = max(1, $page);
          $perPage = max(1, $perPage);
          $offset = ($page - 1) * $perPage;

          $countRows = DB::raw('SELECT COUNT(*) AS total FROM tasks');
          $total = (int) ($countRows[0]['total'] ?? 0);

          $rows = DB::raw(
              '
                  SELECT *
                  FROM tasks
                  ORDER BY id DESC
                  LIMIT ?
                  OFFSET ?
              ',
              [$perPage, $offset]
          );

          return [
              'items' => array_map(
                  static fn (array $row): self => self::fromDatabaseRow($row),
                  $rows
              ),
              'total' => $total,
              'page' => $page,
              'per_page' => $perPage,
          ];
      }
  }
```

```php
  <?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\DB;
  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';

      /**
       * @return list<self>
       */
      public static function nextPageAfterId(int $lastSeenId, int $perPage = 20): array
      {
          $rows = DB::raw(
              '
                  SELECT *
                  FROM tasks
                  WHERE id < ?
                  ORDER BY id DESC
                  LIMIT ?
              ',
              [$lastSeenId, $perPage]
          );

          return array_map(
              static fn (array $row): self => self::fromDatabaseRow($row),
              $rows
          );
      }
  }
```

  A practical rule for these:

  - DB::statement(...) for INSERT, UPDATE, DELETE
  - DB::raw(...) for raw SELECT
  - If pagination starts involving joins, filters across tables, or custom output shapes, move it to a Query + DTO instead of keeping it on the Model
  

Or use a more complicated data model for bigger applications:

  1. Model: single table CRUD

  This is the intended shape for “one table, entity behavior, CRUD”

```php
  <?php

  namespace Modules\Tasks\Models;

  use Wayfinder\Database\Model;

  final class Task extends Model
  {
      protected static string $table = 'tasks';
  }
```

  Usage:

```php
  // create
  $task = Task::create([
      'title' => 'Ship billing',
      'status' => 'open',
  ]);

  // read one
  $task = Task::find(1);

  // filtered read
  $openTasks = Task::where('status', 'open')
      ->orderBy('id', 'DESC')
      ->get();

  // update
  $task->update([
      'status' => 'done',
  ]);

  // delete
  $task->delete();
```

  Rule: if you are only touching tasks, stay in the Task model.

  2. Query + DTO: single-table read model

  This is already in the framework tests at wayfinder/framework/tests/Database/QueryTest.php.

  DTO:
```php
  <?php

  namespace Modules\Tasks\DTOs;

  use Wayfinder\Database\DataTransferObject;

  final class TaskListItemData extends DataTransferObject
  {
  }
```

  Query:

```php
  <?php

  namespace Modules\Tasks\Queries;

  use Modules\Tasks\DTOs\TaskListItemData;
  use Wayfinder\Database\Query;

  final class TaskListQuery extends Query
  {
      /**
       * @return list<TaskListItemData>
       */
      public function execute(): array
      {
          return $this->many(
              TaskListItemData::class,
              'SELECT id, title, status FROM tasks WHERE archived_at IS NULL ORDER BY id DESC'
          );
      }
  }
```

  Usage:

```php
  $items = (new TaskListQuery())->execute();
```

  Rule: even though this is still one table, it’s a read shape, not entity behavior. That makes Query + DTO reasonable.

  3. Query + DTO with a join

  This is the next step up: multiple tables, still read-only. This should be a Query, not a Model.

  DTO:

```php
  <?php

  namespace Modules\Tasks\DTOs;

  use Wayfinder\Database\DataTransferObject;

  final class TaskListItemData extends DataTransferObject
  {
  }
```

  Query:

```php
  <?php

  namespace Modules\Tasks\Queries;

  use Modules\Tasks\DTOs\TaskListItemData;
  use Wayfinder\Database\Query;

  final class TaskListQuery extends Query
  {
      /**
       * @return list<TaskListItemData>
       */
      public function execute(): array
      {
          return $this->many(
              TaskListItemData::class,
              '
                  SELECT
                      t.id,
                      t.title,
                      t.status,
                      p.name AS project_name,
                      u.email AS assignee_email
                  FROM tasks t
                  INNER JOIN projects p ON p.id = t.project_id
                  LEFT JOIN users u ON u.id = t.assignee_id
                  WHERE t.archived_at IS NULL
                  ORDER BY t.id DESC
              '
          );
      }
  }
```

  Each returned item is a DTO row like:

  $item->id;
  $item->title;
  $item->status;
  $item->project_name;
  $item->assignee_email;

  4. Query + DTO with join + aggregate

  If you need grouped reporting, it stays in Query.

  ```php
  <?php

  namespace Modules\Projects\DTOs;

  use Wayfinder\Database\DataTransferObject;

  final class ProjectTaskSummaryData extends DataTransferObject
  {
  }
```

```php
  <?php

  namespace Modules\Projects\Queries;

  use Modules\Projects\DTOs\ProjectTaskSummaryData;
  use Wayfinder\Database\Query;

  final class ProjectTaskSummaryQuery extends Query
  {
      /**
       * @return list<ProjectTaskSummaryData>
       */
      public function execute(): array
      {
          return $this->many(
              ProjectTaskSummaryData::class,
              '
                  SELECT
                      p.id,
                      p.name,
                      COUNT(t.id) AS task_count,
                      SUM(CASE WHEN t.status = ? THEN 1 ELSE 0 END) AS done_count
                  FROM projects p
                  LEFT JOIN tasks t ON t.project_id = p.id
                  GROUP BY p.id, p.name
                  ORDER BY p.name ASC
              ',
              ['done']
          );
      }
  }
```

  Rule of thumb

  - Model: one table, CRUD, entity lifecycle
  - Query: joins, aggregates, grouped reads, report/list screens
  - DTO: typed output for a Query

Simplified DB acccess via "raw":

```php
  use Wayfinder\Database\DB;

  $sql = '
      SELECT id, email, is_admin
      FROM users
      WHERE is_admin = ?
      ORDER BY id DESC
  ';

  $rows = DB::raw($sql, [1]);
```

```php
  use Wayfinder\Database\DB;

  $sql = '
      SELECT id, title, status
      FROM tasks
      WHERE project_id = ?
        AND status = ?
      ORDER BY id ASC
  ';

  $rows = DB::raw($sql, [$projectId, 'open']);
```

```php
  use Wayfinder\Database\DB;

  $sql = '
      SELECT
          t.id,
          t.title,
          p.name AS project_name
      FROM tasks t
      INNER JOIN projects p ON p.id = t.project_id
      WHERE t.archived_at IS NULL
      ORDER BY p.name ASC, t.id ASC
  ';

  $rows = DB::raw($sql);
```

```php
  use Wayfinder\Database\DB;

  $sql = '
      SELECT
          u.id,
          u.name,
          COUNT(t.id) AS task_count
      FROM users u
      LEFT JOIN tasks t ON t.assignee_id = u.id
      WHERE u.status = ?
      GROUP BY u.id, u.name
      ORDER BY task_count DESC, u.name ASC
  ';

  $rows = DB::raw($sql, ['active']);
```

```php
  use Wayfinder\Database\DB;

  $sql = '
      SELECT
          p.id,
          p.name,
          SUM(CASE WHEN t.status = ? THEN 1 ELSE 0 END) AS done_count,
          COUNT(t.id) AS total_count
      FROM projects p
      LEFT JOIN tasks t ON t.project_id = p.id
      GROUP BY p.id, p.name
      ORDER BY p.name ASC
  ';

  $rows = DB::raw($sql, ['done']);
```

```php
  use Wayfinder\Database\DB;

  $sql = '
      WITH active_users AS (
          SELECT id, name
          FROM users
          WHERE status = ?
      )
      SELECT
          u.name,
          COUNT(p.id) AS post_count
      FROM active_users u
      LEFT JOIN posts p ON p.user_id = u.id
      GROUP BY u.id, u.name
      ORDER BY u.name ASC
  ';

  $rows = DB::raw($sql, ['active']);
```

```php
  use Wayfinder\Database\DB;

  $sql = '
      SELECT
          DATE(created_at) AS day,
          COUNT(*) AS registrations
      FROM users
      WHERE created_at >= ?
        AND created_at < ?
      GROUP BY DATE(created_at)
      ORDER BY day ASC
  ';

  $rows = DB::raw($sql, [$startDate, $endDate]);
```
    
Only use this below for quick access not in Controllers, Models, etc. but maybe for quick scripts use the DB api for database access.

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

If you install `trafficinc/stackmint-auth`, set the signed-in destination in your app config:

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
stackmint-auth/
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

Keep foundational app schema out of the module package. For example, the `users` table should stay in the starter app or host application, while a module like `trafficinc/stackmint-auth` should only own auth-specific tables if it truly needs them.

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

The starter app owns foundational schema like the `users` table. Modules such as `trafficinc/stackmint-auth` should stay focused on auth behavior and only bring along module-specific schema when necessary.
