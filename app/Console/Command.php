<?php

declare(strict_types=1);

namespace App\Console;

use RuntimeException;

abstract class Command
{
    abstract public function handle(Input $input): int;

    protected function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    /** @param array<string, string> $replace */
    protected function stub(string $name, array $replace): string
    {
        $file = BASE_PATH . '/app/Console/stubs/' . $name . '.stub';
        $contents = is_file($file) ? file_get_contents($file) : false;
        if ($contents === false) {
            throw new RuntimeException('Stub not found: ' . $name);
        }

        return strtr($contents, $replace);
    }

    protected function writeFile(string $relative, string $contents, bool $force): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, '../')) {
            throw new RuntimeException('Invalid output path.');
        }

        $path = BASE_PATH . '/' . $relative;
        if (is_file($path) && !$force) {
            throw new RuntimeException('File already exists: ' . $relative . '. Use --force to replace it.');
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create directory: ' . $directory);
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to write file: ' . $relative);
        }

        return $relative;
    }

    /** @return list<string> */
    protected function classSegments(string $name, string $suffix = ''): array
    {
        $name = trim(str_replace('\\', '/', $name), '/');
        if ($name === '' || str_contains($name, '..')) {
            throw new RuntimeException('Invalid name.');
        }

        $segments = [];
        foreach (explode('/', $name) as $segment) {
            $studly = $this->studly($segment);
            if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $studly)) {
                throw new RuntimeException('Invalid name: ' . $name);
            }
            $segments[] = $studly;
        }

        if ($suffix !== '' && !str_ends_with($segments[array_key_last($segments)], $suffix)) {
            $segments[array_key_last($segments)] .= $suffix;
        }

        return $segments;
    }

    protected function viewName(string $name): string
    {
        $name = strtolower(trim(str_replace('\\', '/', $name), '/'));
        if ($name === '' || str_contains($name, '..') || !preg_match('~^[a-z0-9_/]+$~', $name)) {
            throw new RuntimeException('Invalid name. Use letters, numbers, underscores and slashes.');
        }

        return $name;
    }

    protected function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', trim($value));

        return str_replace(' ', '', ucwords($value));
    }

    protected function snake(string $value): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $value);

        return strtolower($snake ?? $value);
    }

    protected function plural(string $snake): string
    {
        $parts = explode('_', $snake);
        $last = (string) array_pop($parts);

        if (str_ends_with($last, 'y') && strlen($last) > 1 && !in_array(substr($last, -2, 1), ['a', 'e', 'i', 'o', 'u'], true)) {
            $last = substr($last, 0, -1) . 'ies';
        } elseif (preg_match('/(?:s|x|z|ch|sh)$/', $last) === 1) {
            $last .= 'es';
        } else {
            $last .= 's';
        }

        $parts[] = $last;

        return implode('_', $parts);
    }

    protected function titleFrom(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $base = preg_replace('/Controller$/', '', $base) ?? $base;
        $base = str_replace('_', ' ', $this->snake($base));

        return ucwords($base);
    }
}
