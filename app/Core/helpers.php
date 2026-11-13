<?php

declare(strict_types=1);

/** Framework functions. Application helpers belong in app/Helpers/helpers.php. */

use App\Core\ErrorHandler;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        if (!is_string($value)) {
            return $value;
        }
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (!function_exists('app_debug')) {
    function app_debug(): bool
    {
        $value = env('APP_DEBUG', false);
        if (is_bool($value)) {
            return $value;
        }

        return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('esc')) {
    /** Escape a value for HTML text and attributes. Null becomes an empty string. */
    function esc(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return esc($value);
    }
}

if (!function_exists('request')) {
    function request(): Request
    {
        static $request = null;
        if (!$request instanceof Request) {
            $request = new Request();
        }

        return $request;
    }
}

if (!function_exists('report')) {
    function report(Throwable $exception): void
    {
        ErrorHandler::report($exception);
    }
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = ''): never
    {
        ErrorHandler::abort($status, $message);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $loaded = [];
        [$file, $nested] = array_pad(explode('.', $key, 2), 2, '');

        if (!array_key_exists($file, $loaded)) {
            $path = BASE_PATH . '/config/' . $file . '.php';
            $loaded[$file] = is_file($path) ? require $path : [];
        }

        $value = $loaded[$file];
        if ($nested !== '') {
            foreach (explode('.', $nested) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return $default;
                }
                $value = $value[$segment];
            }
        }
        return $value ?? $default;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return BASE_PATH . '/public' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

/** Build an asset URL that works with either project-root or public/ document root. */
if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '../')) {
            throw new RuntimeException('Invalid asset path.');
        }

        $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
        $projectRoot = realpath(BASE_PATH);
        $publicRoot = realpath(BASE_PATH . '/public');
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');

        if ($documentRoot !== false && $publicRoot !== false && $documentRoot === $publicRoot) {
            // Normal /public document root; preserve a subdirectory install if present.
            $prefix = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');
        } elseif ($documentRoot !== false && $projectRoot !== false && $documentRoot === $projectRoot) {
            // Project root used as document root: assets live under /public.
            $prefix = '/public';
        } else {
            // Fallback for custom aliases / development setups.
            $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/.');
            $prefix = ($scriptDir === '' ? '' : $scriptDir) . '/public';
        }

        return ($prefix === '' ? '' : $prefix) . '/assets/' . $path;
    }
}

/** Escaped asset URL for HTML attributes. Accepts css/app.css or assets/css/app.css. */
if (!function_exists('assets_url')) {
    function assets_url(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($path, 'assets/')) {
            $path = substr($path, strlen('assets/'));
        }

        return e(asset($path));
    }
}

if (!function_exists('view')) {
    /** Render a page view and return its HTML. $template is available inside the view. */
    function view(string $name, array $data = []): string
    {
        $file = resolve_view_file('pages', $name);

        return (new \App\Core\Template())->render($file, $data);
    }
}

if (!function_exists('get_header')) {
    function get_header(string $name = 'header', array $data = []): void
    {
        render_component($name, $data);
    }
}

if (!function_exists('get_footer')) {
    function get_footer(string $name = 'footer', array $data = []): void
    {
        render_component($name, $data);
    }
}

if (!function_exists('get_sidebar')) {
    function get_sidebar(string $name = 'sidebar', array $data = []): void
    {
        render_component($name, $data);
    }
}

if (!function_exists('get_component')) {
    function get_component(string $name, array $data = []): void
    {
        render_component($name, $data);
    }
}

if (!function_exists('render_component')) {
    function render_component(string $name, array $data = []): void
    {
        $file = resolve_view_file('components', $name);
        $current = \App\Core\Template::current();
        if ($current !== null) {
            $template = $current;
            unset($data['template']);
        }
        extract($data, EXTR_SKIP);
        require $file;
    }
}

if (!function_exists('resolve_view_file')) {
    function resolve_view_file(string $directory, string $name): string
    {
        if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || !preg_match('~^[a-zA-Z0-9_/-]+$~', $name)) {
            throw new RuntimeException('Invalid view/component name.');
        }

        if ($directory !== '' && (str_contains($directory, '..') || str_starts_with($directory, '/') || !preg_match('~^[a-zA-Z0-9_/-]+$~', $directory))) {
            throw new RuntimeException('Invalid view directory.');
        }

        $folder = $directory === '' ? VIEWS_PATH : VIEWS_PATH . '/' . $directory;
        $base = realpath($folder);
        $file = realpath($folder . '/' . $name . '.php');

        if ($base === false || $file === false || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
            $label = $directory === '' ? 'View' : ucfirst(rtrim($directory, 's'));
            throw new RuntimeException($label . ' not found: ' . $name);
        }

        return $file;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session has not been started.');
        }

        $token = $_SESSION['_csrf'] ?? '';
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION['_csrf'] = $token;
        }

        return $token;
    }
}

if (!function_exists('csrf')) {
    function csrf(): string
    {
        return '<input type="hidden" name="_token" value="' . esc(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(): bool
    {
        $posted = $_POST['_token'] ?? '';
        $saved = $_SESSION['_csrf'] ?? '';
        if (!is_string($posted) || !is_string($saved) || $posted === '' || $saved === '') {
            return false;
        }

        return hash_equals($saved, $posted);
    }
}

if (!function_exists('form_flash_boot')) {
    /** Load one-time flash input and errors, then remove them from the session. */
    function form_flash_boot(): void
    {
        $store = &form_flash_store();
        $old = $_SESSION['_flash_old'] ?? null;
        $errors = $_SESSION['_flash_errors'] ?? null;
        if (is_array($old)) {
            $store['old'] = $old;
        }
        if (is_array($errors)) {
            $store['errors'] = $errors;
        }
        unset($_SESSION['_flash_old'], $_SESSION['_flash_errors']);
    }
}

if (!function_exists('flash_old')) {
    /** @param array<string, mixed> $input */
    function flash_old(array $input): void
    {
        $store = &form_flash_store();
        $store['old'] = $input;
        $store['old_pending'] = true;
    }
}

if (!function_exists('flash_errors')) {
    /** @param array<string, mixed> $errors */
    function flash_errors(array $errors): void
    {
        $store = &form_flash_store();
        $store['errors'] = $errors;
        $store['errors_pending'] = true;
    }
}

if (!function_exists('old')) {
    function old(?string $key = null, string $default = ''): mixed
    {
        $old = form_flash_store()['old'];
        if ($key === null) {
            return $old;
        }

        $value = $old[$key] ?? $default;

        return is_scalar($value) ? (string) $value : $default;
    }
}

if (!function_exists('error')) {
    function error(?string $key = null): mixed
    {
        $errors = form_flash_store()['errors'];
        if ($key === null) {
            return $errors;
        }

        $message = $errors[$key] ?? '';

        return is_scalar($message) ? (string) $message : '';
    }
}

if (!function_exists('has_error')) {
    function has_error(string $key): bool
    {
        return error($key) !== '';
    }
}

if (!function_exists('redirect')) {
    function redirect(string $to): never
    {
        if (!preg_match('#^/[^\\s]*$#', $to) || str_starts_with($to, '//')) {
            throw new RuntimeException('Invalid redirect path.');
        }

        $store = &form_flash_store();
        if (!empty($store['old_pending'])) {
            $_SESSION['_flash_old'] = $store['old'];
        }
        if (!empty($store['errors_pending'])) {
            $_SESSION['_flash_errors'] = $store['errors'];
        }

        header('Location: ' . $to);
        exit;
    }
}

if (!function_exists('form_flash_store')) {
    /**
     * @return array{old: array<string, mixed>, errors: array<string, mixed>, old_pending: bool, errors_pending: bool}
     */
    function &form_flash_store(): array
    {
        static $store = [
            'old' => [],
            'errors' => [],
            'old_pending' => false,
            'errors_pending' => false,
        ];

        return $store;
    }
}
