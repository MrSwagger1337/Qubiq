<?php

declare(strict_types=1);

namespace App\Services\Rendering;

use App\Exceptions\UnsupportedCapabilityException;
use App\Services\QR\ProcessedLogo;
use App\Services\QR\QrDesignOptions;
use App\Services\QR\QrGenerationResult;
use App\Support\Color;
use chillerlan\QRCode\Data\QRMatrix;
use GdImage;

final class GdRasterRenderer
{
    public function render(QrGenerationResult $result, string $format): string
    {
        $format = strtolower($format);
        if ($format === 'webp' && !function_exists('imagewebp')) {
            throw new UnsupportedCapabilityException('WebP export is not supported by this server\'s GD build.');
        }

        $matrix = $result->matrix;
        $design = $result->design;
        $logo = $result->logo;

        $targetSize = $design->size;
        $totalModules = $matrix->moduleCount;

        // 2x supersampling up to 2400px canvas for crisp anti-aliased circles, diamonds, and rounded corners
        $workSize = $targetSize <= 1200 ? ($targetSize * 2) : $targetSize;
        $workSize = max(1, $workSize, $totalModules * 8);
        $workSize = max(1, min($workSize, 2600));

        $canvas = imagecreatetruecolor($workSize, $workSize);
        if (!$canvas instanceof GdImage) {
            throw new UnsupportedCapabilityException('Unable to allocate raster image buffer.');
        }

        $supportsAlpha = in_array($format, ['png', 'webp'], true);
        $isTransparent = $design->isTransparent() && $supportsAlpha;

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $this->fillBackground($canvas, $workSize, $design, $isTransparent);
        imagealphablending($canvas, true);

        $cell = $workSize / $totalModules;
        $grid = $matrix->matrix;
        $colorCache = [];

        for ($y = 0; $y < $totalModules; $y++) {
            $row = $grid[$y];
            $ny = ($y + 0.5) / $totalModules;
            for ($x = 0; $x < $totalModules; $x++) {
                $val = $row[$x];
                if (($val & QRMatrix::IS_DARK) !== QRMatrix::IS_DARK) {
                    continue;
                }
                if ($val === QRMatrix::M_FINDER_DARK || $val === QRMatrix::M_FINDER_DOT) {
                    continue;
                }

                $nx = ($x + 0.5) / $totalModules;
                $rgb = $design->foregroundRgbAt($nx, $ny);
                $col = $this->allocColor($canvas, $rgb, $colorCache);

                $this->drawModule($canvas, $x * $cell, $y * $cell, $cell, $design->moduleShape, $col);
            }
        }

        $qz = $design->quietZone;
        $finderOrigins = [
            [$qz, $qz],
            [$totalModules - $qz - 7, $qz],
            [$qz, $totalModules - $qz - 7],
        ];

        foreach ($finderOrigins as [$fx, $fy]) {
            $nx = ($fx + 3.5) / $totalModules;
            $ny = ($fy + 3.5) / $totalModules;

            $outerRgb = $design->finderOuterColor !== null
                ? Color::hexToRgb($design->finderOuterColor)
                : $design->foregroundRgbAt($nx, $ny);
            $innerRgb = $design->finderInnerColor !== null
                ? Color::hexToRgb($design->finderInnerColor)
                : $outerRgb;

            $outerCol = $this->allocColor($canvas, $outerRgb, $colorCache);
            $innerCol = $this->allocColor($canvas, $innerRgb, $colorCache);

            $this->drawFinderOuter($canvas, $fx * $cell, $fy * $cell, $cell, $design, $outerCol, $isTransparent, $nx, $ny, $colorCache);
            $this->drawFinderInner($canvas, ($fx + 2) * $cell, ($fy + 2) * $cell, $cell, $design->finderInnerShape, $innerCol);
        }

        if ($logo !== null) {
            $this->drawLogo($canvas, $workSize, $logo, $design, $colorCache);
        }

        $outputImage = $canvas;
        if ($workSize !== $targetSize) {
            $safeTarget = max(1, $targetSize);
            $resized = imagecreatetruecolor($safeTarget, $safeTarget);
            if ($resized instanceof GdImage) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                if ($isTransparent) {
                    $clear = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                    if ($clear !== false) {
                        imagefilledrectangle($resized, 0, 0, $targetSize, $targetSize, $clear);
                    }
                }
                imagecopyresampled($resized, $canvas, 0, 0, 0, 0, $targetSize, $targetSize, $workSize, $workSize);
                imagedestroy($canvas);
                $outputImage = $resized;
            }
        }

        ob_start();
        if ($format === 'png') {
            imagepng($outputImage, null, 6);
        } elseif ($format === 'webp') {
            imagewebp($outputImage, null, 92);
        } else {
            imagejpeg($outputImage, null, 94);
        }
        $binary = (string) ob_get_clean();
        imagedestroy($outputImage);

        return $binary;
    }

    private function fillBackground(GdImage $canvas, int $size, QrDesignOptions $design, bool $isTransparent): void
    {
        if ($isTransparent) {
            $clear = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
            if ($clear !== false) {
                imagefilledrectangle($canvas, 0, 0, $size, $size, $clear);
            }

            return;
        }

        if ($design->backgroundMode === 'solid' || $design->backgroundMode === 'transparent') {
            $hex = $design->backgroundMode === 'transparent' ? '#ffffff' : $design->backgroundColor;
            [$r, $g, $b] = Color::hexToRgb($hex);
            $bg = imagecolorallocate($canvas, $r, $g, $b);
            if ($bg !== false) {
                imagefilledrectangle($canvas, 0, 0, $size, $size, $bg);
            }

            return;
        }

        // Banded high-speed gradient fill for linear/radial backgrounds
        $bands = 128;
        $step = $size / $bands;
        for ($i = 0; $i < $bands; $i++) {
            $t = ($i + 0.5) / $bands;
            [$r, $g, $b] = $design->backgroundMode === 'radial'
                ? $design->backgroundRgbAt(0.5 + ($t * 0.5), 0.5 + ($t * 0.5))
                : $design->backgroundRgbAt($t, $t);
            $col = imagecolorallocate($canvas, $r, $g, $b);
            if ($col === false) {
                continue;
            }
            if ($design->backgroundMode === 'radial') {
                $inv = 1.0 - ($i / $bands);
                $diam = (int) round($size * 1.42 * $inv);
                imagefilledellipse($canvas, (int) ($size / 2), (int) ($size / 2), max(1, $diam), max(1, $diam), $col);
            } else {
                $y1 = (int) floor($i * $step);
                $y2 = (int) ceil(($i + 1) * $step);
                imagefilledrectangle($canvas, 0, $y1, $size, $y2, $col);
            }
        }
    }

    private function drawModule(GdImage $canvas, float $px, float $py, float $cell, string $shape, int $color): void
    {
        if ($shape === 'circle' || $shape === 'dots') {
            $diam = (int) max(2.0, round($cell * ($shape === 'dots' ? 0.72 : 0.92)));
            imagefilledellipse($canvas, (int) round($px + ($cell / 2.0)), (int) round($py + ($cell / 2.0)), $diam, $diam, $color);

            return;
        }

        if ($shape === 'diamond') {
            $cx = (int) round($px + ($cell / 2.0));
            $cy = (int) round($py + ($cell / 2.0));
            $half = (int) max(1.0, round($cell * 0.48));
            imagefilledpolygon($canvas, [
                $cx, $cy - $half,
                $cx + $half, $cy,
                $cx, $cy + $half,
                $cx - $half, $cy,
            ], $color);

            return;
        }

        if ($shape === 'rounded' || $shape === 'soft') {
            $inset = $cell * ($shape === 'soft' ? 0.02 : 0.04);
            $w = $cell - (2.0 * $inset);
            $rad = $cell * ($shape === 'soft' ? 0.38 : 0.28);
            $this->drawFilledRoundedRect($canvas, $px + $inset, $py + $inset, $w, $w, $rad, $color);

            return;
        }

        imagefilledrectangle(
            $canvas,
            (int) floor($px),
            (int) floor($py),
            (int) ceil($px + $cell) - 1,
            (int) ceil($py + $cell) - 1,
            $color
        );
    }

    /**
     * @param array<string, int> $colorCache
     */
    private function drawFinderOuter(
        GdImage $canvas,
        float $fx,
        float $fy,
        float $cell,
        QrDesignOptions $design,
        int $outerColor,
        bool $isTransparent,
        float $nx,
        float $ny,
        array &$colorCache
    ): void {
        $outerSpan = 7.0 * $cell;
        $innerSpan = 5.0 * $cell;

        if ($isTransparent) {
            imagealphablending($canvas, false);
            $cutoutColor = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
            if ($cutoutColor === false) {
                $cutoutColor = 0;
            }
        } else {
            $bgRgb = $design->backgroundRgbAt($nx, $ny);
            $cutoutColor = $this->allocColor($canvas, $bgRgb, $colorCache);
        }

        if ($design->finderOuterShape === 'circle') {
            $cx = (int) round($fx + (3.5 * $cell));
            $cy = (int) round($fy + (3.5 * $cell));
            imagealphablending($canvas, true);
            imagefilledellipse($canvas, $cx, $cy, (int) round($outerSpan), (int) round($outerSpan), $outerColor);
            if ($isTransparent) {
                imagealphablending($canvas, false);
            }
            imagefilledellipse($canvas, $cx, $cy, (int) round($innerSpan), (int) round($innerSpan), $cutoutColor);
            imagealphablending($canvas, true);

            return;
        }

        $outerRad = match ($design->finderOuterShape) {
            'rounded' => 1.8 * $cell,
            'cushion' => 2.5 * $cell,
            default => 0.0,
        };
        $innerRad = match ($design->finderOuterShape) {
            'rounded' => 1.1 * $cell,
            'cushion' => 0.5 * $cell,
            default => 0.0,
        };

        imagealphablending($canvas, true);
        $this->drawFilledRoundedRect($canvas, $fx, $fy, $outerSpan, $outerSpan, $outerRad, $outerColor);
        if ($isTransparent) {
            imagealphablending($canvas, false);
        }
        $this->drawFilledRoundedRect($canvas, $fx + $cell, $fy + $cell, $innerSpan, $innerSpan, $innerRad, $cutoutColor);
        imagealphablending($canvas, true);
    }

    private function drawFinderInner(GdImage $canvas, float $ix, float $iy, float $cell, string $shape, int $color): void
    {
        $span = 3.0 * $cell;
        if ($shape === 'circle') {
            $cx = (int) round($ix + (1.5 * $cell));
            $cy = (int) round($iy + (1.5 * $cell));
            imagefilledellipse($canvas, $cx, $cy, (int) round($span), (int) round($span), $color);

            return;
        }

        $rad = $shape === 'rounded' ? (0.85 * $cell) : 0.0;
        $this->drawFilledRoundedRect($canvas, $ix, $iy, $span, $span, $rad, $color);
    }

    /**
     * @param array<string, int> $colorCache
     */
    private function drawLogo(
        GdImage $canvas,
        int $workSize,
        ProcessedLogo $logo,
        QrDesignOptions $design,
        array &$colorCache
    ): void {
        $logoImg = @imagecreatefromstring($logo->pngBinary);
        if (!$logoImg instanceof GdImage) {
            return;
        }

        $boxSize = $workSize * $design->logoScale;
        $aspect = $logo->width / max(1, $logo->height);
        $logoW = (int) max(1.0, round($aspect >= 1.0 ? $boxSize : ($boxSize * $aspect)));
        $logoH = (int) max(1.0, round($aspect >= 1.0 ? ($boxSize / $aspect) : $boxSize));

        $logoX = (int) round(($workSize - $logoW) / 2.0);
        $logoY = (int) round(($workSize - $logoH) / 2.0);

        if ($design->logoBackgroundEnabled) {
            $padPx = ($design->logoPadding / 1000.0) * $workSize;
            $padW = $logoW + (2.0 * $padPx);
            $padH = $logoH + (2.0 * $padPx);
            $padX = ($workSize - $padW) / 2.0;
            $padY = ($workSize - $padH) / 2.0;
            $padCol = $this->allocColor($canvas, Color::hexToRgb($design->logoBackgroundColor), $colorCache);

            if ($design->logoBackgroundShape === 'circle') {
                $diam = (int) round(max($padW, $padH));
                imagefilledellipse($canvas, (int) round($workSize / 2.0), (int) round($workSize / 2.0), $diam, $diam, $padCol);
            } elseif ($design->logoBackgroundShape === 'rounded') {
                $rad = min($padW, $padH) * 0.22;
                $this->drawFilledRoundedRect($canvas, $padX, $padY, $padW, $padH, $rad, $padCol);
            } else {
                $this->drawFilledRoundedRect($canvas, $padX, $padY, $padW, $padH, 0.0, $padCol);
            }
        }

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $logoImg, $logoX, $logoY, 0, 0, $logoW, $logoH, $logo->width, $logo->height);
        imagedestroy($logoImg);
    }

    private function drawFilledRoundedRect(
        GdImage $canvas,
        float $x,
        float $y,
        float $w,
        float $h,
        float $r,
        int $color
    ): void {
        $r = max(0.0, min($r, $w / 2.0, $h / 2.0));
        $x1 = (int) round($x);
        $y1 = (int) round($y);
        $x2 = (int) round($x + $w) - 1;
        $y2 = (int) round($y + $h) - 1;

        if ($r < 1.0) {
            imagefilledrectangle($canvas, $x1, $y1, max($x1, $x2), max($y1, $y2), $color);

            return;
        }

        $ri = (int) round($r);
        $diam = $ri * 2;

        imagefilledrectangle($canvas, $x1 + $ri, $y1, max($x1 + $ri, $x2 - $ri), $y2, $color);
        imagefilledrectangle($canvas, $x1, $y1 + $ri, $x2, max($y1 + $ri, $y2 - $ri), $color);

        imagefilledellipse($canvas, $x1 + $ri, $y1 + $ri, $diam, $diam, $color);
        imagefilledellipse($canvas, $x2 - $ri, $y1 + $ri, $diam, $diam, $color);
        imagefilledellipse($canvas, $x1 + $ri, $y2 - $ri, $diam, $diam, $color);
        imagefilledellipse($canvas, $x2 - $ri, $y2 - $ri, $diam, $diam, $color);
    }

    /**
     * @param array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>} $rgb
     * @param array<string, int> $cache
     */
    private function allocColor(GdImage $canvas, array $rgb, array &$cache): int
    {
        $key = $rgb[0] . ':' . $rgb[1] . ':' . $rgb[2];
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $allocated = imagecolorallocate($canvas, $rgb[0], $rgb[1], $rgb[2]);
        $col = $allocated !== false ? $allocated : 0;
        $cache[$key] = $col;

        return $col;
    }
}
