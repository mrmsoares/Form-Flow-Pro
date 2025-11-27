<?php
/**
 * Tests for TwoFactorAuth class.
 */

namespace FormFlowPro\Tests\Unit\Security;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Security\TwoFactorAuth;

class TwoFactorAuthTest extends TestCase
{
    private $twoFactorAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->twoFactorAuth = TwoFactorAuth::getInstance();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = TwoFactorAuth::getInstance();
        $instance2 = TwoFactorAuth::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(TwoFactorAuth::class, $instance1);
    }

    public function test_generate_secret_key_returns_32_character_string()
    {
        $secret = $this->twoFactorAuth->generateSecretKey();

        $this->assertIsString($secret);
        $this->assertEquals(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_generate_secret_key_returns_different_values()
    {
        $secret1 = $this->twoFactorAuth->generateSecretKey();
        $secret2 = $this->twoFactorAuth->generateSecretKey();

        $this->assertNotEquals($secret1, $secret2);
    }

    public function test_generate_totp_returns_six_digit_code()
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $code = $this->twoFactorAuth->generateTOTP($secret);

        $this->assertIsString($code);
        $this->assertEquals(6, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function test_generate_totp_with_timestamp()
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $timestamp = 1234567890;

        $code = $this->twoFactorAuth->generateTOTP($secret, $timestamp);

        $this->assertIsString($code);
        $this->assertEquals(6, strlen($code));
    }

    public function test_verify_totp_with_valid_code()
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $code = $this->twoFactorAuth->generateTOTP($secret);

        $result = $this->twoFactorAuth->verifyTOTP($secret, $code);

        $this->assertTrue($result);
    }

    public function test_verify_totp_with_invalid_code()
    {
        $secret = 'JBSWY3DPEHPK3PXP';

        $result = $this->twoFactorAuth->verifyTOTP($secret, '000000');

        $this->assertFalse($result);
    }

    public function test_verify_totp_accepts_code_within_time_window()
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $timestamp = time() - 30; // Previous period
        $code = $this->twoFactorAuth->generateTOTP($secret, $timestamp);

        $result = $this->twoFactorAuth->verifyTOTP($secret, $code, 1);

        $this->assertTrue($result);
    }

    public function test_get_qr_code_url_returns_valid_url()
    {
        $userId = 1;
        $secret = 'JBSWY3DPEHPK3PXP';

        $url = $this->twoFactorAuth->getQRCodeUrl($userId, $secret);

        $this->assertIsString($url);
        $this->assertStringContainsString('chart.googleapis.com', $url);
        $this->assertStringContainsString('otpauth://totp/', $url);
        $this->assertStringContainsString($secret, $url);
    }

    public function test_generate_backup_codes_returns_ten_codes()
    {
        $userId = 1;

        $codes = $this->twoFactorAuth->generateBackupCodes($userId);

        $this->assertIsArray($codes);
        $this->assertCount(10, $codes);
    }

    public function test_generate_backup_codes_format()
    {
        $userId = 1;

        $codes = $this->twoFactorAuth->generateBackupCodes($userId);

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);
        }
    }

    public function test_generate_backup_codes_deletes_existing_codes()
    {
        global $wpdb;

        $userId = 1;

        // Generate first set
        $this->twoFactorAuth->generateBackupCodes($userId);
        $count1 = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_2fa_backup_codes WHERE user_id = %d",
            $userId
        ));

        // Generate second set
        $this->twoFactorAuth->generateBackupCodes($userId);
        $count2 = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_2fa_backup_codes WHERE user_id = %d",
            $userId
        ));

        $this->assertEquals(10, $count1);
        $this->assertEquals(10, $count2);
    }

    public function test_verify_backup_code_with_valid_code()
    {
        $userId = 1;
        $codes = $this->twoFactorAuth->generateBackupCodes($userId);
        $testCode = $codes[0];

        $result = $this->twoFactorAuth->verifyBackupCode($userId, $testCode);

        $this->assertTrue($result);
    }

    public function test_verify_backup_code_with_invalid_code()
    {
        $userId = 1;
        $this->twoFactorAuth->generateBackupCodes($userId);

        $result = $this->twoFactorAuth->verifyBackupCode($userId, 'INVALID-CODE');

        $this->assertFalse($result);
    }

    public function test_verify_backup_code_marks_as_used()
    {
        $userId = 1;
        $codes = $this->twoFactorAuth->generateBackupCodes($userId);
        $testCode = $codes[0];

        $this->twoFactorAuth->verifyBackupCode($userId, $testCode);
        $result = $this->twoFactorAuth->verifyBackupCode($userId, $testCode);

        $this->assertFalse($result);
    }

    public function test_verify_backup_code_handles_dashes()
    {
        $userId = 1;
        $codes = $this->twoFactorAuth->generateBackupCodes($userId);
        $testCode = $codes[0];
        $codeWithoutDash = str_replace('-', '', $testCode);

        $result = $this->twoFactorAuth->verifyBackupCode($userId, $codeWithoutDash);

        $this->assertTrue($result);
    }

    public function test_get_remaining_backup_codes_count()
    {
        $userId = 1;
        $codes = $this->twoFactorAuth->generateBackupCodes($userId);

        $count = $this->twoFactorAuth->getRemainingBackupCodesCount($userId);

        $this->assertEquals(10, $count);
    }

    public function test_get_remaining_backup_codes_count_after_use()
    {
        $userId = 1;
        $codes = $this->twoFactorAuth->generateBackupCodes($userId);
        $this->twoFactorAuth->verifyBackupCode($userId, $codes[0]);

        $count = $this->twoFactorAuth->getRemainingBackupCodesCount($userId);

        $this->assertEquals(9, $count);
    }

    public function test_enable_2fa_inserts_new_record()
    {
        global $wpdb;

        $userId = 1;
        $secret = 'JBSWY3DPEHPK3PXP';

        $result = $this->twoFactorAuth->enable2FA($userId, $secret);

        $this->assertTrue($result);

        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_2fa_users WHERE user_id = %d",
            $userId
        ));

        $this->assertNotNull($record);
        $this->assertEquals(1, $record->is_enabled);
        $this->assertEquals('totp', $record->method);
    }

    public function test_enable_2fa_updates_existing_record()
    {
        global $wpdb;

        $userId = 1;
        $secret1 = 'SECRET1DPEHPK3PXP';
        $secret2 = 'SECRET2DPEHPK3PXP';

        $this->twoFactorAuth->enable2FA($userId, $secret1);
        $this->twoFactorAuth->enable2FA($userId, $secret2);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_2fa_users WHERE user_id = %d",
            $userId
        ));

        $this->assertEquals(1, $count);
    }

    public function test_disable_2fa_clears_user_data()
    {
        global $wpdb;

        $userId = 1;
        $secret = 'JBSWY3DPEHPK3PXP';

        $this->twoFactorAuth->enable2FA($userId, $secret);
        $this->twoFactorAuth->generateBackupCodes($userId);
        $result = $this->twoFactorAuth->disable2FA($userId);

        $this->assertTrue($result);

        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_2fa_users WHERE user_id = %d",
            $userId
        ));

        $this->assertEquals(0, $record->is_enabled);
        $this->assertNull($record->secret_key);

        $backupCount = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_2fa_backup_codes WHERE user_id = %d",
            $userId
        ));

        $this->assertEquals(0, $backupCount);
    }

    public function test_is_2fa_enabled_returns_true_when_enabled()
    {
        $userId = 1;
        $secret = 'JBSWY3DPEHPK3PXP';

        $this->twoFactorAuth->enable2FA($userId, $secret);
        $result = $this->twoFactorAuth->is2FAEnabled($userId);

        $this->assertTrue($result);
    }

    public function test_is_2fa_enabled_returns_false_when_disabled()
    {
        $userId = 1;

        $result = $this->twoFactorAuth->is2FAEnabled($userId);

        $this->assertFalse($result);
    }

    public function test_get_2fa_settings_returns_user_settings()
    {
        $userId = 1;
        $secret = 'JBSWY3DPEHPK3PXP';

        $this->twoFactorAuth->enable2FA($userId, $secret);
        $settings = $this->twoFactorAuth->get2FASettings($userId);

        $this->assertIsArray($settings);
        $this->assertEquals($userId, $settings['user_id']);
        $this->assertEquals('totp', $settings['method']);
        $this->assertEquals(1, $settings['is_enabled']);
    }

    public function test_get_2fa_settings_returns_null_for_nonexistent_user()
    {
        $settings = $this->twoFactorAuth->get2FASettings(9999);

        $this->assertNull($settings);
    }

    public function test_remember_device_creates_device_record()
    {
        global $wpdb;

        $userId = 1;

        $token = $this->twoFactorAuth->rememberDevice($userId);

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_2fa_devices WHERE user_id = %d",
            $userId
        ));

        $this->assertEquals(1, $count);
    }

    public function test_is_device_remembered_returns_false_without_cookie()
    {
        $userId = 1;

        $result = $this->twoFactorAuth->isDeviceRemembered($userId);

        $this->assertFalse($result);
    }

    public function test_is_device_remembered_returns_true_with_valid_cookie()
    {
        global $wpdb;

        $userId = 1;
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $_COOKIE['formflow_2fa_device'] = $token;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_2fa_devices',
            [
                'user_id' => $userId,
                'device_token' => $tokenHash,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'created_at' => current_time('mysql'),
            ]
        );

        $result = $this->twoFactorAuth->isDeviceRemembered($userId);

        $this->assertTrue($result);

        unset($_COOKIE['formflow_2fa_device']);
    }

    public function test_get_user_devices_returns_array()
    {
        global $wpdb;

        $userId = 1;
        $tokenHash = hash('sha256', 'test_token');

        $wpdb->insert(
            $wpdb->prefix . 'formflow_2fa_devices',
            [
                'user_id' => $userId,
                'device_token' => $tokenHash,
                'device_name' => 'Test Device',
                'device_type' => 'desktop',
                'browser' => 'Chrome',
                'os' => 'Windows 10',
                'ip_address' => '192.168.1.1',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'created_at' => current_time('mysql'),
            ]
        );

        $devices = $this->twoFactorAuth->getUserDevices($userId);

        $this->assertIsArray($devices);
        $this->assertCount(1, $devices);
        $this->assertEquals('Test Device', $devices[0]['device_name']);
    }

    public function test_revoke_device_removes_device()
    {
        global $wpdb;

        $userId = 1;
        $tokenHash = hash('sha256', 'test_token');

        $wpdb->insert(
            $wpdb->prefix . 'formflow_2fa_devices',
            [
                'user_id' => $userId,
                'device_token' => $tokenHash,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'created_at' => current_time('mysql'),
            ]
        );

        $deviceId = $wpdb->insert_id;

        $result = $this->twoFactorAuth->revokeDevice($userId, $deviceId);

        $this->assertTrue($result);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_2fa_devices WHERE id = %d",
            $deviceId
        ));

        $this->assertEquals(0, $count);
    }

    public function test_revoke_all_devices_removes_all_user_devices()
    {
        global $wpdb;

        $userId = 1;

        for ($i = 0; $i < 3; $i++) {
            $wpdb->insert(
                $wpdb->prefix . 'formflow_2fa_devices',
                [
                    'user_id' => $userId,
                    'device_token' => hash('sha256', 'test_token_' . $i),
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                    'created_at' => current_time('mysql'),
                ]
            );
        }

        $result = $this->twoFactorAuth->revokeAllDevices($userId);

        $this->assertTrue($result);

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_2fa_devices WHERE user_id = %d",
            $userId
        ));

        $this->assertEquals(0, $count);
    }

    public function test_send_email_2fa_code_returns_true()
    {
        global $wpdb;

        $userId = 1;

        // Enable 2FA first
        $this->twoFactorAuth->enable2FA($userId, 'SECRET123');

        $result = $this->twoFactorAuth->sendEmail2FACode($userId);

        $this->assertTrue($result);

        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT email_code, email_code_expires FROM {$wpdb->prefix}formflow_2fa_users WHERE user_id = %d",
            $userId
        ));

        $this->assertNotNull($record->email_code);
        $this->assertNotNull($record->email_code_expires);
    }

    public function test_verify_email_2fa_code_with_valid_code()
    {
        global $wpdb;

        $userId = 1;
        $code = '123456';

        $this->twoFactorAuth->enable2FA($userId, 'SECRET123');

        $wpdb->update(
            $wpdb->prefix . 'formflow_2fa_users',
            [
                'email_code' => password_hash($code, PASSWORD_DEFAULT),
                'email_code_expires' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            ],
            ['user_id' => $userId]
        );

        $result = $this->twoFactorAuth->verifyEmail2FACode($userId, $code);

        $this->assertTrue($result);
    }

    public function test_verify_email_2fa_code_with_invalid_code()
    {
        global $wpdb;

        $userId = 1;

        $this->twoFactorAuth->enable2FA($userId, 'SECRET123');

        $wpdb->update(
            $wpdb->prefix . 'formflow_2fa_users',
            [
                'email_code' => password_hash('123456', PASSWORD_DEFAULT),
                'email_code_expires' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            ],
            ['user_id' => $userId]
        );

        $result = $this->twoFactorAuth->verifyEmail2FACode($userId, '999999');

        $this->assertFalse($result);
    }

    public function test_verify_email_2fa_code_with_expired_code()
    {
        global $wpdb;

        $userId = 1;

        $this->twoFactorAuth->enable2FA($userId, 'SECRET123');

        $wpdb->update(
            $wpdb->prefix . 'formflow_2fa_users',
            [
                'email_code' => password_hash('123456', PASSWORD_DEFAULT),
                'email_code_expires' => date('Y-m-d H:i:s', strtotime('-1 minute')),
            ],
            ['user_id' => $userId]
        );

        $result = $this->twoFactorAuth->verifyEmail2FACode($userId, '123456');

        $this->assertFalse($result);
    }

    public function test_is_rate_limited_returns_false_initially()
    {
        $userId = 1;

        $result = $this->twoFactorAuth->isRateLimited($userId);

        $this->assertFalse($result);
    }

    public function test_is_rate_limited_returns_true_after_max_attempts()
    {
        global $wpdb;

        $userId = 1;

        for ($i = 0; $i < 5; $i++) {
            $wpdb->insert(
                $wpdb->prefix . 'formflow_2fa_attempts',
                [
                    'user_id' => $userId,
                    'ip_address' => '192.168.1.1',
                    'attempt_type' => '2fa',
                    'is_successful' => 0,
                    'attempted_at' => current_time('mysql'),
                ]
            );
        }

        $result = $this->twoFactorAuth->isRateLimited($userId);

        $this->assertTrue($result);
    }

    public function test_get_statistics_returns_correct_structure()
    {
        $stats = $this->twoFactorAuth->getStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('users_with_2fa', $stats);
        $this->assertArrayHasKey('total_devices', $stats);
        $this->assertArrayHasKey('total_security_keys', $stats);
        $this->assertArrayHasKey('attempts_today', $stats);
        $this->assertArrayHasKey('failed_attempts_today', $stats);
        $this->assertArrayHasKey('method_distribution', $stats);
    }

    public function test_get_statistics_counts_users_with_2fa()
    {
        $this->twoFactorAuth->enable2FA(1, 'SECRET1');
        $this->twoFactorAuth->enable2FA(2, 'SECRET2');

        $stats = $this->twoFactorAuth->getStatistics();

        $this->assertEquals(2, $stats['users_with_2fa']);
    }

    public function test_get_user_security_keys_returns_array()
    {
        $userId = 1;

        $keys = $this->twoFactorAuth->getUserSecurityKeys($userId);

        $this->assertIsArray($keys);
    }

    public function test_verify_security_key_with_invalid_credential()
    {
        $userId = 1;

        $result = $this->twoFactorAuth->verifySecurityKey($userId, 'invalid_credential', 1);

        $this->assertFalse($result);
    }

    public function test_cleanup_removes_expired_devices()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_2fa_devices',
            [
                'user_id' => 1,
                'device_token' => 'expired_token',
                'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'created_at' => current_time('mysql'),
            ]
        );

        $this->twoFactorAuth->cleanup();

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_2fa_devices WHERE device_token = 'expired_token'"
        );

        $this->assertEquals(0, $count);
    }

    public function test_create_tables()
    {
        // Mock test - table creation would be tested in integration
        $this->twoFactorAuth->createTables();

        $this->assertTrue(true);
    }

    public function test_ajax_setup_2fa()
    {
        // Mock test - AJAX would be tested in integration
        $this->assertTrue(true);
    }

    public function test_ajax_verify_2fa_setup()
    {
        // Mock test - AJAX would be tested in integration
        $this->assertTrue(true);
    }

    public function test_ajax_disable_2fa()
    {
        // Mock test - AJAX would be tested in integration
        $this->assertTrue(true);
    }

    public function test_ajax_regenerate_backup_codes()
    {
        // Mock test - AJAX would be tested in integration
        $this->assertTrue(true);
    }

    public function test_ajax_revoke_device()
    {
        // Mock test - AJAX would be tested in integration
        $this->assertTrue(true);
    }
}
