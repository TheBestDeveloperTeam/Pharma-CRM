<?php
declare(strict_types=1);

namespace App\Domain\Masters;

use App\Core\Exceptions\NotFoundException;
use App\Repositories\Contracts\UiFormSchemaRepositoryInterface;
use App\Repositories\Contracts\CatalogMasterRepositoryInterface;

final class UiFormSchemaService
{
    public function __construct(
        private UiFormSchemaRepositoryInterface $schemas,
        private CatalogMasterRepositoryInterface $catalogMasters
    ) {}

    /**
     * Get all active form schemas overview.
     *
     * @return array<array<string,mixed>>
     */
    public function list(): array
    {
        return $this->schemas->listSchemas();
    }

    /**
     * Get full form schema with fields and resolved option metadata.
     *
     * @param string $formKey
     * @param string|null $franchiseRef
     * @param bool $resolveOptions Whether to inline catalog master options
     * @return array<string,mixed>
     */
    public function getSchema(string $formKey, ?string $franchiseRef = null, bool $resolveOptions = true): array
    {
        $schema = $this->schemas->findSchemaByKey($formKey);
        if (!$schema) {
            throw new NotFoundException('FORM_SCHEMA_NOT_FOUND', "UI form schema '{$formKey}' not found.");
        }

        $rawFields = $this->schemas->getFieldsByFormKey($formKey);
        $fields = [];

        foreach ($rawFields as $field) {
            $validation = !empty($field['validation_rules_json'])
                ? json_decode((string)$field['validation_rules_json'], true)
                : null;

            $options = !empty($field['options_json'])
                ? json_decode((string)$field['options_json'], true)
                : null;

            // In-line dynamic options from catalog_master_values if requested
            if ($resolveOptions && $field['options_source_type'] === 'CATALOG_MASTER' && !empty($field['options_source_key']) && $franchiseRef) {
                $masterList = $this->catalogMasters->list($franchiseRef, (string)$field['options_source_key'], ['status' => 'ACTIVE'], 1, 100);
                if (!empty($masterList['data'])) {
                    $options = array_map(fn($item) => [
                        'code'  => $item['name'],
                        'label' => $item['name'],
                        'description' => $item['description'] ?? null,
                    ], $masterList['data']);
                }
            }

            $fields[] = [
                'field_ref'           => $field['field_ref'],
                'field_name'          => $field['field_name'],
                'label'               => $field['label'],
                'field_type'          => $field['field_type'],
                'placeholder'         => $field['placeholder'],
                'default_value'       => $field['default_value'],
                'is_required'         => (bool)$field['is_required'],
                'is_readonly'         => (bool)$field['is_readonly'],
                'validation_rules'    => $validation,
                'options_source_type' => $field['options_source_type'],
                'options_source_key'  => $field['options_source_key'],
                'options'             => $options,
                'step_number'         => (int)$field['step_number'],
                'grid_width'          => (int)$field['grid_width'],
                'sort_order'          => (int)$field['sort_order'],
            ];
        }

        $schema['fields'] = $fields;
        return $schema;
    }
}
