# Light PHP

A Core PHP starter for small applications. You write routes, controllers and views in plain PHP. The framework supplies the repetitive parts: the request, HTML escaping, form tokens, prepared MySQL queries, and a few `php artisan` commands.

It is not Laravel. There is no Blade, no Eloquent, and no migration builder. Dynamic route parameters and middleware are left out on purpose.

## Start

```bash
composer install
cp .env.example .env
php artisan serve
```

Open `http://127.0.0.1:8000`. Change the address with `php artisan serve --host=0.0.0.0 --port=8080`.

The site has three pages: `/`, `/about`, and `/documentation`. Point the production document root at `public/` so `app/`, `routes/`, `views/` and `.env` stay off the web.

## Add a page

1. Create the view.

```bash
php artisan make:view posts
```

2. Return it from a controller. `view()` renders `views/pages/posts.php` and passes the array in as variables.

```php
namespace App\Controllers;

final class PostController
{
    public function index(): string
    {
        return view('posts', [
            'pageTitle' => 'Posts',
        ]);
    }
}
```

3. Register the route in `routes/web.php`.

```php
use App\Controllers\PostController;
use App\Core\Route;

Route::get('/posts', [PostController::class, 'index']);
```

The view can use the shared layout:

```php
<?php
$template->layout('layouts/app');
$template->section('title', esc($pageTitle ?? 'Posts'));
$template->section('content');
?>
<h1><?= esc($pageTitle) ?></h1>
<?php $template->end(); ?>
```

`request()`, `DB::` and `esc()` work inside that view. No import is required.

## Request

`request()` reads the current request. Calling it again returns the same object.

```php
$request = request();

$request->all();
$request->input('name');
$request->query('page');
$request->post();
$request->method();
$request->isMethod('POST');
$request->file('avatar');
$request->has('name');
$request->filled('name');
$request->only(['name', 'email']);
$request->except(['password']);
```

`input()` sees the query string and the form together. When both contain a name, the form value wins. `post()` is only the submitted form, not JSON.

## Database

Set `DB_*` in `.env`, then `php artisan migrate` and `php artisan seed`. `migrate` creates the database named by `DB_DATABASE` when it is missing, then runs `database/schema.sql`.

`DB` is global. A view calls `DB::insert(...)`. A controller or model calls `\DB::insert(...)`.

```php
DB::insert('users', ['name' => 'Ada', 'email' => 'ada@example.com']);
DB::update('users', ['name' => 'New'], ['id' => $id]);
DB::delete('users', ['id' => $id]);

$users = DB::getall('users');
$user = DB::get('users', $id);

DB::begin_transaction();
DB::commit();
```

`update` and `delete` require a where condition. `get` returns `null` when nothing matches. Rows can be read as `$user->name` or `$user['name']`.

Table changes use `DBdelta`, and that class must be imported:

```php
use App\Core\DBdelta;

DBdelta::create('posts', [
    'id' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
    'title' => 'VARCHAR(190) NOT NULL',
]);
DBdelta::add_column('users', 'phone', 'VARCHAR(30) NULL');
```

`app/Models/User.php` is a small example of `\DB::getall('users')`.

## Views

Pages live in `views/pages/`. Layouts and partials can live anywhere under `views/`. Components live in `views/components/`.

```php
$template->layout('layouts/app');
$template->section('content');
$template->end();
$template->yield('content');
$template->component('alert', ['message' => 'Saved']);
$template->include('partials/nav', ['label' => 'Home']);
```

Close `section()`, `push()`, `component()`, `slot()`, `once()` and `fragment()` with `end()`. If you never call `layout()`, the page prints as normal PHP. `get_header()`, `get_footer()` and `get_component()` remain available when you add those component files.

`esc($message)` escapes HTML. `old()` and `error()` return raw strings, so escape them in the view.

## Forms

```php
<?= csrf() ?>
<input name="email" value="<?= esc(old('email')) ?>">
<?php if (has_error('email')): ?>
    <p><?= esc(error('email')) ?></p>
<?php endif; ?>
```

`csrf()` prints a hidden `_token` stored in the session. Call `csrf_verify()` yourself before saving a POST. `flash_old()` and `flash_errors()` keep the values for the next request when you `redirect('/posts')`.

## Artisan

```bash
php artisan make:controller PostController
php artisan make:model Post
php artisan make:view posts
php artisan make:component card
php artisan route:list
php artisan migrate
php artisan seed
php artisan seed --class=UserSeeder
```

Generators do not register a route. Add that line yourself.

## Helpers

Framework functions live in `app/Core/helpers.php`. Put your own functions in `app/Helpers/helpers.php`. Do not redeclare `esc()`, `request()`, or `view()`.

- `config('app.name')` reads `config/app.php`
- `env('APP_NAME', 'Light PHP')` reads `.env`
- `asset('css/app.css')` builds a URL for `public/assets/`
- `redirect('/posts')` sends a relative path only

## Production

Use `public/` as the document root. A root-level document root is only for local experiments.

```nginx
server {
    listen 80;
    server_name example.test;
    root /absolute/path/to/project/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

Escape untrusted HTML with `esc()`. Bind query values through `DB::`, which uses PDO prepared statements. Do not build include paths from request input.
