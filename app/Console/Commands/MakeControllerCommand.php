<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Command;
use App\Console\Input;
use RuntimeException;

final class MakeControllerCommand extends Command
{
    public function handle(Input $input): int
    {
        $name = $input->argument(0);
        if ($name === null || $name === '') {
            throw new RuntimeException('Usage: php artisan make:controller NameController');
        }

        $segments = $this->classSegments($name, 'Controller');
        $class = (string) array_pop($segments);
        $namespace = 'App\\Controllers' . ($segments === [] ? '' : '\\' . implode('\\', $segments));
        $relative = 'app/Controllers/' . ($segments === [] ? '' : implode('/', $segments) . '/') . $class . '.php';

        $viewSegments = array_map(fn (string $segment): string => $this->snake($segment), $segments);
        $base = preg_replace('/Controller$/', '', $class) ?? $class;
        $viewSegments[] = $this->snake($base);
        $view = implode('/', $viewSegments);
        $route = '/' . $view;

        $contents = $this->stub('controller', [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $class,
            '{{ view }}' => $view,
            '{{ title }}' => $this->titleFrom($class),
        ]);

        $path = $this->writeFile($relative, $contents, $input->hasOption('force'));
        $this->line('Controller created: ' . $path);
        $this->line('Register it in routes/web.php:');
        $this->line('use ' . $namespace . '\\' . $class . ';');
        $this->line("Route::get('" . $route . "', [" . $class . "::class, 'index']);");

        return 0;
    }
}
