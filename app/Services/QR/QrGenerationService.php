<?php

declare(strict_types=1);

namespace App\Services\QR;

use App\Payloads\PayloadEncoderFactory;
use App\Support\Config;

final class QrGenerationService
{
    public function __construct(
        private readonly Config $config,
        private readonly PayloadEncoderFactory $payloadFactory,
        private readonly MatrixBuilderService $matrixBuilder,
        private readonly LogoProcessorService $logoProcessor,
        private readonly ReadabilityAdvisorService $advisor
    ) {
    }

    /**
     * Authoritative pipeline that validates input, encodes the typed payload, validates any logo,
     * constructs the QRMatrix, and computes scan-health metadata.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $uploadedLogoFile
     */
    public function prepare(array $input, ?array $uploadedLogoFile = null, ?string $overrideEncodedPayload = null): QrGenerationResult
    {
        $type = isset($input['type']) && is_scalar($input['type']) ? strtolower(trim((string) $input['type'])) : 'url';
        $encoder = $this->payloadFactory->get($type);

        $contentData = isset($input['data']) && is_array($input['data']) ? $input['data'] : $input;
        /** @var array<string, mixed> $typedData */
        $typedData = $contentData;

        $encodedPayload = $overrideEncodedPayload ?? $encoder->encode($typedData);

        $designInput = isset($input['design']) && is_array($input['design']) ? $input['design'] : $input;
        /** @var array<string, mixed> $typedDesign */
        $typedDesign = $designInput;
        $design = QrDesignOptions::fromArray($typedDesign, $this->config);

        $inlineLogo = null;
        if (isset($typedDesign['logo_data']) && is_string($typedDesign['logo_data'])) {
            $inlineLogo = $typedDesign['logo_data'];
        } elseif (isset($input['logo_data']) && is_string($input['logo_data'])) {
            $inlineLogo = $input['logo_data'];
        }

        $logo = $this->logoProcessor->process($uploadedLogoFile, $inlineLogo);
        $matrix = $this->matrixBuilder->build($encodedPayload, $design, $logo !== null);
        $warnings = $this->advisor->evaluate($matrix, $design, $logo !== null);

        return new QrGenerationResult(
            contentType: $encoder->type(),
            encodedPayload: $encodedPayload,
            matrix: $matrix,
            design: $design,
            logo: $logo,
            warnings: $warnings
        );
    }
}
