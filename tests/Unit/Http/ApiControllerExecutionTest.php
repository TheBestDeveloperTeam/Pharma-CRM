<?php
declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Container;
use App\Core\Database;
use App\Core\Request;
use App\Core\Security\Jwt;
use App\Core\Security\PasswordHasher;
use App\Core\Security\RateLimiter;
use App\Core\Security\TokenService;
use App\Core\TenantContext;
use App\Domain\Audit\AuditService;
use App\Http\Controllers\Api\V1\Admin\SettingsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GeoController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Repositories\Contracts\AuditRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ApiControllerExecutionTest extends TestCase
{
    private \PDO $pdo;
    private Database $db;
    private TenantContext $ctx;
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        parent::setUp();

        // In-memory SQLite PDO for isolated controller functional execution testing
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Register MySQL compatibility functions in SQLite
        $this->pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
        $this->pdo->sqliteCreateFunction('CURDATE', fn() => date('Y-m-d'));

        // Setup mock database wrapper
        $this->db = new Database($this->pdo);
        $this->hasher = new PasswordHasher();

        // Setup tenant context
        $this->ctx = new TenantContext(
            orgRef: 'ORG-TEST000000000001',
            franchiseRef: 'FRN-TEST000000000001',
            userRef: 'USR-TEST000000000001',
            role: 'FRANCHISE_ADMIN',
            scope: 'FRANCHISE',
            partyRef: null,
            requestId: 'REQ-EXEC-TEST-001',
            permissions: [
                'settings'      => ['view', 'edit'],
                'internalUsers' => ['view', 'create', 'edit', 'delete'],
            ],
            scopes: [
                'settings'      => 'ALL',
                'internalUsers' => 'ALL',
            ]
        );

        $container = Container::getInstance();
        $container->instance(\PDO::class, $this->pdo);
        $container->instance(Database::class, $this->db);
        $container->instance(TenantContext::class, $this->ctx);

        // Create essential mock tables
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS states (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                state_ref VARCHAR(24),
                state_name VARCHAR(100),
                state_code VARCHAR(10),
                is_active INTEGER DEFAULT 1
            );
            CREATE TABLE IF NOT EXISTS districts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                district_ref VARCHAR(24),
                state_ref VARCHAR(24),
                district_name VARCHAR(100),
                is_active INTEGER DEFAULT 1
            );
            CREATE TABLE IF NOT EXISTS cities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                city_ref VARCHAR(24),
                district_ref VARCHAR(24),
                city_name VARCHAR(100),
                is_active INTEGER DEFAULT 1
            );
            CREATE TABLE IF NOT EXISTS pincodes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pincode_ref VARCHAR(24),
                city_ref VARCHAR(24),
                district_ref VARCHAR(24),
                state_ref VARCHAR(24),
                pincode VARCHAR(6),
                area_name VARCHAR(100),
                is_active INTEGER DEFAULT 1
            );
            CREATE TABLE IF NOT EXISTS system_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                org_ref VARCHAR(24),
                franchise_ref VARCHAR(24),
                setting_key VARCHAR(64),
                setting_value_json TEXT,
                updated_at DATETIME,
                UNIQUE(franchise_ref, setting_key)
            );
            CREATE TABLE IF NOT EXISTS franchises (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                franchise_ref VARCHAR(24) UNIQUE,
                franchise_code VARCHAR(32),
                franchise_name VARCHAR(191),
                gstin VARCHAR(15),
                drug_license_no VARCHAR(64),
                address TEXT,
                state_ref VARCHAR(24),
                brand_primary_hex VARCHAR(7),
                brand_accent_hex VARCHAR(7),
                status VARCHAR(20)
            );
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                org_ref VARCHAR(24),
                franchise_ref VARCHAR(24),
                product_ref VARCHAR(24),
                sku VARCHAR(64),
                product_name VARCHAR(191),
                composition VARCHAR(191),
                mrp REAL DEFAULT 0,
                franchise_rate REAL DEFAULT 0,
                status VARCHAR(20)
            );
            CREATE TABLE IF NOT EXISTS parties (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                org_ref VARCHAR(24),
                franchise_ref VARCHAR(24),
                party_ref VARCHAR(24),
                party_code VARCHAR(32),
                firm_name VARCHAR(191),
                contact_name VARCHAR(191),
                mobile VARCHAR(20),
                gstin VARCHAR(15),
                status VARCHAR(20)
            );
            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                org_ref VARCHAR(24),
                franchise_ref VARCHAR(24),
                order_ref VARCHAR(24),
                order_no VARCHAR(64),
                client_order_ref VARCHAR(64),
                status VARCHAR(30),
                grand_total REAL DEFAULT 0,
                order_date DATETIME
            );
            CREATE TABLE IF NOT EXISTS invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                org_ref VARCHAR(24),
                franchise_ref VARCHAR(24),
                invoice_ref VARCHAR(24),
                invoice_no VARCHAR(64),
                grand_total REAL DEFAULT 0,
                paid_total REAL DEFAULT 0,
                status VARCHAR(30),
                invoice_date DATETIME
            );
            CREATE TABLE IF NOT EXISTS leads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                org_ref VARCHAR(24),
                franchise_ref VARCHAR(24),
                lead_ref VARCHAR(24),
                lead_code VARCHAR(32),
                firm_name VARCHAR(191),
                contact_name VARCHAR(191),
                mobile VARCHAR(20),
                status VARCHAR(30),
                first_response_at DATETIME,
                created_at DATETIME
            );
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_ref VARCHAR(24) UNIQUE,
                org_ref VARCHAR(24),
                franchise_ref VARCHAR(24),
                role VARCHAR(32),
                full_name VARCHAR(191),
                email VARCHAR(191),
                password_hash VARCHAR(255),
                must_change_password INTEGER DEFAULT 0,
                status VARCHAR(20),
                created_at DATETIME,
                updated_at DATETIME
            );
            CREATE TABLE IF NOT EXISTS user_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_ref VARCHAR(24) UNIQUE,
                family_ref VARCHAR(24),
                user_ref VARCHAR(24),
                org_ref VARCHAR(24),
                franchise_ref VARCHAR(24),
                client_id VARCHAR(32),
                ip_address VARCHAR(45),
                user_agent VARCHAR(255),
                issued_at DATETIME,
                abs_expires_at DATETIME,
                revoked_at DATETIME,
                revoke_reason VARCHAR(40)
            );
            CREATE TABLE IF NOT EXISTS oauth_refresh_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token_hash VARCHAR(64),
                session_ref VARCHAR(24),
                family_ref VARCHAR(24),
                user_ref VARCHAR(24),
                status VARCHAR(20),
                issued_at DATETIME,
                idle_expires_at DATETIME,
                abs_expires_at DATETIME
            );
        ");

        // Seed sample test data
        $this->pdo->exec("
            INSERT INTO states (state_ref, state_name, state_code, is_active) VALUES ('STA-001', 'Maharashtra', 'MH', 1);
            INSERT INTO districts (district_ref, state_ref, district_name, is_active) VALUES ('DST-001', 'STA-001', 'Mumbai', 1);
            INSERT INTO cities (city_ref, district_ref, city_name, is_active) VALUES ('CTY-001', 'DST-001', 'Mumbai Central', 1);
            INSERT INTO pincodes (pincode_ref, city_ref, district_ref, state_ref, pincode, area_name, is_active) 
            VALUES ('PIN-001', 'CTY-001', 'DST-001', 'STA-001', '400001', 'Fort', 1);
            INSERT INTO franchises (franchise_ref, franchise_name, status) VALUES ('FRN-TEST000000000001', 'Test Mumbai Franchise', 'ACTIVE');
        ");

        // Seed test user
        $hash = $this->hasher->hash('CurrentPassword123');
        $this->pdo->exec("
            INSERT INTO users (user_ref, org_ref, franchise_ref, role, full_name, email, password_hash, status)
            VALUES ('USR-TEST000000000001', 'ORG-TEST000000000001', 'FRN-TEST000000000001', 'FRANCHISE_ADMIN', 'Admin User', 'admin@example.com', '{$hash}', 'ACTIVE');
            INSERT INTO user_sessions (session_ref, family_ref, user_ref, org_ref, franchise_ref, client_id, ip_address, user_agent, issued_at, abs_expires_at)
            VALUES ('SES-TEST000000000001', 'FAM-001', 'USR-TEST000000000001', 'ORG-TEST000000000001', 'FRN-TEST000000000001', 'client_crm_admin', '127.0.0.1', 'PHPUnit', datetime('now'), datetime('now', '+14 days'));
        ");
    }

    private function decodeData(\App\Core\Response $resp): mixed
    {
        $body = json_decode($resp->body(), true);
        return $body['data'] ?? null;
    }

    public function testGeoControllerExecution(): void
    {
        $geo = new GeoController($this->db);

        // 1. States listing
        $req = Request::create('GET', '/api/v1/geo/states');
        $resp = $geo->states($req);
        $this->assertSame(200, $resp->status());
        $data = $this->decodeData($resp);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertSame('Maharashtra', $data[0]['state_name']);

        // 2. Single pincode lookup
        $reqPin = Request::create('GET', '/api/v1/geo/pincodes/400001');
        $reqPin->params['pin'] = '400001';
        $respPin = $geo->pincode($reqPin, '400001');
        $this->assertSame(200, $respPin->status());
        $pinData = $this->decodeData($respPin);
        $this->assertSame('400001', $pinData['pincode']);
        $this->assertSame('Mumbai Central', $pinData['city_name']);
        $this->assertSame('Mumbai', $pinData['district_name']);
        $this->assertSame('Maharashtra', $pinData['state_name']);

        // 3. Search pincode
        $reqSearch = Request::create('GET', '/api/v1/geo/search?q=Mumbai');
        $respSearch = $geo->search($reqSearch);
        $this->assertSame(200, $respSearch->status());
        $this->assertNotEmpty($this->decodeData($respSearch));
    }

    public function testSettingsControllerExecution(): void
    {
        $auditRepo = $this->createMock(AuditRepositoryInterface::class);
        $auditService = new AuditService($auditRepo);
        $settings = new SettingsController($this->pdo, $auditService);

        // Near-expiry defaults
        $req = Request::create('GET', '/api/v1/admin/settings/near-expiry-thresholds');
        $resp = $settings->getNearExpiryThresholds($req);
        $this->assertSame(200, $resp->status());
        $data = $this->decodeData($resp);
        $this->assertSame(180, $data['days_threshold']);

        // SLA defaults
        $reqSla = Request::create('GET', '/api/v1/admin/settings/sla');
        $respSla = $settings->getSla($reqSla);
        $this->assertSame(200, $respSla->status());
        $dataSla = $this->decodeData($respSla);
        $this->assertSame(4, $dataSla['response_time_hours']);

        // Territory policy defaults
        $reqTerritory = Request::create('GET', '/api/v1/admin/settings/territory-policy');
        $respTerritory = $settings->getTerritoryPolicy($reqTerritory);
        $this->assertSame(200, $respTerritory->status());
        $dataTerritory = $this->decodeData($respTerritory);
        $this->assertSame('BLOCK', $dataTerritory['unassigned_pincode_action']);

        // Credit policy defaults
        $reqCredit = Request::create('GET', '/api/v1/admin/settings/credit-policy');
        $respCredit = $settings->getCreditPolicy($reqCredit);
        $this->assertSame(200, $respCredit->status());
        $dataCredit = $this->decodeData($respCredit);
        $this->assertSame('HARD_BLOCK', $dataCredit['mode']);

        // DCR config defaults
        $reqDcr = Request::create('GET', '/api/v1/admin/settings/dcr-config');
        $respDcr = $settings->getDcrConfig($reqDcr);
        $this->assertSame(200, $respDcr->status());
        $dataDcr = $this->decodeData($respDcr);
        $this->assertSame('23:00', $dataDcr['cutoff_time']);

        // Invite config defaults
        $reqInvite = Request::create('GET', '/api/v1/admin/settings/invite-config');
        $respInvite = $settings->getInviteConfig($reqInvite);
        $this->assertSame(200, $respInvite->status());
        $dataInvite = $this->decodeData($respInvite);
        $this->assertSame(7, $dataInvite['token_validity_days']);

        // Scheme stacking defaults
        $reqScheme = Request::create('GET', '/api/v1/admin/settings/scheme-stacking');
        $respScheme = $settings->getSchemeStacking($reqScheme);
        $this->assertSame(200, $respScheme->status());
        $dataScheme = $this->decodeData($respScheme);
        $this->assertFalse($dataScheme['allow_stacking']);

        // Min shelf-life defaults
        $reqShelf = Request::create('GET', '/api/v1/admin/settings/min-shelf-life');
        $respShelf = $settings->getMinShelfLife($reqShelf);
        $this->assertSame(200, $respShelf->status());
        $dataShelf = $this->decodeData($respShelf);
        $this->assertSame(6, $dataShelf['min_shelf_life_months']);
    }

    public function testSearchControllerExecution(): void
    {
        $search = new SearchController($this->pdo);

        // 1. Short query returns empty buckets
        $reqShort = Request::create('GET', '/api/v1/search?q=a');
        $respShort = $search->search($reqShort);
        $this->assertSame(200, $respShort->status());
        $shortData = $this->decodeData($respShort);
        $this->assertEmpty($shortData['products']);
        $this->assertEmpty($shortData['parties']);

        // 2. Populate sample data
        $this->pdo->exec("
            INSERT INTO products (org_ref, franchise_ref, product_ref, product_name, sku, composition, mrp, franchise_rate, status) 
            VALUES ('ORG-TEST000000000001', 'FRN-TEST000000000001', 'PRD-001', 'Amoxicillin 500mg', 'SKU-AMX500', 'Amoxicillin', 120.0, 95.0, 'ACTIVE');
            INSERT INTO parties (org_ref, franchise_ref, party_ref, party_code, firm_name, contact_name, mobile, gstin, status) 
            VALUES ('ORG-TEST000000000001', 'FRN-TEST000000000001', 'PTY-001', 'PTY-0001', 'Apollo Pharmacy Ltd', 'Apollo Contact', '9876543210', '27AAAAA0000A1Z5', 'ACTIVE');
        ");

        $req = Request::create('GET', '/api/v1/search?q=Apollo');
        $resp = $search->search($req);
        $this->assertSame(200, $resp->status());
        $data = $this->decodeData($resp);
        $this->assertArrayHasKey('parties', $data);
        $this->assertNotEmpty($data['parties']);
        $this->assertSame('Apollo Pharmacy Ltd', $data['parties'][0]['firm_name']);
    }

    public function testEffectivePermissionsExecution(): void
    {
        $jwt = new Jwt(['k1' => 'secret_test_key_123'], 'k1', 'pharma-crm');
        $tokens = new TokenService($this->pdo, $jwt);
        $limiter = new RateLimiter($this->pdo);

        $auth = new AuthController($this->pdo, $this->hasher, $tokens, $limiter);

        $req = Request::create('GET', '/api/v1/auth/effective-permissions');
        $resp = $auth->effectivePermissions($req);
        $this->assertSame(200, $resp->status());
        $data = $this->decodeData($resp);
        $this->assertSame('USR-TEST000000000001', $data['user_ref']);
        $this->assertSame('FRANCHISE_ADMIN', $data['role']);
        $this->assertArrayHasKey('settings', $data['permissions']);
    }

    public function testAuthSessionsAndChangePasswordExecution(): void
    {
        $jwt = new Jwt(['k1' => 'secret_test_key_123'], 'k1', 'pharma-crm');
        $tokens = new TokenService($this->pdo, $jwt);
        $limiter = new RateLimiter($this->pdo);

        $auth = new AuthController($this->pdo, $this->hasher, $tokens, $limiter);

        // 1. Sessions listing (UTL-007)
        $reqSessions = Request::create('GET', '/api/v1/auth/sessions');
        $respSessions = $auth->sessions($reqSessions);
        $this->assertSame(200, $respSessions->status());
        $sessData = $this->decodeData($respSessions);
        $this->assertArrayHasKey('sessions', $sessData);
        $this->assertSame(1, $sessData['total']);
        $this->assertSame('SES-TEST000000000001', $sessData['sessions'][0]['session_ref']);

        // 2. Change password (UTL-005)
        $changePayload = json_encode([
            'old_password' => 'CurrentPassword123',
            'new_password' => 'BrandNewPassword456!',
        ]);
        $reqPass = Request::create('POST', '/api/v1/auth/change-password', [], $changePayload);
        $respPass = $auth->changePassword($reqPass);
        $this->assertSame(200, $respPass->status());
        $passData = $this->decodeData($respPass);
        $this->assertSame('SUCCESS', $passData['status']);

        // 3. Logout All (UTL-008)
        $reqLogout = Request::create('POST', '/api/v1/auth/logout-all');
        $respLogout = $auth->logoutAll($reqLogout);
        $this->assertSame(200, $respLogout->status());
        $logoutData = $this->decodeData($respLogout);
        $this->assertTrue($logoutData['revoked_all']);

        // 4. Verify session is now revoked
        $respSessionsAfter = $auth->sessions($reqSessions);
        $this->assertSame(0, $this->decodeData($respSessionsAfter)['total']);
    }
}
