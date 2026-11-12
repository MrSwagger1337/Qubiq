<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Exceptions\ValidationException;
use App\Services\QR\QrGenerationResult;
use App\Services\Rendering\EpsVectorRenderer;
use App\Services\Rendering\GdRasterRenderer;
use App\Services\Rendering\PdfVectorRenderer;
use App\Services\Rendering\SvgRenderer;

final class QrExportService
{
    public const SUPPORTED_FORMATS = [
        'png' => ['mime' => 'image/png', 'ext' => 'png'],
        'svg' => ['mime' => 'image/svg+xml', 'ext' => 'svg'],
        'pdf' => ['mime' => 'application/pdf', 'ext' => 'pdf'],
        'eps' => ['mime' => 'application/postscript', 'ext' => 'eps'],
        'webp' => ['mime' => 'image/webp', 'ext' => 'webp'],
        'jpeg' => ['mime' => 'image/jpeg', 'ext' => 'jpg'],
        'jpg' => ['mime' => 'image/jpeg', 'ext' => 'jpg'],
    ];

    public function __construct(
        private readonly SvgRenderer $svgRenderer,
        private readonly GdRasterRenderer $rasterRenderer,
        private readonly PdfVectorRenderer $pdfRenderer,
        private readonly EpsVectorRenderer $epsRenderer
    ) {
    }

    public function export(QrGenerationResult $result, string $format, ?string $requestedFilename = null): ExportedFile
    {
        $normalizedFormat = strtolower(trim($format));
        if (!isset(self::SUPPORTED_FORMATS[$normalizedFormat])) {
            throw new ValidationException(sprintf('Unsupported export format "%s".', $format), [
                'format' => 'Supported export formats: png, svg, pdf, eps, webp, jpeg.',
            ]);
        }

        $canonical = $normalizedFormat === 'jpg' ? 'jpeg' : $normalizedFormat;
        $meta = self::SUPPORTED_FORMATS[$canonical];

        $binary = match ($canonical) {
            'svg' => $this->svgRenderer->render($result),
            'png' => $this->rasterRenderer->render($result, 'png'),
            'webp' => $this->rasterRenderer->render($result, 'webp'),
            'jpeg' => $this->rasterRenderer->render($result, 'jpeg'),
            'pdf' => $this->pdfRenderer->render($result),
            'eps' => $this->epsRenderer->render($result),
        };

        $safeBase = 'qubiq-' . $result->contentType . '-qr';
        if ($requestedFilename !== null && trim($requestedFilename) !== '') {
            $cleaned = preg_replace('/\.[a-zA-Z0-9]{2,4}$/', '', trim($requestedFilename)) ?? '';
            $cleaned = preg_replace('/[^A-Za-z0-9._-]/', '-', $cleaned) ?? '';
            $cleaned = trim($cleaned, '.-_');
            if ($cleaned !== '' && strlen($cleaned) <= 80) {
                $safeBase = $cleaned;
            }
        }

        $filename = $safeBase . '.' . $meta['ext'];

        return new ExportedFile(
            content: $binary,
            format: $canonical,
            mimeType: $meta['mime'],
            extension: $meta['ext'],
            filename: $filename
        );
    }
}
