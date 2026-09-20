<?php
declare(strict_types=1);
namespace App\Core\Exceptions;

class ForbiddenException extends AppException
{
    public function __construct(string $code = 'FORBIDDEN', string $msg = 'Access denied')
    {
        parent::__construct($code, $msg, [], 403);
    }
}
