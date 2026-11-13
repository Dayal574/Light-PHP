<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) ($title ?? 'Internal Server Error')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#faf7f2] text-stone-900 antialiased">
<main class="mx-auto flex min-h-screen max-w-3xl flex-col justify-center px-6 py-16">
    <p class="text-sm font-medium uppercase tracking-[0.22em] text-orange-700">Error <?= (int) ($status ?? 500) ?></p>
    <h1 class="mt-3 font-serif text-5xl"><?= e((string) ($title ?? 'Something went wrong')) ?></h1>
    <?php if (app_debug() && isset($exception) && $exception instanceof Throwable): ?>
        <p class="mt-4 text-lg text-stone-700"><?= e($exception->getMessage()) ?></p>
        <pre class="mt-6 overflow-x-auto rounded-2xl bg-stone-900 p-5 text-sm text-stone-100"><?= e((string) $exception) ?></pre>
    <?php else: ?>
        <p class="mt-4 text-lg text-stone-600">We could not complete this request. Please try again later.</p>
    <?php endif; ?>
    <a href="/" class="mt-8 inline-flex w-fit rounded-full bg-stone-900 px-5 py-3 text-sm text-white">Back to home</a>
</main>
</body>
</html>
