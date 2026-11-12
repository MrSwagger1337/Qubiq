<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ValidationException;

final class Color
{
    public static function normalizeHex(string $input, string $fieldName = 'color'): string
    {
        $trimmed = trim($input);
        if (preg_match('/^#?([0-9a-fA-F]{3})$/', $trimmed, $m) === 1) {
            $c = strtolower($m[1]);

            return '#' . $c[0] . $c[0] . $c[1] . $c[1] . $c[2] . $c[2];
        }

        if (preg_match('/^#?([0-9a-fA-F]{6})$/', $trimmed, $m) === 1) {
            return '#' . strtolower($m[1]);
        }

        throw new ValidationException('Invalid hex color value.', [
            $fieldName => 'Color must be a valid 3-digit or 6-digit hex code (e.g. #0f172a).',
        ]);
    }

    /**
     * @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}
     */
    public static function hexToRgb(string $hex): array
    {
        $normalized = ltrim(self::normalizeHex($hex), '#');

        return [
            max(0, min(255, (int) hexdec(substr($normalized, 0, 2)))),
            max(0, min(255, (int) hexdec(substr($normalized, 2, 2)))),
            max(0, min(255, (int) hexdec(substr($normalized, 4, 2)))),
        ];
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    public static function rgbToHex(array $rgb): string
    {
        return sprintf(
            '#%02x%02x%02x',
            max(0, min(255, $rgb[0])),
            max(0, min(255, $rgb[1])),
            max(0, min(255, $rgb[2]))
        );
    }

    /**
     * Interpolates between two RGB colors at normalized coordinate ($nx, $ny) in [0..1].
     *
     * @param array{0: int, 1: int, 2: int} $startRgb
     * @param array{0: int, 1: int, 2: int} $endRgb
     * @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}
     */
    public static function interpolateGradient(
        array $startRgb,
        array $endRgb,
        string $gradientType,
        float $angleDegrees,
        float $nx,
        float $ny
    ): array {
        $nx = max(0.0, min(1.0, $nx));
        $ny = max(0.0, min(1.0, $ny));

        if ($gradientType === 'radial') {
            $dx = $nx - 0.5;
            $dy = $ny - 0.5;
            $t = sqrt(($dx * $dx) + ($dy * $dy)) / 0.70710678;
        } else {
            $rad = deg2rad($angleDegrees);
            $cos = cos($rad);
            $sin = sin($rad);
            $maxProj = (abs($cos) + abs($sin)) * 0.5;
            $proj = (($nx - 0.5) * $cos) + (($ny - 0.5) * $sin);
            $t = $maxProj > 0.0001 ? (($proj / (2.0 * $maxProj)) + 0.5) : 0.5;
        }

        $t = max(0.0, min(1.0, $t));

        return [
            max(0, min(255, (int) round($startRgb[0] + (($endRgb[0] - $startRgb[0]) * $t)))),
            max(0, min(255, (int) round($startRgb[1] + (($endRgb[1] - $startRgb[1]) * $t)))),
            max(0, min(255, (int) round($startRgb[2] + (($endRgb[2] - $startRgb[2]) * $t)))),
        ];
    }

    public static function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        $channels = [$r / 255.0, $g / 255.0, $b / 255.0];

        foreach ($channels as $idx => $c) {
            $channels[$idx] = $c <= 0.03928
                ? ($c / 12.92)
                : pow(($c + 0.055) / 1.055, 2.4);
        }

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }

    public static function contrastRatio(string $hexA, string $hexB): float
    {
        $lumA = self::relativeLuminance($hexA);
        $lumB = self::relativeLuminance($hexB);
        $lighter = max($lumA, $lumB);
        $darker = min($lumA, $lumB);

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}
