<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Command;
use App\Console\Input;
use RuntimeException;

final class MakeViewCommand extends Command
{
    public function handle(Input $input): int
    {
        $name = $input->argument(0);
        if ($name === null || $name === '') {
            throw new RuntimeException('Usage: php artisan make:view name');
        }

        $view = $this->viewName($name);
        $contents = $this->stub('view', [
            '{{ title }}' => $this->titleFrom($view),
        ]);

        $path = $this->writeFile('views/pages/' . $view . '.php', $contents, $input->hasOption('force'));
        $this->line('View created: ' . $path);
        $this->line("Render it with view('" . $view . "').");

        return 0;
    }
}
