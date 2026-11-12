<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ValidationException extends QubiqException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors = []
    ) {
        parent::__construct($message, 422, 'validation_error');
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return ['fields' => $this->errors];
    }
}
