<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\OrderRepositoryInterface;

final class SqlOrderRepository implements OrderRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function findByRef(string $franchiseRef, string $orderRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT o.*, p.firm_name as party_name, p.credit_limit, p.payment_terms_days
             FROM orders o
             JOIN parties p ON o.franchise_ref = p.franchise_ref AND o.party_ref = p.party_ref
             WHERE o.franchise_ref = :f AND o.order_ref = :r LIMIT 1",
            [':f' => $franchiseRef, ':r' => $orderRef]
        );
    }

    public function findByClientRef(string $franchiseRef, string $clientOrderRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM orders WHERE franchise_ref = :f AND client_order_ref = :c LIMIT 1",
            [':f' => $franchiseRef, ':c' => $clientOrderRef]
        );
    }

    public function list(string $franchiseRef, array $filters, int $page, int $perPage, ?string $partyRef = null): array
    {
        $where = ["o.franchise_ref = :f"];
        $params = [':f' => $franchiseRef];

        if ($partyRef !== null) {
            $where[] = "o.party_ref = :party";
            $params[':party'] = $partyRef;
        }

        if (!empty($filters['status'])) {
            $where[] = "o.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['sales_user_ref'])) {
            $where[] = "o.sales_user_ref = :sales";
            $params[':sales'] = $filters['sales_user_ref'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(o.order_no LIKE :s1 OR o.client_order_ref LIKE :s2 OR p.firm_name LIKE :s3)";
            $term = '%' . trim($filters['search']) . '%';
            $params[':s1'] = $term;
            $params[':s2'] = $term;
            $params[':s3'] = $term;
        }

        $whereClause = implode(' AND ', $where);

        $total = (int)$this->db->fetchOne(
            "SELECT COUNT(*) as cnt FROM orders o JOIN parties p ON o.franchise_ref = p.franchise_ref AND o.party_ref = p.party_ref WHERE {$whereClause}",
            $params
        )['cnt'];

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT o.*, p.firm_name as party_name
                FROM orders o
                JOIN parties p ON o.franchise_ref = p.franchise_ref AND o.party_ref = p.party_ref
                WHERE {$whereClause}
                ORDER BY o.id DESC LIMIT {$perPage} OFFSET {$offset}";

        $rows = $this->db->fetchAll($sql, $params);

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function create(array $orderData, array $items): string
    {
        $this->db->transaction(function() use ($orderData, $items) {
            $sqlOrder = "INSERT INTO orders (
                order_ref, order_no, org_ref, franchise_ref, client_order_ref,
                party_ref, sales_user_ref, channel, order_date, shipping_address,
                shipping_pincode, status, territory_status, subtotal, discount_total,
                gst_total, grand_total, version, remarks, created_by_ref
            ) VALUES (
                :order_ref, :order_no, :org_ref, :franchise_ref, :client_order_ref,
                :party_ref, :sales_user_ref, :channel, :order_date, :shipping_address,
                :shipping_pincode, :status, :territory_status, :subtotal, :discount_total,
                :gst_total, :grand_total, 1, :remarks, :created_by_ref
            )";

            $this->db->prepare($sqlOrder)->execute([
                ':order_ref'         => $orderData['order_ref'],
                ':order_no'          => $orderData['order_no'],
                ':org_ref'           => $orderData['org_ref'],
                ':franchise_ref'     => $orderData['franchise_ref'],
                ':client_order_ref'  => $orderData['client_order_ref'],
                ':party_ref'         => $orderData['party_ref'],
                ':sales_user_ref'    => $orderData['sales_user_ref'] ?? null,
                ':channel'           => $orderData['channel'],
                ':order_date'        => $orderData['order_date'],
                ':shipping_address'  => $orderData['shipping_address'] ?? null,
                ':shipping_pincode'  => $orderData['shipping_pincode'] ?? null,
                ':status'            => $orderData['status'] ?? 'DRAFT',
                ':territory_status'  => $orderData['territory_status'] ?? 'OK',
                ':subtotal'          => $orderData['subtotal'],
                ':discount_total'    => $orderData['discount_total'],
                ':gst_total'         => $orderData['gst_total'],
                ':grand_total'       => $orderData['grand_total'],
                ':remarks'           => $orderData['remarks'] ?? null,
                ':created_by_ref'    => $orderData['created_by_ref'],
            ]);

            $sqlItem = "INSERT INTO order_items (
                item_ref, org_ref, franchise_ref, order_ref, product_ref,
                paid_qty, free_qty, rate, rate_source, price_ref,
                discount, gst_percent, line_total, scheme_ref
            ) VALUES (
                :item_ref, :org_ref, :franchise_ref, :order_ref, :product_ref,
                :paid_qty, :free_qty, :rate, :rate_source, :price_ref,
                :discount, :gst_percent, :line_total, :scheme_ref
            )";

            $stmtItem = $this->db->prepare($sqlItem);
            foreach ($items as $it) {
                $stmtItem->execute([
                    ':item_ref'       => $it['item_ref'],
                    ':org_ref'        => $orderData['org_ref'],
                    ':franchise_ref'  => $orderData['franchise_ref'],
                    ':order_ref'      => $orderData['order_ref'],
                    ':product_ref'    => $it['product_ref'],
                    ':paid_qty'       => $it['paid_qty'],
                    ':free_qty'       => $it['free_qty'] ?? 0,
                    ':rate'           => $it['rate'],
                    ':rate_source'    => $it['rate_source'] ?? 'DEFAULT',
                    ':price_ref'      => $it['price_ref'] ?? null,
                    ':discount'       => $it['discount'] ?? 0.00,
                    ':gst_percent'    => $it['gst_percent'] ?? 0.00,
                    ':line_total'     => $it['line_total'],
                    ':scheme_ref'     => $it['scheme_ref'] ?? null,
                ]);
            }

            // Record initial status history
            $sqlHist = "INSERT INTO order_status_history (
                org_ref, franchise_ref, order_ref, from_status, to_status, actor_ref, reason
            ) VALUES (
                :org_ref, :franchise_ref, :order_ref, NULL, :to_status, :actor_ref, :reason
            )";
            $this->db->prepare($sqlHist)->execute([
                ':org_ref'        => $orderData['org_ref'],
                ':franchise_ref'  => $orderData['franchise_ref'],
                ':order_ref'      => $orderData['order_ref'],
                ':to_status'      => $orderData['status'] ?? 'DRAFT',
                ':actor_ref'      => $orderData['created_by_ref'],
                ':reason'         => 'Order placed',
            ]);
        });

        return $orderData['order_ref'];
    }

    public function updateStatus(string $franchiseRef, string $orderRef, string $status, string $actorRef, ?string $reason = null): bool
    {
        $current = $this->findByRef($franchiseRef, $orderRef);
        if (!$current) {
            return false;
        }

        $fromStatus = $current['status'];

        return $this->db->transaction(function() use ($franchiseRef, $orderRef, $fromStatus, $status, $actorRef, $reason, $current) {
            $sql = "UPDATE orders SET status = :s, version = version + 1, updated_by_ref = :actor, updated_at = NOW()
                    WHERE franchise_ref = :f AND order_ref = :r";
            $this->db->prepare($sql)->execute([
                ':s'     => $status,
                ':actor' => $actorRef,
                ':f'     => $franchiseRef,
                ':r'     => $orderRef,
            ]);

            $sqlHist = "INSERT INTO order_status_history (
                org_ref, franchise_ref, order_ref, from_status, to_status, actor_ref, reason
            ) VALUES (
                :org_ref, :franchise_ref, :order_ref, :from_status, :to_status, :actor_ref, :reason
            )";
            $this->db->prepare($sqlHist)->execute([
                ':org_ref'        => $current['org_ref'],
                ':franchise_ref'  => $franchiseRef,
                ':order_ref'      => $orderRef,
                ':from_status'    => $fromStatus,
                ':to_status'      => $status,
                ':actor_ref'      => $actorRef,
                ':reason'         => $reason,
            ]);

            return true;
        });
    }

    public function getItems(string $franchiseRef, string $orderRef): array
    {
        return $this->db->fetchAll(
            "SELECT oi.*, p.product_name, p.sku, p.hsn_code, p.shelf_life_days
             FROM order_items oi
             JOIN products p ON oi.franchise_ref = p.franchise_ref AND oi.product_ref = p.product_ref
             WHERE oi.franchise_ref = :f AND oi.order_ref = :r
             ORDER BY oi.id ASC",
            [':f' => $franchiseRef, ':r' => $orderRef]
        );
    }
}
