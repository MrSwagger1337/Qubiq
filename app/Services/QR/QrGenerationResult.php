<?php

declare(strict_types=1);

namespace App\Services\QR;

use chillerlan\QRCode\Data\QRMatrix;

final readonly class QrGenerationResult
{
    /**
     * @param list<array{code: string, severity: string, message: string}> $warnings
     */
    public function __construct(
        public string $contentType,
        public string $encodedPayload,
        public QRMatrix $matrix,
        public QrDesignOptions $design,
        public ?ProcessedLogo $logo,
        public array $warnings
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        $versionNumber = $this->matrix->version !== null ? $this->matrix->version->getVersionNumber() : 1;
        $symbolModules = $this->matrix->version !== null ? $this->matrix->version->getDimension() : $this->matrix->moduleCount;
        $maskPattern = $this->matrix->maskPattern !== null ? $this->matrix->maskPattern->getPattern() : 0;

        return [
            'content_type' => $this->contentType,
            'byte_length' => strlen($this->encodedPayload),
            'char_length' => mb_strlen($this->encodedPayload, 'UTF-8'),
            'version' => $versionNumber,
            'ecc_level' => $this->design->eccLevel,
            'mask_pattern' => $maskPattern,
            'symbol_modules' => $symbolModules,
            'total_modules' => $this->matrix->moduleCount,
            'quiet_zone' => $this->design->quietZone,
            'output_size' => $this->design->size,
            'has_logo' => $this->logo !== null,
            'warnings' => $this->warnings,
        ];
    }
}
