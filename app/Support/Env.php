<?php

declare(strict_types=1);

namespace App\Support;

final class Env
{
    public static function string(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false) {
            return $default;
        }

        return trim((string) $value);
    }

    public static function int(string $key, int $default = 0): int
    {
        $raw = self::string($key, '');
        if ($raw === '' || !preg_match('/^-?\d+$/', $raw)) {
            return $default;
        }

        return (int) $raw;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $raw = self::string($key, '');
        if ($raw === '' || !is_numeric($raw)) {
            return $default;
        }

        return (float) $raw;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $raw = strtolower(self::string($key, ''));
        if ($raw === '') {
            return $default;
        }

        return match ($raw) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }

    /**
     * @return list<string>
     */
    public static function csv(string $key): array
    {
        $raw = self::string($key, '');
        if ($raw === '') {
            return [];
        }

        $parts = array_map(static fn (string $item): string => trim($item), explode(',', $raw));

        return array_values(array_filter($parts, static fn (string $item): bool => $item !== ''));
    }
}
