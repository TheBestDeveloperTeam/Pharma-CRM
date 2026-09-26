<?php
declare(strict_types=1);

// Bindings registered here. Populated phase by phase.
return function(\App\Core\Container $container): void {
    // P0-S09: Logger
    $container->singleton(\App\Core\Logger::class, function($c) {
        return new \App\Core\Logger(
            \App\Support\Config::get('app.storage') . '/logs',
            \App\Support\Config::get('app.log_level', 'debug')
        );
    });

    // P0-S10: Database & Transaction
    $container->singleton(\PDO::class, fn() => \App\Core\Database::connection());
    $container->singleton(\App\Core\Transaction::class, fn($c) => new \App\Core\Transaction($c->make(\PDO::class)));

    // P0-S11: FileCache
    $container->singleton(\App\Core\FileCache::class, function($c) {
        return new \App\Core\FileCache(
            \App\Support\Config::get('app.storage') . '/cache'
        );
    });

    // P1-S02: PasswordHasher
    $container->singleton(\App\Core\Security\PasswordHasher::class, fn() => new \App\Core\Security\PasswordHasher());

    // P1-S03: Jwt Service
    $container->singleton(\App\Core\Security\Jwt::class, function($c) {
        return new \App\Core\Security\Jwt(
            \App\Support\Config::get('auth.keys', []),
            (string)\App\Support\Config::get('auth.active_kid', 'k1'),
            (string)\App\Support\Config::get('auth.iss', 'pharma-crm')
        );
    });

    // P1-S04: TokenService
    $container->singleton(\App\Core\Security\TokenService::class, function($c) {
        return new \App\Core\Security\TokenService(
            $c->make(\PDO::class),
            $c->make(\App\Core\Security\Jwt::class)
        );
    });

    // P1-S05: RateLimiter
    $container->singleton(\App\Core\Security\RateLimiter::class, function($c) {
        return new \App\Core\Security\RateLimiter($c->make(\PDO::class));
    });

    // P1-S13: Audit Repository & Service
    $container->singleton(\App\Repositories\Contracts\AuditRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\AuditRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Domain\Audit\AuditService::class, function($c) {
        return new \App\Domain\Audit\AuditService($c->make(\App\Repositories\Contracts\AuditRepositoryInterface::class));
    });

    // P1-S10 to P1-S12: Repositories & Services
    $container->singleton(\App\Repositories\Contracts\OrganizationRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlOrganizationRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\FranchiseRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlFranchiseRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\UserRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\UserRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Domain\Franchises\FranchiseDefaultsService::class, function($c) {
        return new \App\Domain\Franchises\FranchiseDefaultsService(new \App\Core\Database($c->make(\PDO::class)));
    });

    // P2: Masters, Products, Pricing, Schemes
    $container->singleton(\App\Repositories\Contracts\ProductCategoryRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlProductCategoryRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\PricingTierRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlPricingTierRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\TransporterRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlTransporterRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\SystemSettingsRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlSystemSettingsRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\NotificationTemplateRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlNotificationTemplateRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\ProductRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlProductRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\ProductPriceRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlProductPriceRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\SchemeRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlSchemeRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\CatalogMasterRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlCatalogMasterRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Domain\Pricing\PriceResolver::class, function($c) {
        return new \App\Domain\Pricing\PriceResolver(
            $c->make(\App\Repositories\Contracts\ProductPriceRepositoryInterface::class),
            $c->make(\App\Repositories\Contracts\ProductRepositoryInterface::class)
        );
    });
    $container->singleton(\App\Domain\Schemes\SchemeCalculator::class, function($c) {
        return new \App\Domain\Schemes\SchemeCalculator(
            $c->make(\App\Repositories\Contracts\SchemeRepositoryInterface::class)
        );
    });

    // P3: Leads, Parties, Territories, Follow-ups, Webhooks, Jobs
    $container->singleton(\App\Repositories\Contracts\LeadRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlLeadRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\PartyRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlPartyRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\PartyTerritoryRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlPartyTerritoryRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\FollowUpRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlFollowUpRepository(new \App\Core\Database($c->make(\PDO::class)));
    });

    $container->singleton(\App\Domain\Leads\LeadStateMachine::class, fn() => new \App\Domain\Leads\LeadStateMachine());
    $container->singleton(\App\Domain\Leads\LeadAssignmentService::class, function($c) {
        return new \App\Domain\Leads\LeadAssignmentService(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Domain\Leads\LeadService::class, function($c) {
        return new \App\Domain\Leads\LeadService(
            $c->make(\App\Repositories\Contracts\LeadRepositoryInterface::class),
            $c->make(\App\Repositories\Contracts\SystemSettingsRepositoryInterface::class),
            $c->make(\App\Domain\Leads\LeadAssignmentService::class),
            $c->make(\App\Domain\Leads\LeadStateMachine::class)
        );
    });
    $container->singleton(\App\Domain\FollowUps\FollowUpService::class, function($c) {
        return new \App\Domain\FollowUps\FollowUpService(
            $c->make(\App\Repositories\Contracts\FollowUpRepositoryInterface::class),
            $c->make(\App\Repositories\Contracts\LeadRepositoryInterface::class)
        );
    });
    $container->singleton(\App\Domain\Parties\PartyService::class, function($c) {
        return new \App\Domain\Parties\PartyService(
            $c->make(\App\Repositories\Contracts\PartyRepositoryInterface::class),
            $c->make(\App\Core\SequenceService::class)
        );
    });
    $container->singleton(\App\Domain\Territory\TerritoryValidator::class, function($c) {
        return new \App\Domain\Territory\TerritoryValidator(
            $c->make(\App\Repositories\Contracts\PartyTerritoryRepositoryInterface::class),
            new \App\Core\Database($c->make(\PDO::class))
        );
    });
    $container->singleton(\App\Domain\Parties\PartyCreditService::class, function($c) {
        return new \App\Domain\Parties\PartyCreditService(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Domain\Territory\TerritoryResolver::class, function($c) {
        return new \App\Domain\Territory\TerritoryResolver(
            $c->make(\App\Repositories\Contracts\PartyTerritoryRepositoryInterface::class),
            new \App\Core\Database($c->make(\PDO::class))
        );
    });
    $container->singleton(\App\Domain\Territory\TerritoryService::class, function($c) {
        return new \App\Domain\Territory\TerritoryService(
            $c->make(\App\Repositories\Contracts\PartyTerritoryRepositoryInterface::class),
            $c->make(\App\Repositories\Contracts\PartyRepositoryInterface::class)
        );
    });
    $container->singleton(\App\Domain\Onboarding\OnboardingService::class, function($c) {
        return new \App\Domain\Onboarding\OnboardingService(
            new \App\Core\Database($c->make(\PDO::class)),
            $c->make(\App\Domain\Parties\PartyService::class),
            $c->make(\App\Domain\Leads\LeadService::class),
            $c->make(\App\Core\Security\PasswordHasher::class),
            $c->make(\App\Domain\Audit\AuditService::class)
        );
    });
    $container->singleton(\App\Domain\Webhooks\WebhookService::class, function($c) {
        return new \App\Domain\Webhooks\WebhookService(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Domain\Jobs\JobDispatcher::class, function($c) {
        return new \App\Domain\Jobs\JobDispatcher(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Core\JobRunner::class, function($c) {
        return new \App\Core\JobRunner(new \App\Core\Database($c->make(\PDO::class)), $c);
    });
    $container->singleton(\App\Domain\Webhooks\ProcessWebhookEventJob::class, function($c) {
        return new \App\Domain\Webhooks\ProcessWebhookEventJob(
            new \App\Core\Database($c->make(\PDO::class)),
            $c->make(\App\Domain\Leads\LeadService::class)
        );
    });

    // P4: Order-to-Cash, Inventory, Billing, Dispatches, Payments
    $container->singleton(\App\Repositories\Contracts\IdempotencyRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\IdempotencyRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\InventoryBatchRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlInventoryBatchRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\InventoryMovementRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlInventoryMovementRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\StockReservationRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlStockReservationRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\OrderRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlOrderRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\InvoiceRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlInvoiceRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\DispatchRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlDispatchRepository(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Repositories\Contracts\PaymentRepositoryInterface::class, function($c) {
        return new \App\Repositories\Sql\SqlPaymentRepository(new \App\Core\Database($c->make(\PDO::class)));
    });

    $container->singleton(\App\Domain\Inventory\InventoryService::class, function($c) {
        return new \App\Domain\Inventory\InventoryService(
            new \App\Core\Database($c->make(\PDO::class)),
            $c->make(\App\Repositories\Contracts\InventoryBatchRepositoryInterface::class),
            $c->make(\App\Repositories\Contracts\InventoryMovementRepositoryInterface::class)
        );
    });
    $container->singleton(\App\Domain\Inventory\FefoAllocator::class, function($c) {
        return new \App\Domain\Inventory\FefoAllocator(
            new \App\Core\Database($c->make(\PDO::class)),
            $c->make(\App\Repositories\Contracts\InventoryBatchRepositoryInterface::class),
            $c->make(\App\Repositories\Contracts\StockReservationRepositoryInterface::class),
            $c->make(\App\Repositories\Contracts\InventoryMovementRepositoryInterface::class)
        );
    });
    $container->singleton(\App\Domain\Orders\CreditRuleService::class, function($c) {
        return new \App\Domain\Orders\CreditRuleService(new \App\Core\Database($c->make(\PDO::class)));
    });
    $container->singleton(\App\Domain\Orders\OrderService::class, function($c) {
        return new \App\Domain\Orders\OrderService(
            $c->make(\App\Repositories\Contracts\OrderRepositoryInterface::class),
            $c->make(\App\Repositories\Contracts\PartyRepositoryInterface::class),
            $c->make(\App\Domain\Parties\PartyCreditService::class),
            $c->make(\App\Repositories\Contracts\ProductRepositoryInterface::class),
            $c->make(\App\Domain\Pricing\PriceResolver::class),
            $c->make(\App\Domain\Schemes\SchemeCalculator::class),
            $c->make(\App\Domain\Territory\TerritoryResolver::class),
            $c->make(\App\Domain\Territory\TerritoryValidator::class),
            $c->make(\App\Domain\Orders\CreditRuleService::class),
            $c->make(\App\Domain\Inventory\FefoAllocator::class),
            $c->make(\App\Core\SequenceService::class),
            new \App\Core\Database($c->make(\PDO::class))
        );
    });
    $container->singleton(\App\Domain\Billing\BillingService::class, function($c) {
        return new \App\Domain\Billing\BillingService(
            new \App\Core\Database($c->make(\PDO::class)),
            $c->make(\App\Repositories\Contracts\InvoiceRepositoryInterface::class),
            $c->make(\App\Repositories\Contracts\OrderRepositoryInterface::class),
            $c->make(\App\Repositories\Contracts\PartyRepositoryInterface::class),
            $c->make(\App\Core\SequenceService::class),
            new \App\Domain\Billing\GstCalculator()
        );
    });
    $container->singleton(\App\Domain\Authorization\Task009ScopePolicy::class, function($c) {
        return new \App\Domain\Authorization\Task009ScopePolicy(new \App\Core\Database($c->make(\PDO::class)), $c->make(\App\Domain\Authorization\AuthorizationService::class));
    });
    $container->singleton(\App\Domain\Dispatch\DispatchService::class, function($c) {
        return new \App\Domain\Dispatch\DispatchService(
            new \App\Core\Database($c->make(\PDO::class)),
            $c->make(\App\Repositories\Contracts\DispatchRepositoryInterface::class),
            $c->make(\App\Repositories\Contracts\InvoiceRepositoryInterface::class),
            $c->make(\App\Repositories\Contracts\OrderRepositoryInterface::class),
            $c->make(\App\Core\SequenceService::class),
            $c->make(\App\Domain\Inventory\FefoAllocator::class)
        );
    });
    $container->singleton(\App\Domain\Payments\PaymentService::class, function($c) {
        return new \App\Domain\Payments\PaymentService(
            new \App\Core\Database($c->make(\PDO::class)),
            $c->make(\App\Repositories\Contracts\PaymentRepositoryInterface::class),
            $c->make(\App\Repositories\Contracts\PartyRepositoryInterface::class),
            $c->make(\App\Core\SequenceService::class)
        );
    });
    $container->singleton(\App\Domain\Payments\AllocationService::class, function($c) {
        return new \App\Domain\Payments\AllocationService(new \App\Core\Database($c->make(\PDO::class)), $c->make(\App\Domain\Authorization\PartyScopePredicate::class));
    });
    $container->singleton(\App\Domain\Payments\OutstandingService::class, function($c) { return new \App\Domain\Payments\OutstandingService(new \App\Core\Database($c->make(\PDO::class)), $c->make(\App\Domain\Authorization\PartyScopePredicate::class)); });
    $container->singleton(\App\Domain\Payments\PaymentReversalService::class, function($c) { return new \App\Domain\Payments\PaymentReversalService(new \App\Core\Database($c->make(\PDO::class))); });
    $container->singleton(\App\Domain\Authorization\PartyScopePredicate::class, function($c) { return new \App\Domain\Authorization\PartyScopePredicate(); });
    $container->singleton(\App\Domain\Payments\PdcService::class, function($c) { return new \App\Domain\Payments\PdcService(new \App\Core\Database($c->make(\PDO::class)), $c->make(\App\Core\SequenceService::class), $c->make(\App\Domain\Authorization\PartyScopePredicate::class)); });

    // P5: Notifications & Scanners
    $container->singleton(\App\Domain\Notifications\NotificationService::class, function($c) {
        return new \App\Domain\Notifications\NotificationService(
            new \App\Core\Database($c->make(\PDO::class))
        );
    });
    $container->singleton(\App\Domain\Jobs\ReminderScannerService::class, function($c) {
        return new \App\Domain\Jobs\ReminderScannerService(
            new \App\Core\Database($c->make(\PDO::class)),
            $c->make(\App\Domain\Notifications\NotificationService::class)
        );
    });

    // P8: Missing Services
    $container->singleton(\App\Domain\Users\UserService::class, function($c) {
        return new \App\Domain\Users\UserService(
            $c->make(\App\Repositories\Contracts\UserRepositoryInterface::class),
            $c->make(\App\Core\Security\PasswordHasher::class)
        );
    });

    $container->singleton(\App\Domain\Reports\ReportService::class, function($c) {
        return new \App\Domain\Reports\ReportService(
            new \App\Core\Database($c->make(\PDO::class))
        );
    });

    $container->singleton(\App\Domain\Dcr\DcrService::class, function($c) {
        return new \App\Domain\Dcr\DcrService(
            new \App\Core\Database($c->make(\PDO::class)),
            $c->make(\App\Domain\Audit\AuditService::class)
        );
    });
    $container->singleton(\App\Domain\Reports\ScopedAnalyticsService::class, function($c) { return new \App\Domain\Reports\ScopedAnalyticsService(new \App\Core\Database($c->make(\PDO::class)), $c->make(\App\Domain\Payments\OutstandingService::class)); });
};
