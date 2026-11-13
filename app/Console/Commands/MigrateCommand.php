<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Command;
use App\Console\Input;
use App\Core\Database;
use PDOException;
use RuntimeException;

final class MigrateCommand extends Command
{
    public function handle(Input $input): int
    {
        $schema = BASE_PATH . '/database/schema.sql';
        if (!is_file($schema)) {
            throw new RuntimeException('Schema file not found: database/schema.sql');
        }

        $sql = file_get_contents($schema);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Schema file is empty: database/schema.sql');
        }

        $statements = $this->statements($sql);
        if ($statements === []) {
            throw new RuntimeException('Schema file has no SQL statements.');
        }

        Database::createDatabase();
        $connection = Database::connection();

        foreach ($statements as $statement) {
            try {
                $connection->exec($statement);
            } catch (PDOException $exception) {
                throw new RuntimeException(
                    'Migration failed: ' . $exception->getMessage(),
                    (int) $exception->getCode(),
                    $exception
                );
            }
        }

        $database = (string) config('database.connections.mysql.database', '');
        $this->line('Database ready: ' . $database);
        $this->line('Migrated: database/schema.sql');

        return 0;
    }

    /** @return list<string> */
    private function statements(string $sql): array
    {
        $withoutComments = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = [];

        foreach (explode(';', $withoutComments) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }
}
