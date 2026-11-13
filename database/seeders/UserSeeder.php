<?php

declare(strict_types=1);

namespace Database\Seeders;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        $statement = $this->db()->prepare(
            'INSERT IGNORE INTO users (name, email) VALUES (:name, :email)'
        );
        $statement->execute([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
        ]);
    }
}
