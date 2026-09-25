<?php
declare(strict_types=1);
namespace App\Core\Exceptions;

class NotFoundException extends AppException
{
    public function __construct(string $codeOrMessage = 'NOT_FOUND', ?string $message = null)
    {
        if ($message === null) {
            parent::__construct('NOT_FOUND', $codeOrMessage, [], 404);
            return;
        }
        parent::__construct($codeOrMessage, $message, [], 404);
    }
}
