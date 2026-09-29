<?php
declare(strict_types=1);

namespace Tests\Unit\Masters;

use App\Core\Exceptions\ValidationException;
use App\Domain\Masters\DynamicFormValidator;
use App\Repositories\Contracts\UiFormSchemaRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class DynamicFormValidatorTest extends TestCase
{
    public static function runStandalone(): void
    {
        $test = new self('test');
        $test->testValidSubmission();
        $test->testInvalidFieldsValidation();
        $test->testMultiStepFiltering();
        $test->testValidateOrFailThrows();
        echo "[PASS] All DynamicFormValidator unit assertions passed successfully.\n";
    }

    private static function createMockRepository(): UiFormSchemaRepositoryInterface
    {
        return new class implements UiFormSchemaRepositoryInterface {
            public function listSchemas(): array { return []; }
            public function findSchemaByKey(string $formKey): ?array {
                if ($formKey === 'party_create' || $formKey === 'onboarding_register') {
                    return ['schema_ref' => 'SCH-001', 'form_key' => $formKey, 'title' => 'Test Schema'];
                }
                return null;
            }
            public function getFieldsByFormKey(string $formKey): array {
                if ($formKey === 'party_create') {
                    return [
                        [
                            'field_ref' => 'FLD-001',
                            'form_key' => 'party_create',
                            'field_name' => 'firm_name',
                            'label' => 'Authorized Firm Name',
                            'field_type' => 'text',
                            'is_required' => 1,
                            'validation_rules_json' => json_encode(['required' => true, 'min' => 3, 'max' => 100]),
                            'step_number' => 1,
                        ],
                        [
                            'field_ref' => 'FLD-002',
                            'form_key' => 'party_create',
                            'field_name' => 'gstin',
                            'label' => 'GSTIN',
                            'field_type' => 'text',
                            'is_required' => 1,
                            'validation_rules_json' => json_encode([
                                'required' => true,
                                'pattern' => '^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$',
                                'message' => 'Enter valid 15-character GSTIN'
                            ]),
                            'step_number' => 1,
                        ],
                        [
                            'field_ref' => 'FLD-003',
                            'form_key' => 'party_create',
                            'field_name' => 'mobile',
                            'label' => 'Mobile Number',
                            'field_type' => 'phone',
                            'is_required' => 1,
                            'validation_rules_json' => json_encode([
                                'required' => true,
                                'pattern' => '^[6-9][0-9]{9}$',
                                'message' => 'Enter valid 10-digit Indian mobile number'
                            ]),
                            'step_number' => 1,
                        ],
                        [
                            'field_ref' => 'FLD-004',
                            'form_key' => 'party_create',
                            'field_name' => 'credit_limit',
                            'label' => 'Credit Limit (₹)',
                            'field_type' => 'currency',
                            'is_required' => 1,
                            'validation_rules_json' => json_encode(['required' => true, 'min' => 0]),
                            'step_number' => 1,
                        ],
                    ];
                }

                if ($formKey === 'onboarding_register') {
                    return [
                        [
                            'field_ref' => 'FLD-ONB-1',
                            'form_key' => 'onboarding_register',
                            'field_name' => 'firm_name',
                            'label' => 'Firm Name',
                            'field_type' => 'text',
                            'is_required' => 1,
                            'validation_rules_json' => json_encode(['required' => true, 'min' => 3]),
                            'step_number' => 1,
                        ],
                        [
                            'field_ref' => 'FLD-ONB-2',
                            'form_key' => 'onboarding_register',
                            'field_name' => 'gstin',
                            'label' => 'GSTIN',
                            'field_type' => 'text',
                            'is_required' => 1,
                            'validation_rules_json' => json_encode(['required' => true]),
                            'step_number' => 2,
                        ],
                    ];
                }

                return [];
            }
        };
    }

    public function testValidSubmission(): void
    {
        $validator = new DynamicFormValidator(self::createMockRepository());
        $payload = [
            'firm_name' => 'Apollo Pharma Agencies',
            'gstin' => '27ABCDE1234F1Z5',
            'mobile' => '9876543210',
            'credit_limit' => '50000.00',
        ];

        $res = $validator->validate('party_create', $payload);
        $this->assertTrue($res['valid'], 'Valid payload should return valid === true');
        $this->assertEmpty($res['errors'], 'Valid payload should have no errors');
        $this->assertSame('Apollo Pharma Agencies', $res['validated_data']['firm_name']);
    }

    public function testInvalidFieldsValidation(): void
    {
        $validator = new DynamicFormValidator(self::createMockRepository());
        $invalidPayload = [
            'firm_name' => 'AB', // too short (< 3)
            'gstin' => 'INVALID_GSTIN_123',
            'mobile' => '12345', // invalid pattern
            'credit_limit' => '-100', // < 0
        ];

        $res = $validator->validate('party_create', $invalidPayload);
        $this->assertFalse($res['valid'], 'Invalid payload should return valid === false');
        $this->assertArrayHasKey('firm_name', $res['errors'], 'Should error on short firm_name');
        $this->assertArrayHasKey('gstin', $res['errors'], 'Should error on invalid GSTIN');
        $this->assertSame('Enter valid 15-character GSTIN', $res['errors']['gstin'][0], 'Custom message should be preserved');
        $this->assertArrayHasKey('mobile', $res['errors'], 'Should error on invalid mobile');
        $this->assertArrayHasKey('credit_limit', $res['errors'], 'Should error on negative credit_limit');
    }

    public function testMultiStepFiltering(): void
    {
        $validator = new DynamicFormValidator(self::createMockRepository());

        // Validate step 1 only: step 2 field 'gstin' should NOT cause a validation error when missing
        $step1Payload = [
            'firm_name' => 'Metropolis Distributors',
        ];

        $res = $validator->validate('onboarding_register', $step1Payload, 1);
        $this->assertTrue($res['valid'], 'Step 1 validation should pass without Step 2 fields');
        $this->assertArrayNotHasKey('gstin', $res['errors'], 'Step 2 gstin should not be validated during step 1');

        // Now validate step 2 with empty payload: should require gstin
        $resStep2 = $validator->validate('onboarding_register', [], 2);
        $this->assertFalse($resStep2['valid'], 'Step 2 without gstin should fail');
        $this->assertArrayHasKey('gstin', $resStep2['errors'], 'Step 2 must report missing gstin');
    }

    public function testValidateOrFailThrows(): void
    {
        $validator = new DynamicFormValidator(self::createMockRepository());
        $this->expectException(ValidationException::class);
        $validator->validateOrFail('party_create', []);
    }
}
