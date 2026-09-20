<?php
declare(strict_types=1);
namespace App\Core\Exceptions;

class NotFoundException extends AppException
{
    public function __construct(string $msg = 'Resource not found')
    {
        parent::__construct('NOT_FOUND', $msg, [], 404);
    }
}
