<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Command;
use App\Console\Input;
use RuntimeException;

final class MakeModelCommand extends Command
{
    public function handle(Input $input): int
    {
        $name = $input->argument(0);
        if ($name === null || $name === '') {
            throw new RuntimeException('Usage: php artisan make:model Name');
        }

        $segments = $this->classSegments($name);
        $class = (string) array_pop($segments);
        if (str_ends_with($class, 'Controller')) {
            throw new RuntimeException('Model names should not end with Controller.');
        }

        $namespace = 'App\\Models' . ($segments === [] ? '' : '\\' . implode('\\', $segments));
        $relative = 'app/Models/' . ($segments === [] ? '' : implode('/', $segments) . '/') . $class . '.php';
        $table = $this->plural($this->snake($class));

        $contents = $this->stub('model', [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $class,
            '{{ table }}' => $table,
        ]);

        $path = $this->writeFile($relative, $contents, $input->hasOption('force'));
        $this->line('Model created: ' . $path);
        $this->line('Table name: ' . $table);

        return 0;
    }
}
