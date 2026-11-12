<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Exceptions\ValidationException;
use App\Validators\UrlSafetyValidator;

final class MeCardPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'mecard';
    }

    public function label(): string
    {
        return 'MeCard';
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
        return 'Generates a compact NTT DoCoMo MECARD contact payload with smaller QR matrix density than vCard.';
    }

    public function sampleData(): array
    {
        return [
            'name' => 'Rostova, Elena',
            'phone' => '+14155550199',
            'email' => 'elena@example.com',
            'address' => '548 Market St, San Francisco, CA',
            'website' => 'https://example.com',
        ];
    }

    public function encode(array $input): string
    {
        $name = isset($input['name']) && is_scalar($input['name']) ? trim((string) $input['name']) : '';
        $phone = isset($input['phone']) && is_scalar($input['phone']) ? trim((string) $input['phone']) : '';
        $email = isset($input['email']) && is_scalar($input['email']) ? trim((string) $input['email']) : '';
        $address = isset($input['address']) && is_scalar($input['address']) ? trim((string) $input['address']) : '';
        $website = isset($input['website']) && is_scalar($input['website']) ? trim((string) $input['website']) : '';

        if ($name === '') {
            throw new ValidationException('Contact name is required for MeCard.', [
                'name' => 'Please enter a contact name.',
            ]);
        }

        $fields = ['N:' . self::escape($name) . ';'];

        if ($phone !== '') {
            $normalizedPhone = PhonePayload::normalizePhone($phone, 'phone');
            $fields[] = 'TEL:' . self::escape($normalizedPhone) . ';';
        }
        if ($email !== '') {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new ValidationException('Invalid MeCard email address.', [
                    'email' => 'Please enter a valid email address.',
                ]);
            }
            $fields[] = 'EMAIL:' . self::escape($email) . ';';
        }
        if ($address !== '') {
            $fields[] = 'ADR:' . self::escape($address) . ';';
        }
        if ($website !== '') {
            $normalizedUrl = UrlSafetyValidator::normalizeWebUrl($website, 'website', true);
            $fields[] = 'URL:' . self::escape($normalizedUrl) . ';';
        }

        return 'MECARD:' . implode('', $fields) . ';';
    }

    public static function escape(string $value): string
    {
        $clean = str_replace(["\r\n", "\r", "\n"], ' ', $value);

        return strtr($clean, [
            '\\' => '\\\\',
            ';' => '\;',
            ':' => '\:',
            ',' => '\,',
            '"' => '\"',
        ]);
    }
}
