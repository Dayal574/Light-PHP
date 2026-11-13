<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionClass;
use RuntimeException;
use Throwable;

final class Router
{
    /** @var array<string, array<string, callable|string|array>> */
    private array $routes = [];

    public function add(string $method, string $path, callable|string|array $handler): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);
        $this->routes[$method][$path] = $handler;
    }

    public function dispatch(): void
    {
        try {
            $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            $path = parse_url($uri, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                $path = '/';
            }
            $path = $this->normalizePath(rawurldecode($path));

            $handler = $this->routes[$method][$path] ?? null;

            if ($handler === null) {
                $allowed = $this->allowedMethods($path);
                if ($allowed !== []) {
                    if (!headers_sent()) {
                        header('Allow: ' . implode(', ', $allowed));
                    }
                    ErrorHandler::abort(405, 'Method Not Allowed');
                }

                ErrorHandler::abort(404, 'Page Not Found');
            }

            $result = $this->runHandler($handler);
            if ($result instanceof Response) {
                $result->send();
            } elseif (is_string($result) || is_numeric($result)) {
                echo (string) $result;
            } elseif ($result !== null) {
                throw new RuntimeException('Route handler must return a string, Response, or null.');
            }
        } catch (HttpException $exception) {
            ErrorHandler::report($exception);
            ErrorHandler::render($exception, $exception->getStatusCode());
        } catch (Throwable $exception) {
            ErrorHandler::report($exception);
            ErrorHandler::render($exception, 500);
        }
    }

    /** @return list<string> */
    private function allowedMethods(string $path): array
    {
        $methods = [];
        foreach ($this->routes as $method => $paths) {
            if (isset($paths[$path])) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    private function runHandler(callable|string|array $handler): mixed
    {
        // A string handler is treated as a trusted, explicitly registered PHP file.
        if (is_string($handler)) {
            $file = realpath($handler);
            if ($file === false || !is_file($file)) {
                throw new RuntimeException('Route file handler not found.');
            }
            return require $file;
        }

        // Resolve [ControllerClass::class, 'method'] to an instance method.
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0]) && class_exists($handler[0])) {
            $class = $handler[0];
            $method = $handler[1];

            if (!is_string($method) || !method_exists($class, $method)) {
                throw new RuntimeException('Route controller method not found: ' . $class . '::' . (string) $method);
            }

            $controller = (new ReflectionClass($class))->newInstance();
            return $controller->{$method}();
        }

        if (!is_callable($handler)) {
            throw new RuntimeException('Route handler is not callable.');
        }

        return call_user_func($handler);
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
