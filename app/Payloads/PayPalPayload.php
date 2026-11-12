<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Validators\UrlSafetyValidator;

final class PayPalPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return 'PayPal';
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
        return 'Standard HTTPS URL QR code scoped to a validated PayPal or PayPal.me payment link.';
    }

    public function sampleData(): array
    {
        return [
            'url' => 'https://www.paypal.me/example/10',
        ];
    }

    public function encode(array $input): string
    {
        $raw = isset($input['url']) && is_scalar($input['url']) ? trim((string) $input['url']) : '';

        return UrlSafetyValidator::validateDomainScopedUrl(
            $raw,
            ['paypal.com', 'paypal.me'],
            'PayPal',
            'url'
        );
    }
}
