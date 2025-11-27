<?php
/**
 * Tests for AccessControl class.
 *
 * @package FormFlowPro\Tests\Unit\Security
 */

namespace FormFlowPro\Tests\Unit\Security;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Security\AccessControl;

class AccessControlTest extends TestCase
{
    private AccessControl $accessControl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessControl = AccessControl::getInstance();
    }

    // ==================== Singleton Tests ====================

    public function testSingletonInstance(): void
    {
        $instance1 = AccessControl::getInstance();
        $instance2 = AccessControl::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    // ==================== IP Validation Tests ====================

    public function testIsValidIP(): void
    {
        $method = new \ReflectionMethod($this->accessControl, 'isValidIP');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->accessControl, '192.168.1.1'));
        $this->assertTrue($method->invoke($this->accessControl, '10.0.0.1'));
        $this->assertTrue($method->invoke($this->accessControl, '8.8.8.8'));
        $this->assertFalse($method->invoke($this->accessControl, 'invalid'));
        $this->assertFalse($method->invoke($this->accessControl, '999.999.999.999'));
    }

    public function testIsValidCIDR(): void
    {
        $method = new \ReflectionMethod($this->accessControl, 'isValidCIDR');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->accessControl, '192.168.1.0/24'));
        $this->assertTrue($method->invoke($this->accessControl, '10.0.0.0/8'));
        $this->assertFalse($method->invoke($this->accessControl, '192.168.1.1'));
        $this->assertFalse($method->invoke($this->accessControl, 'invalid/24'));
        $this->assertFalse($method->invoke($this->accessControl, '192.168.1.0/33'));
    }

    public function testCIDRToRange(): void
    {
        $method = new \ReflectionMethod($this->accessControl, 'cidrToRange');
        $method->setAccessible(true);

        $range = $method->invoke($this->accessControl, '192.168.1.0/24');

        $this->assertEquals('192.168.1.0', $range['start']);
        $this->assertEquals('192.168.1.255', $range['end']);

        $range = $method->invoke($this->accessControl, '10.0.0.0/8');

        $this->assertEquals('10.0.0.0', $range['start']);
        $this->assertEquals('10.255.255.255', $range['end']);
    }

    // ==================== IP Rules Tests ====================

    public function testAddIPRule(): void
    {
        $result = $this->accessControl->addIPRule([
            'ip_address' => '192.168.100.1',
            'rule_type' => 'whitelist',
            'scope' => 'all',
            'description' => 'Test whitelist rule',
        ]);

        $this->assertTrue($result);
    }

    public function testAddIPRuleWithCIDR(): void
    {
        $result = $this->accessControl->addIPRule([
            'ip_address' => '10.0.0.0/24',
            'rule_type' => 'blacklist',
            'scope' => 'admin',
            'description' => 'Block admin access from range',
        ]);

        $this->assertTrue($result);
    }

    public function testAddIPRuleWithInvalidIP(): void
    {
        $result = $this->accessControl->addIPRule([
            'ip_address' => 'not-an-ip',
            'rule_type' => 'whitelist',
        ]);

        $this->assertFalse($result);
    }

    public function testGetIPRulesReturnsArray(): void
    {
        $rules = $this->accessControl->getIPRules();
        $this->assertIsArray($rules);
    }

    public function testGetIPRulesFilterByType(): void
    {
        $whitelistRules = $this->accessControl->getIPRules('whitelist');
        $blacklistRules = $this->accessControl->getIPRules('blacklist');

        $this->assertIsArray($whitelistRules);
        $this->assertIsArray($blacklistRules);
    }

    // ==================== IP Blocking Tests ====================

    public function testBlockIP(): void
    {
        $result = $this->accessControl->blockIP('192.168.200.1', 'Test blocking', 3600);
        $this->assertTrue($result);
    }

    public function testIsIPBlockedReturnsBool(): void
    {
        // Block an IP first
        $this->accessControl->blockIP('192.168.200.2', 'Test', 3600);

        $result = $this->accessControl->isIPBlocked('192.168.200.2');
        $this->assertTrue($result);
    }

    public function testUnblockIP(): void
    {
        $this->accessControl->blockIP('192.168.200.3', 'Test', 3600);
        $result = $this->accessControl->unblockIP('192.168.200.3');
        $this->assertTrue($result);

        // Should no longer be blocked
        $this->assertFalse($this->accessControl->isIPBlocked('192.168.200.3'));
    }

    public function testGetBlockedIPsReturnsArray(): void
    {
        $blockedIPs = $this->accessControl->getBlockedIPs();
        $this->assertIsArray($blockedIPs);
    }

    // ==================== IP Access Tests ====================

    public function testIsIPAllowed(): void
    {
        // By default, IPs should be allowed if no rules exist
        $result = $this->accessControl->isIPAllowed('8.8.8.8', 'all');
        $this->assertIsBool($result);
    }

    public function testIsIPAllowedWithScope(): void
    {
        $result = $this->accessControl->isIPAllowed('8.8.4.4', 'admin');
        $this->assertIsBool($result);
    }

    // ==================== Geo Rules Tests ====================

    public function testAddGeoRule(): void
    {
        $result = $this->accessControl->addGeoRule('CN', 'block', 'all');
        $this->assertTrue($result);
    }

    public function testRemoveGeoRule(): void
    {
        $this->accessControl->addGeoRule('RU', 'block', 'all');
        $result = $this->accessControl->removeGeoRule('RU', 'all');
        $this->assertTrue($result);
    }

    public function testGetGeoRulesReturnsArray(): void
    {
        $rules = $this->accessControl->getGeoRules();
        $this->assertIsArray($rules);
    }

    public function testIsGeoAllowedWhenDisabled(): void
    {
        // Geo blocking disabled by default
        update_option('formflow_geo_blocking_enabled', false);

        $result = $this->accessControl->isGeoAllowed('8.8.8.8', 'all');
        $this->assertTrue($result);
    }

    // ==================== Session Tests ====================

    public function testGetUserSessionsReturnsArray(): void
    {
        $sessions = $this->accessControl->getUserSessions(1);
        $this->assertIsArray($sessions);
    }

    public function testGetSessionReturnsNullForInvalidToken(): void
    {
        $session = $this->accessControl->getSession('invalid_token_123');
        $this->assertNull($session);
    }

    public function testTerminateSessionReturnsBool(): void
    {
        $result = $this->accessControl->terminateSession('nonexistent_token');
        // Returns false for nonexistent session
        $this->assertFalse($result);
    }

    public function testTerminateAllUserSessionsReturnsInt(): void
    {
        $count = $this->accessControl->terminateAllUserSessions(999999);
        $this->assertIsInt($count);
    }

    public function testTerminateAllUserSessionsWithException(): void
    {
        $count = $this->accessControl->terminateAllUserSessions(999999, 'keep_this_token');
        $this->assertIsInt($count);
    }

    // ==================== Login Attempts Tests ====================

    public function testGetLoginAttemptsReturnsArray(): void
    {
        $attempts = $this->accessControl->getLoginAttempts([], 10);
        $this->assertIsArray($attempts);
    }

    public function testGetLoginAttemptsWithFilters(): void
    {
        $attempts = $this->accessControl->getLoginAttempts([
            'ip_address' => '127.0.0.1',
            'is_successful' => false,
        ], 10);

        $this->assertIsArray($attempts);
    }

    // ==================== Statistics Tests ====================

    public function testGetStatisticsReturnsCorrectStructure(): void
    {
        $stats = $this->accessControl->getStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('active_sessions', $stats);
        $this->assertArrayHasKey('blocked_ips', $stats);
        $this->assertArrayHasKey('whitelist_rules', $stats);
        $this->assertArrayHasKey('blacklist_rules', $stats);
        $this->assertArrayHasKey('login_attempts_today', $stats);
        $this->assertArrayHasKey('failed_attempts_today', $stats);
        $this->assertArrayHasKey('geo_rules', $stats);
    }

    // ==================== Cleanup Tests ====================

    public function testCleanupExpiredSessions(): void
    {
        // Should not throw
        $this->accessControl->cleanupExpiredSessions();
        $this->assertTrue(true);
    }

    public function testCleanupOldAttempts(): void
    {
        // Should not throw
        $this->accessControl->cleanupOldAttempts();
        $this->assertTrue(true);
    }

    public function testUnblockExpiredIPs(): void
    {
        // Should not throw
        $this->accessControl->unblockExpiredIPs();
        $this->assertTrue(true);
    }

    // ==================== Device Info Tests ====================

    public function testGetDeviceInfoReturnsCorrectStructure(): void
    {
        $method = new \ReflectionMethod($this->accessControl, 'getDeviceInfo');
        $method->setAccessible(true);

        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36';

        $info = $method->invoke($this->accessControl);

        $this->assertIsArray($info);
        $this->assertArrayHasKey('browser', $info);
        $this->assertArrayHasKey('os', $info);
        $this->assertArrayHasKey('device_type', $info);
        $this->assertArrayHasKey('user_agent', $info);
    }

    public function testGetDeviceInfoDetectsChrome(): void
    {
        $method = new \ReflectionMethod($this->accessControl, 'getDeviceInfo');
        $method->setAccessible(true);

        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36';

        $info = $method->invoke($this->accessControl);

        $this->assertStringContainsString('Chrome', $info['browser']);
    }

    public function testGetDeviceInfoDetectsWindows(): void
    {
        $method = new \ReflectionMethod($this->accessControl, 'getDeviceInfo');
        $method->setAccessible(true);

        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36';

        $info = $method->invoke($this->accessControl);

        $this->assertStringContainsString('Windows', $info['os']);
    }

    public function testGetDeviceInfoDetectsMobile(): void
    {
        $method = new \ReflectionMethod($this->accessControl, 'getDeviceInfo');
        $method->setAccessible(true);

        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15';

        $info = $method->invoke($this->accessControl);

        $this->assertEquals('mobile', $info['device_type']);
    }

    // ==================== Fingerprint Tests ====================

    public function testGenerateDeviceFingerprintReturnsHash(): void
    {
        $method = new \ReflectionMethod($this->accessControl, 'generateDeviceFingerprint');
        $method->setAccessible(true);

        $_SERVER['HTTP_USER_AGENT'] = 'Test Agent';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US';
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip, deflate';

        $fingerprint = $method->invoke($this->accessControl);

        $this->assertIsString($fingerprint);
        $this->assertEquals(64, strlen($fingerprint)); // SHA256 hash length
    }
}
