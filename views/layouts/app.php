<?php
// Shared layout for the starter pages. A page calls $template->layout('layouts/app'),
// then fills the "title" and "content" sections. yield() prints those sections here.
$current = request()->path();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php $template->yield('title', 'Light PHP'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,680&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['Fraunces', 'Georgia', 'serif'],
                        sans: ['Outfit', 'ui-sans-serif', 'system-ui', 'sans-serif']
                    }
                }
            }
        };
    </script>
</head>
<body class="bg-[#faf7f2] font-sans text-stone-900 antialiased">
<header class="sticky top-0 z-20 border-b border-stone-200/80 bg-[#faf7f2]/90 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
        <a href="/" class="flex items-center gap-3">
            <svg viewBox="0 0 32 32" class="h-9 w-9" aria-hidden="true">
                <rect width="32" height="32" rx="10" fill="#c2410c"/>
                <path d="M16 6.5v3.2M16 22.3v3.2M6.5 16h3.2M22.3 16h3.2M9.2 9.2l2.2 2.2M20.6 20.6l2.2 2.2M22.8 9.2l-2.2 2.2M11.4 20.6l-2.2 2.2" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
                <circle cx="16" cy="16" r="3.1" fill="#fff"/>
            </svg>
            <span class="font-display text-xl tracking-tight">Light PHP</span>
        </a>
        <button id="nav-toggle" type="button" class="rounded-full border border-stone-300 px-3 py-2 text-sm md:hidden" aria-expanded="false" aria-controls="site-nav">
            Menu
        </button>
        <nav id="site-nav" class="hidden w-full flex-col gap-1 md:flex md:w-auto md:flex-row md:items-center md:gap-6">
            <?php foreach (['/' => 'Home', '/about' => 'About', '/documentation' => 'Documentation'] as $href => $label): ?>
                <a href="<?= esc($href) ?>" class="rounded-full px-3 py-2 text-sm <?= $current === $href ? 'bg-stone-900 text-white' : 'text-stone-600 hover:text-stone-900' ?>">
                    <?= esc($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
<?php $template->yield('content'); ?>
<footer class="border-t border-stone-200">
    <div class="mx-auto flex max-w-6xl flex-col gap-2 px-6 py-8 text-sm text-stone-500 sm:flex-row sm:items-center sm:justify-between">
        <p>Developed by <a class="hover:text-stone-900" href="mailto:dayal01759712574@gmail.com">Dayal Chandra Roy</a></p>
        <a href="/documentation" class="hover:text-stone-900">Documentation</a>
    </div>
</footer>
<script>
    document.getElementById('nav-toggle').addEventListener('click', function () {
        var nav = document.getElementById('site-nav');
        var open = nav.classList.toggle('hidden');
        nav.classList.toggle('flex');
        this.setAttribute('aria-expanded', open ? 'false' : 'true');
    });
</script>
</body>
</html>
