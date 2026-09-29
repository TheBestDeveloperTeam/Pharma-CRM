<?php
declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

final class P3RoutesTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
        $routesFn = require __DIR__ . '/../../../bootstrap/routes.php';
        $routesFn($this->router);
    }

    private function assertRouteMatches(string $method, string $path, string $expectedClass, string $expectedMethod, array $expectedParams = []): void
    {
        $request = Request::create($method, $path);
        $match = $this->router->dispatch($request);

        $this->assertNotNull($match, "Route {$method} {$path} must match");
        $this->assertIsArray($match['handler'], "Handler for {$method} {$path} must be [Class, Method]");
        $this->assertSame($expectedClass, $match['handler'][0], "Controller class mismatch for {$method} {$path}");
        $this->assertSame($expectedMethod, $match['handler'][1], "Controller method mismatch for {$method} {$path}");

        foreach ($expectedParams as $key => $val) {
            $this->assertArrayHasKey($key, $match['params'], "Missing param {$key} for {$path}");
            $this->assertSame($val, $match['params'][$key], "Param value mismatch for {$key} on {$path}");
        }
    }

    public function testWebhookRoutes(): void
    {
        $whkClass = \App\Http\Controllers\Api\V1\Admin\WebhookSourcesController::class;

        $this->assertRouteMatches('GET', '/api/v1/admin/webhook-events/failures', $whkClass, 'failedEvents');
        $this->assertRouteMatches('GET', '/api/v1/admin/webhook-events', $whkClass, 'listEvents');
        $this->assertRouteMatches('GET', '/api/v1/admin/webhook-events/EVT-001', $whkClass, 'showEvent', ['ref' => 'EVT-001']);
        $this->assertRouteMatches('POST', '/api/v1/admin/webhook-events/EVT-001/retry', $whkClass, 'retryEvent', ['ref' => 'EVT-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/webhook-sources', $whkClass, 'index');
        $this->assertRouteMatches('POST', '/api/v1/admin/webhook-sources', $whkClass, 'store');
        $this->assertRouteMatches('GET', '/api/v1/admin/webhook-sources/WHS-001/events', $whkClass, 'sourceEvents', ['ref' => 'WHS-001']);
        $this->assertRouteMatches('POST', '/api/v1/admin/webhook-sources/WHS-001/test', $whkClass, 'test', ['ref' => 'WHS-001']);
        $this->assertRouteMatches('POST', '/api/v1/admin/webhook-sources/WHS-001/regenerate-secret', $whkClass, 'regenerateSecret', ['ref' => 'WHS-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/webhook-sources/WHS-001', $whkClass, 'show', ['ref' => 'WHS-001']);
        $this->assertRouteMatches('DELETE', '/api/v1/admin/webhook-sources/WHS-001', $whkClass, 'delete', ['ref' => 'WHS-001']);
    }

    public function testNotificationsAndWhatsAppRoutes(): void
    {
        $ntfClass = \App\Http\Controllers\Api\V1\NotificationsController::class;

        // In-app notifications
        $this->assertRouteMatches('GET', '/api/v1/notifications/unread-count', $ntfClass, 'unreadCount');
        $this->assertRouteMatches('GET', '/api/v1/notifications', $ntfClass, 'index');
        $this->assertRouteMatches('POST', '/api/v1/notifications/read-all', $ntfClass, 'markAllRead');
        $this->assertRouteMatches('POST', '/api/v1/notifications/NTF-001/read', $ntfClass, 'markRead', ['ref' => 'NTF-001']);
        $this->assertRouteMatches('DELETE', '/api/v1/notifications/NTF-001', $ntfClass, 'delete', ['ref' => 'NTF-001']);
        $this->assertRouteMatches('POST', '/api/v1/admin/notifications/send', $ntfClass, 'sendSystem');
        $this->assertRouteMatches('GET', '/api/v1/admin/notifications/manage-templates', $ntfClass, 'manageTemplates');

        // WhatsApp
        $this->assertRouteMatches('GET', '/api/v1/admin/whatsapp/delivery-report', $ntfClass, 'whatsAppDeliveryReport');
        $this->assertRouteMatches('GET', '/api/v1/admin/whatsapp/messages', $ntfClass, 'listWhatsAppMessages');
        $this->assertRouteMatches('POST', '/api/v1/admin/whatsapp/messages/WAM-001/retry', $ntfClass, 'retryWhatsAppMessage', ['ref' => 'WAM-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/whatsapp/messages/WAM-001', $ntfClass, 'showWhatsAppMessage', ['ref' => 'WAM-001']);
        $this->assertRouteMatches('POST', '/api/v1/admin/whatsapp/send', $ntfClass, 'sendWhatsApp');
        $this->assertRouteMatches('GET', '/api/v1/admin/whatsapp/templates', $ntfClass, 'listWhatsAppTemplates');
        $this->assertRouteMatches('POST', '/api/v1/admin/whatsapp/templates', $ntfClass, 'createWhatsAppTemplate');
        $this->assertRouteMatches('PATCH', '/api/v1/admin/whatsapp/templates/WAT-001', $ntfClass, 'updateWhatsAppTemplate', ['ref' => 'WAT-001']);
    }

    public function testAuditLogRoutes(): void
    {
        $auditClass = \App\Http\Controllers\Api\V1\Admin\AuditLogsController::class;

        $this->assertRouteMatches('GET', '/api/v1/admin/audit/export', $auditClass, 'export');
        $this->assertRouteMatches('GET', '/api/v1/admin/audit/security-events', $auditClass, 'securityEvents');
        $this->assertRouteMatches('GET', '/api/v1/admin/audit/entity/party/PTY-001', $auditClass, 'entityAudit', ['entity_type' => 'party', 'entity_ref' => 'PTY-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/audit/user/USR-001', $auditClass, 'userAudit', ['user_ref' => 'USR-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/audit/AUD-001', $auditClass, 'show', ['ref' => 'AUD-001']);
        $this->assertRouteMatches('GET', '/api/v1/admin/audit', $auditClass, 'index');
    }

    public function testUtilitiesAndAuthRoutes(): void
    {
        $searchClass = \App\Http\Controllers\Api\V1\SearchController::class;
        $authClass = \App\Http\Controllers\Api\V1\AuthController::class;

        $this->assertRouteMatches('GET', '/api/v1/search', $searchClass, 'search');
        $this->assertRouteMatches('POST', '/api/v1/auth/change-password', $authClass, 'changePassword');
        $this->assertRouteMatches('POST', '/api/v1/auth/refresh-token', $authClass, 'refreshToken');
        $this->assertRouteMatches('GET', '/api/v1/auth/sessions', $authClass, 'sessions');
        $this->assertRouteMatches('POST', '/api/v1/auth/logout-all', $authClass, 'logoutAll');
    }
}
