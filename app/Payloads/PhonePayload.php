<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Exceptions\ValidationException;

final class PhonePayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'phone';
    }

    public function label(): string
    {
        return 'Phone';
    }

    public function category(): string
    {
        return 'primary';
    }

    public function isUrlWrapper(): bool
    {
        return false;
    }

    public function description(): string
    {
        return 'Generates an RFC 3966 tel: URI for one-tap calling on mobile devices.';
    }

    public function sampleData(): array
    {
        return [
            'phone' => '+14155552671',
        ];
    }

    public function encode(array $input): string
    {
        $raw = isset($input['phone']) && is_scalar($input['phone']) ? (string) $input['phone'] : '';
        $normalized = self::normalizePhone($raw, 'phone');

        return 'tel:' . $normalized;
    }

    public static function normalizePhone(string $raw, string $field = 'phone'): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            throw new ValidationException('Phone number is required.', [
                $field => 'Please enter a valid phone number.',
            ]);
        }

        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/[^\d]/', '', $trimmed) ?? '';

        if (strlen($digits) < 3 || strlen($digits) > 18) {
            throw new ValidationException('Phone number must contain between 3 and 18 digits.', [
                $field => 'Enter a valid local or international phone number (e.g. +14155552671).',
            ]);
        }

        return ($hasPlus ? '+' : '') . $digits;
    }
}
