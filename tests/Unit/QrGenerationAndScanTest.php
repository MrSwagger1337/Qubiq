<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\CapacityOverflowException;
use App\Services\Export\QrExportService;
use App\Services\QR\QrGenerationService;
use chillerlan\QRCode\QRCode;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class QrGenerationAndScanTest extends TestCase
{
    private QrGenerationService $qrService;

    private QrExportService $exportService;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var QrGenerationService $qrService */
        $qrService = $this->container['qr_service'];
        /** @var QrExportService $exportService */
        $exportService = $this->container['export_service'];
        $this->qrService = $qrService;
        $this->exportService = $exportService;
    }

    /**
     * @return array<string, array{shape: string, outer: string, inner: string, ecc: string, fgMode: string, withLogo: bool}>
     */
    public static function scannableDesignMatrixProvider(): array
    {
        return [
            'classic_squares' => ['shape' => 'square', 'outer' => 'square', 'inner' => 'square', 'ecc' => 'M', 'fgMode' => 'solid', 'withLogo' => false],
            'rounded_linear_gradient' => ['shape' => 'rounded', 'outer' => 'rounded', 'inner' => 'rounded', 'ecc' => 'Q', 'fgMode' => 'linear', 'withLogo' => false],
            'circular_radial_gradient' => ['shape' => 'circle', 'outer' => 'circle', 'inner' => 'circle', 'ecc' => 'Q', 'fgMode' => 'radial', 'withLogo' => false],
            'dots_cushion_eyes' => ['shape' => 'dots', 'outer' => 'cushion', 'inner' => 'circle', 'ecc' => 'H', 'fgMode' => 'solid', 'withLogo' => false],
            'diamond_modules' => ['shape' => 'diamond', 'outer' => 'square', 'inner' => 'rounded', 'ecc' => 'M', 'fgMode' => 'solid', 'withLogo' => false],
            'soft_with_centered_logo_ecc_h' => ['shape' => 'soft', 'outer' => 'rounded', 'inner' => 'circle', 'ecc' => 'H', 'fgMode' => 'linear', 'withLogo' => true],
        ];
    }

    #[DataProvider('scannableDesignMatrixProvider')]
    public function testGeneratedQrImagesDecodeAccuratelyWithZxingDecoder(
        string $shape,
        string $outer,
        string $inner,
        string $ecc,
        string $fgMode,
        bool $withLogo
    ): void {
        $expectedUrl = 'https://qubiq.test/scan-verify?shape=' . $shape;
        $result = $this->qrService->prepare([
            'type' => 'url',
            'data' => ['url' => $expectedUrl],
            'design' => [
                'ecc' => $ecc,
                'size' => 600,
                'foreground_mode' => $fgMode,
                'foreground_color' => '#0f172a',
                'foreground_gradient_end' => '#047857',
                'background_mode' => 'solid',
                'background_color' => '#ffffff',
                'module_shape' => $shape,
                'finder_outer_shape' => $outer,
                'finder_inner_shape' => $inner,
                'logo_data' => $withLogo ? $this->createSamplePngDataUri() : null,
                'logo_scale' => 0.18,
            ],
        ]);

        $png = $this->exportService->export($result, 'png');
        $decoded = (new QRCode())->readFromBlob($png->content);

        self::assertSame($expectedUrl, $decoded->data);
    }

    public function testVersionOneCapacityOverflowThrowsExplicitException(): void
    {
        $this->expectException(CapacityOverflowException::class);

        $this->qrService->prepare([
            'type' => 'text',
            'data' => ['text' => str_repeat('ExceedsVersion1Capacity-', 10)],
            'design' => [
                'version' => 1,
                'ecc' => 'H',
            ],
        ]);
    }

    public function testReadabilityAdvisorEmitsLowContrastInvertedAndLogoEccWarnings(): void
    {
        $result = $this->qrService->prepare([
            'type' => 'url',
            'data' => ['url' => 'https://example.com'],
            'design' => [
                'ecc' => 'L',
                'quiet_zone' => 2,
                'foreground_color' => '#f8fafc',
                'background_color' => '#e2e8f0',
                'logo_data' => $this->createSamplePngDataUri(),
                'logo_scale' => 0.26,
            ],
        ]);

        $codes = array_column($result->warnings, 'code');
        self::assertContains('inverted_contrast', $codes);
        self::assertContains('low_contrast', $codes);
        self::assertContains('logo_needs_ecc_h', $codes);
        self::assertContains('high_logo_coverage', $codes);
        self::assertContains('small_quiet_zone', $codes);
    }
}
