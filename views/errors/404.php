<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) ($title ?? 'Page Not Found')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#faf7f2] text-stone-900 antialiased">
<main class="mx-auto flex min-h-screen max-w-xl flex-col justify-center px-6">
    <p class="text-sm font-medium uppercase tracking-[0.22em] text-orange-700">Error <?= (int) ($status ?? 404) ?></p>
    <h1 class="mt-3 font-serif text-5xl"><?= e((string) ($title ?? 'Page Not Found')) ?></h1>
    <p class="mt-4 text-lg text-stone-600"><?= e((string) ($message ?? 'The page you requested could not be found.')) ?></p>
    <a href="/" class="mt-8 inline-flex w-fit rounded-full bg-stone-900 px-5 py-3 text-sm text-white">Back to home</a>
</main>
</body>
</html>
