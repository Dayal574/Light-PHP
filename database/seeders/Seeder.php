<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use PDO;

abstract class Seeder
{
    abstract public function run(): void;

    protected function db(): PDO
    {
        return Database::connection();
    }
}
