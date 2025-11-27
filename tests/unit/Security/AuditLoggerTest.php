<?php
/**
 * Tests for AuditLogger class.
 *
 * @package FormFlowPro\Tests\Unit\Security
 */

namespace FormFlowPro\Tests\Unit\Security;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Security\AuditLogger;

class AuditLoggerTest extends TestCase
{
    private AuditLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = AuditLogger::getInstance();
    }

    // ==================== Singleton Tests ====================

    public function testSingletonInstance(): void
    {
        $instance1 = AuditLogger::getInstance();
        $instance2 = AuditLogger::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    // ==================== Constants Tests ====================

    public function testCategoryConstants(): void
    {
        $this->assertEquals('authentication', AuditLogger::CATEGORY_AUTH);
        $this->assertEquals('data_access', AuditLogger::CATEGORY_DATA);
        $this->assertEquals('administration', AuditLogger::CATEGORY_ADMIN);
        $this->assertEquals('security', AuditLogger::CATEGORY_SECURITY);
        $this->assertEquals('system', AuditLogger::CATEGORY_SYSTEM);
        $this->assertEquals('integration', AuditLogger::CATEGORY_INTEGRATION);
        $this->assertEquals('compliance', AuditLogger::CATEGORY_COMPLIANCE);
    }

    public function testSeverityConstants(): void
    {
        $this->assertEquals('debug', AuditLogger::SEVERITY_DEBUG);
        $this->assertEquals('info', AuditLogger::SEVERITY_INFO);
        $this->assertEquals('warning', AuditLogger::SEVERITY_WARNING);
        $this->assertEquals('error', AuditLogger::SEVERITY_ERROR);
        $this->assertEquals('critical', AuditLogger::SEVERITY_CRITICAL);
    }

    public function testEventTypeConstants(): void
    {
        $this->assertEquals('login_success', AuditLogger::EVENT_LOGIN_SUCCESS);
        $this->assertEquals('login_failed', AuditLogger::EVENT_LOGIN_FAILED);
        $this->assertEquals('logout', AuditLogger::EVENT_LOGOUT);
        $this->assertEquals('2fa_enabled', AuditLogger::EVENT_2FA_ENABLED);
        $this->assertEquals('data_exported', AuditLogger::EVENT_DATA_EXPORTED);
        $this->assertEquals('settings_changed', AuditLogger::EVENT_SETTINGS_CHANGED);
    }

    // ==================== Logging Tests ====================

    public function testLogReturnsEventId(): void
    {
        $eventId = $this->logger->log(
            'test_event',
            AuditLogger::CATEGORY_SYSTEM,
            AuditLogger::SEVERITY_INFO,
            ['description' => 'Test log entry']
        );

        $this->assertIsString($eventId);
        $this->assertStringStartsWith('AUD-', $eventId);
    }

    public function testInfoShortcut(): void
    {
        $eventId = $this->logger->info('test_info', 'Test info message');

        $this->assertIsString($eventId);
        $this->assertStringStartsWith('AUD-', $eventId);
    }

    public function testWarningShortcut(): void
    {
        $eventId = $this->logger->warning('test_warning', 'Test warning message');

        $this->assertIsString($eventId);
        $this->assertStringStartsWith('AUD-', $eventId);
    }

    public function testErrorShortcut(): void
    {
        $eventId = $this->logger->error('test_error', 'Test error message');

        $this->assertIsString($eventId);
        $this->assertStringStartsWith('AUD-', $eventId);
    }

    public function testCriticalShortcut(): void
    {
        $eventId = $this->logger->critical('test_critical', 'Test critical message');

        $this->assertIsString($eventId);
        $this->assertStringStartsWith('AUD-', $eventId);
    }

    public function testLogWithFullData(): void
    {
        $eventId = $this->logger->log(
            'test_full_data',
            AuditLogger::CATEGORY_DATA,
            AuditLogger::SEVERITY_INFO,
            [
                'user_id' => 1,
                'user_email' => 'test@example.com',
                'ip_address' => '192.168.1.1',
                'object_type' => 'form',
                'object_id' => 123,
                'object_name' => 'Test Form',
                'description' => 'Full data test',
                'old_value' => ['field' => 'old'],
                'new_value' => ['field' => 'new'],
                'context' => ['key' => 'value'],
            ]
        );

        $this->assertIsString($eventId);
    }

    // ==================== Query Tests ====================

    public function testQueryReturnsArray(): void
    {
        $results = $this->logger->query([], 10, 0);
        $this->assertIsArray($results);
    }

    public function testQueryWithFilters(): void
    {
        $results = $this->logger->query([
            'category' => AuditLogger::CATEGORY_AUTH,
            'severity' => AuditLogger::SEVERITY_WARNING,
        ], 10, 0);

        $this->assertIsArray($results);
    }

    public function testQueryWithMultipleSeverities(): void
    {
        $results = $this->logger->query([
            'severity' => [AuditLogger::SEVERITY_ERROR, AuditLogger::SEVERITY_CRITICAL],
        ], 10, 0);

        $this->assertIsArray($results);
    }

    public function testQueryWithDateRange(): void
    {
        $results = $this->logger->query([
            'date_from' => date('Y-m-d 00:00:00', strtotime('-7 days')),
            'date_to' => date('Y-m-d 23:59:59'),
        ], 10, 0);

        $this->assertIsArray($results);
    }

    public function testQueryWithUserFilter(): void
    {
        $results = $this->logger->query([
            'user_id' => 1,
        ], 10, 0);

        $this->assertIsArray($results);
    }

    public function testQueryWithObjectFilters(): void
    {
        $results = $this->logger->query([
            'object_type' => 'form',
            'object_id' => 123,
        ], 10, 0);

        $this->assertIsArray($results);
    }

    // ==================== Count Tests ====================

    public function testGetCountReturnsInt(): void
    {
        $count = $this->logger->getCount();
        $this->assertIsInt($count);
    }

    public function testGetCountWithFilters(): void
    {
        $count = $this->logger->getCount([
            'category' => AuditLogger::CATEGORY_AUTH,
            'severity' => AuditLogger::SEVERITY_INFO,
        ]);

        $this->assertIsInt($count);
    }

    // ==================== Statistics Tests ====================

    public function testGetStatisticsReturnsCorrectStructure(): void
    {
        $stats = $this->logger->getStatistics('day');

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('by_category', $stats);
        $this->assertArrayHasKey('by_severity', $stats);
        $this->assertArrayHasKey('by_event_type', $stats);
        $this->assertArrayHasKey('top_users', $stats);
        $this->assertArrayHasKey('top_ips', $stats);
        $this->assertArrayHasKey('timeline', $stats);
    }

    public function testGetStatisticsWithDifferentPeriods(): void
    {
        $periods = ['hour', 'day', 'week', 'month'];

        foreach ($periods as $period) {
            $stats = $this->logger->getStatistics($period);
            $this->assertIsArray($stats);
            $this->assertArrayHasKey('total', $stats);
        }
    }

    // ==================== Export Tests ====================

    public function testExportJSON(): void
    {
        $json = $this->logger->export([], 'json');

        $this->assertIsString($json);

        // Should be valid JSON
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
    }

    public function testExportCSV(): void
    {
        // First log something to have data
        $this->logger->log('export_test', AuditLogger::CATEGORY_SYSTEM, AuditLogger::SEVERITY_INFO, [
            'description' => 'Test for CSV export',
        ]);
        $this->logger->flushBuffer();

        $csv = $this->logger->export([], 'csv');

        $this->assertIsString($csv);
    }

    // ==================== Buffer Tests ====================

    public function testFlushBuffer(): void
    {
        // Log something
        $this->logger->log('buffer_test', AuditLogger::CATEGORY_SYSTEM, AuditLogger::SEVERITY_DEBUG, []);

        // Flush should not throw
        $this->logger->flushBuffer();
        $this->assertTrue(true);
    }

    // ==================== Cleanup Tests ====================

    public function testCleanupOldLogs(): void
    {
        // Should not throw
        $this->logger->cleanupOldLogs();
        $this->assertTrue(true);
    }

    // ==================== Event ID Generation Tests ====================

    public function testGenerateEventIdFormat(): void
    {
        $method = new \ReflectionMethod($this->logger, 'generateEventId');
        $method->setAccessible(true);

        $eventId = $method->invoke($this->logger);

        $this->assertStringStartsWith('AUD-', $eventId);
        $this->assertEquals(28, strlen($eventId)); // AUD- (4) + 24 hex chars
    }

    public function testGenerateEventIdUniqueness(): void
    {
        $method = new \ReflectionMethod($this->logger, 'generateEventId');
        $method->setAccessible(true);

        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = $method->invoke($this->logger);
        }

        // All IDs should be unique
        $uniqueIds = array_unique($ids);
        $this->assertCount(100, $uniqueIds);
    }

    // ==================== Checksum Tests ====================

    public function testGenerateChecksumReturnsHash(): void
    {
        $method = new \ReflectionMethod($this->logger, 'generateChecksum');
        $method->setAccessible(true);

        $entry = [
            'event_id' => 'AUD-TEST123',
            'event_type' => 'test',
            'category' => 'system',
            'user_id' => 1,
            'ip_address' => '127.0.0.1',
            'description' => 'Test entry',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $checksum = $method->invoke($this->logger, $entry);

        $this->assertIsString($checksum);
        $this->assertEquals(64, strlen($checksum)); // SHA256 hash
    }

    public function testVerifyChecksumValidates(): void
    {
        $generateMethod = new \ReflectionMethod($this->logger, 'generateChecksum');
        $generateMethod->setAccessible(true);

        $verifyMethod = new \ReflectionMethod($this->logger, 'verifyChecksum');
        $verifyMethod->setAccessible(true);

        $entry = [
            'event_id' => 'AUD-TEST456',
            'event_type' => 'test',
            'category' => 'system',
            'user_id' => 1,
            'ip_address' => '127.0.0.1',
            'description' => 'Test entry',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $checksum = $generateMethod->invoke($this->logger, $entry);
        $entry['checksum'] = $checksum;

        $isValid = $verifyMethod->invoke($this->logger, $entry);

        $this->assertTrue($isValid);
    }

    public function testVerifyChecksumDetectsTampering(): void
    {
        $generateMethod = new \ReflectionMethod($this->logger, 'generateChecksum');
        $generateMethod->setAccessible(true);

        $verifyMethod = new \ReflectionMethod($this->logger, 'verifyChecksum');
        $verifyMethod->setAccessible(true);

        $entry = [
            'event_id' => 'AUD-TEST789',
            'event_type' => 'test',
            'category' => 'system',
            'user_id' => 1,
            'ip_address' => '127.0.0.1',
            'description' => 'Original description',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $checksum = $generateMethod->invoke($this->logger, $entry);
        $entry['checksum'] = $checksum;

        // Tamper with the entry
        $entry['description'] = 'Tampered description';

        $isValid = $verifyMethod->invoke($this->logger, $entry);

        $this->assertFalse($isValid);
    }

    // ==================== Serialization Tests ====================

    public function testSerializeValueString(): void
    {
        $method = new \ReflectionMethod($this->logger, 'serializeValue');
        $method->setAccessible(true);

        $result = $method->invoke($this->logger, 'test string');
        $this->assertEquals('test string', $result);
    }

    public function testSerializeValueArray(): void
    {
        $method = new \ReflectionMethod($this->logger, 'serializeValue');
        $method->setAccessible(true);

        $array = ['key' => 'value', 'nested' => ['a' => 1]];
        $result = $method->invoke($this->logger, $array);

        $this->assertIsString($result);
        $decoded = json_decode($result, true);
        $this->assertEquals($array, $decoded);
    }

    // ==================== Helper Methods Tests ====================

    public function testGetClientIPReturnsString(): void
    {
        $method = new \ReflectionMethod($this->logger, 'getClientIP');
        $method->setAccessible(true);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $ip = $method->invoke($this->logger);

        $this->assertIsString($ip);
        $this->assertNotEmpty($ip);
    }

    public function testGetClientIPWithCloudflare(): void
    {
        $method = new \ReflectionMethod($this->logger, 'getClientIP');
        $method->setAccessible(true);

        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.50';
        $_SERVER['REMOTE_ADDR'] = '172.64.0.1';

        $ip = $method->invoke($this->logger);

        $this->assertEquals('203.0.113.50', $ip);

        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    public function testGetUserAgentReturnsString(): void
    {
        $method = new \ReflectionMethod($this->logger, 'getUserAgent');
        $method->setAccessible(true);

        $_SERVER['HTTP_USER_AGENT'] = 'Test User Agent';

        $ua = $method->invoke($this->logger);

        $this->assertIsString($ua);
        $this->assertEquals('Test User Agent', $ua);
    }

    public function testGetUserAgentTruncatesLongString(): void
    {
        $method = new \ReflectionMethod($this->logger, 'getUserAgent');
        $method->setAccessible(true);

        $_SERVER['HTTP_USER_AGENT'] = str_repeat('A', 1000);

        $ua = $method->invoke($this->logger);

        $this->assertLessThanOrEqual(500, strlen($ua));
    }

    public function testGetRequestUriReturnsString(): void
    {
        $method = new \ReflectionMethod($this->logger, 'getRequestUri');
        $method->setAccessible(true);

        $_SERVER['REQUEST_URI'] = '/test/path?query=value';

        $uri = $method->invoke($this->logger);

        $this->assertIsString($uri);
        $this->assertEquals('/test/path?query=value', $uri);
    }
}
