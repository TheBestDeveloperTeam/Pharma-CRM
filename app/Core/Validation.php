<?php
declare(strict_types=1);
namespace App\Core;

use App\Core\Exceptions\ValidationException;

final class Validation
{
    private array $errors = [];

    /**
     * Validate $data against $rules.
     * Rules syntax: 'required|string|min:3|max:191'
     * Throws ValidationException with field map on failure.
     */
    public static function validate(array $data, array $rules): array
    {
        $v = new self();
        $clean = [];

        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                [$ruleName, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $v->applyRule($field, $value, $ruleName, $param);
            }

            if (!isset($v->errors[$field])) {
                $clean[$field] = $value;
            }
        }

        if (!empty($v->errors)) {
            throw new ValidationException(
                'VALIDATION_FAILED',
                'Validation failed. Please check the fields.',
                $v->errors
            );
        }

        return $clean;
    }

    /**
     * Same as validate() but returns [bool $passes, array $errors, array $clean] instead of throwing.
     */
    public static function check(array $data, array $rules): array
    {
        try {
            $clean = self::validate($data, $rules);
            return [true, [], $clean];
        } catch (ValidationException $e) {
            return [false, $e->fields(), []];
        }
    }

    private function applyRule(string $field, mixed &$value, string $rule, ?string $param): void
    {
        switch ($rule) {

            case 'required':
                if ($value === null || $value === '' || (is_array($value) && count($value) === 0)) {
                    $this->errors[$field][] = 'This field is required.';
                }
                break;

            case 'nullable':
                // No-op — allows null to pass required check
                break;

            case 'string':
                if ($value !== null && !is_string($value)) {
                    $this->errors[$field][] = 'Must be a string.';
                }
                break;

            case 'int':
            case 'integer':
                if ($value !== null) {
                    if (!is_numeric($value) || (int)$value != $value) {
                        $this->errors[$field][] = 'Must be an integer.';
                    } else {
                        $value = (int)$value; // cast
                    }
                }
                break;

            case 'decimal':
            case 'numeric':
                if ($value !== null) {
                    if (!is_numeric($value)) {
                        $this->errors[$field][] = 'Must be a numeric value.';
                    } else {
                        $value = (float)$value;
                    }
                }
                break;

            case 'boolean':
            case 'bool':
                if ($value !== null) {
                    if (!in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true)) {
                        $this->errors[$field][] = 'Must be a boolean.';
                    } else {
                        $value = in_array($value, [true, 1, '1', 'true'], true);
                    }
                }
                break;

            case 'email':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = 'Must be a valid email address.';
                }
                break;

            case 'mobile':
                // Indian mobile: 10 digits, optionally prefixed with +91 or 0
                if ($value !== null) {
                    $normalized = preg_replace('/^(?:\+91|0)?/', '', (string)$value);
                    if (!preg_match('/^[6-9]\d{9}$/', (string)$normalized)) {
                        $this->errors[$field][] = 'Must be a valid 10-digit Indian mobile number.';
                    }
                }
                break;

            case 'gstin':
                // Format: 2-digit state code + 10-char PAN + 1Z + 1 digit + 1 checksum
                if ($value !== null && !preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', strtoupper((string)$value))) {
                    $this->errors[$field][] = 'Must be a valid GSTIN (e.g., 27AAAAA0000A1ZA).';
                }
                break;

            case 'pincode':
                if ($value !== null && !preg_match('/^\d{6}$/', (string)$value)) {
                    $this->errors[$field][] = 'Must be a valid 6-digit Indian pincode.';
                }
                break;

            case 'min':
                if ($value !== null) {
                    if (is_numeric($value) && (float)$value < (float)$param) {
                        $this->errors[$field][] = "Must be at least {$param}.";
                    } elseif (is_string($value) && mb_strlen($value) < (int)$param) {
                        $this->errors[$field][] = "Must be at least {$param} characters.";
                    } elseif (is_array($value) && count($value) < (int)$param) {
                        $this->errors[$field][] = "Must have at least {$param} items.";
                    }
                }
                break;

            case 'max':
                if ($value !== null) {
                    if (is_numeric($value) && (float)$value > (float)$param) {
                        $this->errors[$field][] = "Must not exceed {$param}.";
                    } elseif (is_string($value) && mb_strlen($value) > (int)$param) {
                        $this->errors[$field][] = "Must not exceed {$param} characters.";
                    } elseif (is_array($value) && count($value) > (int)$param) {
                        $this->errors[$field][] = "Must have at most {$param} items.";
                    }
                }
                break;

            case 'enum':
                if ($value !== null) {
                    $allowed = explode(',', $param ?? '');
                    if (!in_array($value, $allowed, true)) {
                        $this->errors[$field][] = "Must be one of: " . implode(', ', $allowed) . '.';
                    }
                }
                break;

            case 'date':
                if ($value !== null) {
                    $fmt    = $param ?? 'Y-m-d';
                    $parsed = \DateTimeImmutable::createFromFormat($fmt, (string)$value);
                    if ($parsed === false || $parsed->format($fmt) !== (string)$value) {
                        $this->errors[$field][] = "Must be a valid date (format: {$fmt}).";
                    }
                }
                break;

            case 'regex':
                if ($value !== null && !preg_match($param, (string)$value)) {
                    $this->errors[$field][] = 'Invalid format.';
                }
                break;

            case 'array':
                if ($value !== null && !is_array($value)) {
                    $this->errors[$field][] = 'Must be an array.';
                }
                break;

            case 'min_length': // alias for string min
                if ($value !== null && is_string($value) && mb_strlen($value) < (int)$param) {
                    $this->errors[$field][] = "Must be at least {$param} characters.";
                }
                break;

            default:
                // Unknown rule — silently ignore (log in debug mode)
                break;
        }
    }
}
