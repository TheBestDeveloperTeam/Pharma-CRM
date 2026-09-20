<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class AuthorizationException extends AppException
{
    public function __construct(string $message = 'Forbidden.', string $code = 'FORBIDDEN')
    {
        parent::__construct($message, $code, 403);
    }
}
