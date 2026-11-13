<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Command;
use App\Console\Input;
use RuntimeException;

final class MakeComponentCommand extends Command
{
    public function handle(Input $input): int
    {
        $name = $input->argument(0);
        if ($name === null || $name === '') {
            throw new RuntimeException('Usage: php artisan make:component name');
        }

        $component = $this->viewName($name);
        $contents = $this->stub('component', [
            '{{ class }}' => str_replace('/', '-', $component),
            '{{ title }}' => $this->titleFrom($component),
        ]);

        $path = $this->writeFile('views/components/' . $component . '.php', $contents, $input->hasOption('force'));
        $this->line('Component created: ' . $path);
        $this->line("Render it with get_component('" . $component . "').");

        return 0;
    }
}
