<?php
declare(strict_types=1);

namespace App\Domain\Masters;

use App\Core\Exceptions\{NotFoundException, ValidationException};
use App\Repositories\Contracts\UiFormSchemaRepositoryInterface;

/**
 * Dynamic Form Validator for Zero-Local-Data Architecture.
 * Validates arbitrary submission payloads directly against validation rules
 * and constraints defined in the ui_form_fields database table.
 */
final class DynamicFormValidator
{
    public function __construct(
        private UiFormSchemaRepositoryInterface $schemas
    ) {}

    /**
     * Validate an arbitrary payload against rules defined in the database for the given form key.
     *
     * @param string $formKey
     * @param array<string, mixed> $payload
     * @param int|null $stepNumber Optional step number filter for multi-step wizards
     * @return array{valid: bool, errors: array<string, list<string>>, validated_data: array<string, mixed>}
     */
    public function validate(string $formKey, array $payload, ?int $stepNumber = null): array
    {
        $schema = $this->schemas->findSchemaByKey($formKey);
        if (!$schema) {
            throw new NotFoundException('FORM_SCHEMA_NOT_FOUND', "UI form schema '{$formKey}' not found.");
        }

        $fields = $this->schemas->getFieldsByFormKey($formKey);
        $errors = [];
        $validatedData = [];

        foreach ($fields as $field) {
            $fieldName = (string)$field['field_name'];
            $fieldStep = (int)($field['step_number'] ?? 1);

            // If a specific step is requested, ignore fields from other steps
            if ($stepNumber !== null && $fieldStep !== $stepNumber) {
                continue;
            }

            $rules = !empty($field['validation_rules_json'])
                ? json_decode((string)$field['validation_rules_json'], true)
                : [];
            if (!is_array($rules)) {
                $rules = [];
            }

            $isRequired = (bool)($field['is_required'] ?? false) || !empty($rules['required']);
            $hasKey = array_key_exists($fieldName, $payload);
            $val = $payload[$fieldName] ?? null;

            $customMessage = $rules['message'] ?? null;

            // 1. Required check
            if ($isRequired) {
                $isEmpty = $val === null || $val === '' || (is_array($val) && empty($val));
                if (!$hasKey || $isEmpty) {
                    $errors[$fieldName][] = $customMessage ?: "The {$field['label']} field is required.";
                    continue; // Skip further checks if empty
                }
            }

            // If value is null or empty string and not required, skip further checks
            if ($val === null || $val === '') {
                if ($hasKey) {
                    $validatedData[$fieldName] = $val;
                }
                continue;
            }

            // 2. Field Type specific checks
            $fieldType = (string)($field['field_type'] ?? 'text');

            if ($fieldType === 'number' || $fieldType === 'currency') {
                if (!is_numeric($val)) {
                    $errors[$fieldName][] = $customMessage ?: "The {$field['label']} must be a valid number.";
                } else {
                    $numVal = (float)$val;
                    if (isset($rules['min']) && $numVal < (float)$rules['min']) {
                        $errors[$fieldName][] = $customMessage ?: "The {$field['label']} must be at least {$rules['min']}.";
                    }
                    if (isset($rules['max']) && $numVal > (float)$rules['max']) {
                        $errors[$fieldName][] = $customMessage ?: "The {$field['label']} may not be greater than {$rules['max']}.";
                    }
                }
            } elseif ($fieldType === 'email' || !empty($rules['email'])) {
                if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $errors[$fieldName][] = $customMessage ?: "The {$field['label']} must be a valid email address.";
                }
            } elseif ($fieldType === 'date' || !empty($rules['date'])) {
                if (strtotime((string)$val) === false) {
                    $errors[$fieldName][] = $customMessage ?: "The {$field['label']} must be a valid date.";
                }
            } else {
                // String-based min/max
                if (is_string($val)) {
                    $strLen = mb_strlen($val);
                    if (isset($rules['min']) && $strLen < (int)$rules['min']) {
                        $errors[$fieldName][] = $customMessage ?: "The {$field['label']} must be at least {$rules['min']} characters.";
                    }
                    if (isset($rules['max']) && $strLen > (int)$rules['max']) {
                        $errors[$fieldName][] = $customMessage ?: "The {$field['label']} may not be greater than {$rules['max']} characters.";
                    }
                }
            }

            // 3. Regex Pattern check
            if (!empty($rules['pattern']) && is_string($val) && $val !== '') {
                $pattern = '/' . str_replace('/', '\/', (string)$rules['pattern']) . '/';
                if (!@preg_match($pattern, $val)) {
                    $errors[$fieldName][] = $customMessage ?: "The {$field['label']} format is invalid.";
                }
            }

            // 4. URL check
            if (!empty($rules['url']) && is_string($val) && $val !== '') {
                if (!filter_var($val, FILTER_VALIDATE_URL)) {
                    $errors[$fieldName][] = $customMessage ?: "The {$field['label']} must be a valid URL.";
                }
            }

            // 5. In-list check
            if (!empty($rules['in']) && is_array($rules['in'])) {
                if (!in_array($val, $rules['in'], true)) {
                    $errors[$fieldName][] = $customMessage ?: "The selected {$field['label']} is invalid.";
                }
            }

            if (!isset($errors[$fieldName])) {
                $validatedData[$fieldName] = $val;
            }
        }

        return [
            'valid'          => empty($errors),
            'errors'         => $errors,
            'validated_data' => $validatedData,
        ];
    }

    /**
     * Validate and throw ValidationException if errors exist.
     *
     * @param string $formKey
     * @param array<string, mixed> $payload
     * @param int|null $stepNumber
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validateOrFail(string $formKey, array $payload, ?int $stepNumber = null): array
    {
        $result = $this->validate($formKey, $payload, $stepNumber);
        if (!$result['valid']) {
            throw new ValidationException(
                'FORM_VALIDATION_FAILED',
                "Validation failed for form '{$formKey}'.",
                $result['errors']
            );
        }
        return $result['validated_data'];
    }
}
