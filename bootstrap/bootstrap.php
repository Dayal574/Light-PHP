<?php

declare(strict_types=1);

use Dotenv\Dotenv;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (!defined('VIEWS_PATH')) {
    define('VIEWS_PATH', BASE_PATH . '/views');
}

require_once BASE_PATH . '/app/Core/helpers.php';
require_once BASE_PATH . '/app/Helpers/helpers.php';

// Load .env. phpdotenv is used when Composer dependencies are installed.
$envFile = BASE_PATH . '/.env';
if (is_file($envFile)) {
    if (class_exists(Dotenv::class)) {
        Dotenv::createImmutable(BASE_PATH)->safeLoad();
    } else {
        // Basic fallback parser; Composer/ phpdotenv is preferred.
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($value !== '' && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            if ($key !== '') {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv($key . '=' . $value);
            }
        }
    }
}

$timezone = env('APP_TIMEZONE', 'UTC');
try {
    date_default_timezone_set((string) $timezone);
} catch (Throwable $exception) {
    date_default_timezone_set('UTC');
}

\App\Core\ErrorHandler::register();

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
    form_flash_boot();
}

require_once BASE_PATH . '/app/Core/DB.php';
require_once BASE_PATH . '/app/Core/Request.php';
