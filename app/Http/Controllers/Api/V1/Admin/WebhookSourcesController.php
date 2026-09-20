<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validation;
use App\Core\TenantContext;
use App\Domain\Webhooks\WebhookService;
use App\Core\Database;

final class WebhookSourcesController
{
    public function __construct(
        private WebhookService $webhookService,
        private Database $db,
    ) {}

    public function index(Request $r): Response
    {
        $ctx = TenantContext::get();
        $items = $this->db->fetchAll(
            "SELECT source_ref, source_name, endpoint_slug, auth_type, status, created_at
             FROM webhook_sources WHERE franchise_ref = :f ORDER BY created_at DESC",
            [':f' => $ctx->franchiseRef]
        );

        return Response::json(['data' => ['items' => $items]]);
    }

    public function store(Request $r): Response
    {
        $ctx = TenantContext::get();
        $clean = Validation::validate($r->all(), [
            'source_name' => 'required|string',
        ]);

        $source = $this->webhookService->createSource(
            $ctx->orgRef,
            $ctx->franchiseRef,
            $clean['source_name'],
            $ctx->userRef
        );

        return Response::json(['data' => $source], 201);
    }
}
