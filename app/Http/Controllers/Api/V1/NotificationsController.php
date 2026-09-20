<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;

use App\Core\{Request, Response, Container, TenantContext};
use App\Domain\Notifications\NotificationService;
use App\Core\Exceptions\ForbiddenException;

final class NotificationsController
{
    public function __construct(private NotificationService $notifService) {}

    private function getCtx(): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        if (!$ctx->userRef && !$ctx->partyRef) {
            throw new ForbiddenException('UNAUTHENTICATED', 'Authentication required.');
        }
        return $ctx;
    }

    public function index(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $limit = min(50, max(1, (int)$r->query('limit', 20)));

        $list = $this->notifService->listInApp(
            $franchiseRef,
            $ctx->userRef,
            $ctx->partyRef,
            $limit
        );

        return Response::json(200, $list, [
            'total' => count($list),
            'limit' => $limit
        ]);
    }

    public function markRead(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $notifRef = (string)($r->params['ref'] ?? '');

        $success = $this->notifService->markAsRead($franchiseRef, $notifRef, $ctx->userRef);

        return Response::json(200, [
            'notification_ref' => $notifRef,
            'read'             => $success
        ]);
    }

    public function markAllRead(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();

        $count = $this->notifService->markAllAsRead($franchiseRef, $ctx->userRef, $ctx->partyRef);

        return Response::json(200, [
            'marked_count' => $count
        ]);
    }
}
