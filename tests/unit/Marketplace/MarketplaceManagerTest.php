<?php
/**
 * Tests for MarketplaceManager class.
 */

namespace FormFlowPro\Tests\Unit\Marketplace;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Marketplace\MarketplaceManager;

class MarketplaceManagerTest extends TestCase
{
    private $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = MarketplaceManager::getInstance();
    }

    public function test_singleton_instance()
    {
        $instance1 = MarketplaceManager::getInstance();
        $instance2 = MarketplaceManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_get_available_integrations()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'name' => 'Slack Integration',
                'slug' => 'slack',
                'category' => 'communication',
                'status' => 'active',
            ],
            (object)[
                'id' => '2',
                'name' => 'Salesforce Integration',
                'slug' => 'salesforce',
                'category' => 'crm',
                'status' => 'active',
            ],
        ]);

        $integrations = $this->manager->getAvailableIntegrations();

        $this->assertIsArray($integrations);
    }

    public function test_get_integration_by_slug()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'name' => 'Slack Integration',
            'slug' => 'slack',
            'description' => 'Send notifications to Slack channels',
            'category' => 'communication',
            'config_schema' => json_encode([
                'webhook_url' => ['type' => 'string', 'required' => true],
                'channel' => ['type' => 'string', 'required' => false],
            ]),
            'status' => 'active',
        ]);

        $integration = $this->manager->getIntegration('slack');

        $this->assertIsObject($integration);
        $this->assertEquals('Slack Integration', $integration->name);
    }

    public function test_install_integration()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'slug' => 'slack',
            'name' => 'Slack Integration',
            'status' => 'active',
        ]);

        $config = [
            'webhook_url' => 'https://hooks.slack.com/services/xxx',
            'channel' => '#notifications',
        ];

        $result = $this->manager->installIntegration('slack', $config);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    public function test_install_integration_validation_fails()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'slug' => 'slack',
            'config_schema' => json_encode([
                'webhook_url' => ['type' => 'string', 'required' => true],
            ]),
        ]);

        // Missing required webhook_url
        $config = [
            'channel' => '#notifications',
        ];

        $result = $this->manager->installIntegration('slack', $config);

        $this->assertFalse($result['success']);
    }

    public function test_uninstall_integration()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'slug' => 'slack',
            'is_installed' => 1,
        ]);

        $result = $this->manager->uninstallIntegration('slack');

        $this->assertTrue($result);
    }

    public function test_get_installed_integrations()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'integration_slug' => 'slack',
                'config' => json_encode(['webhook_url' => 'https://...']),
                'enabled' => 1,
            ],
        ]);

        $installed = $this->manager->getInstalledIntegrations();

        $this->assertIsArray($installed);
    }

    public function test_toggle_integration()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'integration_slug' => 'slack',
            'enabled' => 1,
        ]);

        $result = $this->manager->toggleIntegration('slack', false);

        $this->assertTrue($result['success']);
    }

    public function test_update_integration_config()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'integration_slug' => 'slack',
            'config' => json_encode(['webhook_url' => 'old-url']),
        ]);

        $newConfig = [
            'webhook_url' => 'https://hooks.slack.com/new',
            'channel' => '#new-channel',
        ];

        $result = $this->manager->updateIntegrationConfig('slack', $newConfig);

        $this->assertTrue($result['success']);
    }

    public function test_get_integration_categories()
    {
        $categories = $this->manager->getCategories();

        $this->assertIsArray($categories);
        $this->assertContains('communication', array_keys($categories));
        $this->assertContains('crm', array_keys($categories));
        $this->assertContains('storage', array_keys($categories));
        $this->assertContains('analytics', array_keys($categories));
    }

    public function test_search_integrations()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'name' => 'Slack Integration',
                'slug' => 'slack',
            ],
        ]);

        $results = $this->manager->searchIntegrations('slack');

        $this->assertIsArray($results);
    }

    public function test_filter_integrations_by_category()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'name' => 'Salesforce',
                'category' => 'crm',
            ],
            (object)[
                'id' => '2',
                'name' => 'HubSpot',
                'category' => 'crm',
            ],
        ]);

        $results = $this->manager->getIntegrationsByCategory('crm');

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
    }

    public function test_execute_integration_action()
    {
        global $wpdb, $wp_http_mock_response;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'integration_slug' => 'slack',
            'config' => json_encode(['webhook_url' => 'https://hooks.slack.com/xxx']),
            'enabled' => 1,
        ]);

        // Mock successful webhook response
        $wp_http_mock_response = [
            'response' => ['code' => 200],
            'body' => 'ok',
        ];

        $result = $this->manager->executeAction('slack', 'send_message', [
            'text' => 'Hello from FormFlow!',
        ]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    public function test_execute_integration_action_disabled()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'integration_slug' => 'slack',
            'enabled' => 0, // Disabled
        ]);

        $result = $this->manager->executeAction('slack', 'send_message', []);

        $this->assertFalse($result['success']);
    }

    public function test_get_integration_logs()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'integration_slug' => 'slack',
                'action' => 'send_message',
                'status' => 'success',
                'created_at' => '2024-01-15 10:00:00',
            ],
        ]);

        $logs = $this->manager->getIntegrationLogs('slack', 1, 10);

        $this->assertIsArray($logs);
    }

    public function test_validate_integration_config()
    {
        $schema = [
            'webhook_url' => ['type' => 'string', 'required' => true],
            'channel' => ['type' => 'string', 'required' => false],
            'timeout' => ['type' => 'integer', 'required' => false, 'default' => 30],
        ];

        $config = [
            'webhook_url' => 'https://example.com/webhook',
            'channel' => '#general',
        ];

        $isValid = $this->callPrivateMethod($this->manager, 'validateConfig', [$config, $schema]);

        $this->assertTrue($isValid);
    }

    public function test_validate_integration_config_missing_required()
    {
        $schema = [
            'api_key' => ['type' => 'string', 'required' => true],
        ];

        $config = [
            'other_field' => 'value',
        ];

        $isValid = $this->callPrivateMethod($this->manager, 'validateConfig', [$config, $schema]);

        $this->assertFalse($isValid);
    }

    public function test_register_custom_integration()
    {
        $integrationData = [
            'name' => 'Custom CRM',
            'slug' => 'custom-crm',
            'description' => 'Custom CRM integration',
            'category' => 'crm',
            'config_schema' => [
                'api_url' => ['type' => 'string', 'required' => true],
                'api_key' => ['type' => 'string', 'required' => true],
            ],
            'actions' => [
                'create_contact' => [
                    'label' => 'Create Contact',
                    'fields' => ['name', 'email', 'phone'],
                ],
            ],
        ];

        $result = $this->manager->registerIntegration($integrationData);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    public function test_get_integration_webhooks()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'integration_slug' => 'slack',
                'event' => 'form_submitted',
                'webhook_url' => 'https://hooks.slack.com/xxx',
                'enabled' => 1,
            ],
        ]);

        $webhooks = $this->manager->getIntegrationWebhooks('slack');

        $this->assertIsArray($webhooks);
    }

    public function test_create_webhook()
    {
        global $wpdb;

        $webhookData = [
            'integration_slug' => 'slack',
            'event' => 'form_submitted',
            'form_id' => 'form-123',
            'config' => ['channel' => '#notifications'],
        ];

        $result = $this->manager->createWebhook($webhookData);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    public function test_delete_webhook()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'integration_slug' => 'slack',
        ]);

        $result = $this->manager->deleteWebhook('1');

        $this->assertTrue($result);
    }

    public function test_get_api_credentials()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'api_key' => 'ffp_live_xxxx',
            'api_secret' => 'encrypted_secret',
            'created_at' => '2024-01-01 10:00:00',
        ]);

        $credentials = $this->manager->getAPICredentials();

        $this->assertIsArray($credentials);
        $this->assertArrayHasKey('api_key', $credentials);
    }

    public function test_regenerate_api_credentials()
    {
        $result = $this->manager->regenerateAPICredentials();

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('api_key', $result);
        $this->assertArrayHasKey('api_secret', $result);
    }

    public function test_validate_api_request()
    {
        global $wpdb;

        // Store API key
        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'api_key' => 'ffp_live_test123',
            'api_secret_hash' => password_hash('secret123', PASSWORD_DEFAULT),
            'enabled' => 1,
        ]);

        $isValid = $this->manager->validateAPIRequest('ffp_live_test123', 'secret123');

        $this->assertTrue($isValid);
    }

    public function test_validate_api_request_invalid()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', null);

        $isValid = $this->manager->validateAPIRequest('invalid-key', 'invalid-secret');

        $this->assertFalse($isValid);
    }
}
