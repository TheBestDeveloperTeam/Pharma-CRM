<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\TenantContext;
use App\Domain\Masters\{DynamicFormValidator, UiFormSchemaService};

final class UiFormSchemasController
{
    public function __construct(
        private UiFormSchemaService $schemaService,
        private ?DynamicFormValidator $validator = null
    ) {
        $this->validator = $this->validator ?? \App\Core\Container::getInstance()->make(DynamicFormValidator::class);
    }

    public function index(Request $r): Response
    {
        $schemas = $this->schemaService->list();
        return Response::json(200, $schemas);
    }

    public function show(Request $r, ?string $formKey = null): Response
    {
        $key = $formKey ?? (string)$r->param('form_key');
        if ($key === '') {
            $key = (string)$r->param('key');
        }

        $ctx = TenantContext::get();
        $franchiseRef = $ctx ? $ctx->franchiseRef : null;

        $resolveOptions = $r->query('resolve_options', '1') !== '0';
        $schema = $this->schemaService->getSchema($key, $franchiseRef, $resolveOptions);

        return Response::json(200, $schema);
    }

    public function validate(Request $r, ?string $formKey = null): Response
    {
        $key = $formKey ?? (string)$r->param('form_key');
        if ($key === '') {
            $key = (string)$r->param('key');
        }

        $payload = (array)$r->all();
        $stepNumber = $r->input('step_number') !== null ? (int)$r->input('step_number') : null;

        $result = $this->validator->validate($key, $payload, $stepNumber);

        $status = $result['valid'] ? 200 : 422;
        return Response::json($status, [
            'valid'          => $result['valid'],
            'errors'         => $result['errors'],
            'validated_data' => $result['validated_data'],
        ]);
    }
}
