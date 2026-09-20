<?php
declare(strict_types=1);
namespace App\Domain\Franchises;

use App\Core\{Database, RefGenerator};

final class FranchiseDefaultsService
{
    public function __construct(private Database $db) {}

    public function seed(string $orgRef, string $franchiseRef, string $actorRef): void
    {
        // 1. Default system_settings
        $defaultSettings = [
            'territory_unassigned_policy' => 'BLOCK',
            'credit_policy'               => 'HOLD',
            'near_expiry_days'            => '180',
            'min_shelf_days'              => '0',
            'auto_confirm'                => '0',
            'lead_dup_policy'             => 'LINK',
            'fy_start_month'              => '4',
        ];

        foreach ($defaultSettings as $key => $val) {
            $this->insertSetting($orgRef, $franchiseRef, $key, $val, $actorRef);
        }

        // 2. Default pricing tiers
        foreach (['RETAIL', 'STOCKIST', 'DISTRIBUTOR'] as $tierName) {
            $this->insertTier($orgRef, $franchiseRef, $tierName, $actorRef);
        }

        // 3. Default notification templates
        $this->seedNotificationTemplates($orgRef, $franchiseRef);

        // 4. Default sequence counters (initialized for current year e.g. 2026)
        $period = date('Y');
        $counterKeys = ['ORDER', 'INVOICE', 'DISPATCH', 'PAYMENT', 'PARTY', 'LEAD'];
        foreach ($counterKeys as $ck) {
            $this->insertSequence($orgRef, $franchiseRef, $ck, $period);
        }
    }

    private function insertSetting(string $orgRef, string $franchiseRef, string $key, string $val, string $actorRef): void
    {
        $existing = $this->db->fetchOne(
            "SELECT id FROM system_settings WHERE franchise_ref = ? AND setting_key = ? LIMIT 1",
            [$franchiseRef, $key]
        );

        if (!$existing) {
            $this->db->insert('system_settings', [
                'org_ref'        => $orgRef,
                'franchise_ref'  => $franchiseRef,
                'setting_key'    => $key,
                'setting_value'  => $val,
                'updated_by_ref' => $actorRef,
            ]);
        }
    }

    private function insertTier(string $orgRef, string $franchiseRef, string $name, string $actorRef): void
    {
        $existing = $this->db->fetchOne(
            "SELECT id FROM pricing_tiers WHERE franchise_ref = ? AND tier_name = ? LIMIT 1",
            [$franchiseRef, $name]
        );

        if (!$existing) {
            $tierRef = RefGenerator::make('TIR');
            $this->db->insert('pricing_tiers', [
                'tier_ref'       => $tierRef,
                'org_ref'        => $orgRef,
                'franchise_ref'  => $franchiseRef,
                'tier_name'      => $name,
                'status'         => 'ACTIVE',
                'created_by_ref' => $actorRef,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function seedNotificationTemplates(string $orgRef, string $franchiseRef): void
    {
        $templates = [
            ['ORDER_CONFIRMED', 'INAPP', 'Your order {{order_no}} for Rs. {{grand_total}} has been confirmed.'],
            ['ORDER_CONFIRMED', 'SMS', 'Dear Customer, order {{order_no}} confirmed. Amount: Rs. {{grand_total}}.'],
            ['DISPATCH_SHIPPED', 'INAPP', 'Order {{order_no}} dispatched via {{transporter}} LR: {{lr_number}}.'],
            ['PAYMENT_RECORDED', 'INAPP', 'Payment of Rs. {{amount}} recorded against receipt {{payment_no}}.'],
        ];

        foreach ($templates as [$event, $channel, $body]) {
            $existing = $this->db->fetchOne(
                "SELECT id FROM notification_templates WHERE franchise_ref = ? AND event_type = ? AND channel = ? LIMIT 1",
                [$franchiseRef, $event, $channel]
            );

            if (!$existing) {
                $this->db->insert('notification_templates', [
                    'org_ref'       => $orgRef,
                    'franchise_ref' => $franchiseRef,
                    'event_type'    => $event,
                    'channel'       => $channel,
                    'body_template' => $body,
                    'status'        => 'ACTIVE',
                ]);
            }
        }
    }

    private function insertSequence(string $orgRef, string $franchiseRef, string $key, string $period): void
    {
        $existing = $this->db->fetchOne(
            "SELECT last_value FROM sequence_counters WHERE franchise_ref = ? AND counter_key = ? AND period_key = ? LIMIT 1",
            [$franchiseRef, $key, $period]
        );

        if (!$existing) {
            $this->db->insert('sequence_counters', [
                'org_ref'       => $orgRef,
                'franchise_ref' => $franchiseRef,
                'counter_key'   => $key,
                'period_key'    => $period,
                'last_value'    => 0,
            ]);
        }
    }
}
