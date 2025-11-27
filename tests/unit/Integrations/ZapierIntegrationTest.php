<?php
/**
 * Tests for ZapierIntegration class.
 *
 * @package FormFlowPro\Tests\Unit\Integrations
 */

namespace FormFlowPro\Tests\Unit\Integrations;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Integrations\ZapierIntegration;
use FormFlowPro\Integrations\IntegrationInterface;

class ZapierIntegrationTest extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'default_webhook_url' => 'https://hooks.zapier.com/hooks/catch/123456/abcdef',
            'include_metadata' => true,
            'include_form_fields' => false,
            'flatten_data' => true,
            'retry_failed' => true,
            'max_retries' => 3,
        ]);

        $this->integration = new ZapierIntegration();
    }

    // ==========================================================================
    // Interface Implementation Tests
    // ==========================================================================

    public function test_implements_integration_interface()
    {
        $this->assertInstanceOf(IntegrationInterface::class, $this->integration);
    }

    public function test_get_id_returns_zapier()
    {
        $this->assertEquals('zapier', $this->integration->getId());
    }

    public function test_get_name_returns_zapier()
    {
        $this->assertNotEmpty($this->integration->getName());
    }

    public function test_get_description_returns_string()
    {
        $this->assertIsString($this->integration->getDescription());
    }

    public function test_get_icon_returns_url()
    {
        $icon = $this->integration->getIcon();
        $this->assertIsString($icon);
        $this->assertStringContainsString('zapier', strtolower($icon));
    }

    // ==========================================================================
    // Configuration Tests
    // ==========================================================================

    public function test_is_configured_returns_true_when_webhooks_set()
    {
        set_option('formflow_integration_zapier', [
            'webhooks' => [
                1 => ['url' => 'https://hooks.zapier.com/test'],
            ],
        ]);

        $integration = new ZapierIntegration();
        $this->assertTrue($integration->isConfigured());
    }

    public function test_is_configured_returns_false_when_no_webhooks()
    {
        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'webhooks' => [],
        ]);

        $integration = new ZapierIntegration();
        $this->assertFalse($integration->isConfigured());
    }

    public function test_is_enabled_returns_true_when_enabled()
    {
        $this->assertTrue($this->integration->isEnabled());
    }

    public function test_is_enabled_returns_false_when_disabled()
    {
        set_option('formflow_integration_zapier', ['enabled' => false]);
        $integration = new ZapierIntegration();
        $this->assertFalse($integration->isEnabled());
    }

    public function test_get_config_fields_returns_array()
    {
        $fields = $this->integration->getConfigFields();
        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);
    }

    public function test_get_config_fields_includes_webhook_url()
    {
        $fields = $this->integration->getConfigFields();
        $fieldNames = array_column($fields, 'name');
        $this->assertContains('default_webhook_url', $fieldNames);
    }

    // ==========================================================================
    // Connection Test Tests
    // ==========================================================================

    public function test_test_connection_fails_without_webhook_url()
    {
        set_option('formflow_integration_zapier', ['enabled' => true]);
        $integration = new ZapierIntegration();

        $result = $integration->testConnection();

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_test_connection_sends_test_payload()
    {
        $requestCaptured = null;

        set_wp_remote_post_handler(function($url, $args) use (&$requestCaptured) {
            $requestCaptured = [
                'url' => $url,
                'body' => json_decode($args['body'], true),
            ];

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['status' => 'success']),
            ];
        });

        $result = $this->integration->testConnection();

        $this->assertTrue($result['success']);
        $this->assertNotNull($requestCaptured);
        $this->assertEquals('https://hooks.zapier.com/hooks/catch/123456/abcdef', $requestCaptured['url']);
        $this->assertTrue($requestCaptured['body']['_test']);
    }

    public function test_test_connection_handles_webhook_error()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return new \WP_Error('http_request_failed', 'Connection timeout');
        });

        $result = $this->integration->testConnection();

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_test_connection_handles_http_error_response()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 500],
                'body' => json_encode(['message' => 'Internal Server Error']),
            ];
        });

        $result = $this->integration->testConnection();

        $this->assertFalse($result['success']);
    }

    // ==========================================================================
    // Submission Sending Tests
    // ==========================================================================

    public function test_send_submission_fails_when_disabled()
    {
        set_option('formflow_integration_zapier', ['enabled' => false]);
        $integration = new ZapierIntegration();

        $result = $integration->sendSubmission(
            ['id' => 1, 'form_data' => ['name' => 'John']],
            []
        );

        $this->assertFalse($result['success']);
    }

    public function test_send_submission_fails_without_webhook_url()
    {
        set_option('formflow_integration_zapier', ['enabled' => true]);
        $integration = new ZapierIntegration();

        $result = $integration->sendSubmission(
            ['id' => 1, 'form_data' => ['name' => 'John']],
            []
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('webhook URL', $result['message']);
    }

    public function test_send_submission_uses_mapping_webhook_url()
    {
        $capturedUrl = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedUrl) {
            $capturedUrl = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'zap_123']),
            ];
        });

        $result = $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['_webhook_url' => 'https://hooks.zapier.com/custom']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('https://hooks.zapier.com/custom', $capturedUrl);
    }

    public function test_send_submission_includes_metadata()
    {
        $capturedPayload = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedPayload) {
            $capturedPayload = json_decode($args['body'], true);
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'zap_123']),
            ];
        });

        $submission = [
            'id' => 42,
            'form_id' => 5,
            'form_name' => 'Contact Form',
            'created_at' => '2025-01-01 12:00:00',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'page_url' => 'https://example.com/contact',
            'form_data' => ['email' => 'test@example.com'],
        ];

        $this->integration->sendSubmission($submission, []);

        $this->assertArrayHasKey('_metadata', $capturedPayload);
        $this->assertEquals(42, $capturedPayload['_metadata']['submission_id']);
        $this->assertEquals(5, $capturedPayload['_metadata']['form_id']);
    }

    public function test_send_submission_flattens_nested_data()
    {
        $capturedPayload = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedPayload) {
            $capturedPayload = json_decode($args['body'], true);
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'zap_123']),
            ];
        });

        $submission = [
            'id' => 1,
            'form_data' => [
                'user' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
                'company' => [
                    'name' => 'Acme Corp',
                ],
            ],
        ];

        $this->integration->sendSubmission($submission, []);

        $this->assertArrayHasKey('user_name', $capturedPayload['data']);
        $this->assertArrayHasKey('user_email', $capturedPayload['data']);
        $this->assertArrayHasKey('company_name', $capturedPayload['data']);
    }

    public function test_send_submission_applies_field_mapping()
    {
        $capturedPayload = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedPayload) {
            $capturedPayload = json_decode($args['body'], true);
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'zap_123']),
            ];
        });

        $submission = [
            'id' => 1,
            'form_data' => [
                'email_field' => 'test@example.com',
                'name_field' => 'John Doe',
            ],
        ];

        $mapping = [
            'email' => 'email_field',
            'full_name' => 'name_field',
        ];

        $this->integration->sendSubmission($submission, $mapping);

        $this->assertEquals('test@example.com', $capturedPayload['data']['email']);
        $this->assertEquals('John Doe', $capturedPayload['data']['full_name']);
    }

    public function test_send_submission_handles_successful_response()
    {
        global $wpdb;

        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'zap_123', 'status' => 'success']),
            ];
        });

        $result = $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            []
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('zap_123', $result['external_id']);
        $this->assertEquals('sent', $result['action']);
    }

    public function test_send_submission_handles_failed_response()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 400],
                'body' => json_encode(['message' => 'Invalid data']),
            ];
        });

        $result = $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            []
        );

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    // ==========================================================================
    // Retry Logic Tests
    // ==========================================================================

    public function test_send_submission_schedules_retry_on_failure()
    {
        $scheduledEvents = [];

        set_function_handler('wp_schedule_single_event', function($timestamp, $hook, $args) use (&$scheduledEvents) {
            $scheduledEvents[] = [
                'timestamp' => $timestamp,
                'hook' => $hook,
                'args' => $args,
            ];
            return true;
        });

        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 500],
                'body' => '',
            ];
        });

        $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            []
        );

        $this->assertCount(1, $scheduledEvents);
        $this->assertEquals('formflow_zapier_retry', $scheduledEvents[0]['hook']);
    }

    public function test_retry_respects_max_retries()
    {
        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'default_webhook_url' => 'https://hooks.zapier.com/test',
            'retry_failed' => true,
            'max_retries' => 3,
        ]);

        $integration = new ZapierIntegration();

        // Set retry count to max
        set_transient('formflow_zapier_retry_1', 3, 3600);

        $scheduledEvents = [];
        set_function_handler('wp_schedule_single_event', function($timestamp, $hook, $args) use (&$scheduledEvents) {
            $scheduledEvents[] = $hook;
            return true;
        });

        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 500],
                'body' => '',
            ];
        });

        $integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            []
        );

        // Should not schedule retry when max is reached
        $this->assertEmpty($scheduledEvents);
    }

    // ==========================================================================
    // Webhook Management Tests
    // ==========================================================================

    public function test_add_form_webhook()
    {
        $result = $this->integration->addFormWebhook(
            123,
            'https://hooks.zapier.com/new-webhook',
            'Test Webhook'
        );

        $this->assertTrue($result);

        // Verify webhook was added
        $config = get_option('formflow_integration_zapier');
        $this->assertArrayHasKey('webhooks', $config);
        $this->assertArrayHasKey(123, $config['webhooks']);
        $this->assertEquals('https://hooks.zapier.com/new-webhook', $config['webhooks'][123]['url']);
        $this->assertEquals('Test Webhook', $config['webhooks'][123]['name']);
    }

    public function test_remove_form_webhook()
    {
        // First add a webhook
        $this->integration->addFormWebhook(123, 'https://hooks.zapier.com/test');

        // Then remove it
        $result = $this->integration->removeFormWebhook(123);

        $this->assertTrue($result);

        // Verify webhook was removed
        $config = get_option('formflow_integration_zapier');
        $this->assertArrayNotHasKey(123, $config['webhooks'] ?? []);
    }

    public function test_get_form_webhook_url_returns_specific_webhook()
    {
        set_option('formflow_integration_zapier', [
            'default_webhook_url' => 'https://hooks.zapier.com/default',
            'webhooks' => [
                123 => [
                    'url' => 'https://hooks.zapier.com/form-specific',
                    'enabled' => true,
                ],
            ],
        ]);

        $integration = new ZapierIntegration();
        $url = $integration->getFormWebhookUrl(123);

        $this->assertEquals('https://hooks.zapier.com/form-specific', $url);
    }

    public function test_get_form_webhook_url_returns_default_when_no_specific()
    {
        $url = $this->integration->getFormWebhookUrl(999);
        $this->assertEquals('https://hooks.zapier.com/hooks/catch/123456/abcdef', $url);
    }

    public function test_get_form_webhook_url_returns_null_when_disabled()
    {
        set_option('formflow_integration_zapier', [
            'webhooks' => [
                123 => [
                    'url' => 'https://hooks.zapier.com/test',
                    'enabled' => false,
                ],
            ],
        ]);

        $integration = new ZapierIntegration();
        $url = $integration->getFormWebhookUrl(123);

        $this->assertNull($url);
    }

    // ==========================================================================
    // Field Info Tests
    // ==========================================================================

    public function test_get_available_fields_returns_common_fields()
    {
        $fields = $this->integration->getAvailableFields();

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        $fieldNames = array_column($fields, 'name');
        $this->assertContains('email', $fieldNames);
        $this->assertContains('name', $fieldNames);
    }

    public function test_payload_detects_email_field_type()
    {
        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'default_webhook_url' => 'https://hooks.zapier.com/test',
            'include_form_fields' => true,
        ]);

        $integration = new ZapierIntegration();
        $capturedPayload = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedPayload) {
            $capturedPayload = json_decode($args['body'], true);
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'zap_123']),
            ];
        });

        $integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            []
        );

        $this->assertArrayHasKey('_fields', $capturedPayload);
        $this->assertEquals('email', $capturedPayload['_fields']['email']['type']);
    }

    // ==========================================================================
    // Data Sanitization Tests
    // ==========================================================================

    public function test_send_submission_does_not_flatten_indexed_arrays()
    {
        set_option('formflow_integration_zapier', [
            'enabled' => true,
            'default_webhook_url' => 'https://hooks.zapier.com/test',
            'flatten_data' => true,
        ]);

        $integration = new ZapierIntegration();
        $capturedPayload = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedPayload) {
            $capturedPayload = json_decode($args['body'], true);
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'zap_123']),
            ];
        });

        $integration->sendSubmission(
            [
                'id' => 1,
                'form_data' => [
                    'tags' => ['tag1', 'tag2', 'tag3'],
                ],
            ],
            []
        );

        // Indexed arrays should remain as arrays
        $this->assertIsArray($capturedPayload['data']['tags']);
        $this->assertEquals(['tag1', 'tag2', 'tag3'], $capturedPayload['data']['tags']);
    }

    public function test_webhook_url_is_masked_in_logs()
    {
        global $wpdb;

        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'zap_123']),
            ];
        });

        $this->integration->testConnection();

        // Check that the logged URL doesn't contain the full path
        $inserts = $wpdb->get_mock_inserts();
        $logData = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_logs') !== false) {
                if ($insert['data']['context'] === 'integration_zapier') {
                    $logData = json_decode($insert['data']['data'], true);
                    break;
                }
            }
        }

        if ($logData && isset($logData['webhook_url'])) {
            $this->assertStringContainsString('****', $logData['webhook_url']);
        }
    }

    public function test_send_submission_handles_network_error()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return new \WP_Error('http_request_failed', 'Network timeout');
        });

        $result = $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            []
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Network timeout', $result['message']);
    }
}
