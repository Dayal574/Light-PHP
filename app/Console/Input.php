<?php

declare(strict_types=1);

namespace App\Console;

final class Input
{
    /**
     * @param list<string> $arguments
     * @param array<string, string> $options
     */
    public function __construct(
        private array $arguments,
        private array $options
    ) {}

    /** @param list<string> $argv */
    public static function fromArgv(array $argv): self
    {
        $arguments = [];
        $options = [];
        $items = array_slice($argv, 2);

        for ($index = 0, $count = count($items); $index < $count; $index++) {
            $item = $items[$index];
            if (!str_starts_with($item, '--')) {
                $arguments[] = $item;
                continue;
            }

            $option = substr($item, 2);
            if ($option === '') {
                continue;
            }

            if (str_contains($option, '=')) {
                [$key, $value] = explode('=', $option, 2);
                $options[$key] = $value;
                continue;
            }

            $next = $items[$index + 1] ?? null;
            if (is_string($next) && !str_starts_with($next, '--')) {
                $options[$option] = $next;
                $index++;
                continue;
            }

            $options[$option] = 'true';
        }

        return new self($arguments, $options);
    }

    public function argument(int $index, ?string $default = null): ?string
    {
        return $this->arguments[$index] ?? $default;
    }

    public function option(string $name, ?string $default = null): ?string
    {
        return $this->options[$name] ?? $default;
    }

    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }
}
