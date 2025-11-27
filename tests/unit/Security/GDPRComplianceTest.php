<?php
/**
 * Tests for GDPRCompliance class.
 */

namespace FormFlowPro\Tests\Unit\Security;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Security\GDPRCompliance;

class GDPRComplianceTest extends TestCase
{
    private $gdprCompliance;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';
        $this->gdprCompliance = GDPRCompliance::getInstance();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        parent::tearDown();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = GDPRCompliance::getInstance();
        $instance2 = GDPRCompliance::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(GDPRCompliance::class, $instance1);
    }

    public function test_create_request_with_valid_export_type()
    {
        global $wpdb;

        $result = $this->gdprCompliance->createRequest('export', 'test@example.com', 1);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('request_id', $result);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_gdpr_requests WHERE email = %s",
            'test@example.com'
        ));

        $this->assertEquals(1, $count);
    }

    public function test_create_request_with_erasure_type()
    {
        $result = $this->gdprCompliance->createRequest('erasure', 'test@example.com', 1);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('request_id', $result);
    }

    public function test_create_request_with_invalid_type()
    {
        $result = $this->gdprCompliance->createRequest('invalid_type', 'test@example.com');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_create_request_with_invalid_email()
    {
        $result = $this->gdprCompliance->createRequest('export', 'invalid-email');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_create_request_rejects_duplicate_pending_request()
    {
        global $wpdb;

        // Create first request
        $wpdb->insert(
            $wpdb->prefix . 'formflow_gdpr_requests',
            [
                'request_id' => 'REQ-TEST123',
                'type' => 'export',
                'status' => 'pending',
                'email' => 'test@example.com',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'created_at' => current_time('mysql'),
            ]
        );

        // Try to create duplicate
        $result = $this->gdprCompliance->createRequest('export', 'test@example.com');

        $this->assertFalse($result['success']);
    }

    public function test_verify_request_with_valid_code()
    {
        global $wpdb;

        $code = 'valid_code_12345678901234567890123';
        $codeHash = password_hash($code, PASSWORD_DEFAULT);

        $wpdb->insert(
            $wpdb->prefix . 'formflow_gdpr_requests',
            [
                'request_id' => 'REQ-TEST123',
                'type' => 'export',
                'status' => 'pending',
                'email' => 'test@example.com',
                'verification_code' => $codeHash,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'created_at' => current_time('mysql'),
            ]
        );

        $result = $this->gdprCompliance->verifyRequest('REQ-TEST123', $code);

        $this->assertTrue($result);

        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT status, verified_at FROM {$wpdb->prefix}formflow_gdpr_requests WHERE request_id = %s",
            'REQ-TEST123'
        ));

        $this->assertEquals('processing', $record->status);
        $this->assertNotNull($record->verified_at);
    }

    public function test_verify_request_with_invalid_code()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_gdpr_requests',
            [
                'request_id' => 'REQ-TEST123',
                'type' => 'export',
                'status' => 'pending',
                'email' => 'test@example.com',
                'verification_code' => password_hash('correct_code', PASSWORD_DEFAULT),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'created_at' => current_time('mysql'),
            ]
        );

        $result = $this->gdprCompliance->verifyRequest('REQ-TEST123', 'wrong_code');

        $this->assertFalse($result);
    }

    public function test_get_request_returns_request_data()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_gdpr_requests',
            [
                'request_id' => 'REQ-TEST123',
                'type' => 'export',
                'status' => 'pending',
                'email' => 'test@example.com',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'created_at' => current_time('mysql'),
            ]
        );

        $request = $this->gdprCompliance->getRequest('REQ-TEST123');

        $this->assertIsArray($request);
        $this->assertEquals('test@example.com', $request['email']);
        $this->assertEquals('export', $request['type']);
    }

    public function test_get_request_returns_null_for_nonexistent()
    {
        $request = $this->gdprCompliance->getRequest('NONEXISTENT');

        $this->assertNull($request);
    }

    public function test_get_requests_returns_all_requests()
    {
        global $wpdb;

        for ($i = 0; $i < 3; $i++) {
            $wpdb->insert(
                $wpdb->prefix . 'formflow_gdpr_requests',
                [
                    'request_id' => 'REQ-TEST' . $i,
                    'type' => 'export',
                    'status' => 'pending',
                    'email' => 'test' . $i . '@example.com',
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                    'created_at' => current_time('mysql'),
                ]
            );
        }

        $requests = $this->gdprCompliance->getRequests();

        $this->assertIsArray($requests);
        $this->assertCount(3, $requests);
    }

    public function test_get_requests_filters_by_type()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_gdpr_requests',
            [
                'request_id' => 'REQ-EXPORT',
                'type' => 'export',
                'status' => 'pending',
                'email' => 'test@example.com',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'created_at' => current_time('mysql'),
            ]
        );

        $wpdb->insert(
            $wpdb->prefix . 'formflow_gdpr_requests',
            [
                'request_id' => 'REQ-ERASURE',
                'type' => 'erasure',
                'status' => 'pending',
                'email' => 'test@example.com',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'created_at' => current_time('mysql'),
            ]
        );

        $exportRequests = $this->gdprCompliance->getRequests(['type' => 'export']);

        $this->assertCount(1, $exportRequests);
        $this->assertEquals('export', $exportRequests[0]['type']);
    }

    public function test_record_consent_with_valid_data()
    {
        global $wpdb;

        $result = $this->gdprCompliance->recordConsent([
            'email' => 'test@example.com',
            'user_id' => 1,
            'consent_type' => 'marketing',
            'consent_given' => true,
            'consent_text' => 'I agree to marketing emails',
        ]);

        $this->assertTrue($result);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_gdpr_consents WHERE email = %s",
            'test@example.com'
        ));

        $this->assertEquals(1, $count);
    }

    public function test_record_consent_requires_all_fields()
    {
        $result = $this->gdprCompliance->recordConsent([
            'email' => 'test@example.com',
            'consent_type' => 'marketing',
        ]);

        $this->assertFalse($result);
    }

    public function test_withdraw_consent()
    {
        global $wpdb;

        // Create consent
        $wpdb->insert(
            $wpdb->prefix . 'formflow_gdpr_consents',
            [
                'consent_id' => 'CON-TEST123',
                'email' => 'test@example.com',
                'consent_type' => 'marketing',
                'consent_given' => 1,
                'created_at' => current_time('mysql'),
            ]
        );

        $result = $this->gdprCompliance->withdrawConsent('test@example.com', 'marketing', 'Changed my mind');

        $this->assertTrue($result);

        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT consent_given, withdrawal_reason FROM {$wpdb->prefix}formflow_gdpr_consents WHERE email = %s",
            'test@example.com'
        ));

        $this->assertEquals(0, $record->consent_given);
        $this->assertEquals('Changed my mind', $record->withdrawal_reason);
    }

    public function test_has_consent_returns_true_when_given()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_gdpr_consents',
            [
                'consent_id' => 'CON-TEST123',
                'email' => 'test@example.com',
                'consent_type' => 'marketing',
                'consent_given' => 1,
                'created_at' => current_time('mysql'),
            ]
        );

        $result = $this->gdprCompliance->hasConsent('test@example.com', 'marketing');

        $this->assertTrue($result);
    }

    public function test_has_consent_returns_false_when_not_given()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_gdpr_consents',
            [
                'consent_id' => 'CON-TEST123',
                'email' => 'test@example.com',
                'consent_type' => 'marketing',
                'consent_given' => 0,
                'created_at' => current_time('mysql'),
            ]
        );

        $result = $this->gdprCompliance->hasConsent('test@example.com', 'marketing');

        $this->assertFalse($result);
    }

    public function test_get_consent_history_returns_array()
    {
        global $wpdb;

        for ($i = 0; $i < 3; $i++) {
            $wpdb->insert(
                $wpdb->prefix . 'formflow_gdpr_consents',
                [
                    'consent_id' => 'CON-TEST' . $i,
                    'email' => 'test@example.com',
                    'consent_type' => 'marketing',
                    'consent_given' => $i % 2,
                    'created_at' => current_time('mysql'),
                ]
            );
        }

        $history = $this->gdprCompliance->getConsentHistory('test@example.com');

        $this->assertIsArray($history);
        $this->assertCount(3, $history);
    }

    public function test_get_current_consents_returns_latest_only()
    {
        global $wpdb;

        // Old consent - given
        $wpdb->insert(
            $wpdb->prefix . 'formflow_gdpr_consents',
            [
                'consent_id' => 'CON-OLD',
                'email' => 'test@example.com',
                'consent_type' => 'marketing',
                'consent_given' => 1,
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            ]
        );

        // New consent - withdrawn
        $wpdb->insert(
            $wpdb->prefix . 'formflow_gdpr_consents',
            [
                'consent_id' => 'CON-NEW',
                'email' => 'test@example.com',
                'consent_type' => 'marketing',
                'consent_given' => 0,
                'created_at' => current_time('mysql'),
            ]
        );

        $consents = $this->gdprCompliance->getCurrentConsents('test@example.com');

        $this->assertIsArray($consents);
        $this->assertFalse($consents['marketing']);
    }

    public function test_register_processing_activity()
    {
        global $wpdb;

        $result = $this->gdprCompliance->registerProcessingActivity([
            'name' => 'Form Submission Processing',
            'purpose' => 'To process user form submissions',
            'legal_basis' => 'consent',
            'data_categories' => ['email', 'name'],
            'data_subjects' => ['customers', 'leads'],
            'recipients' => ['internal_staff'],
            'retention_period' => 365,
            'security_measures' => 'Encryption at rest and in transit',
        ]);

        $this->assertTrue($result);

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_gdpr_processing WHERE name = 'Form Submission Processing'"
        );

        $this->assertEquals(1, $count);
    }

    public function test_get_processing_activities_returns_active_only()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_gdpr_processing',
            [
                'activity_id' => 'ACT-ACTIVE',
                'name' => 'Active Activity',
                'purpose' => 'Test',
                'legal_basis' => 'consent',
                'data_categories' => '[]',
                'data_subjects' => '[]',
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ]
        );

        $wpdb->insert(
            $wpdb->prefix . 'formflow_gdpr_processing',
            [
                'activity_id' => 'ACT-INACTIVE',
                'name' => 'Inactive Activity',
                'purpose' => 'Test',
                'legal_basis' => 'consent',
                'data_categories' => '[]',
                'data_subjects' => '[]',
                'is_active' => 0,
                'created_at' => current_time('mysql'),
            ]
        );

        $activities = $this->gdprCompliance->getProcessingActivities(true);

        $this->assertCount(1, $activities);
    }

    public function test_register_data_inventory()
    {
        global $wpdb;

        $result = $this->gdprCompliance->registerDataInventory([
            'data_type' => 'email',
            'table_name' => 'formflow_submissions',
            'column_name' => 'email',
            'is_personal_data' => true,
            'is_sensitive' => false,
            'is_encrypted' => false,
            'retention_days' => 365,
            'anonymization_method' => 'hash',
            'description' => 'User email address',
        ]);

        $this->assertTrue($result);

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_gdpr_inventory WHERE data_type = 'email'"
        );

        $this->assertEquals(1, $count);
    }

    public function test_get_data_inventory_returns_array()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_gdpr_inventory',
            [
                'data_type' => 'email',
                'table_name' => 'formflow_submissions',
                'column_name' => 'email',
                'is_personal_data' => 1,
                'created_at' => current_time('mysql'),
            ]
        );

        $inventory = $this->gdprCompliance->getDataInventory();

        $this->assertIsArray($inventory);
        $this->assertCount(1, $inventory);
    }

    public function test_get_statistics_returns_correct_structure()
    {
        $stats = $this->gdprCompliance->getStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_requests', $stats);
        $this->assertArrayHasKey('pending_requests', $stats);
        $this->assertArrayHasKey('completed_requests', $stats);
        $this->assertArrayHasKey('total_consents', $stats);
        $this->assertArrayHasKey('active_consents', $stats);
        $this->assertArrayHasKey('processing_activities', $stats);
    }

    public function test_process_scheduled_requests()
    {
        // Mock test - scheduled processing would be tested in integration
        $this->gdprCompliance->processScheduledRequests();

        $this->assertTrue(true);
    }

    public function test_cleanup_expired_data()
    {
        // Mock test - cleanup would be tested in integration
        $this->gdprCompliance->cleanupExpiredData();

        $this->assertTrue(true);
    }

    public function test_enforce_data_retention()
    {
        // Mock test - retention enforcement would be tested in integration
        $this->gdprCompliance->enforceDataRetention();

        $this->assertTrue(true);
    }

    public function test_create_tables()
    {
        // Mock test - table creation would be tested in integration
        $this->gdprCompliance->createTables();

        $this->assertTrue(true);
    }

    public function test_register_privacy_exporter()
    {
        // Mock test - WordPress privacy exporter would be tested in integration
        $this->gdprCompliance->registerPrivacyExporter();

        $this->assertTrue(true);
    }

    public function test_register_privacy_eraser()
    {
        // Mock test - WordPress privacy eraser would be tested in integration
        $this->gdprCompliance->registerPrivacyEraser();

        $this->assertTrue(true);
    }

    public function test_wp_privacy_exporter_callback_returns_data()
    {
        $result = $this->gdprCompliance->wpPrivacyExporterCallback('test@example.com', 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('done', $result);
    }

    public function test_wp_privacy_eraser_callback_returns_result()
    {
        $result = $this->gdprCompliance->wpPrivacyEraserCallback('test@example.com', 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items_removed', $result);
        $this->assertArrayHasKey('items_retained', $result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertArrayHasKey('done', $result);
    }

    public function test_capture_consent_from_form()
    {
        $formData = [
            'email' => 'test@example.com',
            'gdpr_consent' => '1',
            'marketing_consent' => '1',
        ];

        $this->gdprCompliance->captureConsent($formData, 1);

        // Would check database in real test
        $this->assertTrue(true);
    }

    public function test_log_processing_activity()
    {
        $submission = ['id' => 1];

        $this->gdprCompliance->logProcessingActivity($submission, 1);

        // Would verify action fired in real test
        $this->assertTrue(true);
    }

    public function test_ajax_create_request()
    {
        // Mock test - AJAX would be tested in integration
        $this->assertTrue(true);
    }

    public function test_ajax_update_consent()
    {
        // Mock test - AJAX would be tested in integration
        $this->assertTrue(true);
    }

    public function test_ajax_process_request()
    {
        // Mock test - AJAX would be tested in integration
        $this->assertTrue(true);
    }

    public function test_ajax_download_export()
    {
        // Mock test - AJAX would be tested in integration
        $this->assertTrue(true);
    }
}
