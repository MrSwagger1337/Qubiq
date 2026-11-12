<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Validators\UrlSafetyValidator;

final class UrlPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'url';
    }

    public function label(): string
    {
        return 'URL';
    }

    public function category(): string
    {
        return 'primary';
    }

    public function isUrlWrapper(): bool
    {
        return true;
    }

    public function description(): string
    {
        return 'Encodes a standard HTTP or HTTPS website link directly into the QR code.';
    }

    public function sampleData(): array
    {
        return [
            'url' => 'https://qubiq.herculesprojects.net',
        ];
    }

    public function encode(array $input): string
    {
        $raw = isset($input['url']) && is_scalar($input['url']) ? (string) $input['url'] : '';

        return UrlSafetyValidator::normalizeWebUrl($raw, 'url', true);
    }
}
