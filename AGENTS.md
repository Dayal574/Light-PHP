# Light PHP agent guide

Light PHP is a Core PHP 8.1 starter. Build features with routes, controllers, plain PHP views, `request()`, and `DB::`. Do not add Blade, Eloquent, a migration builder, middleware, or dynamic route parameters unless the user asks for them.

Read this file before changing the application. The human-facing overview is `README.md`.

## Layout

- `routes/web.php` — browser routes
- `routes/api.php` — API routes, loaded after the web routes
- `app/Controllers/` — `App\Controllers` classes, one action returns a string or a `Response`
- `app/Models/` — optional classes that call `\DB::`
- `views/pages/` — files rendered by `view('name')`
- `views/layouts/app.php` — starter layout (Tailwind CDN, logo, navigation)
- `views/components/` — `component()` and `get_component()`
- `app/Core/` — framework code; do not rewrite it for a normal feature
- `app/Helpers/helpers.php` — the user's functions only
- `database/schema.sql` — applied by `php artisan migrate`
- `database/seeders/` — applied by `php artisan seed`

## Add a page

1. `php artisan make:view posts` creates `views/pages/posts.php` with `layout('layouts/app')`.
2. Add a controller method that `return view('posts', ['pageTitle' => 'Posts']);`
3. Register it: `Route::get('/posts', [PostController::class, 'index']);`
4. Generators do not register routes. Print or add the `Route::` line yourself.

Reuse `views/layouts/app.php` and Tailwind utility classes. Do not add a second CSS framework. The layout already loads the Tailwind CDN.

Inside a view, `request()`, `DB::`, `esc()`, `csrf()`, `old()` and `error()` are global. No `use` line.

Inside a controller, model, or any `App\` file, functions such as `request()` and `view()` still resolve globally. Classes do not: write `\DB::getall('users')`.

## Database

Values go through `DB::` prepared statements. `update` and `delete` require a where array. `get` returns `null` when the row is missing. Rows support `$user->name` and `$user['name']`.

`insert` accepts one associative row, not a list of rows. There is no `$fillable`; pass only the columns you mean to write.

Schema changes use `App\Core\DBdelta` and must be imported:

```php
use App\Core\DBdelta;

DBdelta::add_column('users', 'phone', 'VARCHAR(30) NULL');
```

Do not call `DBdelta` as a global.

## Request and forms

Use `request()->input()`, `query()`, `post()`, `file()`, `has()` and `filled()`. Do not read `$_GET` or `$_POST` in new code.

`csrf()` prints the hidden `_token` field. It is not checked automatically. Call `csrf_verify()` in the POST action and `abort(419)` when it fails.

`old()` and `error()` return raw strings. Escape them with `esc()` in HTML. `redirect('/path')` only accepts a relative path that starts with a single `/`.

## Views

`$template` is optional. These methods matter:

- `layout('layouts/app')`, `section('content')`, `end()`, `yield('content')`
- `component('alert', ['message' => 'Saved'])`, then `slot('footer')`, then `end()` for the slot and `end()` for the component
- `include('partials/nav', ['label' => 'Home'])`
- `push('scripts')` / `stack('scripts')`
- `esc()` for untrusted text

A data key named `template` does not replace the framework object. `get_header()` still works only if that component file exists; the starter no longer ships `header.php`.

## Do not

- Redeclare `esc()`, `request()`, `view()`, `csrf()`, or `redirect()` in `app/Helpers/helpers.php`
- Put framework helpers into `app/Helpers/helpers.php`
- Build a view or include path from request input
- Commit `.env` or put application code under `public/`
- Edit `app/Core` to finish a normal page, form, or query
