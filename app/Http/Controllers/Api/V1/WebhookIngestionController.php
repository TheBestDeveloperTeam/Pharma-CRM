<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\RefGenerator;
use App\Core\Exceptions\ValidationException;
use App\Domain\Webhooks\WebhookService;
use App\Domain\Jobs\JobDispatcher;

final class WebhookIngestionController
{
    public function __construct(
        private WebhookService $webhookService,
        private JobDispatcher $jobs,
        private Database $db,
    ) {}

    public function ingest(Request $r, string $endpointSlug): Response
    {
        $rawBody = $r->rawBody();

        // 1. Max payload 256KB
        if (strlen($rawBody) > 262144) {
            return Response::json(['error' => 'PAYLOAD_TOO_LARGE', 'message' => 'Payload exceeds 256KB.'], 413);
        }

        // 2. Resolve source & verify HMAC signature
        $source = $this->webhookService->resolveSource($endpointSlug);
        $signature = $r->header('x-signature') ?? $r->header('X-Signature');
        $timestamp = (int)($r->header('x-timestamp') ?? $r->header('X-Timestamp') ?? time());

        $this->webhookService->verifySignature($rawBody, $source['decrypted_secret'], $signature, $timestamp);

        // 3. Parse JSON
        $data = json_decode($rawBody, true);
        if (!is_array($data) || empty($data['external_event_id'])) {
            throw new ValidationException('INVALID_PAYLOAD', 'Payload must be valid JSON with external_event_id.');
        }

        $eventId = (string)$data['external_event_id'];
        $eventRef = RefGenerator::generate('WHE');
        $payloadHash = hash('sha256', $rawBody);

        // 4. Insert webhook event (idempotent duplicate check)
        $sql = "INSERT INTO webhook_events (
            event_ref, org_ref, franchise_ref, source_ref, external_event_id,
            payload_hash, payload_json, signature, status
        ) VALUES (
            :event_ref, :org_ref, :franchise_ref, :source_ref, :external_event_id,
            :payload_hash, :payload_json, :signature, 'RECEIVED'
        ) ON DUPLICATE KEY UPDATE status = status";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':event_ref'          => $eventRef,
            ':org_ref'            => $source['org_ref'],
            ':franchise_ref'      => $source['franchise_ref'],
            ':source_ref'         => $source['source_ref'],
            ':external_event_id'  => $eventId,
            ':payload_hash'       => $payloadHash,
            ':payload_json'       => $rawBody,
            ':signature'          => $signature,
        ]);

        if ($stmt->rowCount() === 0) {
            // Duplicate event handled cleanly
            return Response::json(['status' => 'duplicate', 'message' => 'Event already received.']);
        }

        // 5. Dispatch async job
        $this->jobs->dispatch(
            'ProcessWebhookEvent',
            ['event_ref' => $eventRef],
            $source['org_ref'],
            $source['franchise_ref'],
            "webhook_event:{$eventRef}"
        );

        return Response::json(202, [
            'status'    => 'accepted',
            'event_ref' => $eventRef,
        ]);
    }
}
