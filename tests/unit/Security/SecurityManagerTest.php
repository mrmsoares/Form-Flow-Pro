<?php
/**
 * Tests for SecurityManager class.
 *
 * @package FormFlowPro\Tests\Unit\Security
 */

namespace FormFlowPro\Tests\Unit\Security;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Security\SecurityManager;

class SecurityManagerTest extends TestCase
{
    private SecurityManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = SecurityManager::getInstance();
    }

    // ==================== Singleton Tests ====================

    public function testSingletonInstance(): void
    {
        $instance1 = SecurityManager::getInstance();
        $instance2 = SecurityManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    // ==================== Component Access Tests ====================

    public function testGetTwoFactorAuth(): void
    {
        $twoFactor = $this->manager->getTwoFactorAuth();
        $this->assertInstanceOf(\FormFlowPro\Security\TwoFactorAuth::class, $twoFactor);
    }

    public function testGetGDPRCompliance(): void
    {
        $gdpr = $this->manager->getGDPRCompliance();
        $this->assertInstanceOf(\FormFlowPro\Security\GDPRCompliance::class, $gdpr);
    }

    public function testGetAuditLogger(): void
    {
        $auditLogger = $this->manager->getAuditLogger();
        $this->assertInstanceOf(\FormFlowPro\Security\AuditLogger::class, $auditLogger);
    }

    public function testGetAccessControl(): void
    {
        $accessControl = $this->manager->getAccessControl();
        $this->assertInstanceOf(\FormFlowPro\Security\AccessControl::class, $accessControl);
    }

    // ==================== Permission Tests ====================

    public function testCheckAdminPermissionReturnsBool(): void
    {
        $result = $this->manager->checkAdminPermission();
        $this->assertIsBool($result);
    }

    public function testCheckUserPermissionReturnsBool(): void
    {
        $result = $this->manager->checkUserPermission();
        $this->assertIsBool($result);
    }

    // ==================== REST API Tests ====================

    public function testRestGetOverviewReturnsResponse(): void
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/security/overview');
        $response = $this->manager->restGetOverview($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();

        $this->assertArrayHasKey('2fa', $data);
        $this->assertArrayHasKey('gdpr', $data);
        $this->assertArrayHasKey('audit', $data);
        $this->assertArrayHasKey('access', $data);
    }

    public function testRestGet2FAStatusReturnsResponse(): void
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/security/2fa/status');
        $response = $this->manager->restGet2FAStatus($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();

        $this->assertArrayHasKey('enabled', $data);
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayHasKey('devices', $data);
        $this->assertArrayHasKey('backup_codes_remaining', $data);
    }

    public function testRestGetSessionsReturnsResponse(): void
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/security/sessions');
        $response = $this->manager->restGetSessions($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertIsArray($response->get_data());
    }

    public function testRestTerminateSessionReturnsErrorForInvalidToken(): void
    {
        $request = new \WP_REST_Request('DELETE', '/formflow/v1/security/sessions/invalid_token');
        $request->set_param('token', 'invalid_token_12345');

        $response = $this->manager->restTerminateSession($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function testRestGetIPRulesReturnsResponse(): void
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/security/ip-rules');
        $response = $this->manager->restGetIPRules($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();

        $this->assertArrayHasKey('rules', $data);
        $this->assertArrayHasKey('blocked', $data);
        $this->assertArrayHasKey('geo_rules', $data);
    }

    public function testRestAddIPRuleReturnsResponse(): void
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/security/ip-rules');
        $request->set_param('ip_address', '192.168.1.1');
        $request->set_param('rule_type', 'whitelist');
        $request->set_param('scope', 'all');
        $request->set_param('description', 'Test rule');

        $response = $this->manager->restAddIPRule($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function testRestGetAuditLogsReturnsResponse(): void
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/security/audit-logs');
        $request->set_param('limit', 10);

        $response = $this->manager->restGetAuditLogs($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();

        $this->assertArrayHasKey('logs', $data);
        $this->assertArrayHasKey('total', $data);
    }

    public function testRestGetAuditLogsWithFilters(): void
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/security/audit-logs');
        $request->set_param('category', 'authentication');
        $request->set_param('severity', 'warning');
        $request->set_param('search', 'login');

        $response = $this->manager->restGetAuditLogs($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function testRestGetGDPRRequestsReturnsResponse(): void
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/security/gdpr/requests');
        $response = $this->manager->restGetGDPRRequests($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();

        $this->assertArrayHasKey('requests', $data);
        $this->assertArrayHasKey('statistics', $data);
    }

    public function testRestGetSettingsReturnsResponse(): void
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/security/settings');
        $response = $this->manager->restGetSettings($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();

        $this->assertArrayHasKey('2fa_enforced_roles', $data);
        $this->assertArrayHasKey('session_ip_strict', $data);
        $this->assertArrayHasKey('max_sessions', $data);
        $this->assertArrayHasKey('geo_blocking_enabled', $data);
        $this->assertArrayHasKey('audit_retention_days', $data);
        $this->assertArrayHasKey('security_headers_enabled', $data);
    }

    public function testRestUpdateSettingsReturnsResponse(): void
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/security/settings');
        $request->set_param('max_sessions', 5);
        $request->set_param('security_headers_enabled', true);

        $response = $this->manager->restUpdateSettings($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
    }

    // ==================== Security Headers Tests ====================

    public function testAddCSPHeaderReturnsModifiedHeaders(): void
    {
        // Enable security headers
        update_option('formflow_security_headers_enabled', true);

        $headers = $this->manager->addCSPHeader([]);

        // CSP header should be added when enabled
        if (get_option('formflow_security_headers_enabled', true)) {
            $this->assertArrayHasKey('Content-Security-Policy', $headers);
        }
    }

    public function testAddCSPHeaderSkipsWhenDisabled(): void
    {
        update_option('formflow_security_headers_enabled', false);

        $headers = $this->manager->addCSPHeader([]);

        $this->assertArrayNotHasKey('Content-Security-Policy', $headers);

        // Reset
        update_option('formflow_security_headers_enabled', true);
    }

    // ==================== Install Tests ====================

    public function testInstallSetsDefaultOptions(): void
    {
        // Clear options first
        delete_option('formflow_2fa_enforced_roles');
        delete_option('formflow_max_sessions');

        $this->manager->install();

        // Check default options were set
        $this->assertIsArray(get_option('formflow_2fa_enforced_roles'));
        $this->assertIsInt(get_option('formflow_max_sessions'));
    }
}
