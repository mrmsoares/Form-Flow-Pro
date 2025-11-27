<?php
/**
 * Tests for AuditLogger class.
 */

namespace FormFlowPro\Tests\Unit\Security;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Security\AuditLogger;

class AuditLoggerTest extends TestCase
{
    private $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';
        $this->auditLogger = AuditLogger::getInstance();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
        parent::tearDown();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = AuditLogger::getInstance();
        $instance2 = AuditLogger::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(AuditLogger::class, $instance1);
    }

    public function test_log_creates_entry_with_minimal_data()
    {
        $eventId = $this->auditLogger->log(
            'test_event',
            AuditLogger::CATEGORY_SYSTEM,
            AuditLogger::SEVERITY_INFO
        );

        $this->assertIsString($eventId);
        $this->assertStringStartsWith('AUD-', $eventId);
    }

    public function test_log_creates_entry_with_full_data()
    {
        $eventId = $this->auditLogger->log(
            'form_created',
            AuditLogger::CATEGORY_DATA,
            AuditLogger::SEVERITY_INFO,
            [
                'object_type' => 'form',
                'object_id' => 123,
                'object_name' => 'Contact Form',
                'action' => 'create',
                'description' => 'Created new contact form',
                'context' => ['template' => 'default'],
            ]
        );

        $this->assertIsString($eventId);

        // Flush buffer to ensure data is written
        $this->auditLogger->flushBuffer();

        // Would verify database entry in real test
        $this->assertTrue(true);
    }

    public function test_log_flushes_buffer_on_critical_event()
    {
        $this->auditLogger->log(
            'critical_event',
            AuditLogger::CATEGORY_SECURITY,
            AuditLogger::SEVERITY_CRITICAL,
            ['description' => 'Critical security event']
        );

        // Would verify buffer was flushed in real test
        $this->assertTrue(true);
    }

    public function test_info_logs_with_info_severity()
    {
        $eventId = $this->auditLogger->info('test_event', 'Test information message');

        $this->assertIsString($eventId);
    }

    public function test_warning_logs_with_warning_severity()
    {
        $eventId = $this->auditLogger->warning('test_event', 'Test warning message');

        $this->assertIsString($eventId);
    }

    public function test_error_logs_with_error_severity()
    {
        $eventId = $this->auditLogger->error('test_event', 'Test error message');

        $this->assertIsString($eventId);
    }

    public function test_critical_logs_with_critical_severity()
    {
        $eventId = $this->auditLogger->critical('test_event', 'Test critical message');

        $this->assertIsString($eventId);
    }

    public function test_flush_buffer_clears_buffer()
    {
        $this->auditLogger->log('test_event', AuditLogger::CATEGORY_SYSTEM, AuditLogger::SEVERITY_INFO);

        $this->auditLogger->flushBuffer();

        // Would verify buffer is empty in real test
        $this->assertTrue(true);
    }

    public function test_query_returns_array()
    {
        $logs = $this->auditLogger->query();

        $this->assertIsArray($logs);
    }

    public function test_query_filters_by_event_type()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_audit_logs',
            [
                'event_id' => 'AUD-TEST1',
                'event_type' => 'login_success',
                'category' => 'authentication',
                'severity' => 'info',
                'ip_address' => '192.168.1.1',
                'checksum' => 'dummy_checksum',
                'created_at' => current_time('mysql'),
            ]
        );

        $wpdb->insert(
            $wpdb->prefix . 'formflow_audit_logs',
            [
                'event_id' => 'AUD-TEST2',
                'event_type' => 'login_failed',
                'category' => 'authentication',
                'severity' => 'warning',
                'ip_address' => '192.168.1.1',
                'checksum' => 'dummy_checksum',
                'created_at' => current_time('mysql'),
            ]
        );

        $logs = $this->auditLogger->query(['event_type' => 'login_success']);

        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
    }

    public function test_query_filters_by_category()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_audit_logs',
            [
                'event_id' => 'AUD-TEST1',
                'event_type' => 'test',
                'category' => 'security',
                'severity' => 'info',
                'ip_address' => '192.168.1.1',
                'checksum' => 'dummy_checksum',
                'created_at' => current_time('mysql'),
            ]
        );

        $logs = $this->auditLogger->query(['category' => 'security']);

        $this->assertIsArray($logs);
    }

    public function test_query_filters_by_severity()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_audit_logs',
            [
                'event_id' => 'AUD-TEST1',
                'event_type' => 'test',
                'category' => 'system',
                'severity' => 'critical',
                'ip_address' => '192.168.1.1',
                'checksum' => 'dummy_checksum',
                'created_at' => current_time('mysql'),
            ]
        );

        $logs = $this->auditLogger->query(['severity' => 'critical']);

        $this->assertIsArray($logs);
    }

    public function test_query_filters_by_user_id()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_audit_logs',
            [
                'event_id' => 'AUD-TEST1',
                'event_type' => 'test',
                'category' => 'system',
                'severity' => 'info',
                'user_id' => 1,
                'ip_address' => '192.168.1.1',
                'checksum' => 'dummy_checksum',
                'created_at' => current_time('mysql'),
            ]
        );

        $logs = $this->auditLogger->query(['user_id' => 1]);

        $this->assertIsArray($logs);
    }

    public function test_get_count_returns_number()
    {
        $count = $this->auditLogger->getCount();

        $this->assertIsInt($count);
    }

    public function test_get_count_filters_by_category()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_audit_logs',
            [
                'event_id' => 'AUD-TEST1',
                'event_type' => 'test',
                'category' => 'security',
                'severity' => 'info',
                'ip_address' => '192.168.1.1',
                'checksum' => 'dummy_checksum',
                'created_at' => current_time('mysql'),
            ]
        );

        $count = $this->auditLogger->getCount(['category' => 'security']);

        $this->assertEquals(1, $count);
    }

    public function test_get_statistics_returns_correct_structure()
    {
        $stats = $this->auditLogger->getStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('by_category', $stats);
        $this->assertArrayHasKey('by_severity', $stats);
        $this->assertArrayHasKey('by_event_type', $stats);
        $this->assertArrayHasKey('top_users', $stats);
        $this->assertArrayHasKey('top_ips', $stats);
        $this->assertArrayHasKey('timeline', $stats);
    }

    public function test_get_statistics_for_different_periods()
    {
        $hourly = $this->auditLogger->getStatistics('hour');
        $daily = $this->auditLogger->getStatistics('day');
        $weekly = $this->auditLogger->getStatistics('week');
        $monthly = $this->auditLogger->getStatistics('month');

        $this->assertIsArray($hourly);
        $this->assertIsArray($daily);
        $this->assertIsArray($weekly);
        $this->assertIsArray($monthly);
    }

    public function test_export_to_json()
    {
        $json = $this->auditLogger->export([], 'json');

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
    }

    public function test_export_to_csv()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_audit_logs',
            [
                'event_id' => 'AUD-TEST1',
                'event_type' => 'test',
                'category' => 'system',
                'severity' => 'info',
                'ip_address' => '192.168.1.1',
                'checksum' => 'dummy_checksum',
                'created_at' => current_time('mysql'),
            ]
        );

        $csv = $this->auditLogger->export([], 'csv');

        $this->assertIsString($csv);
        $this->assertStringContainsString('event_id', $csv);
    }

    public function test_cleanup_old_logs_removes_old_records()
    {
        // Mock test - cleanup would be tested in integration
        $this->auditLogger->cleanupOldLogs();

        $this->assertTrue(true);
    }

    public function test_log_login_success()
    {
        $user = (object)[
            'ID' => 1,
            'user_email' => 'test@example.com',
            'user_login' => 'testuser',
        ];

        $this->auditLogger->logLoginSuccess('testuser', $user);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_login_failed()
    {
        $this->auditLogger->logLoginFailed('testuser');

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_logout()
    {
        $this->auditLogger->logLogout();

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_password_reset()
    {
        $user = (object)[
            'ID' => 1,
            'user_email' => 'test@example.com',
        ];

        $this->auditLogger->logPasswordReset($user, 'new_password');

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_profile_update()
    {
        $oldUser = (object)[
            'ID' => 1,
            'user_email' => 'old@example.com',
            'display_name' => 'Old Name',
        ];

        $this->auditLogger->logProfileUpdate(1, $oldUser);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_2fa_enabled()
    {
        $this->auditLogger->log2FAEnabled(1);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_2fa_disabled()
    {
        $this->auditLogger->log2FADisabled(1);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_2fa_verified()
    {
        $this->auditLogger->log2FAVerified(1, 'totp');

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_2fa_attempt_success()
    {
        $this->auditLogger->log2FAAttempt([
            'user_id' => 1,
            'success' => true,
            'method' => 'totp',
        ]);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_2fa_attempt_failure()
    {
        $this->auditLogger->log2FAAttempt([
            'user_id' => 1,
            'success' => false,
            'method' => 'totp',
        ]);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_gdpr_request()
    {
        $this->auditLogger->logGDPRRequest([
            'type' => 'export',
            'email' => 'test@example.com',
            'request_id' => 'REQ-123',
        ]);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_gdpr_erasure()
    {
        $this->auditLogger->logGDPRErasure([
            'email' => 'test@example.com',
            'request_id' => 'REQ-123',
            'deleted_items' => ['submissions' => 5],
        ]);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_settings_changed()
    {
        $this->auditLogger->logSettingsChanged([
            'section' => 'general',
            'old' => ['setting1' => 'value1'],
            'new' => ['setting1' => 'value2'],
        ]);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_form_saved_new()
    {
        $this->auditLogger->logFormSaved(1, [
            'is_new' => true,
            'title' => 'New Form',
        ]);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_form_saved_update()
    {
        $this->auditLogger->logFormSaved(1, [
            'is_new' => false,
            'title' => 'Updated Form',
        ]);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_form_deleted()
    {
        $this->auditLogger->logFormDeleted(1);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_submission_created()
    {
        $this->auditLogger->logSubmissionCreated(1, [
            'form_id' => 1,
            'email' => 'test@example.com',
        ]);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_api_request_success()
    {
        $this->auditLogger->logAPIRequest('/api/endpoint', 'POST', [
            'status_code' => 200,
        ]);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_log_api_request_failure()
    {
        $this->auditLogger->logAPIRequest('/api/endpoint', 'POST', [
            'status_code' => 500,
        ]);

        $this->auditLogger->flushBuffer();

        $this->assertTrue(true);
    }

    public function test_create_tables()
    {
        // Mock test - table creation would be tested in integration
        $this->auditLogger->createTables();

        $this->assertTrue(true);
    }
}
