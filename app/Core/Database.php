<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        self::$connection = self::connect(self::settings(), true);

        return self::$connection;
    }

    public static function createDatabase(): void
    {
        $settings = self::settings();
        if (preg_match('/^[A-Za-z0-9_]+$/', $settings['database']) !== 1) {
            throw new RuntimeException('DB_DATABASE may only contain letters, numbers, and underscores.');
        }

        $server = self::connect($settings, false);

        try {
            $server->exec(
                'CREATE DATABASE IF NOT EXISTS `' . $settings['database'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Unable to create the database.',
                (int) $exception->getCode(),
                $exception
            );
        }
    }

    /** @return array{host: string, port: string, database: string, username: string, password: string, charset: string, options: array<int|string, mixed>} */
    private static function settings(): array
    {
        $driver = (string) config('database.default', 'mysql');
        $settings = config('database.connections.' . $driver, []);

        if (!is_array($settings)) {
            throw new RuntimeException('Database connection settings are invalid.');
        }

        if ($driver !== 'mysql') {
            throw new RuntimeException('This starter Database class currently supports MySQL only.');
        }

        $database = trim((string) ($settings['database'] ?? ''));
        if ($database === '') {
            throw new RuntimeException('Database name is not configured. Set DB_DATABASE in your .env file.');
        }

        return [
            'host' => (string) ($settings['host'] ?? '127.0.0.1'),
            'port' => (string) ($settings['port'] ?? '3306'),
            'database' => $database,
            'username' => (string) ($settings['username'] ?? ''),
            'password' => (string) ($settings['password'] ?? ''),
            'charset' => (string) ($settings['charset'] ?? 'utf8mb4'),
            'options' => is_array($settings['options'] ?? null) ? $settings['options'] : [],
        ];
    }

    /** @param array{host: string, port: string, database: string, username: string, password: string, charset: string, options: array<int|string, mixed>} $settings */
    private static function connect(array $settings, bool $withDatabase): PDO
    {
        $dsn = $withDatabase
            ? sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $settings['host'],
                $settings['port'],
                $settings['database'],
                $settings['charset']
            )
            : sprintf(
                'mysql:host=%s;port=%s;charset=%s',
                $settings['host'],
                $settings['port'],
                $settings['charset']
            );

        try {
            return new PDO(
                $dsn,
                $settings['username'],
                $settings['password'],
                $settings['options']
            );
        } catch (PDOException $exception) {
            if ($withDatabase) {
                self::$connection = null;
            }

            throw new RuntimeException(
                $withDatabase
                    ? 'Unable to connect to the database. Check DB_* settings in .env.'
                    : 'Unable to connect to MySQL. Check DB_HOST, DB_PORT, DB_USERNAME and DB_PASSWORD in .env.',
                (int) $exception->getCode(),
                $exception
            );
        }
    }
}
