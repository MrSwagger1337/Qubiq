<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Validators\UrlSafetyValidator;

final class FacebookPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'facebook';
    }

    public function label(): string
    {
        return 'Facebook';
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
        return 'Standard HTTPS URL QR code scoped to a validated Facebook page or profile link.';
    }

    public function sampleData(): array
    {
        return [
            'url' => 'https://www.facebook.com/github',
        ];
    }

    public function encode(array $input): string
    {
        $raw = isset($input['url']) && is_scalar($input['url']) ? trim((string) $input['url']) : '';

        return UrlSafetyValidator::validateDomainScopedUrl(
            $raw,
            ['facebook.com', 'fb.com', 'fb.me'],
            'Facebook',
            'url'
        );
    }
}
