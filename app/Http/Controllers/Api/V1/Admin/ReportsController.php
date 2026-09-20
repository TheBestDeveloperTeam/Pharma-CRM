<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1\Admin;

use App\Core\{Request, Response, Container, TenantContext};
use App\Domain\Reports\ReportService;
use App\Core\Exceptions\ForbiddenException;

final class ReportsController
{
    public function __construct(private ReportService $reportService) {}

    private function getCtx(): TenantContext
    {
        /** @var TenantContext $ctx */
        $ctx = Container::getInstance()->make(TenantContext::class);
        if (!$ctx->isAdmin() && !$ctx->isSuper()) {
            throw new ForbiddenException('FORBIDDEN', 'Franchise Admin or Super Admin permission required.');
        }
        return $ctx;
    }

    public function show(Request $r, string $type): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();

        try {
            $data = $this->reportService->generate($franchiseRef, $type, $r->all());
            return Response::json(200, $data, ['total' => count($data), 'report_type' => $type]);
        } catch (\InvalidArgumentException $e) {
            return Response::error(400, 'INVALID_REPORT_TYPE', $e->getMessage());
        }
    }

    public function exportCsv(Request $r, string $type): Response
    {
        $ctx = $this->getCtx();
        $franchiseRef = $ctx->requireFranchise();

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
