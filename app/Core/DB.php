<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ErrorHandler;

/**
 * Global query API. Loaded by bootstrap, so views and route files can call DB::
 * without an import. Namespaced files call \DB::.
 */
final class DB
{
    public static function insert(string $table, array $data): int
    {
        if ($data === []) {
            throw new RuntimeException('Insert data is required.');
        }

        $columns = [];
        $placeholders = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            if (!is_string($column)) {
                throw new RuntimeException('Invalid column name.');
            }
            $columns[] = self::quote($column);
            $placeholders[] = '?';
            $bindings[] = self::value($value);
        }

        self::run(
            'INSERT INTO ' . self::quote($table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
            $bindings
        );

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(string $table, array $data, array $where): int
    {
        if ($data === []) {
            throw new RuntimeException('Update data is required.');
        }

        $assignments = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            if (!is_string($column)) {
                throw new RuntimeException('Invalid column name.');
            }
            $assignments[] = self::quote($column) . ' = ?';
            $bindings[] = self::value($value);
        }

        [$clause, $whereBindings] = self::where($where);
        $statement = self::run(
            'UPDATE ' . self::quote($table) . ' SET ' . implode(', ', $assignments) . ' WHERE ' . $clause,
            array_merge($bindings, $whereBindings)
        );

        return $statement->rowCount();
    }

    public static function delete(string $table, array $where): int
    {
        [$clause, $bindings] = self::where($where);
        $statement = self::run(
            'DELETE FROM ' . self::quote($table) . ' WHERE ' . $clause,
            $bindings
        );

        return $statement->rowCount();
    }

    /** @return list<DBRow> */
    public static function getall(string $table, array $where = []): array
    {
        $sql = 'SELECT * FROM ' . self::quote($table);
        $bindings = [];
        if ($where !== []) {
            [$clause, $bindings] = self::where($where);
            $sql .= ' WHERE ' . $clause;
        }

        return self::rows(self::run($sql, $bindings));
    }

    public static function get(string $table, int|string|array $idOrWhere): ?DBRow
    {
        $where = is_array($idOrWhere) ? $idOrWhere : ['id' => $idOrWhere];
        [$clause, $bindings] = self::where($where);
        $rows = self::rows(self::run(
            'SELECT * FROM ' . self::quote($table) . ' WHERE ' . $clause . ' LIMIT 1',
            $bindings
        ));

        return $rows[0] ?? null;
    }

    public static function count(string $table, array $where = []): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . self::quote($table);
        $bindings = [];
        if ($where !== []) {
            [$clause, $bindings] = self::where($where);
            $sql .= ' WHERE ' . $clause;
        }

        return (int) self::run($sql, $bindings)->fetchColumn();
    }

    public static function exists(string $table, array $where = []): bool
    {
        return self::count($table, $where) > 0;
    }

    /** @param list<mixed>|array<string, mixed> $bindings */
    public static function query(string $sql, array $bindings = []): array|int
    {
        $sql = trim($sql);
        $sql = rtrim($sql, " \t\n\r\0\x0B;");
        if ($sql === '' || str_contains($sql, ';')) {
            throw new RuntimeException('Only one SQL statement is allowed.');
        }

        $statement = self::run($sql, array_map(static fn (mixed $value): mixed => self::value($value), array_values($bindings)));
        $keyword = strtoupper((string) strtok(ltrim($sql), " \t\n\r("));

        if (in_array($keyword, ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN', 'WITH'], true)) {
            return self::rows($statement);
        }

        if ($keyword === 'INSERT') {
            return (int) Database::connection()->lastInsertId();
        }

        return $statement->rowCount();
    }

    public static function begin_transaction(): void
    {
        $connection = Database::connection();
        if ($connection->inTransaction()) {
            throw new RuntimeException('A transaction is already active.');
        }

        try {
            $connection->beginTransaction();
        } catch (PDOException $exception) {
            self::fail($exception, 'Unable to start a transaction.');
        }
    }

    public static function commit(): void
    {
        $connection = Database::connection();
        if (!$connection->inTransaction()) {
            throw new RuntimeException('No active transaction.');
        }

        try {
            $connection->commit();
        } catch (PDOException $exception) {
            self::fail($exception, 'Unable to commit the transaction.');
        }
    }

    public static function rollback(): void
    {
        $connection = Database::connection();
        if (!$connection->inTransaction()) {
            throw new RuntimeException('No active transaction.');
        }

        try {
            $connection->rollBack();
        } catch (PDOException $exception) {
            self::fail($exception, 'Unable to roll back the transaction.');
        }
    }

    /** @param list<mixed> $bindings */
    private static function run(string $sql, array $bindings): PDOStatement
    {
        try {
            $statement = Database::connection()->prepare($sql);
            $statement->execute($bindings);

            return $statement;
        } catch (PDOException $exception) {
            self::fail($exception, 'Database query failed.');
        }
    }

    /** @return list<DBRow> */
    private static function rows(PDOStatement $statement): array
    {
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn (array $row): DBRow => new DBRow($row), $rows);
    }

    /**
     * @param array<mixed> $where
     * @return array{0: string, 1: list<mixed>}
     */
    private static function where(array $where): array
    {
        if ($where === []) {
            throw new RuntimeException('A where condition is required.');
        }

        $clauses = [];
        $bindings = [];
        foreach ($where as $key => $value) {
            if (!is_string($key) || preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(?:\s+(>=|<=|!=|<>|>|<|=|like|in))?$/i', trim($key), $match) !== 1) {
                throw new RuntimeException('Invalid where column.');
            }

            $column = self::quote($match[1]);
            $operator = strtoupper($match[2] ?? '=');
            if ($operator === '') {
                $operator = '=';
            }

            if ($operator === 'IN') {
                if (!is_array($value) || $value === [] || array_is_list($value) === false) {
                    throw new RuntimeException('IN requires a non-empty list.');
                }
                $placeholders = implode(', ', array_fill(0, count($value), '?'));
                $clauses[] = $column . ' IN (' . $placeholders . ')';
                foreach ($value as $item) {
                    $bindings[] = self::value($item);
                }
                continue;
            }

            if (is_array($value) || is_object($value)) {
                throw new RuntimeException('Invalid where value.');
            }

            if ($value === null) {
                if ($operator === '=') {
                    $clauses[] = $column . ' IS NULL';
                    continue;
                }
                if ($operator === '!=' || $operator === '<>') {
                    $clauses[] = $column . ' IS NOT NULL';
                    continue;
                }
                throw new RuntimeException('Invalid where value.');
            }

            $clauses[] = $column . ' ' . $operator . ' ?';
            $bindings[] = self::value($value);
        }

        return [implode(' AND ', $clauses), $bindings];
    }

    private static function quote(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new RuntimeException('Invalid table or column name.');
        }

        return '`' . $name . '`';
    }

    private static function value(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            throw new RuntimeException('Invalid query value.');
        }

        return is_bool($value) ? (int) $value : $value;
    }

    private static function fail(PDOException $exception, string $message): never
    {
        ErrorHandler::report($exception);
        throw new RuntimeException(
            app_debug() ? $exception->getMessage() : $message,
            (int) $exception->getCode(),
            $exception
        );
    }
}

/** A database row that can be read as an object or an array. */
final class DBRow implements ArrayAccess
{
    /** @param array<string, mixed> $attributes */
    public function __construct(private array $attributes) {}

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && array_key_exists($offset, $this->attributes);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return is_string($offset) ? ($this->attributes[$offset] ?? null) : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_string($offset)) {
            $this->attributes[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        if (is_string($offset)) {
            unset($this->attributes[$offset]);
        }
    }
}
