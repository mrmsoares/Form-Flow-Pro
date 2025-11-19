<?php
/**
 * Tests for WebhookHandler class.
 *
 * @package FormFlowPro
 * @subpackage Tests\Integrations\Autentique
 */

namespace FormFlowPro\Tests\Unit\Integrations\Autentique;

use FormFlowPro\Integrations\Autentique\WebhookHandler;
use FormFlowPro\Integrations\Autentique\AutentiqueService;
use FormFlowPro\Tests\TestCase;

/**
 * WebhookHandler test case.
 */
class WebhookHandlerTest extends TestCase
{
    /**
     * Handler instance.
     *
     * @var WebhookHandler
     */
    private $handler;

    /**
     * Mock service.
     *
     * @var AutentiqueService
     */
    private $mockService;

    /**
     * Setup before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock service
        $this->mockService = $this->createMock(AutentiqueService::class);

        // Create handler with mock
        $this->handler = new WebhookHandler($this->mockService);
    }

    /**
     * Test get_webhook_url returns correct URL.
     *
     * @return void
     */
    public function test_get_webhook_url_returns_correct_url()
    {
        $url = WebhookHandler::get_webhook_url();

        $this->assertStringContainsString('formflow/v1/autentique/webhook', $url);
    }

    /**
     * Test handle_webhook processes valid request.
     *
     * @return void
     */
    public function test_handle_webhook_processes_valid_request()
    {
        $webhook_data = [
            'document_id' => 'doc_123',
            'event' => 'document.signed',
            'signer' => [
                'email' => 'test@example.com',
            ],
        ];

        // Mock service processing
        $this->mockService
            ->expects($this->once())
            ->method('processSignatureWebhook')
            ->with($webhook_data)
            ->willReturn(['success' => true]);

        // Disable signature validation for test
        update_option('formflow_autentique_require_signature', false);

        // Create mock request
        $request = $this->createMockRequest($webhook_data);

        $response = $this->handler->handle_webhook($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
    }

    /**
     * Test handle_webhook rejects invalid JSON.
     *
     * @return void
     */
    public function test_handle_webhook_rejects_invalid_json()
    {
        $request = $this->createMockRequest('invalid json', false);

        $response = $this->handler->handle_webhook($request);

        $this->assertEquals(500, $response->get_status());
        $this->assertFalse($response->get_data()['success']);
        $this->assertStringContainsString('Invalid JSON', $response->get_data()['error']);
    }

    /**
     * Test handle_webhook validates signature.
     *
     * @return void
     */
    public function test_handle_webhook_validates_signature()
    {
        // Enable signature validation
        update_option('formflow_autentique_require_signature', true);
        update_option('formflow_autentique_webhook_secret', 'test_secret');

        $webhook_data = [
            'document_id' => 'doc_123',
            'event' => 'document.signed',
        ];

        // Create request without signature
        $request = $this->createMockRequest($webhook_data);

        $response = $this->handler->handle_webhook($request);

        // Should be rejected
        $this->assertEquals(401, $response->get_status());
        $this->assertStringContainsString('Invalid signature', $response->get_data()['error']);
    }

    /**
     * Test handle_webhook accepts valid signature.
     *
     * @return void
     */
    public function test_handle_webhook_accepts_valid_signature()
    {
        // Enable signature validation
        update_option('formflow_autentique_require_signature', true);
        update_option('formflow_autentique_webhook_secret', 'test_secret');

        $webhook_data = [
            'document_id' => 'doc_123',
            'event' => 'document.signed',
        ];

        // Mock service
        $this->mockService
            ->expects($this->once())
            ->method('processSignatureWebhook')
            ->willReturn(['success' => true]);

        $body = json_encode($webhook_data);
        $signature = hash_hmac('sha256', $body, 'test_secret');

        $request = $this->createMockRequest($webhook_data);
        $request->set_header('X-Autentique-Signature', $signature);

        $response = $this->handler->handle_webhook($request);

        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test handle_webhook logs webhook activity.
     *
     * @return void
     */
    public function test_handle_webhook_logs_activity()
    {
        global $wpdb;

        // Disable signature validation
        update_option('formflow_autentique_require_signature', false);

        $webhook_data = [
            'document_id' => 'doc_123',
            'event' => 'document.signed',
        ];

        $this->mockService
            ->method('processSignatureWebhook')
            ->willReturn(['success' => true]);

        $request = $this->createMockRequest($webhook_data);
        $this->handler->handle_webhook($request);

        // Verify webhook was logged
        $log = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}formflow_webhooks
            WHERE integration = 'autentique'
            ORDER BY created_at DESC LIMIT 1"
        );

        $this->assertNotNull($log);
        $this->assertEquals('autentique', $log->integration);
        $this->assertEquals('document.signed', $log->webhook_type);
    }

    /**
     * Test test_webhook endpoint returns success.
     *
     * @return void
     */
    public function test_test_webhook_endpoint_returns_success()
    {
        $request = $this->createMockRequest([]);

        $response = $this->handler->test_webhook($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);
        $this->assertArrayHasKey('endpoint', $response->get_data());
    }

    /**
     * Test get_webhook_stats returns correct statistics.
     *
     * @return void
     */
    public function test_get_webhook_stats_returns_statistics()
    {
        global $wpdb;

        // Create test webhook logs
        for ($i = 0; $i < 5; $i++) {
            $wpdb->insert(
                $wpdb->prefix . 'formflow_webhooks',
                [
                    'id' => 'webhook_' . $i,
                    'integration' => 'autentique',
                    'webhook_type' => 'document.signed',
                    'status' => 'processed',
                    'payload' => json_encode(['test' => true]),
                    'created_at' => current_time('mysql'),
                ]
            );
        }

        // Create one error
        $wpdb->insert(
            $wpdb->prefix . 'formflow_webhooks',
            [
                'id' => 'webhook_error',
                'integration' => 'autentique',
                'webhook_type' => 'document.signed',
                'status' => 'error',
                'payload' => json_encode(['test' => true]),
                'created_at' => current_time('mysql'),
            ]
        );

        $stats = $this->handler->get_webhook_stats(7);

        $this->assertEquals(6, $stats['total_webhooks']);
        $this->assertEquals(1, $stats['errors']);
        $this->assertEquals(83.33, $stats['success_rate']); // 5/6 * 100
        $this->assertIsArray($stats['by_status']);
        $this->assertIsArray($stats['recent_webhooks']);
    }

    /**
     * Test retry_webhook retries failed webhook.
     *
     * @return void
     */
    public function test_retry_webhook_retries_failed_webhook()
    {
        global $wpdb;

        // Create failed webhook
        $wpdb->insert(
            $wpdb->prefix . 'formflow_webhooks',
            [
                'id' => 'failed_webhook',
                'integration' => 'autentique',
                'webhook_type' => 'document.signed',
                'status' => 'error',
                'payload' => json_encode([
                    'document_id' => 'doc_123',
                    'event' => 'document.signed',
                ]),
                'created_at' => current_time('mysql'),
            ]
        );

        // Mock service
        $this->mockService
            ->expects($this->once())
            ->method('processSignatureWebhook')
            ->willReturn(['success' => true]);

        $result = $this->handler->retry_webhook('failed_webhook');

        $this->assertTrue($result['success']);

        // Verify status was updated
        $webhook = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}formflow_webhooks WHERE id = 'failed_webhook'"
        );

        $this->assertEquals('processed', $webhook->status);
    }

    /**
     * Test retry_webhook rejects non-failed webhooks.
     *
     * @return void
     */
    public function test_retry_webhook_rejects_non_failed_webhooks()
    {
        global $wpdb;

        // Create successful webhook
        $wpdb->insert(
            $wpdb->prefix . 'formflow_webhooks',
            [
                'id' => 'success_webhook',
                'integration' => 'autentique',
                'webhook_type' => 'document.signed',
                'status' => 'processed',
                'payload' => json_encode(['test' => true]),
                'created_at' => current_time('mysql'),
            ]
        );

        $result = $this->handler->retry_webhook('success_webhook');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Can only retry failed', $result['error']);
    }

    /**
     * Test clean_old_logs removes old webhooks.
     *
     * @return void
     */
    public function test_clean_old_logs_removes_old_webhooks()
    {
        global $wpdb;

        // Create old webhook
        $wpdb->insert(
            $wpdb->prefix . 'formflow_webhooks',
            [
                'id' => 'old_webhook',
                'integration' => 'autentique',
                'webhook_type' => 'document.signed',
                'status' => 'processed',
                'payload' => json_encode(['test' => true]),
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            ]
        );

        // Create recent webhook
        $wpdb->insert(
            $wpdb->prefix . 'formflow_webhooks',
            [
                'id' => 'recent_webhook',
                'integration' => 'autentique',
                'webhook_type' => 'document.signed',
                'status' => 'processed',
                'payload' => json_encode(['test' => true]),
                'created_at' => current_time('mysql'),
            ]
        );

        $deleted = $this->handler->clean_old_logs(30);

        $this->assertEquals(1, $deleted);

        // Verify old was deleted
        $old = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_webhooks WHERE id = 'old_webhook'"
        );
        $this->assertEquals(0, $old);

        // Verify recent still exists
        $recent = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_webhooks WHERE id = 'recent_webhook'"
        );
        $this->assertEquals(1, $recent);
    }

    /**
     * Test verify_webhook_permission returns true.
     *
     * @return void
     */
    public function test_verify_webhook_permission_returns_true()
    {
        $request = $this->createMockRequest([]);

        $result = $this->handler->verify_webhook_permission($request);

        $this->assertTrue($result);
    }

    /**
     * Create mock REST request.
     *
     * @param mixed $data Request data.
     * @param bool $encode Whether to JSON encode data.
     * @return \WP_REST_Request Mock request.
     */
    private function createMockRequest($data, $encode = true)
    {
        $body = $encode ? json_encode($data) : $data;

        $request = $this->createMock(\WP_REST_Request::class);

        $request->method('get_body')
            ->willReturn($body);

        $request->method('get_header')
            ->willReturn(null);

        // Allow setting headers
        $headers = [];
        $request->method('set_header')
            ->willReturnCallback(function ($key, $value) use (&$headers, $request) {
                $headers[$key] = $value;
                $request->method('get_header')
                    ->willReturnCallback(function ($k) use (&$headers) {
                        return $headers[$k] ?? null;
                    });
            });

        return $request;
    }
}
