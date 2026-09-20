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
    $router->post('/api/v1/admin/users', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'create']);
    $router->get('/api/v1/admin/users/{ref}', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'show']);
    $router->patch('/api/v1/admin/users/{ref}', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'update']);
    $router->post('/api/v1/admin/users/{ref}/activate', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'activate']);
    $router->post('/api/v1/admin/users/{ref}/deactivate', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'deactivate']);
    $router->post('/api/v1/admin/users/{ref}/reset-password', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'resetPassword']);
    $router->post('/api/v1/admin/users/{ref}/unlock', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'unlock']);

    $router->patch('/api/v1/admin/settings', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'update']);
    $router->get('/api/v1/admin/settings', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'listSettings']);

    // Admin Masters (Categories, Tiers, Transporters, Templates)
    $router->get('/api/v1/admin/categories', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'listCategories']);
    $router->post('/api/v1/admin/categories', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'createCategory']);

    $router->get('/api/v1/admin/tiers', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'listTiers']);
    $router->post('/api/v1/admin/tiers', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'createTier']);

    $router->get('/api/v1/admin/transporters', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'listTransporters']);
    $router->post('/api/v1/admin/transporters', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'createTransporter']);

    $router->get('/api/v1/admin/notification-templates', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'listTemplates']);

    // Admin Products
    $router->get('/api/v1/admin/products', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'index']);
    $router->post('/api/v1/admin/products', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'create']);
    $router->get('/api/v1/admin/products/{ref}', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'show']);
    $router->patch('/api/v1/admin/products/{ref}', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'update']);
    $router->post('/api/v1/admin/products/{ref}/activate', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'activate']);
    $router->post('/api/v1/admin/products/{ref}/deactivate', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'deactivate']);
    $router->post('/api/v1/admin/products/{ref}/archive', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'archive']);

    // Admin Pricing
    $router->get('/api/v1/admin/prices', [\App\Http\Controllers\Api\V1\Admin\PricesController::class, 'index']);
    $router->post('/api/v1/admin/prices', [\App\Http\Controllers\Api\V1\Admin\PricesController::class, 'create']);
    $router->post('/api/v1/admin/pricing/resolve', [\App\Http\Controllers\Api\V1\Admin\PricesController::class, 'resolve']);

    // Admin Schemes
    $router->get('/api/v1/admin/schemes', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'index']);
    $router->post('/api/v1/admin/schemes', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'create']);
    $router->get('/api/v1/admin/schemes/{ref}', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'show']);
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
    $router->post('/api/v1/admin/parties/{ref}/archive', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'archive']);
    $router->get('/api/v1/admin/parties/{ref}/ledger', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'ledger']);

    // Territories
    $router->get('/api/v1/admin/territories', [\App\Http\Controllers\Api\V1\Admin\TerritoriesController::class, 'index']);
    $router->post('/api/v1/admin/territories', [\App\Http\Controllers\Api\V1\Admin\TerritoriesController::class, 'store']);
    $router->post('/api/v1/admin/territories/validate', [\App\Http\Controllers\Api\V1\Admin\TerritoriesController::class, 'validate']);
    $router->post('/api/v1/admin/territories/override', [\App\Http\Controllers\Api\V1\Admin\TerritoriesController::class, 'override']);

    // Onboarding Invites & Public Registration
    $router->post('/api/v1/admin/onboarding/invite', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'invite']);
    $router->post('/api/v1/onboarding/register', [\App\Http\Controllers\Api\V1\PublicOnboardingController::class, 'register']);

    // Webhooks
    $router->get('/api/v1/admin/webhook-sources', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'index']);
    $router->post('/api/v1/admin/webhook-sources', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'store']);
    $router->post('/api/v1/webhooks/{slug}/leads', [\App\Http\Controllers\Api\V1\WebhookIngestionController::class, 'ingest']);

    // P4: Inventory Batches & Stock
    $router->get('/api/v1/admin/inventory/batches/{ref}', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'showBatch']);
    $router->post('/api/v1/admin/inventory/receive', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'receive']);
    $router->post('/api/v1/admin/inventory/batches/{ref}/adjust', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'adjust']);
    $router->get('/api/v1/admin/inventory/near-expiry', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'nearExpiry']);

    // P4: Orders
    $router->get('/api/v1/admin/orders', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'index']);
    $router->post('/api/v1/admin/orders', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'store']);
    $router->get('/api/v1/admin/orders/{ref}', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'show']);
    $router->post('/api/v1/admin/orders/{ref}/confirm', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'confirm']);
    $router->post('/api/v1/admin/orders/{ref}/cancel', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'cancel']);

    // P4: Invoices (Billing)
    $router->get('/api/v1/admin/invoices', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'index']);
    $router->post('/api/v1/admin/invoices/generate', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'generate']);
    $router->get('/api/v1/admin/invoices/{ref}', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'show']);

    // P4: Dispatches
    $router->get('/api/v1/admin/dispatches', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'index']);
    $router->post('/api/v1/admin/dispatches', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'store']);
    $router->get('/api/v1/admin/dispatches/{ref}', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'show']);
    $router->post('/api/v1/admin/dispatches/{ref}/deliver', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'deliver']);

    // P4: Payments
    $router->get('/api/v1/admin/payments', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'index']);
    $router->post('/api/v1/admin/payments', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'store']);
    $router->get('/api/v1/admin/payments/{ref}', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'show']);

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
    $router->get('/sales/dashboard',   [\App\Http\Controllers\Web\DashboardController::class, 'salesDashboard']);
    $router->get('/portal/dashboard', [\App\Http\Controllers\Web\DashboardController::class, 'portalDashboard']);
};
