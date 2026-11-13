<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    private static bool $registered = false;
    private static bool $handling = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        error_reporting(E_ALL);
        ini_set('display_errors', app_debug() ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(Throwable $exception): void
    {
        self::report($exception);
        self::render($exception, 500);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatalTypes, true)) {
            return;
        }

        self::handleException(new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        ));
    }

    public static function abort(int $status, string $message = ''): never
    {
        $exception = new HttpException($status, $message !== '' ? $message : self::defaultMessage($status));
        self::report($exception);
        self::render($exception, $status);
        exit(1);
    }

    public static function report(Throwable $exception): void
    {
        $line = sprintf(
            "[%s] %s: %s in %s:%d%s",
            date('Y-m-d H:i:s'),
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            PHP_EOL . $exception->getTraceAsString() . PHP_EOL . PHP_EOL
        );

        $logDir = BASE_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        @file_put_contents($logDir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function render(Throwable $exception, ?int $status = null): void
    {
        if (self::$handling) {
            self::fallback($exception, $status ?? 500);
            return;
        }

        self::$handling = true;

        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            $cliMessage = trim($exception->getMessage());
            fwrite(STDERR, ($cliMessage !== '' ? $cliMessage : 'Something went wrong.') . PHP_EOL);
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $status = $status ?? ($exception instanceof HttpException ? $exception->getStatusCode() : 500);
        if ($status < 400 || $status > 599) {
            $status = 500;
        }

        if (!headers_sent()) {
            http_response_code($status);
        }

        if (self::wantsJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }

            $payload = [
                'success' => false,
                'message' => app_debug() || $exception instanceof HttpException
                    ? ($exception->getMessage() !== '' ? $exception->getMessage() : self::defaultMessage($status))
                    : self::defaultMessage($status),
            ];

            if (app_debug() && !($exception instanceof HttpException && $exception->getStatusCode() < 500)) {
                $payload['exception'] = $exception::class;
                $payload['file'] = $exception->getFile();
                $payload['line'] = $exception->getLine();
                $payload['trace'] = explode("\n", $exception->getTraceAsString());
            }

            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $view = $status === 404 ? '404' : '500';
        $message = $exception->getMessage() !== '' ? $exception->getMessage() : self::defaultMessage($status);

        try {
            echo self::renderErrorView($view, [
                'status' => $status,
                'title' => self::defaultMessage($status),
                'message' => $message,
                'exception' => $exception,
            ]);
        } catch (Throwable $renderException) {
            self::report($renderException);
            self::fallback($exception, $status);
        }
    }

    private static function renderErrorView(string $name, array $data): string
    {
        $file = VIEWS_PATH . '/errors/' . $name . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Error view missing: ' . $name);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    private static function fallback(Throwable $exception, int $status): void
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            $cliMessage = trim($exception->getMessage());
            fwrite(STDERR, ($cliMessage !== '' ? $cliMessage : 'Something went wrong.') . PHP_EOL);
            return;
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
        }

        $title = self::defaultMessage($status);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $detail = app_debug()
            ? '<pre>' . htmlspecialchars((string) $exception, ENT_QUOTES, 'UTF-8') . '</pre>'
            : '<p>Something went wrong. Please try again later.</p>';

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>'
            . $safeTitle
            . '</title></head><body style="font-family:system-ui,sans-serif;padding:40px">'
            . '<h1>' . $safeTitle . '</h1>'
            . $detail
            . '</body></html>';
    }

    private static function wantsJson(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH) ?: '';
        if (str_starts_with($path, '/api') || str_contains($path, '/api/')) {
            return true;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json');
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            404 => 'Page Not Found',
            405 => 'Method Not Allowed',
            403 => 'Forbidden',
            401 => 'Unauthorized',
            default => 'Internal Server Error',
        };
    }
}
