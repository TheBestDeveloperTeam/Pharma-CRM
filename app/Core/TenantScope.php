<?php
declare(strict_types=1);
namespace App\Core;

final class TenantScope
{
    public function __construct(private TenantContext $ctx) {}

    public function where(string $alias = ''): array
    {
        $col = ($alias ? $alias . '.' : '') . 'franchise_ref';
        return [" $col = :__tenant", [':__tenant' => $this->ctx->requireFranchise()]];
    }

    public function insertDefaults(): array
    {
        return [
            'org_ref'        => $this->ctx->orgRef,
            'franchise_ref'  => $this->ctx->requireFranchise(),
            'created_by_ref' => $this->ctx->userRef,
        ];
    }
}
