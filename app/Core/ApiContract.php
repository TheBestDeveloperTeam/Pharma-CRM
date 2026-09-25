<?php
declare(strict_types=1);

namespace App\Core;

final class ApiContract
{
    public const DEFAULT_PAGE = 1;
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;
    public const DATE_FORMAT = 'Y-m-d';
    public const DATETIME_FORMAT = 'c';
    public const IDEMPOTENCY_HEADER = 'Idempotency-Key';

    /** @var array<int,string> */
    public const ORDER_STATUSES = [
        'DRAFT', 'SUBMITTED', 'CONFIRMED', 'PROCESSING',
        'DISPATCHED', 'DELIVERED', 'CANCELLED',
    ];

    /** @var array<int,string> */
    public const SCOPES = ['ALL', 'TERRITORY', 'TEAM', 'OWN', 'NONE'];

    /** @var array<int,string> */
    public const USER_STATUSES = ['ACTIVE', 'INACTIVE'];

    /** @var array<int,string> */
    public const ROLE_STATUSES = ['ACTIVE', 'INACTIVE'];
}
