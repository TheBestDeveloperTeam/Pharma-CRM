<?php
declare(strict_types=1);
namespace App\Core\Exceptions;

class UnauthorizedException extends AppException
{
    public function __construct(string $code = 'UNAUTHORIZED', string $msg = 'Authentication required')
    {
        parent::__construct($code, $msg, [], 401);
    }
}
