<?php
/**
 * Tests for AutentiqueService class.
 *
 * @package FormFlowPro
 * @subpackage Tests\Integrations\Autentique
 */

namespace FormFlowPro\Tests\Unit\Integrations\Autentique;

use FormFlowPro\Integrations\Autentique\AutentiqueService;
use FormFlowPro\Integrations\Autentique\AutentiqueClient;
use FormFlowPro\Core\CacheManager;
use FormFlowPro\Tests\TestCase;

/**
 * AutentiqueService test case.
 */
class AutentiqueServiceTest extends TestCase
{
    /**
     * Service instance.
     *
     * @var AutentiqueService
     */
    private $service;

    /**
     * Mock client.
     *
     * @var AutentiqueClient
     */
    private $mockClient;

    /**
     * Mock cache.
     *
     * @var CacheManager
     */
    private $mockCache;

    /**
     * Setup before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock client
        $this->mockClient = $this->createMock(AutentiqueClient::class);

        // Create cache instance
        $this->mockCache = new CacheManager();

        // Create service with mocks
        $this->service = new AutentiqueService($this->mockClient, $this->mockCache);

        // Setup test data
        $this->setupTestData();
    }

    /**
     * Setup test database data.
     *
     * @return void
     */
    private function setupTestData()
    {
        global $wpdb;

        // Create temp directory for PDF generation
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/formflow-temp/';
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        // Create test form
        $wpdb->insert(
            $wpdb->prefix . 'formflow_forms',
            [
                'id' => 'test-form-id',
                'name' => 'Test Form',
                'status' => 'active',
                'settings' => json_encode([
                    'autentique_enabled' => true,
                    'autentique_sandbox' => true,
                    'autentique_signers' => [
                        [
                            'email_field' => 'email',
                            'name_field' => 'name',
                            'action' => 'sign',
                        ],
                    ],
                ]),
            ]
        );

        // Create test submission
        $wpdb->insert(
            $wpdb->prefix . 'formflow_submissions',
            [
                'id' => 'test-submission-id',
                'form_id' => 'test-form-id',
                'form_data' => json_encode([
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ]),
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ]
        );
    }

    /**
     * Test createDocumentFromSubmission with valid data.
     *
     * @return void
     */
    public function test_create_document_from_submission_success()
    {
        // Mock client response
        $this->mockClient
            ->expects($this->once())
            ->method('createDocument')
            ->willReturn([
                'id' => 'autentique_doc_123',
                'status' => 'pending',
            ]);

        $result = $this->service->createDocumentFromSubmission('test-submission-id');

        $this->assertTrue($result['success'], 'Error: ' . ($result['error'] ?? 'Unknown error'));
        $this->assertEquals('autentique_doc_123', $result['document_id']);

        // Verify document reference was stored
        global $wpdb;
        $meta = $wpdb->get_var(
            "SELECT meta_value FROM {$wpdb->prefix}formflow_submission_meta
            WHERE submission_id = 'test-submission-id'
            AND meta_key = 'autentique_document_id'"
        );

        $this->assertEquals('autentique_doc_123', $meta);
    }

    /**
     * Test createDocumentFromSubmission with nonexistent submission.
     *
     * @return void
     */
    public function test_create_document_fails_with_invalid_submission()
    {
        $result = $this->service->createDocumentFromSubmission('nonexistent-id');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Submission not found', $result['error']);
    }

    /**
     * Test createDocumentFromSubmission when Autentique is disabled.
     *
     * @return void
     */
    public function test_create_document_fails_when_autentique_disabled()
    {
        global $wpdb;

        // Update form to disable Autentique
        $wpdb->update(
            $wpdb->prefix . 'formflow_forms',
            [
                'settings' => json_encode([
                    'autentique_enabled' => false,
                ]),
            ],
            ['id' => 'test-form-id']
        );

        $result = $this->service->createDocumentFromSubmission('test-submission-id');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not enabled', $result['error']);
    }

    /**
     * Test processSignatureWebhook with document.signed event.
     *
     * @return void
     */
    public function test_process_signature_webhook_document_signed()
    {
        global $wpdb;

        // Store document reference
        $wpdb->insert(
            $wpdb->prefix . 'formflow_submission_meta',
            [
                'submission_id' => 'test-submission-id',
                'meta_key' => 'autentique_document_id',
                'meta_value' => 'doc_123',
                'created_at' => current_time('mysql'),
            ]
        );

        $webhook_data = [
            'document_id' => 'doc_123',
            'event' => 'document.signed',
            'signer' => [
                'email' => 'john@example.com',
            ],
            'signed_at' => current_time('mysql'),
            'all_signed' => false,
        ];

        $result = $this->service->processSignatureWebhook($webhook_data);

        $this->assertTrue($result['success']);

        // Verify log was created
        $log = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}formflow_logs
            WHERE submission_id = 'test-submission-id'
            ORDER BY created_at DESC LIMIT 1"
        );

        $this->assertNotNull($log);
        $this->assertStringContainsString('signed by user', $log->message);
    }

    /**
     * Test processSignatureWebhook with document.completed event.
     *
     * @return void
     */
    public function test_process_signature_webhook_document_completed()
    {
        global $wpdb;

        // Store document reference
        $wpdb->insert(
            $wpdb->prefix . 'formflow_submission_meta',
            [
                'submission_id' => 'test-submission-id',
                'meta_key' => 'autentique_document_id',
                'meta_value' => 'doc_123',
                'created_at' => current_time('mysql'),
            ]
        );

        // Mock downloadDocument method
        $this->mockClient
            ->expects($this->once())
            ->method('downloadDocument')
            ->willReturn('PDF content');

        $webhook_data = [
            'document_id' => 'doc_123',
            'event' => 'document.completed',
        ];

        $result = $this->service->processSignatureWebhook($webhook_data);

        $this->assertTrue($result['success']);
    }

    /**
     * Test processSignatureWebhook with invalid signature.
     *
     * @return void
     */
    public function test_process_signature_webhook_with_missing_data()
    {
        $webhook_data = [
            'event' => 'document.signed',
            // Missing document_id
        ];

        $result = $this->service->processSignatureWebhook($webhook_data);

        // Should return success to prevent webhook retry
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test checkDocumentStatus uses cache.
     *
     * @return void
     */
    public function test_check_document_status_uses_cache()
    {
        // Set cache
        $cached_status = ['id' => 'doc_123', 'status' => 'signed'];
        $this->mockCache->set('autentique_status_doc_123', $cached_status);

        // Client should not be called
        $this->mockClient
            ->expects($this->never())
            ->method('getDocumentStatus');

        $result = $this->service->checkDocumentStatus('doc_123');

        $this->assertEquals('doc_123', $result['id']);
        $this->assertEquals('signed', $result['status']);
    }

    /**
     * Test checkDocumentStatus fetches from API when not cached.
     *
     * @return void
     */
    public function test_check_document_status_fetches_from_api()
    {
        $api_status = ['id' => 'doc_123', 'status' => 'pending'];

        $this->mockClient
            ->expects($this->once())
            ->method('getDocumentStatus')
            ->with('doc_123')
            ->willReturn($api_status);

        $result = $this->service->checkDocumentStatus('doc_123');

        $this->assertEquals('doc_123', $result['id']);

        // Verify it was cached
        $cached = $this->mockCache->get('autentique_status_doc_123');
        $this->assertEquals($api_status, $cached);
    }

    /**
     * Test downloadSignedDocument stores file correctly.
     *
     * @return void
     */
    public function test_download_signed_document_stores_file()
    {
        global $wpdb;

        // Store document reference
        $wpdb->insert(
            $wpdb->prefix . 'formflow_submission_meta',
            [
                'submission_id' => 'test-submission-id',
                'meta_key' => 'autentique_document_id',
                'meta_value' => 'doc_123',
                'created_at' => current_time('mysql'),
            ]
        );

        // Mock download
        $this->mockClient
            ->expects($this->once())
            ->method('downloadDocument')
            ->willReturn('PDF binary content');

        $result = $this->service->downloadSignedDocument('doc_123');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('file_url', $result);
        $this->assertArrayHasKey('file_path', $result);

        // Verify meta was stored
        $meta = $wpdb->get_var(
            "SELECT meta_value FROM {$wpdb->prefix}formflow_submission_meta
            WHERE submission_id = 'test-submission-id'
            AND meta_key = 'signed_document_url'"
        );

        $this->assertNotEmpty($meta);
    }

    /**
     * Test downloadSignedDocument handles errors.
     *
     * @return void
     */
    public function test_download_signed_document_handles_errors()
    {
        $this->mockClient
            ->expects($this->once())
            ->method('downloadDocument')
            ->willThrowException(new \Exception('Download failed'));

        $result = $this->service->downloadSignedDocument('doc_123');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Download failed', $result['error']);
    }

    /**
     * Test submission status updates correctly.
     *
     * @return void
     */
    public function test_submission_status_updates()
    {
        global $wpdb;

        // Store document reference
        $wpdb->insert(
            $wpdb->prefix . 'formflow_submission_meta',
            [
                'submission_id' => 'test-submission-id',
                'meta_key' => 'autentique_document_id',
                'meta_value' => 'doc_123',
                'created_at' => current_time('mysql'),
            ]
        );

        // Mock successful document creation
        $this->mockClient
            ->expects($this->once())
            ->method('createDocument')
            ->willReturn(['id' => 'doc_456']);

        $this->service->createDocumentFromSubmission('test-submission-id');

        // Check status was updated
        $status = $wpdb->get_var(
            "SELECT status FROM {$wpdb->prefix}formflow_submissions
            WHERE id = 'test-submission-id'"
        );

        $this->assertEquals('pending_signature', $status);
    }

    /**
     * Test queue job is created for status check.
     *
     * @return void
     */
    public function test_queue_job_created_for_status_check()
    {
        global $wpdb;

        $this->mockClient
            ->expects($this->once())
            ->method('createDocument')
            ->willReturn(['id' => 'doc_789']);

        $this->service->createDocumentFromSubmission('test-submission-id');

        // Verify queue job exists
        $job = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}formflow_queue
            WHERE submission_id = 'test-submission-id'
            AND job_type = 'autentique_status_check'"
        );

        $this->assertNotNull($job);
        $this->assertEquals('pending', $job->status);

        // Verify payload contains document_id
        $payload = json_decode($job->payload, true);
        $this->assertEquals('doc_789', $payload['document_id']);
    }
}
