<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class AuthenticationException extends AppException
{
    public function __construct(string $message = 'Authentication required.', string $code = 'UNAUTHENTICATED')
    {
        parent::__construct($message, $code, 401);
    }
}
