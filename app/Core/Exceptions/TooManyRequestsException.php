<?php
declare(strict_types=1);
namespace App\Core\Exceptions;

class TooManyRequestsException extends AppException
{
    public function __construct(int $retryAfter = 60)
    {
        parent::__construct('RATE_LIMIT_EXCEEDED', 'Too many requests', ['retry_after' => $retryAfter], 429);
    }
}
