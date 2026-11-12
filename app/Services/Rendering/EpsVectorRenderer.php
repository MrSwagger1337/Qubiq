<?php

declare(strict_types=1);

namespace App\Services\Rendering;

use App\Services\QR\ProcessedLogo;
use App\Services\QR\QrDesignOptions;
use App\Services\QR\QrGenerationResult;
use App\Support\Color;
use chillerlan\QRCode\Data\QRMatrix;
use GdImage;

final class EpsVectorRenderer
{
    public function render(QrGenerationResult $result): string
    {
        $matrix = $result->matrix;
        $design = $result->design;
        $logo = $result->logo;

        $ptSize = max(256, min(2048, $design->size));
        $totalModules = $matrix->moduleCount;
        $cell = $ptSize / $totalModules;

        $lines = [
            '%!PS-Adobe-3.0 EPSF-3.0',
            sprintf('%%%%BoundingBox: 0 0 %d %d', $ptSize, $ptSize),
            sprintf('%%%%HiResBoundingBox: 0.000 0.000 %.3F %.3F', (float) $ptSize, (float) $ptSize),
            '%%Creator: Qubiq Open-Source QR Studio',
            '%%Title: Qubiq QR Code',
            '%%LanguageLevel: 2',
            '%%Pages: 1',
            '%%EndComments',
            '%%BeginProlog',
            '/rgb { setrgbcolor } bind def',
            '/sq { newpath moveto dup 0 rlineto dup 0 exch rlineto neg 0 rlineto closepath fill } bind def',
            '/ci { newpath 0 360 arc closepath fill } bind def',
            '/dm { /h exch def /cy exch def /cx exch def newpath cx cy h add moveto cx h add cy lineto cx cy h sub lineto cx h sub cy lineto closepath fill } bind def',
            '/rpath { /r exch def /h exch def /w exch def /y exch def /x exch def x r add y moveto x w add y x w add y h add r arct x w add y h add x y h add r arct x y h add x y r arct x y x w add y r arct closepath } bind def',
            '/rr { rpath fill } bind def',
            '%%EndProlog',
            'gsave',
        ];

        if (!$design->isTransparent()) {
            if ($design->backgroundMode === 'solid') {
                $lines[] = $this->psColor(Color::hexToRgb($design->backgroundColor));
                $lines[] = sprintf('0 0 %d %d rectfill', $ptSize, $ptSize);
            } else {
                $bands = 64;
                $bandH = $ptSize / $bands;
                for ($i = 0; $i < $bands; $i++) {
                    $t = ($i + 0.5) / $bands;
                    $py = $ptSize - (($i + 1) * $bandH);
                    $lines[] = $this->psColor($design->backgroundRgbAt($t, $t));
                    $lines[] = sprintf('0 %.3F %.3F %.3F rectfill', max(0.0, $py), (float) $ptSize, $bandH + 0.3);
                }
            }
        }

        $lastColor = '';
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
                $colCmd = $this->psColor($design->foregroundRgbAt($nx, $ny));
                if ($colCmd !== $lastColor) {
                    $lines[] = $colCmd;
                    $lastColor = $colCmd;
                }

                $psX = $x * $cell;
                $psY = $ptSize - (($y + 1) * $cell);
                $lines[] = $this->psModule($psX, $psY, $cell, $design->moduleShape);
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

            $psFx = $fx * $cell;
            $psFy = $ptSize - (($fy + 7) * $cell);

            $lines[] = $this->psColor($outerRgb);
            if ($design->finderOuterShape === 'circle') {
                $cx = $psFx + (3.5 * $cell);
                $cy = $psFy + (3.5 * $cell);
                $lines[] = sprintf(
                    'newpath %.3F %.3F %.3F 0 360 arc closepath %.3F %.3F %.3F 0 360 arc closepath eofill',
                    $cx,
                    $cy,
                    3.5 * $cell,
                    $cx,
                    $cy,
                    2.5 * $cell
                );
            } else {
                $outerRad = match ($design->finderOuterShape) {
                    'rounded' => 1.8 * $cell,
                    'cushion' => 2.5 * $cell,
                    default => 0.01,
                };
                $innerRad = match ($design->finderOuterShape) {
                    'rounded' => 1.1 * $cell,
                    'cushion' => 0.5 * $cell,
                    default => 0.01,
                };
                $lines[] = sprintf(
                    'newpath %.3F %.3F %.3F %.3F %.3F rpath %.3F %.3F %.3F %.3F %.3F rpath eofill',
                    $psFx,
                    $psFy,
                    7.0 * $cell,
                    7.0 * $cell,
                    $outerRad,
                    $psFx + $cell,
                    $psFy + $cell,
                    5.0 * $cell,
                    5.0 * $cell,
                    $innerRad
                );
            }

            $lines[] = $this->psColor($innerRgb);
            $psIx = ($fx + 2) * $cell;
            $psIy = $ptSize - (($fy + 5) * $cell);
            if ($design->finderInnerShape === 'circle') {
                $lines[] = sprintf('%.3F %.3F %.3F ci', $psIx + (1.5 * $cell), $psIy + (1.5 * $cell), 1.5 * $cell);
            } elseif ($design->finderInnerShape === 'rounded') {
                $lines[] = sprintf('%.3F %.3F %.3F %.3F %.3F rr', $psIx, $psIy, 3.0 * $cell, 3.0 * $cell, 0.85 * $cell);
            } else {
                $lines[] = sprintf('%.3F %.3F %.3F %.3F rectfill', $psIx, $psIy, 3.0 * $cell, 3.0 * $cell);
            }
        }

        if ($logo !== null) {
            $lines[] = $this->psLogo($logo, $design, (float) $ptSize);
        }

        $lines[] = 'grestore';
        $lines[] = 'showpage';
        $lines[] = '%%EOF';

        return implode("\n", $lines) . "\n";
    }

    private function psModule(float $x, float $y, float $cell, string $shape): string
    {
        return match ($shape) {
            'circle' => sprintf('%.3F %.3F %.3F ci', $x + ($cell / 2.0), $y + ($cell / 2.0), $cell * 0.46),
            'dots' => sprintf('%.3F %.3F %.3F ci', $x + ($cell / 2.0), $y + ($cell / 2.0), $cell * 0.36),
            'diamond' => sprintf('%.3F %.3F %.3F dm', $x + ($cell / 2.0), $y + ($cell / 2.0), $cell * 0.47),
            'rounded' => sprintf('%.3F %.3F %.3F %.3F %.3F rr', $x + ($cell * 0.04), $y + ($cell * 0.04), $cell * 0.92, $cell * 0.92, $cell * 0.28),
            'soft' => sprintf('%.3F %.3F %.3F %.3F %.3F rr', $x + ($cell * 0.02), $y + ($cell * 0.02), $cell * 0.96, $cell * 0.96, $cell * 0.40),
            default => sprintf('%.3F %.3F %.3F sq', $cell + 0.05, $x, $y),
        };
    }

    private function psLogo(ProcessedLogo $logo, QrDesignOptions $design, float $ptSize): string
    {
        $boxSize = $ptSize * $design->logoScale;
        $aspect = $logo->width / max(1, $logo->height);
        $logoW = $aspect >= 1.0 ? $boxSize : ($boxSize * $aspect);
        $logoH = $aspect >= 1.0 ? ($boxSize / $aspect) : $boxSize;
        $logoX = ($ptSize - $logoW) / 2.0;
        $logoY = ($ptSize - $logoH) / 2.0;

        $ps = [];
        $matteHex = $design->logoBackgroundEnabled ? $design->logoBackgroundColor : ($design->isTransparent() ? '#ffffff' : $design->backgroundColor);
        $matteRgb = Color::hexToRgb($matteHex);

        if ($design->logoBackgroundEnabled) {
            $padPt = ($design->logoPadding / 1000.0) * $ptSize;
            $padW = $logoW + (2.0 * $padPt);
            $padH = $logoH + (2.0 * $padPt);
            $padX = ($ptSize - $padW) / 2.0;
            $padY = ($ptSize - $padH) / 2.0;

            $ps[] = $this->psColor($matteRgb);
            if ($design->logoBackgroundShape === 'circle') {
                $ps[] = sprintf('%.3F %.3F %.3F ci', $ptSize / 2.0, $ptSize / 2.0, max($padW, $padH) / 2.0);
            } elseif ($design->logoBackgroundShape === 'rounded') {
                $ps[] = sprintf('%.3F %.3F %.3F %.3F %.3F rr', $padX, $padY, $padW, $padH, min($padW, $padH) * 0.22);
            } else {
                $ps[] = sprintf('%.3F %.3F %.3F %.3F rectfill', $padX, $padY, $padW, $padH);
            }
        }

        // Composite PNG alpha over matte color into a compact 96px max RGB bitmap for PostScript Level 2 colorimage
        $src = @imagecreatefromstring($logo->pngBinary);
        if (!$src instanceof GdImage) {
            return implode("\n", $ps);
        }

        $bmpW = max(1, min(96, $logo->width));
        $bmpH = max(1, (int) round(($logo->height / max(1, $logo->width)) * $bmpW));
        $bmp = imagecreatetruecolor($bmpW, $bmpH);
        if (!$bmp instanceof GdImage) {
            imagedestroy($src);

            return implode("\n", $ps);
        }

        $bgCol = imagecolorallocate($bmp, $matteRgb[0], $matteRgb[1], $matteRgb[2]);
        if ($bgCol !== false) {
            imagefilledrectangle($bmp, 0, 0, $bmpW, $bmpH, $bgCol);
        }
        imagealphablending($bmp, true);
        imagecopyresampled($bmp, $src, 0, 0, 0, 0, $bmpW, $bmpH, $logo->width, $logo->height);
        imagedestroy($src);

        $hexRows = [];
        for ($py = 0; $py < $bmpH; $py++) {
            $rowHex = '';
            for ($px = 0; $px < $bmpW; $px++) {
                $rgbInt = (int) imagecolorat($bmp, $px, $py);
                $r = ($rgbInt >> 16) & 0xFF;
                $g = ($rgbInt >> 8) & 0xFF;
                $b = $rgbInt & 0xFF;
                $rowHex .= sprintf('%02x%02x%02x', $r, $g, $b);
            }
            $hexRows[] = $rowHex;
        }
        imagedestroy($bmp);

        $ps[] = 'gsave';
        $ps[] = sprintf('%.3F %.3F translate', $logoX, $logoY);
        $ps[] = sprintf('%.3F %.3F scale', $logoW, $logoH);
        $ps[] = sprintf('/picstr %d string def', $bmpW * 3);
        $ps[] = sprintf('%d %d 8 [%d 0 0 -%d 0 %d] { currentfile picstr readhexstring pop } false 3 colorimage', $bmpW, $bmpH, $bmpW, $bmpH, $bmpH);
        $ps[] = implode("\n", $hexRows);
        $ps[] = 'grestore';

        return implode("\n", $ps);
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function psColor(array $rgb): string
    {
        return sprintf('%.4F %.4F %.4F rgb', $rgb[0] / 255.0, $rgb[1] / 255.0, $rgb[2] / 255.0);
    }
}
