<?php

declare(strict_types=1);

namespace App\Services\QR;

use App\Support\Color;
use App\Support\Translator;
use chillerlan\QRCode\Data\QRMatrix;

final class ReadabilityAdvisorService
{
    public function __construct(
        private readonly Translator $translator
    ) {
    }

    /**
     * Evaluates visual contrast, inverted luminance, logo coverage, ECC pairing, quiet zone, and matrix density.
     *
     * @return list<array{code: string, severity: string, message: string}>
     */
    public function evaluate(QRMatrix $matrix, QrDesignOptions $design, bool $hasLogo): array
    {
        $warnings = [];

        if ($design->isTransparent()) {
            $warnings[] = [
                'code' => 'transparent_dark_surface',
                'severity' => 'info',
                'message' => $this->translator->get('warnings.transparent_dark_surface'),
            ];
        } else {
            $fgColors = [$design->foregroundColor];
            if ($design->foregroundMode !== 'solid') {
                $fgColors[] = $design->foregroundGradientEnd;
            }
            if ($design->finderOuterColor !== null) {
                $fgColors[] = $design->finderOuterColor;
            }
            if ($design->finderInnerColor !== null) {
                $fgColors[] = $design->finderInnerColor;
            }

            $bgColors = [$design->backgroundColor];
            if ($design->backgroundMode === 'linear' || $design->backgroundMode === 'radial') {
                $bgColors[] = $design->backgroundGradientEnd;
            }

            $minContrast = 21.0;
            $invertedDetected = false;

            foreach ($fgColors as $fg) {
                $fgLum = Color::relativeLuminance($fg);
                foreach ($bgColors as $bg) {
                    $bgLum = Color::relativeLuminance($bg);
                    if ($fgLum > $bgLum) {
                        $invertedDetected = true;
                    }
                    $ratio = Color::contrastRatio($fg, $bg);
                    if ($ratio < $minContrast) {
                        $minContrast = $ratio;
                    }
                }
            }

            if ($invertedDetected) {
                $warnings[] = [
                    'code' => 'inverted_contrast',
                    'severity' => 'warning',
                    'message' => $this->translator->get('warnings.inverted_contrast'),
                ];
            }

            if ($minContrast < 4.0) {
                $warnings[] = [
                    'code' => 'low_contrast',
                    'severity' => $minContrast < 2.5 ? 'high' : 'warning',
                    'message' => $this->translator->get('warnings.low_contrast', [
                        'ratio' => number_format($minContrast, 2),
                    ]),
                ];
            }
        }

        if ($hasLogo) {
            if ($design->eccLevel !== 'H') {
                $warnings[] = [
                    'code' => 'logo_needs_ecc_h',
                    'severity' => 'warning',
                    'message' => $this->translator->get('warnings.logo_needs_ecc_h', [
                        'ecc' => $design->eccLevel,
                    ]),
                ];
            }

            $coveragePercent = (int) round(($design->logoScale * $design->logoScale) * 100);
            if ($design->logoScale > 0.22) {
                $warnings[] = [
                    'code' => 'high_logo_coverage',
                    'severity' => 'warning',
                    'message' => $this->translator->get('warnings.high_logo_coverage', [
                        'percent' => $coveragePercent,
                    ]),
                ];
            }
        }

        if ($design->quietZone < 4) {
            $warnings[] = [
                'code' => 'small_quiet_zone',
                'severity' => 'warning',
                'message' => $this->translator->get('warnings.small_quiet_zone', [
                    'qz' => $design->quietZone,
                ]),
            ];
        }

        $versionNumber = $matrix->version !== null ? $matrix->version->getVersionNumber() : 1;
        $symbolModules = $matrix->version !== null ? $matrix->version->getDimension() : $matrix->moduleCount;
        if ($versionNumber >= 18) {
            $warnings[] = [
                'code' => 'dense_matrix',
                'severity' => 'info',
                'message' => $this->translator->get('warnings.dense_matrix', [
                    'version' => $versionNumber,
                    'modules' => $symbolModules,
                ]),
            ];
        }

        return $warnings;
    }
}
