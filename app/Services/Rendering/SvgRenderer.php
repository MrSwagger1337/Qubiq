<?php

declare(strict_types=1);

namespace App\Services\Rendering;

use App\Services\QR\ProcessedLogo;
use App\Services\QR\QrDesignOptions;
use App\Services\QR\QrGenerationResult;
use chillerlan\QRCode\Data\QRMatrix;

final class SvgRenderer
{
    public function render(QrGenerationResult $result): string
    {
        $matrix = $result->matrix;
        $design = $result->design;
        $logo = $result->logo;
        $totalModules = $matrix->moduleCount;
        $size = $design->size;

        $defs = [];
        $fgFill = $design->foregroundColor;
        if ($design->foregroundMode === 'linear') {
            [$x1, $y1, $x2, $y2] = $this->linearGradientCoords($design->gradientAngle, (float) $totalModules);
            $defs[] = sprintf(
                '<linearGradient id="qubiq-fg" gradientUnits="userSpaceOnUse" x1="%.3F" y1="%.3F" x2="%.3F" y2="%.3F">'
                . '<stop offset="0%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/></linearGradient>',
                $x1,
                $y1,
                $x2,
                $y2,
                $design->foregroundColor,
                $design->foregroundGradientEnd
            );
            $fgFill = 'url(#qubiq-fg)';
        } elseif ($design->foregroundMode === 'radial') {
            $half = $totalModules / 2.0;
            $radius = $totalModules * 0.72;
            $defs[] = sprintf(
                '<radialGradient id="qubiq-fg" gradientUnits="userSpaceOnUse" cx="%.3F" cy="%.3F" r="%.3F">'
                . '<stop offset="0%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/></radialGradient>',
                $half,
                $half,
                $radius,
                $design->foregroundColor,
                $design->foregroundGradientEnd
            );
            $fgFill = 'url(#qubiq-fg)';
        }

        $bgElement = '';
        if (!$design->isTransparent()) {
            $bgFill = $design->backgroundColor;
            if ($design->backgroundMode === 'linear') {
                [$bx1, $by1, $bx2, $by2] = $this->linearGradientCoords($design->backgroundGradientAngle, (float) $totalModules);
                $defs[] = sprintf(
                    '<linearGradient id="qubiq-bg" gradientUnits="userSpaceOnUse" x1="%.3F" y1="%.3F" x2="%.3F" y2="%.3F">'
                    . '<stop offset="0%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/></linearGradient>',
                    $bx1,
                    $by1,
                    $bx2,
                    $by2,
                    $design->backgroundColor,
                    $design->backgroundGradientEnd
                );
                $bgFill = 'url(#qubiq-bg)';
            } elseif ($design->backgroundMode === 'radial') {
                $half = $totalModules / 2.0;
                $defs[] = sprintf(
                    '<radialGradient id="qubiq-bg" gradientUnits="userSpaceOnUse" cx="%.3F" cy="%.3F" r="%.3F">'
                    . '<stop offset="0%%" stop-color="%s"/><stop offset="100%%" stop-color="%s"/></radialGradient>',
                    $half,
                    $half,
                    $totalModules * 0.72,
                    $design->backgroundColor,
                    $design->backgroundGradientEnd
                );
                $bgFill = 'url(#qubiq-bg)';
            }
            $bgElement = sprintf('<rect x="0" y="0" width="%d" height="%d" fill="%s"/>', $totalModules, $totalModules, $bgFill);
        }

        $dataPaths = [];
        $grid = $matrix->matrix;
        for ($y = 0; $y < $totalModules; $y++) {
            $row = $grid[$y];
            for ($x = 0; $x < $totalModules; $x++) {
                $val = $row[$x];
                if (($val & QRMatrix::IS_DARK) !== QRMatrix::IS_DARK) {
                    continue;
                }
                if ($val === QRMatrix::M_FINDER_DARK || $val === QRMatrix::M_FINDER_DOT) {
                    continue;
                }
                $dataPaths[] = $this->modulePath((float) $x, (float) $y, $design->moduleShape);
            }
        }

        $finderOuterFill = $design->finderOuterColor ?? $fgFill;
        $finderInnerFill = $design->finderInnerColor ?? $finderOuterFill;

        $qz = $design->quietZone;
        $finderOrigins = [
            [$qz, $qz],
            [$totalModules - $qz - 7, $qz],
            [$qz, $totalModules - $qz - 7],
        ];

        $finderOuterPaths = [];
        $finderInnerPaths = [];
        foreach ($finderOrigins as [$fx, $fy]) {
            $finderOuterPaths[] = $this->finderOuterPath((float) $fx, (float) $fy, $design->finderOuterShape);
            $finderInnerPaths[] = $this->finderInnerPath((float) ($fx + 2), (float) ($fy + 2), $design->finderInnerShape);
        }

        $logoMarkup = '';
        if ($logo !== null) {
            $logoMarkup = $this->renderLogoSvg($logo, $design, (float) $totalModules);
        }

        $defsMarkup = $defs !== [] ? '<defs>' . implode('', $defs) . '</defs>' : '';
        $modulesMarkup = $dataPaths !== []
            ? sprintf('<path fill="%s" d="%s"/>', $fgFill, implode('', $dataPaths))
            : '';
        $outerMarkup = sprintf(
            '<path fill="%s" fill-rule="evenodd" clip-rule="evenodd" d="%s"/>',
            $finderOuterFill,
            implode('', $finderOuterPaths)
        );
        $innerMarkup = sprintf(
            '<path fill="%s" d="%s"/>',
            $finderInnerFill,
            implode('', $finderInnerPaths)
        );

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" role="img" aria-label="QR Code" shape-rendering="geometricPrecision">',
                $size,
                $size,
                $totalModules,
                $totalModules
            )
            . $defsMarkup
            . $bgElement
            . $modulesMarkup
            . $outerMarkup
            . $innerMarkup
            . $logoMarkup
            . '</svg>';
    }

    private function modulePath(float $x, float $y, string $shape): string
    {
        return match ($shape) {
            'circle' => $this->circlePath($x + 0.5, $y + 0.5, 0.46),
            'dots' => $this->circlePath($x + 0.5, $y + 0.5, 0.36),
            'rounded' => $this->roundedRectPath($x + 0.04, $y + 0.04, 0.92, 0.92, 0.28),
            'soft' => $this->roundedRectPath($x + 0.02, $y + 0.02, 0.96, 0.96, 0.40),
            'diamond' => sprintf(
                'M%.3F %.3FL%.3F %.3FL%.3F %.3FL%.3F %.3FZ',
                $x + 0.5,
                $y + 0.03,
                $x + 0.97,
                $y + 0.5,
                $x + 0.5,
                $y + 0.97,
                $x + 0.03,
                $y + 0.5
            ),
            default => sprintf('M%.2F %.2Fh1v1h-1Z', $x, $y),
        };
    }

    private function finderOuterPath(float $fx, float $fy, string $shape): string
    {
        return match ($shape) {
            'circle' => $this->circlePath($fx + 3.5, $fy + 3.5, 3.5)
                . $this->circlePath($fx + 3.5, $fy + 3.5, 2.5),
            'rounded' => $this->roundedRectPath($fx, $fy, 7.0, 7.0, 1.8)
                . $this->roundedRectPath($fx + 1.0, $fy + 1.0, 5.0, 5.0, 1.1),
            'cushion' => $this->roundedRectPath($fx, $fy, 7.0, 7.0, 2.5)
                . $this->roundedRectPath($fx + 1.0, $fy + 1.0, 5.0, 5.0, 0.5),
            default => sprintf('M%.2F %.2Fh7v7h-7ZM%.2F %.2Fh5v5h-5Z', $fx, $fy, $fx + 1.0, $fy + 1.0),
        };
    }

    private function finderInnerPath(float $ix, float $iy, string $shape): string
    {
        return match ($shape) {
            'circle' => $this->circlePath($ix + 1.5, $iy + 1.5, 1.5),
            'rounded' => $this->roundedRectPath($ix, $iy, 3.0, 3.0, 0.85),
            default => sprintf('M%.2F %.2Fh3v3h-3Z', $ix, $iy),
        };
    }

    private function circlePath(float $cx, float $cy, float $r): string
    {
        return sprintf(
            'M%.3F %.3Fa%.3F %.3F 0 1 0 %.3F 0a%.3F %.3F 0 1 0 -%.3F 0Z',
            $cx - $r,
            $cy,
            $r,
            $r,
            $r * 2.0,
            $r,
            $r,
            $r * 2.0
        );
    }

    private function roundedRectPath(float $x, float $y, float $w, float $h, float $r): string
    {
        $r = max(0.0, min($r, $w / 2.0, $h / 2.0));
        if ($r <= 0.001) {
            return sprintf('M%.3F %.3Fh%.3Fv%.3Fh-%.3FZ', $x, $y, $w, $h, $w);
        }

        return sprintf(
            'M%.3F %.3Fh%.3Fa%.3F %.3F 0 0 1 %.3F %.3Fv%.3Fa%.3F %.3F 0 0 1 -%.3F %.3Fh-%.3Fa%.3F %.3F 0 0 1 -%.3F -%.3Fv-%.3Fa%.3F %.3F 0 0 1 %.3F -%.3FZ',
            $x + $r,
            $y,
            $w - (2.0 * $r),
            $r,
            $r,
            $r,
            $r,
            $h - (2.0 * $r),
            $r,
            $r,
            $r,
            $r,
            $w - (2.0 * $r),
            $r,
            $r,
            $r,
            $r,
            $h - (2.0 * $r),
            $r,
            $r,
            $r,
            $r
        );
    }

    private function renderLogoSvg(ProcessedLogo $logo, QrDesignOptions $design, float $totalModules): string
    {
        $boxSize = $totalModules * $design->logoScale;
        $aspect = $logo->width / max(1, $logo->height);
        $logoW = $aspect >= 1.0 ? $boxSize : $boxSize * $aspect;
        $logoH = $aspect >= 1.0 ? ($boxSize / $aspect) : $boxSize;

        $logoX = ($totalModules - $logoW) / 2.0;
        $logoY = ($totalModules - $logoH) / 2.0;

        $padUnits = ($design->logoPadding / 1000.0) * $totalModules;
        $bgMarkup = '';

        if ($design->logoBackgroundEnabled) {
            $padW = $logoW + (2.0 * $padUnits);
            $padH = $logoH + (2.0 * $padUnits);
            $padX = ($totalModules - $padW) / 2.0;
            $padY = ($totalModules - $padH) / 2.0;

            if ($design->logoBackgroundShape === 'circle') {
                $r = max($padW, $padH) / 2.0;
                $bgMarkup = sprintf(
                    '<circle cx="%.3F" cy="%.3F" r="%.3F" fill="%s"/>',
                    $totalModules / 2.0,
                    $totalModules / 2.0,
                    $r,
                    $design->logoBackgroundColor
                );
            } elseif ($design->logoBackgroundShape === 'rounded') {
                $rx = min($padW, $padH) * 0.22;
                $bgMarkup = sprintf(
                    '<rect x="%.3F" y="%.3F" width="%.3F" height="%.3F" rx="%.3F" ry="%.3F" fill="%s"/>',
                    $padX,
                    $padY,
                    $padW,
                    $padH,
                    $rx,
                    $rx,
                    $design->logoBackgroundColor
                );
            } else {
                $bgMarkup = sprintf(
                    '<rect x="%.3F" y="%.3F" width="%.3F" height="%.3F" fill="%s"/>',
                    $padX,
                    $padY,
                    $padW,
                    $padH,
                    $design->logoBackgroundColor
                );
            }
        }

        return $bgMarkup . sprintf(
            '<image href="%s" x="%.3F" y="%.3F" width="%.3F" height="%.3F" preserveAspectRatio="xMidYMid meet"/>',
            $logo->dataUri(),
            $logoX,
            $logoY,
            $logoW,
            $logoH
        );
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function linearGradientCoords(float $angleDegrees, float $span): array
    {
        $rad = deg2rad($angleDegrees);
        $cos = cos($rad);
        $sin = sin($rad);
        $half = $span / 2.0;

        return [
            $half - ($cos * $half),
            $half - ($sin * $half),
            $half + ($cos * $half),
            $half + ($sin * $half),
        ];
    }
}
