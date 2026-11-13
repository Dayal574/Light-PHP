<?php

declare(strict_types=1);

/**
 * Global request API. Loaded by bootstrap, so views, routes and controllers
 * can call request() without an import.
 */
final class Request
{
    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->queryBag(), $this->post());
    }

    public function input(string $key): mixed
    {
        $input = $this->all();

        return array_key_exists($key, $input) ? $input[$key] : null;
    }

    public function query(string $key): mixed
    {
        $query = $this->queryBag();

        return array_key_exists($key, $query) ? $query[$key] : null;
    }

    /** @return array<string, mixed> */
    public function post(): array
    {
        return $_POST;
    }

    public function method(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        return strtoupper(is_string($method) ? $method : 'GET');
    }

    public function isMethod(string $method): bool
    {
        return strcasecmp($this->method(), $method) === 0;
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function cookie(string $key): mixed
    {
        return array_key_exists($key, $_COOKIE) ? $_COOKIE[$key] : null;
    }

    public function header(string $name): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$serverKey] ?? null;

        if ($value === null && strcasecmp($name, 'Content-Type') === 0) {
            $value = $_SERVER['CONTENT_TYPE'] ?? null;
        }
        if ($value === null && strcasecmp($name, 'Content-Length') === 0) {
            $value = $_SERVER['CONTENT_LENGTH'] ?? null;
        }

        return is_string($value) ? $value : null;
    }

    public function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        return is_string($ip) ? $ip : '';
    }

    public function url(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        if (!is_string($host) || $host === '') {
            $host = 'localhost';
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return $scheme . '://' . $host . (is_string($uri) ? $uri : '/');
    }

    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url(is_string($uri) ? $uri : '/', PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $path = '/' . trim(rawurldecode($path), '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function filled(string $key): bool
    {
        if (!$this->has($key)) {
            return false;
        }

        $value = $this->input($key);
        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && $value !== '';
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $input = $this->all();
        $result = [];
        foreach ($keys as $key) {
            if (is_string($key) && array_key_exists($key, $input)) {
                $result[$key] = $input[$key];
            }
        }

        return $result;
    }

    /**
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        $exclude = [];
        foreach ($keys as $key) {
            if (is_string($key)) {
                $exclude[$key] = true;
            }
        }

        $result = [];
        foreach ($this->all() as $key => $value) {
            if (!isset($exclude[$key])) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function queryBag(): array
    {
        return $_GET;
    }
}
