<?php

declare(strict_types=1);

namespace Database\Seeders;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        (new UserSeeder())->run();
    }
}
