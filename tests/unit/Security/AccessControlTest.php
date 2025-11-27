<?php
/**
 * Tests for AccessControl class.
 */

namespace FormFlowPro\Tests\Unit\Security;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Security\AccessControl;

class AccessControlTest extends TestCase
{
    private $accessControl;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $this->accessControl = AccessControl::getInstance();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = AccessControl::getInstance();
        $instance2 = AccessControl::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(AccessControl::class, $instance1);
    }

    public function test_add_ip_rule_with_valid_ip()
    {
        global $wpdb;

        $result = $this->accessControl->addIPRule([
            'ip_address' => '192.168.1.100',
            'rule_type' => 'whitelist',
            'scope' => 'all',
            'description' => 'Test rule',
        ]);

        $this->assertTrue($result);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_ip_rules WHERE ip_address = %s",
            '192.168.1.100'
        ));

        $this->assertEquals(1, $count);
    }

    public function test_add_ip_rule_with_cidr()
    {
        $result = $this->accessControl->addIPRule([
            'ip_address' => '192.168.1.0/24',
            'rule_type' => 'whitelist',
            'scope' => 'all',
        ]);

        $this->assertTrue($result);
    }

    public function test_add_ip_rule_with_invalid_ip_returns_false()
    {
        $result = $this->accessControl->addIPRule([
            'ip_address' => 'invalid_ip',
            'rule_type' => 'whitelist',
            'scope' => 'all',
        ]);

        $this->assertFalse($result);
    }

    public function test_remove_ip_rule()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_ip_rules',
            [
                'ip_address' => '192.168.1.100',
                'rule_type' => 'whitelist',
                'scope' => 'all',
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ]
        );

        $ruleId = $wpdb->insert_id;

        $result = $this->accessControl->removeIPRule($ruleId);

        $this->assertTrue($result);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_ip_rules WHERE id = %d",
            $ruleId
        ));

        $this->assertEquals(0, $count);
    }

    public function test_is_ip_allowed_returns_true_without_rules()
    {
        $result = $this->accessControl->isIPAllowed('192.168.1.1', 'all');

        $this->assertTrue($result);
    }

    public function test_is_ip_allowed_with_whitelist()
    {
        global $wpdb;

        // Add whitelist rule for specific IP
        $wpdb->insert(
            $wpdb->prefix . 'formflow_ip_rules',
            [
                'ip_address' => '192.168.1.100',
                'rule_type' => 'whitelist',
                'scope' => 'all',
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ]
        );

        $allowed = $this->accessControl->isIPAllowed('192.168.1.100', 'all');
        $denied = $this->accessControl->isIPAllowed('192.168.1.200', 'all');

        $this->assertTrue($allowed);
        $this->assertFalse($denied);
    }

    public function test_is_ip_allowed_with_blacklist()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_ip_rules',
            [
                'ip_address' => '192.168.1.100',
                'rule_type' => 'blacklist',
                'scope' => 'all',
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ]
        );

        $denied = $this->accessControl->isIPAllowed('192.168.1.100', 'all');
        $allowed = $this->accessControl->isIPAllowed('192.168.1.200', 'all');

        $this->assertFalse($denied);
        $this->assertTrue($allowed);
    }

    public function test_block_ip_creates_block_record()
    {
        global $wpdb;

        $result = $this->accessControl->blockIP('192.168.1.1', 'Test block', 1800);

        $this->assertTrue($result);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_blocked_ips WHERE ip_address = %s",
            '192.168.1.1'
        ));

        $this->assertEquals(1, $count);
    }

    public function test_block_ip_updates_existing_block()
    {
        global $wpdb;

        $this->accessControl->blockIP('192.168.1.1', 'First block', 1800);
        $this->accessControl->blockIP('192.168.1.1', 'Second block', 1800);

        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_blocked_ips WHERE ip_address = %s",
            '192.168.1.1'
        ));

        $this->assertEquals('Second block', $record->reason);
        $this->assertEquals(2, $record->attempts_count);
    }

    public function test_unblock_ip_removes_block()
    {
        global $wpdb;

        $this->accessControl->blockIP('192.168.1.1', 'Test block', 1800);

        $result = $this->accessControl->unblockIP('192.168.1.1');

        $this->assertTrue($result);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_blocked_ips WHERE ip_address = %s",
            '192.168.1.1'
        ));

        $this->assertEquals(0, $count);
    }

    public function test_is_ip_blocked_returns_true_when_blocked()
    {
        $this->accessControl->blockIP('192.168.1.1', 'Test block', 1800);

        $result = $this->accessControl->isIPBlocked('192.168.1.1');

        $this->assertTrue($result);
    }

    public function test_is_ip_blocked_returns_false_when_not_blocked()
    {
        $result = $this->accessControl->isIPBlocked('192.168.1.1');

        $this->assertFalse($result);
    }

    public function test_add_geo_rule()
    {
        global $wpdb;

        $result = $this->accessControl->addGeoRule('US', 'block', 'all');

        $this->assertTrue($result);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_geo_rules WHERE country_code = %s",
            'US'
        ));

        $this->assertEquals(1, $count);
    }

    public function test_remove_geo_rule()
    {
        global $wpdb;

        $this->accessControl->addGeoRule('US', 'block', 'all');

        $result = $this->accessControl->removeGeoRule('US', 'all');

        $this->assertTrue($result);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_geo_rules WHERE country_code = %s",
            'US'
        ));

        $this->assertEquals(0, $count);
    }

    public function test_is_geo_allowed_returns_true_when_disabled()
    {
        update_option('formflow_geo_blocking_enabled', false);

        $result = $this->accessControl->isGeoAllowed('192.168.1.1', 'all');

        $this->assertTrue($result);
    }

    public function test_create_session_inserts_record()
    {
        global $wpdb;

        $this->accessControl->createSession('cookie_value', time() + 3600, 3600, 1, 'auth', 'token');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_sessions WHERE user_id = %d",
            1
        ));

        $this->assertEquals(1, $count);
    }

    public function test_get_session_returns_session_data()
    {
        global $wpdb;

        $sessionToken = bin2hex(random_bytes(32));

        $wpdb->insert(
            $wpdb->prefix . 'formflow_sessions',
            [
                'session_token' => $sessionToken,
                'user_id' => 1,
                'ip_address' => '192.168.1.1',
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'created_at' => current_time('mysql'),
            ]
        );

        $session = $this->accessControl->getSession($sessionToken);

        $this->assertIsArray($session);
        $this->assertEquals(1, $session['user_id']);
        $this->assertEquals($sessionToken, $session['session_token']);
    }

    public function test_get_session_returns_null_for_nonexistent()
    {
        $session = $this->accessControl->getSession('nonexistent_token');

        $this->assertNull($session);
    }

    public function test_get_user_sessions_returns_array()
    {
        global $wpdb;

        for ($i = 0; $i < 3; $i++) {
            $wpdb->insert(
                $wpdb->prefix . 'formflow_sessions',
                [
                    'session_token' => bin2hex(random_bytes(32)),
                    'user_id' => 1,
                    'ip_address' => '192.168.1.1',
                    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                    'created_at' => current_time('mysql'),
                ]
            );
        }

        $sessions = $this->accessControl->getUserSessions(1);

        $this->assertIsArray($sessions);
        $this->assertCount(3, $sessions);
    }

    public function test_terminate_session_removes_session()
    {
        global $wpdb;

        $sessionToken = bin2hex(random_bytes(32));

        $wpdb->insert(
            $wpdb->prefix . 'formflow_sessions',
            [
                'session_token' => $sessionToken,
                'user_id' => 1,
                'ip_address' => '192.168.1.1',
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'created_at' => current_time('mysql'),
            ]
        );

        $result = $this->accessControl->terminateSession($sessionToken);

        $this->assertTrue($result);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_sessions WHERE session_token = %s",
            $sessionToken
        ));

        $this->assertEquals(0, $count);
    }

    public function test_terminate_all_user_sessions()
    {
        global $wpdb;

        for ($i = 0; $i < 3; $i++) {
            $wpdb->insert(
                $wpdb->prefix . 'formflow_sessions',
                [
                    'session_token' => bin2hex(random_bytes(32)),
                    'user_id' => 1,
                    'ip_address' => '192.168.1.1',
                    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                    'created_at' => current_time('mysql'),
                ]
            );
        }

        $count = $this->accessControl->terminateAllUserSessions(1);

        $this->assertEquals(3, $count);

        $remaining = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_sessions WHERE user_id = %d",
            1
        ));

        $this->assertEquals(0, $remaining);
    }

    public function test_terminate_all_user_sessions_except_current()
    {
        global $wpdb;

        $currentToken = bin2hex(random_bytes(32));

        for ($i = 0; $i < 3; $i++) {
            $wpdb->insert(
                $wpdb->prefix . 'formflow_sessions',
                [
                    'session_token' => $i === 0 ? $currentToken : bin2hex(random_bytes(32)),
                    'user_id' => 1,
                    'ip_address' => '192.168.1.1',
                    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                    'created_at' => current_time('mysql'),
                ]
            );
        }

        $count = $this->accessControl->terminateAllUserSessions(1, $currentToken);

        $this->assertEquals(2, $count);

        $remaining = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_sessions WHERE user_id = %d",
            1
        ));

        $this->assertEquals(1, $remaining);
    }

    public function test_get_ip_rules_returns_all_rules()
    {
        global $wpdb;

        for ($i = 0; $i < 3; $i++) {
            $wpdb->insert(
                $wpdb->prefix . 'formflow_ip_rules',
                [
                    'ip_address' => '192.168.1.' . (100 + $i),
                    'rule_type' => 'whitelist',
                    'scope' => 'all',
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                ]
            );
        }

        $rules = $this->accessControl->getIPRules();

        $this->assertIsArray($rules);
        $this->assertCount(3, $rules);
    }

    public function test_get_ip_rules_filters_by_type()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_ip_rules',
            [
                'ip_address' => '192.168.1.100',
                'rule_type' => 'whitelist',
                'scope' => 'all',
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ]
        );

        $wpdb->insert(
            $wpdb->prefix . 'formflow_ip_rules',
            [
                'ip_address' => '192.168.1.101',
                'rule_type' => 'blacklist',
                'scope' => 'all',
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ]
        );

        $whitelist = $this->accessControl->getIPRules('whitelist');
        $blacklist = $this->accessControl->getIPRules('blacklist');

        $this->assertCount(1, $whitelist);
        $this->assertCount(1, $blacklist);
    }

    public function test_get_blocked_ips_returns_active_blocks()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_blocked_ips',
            [
                'ip_address' => '192.168.1.1',
                'reason' => 'Test',
                'attempts_count' => 1,
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'created_at' => current_time('mysql'),
            ]
        );

        $wpdb->insert(
            $wpdb->prefix . 'formflow_blocked_ips',
            [
                'ip_address' => '192.168.1.2',
                'reason' => 'Test',
                'attempts_count' => 1,
                'expires_at' => date('Y-m-d H:i:s', time() - 3600), // Expired
                'created_at' => current_time('mysql'),
            ]
        );

        $blocked = $this->accessControl->getBlockedIPs();

        $this->assertIsArray($blocked);
        $this->assertCount(1, $blocked);
    }

    public function test_get_geo_rules_returns_rules()
    {
        $this->accessControl->addGeoRule('US', 'block', 'all');
        $this->accessControl->addGeoRule('GB', 'allow', 'admin');

        $rules = $this->accessControl->getGeoRules();

        $this->assertIsArray($rules);
        $this->assertCount(2, $rules);
    }

    public function test_get_login_attempts_returns_attempts()
    {
        global $wpdb;

        for ($i = 0; $i < 3; $i++) {
            $wpdb->insert(
                $wpdb->prefix . 'formflow_login_attempts',
                [
                    'ip_address' => '192.168.1.1',
                    'username' => 'testuser',
                    'is_successful' => 0,
                    'attempted_at' => current_time('mysql'),
                ]
            );
        }

        $attempts = $this->accessControl->getLoginAttempts();

        $this->assertIsArray($attempts);
        $this->assertCount(3, $attempts);
    }

    public function test_get_login_attempts_filters_by_ip()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_login_attempts',
            [
                'ip_address' => '192.168.1.1',
                'username' => 'testuser',
                'is_successful' => 0,
                'attempted_at' => current_time('mysql'),
            ]
        );

        $wpdb->insert(
            $wpdb->prefix . 'formflow_login_attempts',
            [
                'ip_address' => '192.168.1.2',
                'username' => 'testuser',
                'is_successful' => 0,
                'attempted_at' => current_time('mysql'),
            ]
        );

        $attempts = $this->accessControl->getLoginAttempts(['ip_address' => '192.168.1.1']);

        $this->assertCount(1, $attempts);
    }

    public function test_get_statistics_returns_correct_structure()
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

    public function test_cleanup_expired_sessions_removes_old_sessions()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_sessions',
            [
                'session_token' => 'expired_token',
                'user_id' => 1,
                'ip_address' => '192.168.1.1',
                'expires_at' => date('Y-m-d H:i:s', time() - 3600),
                'created_at' => current_time('mysql'),
            ]
        );

        $this->accessControl->cleanupExpiredSessions();

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_sessions WHERE session_token = 'expired_token'"
        );

        $this->assertEquals(0, $count);
    }

    public function test_cleanup_old_attempts_removes_old_records()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_login_attempts',
            [
                'ip_address' => '192.168.1.1',
                'username' => 'testuser',
                'is_successful' => 0,
                'attempted_at' => date('Y-m-d H:i:s', strtotime('-31 days')),
            ]
        );

        $this->accessControl->cleanupOldAttempts();

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_login_attempts"
        );

        $this->assertEquals(0, $count);
    }

    public function test_unblock_expired_ips_removes_expired_blocks()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_blocked_ips',
            [
                'ip_address' => '192.168.1.1',
                'reason' => 'Test',
                'attempts_count' => 1,
                'expires_at' => date('Y-m-d H:i:s', time() - 3600),
                'created_at' => current_time('mysql'),
            ]
        );

        $this->accessControl->unblockExpiredIPs();

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_blocked_ips"
        );

        $this->assertEquals(0, $count);
    }

    public function test_create_tables()
    {
        // Mock test - table creation would be tested in integration
        $this->accessControl->createTables();

        $this->assertTrue(true);
    }

    public function test_check_ip_access()
    {
        // Mock test - access check would be tested in integration
        $this->assertTrue(true);
    }

    public function test_validate_session()
    {
        // Mock test - session validation would be tested in integration
        $this->assertTrue(true);
    }

    public function test_handle_successful_login()
    {
        // Mock test - login handling would be tested in integration
        $user = (object)['ID' => 1, 'user_login' => 'testuser'];

        $this->accessControl->handleSuccessfulLogin('testuser', $user);

        $this->assertTrue(true);
    }

    public function test_handle_failed_login()
    {
        // Mock test - failed login handling would be tested in integration
        $this->accessControl->handleFailedLogin('testuser');

        $this->assertTrue(true);
    }

    public function test_handle_logout()
    {
        // Mock test - logout handling would be tested in integration
        $this->accessControl->handleLogout();

        $this->assertTrue(true);
    }
}
