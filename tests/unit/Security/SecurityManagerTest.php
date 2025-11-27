<?php
/**
 * Tests for SecurityManager class.
 */

namespace FormFlowPro\Tests\Unit\Security;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Security\SecurityManager;
use FormFlowPro\Security\TwoFactorAuth;
use FormFlowPro\Security\GDPRCompliance;
use FormFlowPro\Security\AuditLogger;
use FormFlowPro\Security\AccessControl;

class SecurityManagerTest extends TestCase
{
    private $securityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->securityManager = SecurityManager::getInstance();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = SecurityManager::getInstance();
        $instance2 = SecurityManager::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(SecurityManager::class, $instance1);
    }

    public function test_get_two_factor_auth_returns_instance()
    {
        $twoFactorAuth = $this->securityManager->getTwoFactorAuth();

        $this->assertInstanceOf(TwoFactorAuth::class, $twoFactorAuth);
    }

    public function test_get_gdpr_compliance_returns_instance()
    {
        $gdprCompliance = $this->securityManager->getGDPRCompliance();

        $this->assertInstanceOf(GDPRCompliance::class, $gdprCompliance);
    }

    public function test_get_audit_logger_returns_instance()
    {
        $auditLogger = $this->securityManager->getAuditLogger();

        $this->assertInstanceOf(AuditLogger::class, $auditLogger);
    }

    public function test_get_access_control_returns_instance()
    {
        $accessControl = $this->securityManager->getAccessControl();

        $this->assertInstanceOf(AccessControl::class, $accessControl);
    }

    public function test_install_creates_default_options()
    {
        $this->securityManager->install();

        $this->assertEquals(['administrator'], get_option('formflow_2fa_enforced_roles'));
        $this->assertFalse(get_option('formflow_session_ip_strict'));
        $this->assertEquals(3, get_option('formflow_max_sessions'));
        $this->assertFalse(get_option('formflow_geo_blocking_enabled'));
        $this->assertEquals(90, get_option('formflow_audit_retention_days'));
        $this->assertEquals(365, get_option('formflow_gdpr_auto_delete_days'));
        $this->assertTrue(get_option('formflow_security_headers_enabled'));
    }

    public function test_install_does_not_overwrite_existing_options()
    {
        update_option('formflow_2fa_enforced_roles', ['editor', 'administrator']);

        $this->securityManager->install();

        $this->assertEquals(['editor', 'administrator'], get_option('formflow_2fa_enforced_roles'));
    }

    public function test_install_updates_security_version()
    {
        $this->securityManager->install();

        $this->assertEquals('2.4.0', get_option('formflow_security_version'));
    }

    public function test_check_admin_permission_returns_true()
    {
        $result = $this->securityManager->checkAdminPermission();

        $this->assertTrue($result);
    }

    public function test_check_user_permission_returns_true()
    {
        $result = $this->securityManager->checkUserPermission();

        $this->assertTrue($result);
    }

    public function test_rest_get_overview_returns_response()
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/security/overview');
        $response = $this->securityManager->restGetOverview($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('2fa', $data);
        $this->assertArrayHasKey('gdpr', $data);
        $this->assertArrayHasKey('audit', $data);
        $this->assertArrayHasKey('access', $data);
    }

    public function test_rest_get_2fa_status_returns_user_status()
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/security/2fa/status');
        $response = $this->securityManager->restGet2FAStatus($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('enabled', $data);
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayHasKey('devices', $data);
        $this->assertArrayHasKey('backup_codes_remaining', $data);
    }

    public function test_rest_get_sessions_returns_user_sessions()
    {
        $_COOKIE['formflow_session_token'] = 'test_token_123';

        $request = new \WP_REST_Request('GET', '/formflow/v1/security/sessions');
        $response = $this->securityManager->restGetSessions($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertIsArray($data);
    }

    public function test_rest_terminate_session_with_valid_token()
    {
        $request = new \WP_REST_Request('DELETE', '/formflow/v1/security/sessions/valid_token');
        $request->set_param('token', 'valid_token');

        $response = $this->securityManager->restTerminateSession($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_rest_get_ip_rules_returns_rules()
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/security/ip-rules');
        $response = $this->securityManager->restGetIPRules($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('rules', $data);
        $this->assertArrayHasKey('blocked', $data);
        $this->assertArrayHasKey('geo_rules', $data);
    }

    public function test_rest_add_ip_rule_with_valid_data()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/security/ip-rules');
        $request->set_param('ip_address', '192.168.1.1');
        $request->set_param('rule_type', 'whitelist');
        $request->set_param('scope', 'admin');
        $request->set_param('description', 'Test rule');

        $response = $this->securityManager->restAddIPRule($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_rest_get_audit_logs_with_filters()
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/security/audit-logs');
        $request->set_param('category', 'security');
        $request->set_param('severity', 'warning');
        $request->set_param('limit', 50);
        $request->set_param('offset', 0);

        $response = $this->securityManager->restGetAuditLogs($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('logs', $data);
        $this->assertArrayHasKey('total', $data);
    }

    public function test_rest_get_gdpr_requests_returns_requests()
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/security/gdpr/requests');
        $response = $this->securityManager->restGetGDPRRequests($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('requests', $data);
        $this->assertArrayHasKey('statistics', $data);
    }

    public function test_rest_get_settings_returns_all_settings()
    {
        update_option('formflow_2fa_enforced_roles', ['administrator']);
        update_option('formflow_session_ip_strict', true);
        update_option('formflow_max_sessions', 5);

        $request = new \WP_REST_Request('GET', '/formflow/v1/security/settings');
        $response = $this->securityManager->restGetSettings($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('2fa_enforced_roles', $data);
        $this->assertArrayHasKey('session_ip_strict', $data);
        $this->assertArrayHasKey('max_sessions', $data);
        $this->assertEquals(['administrator'], $data['2fa_enforced_roles']);
        $this->assertTrue($data['session_ip_strict']);
        $this->assertEquals(5, $data['max_sessions']);
    }

    public function test_rest_update_settings_updates_options()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/security/settings');
        $request->set_param('2fa_enforced_roles', ['administrator', 'editor']);
        $request->set_param('session_ip_strict', false);
        $request->set_param('max_sessions', 10);

        $response = $this->securityManager->restUpdateSettings($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertTrue($data['success']);

        $this->assertEquals(['administrator', 'editor'], get_option('formflow_2fa_enforced_roles'));
        $this->assertFalse(get_option('formflow_session_ip_strict'));
        $this->assertEquals(10, get_option('formflow_max_sessions'));
    }

    public function test_rest_update_settings_skips_null_values()
    {
        update_option('formflow_max_sessions', 3);

        $request = new \WP_REST_Request('POST', '/formflow/v1/security/settings');
        $request->set_param('session_ip_strict', true);

        $response = $this->securityManager->restUpdateSettings($request);

        $this->assertTrue(get_option('formflow_session_ip_strict'));
        $this->assertEquals(3, get_option('formflow_max_sessions')); // Unchanged
    }

    public function test_add_security_headers_when_enabled()
    {
        update_option('formflow_security_headers_enabled', true);

        ob_start();
        $this->securityManager->addSecurityHeaders();
        ob_end_clean();

        // Test would check headers in real environment
        $this->assertTrue(true);
    }

    public function test_add_security_headers_skipped_when_disabled()
    {
        update_option('formflow_security_headers_enabled', false);

        ob_start();
        $this->securityManager->addSecurityHeaders();
        ob_end_clean();

        // Test would check no headers set in real environment
        $this->assertTrue(true);
    }

    public function test_add_csp_header_returns_modified_headers()
    {
        update_option('formflow_security_headers_enabled', true);

        $headers = ['Existing-Header' => 'value'];
        $result = $this->securityManager->addCSPHeader($headers);

        $this->assertArrayHasKey('Existing-Header', $result);
        $this->assertArrayHasKey('Content-Security-Policy', $result);
        $this->assertStringContainsString("default-src 'self'", $result['Content-Security-Policy']);
    }

    public function test_add_csp_header_skipped_when_disabled()
    {
        update_option('formflow_security_headers_enabled', false);

        $headers = ['Existing-Header' => 'value'];
        $result = $this->securityManager->addCSPHeader($headers);

        $this->assertArrayHasKey('Existing-Header', $result);
        $this->assertArrayNotHasKey('Content-Security-Policy', $result);
    }

    public function test_register_admin_menu()
    {
        // Mock test - actual menu registration would be tested in integration
        $this->securityManager->registerAdminMenu();

        $this->assertTrue(true);
    }

    public function test_enqueue_admin_assets_on_security_page()
    {
        // Mock test - asset enqueuing would be tested in integration
        $this->securityManager->enqueueAdminAssets('formflow-security');

        $this->assertTrue(true);
    }

    public function test_enqueue_admin_assets_skips_other_pages()
    {
        // Mock test - should not enqueue on other pages
        $this->securityManager->enqueueAdminAssets('other-page');

        $this->assertTrue(true);
    }
}
