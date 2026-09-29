<?php
declare(strict_types=1);

namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\UiFormSchemaRepositoryInterface;

final class SqlUiFormSchemaRepository implements UiFormSchemaRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function listSchemas(): array
    {
        return $this->db->fetchAll(
            "SELECT schema_ref, form_key, title, description, entity_type, version, status, created_at, updated_at
             FROM ui_form_schemas
             WHERE status = 'ACTIVE'
             ORDER BY entity_type ASC, form_key ASC"
        );
    }

    public function findSchemaByKey(string $formKey): ?array
    {
        return $this->db->fetchOne(
            "SELECT schema_ref, form_key, title, description, entity_type, version, status, created_at, updated_at
             FROM ui_form_schemas
             WHERE form_key = ? AND status = 'ACTIVE'
             LIMIT 1",
            [$formKey]
        );
    }

    public function getFieldsByFormKey(string $formKey): array
    {
        return $this->db->fetchAll(
            "SELECT field_ref, form_key, field_name, label, field_type, placeholder, default_value,
                    is_required, is_readonly, validation_rules_json, options_source_type,
                    options_source_key, options_json, step_number, grid_width, sort_order, is_active
             FROM ui_form_fields
             WHERE form_key = ? AND is_active = 1
             ORDER BY step_number ASC, sort_order ASC, id ASC",
            [$formKey]
        );
    }
}
