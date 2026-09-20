<?php
namespace App\Domain\DCR;

use App\Repositories\Contracts\DcrRepositoryInterface;
use App\Domain\Audit\AuditService;

class DcrService {
    private DcrRepositoryInterface $dcrRepo;
    private AuditService $audit;

    public function __construct(DcrRepositoryInterface $dcrRepo, AuditService $audit) {
        $this->dcrRepo = $dcrRepo;
        $this->audit = $audit;
    }

    public function createDcr(int $userId, array $data): array {
        // Validate date
        $date = $data['dcr_date'] ?? date('Y-m-d');
        
        // Check if DCR already exists for this user and date
        $existing = $this->dcrRepo->getDcrByDateAndUser($date, $userId);
        if ($existing) {
            throw new \Exception("A DCR for this date already exists.", 400);
        }

        $dcrData = [
            'user_id' => $userId,
            'territory_id' => $data['territory_id'],
            'dcr_date' => $date
        ];
        
        $id = $this->dcrRepo->createDcr($dcrData);
        $dcr = $this->dcrRepo->getDcrById($id);
        
        $this->audit->log('dcr.create', 'dcr', $id, null, $dcr);
        
        return $dcr;
    }
    
    public function getDcrWithVisits(int $id, int $userId): array {
        $dcr = $this->dcrRepo->getDcrById($id);
        if (!$dcr) {
            throw new \Exception("DCR not found", 404);
        }
        
        // Ensure the user owns this DCR (or is a manager, which we could check via roles)
        // For simplicity, just check ownership
        if ($dcr['user_id'] != $userId) {
            throw new \Exception("Unauthorized access to this DCR", 403);
        }
        
        $dcr['visits'] = $this->dcrRepo->listVisitsByDcr($id);
        return $dcr;
    }
    
    public function listUserDcrs(int $userId, ?string $date = null, ?string $status = null): array {
        return $this->dcrRepo->listDcrs($userId, $date, $status);
    }

    public function addVisit(int $dcrId, int $userId, array $visitData): array {
        $dcr = $this->dcrRepo->getDcrById($dcrId);
        if (!$dcr || $dcr['user_id'] != $userId) {
            throw new \Exception("DCR not found or unauthorized", 404);
        }
        
        if (in_array($dcr['status'], ['submitted', 'approved'])) {
            throw new \Exception("Cannot add visits to a {$dcr['status']} DCR", 400);
        }
        
        $visitId = $this->dcrRepo->addVisit($dcrId, $visitData);
        
        if (!empty($visitData['products'])) {
            $this->dcrRepo->addProductsToVisit($visitId, $visitData['products']);
        }
        
        $this->audit->log('dcr.visit.add', 'dcr_visit', $visitId, null, $visitData);
        
        return $this->dcrRepo->getVisitById($visitId);
    }
    
    public function updateStatus(int $dcrId, int $userId, string $status, ?string $managerNotes = null): array {
        $dcr = $this->dcrRepo->getDcrById($dcrId);
        if (!$dcr) {
            throw new \Exception("DCR not found", 404);
        }
        
        $validStatuses = ['draft', 'submitted', 'approved', 'rejected'];
        if (!in_array($status, $validStatuses)) {
            throw new \Exception("Invalid status", 400);
        }
        
        // Simple permission check: if transitioning from draft -> submitted, the owner can do it.
        // If approved/rejected, typically only a manager can do it. For this scope, we let it pass but
        // in reality you'd check roles.
        if ($status === 'submitted' && $dcr['user_id'] != $userId) {
            throw new \Exception("Only the owner can submit the DCR", 403);
        }
        
        $this->dcrRepo->updateDcrStatus($dcrId, $status, $managerNotes);
        
        $newDcr = $this->dcrRepo->getDcrById($dcrId);
        $this->audit->log('dcr.status.update', 'dcr', $dcrId, ['status' => $dcr['status']], ['status' => $status]);
        
        return $newDcr;
    }
}
