<?php
declare(strict_types=1);
namespace App\Repositories\Sql;

use App\Core\Database;
use App\Repositories\Contracts\FollowUpRepositoryInterface;

final class SqlFollowUpRepository implements FollowUpRepositoryInterface
{
    public function __construct(private Database $db) {}

    public function list(string $franchiseRef, array $filters, int $page, int $perPage, ?string $assignedUserRef = null): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [':f' => $franchiseRef];
        $where = ["franchise_ref = :f"];

        if ($assignedUserRef !== null) {
            $where[] = "assigned_user_ref = :assigned_user";
            $params[':assigned_user'] = $assignedUserRef;
        }

        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['lead_ref'])) {
            $where[] = "lead_ref = :lead_ref";
            $params[':lead_ref'] = $filters['lead_ref'];
        }

        if (!empty($filters['overdue'])) {
            $where[] = "status = 'PENDING' AND next_follow_up_at < NOW()";
        }

        $whereSql = implode(' AND ', $where);

        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM follow_ups WHERE {$whereSql}",
            $params
        );

        $sql = "SELECT * FROM follow_ups WHERE {$whereSql} ORDER BY next_follow_up_at ASC LIMIT {$perPage} OFFSET {$offset}";
        $items = $this->db->fetchAll($sql, $params);

        return [
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    public function findByRef(string $franchiseRef, string $followupRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM follow_ups WHERE franchise_ref = :f AND followup_ref = :r LIMIT 1",
            [':f' => $franchiseRef, ':r' => $followupRef]
        );
    }

    public function create(array $data): string
    {
        $sql = "INSERT INTO follow_ups (
            followup_ref, org_ref, franchise_ref, lead_ref, party_ref,
            assigned_user_ref, activity_type, next_action, next_follow_up_at,
            status, remark, created_by_ref
        ) VALUES (
            :followup_ref, :org_ref, :franchise_ref, :lead_ref, :party_ref,
            :assigned_user_ref, :activity_type, :next_action, :next_follow_up_at,
            :status, :remark, :created_by_ref
        )";

        $this->db->prepare($sql)->execute([
            ':followup_ref'       => $data['followup_ref'],
            ':org_ref'            => $data['org_ref'],
            ':franchise_ref'      => $data['franchise_ref'],
            ':lead_ref'           => $data['lead_ref'] ?? null,
            ':party_ref'          => $data['party_ref'] ?? null,
            ':assigned_user_ref'  => $data['assigned_user_ref'],
            ':activity_type'      => $data['activity_type'],
            ':next_action'        => $data['next_action'],
            ':next_follow_up_at'  => $data['next_follow_up_at'],
            ':status'             => $data['status'] ?? 'PENDING',
            ':remark'             => $data['remark'] ?? null,
            ':created_by_ref'     => $data['created_by_ref'],
        ]);

        return $data['followup_ref'];
    }

    public function update(string $franchiseRef, string $followupRef, array $data): bool
    {
        $allowed = ['status', 'next_action', 'next_follow_up_at', 'remark', 'activity_type'];
        $sets = [];
        $params = [':f' => $franchiseRef, ':r' => $followupRef];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "{$f} = :{$f}";
                $params[":{$f}"] = $data[$f];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sql = "UPDATE follow_ups SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE franchise_ref = :f AND followup_ref = :r";
        return $this->db->prepare($sql)->execute($params);
    }

    public function countPendingForLead(string $franchiseRef, string $leadRef): int
    {
        return (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM follow_ups WHERE franchise_ref = :f AND lead_ref = :l AND status = 'PENDING'",
            [':f' => $franchiseRef, ':l' => $leadRef]
        );
    }
}
