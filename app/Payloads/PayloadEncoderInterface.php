<?php

declare(strict_types=1);

namespace App\Payloads;

interface PayloadEncoderInterface
{
    public function type(): string;

    public function label(): string;

    /**
     * 'primary' (shown in top visible tab bar) or 'more' (shown in searchable More menu).
     */
    public function category(): string;

    /**
     * True when the QR payload is simply a validated web/deep-link URL rather than a dedicated non-HTTP QR schema.
     */
    public function isUrlWrapper(): bool;

    public function description(): string;

    /**
     * @return array<string, mixed>
     */
    public function sampleData(): array;

    /**
     * @param array<string, mixed> $input
     */
    public function encode(array $input): string;
}
