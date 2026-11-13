<?php
// About page for GET /about. HomeController::about() returns view('about', ...).
$template->layout('layouts/app');
$template->section('title', esc($pageTitle ?? 'About'));
$template->section('content');
?>
<main class="mx-auto max-w-6xl px-6 py-16 md:py-24">
    <p class="text-sm font-medium uppercase tracking-[0.22em] text-orange-700">About</p>
    <h1 class="mt-4 max-w-3xl font-display text-5xl leading-tight text-stone-950">A framework you can read in an afternoon.</h1>
    <div class="mt-10 grid gap-10 md:grid-cols-[1.2fr_0.8fr]">
        <div class="space-y-5 text-lg leading-relaxed text-stone-700">
            <p>Light PHP is for small applications that should stay close to PHP itself. Routes, controllers and views are ordinary classes and files. There is no template language to compile and no ORM to learn first.</p>
            <p>The global helpers cover the work that usually gets messy: reading the request, escaping HTML, saving a form, and running a prepared query. Schema changes stay on <code class="rounded bg-orange-100 px-1.5 py-0.5 text-base">DBdelta</code>, so they are explicit.</p>
            <p>When a page does not need a layout, it can be plain PHP. <code class="rounded bg-orange-100 px-1.5 py-0.5 text-base">get_header()</code> still works if you add those components yourself.</p>
            <p>Developed by Dayal Chandra Roy. Email: <a class="text-orange-700 hover:text-stone-900" href="mailto:dayal01759712574@gmail.com">dayal01759712574@gmail.com</a></p>
        </div>
        <aside class="h-fit rounded-3xl bg-stone-900 p-6 text-stone-100">
            <h2 class="font-display text-2xl">Included</h2>
            <ul class="mt-4 space-y-2 text-sm text-stone-300">
                <li>GET and POST routes</li>
                <li>Controllers and PHP views</li>
                <li>Request, CSRF, old input and errors</li>
                <li>PDO queries and schema changes</li>
                <li><code>php artisan</code> for serve, make, migrate and seed</li>
            </ul>
            <a href="/documentation" class="mt-6 inline-flex rounded-full bg-white px-4 py-2 text-sm font-medium text-stone-900">Open the documentation</a>
        </aside>
    </div>
</main>
<?php $template->end(); ?>
