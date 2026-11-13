<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Command;
use App\Console\Input;
use RuntimeException;

final class ServeCommand extends Command
{
    public function handle(Input $input): int
    {
        $host = $input->option('host', '127.0.0.1') ?? '127.0.0.1';
        $port = $input->option('port', '8000') ?? '8000';

        if (!preg_match('/^[A-Za-z0-9.-]+$/', $host)) {
            throw new RuntimeException('Invalid host: ' . $host);
        }

        if (!preg_match('/^\d+$/', $port) || (int) $port < 1 || (int) $port > 65535) {
            throw new RuntimeException('Invalid port: ' . $port);
        }

        $public = BASE_PATH . DIRECTORY_SEPARATOR . 'public';
        $router = $public . DIRECTORY_SEPARATOR . 'index.php';
        if (!is_file($router)) {
            throw new RuntimeException('Router not found: public/index.php');
        }

        $command = escapeshellarg(PHP_BINARY)
            . ' -S ' . escapeshellarg($host . ':' . $port)
            . ' -t ' . escapeshellarg($public)
            . ' ' . escapeshellarg($router);

        $this->line('Server running at http://' . $host . ':' . $port);
        $this->line('Press Ctrl+C to stop.');

        passthru($command, $exitCode);

        return $exitCode === 0 ? 0 : 1;
    }
}
