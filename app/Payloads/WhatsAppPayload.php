<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Exceptions\ValidationException;

final class WhatsAppPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'whatsapp';
    }

    public function label(): string
    {
        return 'WhatsApp';
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
        return 'Generates an official https://wa.me/ click-to-chat deep link with optional prefilled message.';
    }

    public function sampleData(): array
    {
        return [
            'phone' => '+14155552671',
            'message' => 'Hello! I scanned your Qubiq QR code.',
        ];
    }

    public function encode(array $input): string
    {
        $rawPhone = isset($input['phone']) && is_scalar($input['phone']) ? trim((string) $input['phone']) : '';
        $message = isset($input['message']) && is_scalar($input['message']) ? trim((string) $input['message']) : '';

        // Official wa.me format requires international digits only (no +, 00, spaces, or dashes)
        $digits = preg_replace('/[^\d]/', '', $rawPhone) ?? '';
        $digits = ltrim($digits, '0');

        if (strlen($digits) < 6 || strlen($digits) > 15) {
            throw new ValidationException('WhatsApp phone number must include country code (6–15 digits).', [
                'phone' => 'Enter a full international phone number (e.g. +14155552671).',
            ]);
        }

        $url = 'https://wa.me/' . $digits;
        if ($message !== '') {
            $normalizedMessage = str_replace(["\r\n", "\r"], "\n", $message);
            $url .= '?text=' . rawurlencode($normalizedMessage);
        }

        return $url;
    }
}
