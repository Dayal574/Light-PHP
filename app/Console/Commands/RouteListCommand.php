<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Command;
use App\Console\Input;

final class RouteListCommand extends Command
{
    public function handle(Input $input): int
    {
        $routes = array_merge(
            $this->routesFrom('routes/web.php'),
            $this->routesFrom('routes/api.php')
        );

        if ($routes === []) {
            $this->line('No routes found.');
            return 0;
        }

        $this->line(str_pad('Method', 8) . str_pad('Path', 24) . 'Handler');
        foreach ($routes as $route) {
            $this->line(str_pad($route['method'], 8) . str_pad($route['path'], 24) . $route['handler']);
        }

        return 0;
    }

    /** @return list<array{method: string, path: string, handler: string}> */
    private function routesFrom(string $relative): array
    {
        $path = BASE_PATH . '/' . $relative;
        if (!is_file($path)) {
            return [];
        }

        $code = file_get_contents($path);
        if ($code === false) {
            return [];
        }

        $routes = [];
        $prefixes = [];
        $depth = 0;
        $length = strlen($code);

        for ($index = 0; $index < $length; $index++) {
            $character = $code[$index];
            if ($character === "'" || $character === '"') {
                $index = $this->skipString($code, $index) - 1;
                continue;
            }

            if ($character === '{') {
                $depth++;
                $last = array_key_last($prefixes);
                if ($last !== null && $prefixes[$last]['depth'] === null) {
                    $prefixes[$last]['depth'] = $depth;
                }
                continue;
            }

            if ($character === '}') {
                $depth--;
                $last = array_key_last($prefixes);
                if ($last !== null && $prefixes[$last]['depth'] !== null && $depth < $prefixes[$last]['depth']) {
                    array_pop($prefixes);
                }
                continue;
            }

            if (!str_starts_with(substr($code, $index), 'Route::')) {
                continue;
            }

            if (preg_match('/\ARoute::prefix\s*\(\s*([\'"])([^\'"]*)\1/i', substr($code, $index), $match) === 1) {
                $prefixes[] = ['path' => $match[2], 'depth' => null];
                $index += strlen($match[0]) - 1;
                continue;
            }

            if (preg_match('/\ARoute::(get|post)\s*\(\s*([\'"])([^\'"]*)\2/i', substr($code, $index), $match) !== 1) {
                continue;
            }

            $callEnd = $this->callEnd($code, $index + strlen('Route::' . $match[1]));
            $call = substr($code, $index, $callEnd - $index + 1);
            $routes[] = [
                'method' => strtoupper($match[1]),
                'path' => $this->joinPath($prefixes, $match[3]),
                'handler' => $this->handler($call),
            ];
            $index = $callEnd;
        }

        return $routes;
    }

    /** @param list<array{path: string, depth: int|null}> $prefixes */
    private function joinPath(array $prefixes, string $path): string
    {
        $prefix = '';
        foreach ($prefixes as $item) {
            $prefix .= '/' . trim($item['path'], '/');
        }

        $full = '/' . trim($prefix . '/' . ltrim($path, '/'), '/');

        return $full === '/' ? '/' : rtrim($full, '/');
    }

    private function handler(string $call): string
    {
        if (preg_match('/\[([A-Za-z0-9_\\\\]+)::class\s*,\s*[\'"]([A-Za-z0-9_]+)[\'"]\]/', $call, $match) === 1) {
            $class = $match[1];
            $short = str_contains($class, '\\') ? substr($class, (int) strrpos($class, '\\') + 1) : $class;

            return $short . '@' . $match[2];
        }

        return 'Closure';
    }

    private function callEnd(string $code, int $openParen): int
    {
        $depth = 0;
        $length = strlen($code);
        for ($index = $openParen; $index < $length; $index++) {
            $character = $code[$index];
            if ($character === "'" || $character === '"') {
                $index = $this->skipString($code, $index) - 1;
                continue;
            }
            if ($character === '(') {
                $depth++;
            } elseif ($character === ')' && --$depth === 0) {
                return $index;
            }
        }

        return $length - 1;
    }

    private function skipString(string $code, int $index): int
    {
        $quote = $code[$index];
        $length = strlen($code);
        $index++;

        while ($index < $length) {
            if ($code[$index] === '\\') {
                $index += 2;
                continue;
            }
            if ($code[$index] === $quote) {
                return $index + 1;
            }
            $index++;
        }

        return $length;
    }
}
