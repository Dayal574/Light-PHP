<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Static, Laravel-like route registration facade. */
final class Route
{
    private static ?Router $router = null;
    private static string $prefix = '';

    public static function setRouter(Router $router): void
    {
        self::$router = $router;
    }

    public static function get(string $path, callable|string|array $handler): void
    {
        self::router()->add('GET', self::withPrefix($path), $handler);
    }

    public static function post(string $path, callable|string|array $handler): void
    {
        self::router()->add('POST', self::withPrefix($path), $handler);
    }

    /** Register a group of routes under a common prefix. */
    public static function prefix(string $prefix, callable $callback): void
    {
        $previous = self::$prefix;
        self::$prefix = self::normalizePath($previous . '/' . trim($prefix, '/'));
        try {
            $callback();
        } finally {
            self::$prefix = $previous;
        }
    }

    private static function router(): Router
    {
        if (self::$router === null) {
            throw new RuntimeException('Router has not been initialized.');
        }
        return self::$router;
    }

    private static function withPrefix(string $path): string
    {
        return self::normalizePath(self::$prefix . '/' . ltrim($path, '/'));
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
