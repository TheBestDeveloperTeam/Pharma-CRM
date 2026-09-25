<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\ValidationException;

final class QueryParams
{
    /** @var array<int,string> */
    private const SORT_DIRECTIONS = ['asc', 'desc'];

    /** @param array<int,string> $allowedSorts */
    public static function fromRequest(Request $request, array $allowedSorts = []): array
    {
        $page = self::integer($request->query('page'), ApiContract::DEFAULT_PAGE);
        $perPage = self::integer($request->query('per_page'), ApiContract::DEFAULT_PER_PAGE);

        if ($page < 1 || $perPage < 1 || $perPage > ApiContract::MAX_PER_PAGE) {
            throw new ValidationException(
                ApiErrorCodes::INVALID_PAGINATION,
                'page must be at least 1 and per_page must be between 1 and 100.',
                ['page' => ['Must be at least 1.'], 'per_page' => ['Must be between 1 and 100.']]
            );
        }

        $sortBy = trim((string)$request->query('sort_by', ''));
        $sortDir = strtolower(trim((string)$request->query('sort_dir', 'asc')));
        if ($sortBy !== '' && $allowedSorts !== [] && !in_array($sortBy, $allowedSorts, true)) {
            throw new ValidationException(ApiErrorCodes::INVALID_SORT, 'sort_by is not supported.', ['sort_by' => ['Unsupported sort field.']]);
        }
        if (!in_array($sortDir, self::SORT_DIRECTIONS, true)) {
            throw new ValidationException(ApiErrorCodes::INVALID_SORT, 'sort_dir must be asc or desc.', ['sort_dir' => ['Use asc or desc.']]);
        }

        return [
            'page' => $page,
            'per_page' => $perPage,
            'search' => trim((string)$request->query('search', '')),
            'status' => self::nullableString($request->query('status')),
            'date_from' => self::date($request->query('date_from'), 'date_from'),
            'date_to' => self::date($request->query('date_to'), 'date_to'),
            'sort_by' => $sortBy !== '' ? $sortBy : null,
            'sort_dir' => $sortDir,
        ];
    }

    private static function integer(mixed $value, int $default): int
    {
        if ($value === null || $value === '') return $default;
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new ValidationException(ApiErrorCodes::INVALID_PAGINATION, 'Pagination values must be integers.');
        }
        return (int)$value;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));
        return $value === '' ? null : $value;
    }

    private static function date(mixed $value, string $field): ?string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') return null;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw new ValidationException(ApiErrorCodes::INVALID_DATE, "$field must use YYYY-MM-DD.", [$field => ['Use YYYY-MM-DD.']]);
        }
        return $value;
    }
}
