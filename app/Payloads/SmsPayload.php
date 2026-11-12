<?php

declare(strict_types=1);

namespace App\Payloads;

final class SmsPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'sms';
    }

    public function label(): string
    {
        return 'SMS';
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
        return 'Generates a standards-compliant RFC 5724 sms: URI with optional prefilled message.';
    }

    public function sampleData(): array
    {
        return [
            'phone' => '+14155552671',
            'message' => 'Hi! Please send me more details.',
        ];
    }

    public function encode(array $input): string
    {
        $rawPhone = isset($input['phone']) && is_scalar($input['phone']) ? (string) $input['phone'] : '';
        $phone = PhonePayload::normalizePhone($rawPhone, 'phone');
        $message = trim(isset($input['message']) && is_scalar($input['message']) ? (string) $input['message'] : '');

        if ($message === '') {
            return 'sms:' . $phone;
        }

        $normalizedMessage = str_replace(["\r\n", "\r"], "\n", $message);

        return 'sms:' . $phone . '?body=' . rawurlencode($normalizedMessage);
    }
}
