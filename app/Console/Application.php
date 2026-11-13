<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\MakeComponentCommand;
use App\Console\Commands\MakeControllerCommand;
use App\Console\Commands\MakeModelCommand;
use App\Console\Commands\MakeViewCommand;
use App\Console\Commands\MigrateCommand;
use App\Console\Commands\RouteListCommand;
use App\Console\Commands\SeedCommand;
use App\Console\Commands\ServeCommand;
use App\Core\ErrorHandler;
use Throwable;

final class Application
{
    /** @var array<string, class-string<Command>> */
    private array $commands = [
        'serve' => ServeCommand::class,
        'make:controller' => MakeControllerCommand::class,
        'make:model' => MakeModelCommand::class,
        'make:view' => MakeViewCommand::class,
        'make:component' => MakeComponentCommand::class,
        'route:list' => RouteListCommand::class,
        'migrate' => MigrateCommand::class,
        'seed' => SeedCommand::class,
    ];

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $name = $argv[1] ?? 'list';
        if (in_array($name, ['list', 'help', '-h', '--help'], true)) {
            $this->help();
            return 0;
        }

        if (!isset($this->commands[$name])) {
            fwrite(STDERR, 'Command not found: ' . $name . PHP_EOL . PHP_EOL);
            $this->help();
            return 1;
        }

        try {
            $command = new ($this->commands[$name])();
            return $command->handle(Input::fromArgv($argv));
        } catch (Throwable $exception) {
            ErrorHandler::report($exception);
            fwrite(STDERR, $exception->getMessage() . PHP_EOL);
            return 1;
        }
    }

    private function help(): void
    {
        $lines = [
            'Usage: php artisan <command> [options]',
            '',
            'Available commands:',
            '  serve                 Start the PHP server at http://127.0.0.1:8000',
            '  serve --host= --port= Change the host or port',
            '  make:controller       Create a controller in app/Controllers',
            '  make:model            Create a model in app/Models',
            '  make:view             Create a page in views/pages',
            '  make:component        Create a component in views/components',
            '  route:list            Show routes from routes/web.php and routes/api.php',
            '  migrate               Create the database and run database/schema.sql',
            '  seed                  Run database/seeders/DatabaseSeeder.php',
            '  seed --class=         Run one seeder, for example UserSeeder',
            '',
            'Generator options:',
            '  --force               Replace an existing file',
        ];

        fwrite(STDOUT, implode(PHP_EOL, $lines) . PHP_EOL);
    }
}
