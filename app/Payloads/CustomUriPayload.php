<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Validators\UrlSafetyValidator;

final class CustomUriPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'custom_uri';
    }

    public function label(): string
    {
        return 'Custom URI';
    }

    public function category(): string
    {
        return 'more';
    }

    public function isUrlWrapper(): bool
    {
        return false;
    }

    public function description(): string
    {
        return 'Encodes safe custom protocol URIs (e.g. spotify:, slack://, otpauth://) while blocking executable schemes.';
    }

    public function sampleData(): array
    {
        return [
            'uri' => 'spotify:track:4uLU6hMCjMI75M1A2tKUQC',
        ];
    }

    public function encode(array $input): string
    {
        $raw = isset($input['uri']) && is_scalar($input['uri']) ? (string) $input['uri'] : '';

        return UrlSafetyValidator::validateSafeCustomUri($raw, 'uri');
    }
}
