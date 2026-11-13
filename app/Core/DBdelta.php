<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

final class DBdelta
{
    public static function exists(string $table): bool
    {
        self::quote($table);

        return self::tableExists($table);
    }

    /** @param array<string, string> $columns */
    public static function create(string $table, array $columns): void
    {
        if ($columns === []) {
            throw new RuntimeException('At least one column is required.');
        }

        $definitions = [];
        foreach ($columns as $name => $definition) {
            if (!is_string($name) || !is_string($definition)) {
                throw new RuntimeException('Invalid column definition.');
            }
            $definitions[] = self::quote($name) . ' ' . self::columnDefinition($definition);
        }

        self::execute(
            'CREATE TABLE IF NOT EXISTS ' . self::quote($table) . ' (' . implode(', ', $definitions) . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public static function empty(string $table): void
    {
        self::requireTable($table);
        self::execute('TRUNCATE TABLE ' . self::quote($table));
    }

    public static function drop(string $table): void
    {
        self::requireTable($table);
        self::execute('DROP TABLE ' . self::quote($table));
    }

    public static function rename(string $from, string $to): void
    {
        self::requireTable($from);
        $target = self::quote($to);
        if (self::tableExists($to)) {
            throw new RuntimeException('Table already exists: ' . $to);
        }

        self::execute('RENAME TABLE ' . self::quote($from) . ' TO ' . $target);
    }

    public static function add_column(string $table, string $column, string $definition): void
    {
        $sqlDefinition = self::columnDefinition($definition);
        $quotedColumn = self::quote($column);
        $quotedTable = self::requireTable($table);
        if (self::columnExists($table, $column)) {
            throw new RuntimeException('Column already exists: ' . $column);
        }

        self::execute('ALTER TABLE ' . $quotedTable . ' ADD COLUMN ' . $quotedColumn . ' ' . $sqlDefinition);
    }

    public static function drop_column(string $table, string $column): void
    {
        $quotedTable = self::requireTable($table);
        $quotedColumn = self::requireColumn($table, $column);
        self::execute('ALTER TABLE ' . $quotedTable . ' DROP COLUMN ' . $quotedColumn);
    }

    public static function rename_column(string $table, string $from, string $to): void
    {
        $quotedTable = self::requireTable($table);
        $quotedFrom = self::requireColumn($table, $from);
        $quotedTo = self::quote($to);
        if (self::columnExists($table, $to)) {
            throw new RuntimeException('Column already exists: ' . $to);
        }

        self::execute('ALTER TABLE ' . $quotedTable . ' RENAME COLUMN ' . $quotedFrom . ' TO ' . $quotedTo);
    }

    public static function modify_column(string $table, string $column, string $definition): void
    {
        $sqlDefinition = self::columnDefinition($definition);
        $quotedTable = self::requireTable($table);
        $quotedColumn = self::requireColumn($table, $column);
        self::execute('ALTER TABLE ' . $quotedTable . ' MODIFY COLUMN ' . $quotedColumn . ' ' . $sqlDefinition);
    }

    public static function add_index(string $table, string $column): void
    {
        self::requireColumn($table, $column);
        $index = $table . '_' . $column . '_index';
        if (strlen($index) > 64) {
            throw new RuntimeException('Index name is too long.');
        }
        if (self::indexExists($table, $index)) {
            throw new RuntimeException('Index already exists: ' . $index);
        }

        self::execute(
            'ALTER TABLE ' . self::quote($table) . ' ADD INDEX ' . self::quote($index) . ' (' . self::quote($column) . ')'
        );
    }

    public static function drop_index(string $table, string $index): void
    {
        self::requireTable($table);
        self::quote($index);
        if (!self::indexExists($table, $index)) {
            throw new RuntimeException('Index not found: ' . $index);
        }

        self::execute('ALTER TABLE ' . self::quote($table) . ' DROP INDEX ' . self::quote($index));
    }

    private static function requireTable(string $table): string
    {
        $quoted = self::quote($table);
        if (!self::tableExists($table)) {
            throw new RuntimeException('Table not found: ' . $table);
        }

        return $quoted;
    }

    private static function requireColumn(string $table, string $column): string
    {
        self::requireTable($table);
        $quoted = self::quote($column);
        if (!self::columnExists($table, $column)) {
            throw new RuntimeException('Column not found: ' . $column);
        }

        return $quoted;
    }

    private static function tableExists(string $table): bool
    {
        return (int) self::run(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        )->fetchColumn() > 0;
    }

    private static function columnExists(string $table, string $column): bool
    {
        return (int) self::run(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        )->fetchColumn() > 0;
    }

    private static function indexExists(string $table, string $index): bool
    {
        return (int) self::run(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        )->fetchColumn() > 0;
    }

    private static function columnDefinition(string $definition): string
    {
        if ($definition === '' || preg_match('/[;`]|--|\/\*|\*\//', $definition) === 1) {
            throw new RuntimeException('Invalid column definition.');
        }

        $tokens = self::tokens($definition);
        if ($tokens === []) {
            throw new RuntimeException('Invalid column definition.');
        }

        $type = strtoupper($tokens[0]);
        $allowed = [
            'BIGINT', 'INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT',
            'VARCHAR', 'CHAR', 'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT',
            'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'YEAR',
            'DECIMAL', 'NUMERIC', 'FLOAT', 'DOUBLE', 'BOOLEAN', 'BOOL', 'JSON',
        ];
        if (!in_array($type, $allowed, true)) {
            throw new RuntimeException('Invalid column definition.');
        }

        $numeric = ['BIGINT', 'INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'DECIMAL', 'NUMERIC', 'FLOAT', 'DOUBLE'];
        $integer = ['BIGINT', 'INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT'];
        $index = 1;
        $normalized = [$type];

        if (isset($tokens[$index]) && str_starts_with($tokens[$index], '(')) {
            if (!in_array($type, array_merge($numeric, ['VARCHAR', 'CHAR']), true) || preg_match('/^\(\d+(?:,\s*\d+)?\)$/', $tokens[$index]) !== 1) {
                throw new RuntimeException('Invalid column definition.');
            }
            if (!in_array($type, ['DECIMAL', 'NUMERIC', 'FLOAT', 'DOUBLE'], true) && str_contains($tokens[$index], ',')) {
                throw new RuntimeException('Invalid column definition.');
            }
            $normalized[0] .= $tokens[$index];
            $index++;
        } elseif (in_array($type, ['VARCHAR', 'CHAR'], true)) {
            throw new RuntimeException('Invalid column definition.');
        }

        $seen = [];
        $count = count($tokens);
        while ($index < $count) {
            $token = strtoupper($tokens[$index]);
            if (in_array($token, ['UNSIGNED', 'ZEROFILL'], true)) {
                if (!in_array($type, $numeric, true) || isset($seen[$token])) {
                    throw new RuntimeException('Invalid column definition.');
                }
                $seen[$token] = true;
                $normalized[] = $token;
                $index++;
                continue;
            }
            if ($token === 'AUTO_INCREMENT') {
                if (!in_array($type, $integer, true) || isset($seen[$token])) {
                    throw new RuntimeException('Invalid column definition.');
                }
                $seen[$token] = true;
                $normalized[] = $token;
                $index++;
                continue;
            }
            if ($token === 'PRIMARY') {
                if (strtoupper($tokens[$index + 1] ?? '') !== 'KEY' || isset($seen['PRIMARY'])) {
                    throw new RuntimeException('Invalid column definition.');
                }
                $seen['PRIMARY'] = true;
                $normalized[] = 'PRIMARY KEY';
                $index += 2;
                continue;
            }
            if ($token === 'UNIQUE') {
                if (isset($seen['UNIQUE'])) {
                    throw new RuntimeException('Invalid column definition.');
                }
                $seen['UNIQUE'] = true;
                $normalized[] = 'UNIQUE';
                $index++;
                continue;
            }
            if ($token === 'NOT') {
                if (strtoupper($tokens[$index + 1] ?? '') !== 'NULL' || isset($seen['NULL'])) {
                    throw new RuntimeException('Invalid column definition.');
                }
                $seen['NULL'] = true;
                $normalized[] = 'NOT NULL';
                $index += 2;
                continue;
            }
            if ($token === 'NULL') {
                if (isset($seen['NULL'])) {
                    throw new RuntimeException('Invalid column definition.');
                }
                $seen['NULL'] = true;
                $normalized[] = 'NULL';
                $index++;
                continue;
            }
            if ($token === 'DEFAULT') {
                $next = $tokens[$index + 1] ?? null;
                if ($next === null || isset($seen['DEFAULT'])) {
                    throw new RuntimeException('Invalid column definition.');
                }
                $seen['DEFAULT'] = true;
                $normalized[] = 'DEFAULT ' . self::defaultValue($next);
                $index += 2;
                continue;
            }

            throw new RuntimeException('Invalid column definition.');
        }

        return implode(' ', $normalized);
    }

    /** @return list<string> */
    private static function tokens(string $definition): array
    {
        $tokens = [];
        $length = strlen($definition);
        $index = 0;

        while ($index < $length) {
            $character = $definition[$index];
            if (ctype_space($character)) {
                $index++;
                continue;
            }

            if ($character === "'") {
                $value = "'";
                $index++;
                $closed = false;
                while ($index < $length) {
                    if ($definition[$index] === "'" && ($definition[$index + 1] ?? '') === "'") {
                        $value .= "''";
                        $index += 2;
                        continue;
                    }
                    $value .= $definition[$index];
                    if ($definition[$index] === "'") {
                        $closed = true;
                        $index++;
                        break;
                    }
                    $index++;
                }
                if (!$closed) {
                    throw new RuntimeException('Invalid column definition.');
                }
                $tokens[] = $value;
                continue;
            }

            if ($character === '(') {
                $end = strpos($definition, ')', $index);
                if ($end === false) {
                    throw new RuntimeException('Invalid column definition.');
                }
                $tokens[] = substr($definition, $index, $end - $index + 1);
                $index = $end + 1;
                continue;
            }

            if (preg_match('/[A-Za-z_][A-Za-z0-9_]*|-?\d+(?:\.\d+)?/', $definition, $match, 0, $index) === 1 && str_starts_with(substr($definition, $index), $match[0])) {
                $tokens[] = $match[0];
                $index += strlen($match[0]);
                continue;
            }

            throw new RuntimeException('Invalid column definition.');
        }

        return $tokens;
    }

    private static function defaultValue(string $token): string
    {
        if (strcasecmp($token, 'NULL') === 0 || strcasecmp($token, 'CURRENT_TIMESTAMP') === 0) {
            return strtoupper($token);
        }
        if (preg_match('/^-?\d+(?:\.\d+)?$/', $token) === 1 || preg_match("/^'(?:''|[^'])*'$/", $token) === 1) {
            return $token;
        }

        throw new RuntimeException('Invalid column definition.');
    }

    private static function quote(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new RuntimeException('Invalid table or column name.');
        }

        return '`' . $name . '`';
    }

    /** @param list<mixed> $bindings */
    private static function run(string $sql, array $bindings): PDOStatement
    {
        try {
            $statement = Database::connection()->prepare($sql);
            $statement->execute($bindings);

            return $statement;
        } catch (PDOException $exception) {
            self::fail($exception);
        }
    }

    private static function execute(string $sql): void
    {
        try {
            Database::connection()->exec($sql);
        } catch (PDOException $exception) {
            self::fail($exception);
        }
    }

    private static function fail(PDOException $exception): never
    {
        ErrorHandler::report($exception);
        throw new RuntimeException(
            app_debug() ? $exception->getMessage() : 'Database schema change failed.',
            (int) $exception->getCode(),
            $exception
        );
    }
}
