<?php

declare(strict_types=1);

namespace App\Exceptions;

final class UnsupportedCapabilityException extends QubiqException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 422, 'unsupported_capability');
    }
}
