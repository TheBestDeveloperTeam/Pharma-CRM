<?php
declare(strict_types=1);

namespace App\Core;

final class ApiErrorCodes
{
    public const VALIDATION_FAILED = 'VALIDATION_FAILED';
    public const INVALID_REFERENCE = 'INVALID_REFERENCE';
    public const INVALID_ENUM = 'INVALID_ENUM';
    public const INVALID_DATE = 'INVALID_DATE';
    public const INVALID_PAGINATION = 'INVALID_PAGINATION';
    public const INVALID_SORT = 'INVALID_SORT';
    public const UNAUTHORIZED = 'UNAUTHORIZED';
    public const FORBIDDEN = 'FORBIDDEN';
    public const PERMISSION_DENIED = 'PERMISSION_DENIED';
    public const SCOPE_DENIED = 'SCOPE_DENIED';
    public const INACTIVE_USER = 'USER_INACTIVE';
    public const NOT_FOUND = 'NOT_FOUND';
    public const CONFLICT = 'CONFLICT';
    public const DUPLICATE = 'DUPLICATE';
    public const INVALID_STATE_TRANSITION = 'INVALID_STATE_TRANSITION';
    public const TENANT_VIOLATION = 'TENANT_MISMATCH';
    public const IDEMPOTENCY_CONFLICT = 'IDEMPOTENCY_MISMATCH';
    public const IDEMPOTENCY_IN_PROGRESS = 'REQUEST_IN_PROGRESS';
    public const BUSINESS_RULE = 'BUSINESS_RULE_VIOLATION';
    public const INTERNAL_ERROR = 'INTERNAL_ERROR';
}
