<?php

declare(strict_types=1);

namespace App\Exceptions;

final class RateLimitException extends QubiqException
{
    public function __construct(
        string $message,
        private readonly int $retryAfterSeconds = 60
    ) {
        parent::__construct($message, 429, 'rate_limit_exceeded');
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return ['retry_after' => $this->retryAfterSeconds];
    }
}
