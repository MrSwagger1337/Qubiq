<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Exceptions\ValidationException;

final class TextPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return 'Text';
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
        return 'Encodes multiline UTF-8 plain text, notes, serial codes, or emoji offline.';
    }

    public function sampleData(): array
    {
        return [
            'text' => "Welcome to Qubiq ✨\n100% Free & Privacy-First QR Code Generator.",
        ];
    }

    public function encode(array $input): string
    {
        $text = isset($input['text']) && is_scalar($input['text']) ? (string) $input['text'] : '';
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new ValidationException('Text must be valid UTF-8.', [
                'text' => 'Input contains invalid UTF-8 byte sequences.',
            ]);
        }

        // Normalize CRLF to LF while preserving intentional multiline whitespace
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        if (trim($normalized) === '') {
            throw new ValidationException('Plain text content cannot be empty.', [
                'text' => 'Please enter text to encode.',
            ]);
        }

        return $normalized;
    }
}
