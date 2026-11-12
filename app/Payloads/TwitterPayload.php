<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Validators\UrlSafetyValidator;

final class TwitterPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'twitter';
    }

    public function label(): string
    {
        return 'X / Twitter';
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
        return 'Standard HTTPS URL QR code scoped to a validated X (Twitter) profile or post link.';
    }

    public function sampleData(): array
    {
        return [
            'url' => 'https://x.com/github',
        ];
    }

    public function encode(array $input): string
    {
        $raw = isset($input['url']) && is_scalar($input['url']) ? trim((string) $input['url']) : '';
        if (preg_match('/^@?([A-Za-z0-9_]{1,15})$/', $raw, $m) === 1 && !str_contains($raw, '.')) {
            $raw = 'https://x.com/' . $m[1];
        }

        return UrlSafetyValidator::validateDomainScopedUrl(
            $raw,
            ['x.com', 'twitter.com'],
            'X / Twitter',
            'url'
        );
    }
}
