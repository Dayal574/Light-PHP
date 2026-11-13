<?php

declare(strict_types=1);

// Root entry point. In production, configure the web server to use /public.
$basePath = __DIR__;

// Composer autoload (third-party packages + App\\ PSR-4 + helper functions).
$autoload = $basePath . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
} else {
    // Small fallback for first run before `composer install`.
    spl_autoload_register(static function (string $class) use ($basePath): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = $basePath . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });

    require_once $basePath . '/app/Core/helpers.php';
    require_once $basePath . '/app/Helpers/helpers.php';
}

require $basePath . '/bootstrap/bootstrap.php';

use App\Core\ErrorHandler;
use App\Core\Route;
use App\Core\Router;

try {
    $router = new Router();
    Route::setRouter($router);

    // Register application routes.
    require $basePath . '/routes/web.php';
    require $basePath . '/routes/api.php';

    // Match and execute the current request.
    $router->dispatch();
} catch (Throwable $exception) {
    ErrorHandler::report($exception);
    ErrorHandler::render($exception, 500);
}