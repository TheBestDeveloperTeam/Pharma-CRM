<?php
declare(strict_types=1);

namespace App\Repositories\Contracts;

interface UiFormSchemaRepositoryInterface
{
    /**
     * List all active form schemas.
     *
     * @return array<array<string,mixed>>
     */
    public function listSchemas(): array;

    /**
     * Get schema by form_key.
     *
     * @param string $formKey
     * @return array<string,mixed>|null
     */
    public function findSchemaByKey(string $formKey): ?array;

    /**
     * Get all active fields for a given form_key ordered by step_number and sort_order.
     *
     * @param string $formKey
     * @return array<array<string,mixed>>
     */
    public function getFieldsByFormKey(string $formKey): array;
}
