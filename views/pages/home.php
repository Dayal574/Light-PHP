<?php
// Home page for GET /. HomeController::index() returns view('home', ...).
// layout() wraps this file in views/layouts/app.php. end() closes the content section.
$template->layout('layouts/app');
$template->section('title', esc($pageTitle ?? 'Welcome Light PHP'));
$template->section('content');
?>
<main>
    <section class="mx-auto max-w-6xl px-6 pb-16 pt-16 md:pt-24">
        <p class="text-sm font-medium uppercase tracking-[0.22em] text-orange-700">Core PHP starter</p>
        <h1 class="mt-4 max-w-3xl font-display text-5xl leading-[1.05] text-stone-950 md:text-7xl">Welcome<br>Light PHP</h1>
        <p class="mt-6 max-w-xl text-lg leading-relaxed text-stone-600">A small framework for routes, views, requests and MySQL. You write plain PHP. The helpers stay out of the way.</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="/documentation" class="rounded-full bg-orange-700 px-5 py-3 text-sm font-medium text-white transition hover:bg-stone-900">Read the docs</a>
            <a href="/about" class="rounded-full border border-stone-300 bg-white px-5 py-3 text-sm font-medium text-stone-800 transition hover:border-stone-900">About the project</a>
        </div>
    </section>
    <section class="mx-auto grid max-w-6xl gap-4 px-6 pb-20 md:grid-cols-3">
        <?php
        $features = [
            ['Routes', 'Map a URL to a controller method. The method returns a view.'],
            ['Views', 'Use a layout and sections, or write the page as plain PHP.'],
            ['Database', 'Call DB::insert() and DB::get() with prepared statements.'],
        ];
        foreach ($features as [$title, $copy]):
        ?>
            <article class="rounded-3xl border border-stone-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                <h2 class="font-display text-2xl"><?= esc($title) ?></h2>
                <p class="mt-3 text-sm leading-relaxed text-stone-600"><?= esc($copy) ?></p>
            </article>
        <?php endforeach; ?>
    </section>
</main>
<?php $template->end(); ?>
