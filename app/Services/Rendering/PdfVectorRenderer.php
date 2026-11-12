<?php

declare(strict_types=1);

namespace App\Services\Rendering;

use App\Services\QR\ProcessedLogo;
use App\Services\QR\QrDesignOptions;
use App\Services\QR\QrGenerationResult;
use App\Support\Color;
use App\Support\Config;
use chillerlan\QRCode\Data\QRMatrix;

final class PdfVectorRenderer
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function render(QrGenerationResult $result): string
    {
        $matrix = $result->matrix;
        $design = $result->design;
        $logo = $result->logo;

        // Convert requested pixel dimension into clean print points (72 pt/in at 150 DPI reference, minimum 288 pt = 4 inches)
        $pagePt = max(216.0, min(1440.0, round($design->size * 0.5, 2)));
        $totalModules = $matrix->moduleCount;
        $cell = $pagePt / $totalModules;

        $pdf = new QubiqPdfDocument('P', 'pt', [$pagePt, $pagePt]);
        $pdf->SetTitle('Qubiq QR Code');
        $pdf->SetCreator('Qubiq Open-Source QR Studio');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        if (!$design->isTransparent()) {
            if ($design->backgroundMode === 'solid') {
                $pdf->setRgbFill(Color::hexToRgb($design->backgroundColor));
                $pdf->fillSquare(0.0, 0.0, $pagePt, $pagePt);
            } else {
                $bands = 64;
                $bandH = $pagePt / $bands;
                for ($i = 0; $i < $bands; $i++) {
                    $t = ($i + 0.5) / $bands;
                    $pdf->setRgbFill($design->backgroundRgbAt($t, $t));
                    $pdf->fillSquare(0.0, $i * $bandH, $pagePt, $bandH + 0.3);
                }
            }
        }

        $grid = $matrix->matrix;
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
                $pdf->setRgbFill($design->foregroundRgbAt($nx, $ny));
                $this->drawPdfModule($pdf, $x * $cell, $y * $cell, $cell, $design->moduleShape);
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

            $outerRad = match ($design->finderOuterShape) {
                'rounded' => 1.8 * $cell,
                'cushion' => 2.5 * $cell,
                default => 0.0,
            };
            $innerCutRad = match ($design->finderOuterShape) {
                'rounded' => 1.1 * $cell,
                'cushion' => 0.5 * $cell,
                default => 0.0,
            };

            $pdf->setRgbFill($outerRgb);
            $pdf->fillFinderRing(
                $fx * $cell,
                $fy * $cell,
                7.0 * $cell,
                $outerRad,
                $cell,
                5.0 * $cell,
                $innerCutRad,
                $design->finderOuterShape === 'circle'
            );

            $pdf->setRgbFill($innerRgb);
            $ix = ($fx + 2) * $cell;
            $iy = ($fy + 2) * $cell;
            if ($design->finderInnerShape === 'circle') {
                $pdf->fillCircle($ix + (1.5 * $cell), $iy + (1.5 * $cell), 1.5 * $cell);
            } elseif ($design->finderInnerShape === 'rounded') {
                $pdf->fillRoundedRect($ix, $iy, 3.0 * $cell, 3.0 * $cell, 0.85 * $cell);
            } else {
                $pdf->fillSquare($ix, $iy, 3.0 * $cell, 3.0 * $cell);
            }
        }

        if ($logo !== null) {
            $this->embedPdfLogo($pdf, $pagePt, $logo, $design);
        }

        return (string) $pdf->Output('S');
    }

    private function drawPdfModule(QubiqPdfDocument $pdf, float $px, float $py, float $cell, string $shape): void
    {
        if ($shape === 'circle' || $shape === 'dots') {
            $r = $cell * ($shape === 'dots' ? 0.36 : 0.46);
            $pdf->fillCircle($px + ($cell / 2.0), $py + ($cell / 2.0), $r);

            return;
        }
        if ($shape === 'diamond') {
            $pdf->fillDiamond($px + ($cell / 2.0), $py + ($cell / 2.0), $cell * 0.47);

            return;
        }
        if ($shape === 'rounded' || $shape === 'soft') {
            $inset = $cell * ($shape === 'soft' ? 0.02 : 0.04);
            $w = $cell - (2.0 * $inset);
            $rad = $cell * ($shape === 'soft' ? 0.40 : 0.28);
            $pdf->fillRoundedRect($px + $inset, $py + $inset, $w, $w, $rad);

            return;
        }

        // Slight +0.08 pt overlap prevents sub-point hairline seams in PDF viewers
        $pdf->fillSquare($px, $py, $cell + 0.08, $cell + 0.08);
    }

    private function embedPdfLogo(QubiqPdfDocument $pdf, float $pagePt, ProcessedLogo $logo, QrDesignOptions $design): void
    {
        $boxSize = $pagePt * $design->logoScale;
        $aspect = $logo->width / max(1, $logo->height);
        $logoW = $aspect >= 1.0 ? $boxSize : ($boxSize * $aspect);
        $logoH = $aspect >= 1.0 ? ($boxSize / $aspect) : $boxSize;
        $logoX = ($pagePt - $logoW) / 2.0;
        $logoY = ($pagePt - $logoH) / 2.0;

        if ($design->logoBackgroundEnabled) {
            $padPt = ($design->logoPadding / 1000.0) * $pagePt;
            $padW = $logoW + (2.0 * $padPt);
            $padH = $logoH + (2.0 * $padPt);
            $padX = ($pagePt - $padW) / 2.0;
            $padY = ($pagePt - $padH) / 2.0;

            $pdf->setRgbFill(Color::hexToRgb($design->logoBackgroundColor));
            if ($design->logoBackgroundShape === 'circle') {
                $pdf->fillCircle($pagePt / 2.0, $pagePt / 2.0, max($padW, $padH) / 2.0);
            } elseif ($design->logoBackgroundShape === 'rounded') {
                $pdf->fillRoundedRect($padX, $padY, $padW, $padH, min($padW, $padH) * 0.22);
            } else {
                $pdf->fillSquare($padX, $padY, $padW, $padH);
            }
        }

        $tempDir = $this->config->string('paths.temp', sys_get_temp_dir());
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }
        $tempFile = rtrim($tempDir, '/\\') . DIRECTORY_SEPARATOR . 'qubiq_logo_' . bin2hex(random_bytes(8)) . '.png';

        try {
            file_put_contents($tempFile, $logo->pngBinary, LOCK_EX);
            $pdf->Image($tempFile, $logoX, $logoY, $logoW, $logoH, 'PNG');
        } finally {
            @unlink($tempFile);
        }
    }
}
