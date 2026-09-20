<?php
declare(strict_types=1);
namespace App\Core\Exceptions;

class ValidationException extends AppException
{
    public function __construct(string $code, string $msg = '', array $fields = [])
    {
        parent::__construct($code, $msg ?: $code, $fields, 422);
    }
}
