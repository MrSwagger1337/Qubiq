<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Exceptions\ValidationException;
use App\Validators\UrlSafetyValidator;

final class TelegramPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'telegram';
    }

    public function label(): string
    {
        return 'Telegram';
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
        return 'Generates an official https://t.me/ link from a Telegram @username or public channel URL.';
    }

    public function sampleData(): array
    {
        return [
            'target' => '@telegram',
        ];
    }

    public function encode(array $input): string
    {
        $raw = isset($input['target']) && is_scalar($input['target']) ? trim((string) $input['target']) : '';
        if ($raw === '') {
            throw new ValidationException('Telegram username or public link is required.', [
                'target' => 'Enter a @username (5–32 chars) or https://t.me/... link.',
            ]);
        }

        if (!str_contains($raw, '/') && !str_contains($raw, '.')) {
            $handle = ltrim($raw, '@');
            if (preg_match('/^[A-Za-z][A-Za-z0-9_]{4,31}$/', $handle) !== 1) {
                throw new ValidationException('Invalid Telegram username.', [
                    'target' => 'Telegram usernames must be 5–32 letters, numbers, or underscores.',
                ]);
            }

            return 'https://t.me/' . $handle;
        }

        return UrlSafetyValidator::validateDomainScopedUrl(
            $raw,
            ['t.me', 'telegram.me', 'telegram.org'],
            'Telegram',
            'target'
        );
    }
}
