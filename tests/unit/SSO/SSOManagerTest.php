<?php
/**
 * Tests for SSOManager class.
 *
 * @package FormFlowPro\Tests\Unit\SSO
 */

namespace FormFlowPro\Tests\Unit\SSO;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\SSO\SSOManager;
use FormFlowPro\SSO\SSOSession;
use FormFlowPro\SSO\SSOIdentityLink;
use FormFlowPro\SSO\SSOSettings;

class SSOManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset singleton for each test
        $reflection = new \ReflectionClass(SSOManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }

    protected function tearDown(): void
    {
        // Reset singleton
        $reflection = new \ReflectionClass(SSOManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        parent::tearDown();
    }

    // ==========================================================================
    // Singleton Tests
    // ==========================================================================

    public function test_singleton_instance()
    {
        $instance1 = SSOManager::getInstance();
        $instance2 = SSOManager::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(SSOManager::class, $instance1);
    }

    // ==========================================================================
    // SSOSession Model Tests
    // ==========================================================================

    public function test_sso_session_constructor()
    {
        $session = new SSOSession([
            'id' => 1,
            'user_id' => 100,
            'provider_type' => 'oauth2',
            'provider_id' => 'azure',
            'session_id' => 'session-uuid-123',
            'external_id' => 'ext-user-123',
            'attributes' => ['department' => 'IT'],
            'access_token' => 'access_token_here',
            'refresh_token' => 'refresh_token_here',
            'token_expires' => time() + 3600,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0'
        ]);

        $this->assertEquals(1, $session->id);
        $this->assertEquals(100, $session->user_id);
        $this->assertEquals('oauth2', $session->provider_type);
        $this->assertEquals('azure', $session->provider_id);
        $this->assertEquals('session-uuid-123', $session->session_id);
        $this->assertEquals('ext-user-123', $session->external_id);
        $this->assertEquals('IT', $session->attributes['department']);
    }

    public function test_sso_session_defaults()
    {
        $session = new SSOSession([]);

        $this->assertEquals(0, $session->id);
        $this->assertEquals(0, $session->user_id);
        $this->assertEquals('', $session->provider_type);
        $this->assertEquals('', $session->session_id);
        $this->assertIsArray($session->attributes);
    }

    public function test_sso_session_is_expired_false()
    {
        $session = new SSOSession([
            'expires_at' => date('Y-m-d H:i:s', time() + 3600)
        ]);

        $this->assertFalse($session->isExpired());
    }

    public function test_sso_session_is_expired_true()
    {
        $session = new SSOSession([
            'expires_at' => date('Y-m-d H:i:s', time() - 3600)
        ]);

        $this->assertTrue($session->isExpired());
    }

    public function test_sso_session_is_expired_empty()
    {
        $session = new SSOSession([
            'expires_at' => ''
        ]);

        $this->assertFalse($session->isExpired());
    }

    public function test_sso_session_to_array()
    {
        $session = new SSOSession([
            'id' => 1,
            'user_id' => 100,
            'provider_type' => 'saml'
        ]);

        $array = $session->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('user_id', $array);
        $this->assertArrayHasKey('provider_type', $array);
        $this->assertEquals(1, $array['id']);
    }

    // ==========================================================================
    // SSOIdentityLink Model Tests
    // ==========================================================================

    public function test_sso_identity_link_constructor()
    {
        $link = new SSOIdentityLink([
            'id' => 1,
            'user_id' => 100,
            'provider_type' => 'oauth2',
            'provider_id' => 'google',
            'external_id' => 'google-user-123',
            'email' => 'user@example.com',
            'profile_data' => ['picture' => 'https://example.com/avatar.jpg'],
            'is_primary' => true
        ]);

        $this->assertEquals(1, $link->id);
        $this->assertEquals(100, $link->user_id);
        $this->assertEquals('oauth2', $link->provider_type);
        $this->assertEquals('google', $link->provider_id);
        $this->assertEquals('user@example.com', $link->email);
        $this->assertTrue($link->is_primary);
    }

    public function test_sso_identity_link_defaults()
    {
        $link = new SSOIdentityLink([]);

        $this->assertEquals(0, $link->id);
        $this->assertEquals(0, $link->user_id);
        $this->assertEquals('', $link->provider_type);
        $this->assertEquals('', $link->email);
        $this->assertFalse($link->is_primary);
    }

    public function test_sso_identity_link_to_array()
    {
        $link = new SSOIdentityLink([
            'id' => 5,
            'user_id' => 50,
            'email' => 'test@test.com'
        ]);

        $array = $link->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('user_id', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertEquals('test@test.com', $array['email']);
    }

    // ==========================================================================
    // SSOSettings Model Tests
    // ==========================================================================

    public function test_sso_settings_constructor()
    {
        $settings = new SSOSettings([
            'enabled' => true,
            'force_sso' => true,
            'allow_local_login' => false,
            'auto_provision_users' => true,
            'default_role' => 'editor',
            'session_lifetime' => 3600,
            'single_logout_enabled' => true,
            'allowed_domains' => ['company.com'],
            'blocked_domains' => ['blocked.com']
        ]);

        $this->assertTrue($settings->enabled);
        $this->assertTrue($settings->force_sso);
        $this->assertFalse($settings->allow_local_login);
        $this->assertTrue($settings->auto_provision_users);
        $this->assertEquals('editor', $settings->default_role);
        $this->assertEquals(3600, $settings->session_lifetime);
        $this->assertContains('company.com', $settings->allowed_domains);
    }

    public function test_sso_settings_defaults()
    {
        $settings = new SSOSettings([]);

        $this->assertFalse($settings->enabled);
        $this->assertFalse($settings->force_sso);
        $this->assertTrue($settings->allow_local_login);
        $this->assertTrue($settings->auto_provision_users);
        $this->assertEquals('subscriber', $settings->default_role);
        $this->assertEquals(28800, $settings->session_lifetime);
        $this->assertTrue($settings->single_logout_enabled);
        $this->assertEmpty($settings->allowed_domains);
        $this->assertEmpty($settings->blocked_domains);
    }

    public function test_sso_settings_to_array()
    {
        $settings = new SSOSettings([
            'enabled' => true,
            'default_role' => 'author'
        ]);

        $array = $settings->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('enabled', $array);
        $this->assertArrayHasKey('default_role', $array);
        $this->assertArrayHasKey('force_sso', $array);
        $this->assertArrayHasKey('allow_local_login', $array);
        $this->assertArrayHasKey('auto_provision_users', $array);
        $this->assertArrayHasKey('session_lifetime', $array);
        $this->assertEquals('author', $array['default_role']);
    }

    public function test_sso_settings_role_mapping()
    {
        $settings = new SSOSettings([
            'role_mapping' => [
                'administrator' => ['IT Admins', 'Super Users'],
                'editor' => ['Content Team']
            ]
        ]);

        $this->assertIsArray($settings->role_mapping);
        $this->assertArrayHasKey('administrator', $settings->role_mapping);
        $this->assertContains('IT Admins', $settings->role_mapping['administrator']);
    }

    public function test_sso_settings_login_configuration()
    {
        $settings = new SSOSettings([
            'login_button_text' => 'Sign in with Corporate ID',
            'login_button_position' => 'below',
            'hide_local_login' => true,
            'redirect_after_login' => '/dashboard',
            'redirect_after_logout' => '/goodbye'
        ]);

        $this->assertEquals('Sign in with Corporate ID', $settings->login_button_text);
        $this->assertEquals('below', $settings->login_button_position);
        $this->assertTrue($settings->hide_local_login);
        $this->assertEquals('/dashboard', $settings->redirect_after_login);
        $this->assertEquals('/goodbye', $settings->redirect_after_logout);
    }

    public function test_sso_settings_enabled_providers()
    {
        $settings = new SSOSettings([
            'enabled_providers' => ['saml', 'oauth2_azure', 'ldap']
        ]);

        $this->assertIsArray($settings->enabled_providers);
        $this->assertContains('saml', $settings->enabled_providers);
        $this->assertContains('oauth2_azure', $settings->enabled_providers);
        $this->assertContains('ldap', $settings->enabled_providers);
    }

    // ==========================================================================
    // Manager Public Methods Tests
    // ==========================================================================

    public function test_validate_sso_session_when_not_logged_in()
    {
        $manager = SSOManager::getInstance();

        // Should not throw when user is not logged in
        $manager->validateSSOSession();

        $this->assertTrue(true);
    }

    public function test_filter_login_message_without_error()
    {
        $manager = SSOManager::getInstance();

        $message = $manager->filterLoginMessage('Existing message');

        $this->assertEquals('Existing message', $message);
    }

    public function test_filter_login_message_with_error()
    {
        $_GET['sso_error'] = urlencode('Test error message');

        $manager = SSOManager::getInstance();
        $message = $manager->filterLoginMessage('');

        $this->assertStringContainsString('Test error message', $message);
        $this->assertStringContainsString('SSO Error:', $message);

        unset($_GET['sso_error']);
    }

    public function test_clear_sso_session()
    {
        $manager = SSOManager::getInstance();

        // Should not throw
        $manager->clearSSOSession();

        $this->assertTrue(true);
    }

    public function test_handle_logout()
    {
        $manager = SSOManager::getInstance();

        // Should not throw
        $manager->handleLogout();

        $this->assertTrue(true);
    }

    public function test_cleanup_expired_sessions()
    {
        global $wpdb;

        $manager = SSOManager::getInstance();

        // Should not throw
        $manager->cleanupExpiredSessions();

        $this->assertTrue(true);
    }

    // ==========================================================================
    // Integration Tests
    // ==========================================================================

    public function test_sso_settings_persistence()
    {
        $original = new SSOSettings([
            'enabled' => true,
            'force_sso' => true,
            'default_role' => 'editor'
        ]);

        // Convert to array and back
        $array = $original->toArray();
        $restored = new SSOSettings($array);

        $this->assertEquals($original->enabled, $restored->enabled);
        $this->assertEquals($original->force_sso, $restored->force_sso);
        $this->assertEquals($original->default_role, $restored->default_role);
    }

    public function test_sso_session_token_management()
    {
        $session = new SSOSession([
            'access_token' => 'token123',
            'refresh_token' => 'refresh456',
            'token_expires' => time() + 3600
        ]);

        $this->assertEquals('token123', $session->access_token);
        $this->assertEquals('refresh456', $session->refresh_token);
        $this->assertGreaterThan(time(), $session->token_expires);
    }
}
