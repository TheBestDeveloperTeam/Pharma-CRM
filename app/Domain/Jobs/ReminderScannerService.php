<?php
declare(strict_types=1);
namespace App\Domain\Jobs;

use App\Core\Database;
use App\Domain\Notifications\NotificationService;

final class ReminderScannerService
{
    public function __construct(
        private Database $db,
        private NotificationService $notifService
    ) {}

    /**
     * Scan follow-ups due in next 2 hours and queue notifications
     */
    public function scanDueFollowUps(): int
    {
        $sql = "SELECT f.followup_ref, f.org_ref, f.franchise_ref, f.lead_ref, f.assigned_user_ref, f.next_action, f.next_follow_up_at, l.contact_name, l.mobile
                FROM follow_ups f
                JOIN leads l ON f.franchise_ref = l.franchise_ref AND f.lead_ref = l.lead_ref
                WHERE f.status = 'PENDING'
                  AND f.next_follow_up_at <= DATE_ADD(NOW(), INTERVAL 2 HOUR)
                  AND f.next_follow_up_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";

        $rows = $this->db->fetchAll($sql);
        $count = 0;

        foreach ($rows as $r) {
            $msg = "Follow-up due for {$r['contact_name']} ({$r['next_action']}) at {$r['next_follow_up_at']}";
            $this->notifService->send(
                orgRef: $r['org_ref'],
                franchiseRef: $r['franchise_ref'],
                eventType: 'FOLLOW_UP_DUE',
                channel: 'INAPP',
                message: $msg,
                userRef: $r['assigned_user_ref'],
                entityType: 'follow_up',
                entityRef: $r['followup_ref'],
                payload: ['lead_ref' => $r['lead_ref'], 'contact_name' => $r['contact_name']]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Scan overdue invoices and queue payment reminders
     */
    public function scanOverdueInvoices(): int
    {
        $sql = "SELECT i.invoice_ref, i.org_ref, i.franchise_ref, i.party_ref, i.invoice_no, i.grand_total, (i.grand_total - i.paid_total) AS balance_due, i.due_date, p.firm_name, p.email, p.mobile
                FROM invoices i
                JOIN parties p ON i.franchise_ref = p.franchise_ref AND i.party_ref = p.party_ref
                WHERE i.status = 'POSTED'
                  AND i.due_date IS NOT NULL
                  AND i.due_date < CURDATE()
                  AND (i.grand_total - i.paid_total) > 0";

        $rows = $this->db->fetchAll($sql);
        $count = 0;

        foreach ($rows as $r) {
            $msg = "Payment overdue for Invoice {$r['invoice_no']} (Due: {$r['due_date']}, Balance: ₹{$r['balance_due']})";
            $this->notifService->send(
                orgRef: $r['org_ref'],
                franchiseRef: $r['franchise_ref'],
                eventType: 'PAYMENT_OVERDUE',
                channel: 'INAPP',
                message: $msg,
                partyRef: $r['party_ref'],
                entityType: 'invoice',
                entityRef: $r['invoice_ref'],
                payload: ['balance_due' => $r['balance_due'], 'email' => $r['email'], 'mobile' => $r['mobile']]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Scan batches nearing expiry (within 180 days)
     */
    public function scanNearExpiryBatches(): int
    {
        $sql = "SELECT b.batch_ref, b.org_ref, b.franchise_ref, b.batch_no, (b.on_hand_qty - b.reserved_qty) AS available_qty, b.expiry_date, p.product_name, p.sku
                FROM inventory_batches b
                JOIN products p ON b.franchise_ref = p.franchise_ref AND b.product_ref = p.product_ref
                WHERE b.status = 'SALEABLE'
                  AND (b.on_hand_qty - b.reserved_qty) > 0
                  AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 180 DAY)";

        $rows = $this->db->fetchAll($sql);
        $count = 0;

        foreach ($rows as $r) {
            $msg = "Batch {$r['batch_no']} ({$r['product_name']}) expires on {$r['expiry_date']} (Qty: {$r['available_qty']})";
            $this->notifService->send(
                orgRef: $r['org_ref'],
                franchiseRef: $r['franchise_ref'],
                eventType: 'NEAR_EXPIRY_BATCH',
                channel: 'INAPP',
                message: $msg,
                entityType: 'inventory_batch',
                entityRef: $r['batch_ref'],
                payload: ['batch_no' => $r['batch_no'], 'available_qty' => $r['available_qty']]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Release expired unbilled reservations
     */
    public function releaseExpiredReservations(): int
    {
        $sql = "SELECT r.id, r.franchise_ref, r.batch_ref, r.reserved_qty
                FROM stock_reservations r
                WHERE r.status = 'ACTIVE'
                  AND r.expires_at < NOW()";

        $rows = $this->db->fetchAll($sql);
        $count = 0;

        foreach ($rows as $r) {
            $this->db->beginTransaction();
            try {
                $this->db->prepare(
                    "UPDATE inventory_batches 
                     SET reserved_qty = GREATEST(0, reserved_qty - :qty)
                     WHERE franchise_ref = :f AND batch_ref = :b"
                )->execute([
                    ':qty' => $r['reserved_qty'],
                    ':f'   => $r['franchise_ref'],
                    ':b'   => $r['batch_ref']
                ]);

                $this->db->prepare(
                    "UPDATE stock_reservations SET status = 'EXPIRED' WHERE id = :id"
                )->execute([':id' => $r['id']]);

                $this->db->commit();
                $count++;
            } catch (\Throwable $e) {
                $this->db->rollBack();
            }
        }

        return $count;
    }
}
