<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Command;
use App\Console\Input;
use Database\Seeders\Seeder;
use PDOException;
use RuntimeException;

final class SeedCommand extends Command
{
    public function handle(Input $input): int
    {
        $class = $this->seederClass($input->option('class', 'DatabaseSeeder') ?? 'DatabaseSeeder');

        try {
            (new $class())->run();
        } catch (PDOException $exception) {
            $message = str_contains($exception->getMessage(), 'Base table or view not found')
                ? 'Database table is missing. Run php artisan migrate first.'
                : 'Seeding failed. ' . $exception->getMessage();

            throw new RuntimeException($message, (int) $exception->getCode(), $exception);
        }

        $short = substr($class, (int) strrpos($class, '\\') + 1);
        $this->line('Seeded: ' . $short);

        return 0;
    }

    /** @return class-string<Seeder> */
    private function seederClass(string $name): string
    {
        $name = ltrim(trim($name), '\\');
        $short = str_contains($name, '\\') ? substr($name, (int) strrpos($name, '\\') + 1) : $name;
        if (!preg_match('/^[A-Z][A-Za-z0-9_]*$/', $short)) {
            throw new RuntimeException('Invalid seeder class: ' . $name);
        }

        $expected = 'Database\\Seeders\\' . $short;
        if ($name !== $short && $name !== $expected) {
            throw new RuntimeException('Seeders must live in Database\\Seeders.');
        }

        $file = BASE_PATH . '/database/seeders/' . $short . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Seeder not found: database/seeders/' . $short . '.php');
        }

        require_once $file;
        if (!class_exists($expected) || !is_subclass_of($expected, Seeder::class)) {
            throw new RuntimeException($expected . ' is not a seeder.');
        }

        return $expected;
    }
}
