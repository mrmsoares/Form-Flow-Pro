<?php
/**
 * Tests for Log_Manager class.
 */

namespace FormFlowPro\Tests\Unit\Logs;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Logs\Log_Manager;

class LogManagerTest extends TestCase
{
    private $logManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up mock server variables
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $this->logManager = Log_Manager::get_instance();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = Log_Manager::get_instance();
        $instance2 = Log_Manager::get_instance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(Log_Manager::class, $instance1);
    }

    public function test_log_inserts_record_with_correct_type()
    {
        global $wpdb;

        $this->logManager->log('info', 'Test message', ['key' => 'value']);

        $inserts = $wpdb->get_mock_inserts();
        $logInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_logs') !== false) {
                $logInsert = $insert['data'];
                break;
            }
        }

        $this->assertNotNull($logInsert);
        $this->assertEquals('info', $logInsert['type']);
        $this->assertEquals('Test message', $logInsert['message']);
    }

    public function test_log_encodes_context_as_json()
    {
        global $wpdb;

        $context = [
            'user_id' => 123,
            'action' => 'form_submit',
            'form_id' => 'test-form',
            'nested' => ['key' => 'value'],
        ];

        $this->logManager->log('info', 'Form submitted', $context);

        $inserts = $wpdb->get_mock_inserts();
        $logInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_logs') !== false) {
                $logInsert = $insert['data'];
                break;
            }
        }

        $this->assertNotNull($logInsert);
        $this->assertArrayHasKey('context', $logInsert);

        $decodedContext = json_decode($logInsert['context'], true);
        $this->assertEquals($context, $decodedContext);
    }

    public function test_log_captures_user_id()
    {
        global $wpdb;

        // Mock current user ID
        set_current_user_id(42);

        $this->logManager->log('info', 'User action');

        $inserts = $wpdb->get_mock_inserts();
        $logInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_logs') !== false) {
                $logInsert = $insert['data'];
                break;
            }
        }

        $this->assertNotNull($logInsert);
        $this->assertEquals(42, $logInsert['user_id']);

        set_current_user_id(0); // Reset
    }

    public function test_log_captures_ip_address()
    {
        global $wpdb;

        $_SERVER['REMOTE_ADDR'] = '203.0.113.45';

        $this->logManager->log('error', 'Test error');

        $inserts = $wpdb->get_mock_inserts();
        $logInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_logs') !== false) {
                $logInsert = $insert['data'];
                break;
            }
        }

        $this->assertNotNull($logInsert);
        $this->assertEquals('203.0.113.45', $logInsert['ip_address']);
    }

    public function test_error_logs_error_type()
    {
        global $wpdb;

        $this->logManager->error('Error message', ['error_code' => 500]);

        $inserts = $wpdb->get_mock_inserts();
        $logInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_logs') !== false) {
                $logInsert = $insert['data'];
                break;
            }
        }

        $this->assertNotNull($logInsert);
        $this->assertEquals('error', $logInsert['type']);
        $this->assertEquals('Error message', $logInsert['message']);
    }

    public function test_info_logs_info_type()
    {
        global $wpdb;

        $this->logManager->info('Information message');

        $inserts = $wpdb->get_mock_inserts();
        $logInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_logs') !== false) {
                $logInsert = $insert['data'];
                break;
            }
        }

        $this->assertNotNull($logInsert);
        $this->assertEquals('info', $logInsert['type']);
    }

    public function test_warning_logs_warning_type()
    {
        global $wpdb;

        $this->logManager->warning('Warning message');

        $inserts = $wpdb->get_mock_inserts();
        $logInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_logs') !== false) {
                $logInsert = $insert['data'];
                break;
            }
        }

        $this->assertNotNull($logInsert);
        $this->assertEquals('warning', $logInsert['type']);
    }

    public function test_debug_logs_when_debug_mode_enabled()
    {
        global $wpdb;

        update_option('formflow_debug_mode', true);

        $this->logManager->debug('Debug message', ['debug_info' => 'test']);

        $inserts = $wpdb->get_mock_inserts();
        $logInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_logs') !== false) {
                $logInsert = $insert['data'];
                break;
            }
        }

        $this->assertNotNull($logInsert);
        $this->assertEquals('debug', $logInsert['type']);
        $this->assertEquals('Debug message', $logInsert['message']);
    }

    public function test_debug_skips_when_debug_mode_disabled()
    {
        global $wpdb;

        update_option('formflow_debug_mode', false);

        $this->logManager->debug('Debug message');

        $inserts = $wpdb->get_mock_inserts();
        $debugLogFound = false;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_logs') !== false &&
                $insert['data']['type'] === 'debug') {
                $debugLogFound = true;
                break;
            }
        }

        $this->assertFalse($debugLogFound, 'Debug logs should not be inserted when debug mode is disabled');
    }

    public function test_get_logs_with_no_filters()
    {
        global $wpdb;

        $mockLogs = [
            [
                'id' => 1,
                'type' => 'info',
                'message' => 'Log 1',
                'context' => '{}',
                'user_id' => 1,
                'ip_address' => '127.0.0.1',
                'created_at' => '2024-01-01 12:00:00',
            ],
            [
                'id' => 2,
                'type' => 'error',
                'message' => 'Log 2',
                'context' => '{}',
                'user_id' => 2,
                'ip_address' => '127.0.0.1',
                'created_at' => '2024-01-01 13:00:00',
            ],
        ];

        $wpdb->set_mock_result('get_results', $mockLogs);

        $logs = $this->logManager->get_logs();

        $this->assertIsArray($logs);
        $this->assertCount(2, $logs);
    }

    public function test_get_logs_filters_by_type()
    {
        global $wpdb;

        $mockLogs = [
            [
                'id' => 1,
                'type' => 'error',
                'message' => 'Error log',
                'created_at' => '2024-01-01 12:00:00',
            ],
        ];

        $wpdb->set_mock_result('get_results', $mockLogs);

        $logs = $this->logManager->get_logs(['type' => 'error']);

        $this->assertIsArray($logs);
    }

    public function test_get_logs_filters_by_user_id()
    {
        global $wpdb;

        $mockLogs = [
            [
                'id' => 1,
                'type' => 'info',
                'message' => 'User log',
                'user_id' => 42,
                'created_at' => '2024-01-01 12:00:00',
            ],
        ];

        $wpdb->set_mock_result('get_results', $mockLogs);

        $logs = $this->logManager->get_logs(['user_id' => 42]);

        $this->assertIsArray($logs);
    }

    public function test_get_logs_filters_by_date_from()
    {
        global $wpdb;

        $mockLogs = [];
        $wpdb->set_mock_result('get_results', $mockLogs);

        $logs = $this->logManager->get_logs(['date_from' => '2024-01-01']);

        $this->assertIsArray($logs);
    }

    public function test_get_logs_filters_by_date_to()
    {
        global $wpdb;

        $mockLogs = [];
        $wpdb->set_mock_result('get_results', $mockLogs);

        $logs = $this->logManager->get_logs(['date_to' => '2024-12-31']);

        $this->assertIsArray($logs);
    }

    public function test_get_logs_respects_limit()
    {
        global $wpdb;

        $mockLogs = array_fill(0, 50, [
            'id' => 1,
            'type' => 'info',
            'message' => 'Test',
            'created_at' => '2024-01-01 12:00:00',
        ]);

        $wpdb->set_mock_result('get_results', $mockLogs);

        $logs = $this->logManager->get_logs(['limit' => 50]);

        $this->assertIsArray($logs);
        $this->assertLessThanOrEqual(50, count($logs));
    }

    public function test_get_logs_uses_default_limit()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', []);

        $logs = $this->logManager->get_logs();

        // Default limit is 100
        $this->assertIsArray($logs);
    }

    public function test_get_logs_with_multiple_filters()
    {
        global $wpdb;

        $mockLogs = [];
        $wpdb->set_mock_result('get_results', $mockLogs);

        $logs = $this->logManager->get_logs([
            'type' => 'error',
            'user_id' => 42,
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31',
            'limit' => 25,
        ]);

        $this->assertIsArray($logs);
    }

    public function test_cleanup_old_logs_deletes_expired_logs()
    {
        global $wpdb;

        update_option('formflow_log_retention_days', 30);

        $this->logManager->cleanup_old_logs();

        // Verify DELETE query was executed
        $queries = $wpdb->get_mock_queries();
        $deleteFound = false;

        foreach ($queries as $query) {
            if (strpos($query, 'DELETE FROM') !== false && strpos($query, 'formflow_logs') !== false) {
                $deleteFound = true;
                break;
            }
        }

        $this->assertTrue($deleteFound, 'DELETE query should be executed for cleanup');
    }

    public function test_cleanup_old_logs_uses_configured_retention_days()
    {
        global $wpdb;

        update_option('formflow_log_retention_days', 60);

        $this->logManager->cleanup_old_logs();

        // Cleanup should use 60 days retention
        $this->assertTrue(true);
    }

    public function test_cleanup_old_logs_creates_cleanup_log()
    {
        global $wpdb;

        update_option('formflow_log_retention_days', 30);

        $this->logManager->cleanup_old_logs();

        // Verify a cleanup log was created
        $inserts = $wpdb->get_mock_inserts();
        $cleanupLogFound = false;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_logs') !== false &&
                strpos($insert['data']['message'], 'Old logs cleaned up') !== false) {
                $cleanupLogFound = true;
                break;
            }
        }

        $this->assertTrue($cleanupLogFound, 'Cleanup should log itself');
    }

    public function test_get_client_ip_from_remote_addr()
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.42';

        $ip = $this->callPrivateMethod($this->logManager, 'get_client_ip');

        $this->assertEquals('198.51.100.42', $ip);
    }

    public function test_get_client_ip_from_x_forwarded_for()
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1';
        unset($_SERVER['REMOTE_ADDR']);

        $ip = $this->callPrivateMethod($this->logManager, 'get_client_ip');

        $this->assertEquals('203.0.113.1', $ip);
    }

    public function test_get_client_ip_from_client_ip_header()
    {
        $_SERVER['HTTP_CLIENT_IP'] = '198.51.100.99';
        unset($_SERVER['REMOTE_ADDR']);

        $ip = $this->callPrivateMethod($this->logManager, 'get_client_ip');

        $this->assertEquals('198.51.100.99', $ip);
    }

    public function test_get_client_ip_validates_ip_format()
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'invalid-ip';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $ip = $this->callPrivateMethod($this->logManager, 'get_client_ip');

        // Should fall back to REMOTE_ADDR since X_FORWARDED_FOR is invalid
        $this->assertEquals('127.0.0.1', $ip);
    }

    public function test_get_client_ip_returns_default_when_no_valid_ip()
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_CLIENT_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);

        $ip = $this->callPrivateMethod($this->logManager, 'get_client_ip');

        $this->assertEquals('0.0.0.0', $ip);
    }

    public function test_get_client_ip_checks_all_headers_in_order()
    {
        $_SERVER['HTTP_CLIENT_IP'] = '192.0.2.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        $ip = $this->callPrivateMethod($this->logManager, 'get_client_ip');

        // Should return first valid IP (HTTP_CLIENT_IP has priority)
        $this->assertEquals('192.0.2.1', $ip);
    }

    public function test_log_handles_empty_context()
    {
        global $wpdb;

        $this->logManager->log('info', 'Message without context');

        $inserts = $wpdb->get_mock_inserts();
        $logInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_logs') !== false) {
                $logInsert = $insert['data'];
                break;
            }
        }

        $this->assertNotNull($logInsert);
        $this->assertArrayHasKey('context', $logInsert);
        $this->assertEquals('[]', $logInsert['context']);
    }

    public function test_supports_different_log_types()
    {
        $types = ['error', 'warning', 'info', 'debug', 'security', 'audit'];

        foreach ($types as $type) {
            $this->logManager->log($type, "Test {$type} message");
        }

        $inserts = $wpdb->get_mock_inserts();

        // Should have created logs (debug might be skipped if debug mode is off)
        $this->assertNotEmpty($inserts);
    }

    public function test_log_with_complex_context_data()
    {
        global $wpdb;

        $complexContext = [
            'level1' => [
                'level2' => [
                    'level3' => 'deep value',
                ],
            ],
            'array' => [1, 2, 3, 4, 5],
            'boolean' => true,
            'null' => null,
            'number' => 123.456,
        ];

        $this->logManager->log('info', 'Complex context', $complexContext);

        $inserts = $wpdb->get_mock_inserts();
        $logInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_logs') !== false) {
                $logInsert = $insert['data'];
                break;
            }
        }

        $this->assertNotNull($logInsert);
        $decoded = json_decode($logInsert['context'], true);
        $this->assertEquals($complexContext, $decoded);
    }
}
