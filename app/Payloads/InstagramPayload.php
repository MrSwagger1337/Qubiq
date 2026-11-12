<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Validators\UrlSafetyValidator;

final class InstagramPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'instagram';
    }

    public function label(): string
    {
        return 'Instagram';
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
        return 'Standard HTTPS URL QR code scoped to a validated Instagram profile link.';
    }

    public function sampleData(): array
    {
        return [
            'url' => 'https://www.instagram.com/github/',
        ];
    }

    public function encode(array $input): string
    {
        $raw = isset($input['url']) && is_scalar($input['url']) ? trim((string) $input['url']) : '';
        if ($raw !== '' && !str_contains($raw, '/') && preg_match('/^@?([A-Za-z0-9._]{1,30})$/', $raw, $m) === 1 && !str_starts_with(strtolower($raw), 'instagram.com')) {
            $raw = 'https://www.instagram.com/' . $m[1] . '/';
        }

        return UrlSafetyValidator::validateDomainScopedUrl(
            $raw,
            ['instagram.com', 'instagr.am'],
            'Instagram',
            'url'
        );
    }
}
