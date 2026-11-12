<?php

declare(strict_types=1);

namespace App\Services\QR;

use App\Exceptions\ValidationException;
use App\Support\Color;
use App\Support\Config;

final readonly class QrDesignOptions
{
    public const ECC_LEVELS = ['L', 'M', 'Q', 'H'];

    public const MODULE_SHAPES = ['square', 'rounded', 'circle', 'dots', 'diamond', 'soft'];

    public const FINDER_OUTER_SHAPES = ['square', 'rounded', 'circle', 'cushion'];

    public const FINDER_INNER_SHAPES = ['square', 'rounded', 'circle'];

    public const FOREGROUND_MODES = ['solid', 'linear', 'radial'];

    public const BACKGROUND_MODES = ['solid', 'transparent', 'linear', 'radial'];

    public const LOGO_BG_SHAPES = ['rounded', 'square', 'circle'];

    public function __construct(
        public string $eccLevel,
        public int $version,
        public int $maskPattern,
        public int $quietZone,
        public int $size,
        public string $foregroundMode,
        public string $foregroundColor,
        public string $foregroundGradientEnd,
        public float $gradientAngle,
        public ?string $finderOuterColor,
        public ?string $finderInnerColor,
        public string $backgroundMode,
        public string $backgroundColor,
        public string $backgroundGradientEnd,
        public float $backgroundGradientAngle,
        public string $moduleShape,
        public string $finderOuterShape,
        public string $finderInnerShape,
        public float $logoScale,
        public int $logoPadding,
        public bool $logoBackgroundEnabled,
        public string $logoBackgroundShape,
        public string $logoBackgroundColor
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input, Config $config): self
    {
        $defaultEcc = $config->string('qr.default_ecc', 'M');
        $eccRaw = isset($input['ecc']) && is_scalar($input['ecc'])
            ? strtoupper(trim((string) $input['ecc']))
            : $defaultEcc;
        if (!in_array($eccRaw, self::ECC_LEVELS, true)) {
            throw new ValidationException('Invalid error correction level.', [
                'ecc' => 'Error correction level must be L, M, Q, or H.',
            ]);
        }

        $versionRaw = $input['version'] ?? 'auto';
        $version = 0; // 0 = AUTO
        if ($versionRaw !== 'auto' && $versionRaw !== '' && $versionRaw !== 0 && $versionRaw !== '0') {
            if (!is_numeric($versionRaw) || (int) $versionRaw < 1 || (int) $versionRaw > 40) {
                throw new ValidationException('Invalid QR version.', [
                    'version' => 'QR version must be "auto" or an integer from 1 to 40.',
                ]);
            }
            $version = (int) $versionRaw;
        }

        $maskRaw = $input['mask_pattern'] ?? 'auto';
        $maskPattern = -1; // -1 = AUTO
        if ($maskRaw !== 'auto' && $maskRaw !== '' && $maskRaw !== -1 && $maskRaw !== '-1') {
            if (!is_numeric($maskRaw) || (int) $maskRaw < 0 || (int) $maskRaw > 7) {
                throw new ValidationException('Invalid mask pattern.', [
                    'mask_pattern' => 'Mask pattern must be "auto" or an integer from 0 to 7.',
                ]);
            }
            $maskPattern = (int) $maskRaw;
        }

        $defaultQz = $config->int('qr.default_quiet_zone', 4);
        $qzRaw = $input['quiet_zone'] ?? $defaultQz;
        if (!is_numeric($qzRaw) || (int) $qzRaw < 0 || (int) $qzRaw > 12) {
            throw new ValidationException('Invalid quiet zone size.', [
                'quiet_zone' => 'Quiet zone must be between 0 and 12 modules.',
            ]);
        }
        $quietZone = (int) $qzRaw;

        $minSize = $config->int('qr.min_size', 128);
        $maxSize = $config->int('qr.max_size', 2048);
        $defaultSize = $config->int('qr.default_size', 1000);
        $sizeRaw = $input['size'] ?? $defaultSize;
        if (!is_numeric($sizeRaw) || (int) $sizeRaw < $minSize || (int) $sizeRaw > $maxSize) {
            throw new ValidationException(
                sprintf('Output dimensions must be between %d and %d pixels.', $minSize, $maxSize),
                ['size' => sprintf('Select a dimension between %d and %d px.', $minSize, $maxSize)]
            );
        }
        $size = (int) $sizeRaw;

        $fgMode = isset($input['foreground_mode']) && is_scalar($input['foreground_mode'])
            ? strtolower(trim((string) $input['foreground_mode']))
            : 'solid';
        if (!in_array($fgMode, self::FOREGROUND_MODES, true)) {
            throw new ValidationException('Invalid foreground color mode.', [
                'foreground_mode' => 'Foreground mode must be solid, linear, or radial.',
            ]);
        }

        $fgColor = Color::normalizeHex(
            isset($input['foreground_color']) && is_scalar($input['foreground_color'])
                ? (string) $input['foreground_color']
                : '#0f172a',
            'foreground_color'
        );
        $fgEnd = Color::normalizeHex(
            isset($input['foreground_gradient_end']) && is_scalar($input['foreground_gradient_end'])
                ? (string) $input['foreground_gradient_end']
                : '#059669',
            'foreground_gradient_end'
        );

        $angleRaw = $input['gradient_angle'] ?? 135;
        $gradientAngle = is_numeric($angleRaw) ? max(0.0, min(360.0, (float) $angleRaw)) : 135.0;

        $finderOuterColor = null;
        if (isset($input['finder_outer_color']) && is_scalar($input['finder_outer_color']) && trim((string) $input['finder_outer_color']) !== '') {
            $finderOuterColor = Color::normalizeHex((string) $input['finder_outer_color'], 'finder_outer_color');
        }

        $finderInnerColor = null;
        if (isset($input['finder_inner_color']) && is_scalar($input['finder_inner_color']) && trim((string) $input['finder_inner_color']) !== '') {
            $finderInnerColor = Color::normalizeHex((string) $input['finder_inner_color'], 'finder_inner_color');
        }

        $bgMode = isset($input['background_mode']) && is_scalar($input['background_mode'])
            ? strtolower(trim((string) $input['background_mode']))
            : 'solid';
        if (filter_var($input['transparent_background'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $bgMode = 'transparent';
        }
        if (!in_array($bgMode, self::BACKGROUND_MODES, true)) {
            throw new ValidationException('Invalid background mode.', [
                'background_mode' => 'Background mode must be solid, transparent, linear, or radial.',
            ]);
        }

        $bgColor = Color::normalizeHex(
            isset($input['background_color']) && is_scalar($input['background_color'])
                ? (string) $input['background_color']
                : '#ffffff',
            'background_color'
        );
        $bgEnd = Color::normalizeHex(
            isset($input['background_gradient_end']) && is_scalar($input['background_gradient_end'])
                ? (string) $input['background_gradient_end']
                : '#f1f5f9',
            'background_gradient_end'
        );
        $bgAngleRaw = $input['background_gradient_angle'] ?? 180;
        $bgGradientAngle = is_numeric($bgAngleRaw) ? max(0.0, min(360.0, (float) $bgAngleRaw)) : 180.0;

        $moduleShape = isset($input['module_shape']) && is_scalar($input['module_shape'])
            ? strtolower(trim((string) $input['module_shape']))
            : 'square';
        if (!in_array($moduleShape, self::MODULE_SHAPES, true)) {
            throw new ValidationException('Unsupported QR module shape.', [
                'module_shape' => 'Choose square, rounded, circle, dots, diamond, or soft.',
            ]);
        }

        $finderOuterShape = isset($input['finder_outer_shape']) && is_scalar($input['finder_outer_shape'])
            ? strtolower(trim((string) $input['finder_outer_shape']))
            : 'square';
        if (!in_array($finderOuterShape, self::FINDER_OUTER_SHAPES, true)) {
            throw new ValidationException('Unsupported finder outer shape.', [
                'finder_outer_shape' => 'Choose square, rounded, circle, or cushion.',
            ]);
        }

        $finderInnerShape = isset($input['finder_inner_shape']) && is_scalar($input['finder_inner_shape'])
            ? strtolower(trim((string) $input['finder_inner_shape']))
            : 'square';
        if (!in_array($finderInnerShape, self::FINDER_INNER_SHAPES, true)) {
            throw new ValidationException('Unsupported finder inner shape.', [
                'finder_inner_shape' => 'Choose square, rounded, or circle.',
            ]);
        }

        $maxLogoCoverage = $config->float('logo.max_coverage_ratio', 0.28);
        $logoScaleRaw = $input['logo_scale'] ?? 0.20;
        if (!is_numeric($logoScaleRaw) || (float) $logoScaleRaw < 0.08 || (float) $logoScaleRaw > $maxLogoCoverage) {
            throw new ValidationException(
                sprintf('Logo size ratio must be between 0.08 and %.2f.', $maxLogoCoverage),
                ['logo_scale' => sprintf('Logo scale must be between 8%% and %d%%.', (int) round($maxLogoCoverage * 100))]
            );
        }
        $logoScale = (float) $logoScaleRaw;

        $logoPaddingRaw = $input['logo_padding'] ?? 8;
        $logoPadding = is_numeric($logoPaddingRaw) ? max(0, min(32, (int) $logoPaddingRaw)) : 8;

        $logoBgEnabled = !isset($input['logo_background_enabled'])
            || filter_var($input['logo_background_enabled'], FILTER_VALIDATE_BOOLEAN);

        $logoBgShape = isset($input['logo_background_shape']) && is_scalar($input['logo_background_shape'])
            ? strtolower(trim((string) $input['logo_background_shape']))
            : 'rounded';
        if (!in_array($logoBgShape, self::LOGO_BG_SHAPES, true)) {
            throw new ValidationException('Invalid logo background shape.', [
                'logo_background_shape' => 'Choose rounded, square, or circle.',
            ]);
        }

        $logoBgColor = Color::normalizeHex(
            isset($input['logo_background_color']) && is_scalar($input['logo_background_color'])
                ? (string) $input['logo_background_color']
                : '#ffffff',
            'logo_background_color'
        );

        return new self(
            eccLevel: $eccRaw,
            version: $version,
            maskPattern: $maskPattern,
            quietZone: $quietZone,
            size: $size,
            foregroundMode: $fgMode,
            foregroundColor: $fgColor,
            foregroundGradientEnd: $fgEnd,
            gradientAngle: $gradientAngle,
            finderOuterColor: $finderOuterColor,
            finderInnerColor: $finderInnerColor,
            backgroundMode: $bgMode,
            backgroundColor: $bgColor,
            backgroundGradientEnd: $bgEnd,
            backgroundGradientAngle: $bgGradientAngle,
            moduleShape: $moduleShape,
            finderOuterShape: $finderOuterShape,
            finderInnerShape: $finderInnerShape,
            logoScale: $logoScale,
            logoPadding: $logoPadding,
            logoBackgroundEnabled: $logoBgEnabled,
            logoBackgroundShape: $logoBgShape,
            logoBackgroundColor: $logoBgColor
        );
    }

    /**
     * @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}
     */
    public function foregroundRgbAt(float $nx, float $ny): array
    {
        $start = Color::hexToRgb($this->foregroundColor);
        if ($this->foregroundMode === 'solid') {
            return $start;
        }

        $end = Color::hexToRgb($this->foregroundGradientEnd);

        return Color::interpolateGradient($start, $end, $this->foregroundMode, $this->gradientAngle, $nx, $ny);
    }

    /**
     * @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}
     */
    public function backgroundRgbAt(float $nx, float $ny): array
    {
        $start = Color::hexToRgb($this->backgroundColor);
        if ($this->backgroundMode === 'solid' || $this->backgroundMode === 'transparent') {
            return $start;
        }

        $end = Color::hexToRgb($this->backgroundGradientEnd);

        return Color::interpolateGradient($start, $end, $this->backgroundMode, $this->backgroundGradientAngle, $nx, $ny);
    }

    public function isTransparent(): bool
    {
        return $this->backgroundMode === 'transparent';
    }
}
