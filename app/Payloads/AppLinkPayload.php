<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Exceptions\ValidationException;
use App\Validators\UrlSafetyValidator;

final class AppLinkPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'applink';
    }

    public function label(): string
    {
        return 'App Link';
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
        return 'Encodes an Apple App Store link, Google Play Store link, or custom app deep link.';
    }

    public function sampleData(): array
    {
        return [
            'platform' => 'appstore',
            'url' => 'https://apps.apple.com/us/app/github/id1477376905',
        ];
    }

    public function encode(array $input): string
    {
        $platform = isset($input['platform']) && is_scalar($input['platform'])
            ? strtolower(trim((string) $input['platform']))
            : 'generic';
        $raw = isset($input['url']) && is_scalar($input['url']) ? trim((string) $input['url']) : '';

        return match ($platform) {
            'appstore', 'ios' => UrlSafetyValidator::validateDomainScopedUrl(
                $raw,
                ['apps.apple.com', 'itunes.apple.com'],
                'Apple App Store',
                'url'
            ),
            'playstore', 'android' => UrlSafetyValidator::validateDomainScopedUrl(
                $raw,
                ['play.google.com'],
                'Google Play Store',
                'url'
            ),
            'generic', 'deeplink' => str_starts_with(strtolower($raw), 'http://') || str_starts_with(strtolower($raw), 'https://') || !str_contains($raw, '://')
                ? UrlSafetyValidator::normalizeWebUrl($raw, 'url', true)
                : UrlSafetyValidator::validateSafeCustomUri($raw, 'url'),
            default => throw new ValidationException('Invalid App Link platform.', [
                'platform' => 'Choose appstore, playstore, or generic.',
            ]),
        };
    }
}
