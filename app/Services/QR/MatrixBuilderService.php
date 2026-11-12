<?php

declare(strict_types=1);

namespace App\Services\QR;

use App\Exceptions\CapacityOverflowException;
use App\Support\Config;
use App\Support\Translator;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Common\MaskPattern;
use chillerlan\QRCode\Common\Version;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Throwable;

final class MatrixBuilderService
{
    public function __construct(
        private readonly Config $config,
        private readonly Translator $translator
    ) {
    }

    public function build(string $payload, QrDesignOptions $design, bool $hasLogo = false): QRMatrix
    {
        $maxPayloadBytes = $this->config->int('qr.max_payload_bytes', 2953);
        $byteLength = strlen($payload);

        if ($byteLength === 0 || $byteLength > $maxPayloadBytes) {
            throw new CapacityOverflowException(
                $this->translator->get('errors.capacity_exceeded', ['bytes' => $byteLength])
            );
        }

        $eccConstant = match ($design->eccLevel) {
            'L' => EccLevel::L,
            'M' => EccLevel::M,
            'Q' => EccLevel::Q,
            'H' => EccLevel::H,
            default => EccLevel::M,
        };

        $options = new QROptions([
            'version' => $design->version === 0 ? Version::AUTO : $design->version,
            'eccLevel' => $eccConstant,
            'maskPattern' => $design->maskPattern === -1 ? MaskPattern::AUTO : $design->maskPattern,
            'addQuietzone' => $design->quietZone > 0,
            'quietzoneSize' => $design->quietZone,
            'addLogoSpace' => false,
        ]);

        try {
            $qrCode = new QRCode($options);
            $qrCode->addByteSegment($payload);
            $matrix = $qrCode->getQRMatrix();
        } catch (Throwable $e) {
            throw new CapacityOverflowException(
                $this->translator->get('errors.capacity_exceeded', ['bytes' => $byteLength])
                . ' Try setting QR Version to Auto or choosing a lower Error Correction level.',
            );
        }

        // Reserve clean central logo space when ECC is H so partial modules do not peek around transparent logos
        if ($hasLogo && $design->eccLevel === 'H' && $matrix->version !== null) {
            $symbolDim = $matrix->version->getDimension();
            $totalDim = $matrix->moduleCount;
            $logoModules = (int) floor($totalDim * $design->logoScale);
            $maxSafeModules = (int) floor(sqrt($symbolDim * $symbolDim * 0.23));
            $clampedModules = max(3, min($logoModules, $maxSafeModules));
            if (($clampedModules * $clampedModules) <= (int) ($symbolDim * $symbolDim * 0.24)) {
                $matrix->setLogoSpace($clampedModules, $clampedModules);
            }
        }

        return $matrix;
    }
}
