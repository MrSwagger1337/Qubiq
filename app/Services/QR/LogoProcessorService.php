<?php

declare(strict_types=1);

namespace App\Services\QR;

use App\Exceptions\ValidationException;
use App\Support\Config;
use App\Support\Translator;
use finfo;
use GdImage;

final class LogoProcessorService
{
    public function __construct(
        private readonly Config $config,
        private readonly Translator $translator
    ) {
    }

    /**
     * Processes either an uploaded $_FILES['logo'] entry or a base64 data URI string.
     * Remote HTTP/HTTPS URLs are strictly rejected to prevent SSRF.
     * SVG uploads are strictly rejected per security & cross-renderer fidelity policy.
     *
     * @param array<string, mixed>|null $uploadedFile
     */
    public function process(?array $uploadedFile, ?string $inlineLogoData): ?ProcessedLogo
    {
        if ($uploadedFile !== null) {
            return $this->processUploadedFile($uploadedFile);
        }

        if ($inlineLogoData !== null && trim($inlineLogoData) !== '') {
            return $this->processInlineData($inlineLogoData);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $file
     */
    private function processUploadedFile(array $file): ProcessedLogo
    {
        $error = isset($file['error']) && is_numeric($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new ValidationException('Logo file upload failed.', [
                'logo' => 'File could not be uploaded cleanly (check file size limits).',
            ]);
        }

        $tmpName = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';
        if ($tmpName === '' || !is_file($tmpName)) {
            throw new ValidationException($this->translator->get('errors.invalid_logo'), [
                'logo' => 'Uploaded temporary file could not be read.',
            ]);
        }

        $maxBytes = $this->config->int('logo.max_bytes', 2097152);
        try {
            $size = filesize($tmpName);
            if ($size === false || $size <= 0 || $size > $maxBytes) {
                throw new ValidationException(
                    sprintf('Logo file must not exceed %d KB.', (int) round($maxBytes / 1024)),
                    ['logo' => 'Uploaded logo exceeds maximum allowed file size.']
                );
            }

            $bytes = file_get_contents($tmpName);
            if ($bytes === false) {
                throw new ValidationException($this->translator->get('errors.invalid_logo'), [
                    'logo' => 'Unable to read uploaded logo bytes.',
                ]);
            }

            return $this->validateAndSanitizeBytes($bytes);
        } finally {
            if (is_uploaded_file($tmpName)) {
                @unlink($tmpName);
            }
        }
    }

    private function processInlineData(string $raw): ProcessedLogo
    {
        $trimmed = trim($raw);

        // Explicitly block remote URLs (SSRF prevention)
        if (preg_match('/^https?:\/\//i', $trimmed) === 1 || preg_match('/^[a-zA-Z]+:\/\//', $trimmed) === 1) {
            throw new ValidationException('Remote logo URLs are forbidden for security. Upload the image or pass a base64 data URI.', [
                'logo' => 'Remote URL fetching is disabled.',
            ]);
        }

        if (str_starts_with(strtolower($trimmed), 'data:image/svg') || str_starts_with(ltrim($trimmed), '<svg') || str_contains(strtolower(substr($trimmed, 0, 256)), '<svg')) {
            throw new ValidationException($this->translator->get('errors.svg_logo_rejected'), [
                'logo' => $this->translator->get('errors.svg_logo_rejected'),
            ]);
        }

        if (preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,([A-Za-z0-9+\/\s=]+)$/i', $trimmed, $m) !== 1) {
            throw new ValidationException($this->translator->get('errors.invalid_logo'), [
                'logo' => 'Inline logo must be a valid PNG, JPEG, or WebP base64 data URI.',
            ]);
        }

        $maxBytes = $this->config->int('logo.max_bytes', 2097152);
        $b64 = preg_replace('/\s+/', '', $m[2]) ?? '';
        if (strlen($b64) > (int) ceil($maxBytes * 1.38)) {
            throw new ValidationException(
                sprintf('Logo file must not exceed %d KB.', (int) round($maxBytes / 1024)),
                ['logo' => 'Encoded logo exceeds maximum allowed file size.']
            );
        }

        $decoded = base64_decode($b64, true);
        if ($decoded === false || $decoded === '' || strlen($decoded) > $maxBytes) {
            throw new ValidationException($this->translator->get('errors.invalid_logo'), [
                'logo' => 'Base64 logo payload is corrupted or exceeds size limits.',
            ]);
        }

        return $this->validateAndSanitizeBytes($decoded);
    }

    public function validateAndSanitizeBytes(string $bytes): ProcessedLogo
    {
        $maxBytes = $this->config->int('logo.max_bytes', 2097152);
        if ($bytes === '' || strlen($bytes) > $maxBytes) {
            throw new ValidationException($this->translator->get('errors.invalid_logo'), [
                'logo' => 'Logo image exceeds configured byte limit.',
            ]);
        }

        // Detect SVG / XML / HTML payloads by content inspection regardless of filename or extension
        $head = strtolower(ltrim(substr($bytes, 0, 512)));
        if (str_starts_with($head, '<svg') || str_starts_with($head, '<?xml') || str_contains($head, '<svg') || str_contains($head, '<!doctype')) {
            throw new ValidationException($this->translator->get('errors.svg_logo_rejected'), [
                'logo' => $this->translator->get('errors.svg_logo_rejected'),
            ]);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) $finfo->buffer($bytes);

        if (str_contains($detectedMime, 'svg') || str_contains($detectedMime, 'xml') || str_contains($detectedMime, 'html')) {
            throw new ValidationException($this->translator->get('errors.svg_logo_rejected'), [
                'logo' => $this->translator->get('errors.svg_logo_rejected'),
            ]);
        }

        /** @var list<string> $allowedMimes */
        $allowedMimes = $this->config->array('logo.allowed_mimes') ?: ['image/png', 'image/jpeg', 'image/webp'];
        if (!in_array($detectedMime, $allowedMimes, true)) {
            throw new ValidationException($this->translator->get('errors.invalid_logo'), [
                'logo' => sprintf('Unsupported logo MIME type "%s". Use PNG, JPEG, or WebP.', $detectedMime),
            ]);
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            throw new ValidationException($this->translator->get('errors.invalid_logo'), [
                'logo' => 'Corrupted image header.',
            ]);
        }

        [$width, $height] = [$info[0], $info[1]];
        $maxDim = $this->config->int('logo.max_dimension', 2048);
        $maxPixels = $this->config->int('logo.max_pixels', 4194304);

        if ($width < 8 || $height < 8 || $width > $maxDim || $height > $maxDim || ($width * $height) > $maxPixels) {
            throw new ValidationException(
                sprintf('Logo dimensions must be between 8x8 and %dx%d pixels.', $maxDim, $maxDim),
                ['logo' => sprintf('Image dimensions (%dx%d) exceed safe processing bounds.', $width, $height)]
            );
        }

        $source = @imagecreatefromstring($bytes);
        if (!$source instanceof GdImage) {
            throw new ValidationException($this->translator->get('errors.invalid_logo'), [
                'logo' => 'Image could not be decoded safely.',
            ]);
        }

        // Re-encode into a fresh truecolor RGBA PNG canvas to strip all metadata, EXIF, and trailing polyglot data
        $targetW = min($width, 512);
        $targetH = max(1, (int) round(($height / $width) * $targetW));
        if ($targetH > 512) {
            $targetH = 512;
            $targetW = max(1, (int) round(($width / $height) * $targetH));
        }

        $clean = imagecreatetruecolor($targetW, $targetH);
        if (!$clean instanceof GdImage) {
            imagedestroy($source);
            throw new ValidationException('Failed to allocate sanitized logo canvas.');
        }

        imagealphablending($clean, false);
        imagesavealpha($clean, true);
        $transparent = imagecolorallocatealpha($clean, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefilledrectangle($clean, 0, 0, $targetW, $targetH, $transparent);
        }
        imagecopyresampled($clean, $source, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
        imagedestroy($source);

        ob_start();
        imagepng($clean, null, 6);
        $pngBinary = (string) ob_get_clean();
        imagedestroy($clean);

        if ($pngBinary === '') {
            throw new ValidationException('Failed to encode sanitized logo.');
        }

        return new ProcessedLogo($pngBinary, $targetW, $targetH, $detectedMime);
    }
}
