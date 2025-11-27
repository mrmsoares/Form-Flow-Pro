<?php
/**
 * Tests for IntegrationManager class.
 *
 * @package FormFlowPro\Tests\Unit\Integrations
 */

namespace FormFlowPro\Tests\Unit\Integrations;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Integrations\IntegrationManager;
use FormFlowPro\Integrations\IntegrationInterface;

class IntegrationManagerTest extends TestCase
{
    private $manager;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset singleton
        $reflection = new \ReflectionClass(IntegrationManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $this->manager = IntegrationManager::getInstance();
    }

    // ==========================================================================
    // Singleton Tests
    // ==========================================================================

    public function test_get_instance_returns_same_instance()
    {
        $instance1 = IntegrationManager::getInstance();
        $instance2 = IntegrationManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_get_instance_returns_integration_manager()
    {
        $instance = IntegrationManager::getInstance();
        $this->assertInstanceOf(IntegrationManager::class, $instance);
    }

    // ==========================================================================
    // Registration Tests
    // ==========================================================================

    public function test_default_integrations_are_registered()
    {
        $all = $this->manager->getAll();

        $this->assertArrayHasKey('salesforce', $all);
        $this->assertArrayHasKey('hubspot', $all);
        $this->assertArrayHasKey('zapier', $all);
        $this->assertArrayHasKey('google_sheets', $all);
    }

    public function test_register_custom_integration()
    {
        $mockIntegration = $this->createMock(IntegrationInterface::class);
        $mockIntegration->method('getId')->willReturn('custom_integration');

        $result = $this->manager->register($mockIntegration);

        $this->assertSame($this->manager, $result); // Fluent interface
        $this->assertSame($mockIntegration, $this->manager->get('custom_integration'));
    }

    public function test_get_returns_null_for_unknown_integration()
    {
        $result = $this->manager->get('non_existent_integration');
        $this->assertNull($result);
    }

    public function test_get_returns_integration_instance()
    {
        $integration = $this->manager->get('zapier');

        $this->assertNotNull($integration);
        $this->assertInstanceOf(IntegrationInterface::class, $integration);
        $this->assertEquals('zapier', $integration->getId());
    }

    // ==========================================================================
    // Get All/Enabled Tests
    // ==========================================================================

    public function test_get_all_returns_array()
    {
        $all = $this->manager->getAll();

        $this->assertIsArray($all);
        $this->assertNotEmpty($all);
    }

    public function test_get_enabled_returns_only_enabled_integrations()
    {
        // Enable Zapier
        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'webhooks' => [1 => ['url' => 'https://test.com']],
        ]);

        // Disable Salesforce
        set_option('formflow_integration_salesforce', [
            'enabled' => false,
        ]);

        // Reset manager to pick up new options
        $reflection = new \ReflectionClass(IntegrationManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $manager = IntegrationManager::getInstance();

        $enabled = $manager->getEnabled();

        $enabledIds = array_keys($enabled);
        $this->assertContains('zapier', $enabledIds);
        $this->assertNotContains('salesforce', $enabledIds);
    }

    public function test_get_enabled_requires_configured()
    {
        // Enable but don't configure
        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'webhooks' => [], // Not configured
        ]);

        // Reset manager
        $reflection = new \ReflectionClass(IntegrationManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $manager = IntegrationManager::getInstance();

        $enabled = $manager->getEnabled();

        $enabledIds = array_keys($enabled);
        $this->assertNotContains('zapier', $enabledIds);
    }

    public function test_get_integrations_list_returns_formatted_array()
    {
        $list = $this->manager->getIntegrationsList();

        $this->assertIsArray($list);
        $this->assertNotEmpty($list);

        foreach ($list as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('icon', $item);
            $this->assertArrayHasKey('configured', $item);
            $this->assertArrayHasKey('enabled', $item);
        }
    }

    // ==========================================================================
    // Form Mapping Tests
    // ==========================================================================

    public function test_save_form_mapping()
    {
        $mapping = [
            'Email' => 'email_field',
            'Name' => 'name_field',
        ];

        $result = $this->manager->saveFormMapping(123, 'salesforce', $mapping);

        $this->assertTrue($result);

        $saved = $this->manager->getFormMappings(123);
        $this->assertArrayHasKey('salesforce', $saved);
        $this->assertEquals($mapping, $saved['salesforce']);
    }

    public function test_get_form_mappings_returns_empty_for_unmapped_form()
    {
        $mappings = $this->manager->getFormMappings(999);
        $this->assertIsArray($mappings);
        $this->assertEmpty($mappings);
    }

    public function test_get_form_mappings_returns_all_integrations_for_form()
    {
        $this->manager->saveFormMapping(123, 'salesforce', ['Email' => 'email']);
        $this->manager->saveFormMapping(123, 'hubspot', ['email' => 'email']);

        $mappings = $this->manager->getFormMappings(123);

        $this->assertCount(2, $mappings);
        $this->assertArrayHasKey('salesforce', $mappings);
        $this->assertArrayHasKey('hubspot', $mappings);
    }

    public function test_remove_form_mapping()
    {
        $this->manager->saveFormMapping(123, 'salesforce', ['Email' => 'email']);
        $this->manager->saveFormMapping(123, 'hubspot', ['email' => 'email']);

        $result = $this->manager->removeFormMapping(123, 'salesforce');

        $this->assertTrue($result);

        $mappings = $this->manager->getFormMappings(123);
        $this->assertArrayNotHasKey('salesforce', $mappings);
        $this->assertArrayHasKey('hubspot', $mappings);
    }

    // ==========================================================================
    // Submission Processing Tests
    // ==========================================================================

    public function test_process_submission_sends_to_all_enabled_integrations()
    {
        global $wpdb;

        // Configure and enable Zapier
        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'webhooks' => [1 => ['url' => 'https://test.com']],
            'default_webhook_url' => 'https://hooks.zapier.com/test',
        ]);

        // Set mapping for form
        set_option('formflow_integration_mappings', [
            123 => [
                'zapier' => ['_webhook_url' => 'https://hooks.zapier.com/test'],
            ],
        ]);

        // Reset manager
        $reflection = new \ReflectionClass(IntegrationManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $manager = IntegrationManager::getInstance();

        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'test_123']),
            ];
        });

        $submission = [
            'id' => 1,
            'form_data' => ['email' => 'test@example.com'],
        ];

        $results = $manager->processSubmission($submission, 123);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('zapier', $results);
        $this->assertTrue($results['zapier']['success']);
    }

    public function test_process_submission_skips_unmapped_integrations()
    {
        // Enable Zapier but don't map it to form
        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'webhooks' => [1 => ['url' => 'https://test.com']],
        ]);

        set_option('formflow_integration_mappings', [
            123 => [], // No mappings for form
        ]);

        // Reset manager
        $reflection = new \ReflectionClass(IntegrationManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $manager = IntegrationManager::getInstance();

        $submission = [
            'id' => 1,
            'form_data' => ['email' => 'test@example.com'],
        ];

        $results = $manager->processSubmission($submission, 123);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function test_process_submission_logs_success()
    {
        global $wpdb;

        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'webhooks' => [1 => ['url' => 'https://test.com']],
            'default_webhook_url' => 'https://hooks.zapier.com/test',
        ]);

        set_option('formflow_integration_mappings', [
            123 => [
                'zapier' => [],
            ],
        ]);

        // Reset manager
        $reflection = new \ReflectionClass(IntegrationManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $manager = IntegrationManager::getInstance();

        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'test_123']),
            ];
        });

        $submission = [
            'id' => 42,
            'form_data' => ['email' => 'test@example.com'],
        ];

        $manager->processSubmission($submission, 123);

        // Check sync log
        $inserts = $wpdb->get_mock_inserts();
        $syncLogged = false;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_integration_sync') !== false) {
                if ($insert['data']['submission_id'] === 42 &&
                    $insert['data']['integration_id'] === 'zapier' &&
                    $insert['data']['status'] === 'success') {
                    $syncLogged = true;
                }
            }
        }

        $this->assertTrue($syncLogged);
    }

    public function test_process_submission_logs_failure()
    {
        global $wpdb;

        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'webhooks' => [1 => ['url' => 'https://test.com']],
            'default_webhook_url' => 'https://hooks.zapier.com/test',
        ]);

        set_option('formflow_integration_mappings', [
            123 => [
                'zapier' => [],
            ],
        ]);

        // Reset manager
        $reflection = new \ReflectionClass(IntegrationManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $manager = IntegrationManager::getInstance();

        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 500],
                'body' => json_encode(['error' => 'Server error']),
            ];
        });

        $submission = [
            'id' => 42,
            'form_data' => ['email' => 'test@example.com'],
        ];

        $manager->processSubmission($submission, 123);

        // Check sync log
        $inserts = $wpdb->get_mock_inserts();
        $failureLogged = false;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_integration_sync') !== false) {
                if ($insert['data']['submission_id'] === 42 &&
                    $insert['data']['integration_id'] === 'zapier' &&
                    $insert['data']['status'] === 'failed') {
                    $failureLogged = true;
                }
            }
        }

        $this->assertTrue($failureLogged);
    }

    public function test_process_submission_handles_exception()
    {
        global $wpdb;

        // Create a mock integration that throws exception
        $mockIntegration = $this->createMock(IntegrationInterface::class);
        $mockIntegration->method('getId')->willReturn('mock_error');
        $mockIntegration->method('isEnabled')->willReturn(true);
        $mockIntegration->method('isConfigured')->willReturn(true);
        $mockIntegration->method('sendSubmission')
            ->willThrowException(new \Exception('Test exception'));

        $this->manager->register($mockIntegration);

        set_option('formflow_integration_mappings', [
            123 => [
                'mock_error' => [],
            ],
        ]);

        $submission = [
            'id' => 1,
            'form_data' => ['email' => 'test@example.com'],
        ];

        $results = $this->manager->processSubmission($submission, 123);

        $this->assertArrayHasKey('mock_error', $results);
        $this->assertFalse($results['mock_error']['success']);
        $this->assertEquals('Test exception', $results['mock_error']['message']);
    }

    // ==========================================================================
    // Zapier Retry Tests
    // ==========================================================================

    public function test_handle_zapier_retry()
    {
        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'webhooks' => [1 => ['url' => 'https://test.com']],
            'default_webhook_url' => 'https://hooks.zapier.com/test',
        ]);

        // Reset manager
        $reflection = new \ReflectionClass(IntegrationManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $manager = IntegrationManager::getInstance();

        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'retry_success']),
            ];
        });

        $manager->handleZapierRetry(
            'https://hooks.zapier.com/retry',
            ['data' => ['email' => 'test@example.com']],
            42
        );

        // Should log the retry result
        global $wpdb;
        $inserts = $wpdb->get_mock_inserts();
        $retryLogged = false;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_integration_sync') !== false) {
                if ($insert['data']['submission_id'] === 42 &&
                    $insert['data']['integration_id'] === 'zapier') {
                    $retryLogged = true;
                }
            }
        }

        $this->assertTrue($retryLogged);
    }

    public function test_handle_zapier_retry_disabled()
    {
        set_option('formflow_integration_zapier', [
            'enabled' => false,
        ]);

        // Reset manager
        $reflection = new \ReflectionClass(IntegrationManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $manager = IntegrationManager::getInstance();

        global $wpdb;
        $wpdb->reset_mock_data();

        $manager->handleZapierRetry(
            'https://hooks.zapier.com/test',
            ['data' => []],
            1
        );

        // Should not log anything when disabled
        $inserts = $wpdb->get_mock_inserts();
        $this->assertEmpty($inserts);
    }

    // ==========================================================================
    // Sync History Tests
    // ==========================================================================

    public function test_get_sync_history()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            [
                'id' => 1,
                'submission_id' => 42,
                'integration_id' => 'salesforce',
                'status' => 'success',
                'external_id' => 'lead_123',
                'error_message' => null,
                'synced_at' => '2025-01-01 12:00:00',
            ],
            [
                'id' => 2,
                'submission_id' => 42,
                'integration_id' => 'hubspot',
                'status' => 'success',
                'external_id' => 'contact_456',
                'error_message' => null,
                'synced_at' => '2025-01-01 12:00:05',
            ],
        ]);

        $history = $this->manager->getSyncHistory(42);

        $this->assertCount(2, $history);
        $this->assertEquals('salesforce', $history[0]['integration_id']);
        $this->assertEquals('hubspot', $history[1]['integration_id']);
    }

    public function test_get_sync_history_empty()
    {
        global $wpdb;
        $wpdb->set_mock_result('get_results', []);

        $history = $this->manager->getSyncHistory(999);

        $this->assertIsArray($history);
        $this->assertEmpty($history);
    }

    // ==========================================================================
    // Sync Statistics Tests
    // ==========================================================================

    public function test_get_sync_stats_all()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', [
            'total' => 100,
            'success' => 85,
            'failed' => 10,
            'skipped' => 5,
        ]);

        $stats = $this->manager->getSyncStats();

        $this->assertEquals(100, $stats['total']);
        $this->assertEquals(85, $stats['success']);
        $this->assertEquals(10, $stats['failed']);
        $this->assertEquals(5, $stats['skipped']);
        $this->assertEquals(85.0, $stats['success_rate']);
    }

    public function test_get_sync_stats_by_integration()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', [
            'total' => 50,
            'success' => 45,
            'failed' => 5,
            'skipped' => 0,
        ]);

        $stats = $this->manager->getSyncStats('salesforce');

        $this->assertEquals(50, $stats['total']);
        $this->assertEquals(45, $stats['success']);
        $this->assertEquals(90.0, $stats['success_rate']);
    }

    public function test_get_sync_stats_by_period()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', [
            'total' => 10,
            'success' => 10,
            'failed' => 0,
            'skipped' => 0,
        ]);

        $stats = $this->manager->getSyncStats(null, 'today');

        $this->assertEquals(10, $stats['total']);
        $this->assertEquals(100.0, $stats['success_rate']);
    }

    public function test_get_sync_stats_zero_division()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
        ]);

        $stats = $this->manager->getSyncStats();

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['success_rate']);
    }

    // ==========================================================================
    // REST API Tests
    // ==========================================================================

    public function test_rest_get_integrations()
    {
        $response = $this->manager->restGetIntegrations();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }

    public function test_rest_get_integration_success()
    {
        $request = new \WP_REST_Request();
        $request->set_param('id', 'zapier');

        $response = $this->manager->restGetIntegration($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertEquals('zapier', $data['id']);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('config_fields', $data);
    }

    public function test_rest_get_integration_not_found()
    {
        $request = new \WP_REST_Request();
        $request->set_param('id', 'non_existent');

        $response = $this->manager->restGetIntegration($request);

        $this->assertEquals(404, $response->get_status());
    }

    public function test_rest_test_connection()
    {
        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'default_webhook_url' => 'https://hooks.zapier.com/test',
        ]);

        // Reset manager
        $reflection = new \ReflectionClass(IntegrationManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $manager = IntegrationManager::getInstance();

        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['status' => 'success']),
            ];
        });

        $request = new \WP_REST_Request();
        $request->set_param('id', 'zapier');

        $response = $manager->restTestConnection($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function test_rest_test_connection_not_found()
    {
        $request = new \WP_REST_Request();
        $request->set_param('id', 'non_existent');

        $response = $this->manager->restTestConnection($request);

        $this->assertEquals(404, $response->get_status());
    }

    public function test_rest_get_fields()
    {
        $request = new \WP_REST_Request();
        $request->set_param('id', 'zapier');

        $response = $this->manager->restGetFields($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertIsArray($data);
    }

    public function test_rest_get_fields_not_found()
    {
        $request = new \WP_REST_Request();
        $request->set_param('id', 'non_existent');

        $response = $this->manager->restGetFields($request);

        $this->assertEquals(404, $response->get_status());
    }

    // ==========================================================================
    // Admin Notice Tests
    // ==========================================================================

    public function test_display_connection_warnings_on_formflow_pages()
    {
        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'webhooks' => [], // Enabled but not configured
        ]);

        // Reset manager
        $reflection = new \ReflectionClass(IntegrationManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $manager = IntegrationManager::getInstance();

        // Mock current screen
        set_current_screen('formflow-settings');

        ob_start();
        $manager->displayConnectionWarnings();
        $output = ob_get_clean();

        $this->assertStringContainsString('not properly configured', $output);
        $this->assertStringContainsString('Zapier', $output);
    }

    public function test_display_connection_warnings_skipped_on_other_pages()
    {
        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'webhooks' => [],
        ]);

        // Reset manager
        $reflection = new \ReflectionClass(IntegrationManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $manager = IntegrationManager::getInstance();

        // Mock non-formflow screen
        set_current_screen('edit-post');

        ob_start();
        $manager->displayConnectionWarnings();
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    public function test_no_warnings_when_properly_configured()
    {
        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'webhooks' => [1 => ['url' => 'https://test.com']],
        ]);

        // Reset manager
        $reflection = new \ReflectionClass(IntegrationManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $manager = IntegrationManager::getInstance();

        set_current_screen('formflow-settings');

        ob_start();
        $manager->displayConnectionWarnings();
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }
}
