<?php
// Documentation page for GET /documentation.
// HomeController::documentation() returns view('documentation', ...).
// Code samples are nowdocs so the <?php tags inside them are not executed.
$template->layout('layouts/app');
$template->section('title', esc($pageTitle ?? 'Documentation'));
$template->section('content');

$topics = [
    'routing' => 'Routing',
    'controller' => 'Controller',
    'views' => 'Views',
    'request' => 'Request',
    'database' => 'Database',
    'forms' => 'Forms',
    'artisan' => 'Artisan',
];
?>
<main class="mx-auto grid max-w-6xl gap-10 px-6 py-16 md:grid-cols-[220px_1fr] md:py-20">
    <nav class="flex gap-2 overflow-x-auto md:block md:space-y-1">
        <?php foreach ($topics as $id => $label): ?>
            <a href="#<?= esc($id) ?>" class="block whitespace-nowrap rounded-full px-3 py-2 text-sm text-stone-600 hover:bg-white hover:text-stone-900"><?= esc($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="space-y-14">
        <header>
            <p class="text-sm font-medium uppercase tracking-[0.22em] text-orange-700">Documentation</p>
            <h1 class="mt-3 font-display text-5xl text-stone-950">Build a page</h1>
            <p class="mt-4 max-w-2xl text-lg text-stone-600">The same three steps used by this site: a route, a controller method, and a view.</p>
        </header>

        <section id="routing" class="scroll-mt-24">
            <h2 class="font-display text-3xl">Routing</h2>
            <p class="mt-3 text-stone-600">Add the URL in <code>routes/web.php</code>. The array points at a controller class and method.</p>
            <pre class="mt-4 overflow-x-auto rounded-2xl bg-stone-900 p-5 text-sm leading-relaxed text-stone-100"><code><?= esc(<<<'CODE'
use App\Controllers\PostController;
use App\Core\Route;

Route::get('/posts', [PostController::class, 'index']);
Route::post('/posts', [PostController::class, 'store']);
CODE) ?></code></pre>
        </section>

        <section id="controller" class="scroll-mt-24">
            <h2 class="font-display text-3xl">Controller</h2>
            <p class="mt-3 text-stone-600">Return <code>view()</code> from the method. The array becomes variables in the view. Inside a controller, call the global database class as <code>\DB</code>.</p>
            <pre class="mt-4 overflow-x-auto rounded-2xl bg-stone-900 p-5 text-sm leading-relaxed text-stone-100"><code><?= esc(<<<'CODE'
public function index(): string
{
    return view('posts', [
        'pageTitle' => 'Posts',
        'posts' => \DB::getall('posts'),
    ]);
}
CODE) ?></code></pre>
        </section>

        <section id="views" class="scroll-mt-24">
            <h2 class="font-display text-3xl">Views</h2>
            <p class="mt-3 text-stone-600"><code>$template->layout()</code> is optional. Without it, the file prints as written. <code>end()</code> closes the section opened above it.</p>
            <pre class="mt-4 overflow-x-auto rounded-2xl bg-stone-900 p-5 text-sm leading-relaxed text-stone-100"><code><?= esc(<<<'CODE'
<?php
$template->layout('layouts/app');
$template->section('title', esc($pageTitle ?? 'Posts'));
$template->section('content');
?>
<h1><?= esc($pageTitle) ?></h1>
<?php $template->end(); ?>
CODE) ?></code></pre>
        </section>

        <section id="request" class="scroll-mt-24">
            <h2 class="font-display text-3xl">Request</h2>
            <p class="mt-3 text-stone-600"><code>request()</code> is global in views, routes and controllers. Form values win when the same name is also in the query string.</p>
            <pre class="mt-4 overflow-x-auto rounded-2xl bg-stone-900 p-5 text-sm leading-relaxed text-stone-100"><code><?= esc(<<<'CODE'
$name = request()->input('name');
$page = request()->query('page');
$request->filled('email');
CODE) ?></code></pre>
        </section>

        <section id="database" class="scroll-mt-24">
            <h2 class="font-display text-3xl">Database</h2>
            <p class="mt-3 text-stone-600">Views call <code>DB::</code>. Controllers call <code>\DB::</code>. Import <code>DBdelta</code> only when you change a table.</p>
            <pre class="mt-4 overflow-x-auto rounded-2xl bg-stone-900 p-5 text-sm leading-relaxed text-stone-100"><code><?= esc(<<<'CODE'
DB::insert('users', ['name' => $name, 'email' => $email]);
$user = DB::get('users', $id);

use App\Core\DBdelta;
DBdelta::add_column('users', 'phone', 'VARCHAR(30) NULL');
CODE) ?></code></pre>
        </section>

        <section id="forms" class="scroll-mt-24">
            <h2 class="font-display text-3xl">Forms</h2>
            <p class="mt-3 text-stone-600">Print <code>csrf()</code> inside the form. Check <code>csrf_verify()</code> yourself before saving. <code>old()</code> and <code>error()</code> must be escaped.</p>
            <pre class="mt-4 overflow-x-auto rounded-2xl bg-stone-900 p-5 text-sm leading-relaxed text-stone-100"><code><?= esc(<<<'CODE'
<?= csrf() ?>
<input name="email" value="<?= esc(old('email')) ?>">
<?php if (has_error('email')): ?>
    <p><?= esc(error('email')) ?></p>
<?php endif; ?>
CODE) ?></code></pre>
        </section>

        <section id="artisan" class="scroll-mt-24">
            <h2 class="font-display text-3xl">Artisan</h2>
            <p class="mt-3 text-stone-600">Run these from the project root. Generators do not register a route for you.</p>
            <pre class="mt-4 overflow-x-auto rounded-2xl bg-stone-900 p-5 text-sm leading-relaxed text-stone-100"><code><?= esc(<<<'CODE'
php artisan serve
php artisan make:controller PostController
php artisan make:view posts
php artisan migrate
php artisan seed
CODE) ?></code></pre>
        </section>
    </div>
</main>
<?php $template->end(); ?>
