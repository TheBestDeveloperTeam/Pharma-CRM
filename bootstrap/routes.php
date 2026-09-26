<?php
declare(strict_types=1);

// Routes registered here. Populated phase by phase.
return function(\App\Core\Router $router): void {
    // Health routes
    $router->get('/health', [\App\Http\Controllers\Api\V1\HealthController::class, 'health']);
    $router->get('/ready',  [\App\Http\Controllers\Api\V1\HealthController::class, 'ready']);
    $router->get('/api/v1/health', [\App\Http\Controllers\Api\V1\HealthController::class, 'health']);
    $router->get('/api/v1/ready',  [\App\Http\Controllers\Api\V1\HealthController::class, 'ready']);

    // OAuth & Auth
    $router->post('/api/v1/oauth/token', [\App\Http\Controllers\Api\V1\AuthController::class, 'token']);
    $router->post('/api/v1/oauth/revoke', [\App\Http\Controllers\Api\V1\AuthController::class, 'revoke']);
    $router->get('/api/v1/auth/me', [\App\Http\Controllers\Api\V1\AuthController::class, 'me']);

    // Geo Reference Endpoints (public)
    $router->get('/geo/states', [\App\Http\Controllers\Api\V1\GeoController::class, 'states']);
    $router->get('/geo/districts', [\App\Http\Controllers\Api\V1\GeoController::class, 'districts']);
    $router->get('/geo/pincodes/{pin}', [\App\Http\Controllers\Api\V1\GeoController::class, 'pincode']);
    $router->get('/api/v1/geo/states', [\App\Http\Controllers\Api\V1\GeoController::class, 'states']);
    $router->get('/api/v1/geo/districts', [\App\Http\Controllers\Api\V1\GeoController::class, 'districts']);
    $router->get('/api/v1/geo/pincodes/{pin}', [\App\Http\Controllers\Api\V1\GeoController::class, 'pincode']);

    // Super Admin Routes
    $router->get('/api/v1/super/organizations', [\App\Http\Controllers\Api\V1\Super\OrganizationsController::class, 'index']);
    $router->post('/api/v1/super/organizations', [\App\Http\Controllers\Api\V1\Super\OrganizationsController::class, 'create']);
    $router->get('/api/v1/super/organizations/{ref}', [\App\Http\Controllers\Api\V1\Super\OrganizationsController::class, 'show']);
    $router->patch('/api/v1/super/organizations/{ref}', [\App\Http\Controllers\Api\V1\Super\OrganizationsController::class, 'update']);

    $router->get('/api/v1/super/franchises', [\App\Http\Controllers\Api\V1\Super\FranchisesController::class, 'index']);
    $router->post('/api/v1/super/franchises', [\App\Http\Controllers\Api\V1\Super\FranchisesController::class, 'create']);
    $router->get('/api/v1/super/franchises/{ref}', [\App\Http\Controllers\Api\V1\Super\FranchisesController::class, 'show']);
    $router->patch('/api/v1/super/franchises/{ref}', [\App\Http\Controllers\Api\V1\Super\FranchisesController::class, 'update']);
    $router->post('/api/v1/super/franchises/{ref}/suspend', [\App\Http\Controllers\Api\V1\Super\FranchisesController::class, 'suspend']);
    $router->post('/api/v1/super/franchises/{ref}/activate', [\App\Http\Controllers\Api\V1\Super\FranchisesController::class, 'activate']);
    $router->post('/api/v1/super/franchises/{ref}/admins', [\App\Http\Controllers\Api\V1\Super\FranchisesController::class, 'createAdmin']);

    $router->post('/api/v1/super/impersonate/{ref}', [\App\Http\Controllers\Api\V1\Super\ImpersonationController::class, 'impersonate']);
    $router->get('/api/v1/super/dashboard/stats', [\App\Http\Controllers\Api\V1\Super\SuperMetricsController::class, 'stats']);
    $router->get('/api/v1/super/audit', [\App\Http\Controllers\Api\V1\Super\SuperMetricsController::class, 'audit']);
    $router->get('/api/v1/super/security-events', [\App\Http\Controllers\Api\V1\Super\SuperMetricsController::class, 'securityEvents']);

    // Franchise Admin Routes
    $router->get('/api/v1/admin/users', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'index']);
    $router->get('/api/v1/admin/audit', [\App\Http\Controllers\Api\V1\Admin\AuditLogsController::class, 'index']);
    $router->get('/api/v1/admin/dashboard', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'dashboard']);
    $router->get('/api/v1/admin/analytics/reports/{key}', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'report']);
    $router->post('/api/v1/admin/users', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'create']);
    $router->get('/api/v1/admin/users/{ref}', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'show']);
    $router->patch('/api/v1/admin/users/{ref}', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'update']);
    $router->post('/api/v1/admin/users/{ref}/activate', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'activate']);
    $router->post('/api/v1/admin/users/{ref}/deactivate', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'deactivate']);
    $router->post('/api/v1/admin/users/{ref}/reset-password', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'resetPassword']);
    $router->post('/api/v1/admin/users/{ref}/unlock', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'unlock']);

    // TASK-001: normalized roles, permissions, scopes and assignments
    $router->get('/api/v1/admin/roles', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'roles']);
    $router->post('/api/v1/admin/roles', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'createRole']);
    $router->get('/api/v1/admin/roles/{ref}', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'role']);
    $router->patch('/api/v1/admin/roles/{ref}', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'updateRole']);
    $router->post('/api/v1/admin/roles/{ref}/clone', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'cloneRole']);
    $router->delete('/api/v1/admin/roles/{ref}', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'deleteRole']);
    $router->get('/api/v1/admin/permissions', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'permissionCatalogue']);
    $router->post('/api/v1/admin/users/{ref}/roles', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'assignUserRole']);
    $router->delete('/api/v1/admin/users/{ref}/roles/{role_ref}', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'revokeUserRole']);

    $router->patch('/api/v1/admin/settings', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'update']);
    $router->get('/api/v1/admin/settings', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'listSettings']);

    // Admin Masters (Categories, Tiers, Transporters, Templates)
    $router->get('/api/v1/admin/categories', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'listCategories']);
    $router->post('/api/v1/admin/categories', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'createCategory']);
    $router->get('/api/v1/admin/categories/{ref}', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'showCategory']);
    $router->patch('/api/v1/admin/categories/{ref}', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'updateCategory']);
    $router->post('/api/v1/admin/categories/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'categoryStatus']);

    $router->get('/api/v1/admin/tiers', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'listTiers']);
    $router->post('/api/v1/admin/tiers', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'createTier']);
    $router->get('/api/v1/admin/tiers/{ref}', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'showTier']);
    $router->patch('/api/v1/admin/tiers/{ref}', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'updateTier']);
    $router->post('/api/v1/admin/tiers/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'tierStatus']);

    $router->get('/api/v1/admin/transporters', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'listTransporters']);
    $router->post('/api/v1/admin/transporters', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'createTransporter']);
    $router->get('/api/v1/admin/catalog-masters/{category}', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'index']);
    $router->post('/api/v1/admin/catalog-masters/{category}', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'create']);
    $router->get('/api/v1/admin/catalog-masters/{category}/{ref}', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'show']);
    $router->patch('/api/v1/admin/catalog-masters/{category}/{ref}', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'update']);
    $router->post('/api/v1/admin/catalog-masters/{category}/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'status']);

    $router->get('/api/v1/admin/notification-templates', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'listTemplates']);

    // Admin Products
    $router->get('/api/v1/admin/products', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'index']);
    $router->post('/api/v1/admin/products', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'create']);
    $router->get('/api/v1/admin/products/{ref}', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'show']);
    $router->patch('/api/v1/admin/products/{ref}', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'update']);
    $router->post('/api/v1/admin/products/{ref}/activate', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'activate']);
    $router->post('/api/v1/admin/products/{ref}/deactivate', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'deactivate']);
    $router->post('/api/v1/admin/products/{ref}/archive', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'archive']);
    $router->post('/api/v1/admin/products/{ref}/restore', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'restore']);
    $router->delete('/api/v1/admin/products/{ref}', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'delete']);

    // Admin Pricing
    $router->get('/api/v1/admin/prices', [\App\Http\Controllers\Api\V1\Admin\PricesController::class, 'index']);
    $router->post('/api/v1/admin/prices', [\App\Http\Controllers\Api\V1\Admin\PricesController::class, 'create']);
    $router->get('/api/v1/admin/prices/{ref}', [\App\Http\Controllers\Api\V1\Admin\PricesController::class, 'show']);
    $router->post('/api/v1/admin/prices/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\PricesController::class, 'status']);
    $router->post('/api/v1/admin/pricing/resolve', [\App\Http\Controllers\Api\V1\Admin\PricesController::class, 'resolve']);

    // Admin Schemes
    $router->get('/api/v1/admin/schemes', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'index']);
    $router->post('/api/v1/admin/schemes', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'create']);
    $router->get('/api/v1/admin/schemes/{ref}', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'show']);
    $router->patch('/api/v1/admin/schemes/{ref}', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'update']);
    $router->post('/api/v1/admin/schemes/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'status']);
    $router->post('/api/v1/admin/schemes/calculate', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'calculate']);

    // Admin & Sales Leads
    $router->get('/api/v1/admin/leads', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'index']);
    $router->post('/api/v1/admin/leads', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'store']);
    $router->get('/api/v1/admin/leads/{ref}', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'show']);
    $router->patch('/api/v1/admin/leads/{ref}', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'update']);
    $router->post('/api/v1/admin/leads/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'status']);
    $router->post('/api/v1/admin/leads/{ref}/assign', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'assign']);

    // Follow-ups
    $router->get('/api/v1/admin/follow-ups', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'index']);
    $router->post('/api/v1/admin/follow-ups', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'store']);
    $router->post('/api/v1/admin/follow-ups/{ref}/complete', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'complete']);
    $router->post('/api/v1/admin/follow-ups/{ref}/reschedule', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'reschedule']);

    // Parties
    $router->get('/api/v1/admin/parties', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'index']);
    $router->post('/api/v1/admin/parties', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'store']);
    $router->get('/api/v1/admin/parties/{ref}', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'show']);
    $router->patch('/api/v1/admin/parties/{ref}', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'update']);
    $router->post('/api/v1/admin/parties/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'status']);
    $router->post('/api/v1/admin/parties/{ref}/archive', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'archive']);
    $router->post('/api/v1/admin/parties/{ref}/restore', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'restore']);
    $router->get('/api/v1/admin/parties/{ref}/ledger', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'ledger']);

    // Territories
    $router->get('/api/v1/admin/territories', [\App\Http\Controllers\Api\V1\Admin\TerritoriesController::class, 'index']);
    $router->post('/api/v1/admin/territories', [\App\Http\Controllers\Api\V1\Admin\TerritoriesController::class, 'store']);
    $router->get('/api/v1/admin/territories/{ref}', [\App\Http\Controllers\Api\V1\Admin\TerritoriesController::class, 'show']);
    $router->patch('/api/v1/admin/territories/{ref}', [\App\Http\Controllers\Api\V1\Admin\TerritoriesController::class, 'update']);
    $router->post('/api/v1/admin/territories/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\TerritoriesController::class, 'status']);
    $router->post('/api/v1/admin/territories/resolve', [\App\Http\Controllers\Api\V1\Admin\TerritoriesController::class, 'resolve']);
    $router->post('/api/v1/admin/territories/validate', [\App\Http\Controllers\Api\V1\Admin\TerritoriesController::class, 'validate']);
    $router->post('/api/v1/admin/territories/override', [\App\Http\Controllers\Api\V1\Admin\TerritoriesController::class, 'override']);

    // Onboarding Invites & Public Registration
    $router->post('/api/v1/admin/onboarding/invite', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'invite']);
    $router->post('/api/v1/onboarding/register', [\App\Http\Controllers\Api\V1\PublicOnboardingController::class, 'register']);
    $router->get('/api/v1/admin/onboarding', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'index']);
    $router->get('/api/v1/admin/onboarding/{ref}', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'show']);
    $router->post('/api/v1/admin/onboarding/{ref}/approve', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'approve']);
    $router->post('/api/v1/admin/onboarding/{ref}/reject', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'reject']);
    $router->post('/api/v1/admin/onboarding/{ref}/request-info', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'requestInfo']);
    $router->post('/api/v1/admin/onboarding/{ref}/convert', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'convert']);
    $router->post('/api/v1/admin/onboarding/{ref}/kyc-documents/{document_ref}/verify', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'verifyKyc']);

    // TASK-009 online DCR (offline capture/synchronisation is intentionally not implemented).
    $router->get('/api/v1/portal/dcrs', [\App\Http\Controllers\Api\DCR\DcrController::class, 'index']);
    $router->post('/api/v1/portal/dcrs', [\App\Http\Controllers\Api\DCR\DcrController::class, 'store']);
    $router->get('/api/v1/portal/dcrs/{ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'show']);
    $router->patch('/api/v1/portal/dcrs/{ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'update']);
    $router->post('/api/v1/portal/dcrs/{ref}/status', [\App\Http\Controllers\Api\DCR\DcrController::class, 'status']);

    // Webhooks
    $router->get('/api/v1/admin/webhook-sources', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'index']);
    $router->post('/api/v1/admin/webhook-sources', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'store']);
    $router->post('/api/v1/webhooks/{slug}/leads', [\App\Http\Controllers\Api\V1\WebhookIngestionController::class, 'ingest']);

    // P4: Inventory Batches & Stock
    $router->get('/api/v1/admin/inventory/batches', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'index']);
    $router->get('/api/v1/admin/inventory/batches/{ref}', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'showBatch']);
    $router->post('/api/v1/admin/inventory/receive', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'receive']);
    $router->post('/api/v1/admin/inventory/batches/{ref}/adjust', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'adjust']);
    $router->get('/api/v1/admin/inventory/near-expiry', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'nearExpiry']);
    $router->get('/api/v1/admin/inventory/reservations/{order_ref}', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'reservations']);
    $router->post('/api/v1/admin/inventory/reservations/{order_ref}/release', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'release']);
    $router->post('/api/v1/admin/inventory/reservations/{order_ref}/consume', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'consume']);

    // P4: Orders
    $router->get('/api/v1/admin/orders', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'index']);
    $router->post('/api/v1/admin/orders', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'store']);
    $router->get('/api/v1/admin/orders/{ref}', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'show']);
    $router->patch('/api/v1/admin/orders/{ref}', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'updateDraft']);
    $router->delete('/api/v1/admin/orders/{ref}', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'deleteDraft']);
    $router->post('/api/v1/admin/orders/{ref}/submit', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'submit']);
    $router->post('/api/v1/admin/orders/{ref}/confirm', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'confirm']);
    $router->post('/api/v1/admin/orders/{ref}/cancel', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'cancel']);

    // P4: Invoices (Billing)
    $router->get('/api/v1/admin/invoices', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'index']);
    $router->post('/api/v1/admin/invoices/generate', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'generate']);
    $router->get('/api/v1/admin/invoices/by-order/{order_ref}', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'byOrder']);
    $router->get('/api/v1/admin/invoices/{ref}', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'show']);
    $router->post('/api/v1/admin/invoices/{ref}/cancel', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'cancel']);

    // P4: Dispatches
    $router->get('/api/v1/admin/dispatches', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'index']);
    $router->post('/api/v1/admin/dispatches', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'store']);
    $router->get('/api/v1/admin/dispatches/{ref}', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'show']);
    $router->post('/api/v1/admin/dispatches/{ref}/deliver', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'deliver']);

    // P4: Payments
    $router->get('/api/v1/admin/payments', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'index']);
    $router->post('/api/v1/admin/payments', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'store']);
    $router->get('/api/v1/admin/payments/{ref}', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'show']);
    $router->post('/api/v1/admin/payments/{ref}/allocations', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'allocate']);
    $router->post('/api/v1/admin/payments/{ref}/reverse', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'reverse']);
    $router->get('/api/v1/admin/outstanding', [\App\Http\Controllers\Api\V1\Admin\OutstandingController::class, 'index']);
    $router->get('/api/v1/admin/pdc', [\App\Http\Controllers\Api\V1\Admin\PdcsController::class, 'index']);
    $router->post('/api/v1/admin/pdc', [\App\Http\Controllers\Api\V1\Admin\PdcsController::class, 'store']);
    $router->get('/api/v1/admin/pdc/{ref}', [\App\Http\Controllers\Api\V1\Admin\PdcsController::class, 'show']);
    $router->post('/api/v1/admin/pdc/{ref}/realize', [\App\Http\Controllers\Api\V1\Admin\PdcsController::class, 'realize']);
    $router->post('/api/v1/admin/pdc/{ref}/bounce', [\App\Http\Controllers\Api\V1\Admin\PdcsController::class, 'bounce']);
    $router->post('/api/v1/admin/pdc/{ref}/cancel', [\App\Http\Controllers\Api\V1\Admin\PdcsController::class, 'cancel']);

    // P7: Reports
    $router->get('/api/v1/admin/reports/{type}', [\App\Http\Controllers\Api\V1\Admin\ReportsController::class, 'show']);
    $router->get('/api/v1/admin/reports/{type}/export', [\App\Http\Controllers\Api\V1\Admin\ReportsController::class, 'exportCsv']);

    // P5: Notifications
    $router->get('/api/v1/notifications', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'index']);
    $router->post('/api/v1/notifications/read-all', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'markAllRead']);
    $router->post('/api/v1/notifications/{ref}/read', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'markRead']);

    // P6: Distributor Portal API
    $router->get('/api/v1/portal/profile', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'profile']);
    $router->patch('/api/v1/portal/profile', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'updateProfile']);
    $router->get('/api/v1/portal/catalogue', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'catalogue']);
    $router->post('/api/v1/portal/cart/calculate', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'calculateCart']);
    $router->get('/api/v1/portal/orders', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'listOrders']);
    $router->post('/api/v1/portal/orders', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'placeOrder']);
    $router->get('/api/v1/portal/orders/{ref}', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'showOrder']);
    $router->post('/api/v1/portal/orders/{ref}/cancel', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'cancelOrder']);
    $router->get('/api/v1/portal/invoices', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'listInvoices']);
    $router->get('/api/v1/portal/dispatches', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'listDispatches']);
    $router->get('/api/v1/portal/outstanding', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'outstanding']);

    // Web shells (serve HTML views for the 4 surfaces)
    $router->get('/super/login',  [\App\Http\Controllers\Web\AuthController::class, 'superLogin']);
    $router->get('/admin/login',  [\App\Http\Controllers\Web\AuthController::class, 'adminLogin']);
    $router->get('/sales/login',  [\App\Http\Controllers\Web\AuthController::class, 'salesLogin']);
    $router->get('/portal/login', [\App\Http\Controllers\Web\AuthController::class, 'portalLogin']);

    $router->get('/super/dashboard',  [\App\Http\Controllers\Web\DashboardController::class, 'superDashboard']);
    $router->get('/admin/dashboard',  [\App\Http\Controllers\Web\DashboardController::class, 'adminDashboard']);
    $router->get('/admin/categories', [\App\Http\Controllers\Web\DashboardController::class, 'adminCategories']);
    $router->get('/admin/tiers',      [\App\Http\Controllers\Web\DashboardController::class, 'adminTiers']);
    $router->get('/admin/products',   [\App\Http\Controllers\Web\DashboardController::class, 'adminProducts']);
    $router->get('/admin/prices',     [\App\Http\Controllers\Web\DashboardController::class, 'adminPrices']);
    $router->get('/admin/schemes',     [\App\Http\Controllers\Web\DashboardController::class, 'adminSchemes']);
    $router->get('/admin/leads',       [\App\Http\Controllers\Web\DashboardController::class, 'adminLeads']);
    $router->get('/admin/follow-ups',  [\App\Http\Controllers\Web\DashboardController::class, 'adminFollowUps']);
    $router->get('/admin/parties',     [\App\Http\Controllers\Web\DashboardController::class, 'adminParties']);
    $router->get('/admin/territories', [\App\Http\Controllers\Web\DashboardController::class, 'adminTerritories']);
    $router->get('/admin/orders',      [\App\Http\Controllers\Web\DashboardController::class, 'adminOrders']);
    $router->get('/admin/invoices',    [\App\Http\Controllers\Web\DashboardController::class, 'adminInvoices']);
    $router->get('/admin/inventory',   [\App\Http\Controllers\Web\DashboardController::class, 'adminInventory']);
    $router->get('/admin/payments',    [\App\Http\Controllers\Web\DashboardController::class, 'adminPayments']);
    $router->get('/admin/dispatches',  [\App\Http\Controllers\Web\DashboardController::class, 'adminDispatches']);
    $router->get('/admin/users',       [\App\Http\Controllers\Web\DashboardController::class, 'adminUsers']);
    $router->get('/admin/settings',    [\App\Http\Controllers\Web\DashboardController::class, 'adminSettings']);
    $router->get('/admin/reports',     [\App\Http\Controllers\Web\DashboardController::class, 'adminReports']);
    $router->get('/admin/notifications', [\App\Http\Controllers\Web\DashboardController::class, 'adminNotifications']);
    $router->get('/sales/dashboard',   [\App\Http\Controllers\Web\DashboardController::class, 'salesDashboard']);
    $router->get('/portal/dashboard', [\App\Http\Controllers\Web\DashboardController::class, 'portalDashboard']);
};
