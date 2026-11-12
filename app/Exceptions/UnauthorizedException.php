<?php

declare(strict_types=1);

namespace App\Exceptions;

final class UnauthorizedException extends QubiqException
{
    public function __construct(string $message = 'Unauthorized.')
    {
        parent::__construct($message, 401, 'unauthorized');
    }
}
