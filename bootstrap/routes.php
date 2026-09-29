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
    $router->get('/api/v1/auth/effective-permissions', [\App\Http\Controllers\Api\V1\AuthController::class, 'effectivePermissions']);
    $router->post('/api/v1/auth/change-password', [\App\Http\Controllers\Api\V1\AuthController::class, 'changePassword']);
    $router->post('/api/v1/auth/refresh-token', [\App\Http\Controllers\Api\V1\AuthController::class, 'refreshToken']);
    $router->get('/api/v1/auth/sessions', [\App\Http\Controllers\Api\V1\AuthController::class, 'sessions']);
    $router->post('/api/v1/auth/logout-all', [\App\Http\Controllers\Api\V1\AuthController::class, 'logoutAll']);

    // Global Search (UTL-001)
    $router->get('/api/v1/search', [\App\Http\Controllers\Api\V1\SearchController::class, 'search']);

    // Geo Reference & Cascading Endpoints (public)
    $router->get('/geo/states', [\App\Http\Controllers\Api\V1\GeoController::class, 'states']);
    $router->get('/geo/districts', [\App\Http\Controllers\Api\V1\GeoController::class, 'districts']);
    $router->get('/geo/pincodes/{pin}', [\App\Http\Controllers\Api\V1\GeoController::class, 'pincode']);
    $router->get('/api/v1/geo/states', [\App\Http\Controllers\Api\V1\GeoController::class, 'states']);
    $router->get('/api/v1/geo/states/{state_ref}/districts', [\App\Http\Controllers\Api\V1\GeoController::class, 'districtsByState']);
    $router->get('/api/v1/geo/districts', [\App\Http\Controllers\Api\V1\GeoController::class, 'districts']);
    $router->get('/api/v1/geo/districts/{district_ref}/cities', [\App\Http\Controllers\Api\V1\GeoController::class, 'citiesByDistrict']);
    $router->get('/api/v1/geo/cities', [\App\Http\Controllers\Api\V1\GeoController::class, 'cities']);
    $router->get('/api/v1/geo/cities/{city_ref}/pincodes', [\App\Http\Controllers\Api\V1\GeoController::class, 'pincodesByCity']);
    $router->get('/api/v1/geo/pincodes', [\App\Http\Controllers\Api\V1\GeoController::class, 'pincodes']);
    $router->get('/api/v1/geo/pincodes/{pin}', [\App\Http\Controllers\Api\V1\GeoController::class, 'pincode']);
    $router->get('/api/v1/geo/search', [\App\Http\Controllers\Api\V1\GeoController::class, 'search']);

    // Admin Geo Management
    $router->post('/api/v1/admin/geo/states', [\App\Http\Controllers\Api\V1\GeoController::class, 'createState']);
    $router->get('/api/v1/admin/geo/states/{ref}', [\App\Http\Controllers\Api\V1\GeoController::class, 'showState']);
    $router->patch('/api/v1/admin/geo/states/{ref}', [\App\Http\Controllers\Api\V1\GeoController::class, 'updateState']);
    $router->delete('/api/v1/admin/geo/states/{ref}', [\App\Http\Controllers\Api\V1\GeoController::class, 'deleteState']);
    $router->post('/api/v1/admin/geo/districts', [\App\Http\Controllers\Api\V1\GeoController::class, 'createDistrict']);
    $router->get('/api/v1/admin/geo/districts/{ref}', [\App\Http\Controllers\Api\V1\GeoController::class, 'showDistrict']);
    $router->patch('/api/v1/admin/geo/districts/{ref}', [\App\Http\Controllers\Api\V1\GeoController::class, 'updateDistrict']);
    $router->delete('/api/v1/admin/geo/districts/{ref}', [\App\Http\Controllers\Api\V1\GeoController::class, 'deleteDistrict']);
    $router->post('/api/v1/admin/geo/cities', [\App\Http\Controllers\Api\V1\GeoController::class, 'createCity']);
    $router->get('/api/v1/admin/geo/cities/{ref}', [\App\Http\Controllers\Api\V1\GeoController::class, 'showCity']);
    $router->patch('/api/v1/admin/geo/cities/{ref}', [\App\Http\Controllers\Api\V1\GeoController::class, 'updateCity']);
    $router->delete('/api/v1/admin/geo/cities/{ref}', [\App\Http\Controllers\Api\V1\GeoController::class, 'deleteCity']);
    $router->post('/api/v1/admin/geo/pincodes', [\App\Http\Controllers\Api\V1\GeoController::class, 'createPincode']);
    $router->patch('/api/v1/admin/geo/pincodes/{ref}', [\App\Http\Controllers\Api\V1\GeoController::class, 'updatePincode']);
    $router->delete('/api/v1/admin/geo/pincodes/{ref}', [\App\Http\Controllers\Api\V1\GeoController::class, 'deletePincode']);

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
    $router->get('/api/v1/admin/users/dropdown', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'dropdown']);
    $router->get('/api/v1/admin/users', [\App\Http\Controllers\Api\V1\Admin\UsersController::class, 'index']);
    $router->get('/api/v1/admin/audit/export', [\App\Http\Controllers\Api\V1\Admin\AuditLogsController::class, 'export']);
    $router->get('/api/v1/admin/audit/security-events', [\App\Http\Controllers\Api\V1\Admin\AuditLogsController::class, 'securityEvents']);
    $router->get('/api/v1/admin/audit/entity/{entity_type}/{entity_ref}', [\App\Http\Controllers\Api\V1\Admin\AuditLogsController::class, 'entityAudit']);
    $router->get('/api/v1/admin/audit/user/{user_ref}', [\App\Http\Controllers\Api\V1\Admin\AuditLogsController::class, 'userAudit']);
    $router->get('/api/v1/admin/audit/{ref}', [\App\Http\Controllers\Api\V1\Admin\AuditLogsController::class, 'show']);
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
    $router->get('/api/v1/admin/roles/matrix', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'matrix']);
    $router->get('/api/v1/admin/roles', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'roles']);
    $router->post('/api/v1/admin/roles', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'createRole']);
    $router->get('/api/v1/admin/roles/{ref}', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'role']);
    $router->patch('/api/v1/admin/roles/{ref}', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'updateRole']);
    $router->post('/api/v1/admin/roles/{ref}/clone', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'cloneRole']);
    $router->post('/api/v1/admin/roles/{ref}/activate', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'activate']);
    $router->post('/api/v1/admin/roles/{ref}/deactivate', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'deactivate']);
    $router->get('/api/v1/admin/roles/{ref}/users', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'roleUsers']);
    $router->delete('/api/v1/admin/roles/{ref}', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'deleteRole']);
    $router->get('/api/v1/admin/permissions', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'permissionCatalogue']);
    $router->post('/api/v1/admin/users/{ref}/roles', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'assignUserRole']);
    $router->delete('/api/v1/admin/users/{ref}/roles/{role_ref}', [\App\Http\Controllers\Api\V1\Admin\AuthorizationController::class, 'revokeUserRole']);

    // Settings & Configuration (SET-001 to SET-016)
    $router->get('/api/v1/admin/settings/near-expiry-thresholds', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'getNearExpiryThresholds']);
    $router->patch('/api/v1/admin/settings/near-expiry-thresholds', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'updateNearExpiryThresholds']);
    $router->get('/api/v1/admin/settings/sla', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'getSla']);
    $router->patch('/api/v1/admin/settings/sla', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'updateSla']);
    $router->get('/api/v1/admin/settings/territory-policy', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'getTerritoryPolicy']);
    $router->patch('/api/v1/admin/settings/territory-policy', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'updateTerritoryPolicy']);
    $router->get('/api/v1/admin/settings/credit-policy', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'getCreditPolicy']);
    $router->patch('/api/v1/admin/settings/credit-policy', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'updateCreditPolicy']);
    $router->get('/api/v1/admin/settings/dcr-config', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'getDcrConfig']);
    $router->patch('/api/v1/admin/settings/dcr-config', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'updateDcrConfig']);
    $router->get('/api/v1/admin/settings/invite-config', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'getInviteConfig']);
    $router->patch('/api/v1/admin/settings/invite-config', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'updateInviteConfig']);
    $router->get('/api/v1/admin/settings/scheme-stacking', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'getSchemeStacking']);
    $router->patch('/api/v1/admin/settings/scheme-stacking', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'updateSchemeStacking']);
    $router->get('/api/v1/admin/settings/min-shelf-life', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'getMinShelfLife']);
    $router->patch('/api/v1/admin/settings/min-shelf-life', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'updateMinShelfLife']);
    $router->patch('/api/v1/admin/settings', [\App\Http\Controllers\Api\V1\Admin\SettingsController::class, 'update']);
    $router->get('/api/v1/admin/settings', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'listSettings']);

    // Admin Masters (Categories, Tiers, Transporters, Catalog Masters, Aliases)
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
    $router->get('/api/v1/admin/transporters/{ref}', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'showTransporter']);
    $router->patch('/api/v1/admin/transporters/{ref}', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'updateTransporter']);
    $router->post('/api/v1/admin/transporters/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'transporterStatus']);

    $router->get('/api/v1/admin/catalog-masters', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'listAll']);
    $router->get('/api/v1/admin/catalog-masters/{category}', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'index']);
    $router->post('/api/v1/admin/catalog-masters/{category}', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'create']);
    $router->get('/api/v1/admin/catalog-masters/{category}/{ref}', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'show']);
    $router->patch('/api/v1/admin/catalog-masters/{category}/{ref}', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'update']);
    $router->post('/api/v1/admin/catalog-masters/{category}/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'status']);

    // Admin Masters aliases (/api/v1/admin/masters/* -> CatalogMastersController)
    $router->get('/api/v1/admin/masters', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'listAll']);
    $router->get('/api/v1/admin/masters/{category}', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'index']);
    $router->post('/api/v1/admin/masters/{category}', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'create']);
    $router->get('/api/v1/admin/masters/{category}/{ref}', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'show']);
    $router->patch('/api/v1/admin/masters/{category}/{ref}', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'update']);
    $router->post('/api/v1/admin/masters/{category}/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\CatalogMastersController::class, 'status']);

    // Zero-Local-Data UI Dynamic Form Schemas
    $router->get('/api/v1/ui/forms', [\App\Http\Controllers\Api\V1\Admin\UiFormSchemasController::class, 'index']);
    $router->get('/api/v1/ui/forms/{form_key}', [\App\Http\Controllers\Api\V1\Admin\UiFormSchemasController::class, 'show']);
    $router->post('/api/v1/ui/forms/{form_key}/validate', [\App\Http\Controllers\Api\V1\Admin\UiFormSchemasController::class, 'validate']);
    $router->get('/api/v1/forms', [\App\Http\Controllers\Api\V1\Admin\UiFormSchemasController::class, 'index']);
    $router->get('/api/v1/forms/{form_key}', [\App\Http\Controllers\Api\V1\Admin\UiFormSchemasController::class, 'show']);
    $router->post('/api/v1/forms/{form_key}/validate', [\App\Http\Controllers\Api\V1\Admin\UiFormSchemasController::class, 'validate']);

    $router->get('/api/v1/admin/notification-templates', [\App\Http\Controllers\Api\V1\Admin\MastersController::class, 'listTemplates']);

    // Admin Products
    $router->get('/api/v1/admin/products/dropdown', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'dropdown']);
    $router->get('/api/v1/admin/products', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'index']);
    $router->post('/api/v1/admin/products', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'create']);
    $router->get('/api/v1/admin/products/{ref}', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'show']);
    $router->get('/api/v1/admin/products/{ref}/batches', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'batches']);
    $router->get('/api/v1/admin/products/{ref}/prices', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'prices']);
    $router->get('/api/v1/admin/products/{ref}/schemes', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'schemes']);
    $router->get('/api/v1/admin/products/{ref}/stock-summary', [\App\Http\Controllers\Api\V1\Admin\ProductsController::class, 'stockSummary']);
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
    $router->patch('/api/v1/admin/prices/{ref}', [\App\Http\Controllers\Api\V1\Admin\PricesController::class, 'update']);
    $router->delete('/api/v1/admin/prices/{ref}', [\App\Http\Controllers\Api\V1\Admin\PricesController::class, 'delete']);
    $router->post('/api/v1/admin/prices/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\PricesController::class, 'status']);
    $router->get('/api/v1/admin/prices/{ref}/history', [\App\Http\Controllers\Api\V1\Admin\PricesController::class, 'history']);
    $router->post('/api/v1/admin/pricing/resolve', [\App\Http\Controllers\Api\V1\Admin\PricesController::class, 'resolve']);

    // Admin Schemes
    $router->get('/api/v1/admin/schemes/active', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'active']);
    $router->post('/api/v1/admin/schemes/calculate', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'calculate']);
    $router->get('/api/v1/admin/schemes', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'index']);
    $router->post('/api/v1/admin/schemes', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'create']);
    $router->get('/api/v1/admin/schemes/{ref}', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'show']);
    $router->patch('/api/v1/admin/schemes/{ref}', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'update']);
    $router->delete('/api/v1/admin/schemes/{ref}', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'delete']);
    $router->post('/api/v1/admin/schemes/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'status']);
    $router->post('/api/v1/admin/schemes/{ref}/clone', [\App\Http\Controllers\Api\V1\Admin\SchemesController::class, 'cloneScheme']);

    // Admin & Sales Leads
    $router->get('/api/v1/admin/leads/check-duplicate', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'duplicateCheck']);
    $router->post('/api/v1/admin/leads/bulk-assign', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'bulkAssign']);
    $router->get('/api/v1/admin/leads', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'index']);
    $router->post('/api/v1/admin/leads', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'store']);
    $router->get('/api/v1/admin/leads/{ref}', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'show']);
    $router->patch('/api/v1/admin/leads/{ref}', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'update']);
    $router->post('/api/v1/admin/leads/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'status']);
    $router->post('/api/v1/admin/leads/{ref}/assign', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'assign']);
    $router->post('/api/v1/admin/leads/{ref}/archive', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'archive']);
    $router->post('/api/v1/admin/leads/{ref}/restore', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'restore']);
    $router->post('/api/v1/admin/leads/{ref}/convert', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'convert']);
    $router->get('/api/v1/admin/leads/{ref}/remarks', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'remarks']);
    $router->post('/api/v1/admin/leads/{ref}/remarks', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'addRemark']);
    $router->get('/api/v1/admin/leads/{ref}/timeline', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'timeline']);
    $router->get('/api/v1/admin/leads/{ref}/follow-ups', [\App\Http\Controllers\Api\V1\Admin\LeadsController::class, 'followUps']);

    // Follow-ups
    $router->get('/api/v1/admin/follow-ups/history', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'history']);
    $router->get('/api/v1/admin/follow-ups', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'index']);
    $router->post('/api/v1/admin/follow-ups', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'store']);
    $router->get('/api/v1/admin/follow-ups/{ref}', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'show']);
    $router->patch('/api/v1/admin/follow-ups/{ref}', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'update']);
    $router->post('/api/v1/admin/follow-ups/{ref}/complete', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'complete']);
    $router->post('/api/v1/admin/follow-ups/{ref}/reschedule', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'reschedule']);
    $router->post('/api/v1/admin/follow-ups/{ref}/mark-missed', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'markMissed']);
    $router->post('/api/v1/admin/follow-ups/{ref}/cancel', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'cancel']);
    $router->get('/api/v1/admin/follow-ups/{ref}/remarks', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'remarks']);
    $router->post('/api/v1/admin/follow-ups/{ref}/remarks', [\App\Http\Controllers\Api\V1\Admin\FollowUpsController::class, 'addRemark']);

    // Parties
    $router->get('/api/v1/admin/parties/dropdown', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'dropdown']);
    $router->get('/api/v1/admin/parties', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'index']);
    $router->post('/api/v1/admin/parties', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'store']);
    $router->get('/api/v1/admin/parties/{ref}', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'show']);
    $router->patch('/api/v1/admin/parties/{ref}', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'update']);
    $router->post('/api/v1/admin/parties/{ref}/status', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'status']);
    $router->post('/api/v1/admin/parties/{ref}/archive', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'archive']);
    $router->post('/api/v1/admin/parties/{ref}/restore', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'restore']);
    $router->get('/api/v1/admin/parties/{ref}/ledger', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'ledger']);
    $router->get('/api/v1/admin/parties/{ref}/orders', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'orders']);
    $router->get('/api/v1/admin/parties/{ref}/invoices', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'invoices']);
    $router->get('/api/v1/admin/parties/{ref}/payments', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'payments']);
    $router->post('/api/v1/admin/parties/{ref}/credit-limit', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'updateCreditLimit']);
    $router->post('/api/v1/admin/parties/{ref}/suspend', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'suspend']);
    $router->post('/api/v1/admin/parties/{ref}/activate', [\App\Http\Controllers\Api\V1\Admin\PartiesController::class, 'activate']);

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
    $router->get('/api/v1/onboarding/validate', [\App\Http\Controllers\Api\V1\PublicOnboardingController::class, 'validateToken']);
    $router->get('/api/v1/onboarding/invite-context', [\App\Http\Controllers\Api\V1\PublicOnboardingController::class, 'inviteContext']);
    $router->post('/api/v1/onboarding/register', [\App\Http\Controllers\Api\V1\PublicOnboardingController::class, 'register']);
    $router->post('/api/v1/admin/onboarding/invite', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'invite']);
    $router->post('/api/v1/admin/onboarding/invites/{ref}/resend', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'resend']);
    $router->post('/api/v1/admin/onboarding/invites/{ref}/revoke', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'revoke']);
    $router->get('/api/v1/admin/onboarding', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'index']);
    $router->get('/api/v1/admin/onboarding/{ref}', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'show']);
    $router->get('/api/v1/admin/onboarding/{ref}/documents', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'documents']);
    $router->get('/api/v1/admin/onboarding/{ref}/timeline', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'timeline']);
    $router->post('/api/v1/admin/onboarding/{ref}/approve', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'approve']);
    $router->post('/api/v1/admin/onboarding/{ref}/reject', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'reject']);
    $router->post('/api/v1/admin/onboarding/{ref}/request-info', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'requestInfo']);
    $router->post('/api/v1/admin/onboarding/{ref}/convert', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'convert']);
    $router->post('/api/v1/admin/onboarding/{ref}/kyc-documents/{document_ref}/verify', [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'verifyKyc']);

    // TASK-009 / DCR-001 to DCR-030: Field Operations & DCR Workflow
    // Admin DCR routes
    $router->get('/api/v1/admin/dcrs', [\App\Http\Controllers\Api\DCR\DcrController::class, 'index']);
    $router->post('/api/v1/admin/dcrs', [\App\Http\Controllers\Api\DCR\DcrController::class, 'store']);
    $router->get('/api/v1/admin/dcrs/{ref}/visits', [\App\Http\Controllers\Api\DCR\DcrController::class, 'visits']);
    $router->post('/api/v1/admin/dcrs/{ref}/visits', [\App\Http\Controllers\Api\DCR\DcrController::class, 'addVisit']);
    $router->patch('/api/v1/admin/dcrs/{ref}/visits/{visit_ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'updateVisit']);
    $router->delete('/api/v1/admin/dcrs/{ref}/visits/{visit_ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'deleteVisit']);
    $router->post('/api/v1/admin/dcrs/{ref}/submit', [\App\Http\Controllers\Api\DCR\DcrController::class, 'submit']);
    $router->post('/api/v1/admin/dcrs/{ref}/approve', [\App\Http\Controllers\Api\DCR\DcrController::class, 'approve']);
    $router->post('/api/v1/admin/dcrs/{ref}/reject', [\App\Http\Controllers\Api\DCR\DcrController::class, 'reject']);
    $router->post('/api/v1/admin/dcrs/{ref}/reopen', [\App\Http\Controllers\Api\DCR\DcrController::class, 'reopen']);
    $router->get('/api/v1/admin/dcrs/{ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'show']);
    $router->patch('/api/v1/admin/dcrs/{ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'update']);
    $router->post('/api/v1/admin/dcrs/{ref}/status', [\App\Http\Controllers\Api\DCR\DcrController::class, 'status']);

    // Portal DCR routes
    $router->get('/api/v1/portal/dcrs', [\App\Http\Controllers\Api\DCR\DcrController::class, 'index']);
    $router->post('/api/v1/portal/dcrs', [\App\Http\Controllers\Api\DCR\DcrController::class, 'store']);
    $router->get('/api/v1/portal/dcrs/{ref}/visits', [\App\Http\Controllers\Api\DCR\DcrController::class, 'visits']);
    $router->post('/api/v1/portal/dcrs/{ref}/visits', [\App\Http\Controllers\Api\DCR\DcrController::class, 'addVisit']);
    $router->patch('/api/v1/portal/dcrs/{ref}/visits/{visit_ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'updateVisit']);
    $router->delete('/api/v1/portal/dcrs/{ref}/visits/{visit_ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'deleteVisit']);
    $router->post('/api/v1/portal/dcrs/{ref}/submit', [\App\Http\Controllers\Api\DCR\DcrController::class, 'submit']);
    $router->post('/api/v1/portal/dcrs/{ref}/approve', [\App\Http\Controllers\Api\DCR\DcrController::class, 'approve']);
    $router->post('/api/v1/portal/dcrs/{ref}/reject', [\App\Http\Controllers\Api\DCR\DcrController::class, 'reject']);
    $router->post('/api/v1/portal/dcrs/{ref}/reopen', [\App\Http\Controllers\Api\DCR\DcrController::class, 'reopen']);
    $router->get('/api/v1/portal/dcrs/{ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'show']);
    $router->patch('/api/v1/portal/dcrs/{ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'update']);
    $router->post('/api/v1/portal/dcrs/{ref}/status', [\App\Http\Controllers\Api\DCR\DcrController::class, 'status']);

    // Field Customers (Doctor/Chemist/Clinic/Stockist)
    $router->get('/api/v1/portal/field-customers', [\App\Http\Controllers\Api\DCR\DcrController::class, 'listFieldCustomers']);
    $router->post('/api/v1/portal/field-customers', [\App\Http\Controllers\Api\DCR\DcrController::class, 'createFieldCustomer']);
    $router->get('/api/v1/portal/field-customers/{ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'showFieldCustomer']);
    $router->patch('/api/v1/portal/field-customers/{ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'updateFieldCustomer']);
    $router->post('/api/v1/portal/field-customers/{ref}/status', [\App\Http\Controllers\Api\DCR\DcrController::class, 'statusFieldCustomer']);
    $router->get('/api/v1/admin/field-customers', [\App\Http\Controllers\Api\DCR\DcrController::class, 'listFieldCustomers']);
    $router->post('/api/v1/admin/field-customers', [\App\Http\Controllers\Api\DCR\DcrController::class, 'createFieldCustomer']);
    $router->get('/api/v1/admin/field-customers/{ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'showFieldCustomer']);
    $router->patch('/api/v1/admin/field-customers/{ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'updateFieldCustomer']);
    $router->post('/api/v1/admin/field-customers/{ref}/status', [\App\Http\Controllers\Api\DCR\DcrController::class, 'statusFieldCustomer']);

    // Beats & Areas
    $router->get('/api/v1/portal/beats', [\App\Http\Controllers\Api\DCR\DcrController::class, 'listBeats']);
    $router->post('/api/v1/portal/beats', [\App\Http\Controllers\Api\DCR\DcrController::class, 'createBeat']);
    $router->patch('/api/v1/portal/beats/{ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'updateBeat']);
    $router->get('/api/v1/admin/beats', [\App\Http\Controllers\Api\DCR\DcrController::class, 'listBeats']);
    $router->post('/api/v1/admin/beats', [\App\Http\Controllers\Api\DCR\DcrController::class, 'createBeat']);
    $router->patch('/api/v1/admin/beats/{ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'updateBeat']);

    // Tour Plans
    $router->get('/api/v1/portal/tour-plans/{ref}/actual-comparison', [\App\Http\Controllers\Api\DCR\DcrController::class, 'tourPlanComparison']);
    $router->get('/api/v1/portal/tour-plans', [\App\Http\Controllers\Api\DCR\DcrController::class, 'listTourPlans']);
    $router->post('/api/v1/portal/tour-plans', [\App\Http\Controllers\Api\DCR\DcrController::class, 'createTourPlan']);
    $router->patch('/api/v1/portal/tour-plans/{ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'updateTourPlan']);
    $router->get('/api/v1/admin/tour-plans/{ref}/actual-comparison', [\App\Http\Controllers\Api\DCR\DcrController::class, 'tourPlanComparison']);
    $router->get('/api/v1/admin/tour-plans', [\App\Http\Controllers\Api\DCR\DcrController::class, 'listTourPlans']);
    $router->post('/api/v1/admin/tour-plans', [\App\Http\Controllers\Api\DCR\DcrController::class, 'createTourPlan']);
    $router->patch('/api/v1/admin/tour-plans/{ref}', [\App\Http\Controllers\Api\DCR\DcrController::class, 'updateTourPlan']);

    // POB (Person Order Booking)
    $router->get('/api/v1/portal/pob', [\App\Http\Controllers\Api\DCR\DcrController::class, 'listPob']);
    $router->post('/api/v1/portal/pob/convert-to-order', [\App\Http\Controllers\Api\DCR\DcrController::class, 'convertToOrder']);
    $router->get('/api/v1/admin/pob', [\App\Http\Controllers\Api\DCR\DcrController::class, 'listPob']);
    $router->post('/api/v1/admin/pob/convert-to-order', [\App\Http\Controllers\Api\DCR\DcrController::class, 'convertToOrder']);

    // DCR Reports
    $router->get('/api/v1/portal/dcr-reports/daily-summary', [\App\Http\Controllers\Api\DCR\DcrController::class, 'dailySummary']);
    $router->get('/api/v1/portal/dcr-reports/monthly-summary', [\App\Http\Controllers\Api\DCR\DcrController::class, 'monthlySummary']);
    $router->get('/api/v1/portal/dcr-reports/coverage', [\App\Http\Controllers\Api\DCR\DcrController::class, 'coverageReport']);
    $router->get('/api/v1/portal/dcr-reports/product-promotion', [\App\Http\Controllers\Api\DCR\DcrController::class, 'productPromotionReport']);
    $router->get('/api/v1/portal/dcr-reports/missed-dcr', [\App\Http\Controllers\Api\DCR\DcrController::class, 'missedDcrReport']);
    $router->get('/api/v1/portal/dcr-reports/sample-gift', [\App\Http\Controllers\Api\DCR\DcrController::class, 'sampleGiftReport']);
    $router->get('/api/v1/portal/dcr-reports/expense', [\App\Http\Controllers\Api\DCR\DcrController::class, 'expenseReport']);
    $router->get('/api/v1/portal/dcr-reports/pob-summary', [\App\Http\Controllers\Api\DCR\DcrController::class, 'pobSummaryReport']);
    $router->get('/api/v1/admin/dcr-reports/daily-summary', [\App\Http\Controllers\Api\DCR\DcrController::class, 'dailySummary']);
    $router->get('/api/v1/admin/dcr-reports/monthly-summary', [\App\Http\Controllers\Api\DCR\DcrController::class, 'monthlySummary']);
    $router->get('/api/v1/admin/dcr-reports/coverage', [\App\Http\Controllers\Api\DCR\DcrController::class, 'coverageReport']);
    $router->get('/api/v1/admin/dcr-reports/product-promotion', [\App\Http\Controllers\Api\DCR\DcrController::class, 'productPromotionReport']);
    $router->get('/api/v1/admin/dcr-reports/missed-dcr', [\App\Http\Controllers\Api\DCR\DcrController::class, 'missedDcrReport']);
    $router->get('/api/v1/admin/dcr-reports/sample-gift', [\App\Http\Controllers\Api\DCR\DcrController::class, 'sampleGiftReport']);
    $router->get('/api/v1/admin/dcr-reports/expense', [\App\Http\Controllers\Api\DCR\DcrController::class, 'expenseReport']);
    $router->get('/api/v1/admin/dcr-reports/pob-summary', [\App\Http\Controllers\Api\DCR\DcrController::class, 'pobSummaryReport']);

    // Webhooks & Events (WHK-001 to WHK-008)
    $router->get('/api/v1/admin/webhook-events/failures', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'failedEvents']);
    $router->get('/api/v1/admin/webhook-events', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'listEvents']);
    $router->get('/api/v1/admin/webhook-events/{ref}', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'showEvent']);
    $router->post('/api/v1/admin/webhook-events/{ref}/retry', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'retryEvent']);
    $router->get('/api/v1/admin/webhook-sources', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'index']);
    $router->post('/api/v1/admin/webhook-sources', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'store']);
    $router->get('/api/v1/admin/webhook-sources/{ref}/events', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'sourceEvents']);
    $router->post('/api/v1/admin/webhook-sources/{ref}/test', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'test']);
    $router->post('/api/v1/admin/webhook-sources/{ref}/regenerate-secret', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'regenerateSecret']);
    $router->get('/api/v1/admin/webhook-sources/{ref}', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'show']);
    $router->delete('/api/v1/admin/webhook-sources/{ref}', [\App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class, 'delete']);
    $router->post('/api/v1/webhooks/{slug}/leads', [\App\Http\Controllers\Api\V1\WebhookIngestionController::class, 'ingest']);

    // Audit Logs (AUD-001 to AUD-005)
    $router->get('/api/v1/admin/audit/export', [\App\Http\Controllers\Api\V1\Admin\AuditLogsController::class, 'export']);
    $router->get('/api/v1/admin/audit/security-events', [\App\Http\Controllers\Api\V1\Admin\AuditLogsController::class, 'securityEvents']);
    $router->get('/api/v1/admin/audit/entity/{entity_type}/{entity_ref}', [\App\Http\Controllers\Api\V1\Admin\AuditLogsController::class, 'entityAudit']);
    $router->get('/api/v1/admin/audit/user/{user_ref}', [\App\Http\Controllers\Api\V1\Admin\AuditLogsController::class, 'userAudit']);
    $router->get('/api/v1/admin/audit/{ref}', [\App\Http\Controllers\Api\V1\Admin\AuditLogsController::class, 'show']);
    $router->get('/api/v1/admin/audit', [\App\Http\Controllers\Api\V1\Admin\AuditLogsController::class, 'index']);

    // P4: Inventory Batches & Stock
    $router->get('/api/v1/admin/inventory/stock-summary', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'stockSummary']);
    $router->get('/api/v1/admin/inventory/near-expiry', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'nearExpiry']);
    $router->get('/api/v1/admin/inventory/expired', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'expired']);
    $router->get('/api/v1/admin/inventory/movements', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'movements']);
    $router->get('/api/v1/admin/inventory/batches', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'index']);
    $router->post('/api/v1/admin/inventory/receive', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'receive']);
    $router->post('/api/v1/admin/inventory/transfer', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'transfer']);
    $router->get('/api/v1/admin/inventory/reservations/{order_ref}', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'reservations']);
    $router->post('/api/v1/admin/inventory/reservations/{order_ref}/release', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'release']);
    $router->post('/api/v1/admin/inventory/reservations/{order_ref}/consume', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'consume']);
    $router->get('/api/v1/admin/inventory/batches/{ref}', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'showBatch']);
    $router->post('/api/v1/admin/inventory/batches/{ref}/adjust', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'adjust']);
    $router->post('/api/v1/admin/inventory/batches/{ref}/edit', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'editBatch']);
    $router->post('/api/v1/admin/inventory/batches/{ref}/quarantine', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'quarantine']);
    $router->post('/api/v1/admin/inventory/batches/{ref}/unquarantine', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'unquarantine']);
    $router->post('/api/v1/admin/inventory/batches/{ref}/damage', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'damage']);
    $router->get('/api/v1/admin/inventory/batches/{ref}/movements', [\App\Http\Controllers\Api\V1\Admin\InventoryController::class, 'batchMovements']);

    // P4: Orders
    $router->get('/api/v1/admin/orders/pending-dispatch', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'pendingDispatch']);
    $router->get('/api/v1/admin/orders/pending-billing', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'pendingBilling']);
    $router->get('/api/v1/admin/orders/blocked', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'blocked']);
    $router->get('/api/v1/admin/orders', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'index']);
    $router->post('/api/v1/admin/orders', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'store']);
    $router->post('/api/v1/admin/orders/calculate', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'calculate']);
    $router->get('/api/v1/admin/orders/{ref}', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'show']);
    $router->get('/api/v1/admin/orders/{ref}/items', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'items']);
    $router->get('/api/v1/admin/orders/{ref}/timeline', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'timeline']);
    $router->get('/api/v1/admin/orders/{ref}/invoices', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'invoices']);
    $router->get('/api/v1/admin/orders/{ref}/dispatches', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'dispatches']);
    $router->get('/api/v1/admin/orders/{ref}/payments', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'payments']);
    $router->get('/api/v1/admin/orders/{ref}/reservations', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'reservations']);
    $router->patch('/api/v1/admin/orders/{ref}', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'updateDraft']);
    $router->delete('/api/v1/admin/orders/{ref}', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'deleteDraft']);
    $router->post('/api/v1/admin/orders/{ref}/submit', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'submit']);
    $router->post('/api/v1/admin/orders/{ref}/confirm', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'confirm']);
    $router->post('/api/v1/admin/orders/{ref}/cancel', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'cancel']);
    $router->post('/api/v1/admin/orders/{ref}/reopen', [\App\Http\Controllers\Api\V1\Admin\OrdersController::class, 'reopen']);

    // P4: Invoices (Billing)
    $router->get('/api/v1/admin/invoices', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'index']);
    $router->post('/api/v1/admin/invoices/generate', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'generate']);
    $router->get('/api/v1/admin/invoices/by-order/{order_ref}', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'byOrder']);
    $router->get('/api/v1/admin/invoices/{ref}', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'show']);
    $router->get('/api/v1/admin/invoices/{ref}/items', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'items']);
    $router->get('/api/v1/admin/invoices/{ref}/payments', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'payments']);
    $router->get('/api/v1/admin/invoices/{ref}/dispatch', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'dispatch']);
    $router->post('/api/v1/admin/invoices/{ref}/cancel', [\App\Http\Controllers\Api\V1\Admin\InvoicesController::class, 'cancel']);

    // P4: Dispatches
    $router->get('/api/v1/admin/dispatches/pending', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'pending']);
    $router->get('/api/v1/admin/dispatches', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'index']);
    $router->post('/api/v1/admin/dispatches', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'store']);
    $router->get('/api/v1/admin/dispatches/{ref}', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'show']);
    $router->patch('/api/v1/admin/dispatches/{ref}', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'update']);
    $router->post('/api/v1/admin/dispatches/{ref}/packed', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'packed']);
    $router->post('/api/v1/admin/dispatches/{ref}/ship', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'ship']);
    $router->post('/api/v1/admin/dispatches/{ref}/in-transit', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'inTransit']);
    $router->patch('/api/v1/admin/dispatches/{ref}/lr', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'updateLr']);
    $router->post('/api/v1/admin/dispatches/{ref}/deliver', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'deliver']);
    $router->get('/api/v1/admin/dispatches/{ref}/timeline', [\App\Http\Controllers\Api\V1\Admin\DispatchesController::class, 'timeline']);

    // P4: Payments
    $router->get('/api/v1/admin/payments', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'index']);
    $router->post('/api/v1/admin/payments', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'store']);
    $router->get('/api/v1/admin/payments/{ref}', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'show']);
    $router->patch('/api/v1/admin/payments/{ref}', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'update']);
    $router->get('/api/v1/admin/payments/{ref}/allocations', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'allocations']);
    $router->get('/api/v1/admin/payments/{ref}/receipt', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'receipt']);
    $router->post('/api/v1/admin/payments/{ref}/allocations', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'allocate']);
    $router->post('/api/v1/admin/payments/{ref}/reverse', [\App\Http\Controllers\Api\V1\Admin\PaymentsController::class, 'reverse']);

    // Outstanding
    $router->get('/api/v1/admin/outstanding/ageing', [\App\Http\Controllers\Api\V1\Admin\OutstandingController::class, 'ageing']);
    $router->get('/api/v1/admin/outstanding/party/{party_ref}', [\App\Http\Controllers\Api\V1\Admin\OutstandingController::class, 'partyOutstanding']);
    $router->get('/api/v1/admin/outstanding', [\App\Http\Controllers\Api\V1\Admin\OutstandingController::class, 'index']);
    $router->get('/api/v1/admin/pdc', [\App\Http\Controllers\Api\V1\Admin\PdcsController::class, 'index']);
    $router->post('/api/v1/admin/pdc', [\App\Http\Controllers\Api\V1\Admin\PdcsController::class, 'store']);
    $router->get('/api/v1/admin/pdc/{ref}', [\App\Http\Controllers\Api\V1\Admin\PdcsController::class, 'show']);
    $router->post('/api/v1/admin/pdc/{ref}/realize', [\App\Http\Controllers\Api\V1\Admin\PdcsController::class, 'realize']);
    $router->post('/api/v1/admin/pdc/{ref}/bounce', [\App\Http\Controllers\Api\V1\Admin\PdcsController::class, 'bounce']);
    $router->post('/api/v1/admin/pdc/{ref}/cancel', [\App\Http\Controllers\Api\V1\Admin\PdcsController::class, 'cancel']);

    // P7: Dashboard Widgets (DSH-001 to DSH-011)
    $router->get('/api/v1/admin/dashboard/lead-stats', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'leadStats']);
    $router->get('/api/v1/admin/dashboard/follow-up-stats', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'followUpStats']);
    $router->get('/api/v1/admin/dashboard/sla-breach-trend', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'slaBreachTrend']);
    $router->get('/api/v1/admin/dashboard/lead-conversion-stage', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'leadConversionStage']);
    $router->get('/api/v1/admin/dashboard/order-stats', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'orderStats']);
    $router->get('/api/v1/admin/dashboard/territory-violations', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'territoryViolations']);
    $router->get('/api/v1/admin/dashboard/orders-sales-trend', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'ordersSalesTrend']);
    $router->get('/api/v1/admin/dashboard/outstanding-summary', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'outstandingSummary']);
    $router->get('/api/v1/admin/dashboard/outstanding-ageing', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'outstandingAgeing']);
    $router->get('/api/v1/admin/dashboard/near-expiry-value', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'nearExpiryValue']);
    $router->get('/api/v1/admin/dashboard/sales-team-productivity', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'salesTeamProductivity']);
    $router->get('/api/v1/admin/dashboard', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'dashboard']);

    // P7: Dedicated Report Endpoints (RPT-001 to RPT-016)
    $router->get('/api/v1/admin/reports/lead-source', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'leadSource']);
    $router->get('/api/v1/admin/reports/response-time', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'responseTime']);
    $router->get('/api/v1/admin/reports/conversion', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'conversion']);
    $router->get('/api/v1/admin/reports/sales-team-productivity', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'salesTeamProductivityReport']);
    $router->get('/api/v1/admin/reports/territory-sales', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'territorySales']);
    $router->get('/api/v1/admin/reports/party-sales', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'partySales']);
    $router->get('/api/v1/admin/reports/product-sales', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'productSales']);
    $router->get('/api/v1/admin/reports/scheme-utilization', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'schemeUtilization']);
    $router->get('/api/v1/admin/reports/order-status', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'orderStatus']);
    $router->get('/api/v1/admin/reports/dispatch-pending', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'dispatchPending']);
    $router->get('/api/v1/admin/reports/payment-outstanding', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'paymentOutstanding']);
    $router->get('/api/v1/admin/reports/batch-inventory', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'batchInventory']);
    $router->get('/api/v1/admin/reports/near-expiry', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'nearExpiryReport']);
    $router->get('/api/v1/admin/reports/territory-violations', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'territoryViolationsReport']);
    $router->get('/api/v1/admin/reports/webhook-failures', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'webhookFailures']);
    $router->get('/api/v1/admin/reports/whatsapp-delivery', [\App\Http\Controllers\Api\V1\Admin\AnalyticsController::class, 'whatsappDelivery']);
    $router->get('/api/v1/admin/reports/{type}', [\App\Http\Controllers\Api\V1\Admin\ReportsController::class, 'show']);
    $router->get('/api/v1/admin/reports/{type}/export', [\App\Http\Controllers\Api\V1\Admin\ReportsController::class, 'exportCsv']);

    // P5: Notifications & WhatsApp (NTF-001 to NTF-012)
    $router->get('/api/v1/notifications/unread-count', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'unreadCount']);
    $router->get('/api/v1/notifications', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'index']);
    $router->post('/api/v1/notifications/read-all', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'markAllRead']);
    $router->post('/api/v1/notifications/{ref}/read', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'markRead']);
    $router->delete('/api/v1/notifications/{ref}', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'delete']);
    $router->post('/api/v1/admin/notifications/send', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'sendSystem']);
    $router->get('/api/v1/admin/notifications/manage-templates', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'manageTemplates']);

    // WhatsApp routes
    $router->get('/api/v1/admin/whatsapp/delivery-report', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'whatsAppDeliveryReport']);
    $router->get('/api/v1/admin/whatsapp/messages', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'listWhatsAppMessages']);
    $router->post('/api/v1/admin/whatsapp/messages/{ref}/retry', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'retryWhatsAppMessage']);
    $router->get('/api/v1/admin/whatsapp/messages/{ref}', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'showWhatsAppMessage']);
    $router->post('/api/v1/admin/whatsapp/send', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'sendWhatsApp']);
    $router->get('/api/v1/admin/whatsapp/templates', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'listWhatsAppTemplates']);
    $router->post('/api/v1/admin/whatsapp/templates', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'createWhatsAppTemplate']);
    $router->patch('/api/v1/admin/whatsapp/templates/{ref}', [\App\Http\Controllers\Api\V1\NotificationsController::class, 'updateWhatsAppTemplate']);

    // P6: Distributor Portal API (PRT-001 to PRT-022)
    $router->get('/api/v1/portal/dashboard', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'dashboard']);
    $router->get('/api/v1/portal/profile', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'profile']);
    $router->patch('/api/v1/portal/profile', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'updateProfile']);
    $router->get('/api/v1/portal/catalogue', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'catalogue']);
    $router->post('/api/v1/portal/cart/calculate', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'calculateCart']);
    $router->get('/api/v1/portal/orders', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'listOrders']);
    $router->post('/api/v1/portal/orders', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'placeOrder']);
    $router->get('/api/v1/portal/orders/{ref}/timeline', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'orderTimeline']);
    $router->get('/api/v1/portal/orders/{ref}', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'showOrder']);
    $router->post('/api/v1/portal/orders/{ref}/cancel', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'cancelOrder']);
    $router->get('/api/v1/portal/invoices', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'listInvoices']);
    $router->get('/api/v1/portal/invoices/{ref}/download', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'downloadInvoice']);
    $router->get('/api/v1/portal/invoices/{ref}', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'showInvoice']);
    $router->get('/api/v1/portal/dispatches', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'listDispatches']);
    $router->get('/api/v1/portal/dispatches/{ref}', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'showDispatch']);
    $router->get('/api/v1/portal/payments', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'payments']);
    $router->get('/api/v1/portal/outstanding/ageing', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'outstandingAgeing']);
    $router->get('/api/v1/portal/outstanding', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'outstanding']);
    $router->get('/api/v1/portal/schemes', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'schemes']);
    $router->get('/api/v1/portal/support', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'support']);
    $router->get('/api/v1/portal/notifications', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'notifications']);
    $router->post('/api/v1/portal/notifications/{ref}/read', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'markNotificationRead']);
    $router->get('/api/v1/portal/shipping-addresses', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'listShippingAddresses']);
    $router->post('/api/v1/portal/shipping-addresses', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'addShippingAddress']);
    $router->patch('/api/v1/portal/shipping-addresses/{ref}', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'updateShippingAddress']);
    $router->delete('/api/v1/portal/shipping-addresses/{ref}', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'deleteShippingAddress']);
    $router->get('/api/v1/portal/team', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'listTeam']);
    $router->post('/api/v1/portal/team', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'createTeam']);
    $router->get('/api/v1/portal/team/{ref}', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'showTeam']);
    $router->patch('/api/v1/portal/team/{ref}', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'updateTeam']);
    $router->post('/api/v1/portal/team/{ref}/activate', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'activateTeam']);
    $router->post('/api/v1/portal/team/{ref}/deactivate', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'deactivateTeam']);
    $router->post('/api/v1/portal/team/{ref}/assign-beat', [\App\Http\Controllers\Api\V1\Portal\PortalController::class, 'assignBeat']);

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
