<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Validators\UrlSafetyValidator;

final class YouTubePayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'youtube';
    }

    public function label(): string
    {
        return 'YouTube';
    }

    public function category(): string
    {
        return 'more';
    }

    public function isUrlWrapper(): bool
    {
        return true;
    }

    public function description(): string
    {
        return 'Standard HTTPS URL QR code scoped to a validated YouTube channel, video, or playlist link.';
    }

    public function sampleData(): array
    {
        return [
            'url' => 'https://www.youtube.com/@GitHub',
        ];
    }

    public function encode(array $input): string
    {
        $raw = isset($input['url']) && is_scalar($input['url']) ? trim((string) $input['url']) : '';

        return UrlSafetyValidator::validateDomainScopedUrl(
            $raw,
            ['youtube.com', 'youtu.be'],
            'YouTube',
            'url'
        );
    }
}
