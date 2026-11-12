<?php

declare(strict_types=1);

namespace App\Exceptions;

final class NotFoundException extends QubiqException
{
    public function __construct(string $message = 'Resource not found.')
    {
        parent::__construct($message, 404, 'not_found');
    }
}
