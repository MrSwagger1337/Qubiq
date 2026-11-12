<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Exceptions\ValidationException;

final class EmailPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'email';
    }

    public function label(): string
    {
        return 'Email';
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
        return 'Generates a standards-compliant RFC 6068 mailto: URI with optional subject and body.';
    }

    public function sampleData(): array
    {
        return [
            'email' => 'hello@example.com',
            'subject' => 'Inquiry via Qubiq QR',
            'body' => 'Hello team, I scanned your QR code and would like to get in touch.',
        ];
    }

    public function encode(array $input): string
    {
        $email = trim(isset($input['email']) && is_scalar($input['email']) ? (string) $input['email'] : '');
        $subject = trim(isset($input['subject']) && is_scalar($input['subject']) ? (string) $input['subject'] : '');
        $body = trim(isset($input['body']) && is_scalar($input['body']) ? (string) $input['body'] : '');

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Please enter a valid recipient email address.', [
                'email' => 'Valid email address is required (e.g. name@example.com).',
            ]);
        }

        $params = [];
        if ($subject !== '') {
            $params[] = 'subject=' . rawurlencode($subject);
        }
        if ($body !== '') {
            $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
            $params[] = 'body=' . rawurlencode($normalizedBody);
        }

        $uri = 'mailto:' . $email;
        if ($params !== []) {
            $uri .= '?' . implode('&', $params);
        }

        return $uri;
    }
}
