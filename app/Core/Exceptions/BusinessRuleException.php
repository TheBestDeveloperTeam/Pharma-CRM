<?php
declare(strict_types=1);
namespace App\Core\Exceptions;

class BusinessRuleException extends AppException
{
    public function __construct(string $code, string $msg = '')
    {
        parent::__construct($code, $msg ?: $code, [], 422);
    }
}
