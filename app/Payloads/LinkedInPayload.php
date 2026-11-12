<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Validators\UrlSafetyValidator;

final class LinkedInPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'linkedin';
    }

    public function label(): string
    {
        return 'LinkedIn';
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
        return 'Standard HTTPS URL QR code scoped to a validated LinkedIn profile or company link.';
    }

    public function sampleData(): array
    {
        return [
            'url' => 'https://www.linkedin.com/company/github/',
        ];
    }

    public function encode(array $input): string
    {
        $raw = isset($input['url']) && is_scalar($input['url']) ? trim((string) $input['url']) : '';

        return UrlSafetyValidator::validateDomainScopedUrl(
            $raw,
            ['linkedin.com', 'lnkd.in'],
            'LinkedIn',
            'url'
        );
    }
}
