<?php
declare(strict_types=1);
namespace App\Core\Exceptions;

abstract class AppException extends \RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message = '',
        private readonly array $errorFields = [],
        private readonly int $httpStatus = 500,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?: $errorCode, $httpStatus, $previous);
    }

    public function statusCode(): int   { return $this->httpStatus; }
    public function errorCode(): string { return $this->errorCode; }
    public function fields(): array     { return $this->errorFields; }
}
