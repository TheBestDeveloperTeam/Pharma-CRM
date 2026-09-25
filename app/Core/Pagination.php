<?php
declare(strict_types=1);

namespace App\Core;

final class Pagination
{
    /** @return array{page:int,per_page:int,total:int,total_pages:int} */
    public static function meta(int $page, int $perPage, int $total): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $total === 0 ? 0 : (int)ceil($total / $perPage),
        ];
    }
}
