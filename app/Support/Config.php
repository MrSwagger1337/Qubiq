<?php

declare(strict_types=1);

namespace App\Support;

final class Config
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(
        private readonly array $items
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    public function string(string $key, string $default = ''): string
    {
        $val = $this->get($key, $default);

        return is_scalar($val) ? (string) $val : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $val = $this->get($key, $default);

        return is_numeric($val) ? (int) $val : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $val = $this->get($key, $default);

        return is_numeric($val) ? (float) $val : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $val = $this->get($key, $default);

        return is_bool($val) ? $val : $default;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function array(string $key): array
    {
        $val = $this->get($key, []);

        return is_array($val) ? $val : [];
    }
}
