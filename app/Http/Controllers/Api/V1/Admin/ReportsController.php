<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Container, TenantContext};
use App\Domain\Reports\ReportService;
use App\Domain\Authorization\AuthorizationService;
use App\Core\Exceptions\ForbiddenException;

final class ReportsController
{
    public function __construct(private ReportService $reportService, private AuthorizationService $authorization) {}

    private function getCtx(): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        $this->authorization->requirePermission($ctx, 'reports', 'view');
        // Existing report SQL aggregates cannot safely attribute every source row
        // to a territory. Until a report has a module-specific scoped query it is
        // deliberately unavailable outside ALL scope.
        if (!$ctx->isSuper() && $ctx->scopeFor('reports') !== 'ALL') {
            throw new ForbiddenException('REPORT_SCOPE_UNSUPPORTED', 'Reports require ALL scope until scoped report queries are configured.');
        }
        return $ctx;
    }

    public function show(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $type = (string)$r->param('type');

        try {
            $data = $this->reportService->generate($franchiseRef, $type, $r->all());
            return Response::json(200, $data, ['total' => count($data), 'report_type' => $type]);
        } catch (\InvalidArgumentException $e) {
            return Response::error(400, 'INVALID_REPORT_TYPE', $e->getMessage());
        }
    }

    public function exportCsv(Request $r): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();
        $type = (string)$r->param('type');

        try {
            $data = $this->reportService->generate($franchiseRef, $type, $r->all());
        } catch (\InvalidArgumentException $e) {
            return Response::error(400, 'INVALID_REPORT_TYPE', $e->getMessage());
        }

        $fh = fopen('php://temp', 'r+');
        if (!empty($data)) {
            fputcsv($fh, array_keys(reset($data)));
            foreach ($data as $row) {
                fputcsv($fh, array_values($row));
            }
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $type . '-' . date('Ymd') . '.csv"');
        echo $csv;
        exit(0);
    }
}
