<?php

declare(strict_types=1);

namespace App\Exceptions;

final class CapacityOverflowException extends QubiqException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 422, 'qr_capacity_exceeded');
    }
}
