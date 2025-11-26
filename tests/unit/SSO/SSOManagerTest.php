<?php
/**
 * Tests for SSOManager class.
 */

namespace FormFlowPro\Tests\Unit\SSO;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\SSO\SSOManager;

class SSOManagerTest extends TestCase
{
    private $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = SSOManager::getInstance();
    }

    public function test_singleton_instance()
    {
        $instance1 = SSOManager::getInstance();
        $instance2 = SSOManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_get_supported_providers()
    {
        $providers = $this->manager->getSupportedProviders();

        $this->assertIsArray($providers);
        $this->assertArrayHasKey('google', $providers);
        $this->assertArrayHasKey('microsoft', $providers);
        $this->assertArrayHasKey('okta', $providers);
        $this->assertArrayHasKey('auth0', $providers);
    }

    public function test_configure_provider()
    {
        $config = [
            'provider' => 'google',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'redirect_uri' => 'https://example.com/callback',
            'enabled' => true,
        ];

        $result = $this->manager->configureProvider($config);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    public function test_configure_provider_validation_fails()
    {
        $config = [
            'provider' => 'google',
            // Missing required fields
        ];

        $result = $this->manager->configureProvider($config);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_get_provider_config()
    {
        // First configure a provider
        update_option('formflow_sso_google', [
            'client_id' => 'test-id',
            'client_secret' => 'test-secret',
            'enabled' => true,
        ]);

        $config = $this->manager->getProviderConfig('google');

        $this->assertIsArray($config);
        $this->assertEquals('test-id', $config['client_id']);
    }

    public function test_get_authorization_url()
    {
        update_option('formflow_sso_google', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-secret',
            'redirect_uri' => 'https://example.com/callback',
            'enabled' => true,
        ]);

        $url = $this->manager->getAuthorizationUrl('google', 'random-state-123');

        $this->assertIsString($url);
        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('state=random-state-123', $url);
    }

    public function test_exchange_code_for_tokens()
    {
        global $wp_http_mock_response;

        update_option('formflow_sso_google', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-secret',
            'redirect_uri' => 'https://example.com/callback',
            'enabled' => true,
        ]);

        // Mock successful token response
        $wp_http_mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'access_token' => 'mock-access-token',
                'refresh_token' => 'mock-refresh-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
        ];

        $tokens = $this->manager->exchangeCodeForTokens('google', 'auth-code-123');

        $this->assertIsArray($tokens);
        $this->assertArrayHasKey('access_token', $tokens);
    }

    public function test_exchange_code_handles_error()
    {
        global $wp_http_mock_error;

        update_option('formflow_sso_google', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-secret',
            'enabled' => true,
        ]);

        $wp_http_mock_error = 'Connection failed';

        $tokens = $this->manager->exchangeCodeForTokens('google', 'auth-code-123');

        $this->assertFalse($tokens);
    }

    public function test_get_user_info()
    {
        global $wp_http_mock_response;

        // Mock user info response
        $wp_http_mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'sub' => 'user-123',
                'email' => 'user@example.com',
                'name' => 'Test User',
                'picture' => 'https://example.com/avatar.jpg',
            ]),
        ];

        $userInfo = $this->manager->getUserInfo('google', 'access-token-123');

        $this->assertIsArray($userInfo);
        $this->assertEquals('user@example.com', $userInfo['email']);
        $this->assertEquals('Test User', $userInfo['name']);
    }

    public function test_validate_token()
    {
        global $wp_http_mock_response;

        // Mock valid token response
        $wp_http_mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'aud' => 'test-client-id',
                'exp' => time() + 3600,
                'iss' => 'https://accounts.google.com',
            ]),
        ];

        update_option('formflow_sso_google', [
            'client_id' => 'test-client-id',
            'enabled' => true,
        ]);

        $isValid = $this->manager->validateToken('google', 'id-token-123');

        $this->assertTrue($isValid);
    }

    public function test_validate_token_expired()
    {
        global $wp_http_mock_response;

        // Mock expired token response
        $wp_http_mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'aud' => 'test-client-id',
                'exp' => time() - 3600, // Expired
                'iss' => 'https://accounts.google.com',
            ]),
        ];

        update_option('formflow_sso_google', [
            'client_id' => 'test-client-id',
            'enabled' => true,
        ]);

        $isValid = $this->manager->validateToken('google', 'expired-token');

        $this->assertFalse($isValid);
    }

    public function test_refresh_access_token()
    {
        global $wp_http_mock_response;

        update_option('formflow_sso_google', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-secret',
            'enabled' => true,
        ]);

        $wp_http_mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
            ]),
        ];

        $newToken = $this->manager->refreshAccessToken('google', 'refresh-token-123');

        $this->assertIsArray($newToken);
        $this->assertEquals('new-access-token', $newToken['access_token']);
    }

    public function test_create_or_update_user()
    {
        global $wpdb;

        $userInfo = [
            'provider' => 'google',
            'provider_id' => 'user-123',
            'email' => 'newuser@example.com',
            'name' => 'New User',
        ];

        // User doesn't exist yet
        $wpdb->set_mock_result('get_row', null);

        $result = $this->manager->createOrUpdateUser($userInfo);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    public function test_link_provider_to_user()
    {
        global $wpdb;

        $result = $this->manager->linkProviderToUser(1, 'google', 'provider-user-id-123');

        $this->assertTrue($result);

        $inserts = $wpdb->get_mock_inserts();
        $linkFound = false;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_sso_links') !== false) {
                $linkFound = true;
                $this->assertEquals('google', $insert['data']['provider']);
            }
        }

        $this->assertTrue($linkFound);
    }

    public function test_unlink_provider_from_user()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'user_id' => 1,
            'provider' => 'google',
        ]);

        $result = $this->manager->unlinkProviderFromUser(1, 'google');

        $this->assertTrue($result);
    }

    public function test_get_user_linked_providers()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)['provider' => 'google', 'provider_user_id' => 'g-123'],
            (object)['provider' => 'microsoft', 'provider_user_id' => 'm-456'],
        ]);

        $providers = $this->manager->getUserLinkedProviders(1);

        $this->assertIsArray($providers);
        $this->assertCount(2, $providers);
    }

    public function test_generate_state_token()
    {
        $state = $this->manager->generateStateToken();

        $this->assertIsString($state);
        $this->assertGreaterThanOrEqual(32, strlen($state));
    }

    public function test_validate_state_token()
    {
        $state = $this->manager->generateStateToken();

        // Store state in transient
        set_transient('formflow_sso_state_' . $state, ['created' => time()], 600);

        $isValid = $this->manager->validateStateToken($state);

        $this->assertTrue($isValid);
    }

    public function test_validate_state_token_invalid()
    {
        $isValid = $this->manager->validateStateToken('invalid-state-token');

        $this->assertFalse($isValid);
    }

    public function test_saml_configuration()
    {
        $samlConfig = [
            'idp_entity_id' => 'https://idp.example.com',
            'idp_sso_url' => 'https://idp.example.com/sso',
            'idp_certificate' => '-----BEGIN CERTIFICATE-----...',
            'sp_entity_id' => 'https://sp.example.com',
            'sp_acs_url' => 'https://sp.example.com/acs',
        ];

        $result = $this->manager->configureSAML($samlConfig);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    public function test_get_enabled_providers()
    {
        update_option('formflow_sso_google', ['enabled' => true, 'client_id' => 'test']);
        update_option('formflow_sso_microsoft', ['enabled' => false]);
        update_option('formflow_sso_okta', ['enabled' => true, 'client_id' => 'test']);

        $enabled = $this->manager->getEnabledProviders();

        $this->assertIsArray($enabled);
        $this->assertContains('google', $enabled);
        $this->assertContains('okta', $enabled);
        $this->assertNotContains('microsoft', $enabled);
    }
}
