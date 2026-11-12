<?php

declare(strict_types=1);

namespace App\Services\Rendering;

use FPDF;

final class QubiqPdfDocument extends FPDF
{
    private const KAPPA = 0.5522847498;

    private string $lastFillCmd = '';

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    public function setRgbFill(array $rgb): void
    {
        $cmd = sprintf('%.4F %.4F %.4F rg', $rgb[0] / 255.0, $rgb[1] / 255.0, $rgb[2] / 255.0);
        if ($cmd !== $this->lastFillCmd) {
            $this->lastFillCmd = $cmd;
            $this->_out($cmd);
        }
    }

    public function fillSquare(float $x, float $y, float $w, float $h): void
    {
        $this->_out(sprintf('%.3F %.3F %.3F %.3F re f', $x, $this->h - $y - $h, $w, $h));
    }

    public function fillCircle(float $cx, float $cy, float $r): void
    {
        $this->pathCircle($cx, $cy, $r);
        $this->_out('f');
    }

    public function fillDiamond(float $cx, float $cy, float $half): void
    {
        $py = $this->h - $cy;
        $this->_out(sprintf(
            '%.3F %.3F m %.3F %.3F l %.3F %.3F l %.3F %.3F l h f',
            $cx,
            $py + $half,
            $cx + $half,
            $py,
            $cx,
            $py - $half,
            $cx - $half,
            $py
        ));
    }

    public function fillRoundedRect(float $x, float $y, float $w, float $h, float $r): void
    {
        if ($r <= 0.01) {
            $this->fillSquare($x, $y, $w, $h);

            return;
        }
        $this->pathRoundedRect($x, $y, $w, $h, $r);
        $this->_out('f');
    }

    public function fillFinderRing(
        float $fx,
        float $fy,
        float $outerSpan,
        float $outerRad,
        float $innerOffset,
        float $innerSpan,
        float $innerRad,
        bool $circular
    ): void {
        if ($circular) {
            $cx = $fx + ($outerSpan / 2.0);
            $cy = $fy + ($outerSpan / 2.0);
            $this->pathCircle($cx, $cy, $outerSpan / 2.0);
            $this->pathCircle($cx, $cy, $innerSpan / 2.0);
            $this->_out('f*');

            return;
        }

        $this->pathRoundedRect($fx, $fy, $outerSpan, $outerSpan, $outerRad);
        $this->pathRoundedRect($fx + $innerOffset, $fy + $innerOffset, $innerSpan, $innerSpan, $innerRad);
        $this->_out('f*');
    }

    private function pathCircle(float $cx, float $cy, float $r): void
    {
        $py = $this->h - $cy;
        $c = $r * self::KAPPA;

        $this->_out(sprintf('%.3F %.3F m', $cx + $r, $py));
        $this->_out(sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c', $cx + $r, $py + $c, $cx + $c, $py + $r, $cx, $py + $r));
        $this->_out(sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c', $cx - $c, $py + $r, $cx - $r, $py + $c, $cx - $r, $py));
        $this->_out(sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c', $cx - $r, $py - $c, $cx - $c, $py - $r, $cx, $py - $r));
        $this->_out(sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c h', $cx + $c, $py - $r, $cx + $r, $py - $c, $cx + $r, $py));
    }

    private function pathRoundedRect(float $x, float $y, float $w, float $h, float $r): void
    {
        $r = max(0.0, min($r, $w / 2.0, $h / 2.0));
        if ($r <= 0.01) {
            $this->_out(sprintf('%.3F %.3F %.3F %.3F re', $x, $this->h - $y - $h, $w, $h));

            return;
        }

        $c = $r * self::KAPPA;
        $top = $this->h - $y;
        $bottom = $this->h - ($y + $h);
        $left = $x;
        $right = $x + $w;

        $this->_out(sprintf('%.3F %.3F m', $left + $r, $top));
        $this->_out(sprintf('%.3F %.3F l', $right - $r, $top));
        $this->_out(sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c', $right - $r + $c, $top, $right, $top - $r + $c, $right, $top - $r));
        $this->_out(sprintf('%.3F %.3F l', $right, $bottom + $r));
        $this->_out(sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c', $right, $bottom + $r - $c, $right - $r + $c, $bottom, $right - $r, $bottom));
        $this->_out(sprintf('%.3F %.3F l', $left + $r, $bottom));
        $this->_out(sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c', $left + $r - $c, $bottom, $left, $bottom + $r - $c, $left, $bottom + $r));
        $this->_out(sprintf('%.3F %.3F l', $left, $top - $r));
        $this->_out(sprintf('%.3F %.3F %.3F %.3F %.3F %.3F c h', $left, $top - $r + $c, $left + $r - $c, $top, $left + $r, $top));
    }
}
