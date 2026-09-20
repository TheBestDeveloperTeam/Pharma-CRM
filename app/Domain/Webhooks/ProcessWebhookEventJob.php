<?php
declare(strict_types=1);
namespace App\Domain\Webhooks;

use App\Core\Database;
use App\Domain\Leads\LeadService;

final class ProcessWebhookEventJob
{
    public function __construct(
        private Database $db,
        private LeadService $leadService,
    ) {}

    public function handle(array $payload): void
    {
        $eventRef = $payload['event_ref'] ?? null;
        if (!$eventRef) {
            return;
        }

        $event = $this->db->fetchOne(
            "SELECT * FROM webhook_events WHERE event_ref = :r LIMIT 1",
            [':r' => $eventRef]
        );

        if (!$event || $event['status'] === 'PROCESSED') {
            return;
        }

        $leadData = json_decode($event['payload_json'], true) ?? [];

        try {
            $leadData['org_ref']             = $event['org_ref'];
            $leadData['franchise_ref']       = $event['franchise_ref'];
            $leadData['external_source_ref'] = $event['source_ref'];
            $leadData['external_lead_id']    = $event['external_event_id'];
            $leadData['lead_source']         = 'WEBHOOK';
            $leadData['created_by_ref']      = 'WEBHOOK';

            $this->leadService->create($leadData);

            $this->db->prepare(
                "UPDATE webhook_events SET status = 'PROCESSED', processed_at = NOW() WHERE event_ref = :r"
            )->execute([':r' => $eventRef]);
        } catch (\Throwable $e) {
            $this->db->prepare(
                "UPDATE webhook_events SET status = 'FAILED', error_code = 'LEAD_PROCESSING_FAILED', error_message = :err, attempt_count = attempt_count + 1 WHERE event_ref = :r"
            )->execute([
                ':r'   => $eventRef,
                ':err' => substr($e->getMessage(), 0, 250),
            ]);
            throw $e;
        }
    }
}
