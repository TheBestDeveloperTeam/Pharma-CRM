<?php
declare(strict_types=1);

// Tenancy configuration
return [
    // HTTP header used by Super Admin to specify tenant context
    'tenant_header'    => 'X-Tenant-Ref',

    // Default scope behavior — always enforce unless explicitly bypassed
    'enforce_scope'    => true,

    // Maximum franchises per organization
    'max_franchises'   => 50,

    // Cross-tenant bypass reasons that are audit-logged (R08)
    'bypass_reasons'   => [
        'super_admin_report',
        'super_admin_impersonation',
        'system_migration',
        'backup_verification',
    ],

    // Columns used for tenant scoping
    'org_column'       => 'org_ref',
    'franchise_column' => 'franchise_ref',
];
