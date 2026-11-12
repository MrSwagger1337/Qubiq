<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ForbiddenException extends QubiqException
{
    public function __construct(string $message = 'Action is forbidden.')
    {
        parent::__construct($message, 403, 'forbidden');
    }
}
