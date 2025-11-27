<?php

declare(strict_types=1);

namespace FormFlowPro\Security;

/**
 * Two-Factor Authentication System
 *
 * Enterprise-grade 2FA implementation supporting:
 * - TOTP (Time-based One-Time Password) - Google Authenticator, Authy, Microsoft Authenticator
 * - Backup codes for account recovery
 * - Remember device functionality
 * - Email-based 2FA fallback
 * - WebAuthn/FIDO2 security keys
 * - Rate limiting and brute force protection
 *
 * @package FormFlowPro\Security
 * @since 2.4.0
 */
class TwoFactorAuth
{
    private static ?TwoFactorAuth $instance = null;

    private const TOTP_DIGITS = 6;
    private const TOTP_PERIOD = 30;
    private const TOTP_ALGORITHM = 'sha1';
    private const BACKUP_CODES_COUNT = 10;
    private const REMEMBER_DEVICE_DAYS = 30;
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 900; // 15 minutes

    private string $tableUsers2FA;
    private string $tableBackupCodes;
    private string $tableDevices;
    private string $tableSecurityKeys;
    private string $tableAttempts;

    private function __construct()
    {
        global $wpdb;
        $this->tableUsers2FA = $wpdb->prefix . 'formflow_2fa_users';
        $this->tableBackupCodes = $wpdb->prefix . 'formflow_2fa_backup_codes';
        $this->tableDevices = $wpdb->prefix . 'formflow_2fa_devices';
        $this->tableSecurityKeys = $wpdb->prefix . 'formflow_2fa_security_keys';
        $this->tableAttempts = $wpdb->prefix . 'formflow_2fa_attempts';

        $this->initHooks();
    }

    public static function getInstance(): TwoFactorAuth
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initHooks(): void
    {
        add_action('wp_login', [$this, 'handleLogin'], 10, 2);
        add_action('init', [$this, 'handle2FAVerification']);
        add_filter('authenticate', [$this, 'checkRateLimiting'], 30, 3);
        add_action('wp_ajax_formflow_setup_2fa', [$this, 'ajaxSetup2FA']);
        add_action('wp_ajax_formflow_verify_2fa_setup', [$this, 'ajaxVerify2FASetup']);
        add_action('wp_ajax_formflow_disable_2fa', [$this, 'ajaxDisable2FA']);
        add_action('wp_ajax_formflow_regenerate_backup_codes', [$this, 'ajaxRegenerateBackupCodes']);
        add_action('wp_ajax_formflow_register_security_key', [$this, 'ajaxRegisterSecurityKey']);
        add_action('wp_ajax_formflow_remove_security_key', [$this, 'ajaxRemoveSecurityKey']);
        add_action('wp_ajax_formflow_revoke_device', [$this, 'ajaxRevokeDevice']);
        add_action('wp_ajax_nopriv_formflow_verify_2fa', [$this, 'ajaxVerify2FA']);
    }

    /**
     * Create database tables for 2FA
     */
    public function createTables(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [];

        // Users 2FA settings
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableUsers2FA} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            method ENUM('totp', 'email', 'security_key') DEFAULT 'totp',
            secret_key VARCHAR(64) NULL,
            is_enabled TINYINT(1) DEFAULT 0,
            is_enforced TINYINT(1) DEFAULT 0,
            email_code VARCHAR(10) NULL,
            email_code_expires DATETIME NULL,
            last_used DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (user_id),
            INDEX idx_enabled (is_enabled),
            INDEX idx_method (method)
        ) {$charset};";

        // Backup codes
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableBackupCodes} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            is_used TINYINT(1) DEFAULT 0,
            used_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_used (is_used)
        ) {$charset};";

        // Remembered devices
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableDevices} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            device_token VARCHAR(64) NOT NULL,
            device_name VARCHAR(255) NULL,
            device_type VARCHAR(50) NULL,
            browser VARCHAR(100) NULL,
            os VARCHAR(100) NULL,
            ip_address VARCHAR(45) NULL,
            location VARCHAR(255) NULL,
            last_used DATETIME NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_token (device_token),
            INDEX idx_user (user_id),
            INDEX idx_expires (expires_at)
        ) {$charset};";

        // WebAuthn security keys
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableSecurityKeys} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            credential_id VARBINARY(1024) NOT NULL,
            public_key TEXT NOT NULL,
            sign_count INT UNSIGNED DEFAULT 0,
            name VARCHAR(255) NULL,
            transports JSON NULL,
            attestation_type VARCHAR(50) NULL,
            aaguid VARBINARY(16) NULL,
            last_used DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_credential (credential_id(255))
        ) {$charset};";

        // Rate limiting attempts
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableAttempts} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempt_type ENUM('login', '2fa', 'backup_code') NOT NULL,
            is_successful TINYINT(1) DEFAULT 0,
            attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip (ip_address),
            INDEX idx_user (user_id),
            INDEX idx_type_time (attempt_type, attempted_at)
        ) {$charset};";

        $upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
        if (file_exists($upgrade_file)) {
            require_once $upgrade_file;
            foreach ($sql as $query) {
                dbDelta($query);
            }
        }
    }

    /**
     * Generate TOTP secret key
     */
    public function generateSecretKey(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Generate TOTP code
     */
    public function generateTOTP(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $counter = floor($timestamp / self::TOTP_PERIOD);

        $binarySecret = $this->base32Decode($secret);
        $binaryCounter = pack('N*', 0, $counter);

        $hash = hash_hmac(self::TOTP_ALGORITHM, $binaryCounter, $binarySecret, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % pow(10, self::TOTP_DIGITS);

        return str_pad((string) $code, self::TOTP_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verify TOTP code with time drift tolerance
     */
    public function verifyTOTP(string $secret, string $code, int $window = 1): bool
    {
        $timestamp = time();

        for ($i = -$window; $i <= $window; $i++) {
            $checkTime = $timestamp + ($i * self::TOTP_PERIOD);
            if (hash_equals($this->generateTOTP($secret, $checkTime), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Base32 decode
     */
    private function base32Decode(string $input): string
    {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper($input);
        $input = str_replace('=', '', $input);

        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $val = strpos($map, $input[$i]);
            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }

    /**
     * Generate QR code URL for authenticator apps
     */
    public function getQRCodeUrl(int $userId, string $secret): string
    {
        $user = get_userdata($userId);
        $siteName = get_bloginfo('name');
        $accountName = $user ? $user->user_email : 'user';

        $issuer = rawurlencode($siteName);
        $account = rawurlencode($accountName);

        $otpauthUrl = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=%s&digits=%d&period=%d',
            $issuer,
            $account,
            $secret,
            $issuer,
            strtoupper(self::TOTP_ALGORITHM),
            self::TOTP_DIGITS,
            self::TOTP_PERIOD
        );

        // Using Google Charts API for QR code generation
        return sprintf(
            'https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=%s&choe=UTF-8',
            rawurlencode($otpauthUrl)
        );
    }

    /**
     * Generate backup codes
     */
    public function generateBackupCodes(int $userId): array
    {
        global $wpdb;

        // Delete existing codes
        $wpdb->delete($this->tableBackupCodes, ['user_id' => $userId], ['%d']);

        $codes = [];
        for ($i = 0; $i < self::BACKUP_CODES_COUNT; $i++) {
            $code = $this->generateRandomCode(8);
            $codes[] = $code;

            $wpdb->insert(
                $this->tableBackupCodes,
                [
                    'user_id' => $userId,
                    'code_hash' => password_hash($code, PASSWORD_ARGON2ID),
                    'is_used' => 0,
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%d', '%s']
            );
        }

        return $codes;
    }

    /**
     * Generate random alphanumeric code
     */
    private function generateRandomCode(int $length): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Removed confusing chars: I, O, 0, 1
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return substr_replace($code, '-', 4, 0); // Format: XXXX-XXXX
    }

    /**
     * Verify backup code
     */
    public function verifyBackupCode(int $userId, string $code): bool
    {
        global $wpdb;

        $code = str_replace('-', '', strtoupper($code));

        $codes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, code_hash FROM {$this->tableBackupCodes}
                WHERE user_id = %d AND is_used = 0",
                $userId
            )
        );

        foreach ($codes as $stored) {
            if (password_verify($code, $stored->code_hash)) {
                // Mark as used
                $wpdb->update(
                    $this->tableBackupCodes,
                    [
                        'is_used' => 1,
                        'used_at' => current_time('mysql'),
                    ],
                    ['id' => $stored->id],
                    ['%d', '%s'],
                    ['%d']
                );

                $this->logAttempt($userId, '2fa', true, 'backup_code');
                return true;
            }
        }

        $this->logAttempt($userId, '2fa', false, 'backup_code');
        return false;
    }

    /**
     * Get remaining backup codes count
     */
    public function getRemainingBackupCodesCount(int $userId): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tableBackupCodes}
                WHERE user_id = %d AND is_used = 0",
                $userId
            )
        );
    }

    /**
     * Enable 2FA for user
     */
    public function enable2FA(int $userId, string $secret, string $method = 'totp'): bool
    {
        global $wpdb;

        $encryptedSecret = $this->encryptSecret($secret);

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$this->tableUsers2FA} WHERE user_id = %d",
                $userId
            )
        );

        if ($existing) {
            return (bool) $wpdb->update(
                $this->tableUsers2FA,
                [
                    'method' => $method,
                    'secret_key' => $encryptedSecret,
                    'is_enabled' => 1,
                    'updated_at' => current_time('mysql'),
                ],
                ['user_id' => $userId],
                ['%s', '%s', '%d', '%s'],
                ['%d']
            );
        }

        return (bool) $wpdb->insert(
            $this->tableUsers2FA,
            [
                'user_id' => $userId,
                'method' => $method,
                'secret_key' => $encryptedSecret,
                'is_enabled' => 1,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%s']
        );
    }

    /**
     * Disable 2FA for user
     */
    public function disable2FA(int $userId): bool
    {
        global $wpdb;

        // Delete backup codes
        $wpdb->delete($this->tableBackupCodes, ['user_id' => $userId], ['%d']);

        // Delete remembered devices
        $wpdb->delete($this->tableDevices, ['user_id' => $userId], ['%d']);

        // Delete security keys
        $wpdb->delete($this->tableSecurityKeys, ['user_id' => $userId], ['%d']);

        // Update user 2FA settings
        return (bool) $wpdb->update(
            $this->tableUsers2FA,
            [
                'is_enabled' => 0,
                'secret_key' => null,
                'updated_at' => current_time('mysql'),
            ],
            ['user_id' => $userId],
            ['%d', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * Check if 2FA is enabled for user
     */
    public function is2FAEnabled(int $userId): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT is_enabled FROM {$this->tableUsers2FA} WHERE user_id = %d",
                $userId
            )
        );
    }

    /**
     * Get user 2FA settings
     */
    public function get2FASettings(int $userId): ?array
    {
        global $wpdb;

        $settings = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableUsers2FA} WHERE user_id = %d",
                $userId
            ),
            ARRAY_A
        );

        if ($settings && $settings['secret_key']) {
            $settings['secret_key'] = $this->decryptSecret($settings['secret_key']);
        }

        return $settings;
    }

    /**
     * Encrypt secret key
     */
    private function encryptSecret(string $secret): string
    {
        $key = $this->getEncryptionKey();
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($secret, 'AES-256-GCM', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt secret key
     */
    private function decryptSecret(string $encrypted): string
    {
        $key = $this->getEncryptionKey();
        $data = base64_decode($encrypted);

        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $ciphertext = substr($data, 32);

        $decrypted = openssl_decrypt($ciphertext, 'AES-256-GCM', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $decrypted ?: '';
    }

    /**
     * Get encryption key
     */
    private function getEncryptionKey(): string
    {
        $key = get_option('formflow_2fa_encryption_key');

        if (!$key) {
            $key = base64_encode(random_bytes(32));
            update_option('formflow_2fa_encryption_key', $key, false);
        }

        return base64_decode($key);
    }

    /**
     * Remember device
     */
    public function rememberDevice(int $userId): string
    {
        global $wpdb;

        $token = bin2hex(random_bytes(32));
        $deviceInfo = $this->getDeviceInfo();

        $wpdb->insert(
            $this->tableDevices,
            [
                'user_id' => $userId,
                'device_token' => hash('sha256', $token),
                'device_name' => $deviceInfo['device_name'],
                'device_type' => $deviceInfo['device_type'],
                'browser' => $deviceInfo['browser'],
                'os' => $deviceInfo['os'],
                'ip_address' => $this->getClientIP(),
                'location' => $this->getLocationFromIP($this->getClientIP()),
                'last_used' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+' . self::REMEMBER_DEVICE_DAYS . ' days')),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        // Set cookie
        setcookie(
            'formflow_2fa_device',
            $token,
            [
                'expires' => strtotime('+' . self::REMEMBER_DEVICE_DAYS . ' days'),
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );

        return $token;
    }

    /**
     * Check if device is remembered
     */
    public function isDeviceRemembered(int $userId): bool
    {
        global $wpdb;

        if (!isset($_COOKIE['formflow_2fa_device'])) {
            return false;
        }

        $tokenHash = hash('sha256', sanitize_text_field($_COOKIE['formflow_2fa_device']));

        $device = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$this->tableDevices}
                WHERE user_id = %d AND device_token = %s AND expires_at > NOW()",
                $userId,
                $tokenHash
            )
        );

        if ($device) {
            // Update last used
            $wpdb->update(
                $this->tableDevices,
                ['last_used' => current_time('mysql')],
                ['id' => $device->id],
                ['%s'],
                ['%d']
            );
            return true;
        }

        return false;
    }

    /**
     * Get user's remembered devices
     */
    public function getUserDevices(int $userId): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, device_name, device_type, browser, os, ip_address, location,
                        last_used, expires_at, created_at
                FROM {$this->tableDevices}
                WHERE user_id = %d AND expires_at > NOW()
                ORDER BY last_used DESC",
                $userId
            ),
            ARRAY_A
        );
    }

    /**
     * Revoke device
     */
    public function revokeDevice(int $userId, int $deviceId): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete(
            $this->tableDevices,
            [
                'id' => $deviceId,
                'user_id' => $userId,
            ],
            ['%d', '%d']
        );
    }

    /**
     * Revoke all devices
     */
    public function revokeAllDevices(int $userId): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete(
            $this->tableDevices,
            ['user_id' => $userId],
            ['%d']
        );
    }

    /**
     * Get device info from user agent
     */
    private function getDeviceInfo(): array
    {
        $userAgent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $browser = 'Unknown';
        $os = 'Unknown';
        $deviceType = 'desktop';
        $deviceName = 'Unknown Device';

        // Detect browser
        if (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Firefox ' . $matches[1];
        } elseif (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Chrome ' . $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Safari ' . $matches[1];
        } elseif (preg_match('/Edge\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Edge ' . $matches[1];
        } elseif (preg_match('/MSIE ([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'IE ' . $matches[1];
        }

        // Detect OS
        if (preg_match('/Windows NT ([0-9.]+)/', $userAgent, $matches)) {
            $versions = ['10.0' => '10', '6.3' => '8.1', '6.2' => '8', '6.1' => '7'];
            $os = 'Windows ' . ($versions[$matches[1]] ?? $matches[1]);
        } elseif (preg_match('/Mac OS X ([0-9_.]+)/', $userAgent, $matches)) {
            $os = 'macOS ' . str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/Linux/', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android ([0-9.]+)/', $userAgent, $matches)) {
            $os = 'Android ' . $matches[1];
            $deviceType = 'mobile';
        } elseif (preg_match('/iPhone OS ([0-9_]+)/', $userAgent, $matches)) {
            $os = 'iOS ' . str_replace('_', '.', $matches[1]);
            $deviceType = 'mobile';
        } elseif (preg_match('/iPad/', $userAgent)) {
            $os = 'iPadOS';
            $deviceType = 'tablet';
        }

        $deviceName = $browser . ' on ' . $os;

        return [
            'browser' => $browser,
            'os' => $os,
            'device_type' => $deviceType,
            'device_name' => $deviceName,
        ];
    }

    /**
     * Get client IP address
     */
    private function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field($_SERVER[$header]);
                // Handle multiple IPs in X-Forwarded-For
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get location from IP (basic implementation)
     */
    private function getLocationFromIP(string $ip): string
    {
        // Skip for local IPs
        if (in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'])) {
            return 'Local';
        }

        // Use ip-api.com for geolocation (free tier)
        $cached = get_transient('formflow_ip_location_' . md5($ip));
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get(
            "http://ip-api.com/json/{$ip}?fields=city,country",
            ['timeout' => 3]
        );

        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($body && isset($body['city'], $body['country'])) {
                $location = $body['city'] . ', ' . $body['country'];
                set_transient('formflow_ip_location_' . md5($ip), $location, DAY_IN_SECONDS);
                return $location;
            }
        }

        return 'Unknown';
    }

    /**
     * Send email 2FA code
     */
    public function sendEmail2FACode(int $userId): bool
    {
        global $wpdb;

        $user = get_userdata($userId);
        if (!$user) {
            return false;
        }

        $code = sprintf('%06d', random_int(0, 999999));
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $wpdb->update(
            $this->tableUsers2FA,
            [
                'email_code' => password_hash($code, PASSWORD_DEFAULT),
                'email_code_expires' => $expires,
            ],
            ['user_id' => $userId],
            ['%s', '%s'],
            ['%d']
        );

        $siteName = get_bloginfo('name');
        $subject = sprintf(__('[%s] Your verification code', 'formflow-pro'), $siteName);

        $message = sprintf(
            __("Your verification code is: %s\n\nThis code will expire in 10 minutes.\n\nIf you didn't request this code, please ignore this email.", 'formflow-pro'),
            $code
        );

        return wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Verify email 2FA code
     */
    public function verifyEmail2FACode(int $userId, string $code): bool
    {
        global $wpdb;

        $settings = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT email_code, email_code_expires FROM {$this->tableUsers2FA}
                WHERE user_id = %d",
                $userId
            )
        );

        if (!$settings || !$settings->email_code) {
            return false;
        }

        if (strtotime($settings->email_code_expires) < time()) {
            return false;
        }

        if (password_verify($code, $settings->email_code)) {
            // Clear the code
            $wpdb->update(
                $this->tableUsers2FA,
                [
                    'email_code' => null,
                    'email_code_expires' => null,
                    'last_used' => current_time('mysql'),
                ],
                ['user_id' => $userId],
                ['%s', '%s', '%s'],
                ['%d']
            );

            $this->logAttempt($userId, '2fa', true, 'email');
            return true;
        }

        $this->logAttempt($userId, '2fa', false, 'email');
        return false;
    }

    /**
     * Log authentication attempt
     */
    private function logAttempt(int $userId, string $type, bool $success, string $method = ''): void
    {
        global $wpdb;

        $wpdb->insert(
            $this->tableAttempts,
            [
                'user_id' => $userId,
                'ip_address' => $this->getClientIP(),
                'attempt_type' => $type,
                'is_successful' => $success ? 1 : 0,
                'attempted_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%s']
        );

        // Fire action for audit logging
        do_action('formflow_2fa_attempt', [
            'user_id' => $userId,
            'type' => $type,
            'method' => $method,
            'success' => $success,
            'ip' => $this->getClientIP(),
        ]);
    }

    /**
     * Check rate limiting
     */
    public function checkRateLimiting($user, string $username, string $password)
    {
        if (is_wp_error($user) || !$user) {
            return $user;
        }

        $ip = $this->getClientIP();

        if ($this->isRateLimited($user->ID)) {
            return new \WP_Error(
                'rate_limited',
                __('Too many failed attempts. Please try again in 15 minutes.', 'formflow-pro')
            );
        }

        return $user;
    }

    /**
     * Check if user/IP is rate limited
     */
    public function isRateLimited(int $userId): bool
    {
        global $wpdb;

        $ip = $this->getClientIP();
        $since = date('Y-m-d H:i:s', time() - self::LOCKOUT_DURATION);

        $attempts = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tableAttempts}
                WHERE (user_id = %d OR ip_address = %s)
                AND is_successful = 0
                AND attempted_at > %s",
                $userId,
                $ip,
                $since
            )
        );

        return (int) $attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * Handle login - check if 2FA is required
     */
    public function handleLogin(string $userLogin, \WP_User $user): void
    {
        if (!$this->is2FAEnabled($user->ID)) {
            return;
        }

        // Check if device is remembered
        if ($this->isDeviceRemembered($user->ID)) {
            return;
        }

        // Logout and redirect to 2FA verification
        wp_logout();

        $token = $this->create2FASession($user->ID);

        wp_safe_redirect(
            add_query_arg(
                ['action' => 'formflow_2fa', 'token' => $token],
                wp_login_url()
            )
        );
        exit;
    }

    /**
     * Create temporary 2FA session
     */
    private function create2FASession(int $userId): string
    {
        $token = bin2hex(random_bytes(32));

        set_transient(
            'formflow_2fa_session_' . $token,
            [
                'user_id' => $userId,
                'created' => time(),
                'ip' => $this->getClientIP(),
            ],
            600 // 10 minutes
        );

        return $token;
    }

    /**
     * Handle 2FA verification page
     */
    public function handle2FAVerification(): void
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'formflow_2fa') {
            return;
        }

        $token = sanitize_text_field($_GET['token'] ?? '');
        $session = get_transient('formflow_2fa_session_' . $token);

        if (!$session) {
            wp_safe_redirect(wp_login_url());
            exit;
        }

        // Verify same IP
        if ($session['ip'] !== $this->getClientIP()) {
            delete_transient('formflow_2fa_session_' . $token);
            wp_safe_redirect(wp_login_url());
            exit;
        }

        $this->render2FAPage($session['user_id'], $token);
        exit;
    }

    /**
     * Render 2FA verification page
     */
    private function render2FAPage(int $userId, string $token): void
    {
        $settings = $this->get2FASettings($userId);
        $user = get_userdata($userId);
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html__('Two-Factor Authentication', 'formflow-pro'); ?> - <?php bloginfo('name'); ?></title>
            <?php wp_head(); ?>
            <style>
                body.formflow-2fa {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                }
                .formflow-2fa-container {
                    background: #fff;
                    border-radius: 16px;
                    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
                    padding: 40px;
                    max-width: 420px;
                    width: 100%;
                    margin: 20px;
                }
                .formflow-2fa-logo {
                    text-align: center;
                    margin-bottom: 24px;
                }
                .formflow-2fa-logo img {
                    max-width: 60px;
                    height: auto;
                }
                .formflow-2fa-title {
                    font-size: 24px;
                    font-weight: 600;
                    color: #1a202c;
                    text-align: center;
                    margin: 0 0 8px;
                }
                .formflow-2fa-subtitle {
                    color: #718096;
                    text-align: center;
                    margin: 0 0 32px;
                    font-size: 14px;
                }
                .formflow-2fa-tabs {
                    display: flex;
                    gap: 8px;
                    margin-bottom: 24px;
                    border-bottom: 1px solid #e2e8f0;
                    padding-bottom: 16px;
                }
                .formflow-2fa-tab {
                    flex: 1;
                    padding: 12px 16px;
                    border: none;
                    background: #f7fafc;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 13px;
                    font-weight: 500;
                    color: #4a5568;
                    transition: all 0.2s;
                }
                .formflow-2fa-tab:hover {
                    background: #edf2f7;
                }
                .formflow-2fa-tab.active {
                    background: #667eea;
                    color: #fff;
                }
                .formflow-2fa-input-group {
                    margin-bottom: 20px;
                }
                .formflow-2fa-code-inputs {
                    display: flex;
                    gap: 8px;
                    justify-content: center;
                }
                .formflow-2fa-code-input {
                    width: 48px;
                    height: 56px;
                    border: 2px solid #e2e8f0;
                    border-radius: 12px;
                    font-size: 24px;
                    font-weight: 600;
                    text-align: center;
                    transition: all 0.2s;
                }
                .formflow-2fa-code-input:focus {
                    border-color: #667eea;
                    outline: none;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }
                .formflow-2fa-backup-input {
                    width: 100%;
                    padding: 16px;
                    border: 2px solid #e2e8f0;
                    border-radius: 12px;
                    font-size: 18px;
                    font-weight: 500;
                    text-align: center;
                    letter-spacing: 2px;
                    text-transform: uppercase;
                }
                .formflow-2fa-backup-input:focus {
                    border-color: #667eea;
                    outline: none;
                }
                .formflow-2fa-submit {
                    width: 100%;
                    padding: 16px 24px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border: none;
                    border-radius: 12px;
                    color: #fff;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                .formflow-2fa-submit:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
                }
                .formflow-2fa-submit:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                    transform: none;
                }
                .formflow-2fa-remember {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    margin: 16px 0;
                    font-size: 14px;
                    color: #4a5568;
                }
                .formflow-2fa-remember input {
                    width: 18px;
                    height: 18px;
                    accent-color: #667eea;
                }
                .formflow-2fa-error {
                    background: #fed7d7;
                    color: #c53030;
                    padding: 12px 16px;
                    border-radius: 8px;
                    margin-bottom: 16px;
                    font-size: 14px;
                    display: none;
                }
                .formflow-2fa-section {
                    display: none;
                }
                .formflow-2fa-section.active {
                    display: block;
                }
                .formflow-2fa-footer {
                    margin-top: 24px;
                    text-align: center;
                }
                .formflow-2fa-footer a {
                    color: #667eea;
                    text-decoration: none;
                    font-size: 14px;
                }
                .formflow-2fa-footer a:hover {
                    text-decoration: underline;
                }
                .formflow-2fa-resend {
                    text-align: center;
                    margin-top: 16px;
                }
                .formflow-2fa-resend button {
                    background: none;
                    border: none;
                    color: #667eea;
                    cursor: pointer;
                    font-size: 14px;
                }
                .formflow-2fa-resend button:disabled {
                    color: #a0aec0;
                    cursor: not-allowed;
                }
            </style>
        </head>
        <body class="formflow-2fa">
            <div class="formflow-2fa-container">
                <div class="formflow-2fa-logo">
                    <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
                        <rect width="60" height="60" rx="12" fill="url(#grad)"/>
                        <path d="M30 15L42 22.5V37.5L30 45L18 37.5V22.5L30 15Z" stroke="#fff" stroke-width="2" fill="none"/>
                        <circle cx="30" cy="30" r="8" stroke="#fff" stroke-width="2" fill="none"/>
                        <defs>
                            <linearGradient id="grad" x1="0" y1="0" x2="60" y2="60">
                                <stop offset="0%" stop-color="#667eea"/>
                                <stop offset="100%" stop-color="#764ba2"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>

                <h1 class="formflow-2fa-title"><?php esc_html_e('Two-Factor Authentication', 'formflow-pro'); ?></h1>
                <p class="formflow-2fa-subtitle">
                    <?php printf(esc_html__('Verify your identity to continue as %s', 'formflow-pro'), esc_html($user->display_name)); ?>
                </p>

                <div class="formflow-2fa-error" id="formflow-2fa-error"></div>

                <div class="formflow-2fa-tabs">
                    <button class="formflow-2fa-tab active" data-tab="totp">
                        <?php esc_html_e('Authenticator', 'formflow-pro'); ?>
                    </button>
                    <?php if ($settings['method'] === 'email' || true): ?>
                    <button class="formflow-2fa-tab" data-tab="email">
                        <?php esc_html_e('Email', 'formflow-pro'); ?>
                    </button>
                    <?php endif; ?>
                    <button class="formflow-2fa-tab" data-tab="backup">
                        <?php esc_html_e('Backup Code', 'formflow-pro'); ?>
                    </button>
                </div>

                <form id="formflow-2fa-form" method="post">
                    <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                    <input type="hidden" name="method" id="formflow-2fa-method" value="totp">
                    <?php wp_nonce_field('formflow_verify_2fa', 'formflow_2fa_nonce'); ?>

                    <!-- TOTP Section -->
                    <div class="formflow-2fa-section active" id="section-totp">
                        <div class="formflow-2fa-input-group">
                            <div class="formflow-2fa-code-inputs">
                                <?php for ($i = 0; $i < 6; $i++): ?>
                                <input type="text"
                                       class="formflow-2fa-code-input"
                                       maxlength="1"
                                       pattern="[0-9]"
                                       inputmode="numeric"
                                       autocomplete="one-time-code"
                                       data-index="<?php echo $i; ?>">
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="totp_code" id="totp-code-hidden">
                        </div>
                    </div>

                    <!-- Email Section -->
                    <div class="formflow-2fa-section" id="section-email">
                        <div class="formflow-2fa-input-group">
                            <div class="formflow-2fa-code-inputs">
                                <?php for ($i = 0; $i < 6; $i++): ?>
                                <input type="text"
                                       class="formflow-2fa-code-input formflow-2fa-email-input"
                                       maxlength="1"
                                       pattern="[0-9]"
                                       inputmode="numeric"
                                       data-index="<?php echo $i; ?>">
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="email_code" id="email-code-hidden">
                        </div>
                        <div class="formflow-2fa-resend">
                            <button type="button" id="resend-email-code">
                                <?php esc_html_e('Send code to email', 'formflow-pro'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Backup Code Section -->
                    <div class="formflow-2fa-section" id="section-backup">
                        <div class="formflow-2fa-input-group">
                            <input type="text"
                                   name="backup_code"
                                   class="formflow-2fa-backup-input"
                                   placeholder="XXXX-XXXX"
                                   autocomplete="off">
                        </div>
                    </div>

                    <label class="formflow-2fa-remember">
                        <input type="checkbox" name="remember_device" value="1">
                        <?php esc_html_e('Remember this device for 30 days', 'formflow-pro'); ?>
                    </label>

                    <button type="submit" class="formflow-2fa-submit">
                        <?php esc_html_e('Verify', 'formflow-pro'); ?>
                    </button>
                </form>

                <div class="formflow-2fa-footer">
                    <a href="<?php echo esc_url(wp_login_url()); ?>">
                        <?php esc_html_e('← Back to login', 'formflow-pro'); ?>
                    </a>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const tabs = document.querySelectorAll('.formflow-2fa-tab');
                const sections = document.querySelectorAll('.formflow-2fa-section');
                const methodInput = document.getElementById('formflow-2fa-method');
                const errorDiv = document.getElementById('formflow-2fa-error');

                // Tab switching
                tabs.forEach(tab => {
                    tab.addEventListener('click', function() {
                        const tabName = this.dataset.tab;

                        tabs.forEach(t => t.classList.remove('active'));
                        sections.forEach(s => s.classList.remove('active'));

                        this.classList.add('active');
                        document.getElementById('section-' + tabName).classList.add('active');
                        methodInput.value = tabName;
                    });
                });

                // Code inputs handling (TOTP)
                const codeInputs = document.querySelectorAll('#section-totp .formflow-2fa-code-input');
                const totpHidden = document.getElementById('totp-code-hidden');

                function updateHiddenCode(inputs, hidden) {
                    let code = '';
                    inputs.forEach(input => code += input.value);
                    hidden.value = code;
                }

                codeInputs.forEach((input, index) => {
                    input.addEventListener('input', function(e) {
                        const value = this.value.replace(/[^0-9]/g, '');
                        this.value = value;

                        if (value && index < codeInputs.length - 1) {
                            codeInputs[index + 1].focus();
                        }

                        updateHiddenCode(codeInputs, totpHidden);

                        // Auto-submit when all 6 digits entered
                        if (totpHidden.value.length === 6) {
                            document.getElementById('formflow-2fa-form').dispatchEvent(new Event('submit'));
                        }
                    });

                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Backspace' && !this.value && index > 0) {
                            codeInputs[index - 1].focus();
                        }
                    });

                    input.addEventListener('paste', function(e) {
                        e.preventDefault();
                        const paste = (e.clipboardData || window.clipboardData).getData('text');
                        const digits = paste.replace(/[^0-9]/g, '').slice(0, 6);

                        digits.split('').forEach((digit, i) => {
                            if (codeInputs[i]) {
                                codeInputs[i].value = digit;
                            }
                        });

                        updateHiddenCode(codeInputs, totpHidden);

                        if (digits.length === 6) {
                            document.getElementById('formflow-2fa-form').dispatchEvent(new Event('submit'));
                        }
                    });
                });

                // Email code inputs
                const emailInputs = document.querySelectorAll('.formflow-2fa-email-input');
                const emailHidden = document.getElementById('email-code-hidden');

                emailInputs.forEach((input, index) => {
                    input.addEventListener('input', function(e) {
                        const value = this.value.replace(/[^0-9]/g, '');
                        this.value = value;

                        if (value && index < emailInputs.length - 1) {
                            emailInputs[index + 1].focus();
                        }

                        updateHiddenCode(emailInputs, emailHidden);
                    });

                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Backspace' && !this.value && index > 0) {
                            emailInputs[index - 1].focus();
                        }
                    });
                });

                // Send email code
                const resendBtn = document.getElementById('resend-email-code');
                let countdown = 0;

                resendBtn.addEventListener('click', function() {
                    if (countdown > 0) return;

                    this.disabled = true;

                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            action: 'formflow_send_2fa_email',
                            token: '<?php echo esc_js($token); ?>',
                            nonce: '<?php echo esc_js(wp_create_nonce('formflow_send_2fa_email')); ?>'
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            countdown = 60;
                            const interval = setInterval(() => {
                                countdown--;
                                resendBtn.textContent = countdown > 0
                                    ? `Resend in ${countdown}s`
                                    : '<?php esc_html_e('Resend code', 'formflow-pro'); ?>';
                                resendBtn.disabled = countdown > 0;
                                if (countdown <= 0) clearInterval(interval);
                            }, 1000);
                        } else {
                            errorDiv.textContent = data.data?.message || 'Error sending code';
                            errorDiv.style.display = 'block';
                            resendBtn.disabled = false;
                        }
                    });
                });

                // Form submission
                document.getElementById('formflow-2fa-form').addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    formData.append('action', 'formflow_verify_2fa');

                    const submitBtn = this.querySelector('.formflow-2fa-submit');
                    submitBtn.disabled = true;
                    submitBtn.textContent = '<?php esc_html_e('Verifying...', 'formflow-pro'); ?>';

                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = data.data.redirect || '<?php echo esc_url(admin_url()); ?>';
                        } else {
                            errorDiv.textContent = data.data?.message || '<?php esc_html_e('Invalid code', 'formflow-pro'); ?>';
                            errorDiv.style.display = 'block';
                            submitBtn.disabled = false;
                            submitBtn.textContent = '<?php esc_html_e('Verify', 'formflow-pro'); ?>';

                            // Clear inputs
                            document.querySelectorAll('.formflow-2fa-code-input').forEach(i => i.value = '');
                            codeInputs[0].focus();
                        }
                    })
                    .catch(err => {
                        errorDiv.textContent = '<?php esc_html_e('An error occurred', 'formflow-pro'); ?>';
                        errorDiv.style.display = 'block';
                        submitBtn.disabled = false;
                        submitBtn.textContent = '<?php esc_html_e('Verify', 'formflow-pro'); ?>';
                    });
                });

                // Focus first input
                codeInputs[0].focus();
            });
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * AJAX: Verify 2FA code
     */
    public function ajaxVerify2FA(): void
    {
        check_ajax_referer('formflow_verify_2fa', 'formflow_2fa_nonce');

        $token = sanitize_text_field($_POST['token'] ?? '');
        $method = sanitize_text_field($_POST['method'] ?? 'totp');
        $rememberDevice = !empty($_POST['remember_device']);

        $session = get_transient('formflow_2fa_session_' . $token);

        if (!$session) {
            wp_send_json_error(['message' => __('Session expired. Please log in again.', 'formflow-pro')]);
        }

        $userId = $session['user_id'];

        // Check rate limiting
        if ($this->isRateLimited($userId)) {
            wp_send_json_error(['message' => __('Too many attempts. Please try again later.', 'formflow-pro')]);
        }

        $verified = false;

        switch ($method) {
            case 'totp':
                $code = sanitize_text_field($_POST['totp_code'] ?? '');
                $settings = $this->get2FASettings($userId);
                if ($settings && $settings['secret_key']) {
                    $verified = $this->verifyTOTP($settings['secret_key'], $code);
                }
                break;

            case 'email':
                $code = sanitize_text_field($_POST['email_code'] ?? '');
                $verified = $this->verifyEmail2FACode($userId, $code);
                break;

            case 'backup':
                $code = sanitize_text_field($_POST['backup_code'] ?? '');
                $verified = $this->verifyBackupCode($userId, $code);
                break;
        }

        if (!$verified) {
            $this->logAttempt($userId, '2fa', false, $method);
            wp_send_json_error(['message' => __('Invalid verification code.', 'formflow-pro')]);
        }

        // Success - log in the user
        delete_transient('formflow_2fa_session_' . $token);

        $user = get_userdata($userId);
        wp_set_current_user($userId);
        wp_set_auth_cookie($userId, true);

        // Remember device if requested
        if ($rememberDevice) {
            $this->rememberDevice($userId);
        }

        $this->logAttempt($userId, '2fa', true, $method);

        // Update last used
        global $wpdb;
        $wpdb->update(
            $this->tableUsers2FA,
            ['last_used' => current_time('mysql')],
            ['user_id' => $userId],
            ['%s'],
            ['%d']
        );

        do_action('formflow_2fa_verified', $userId, $method);

        wp_send_json_success([
            'redirect' => admin_url(),
            'message' => __('Verification successful!', 'formflow-pro'),
        ]);
    }

    /**
     * AJAX: Setup 2FA
     */
    public function ajaxSetup2FA(): void
    {
        check_ajax_referer('formflow_security_nonce', 'nonce');

        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $userId = get_current_user_id();
        $secret = $this->generateSecretKey();

        // Store temporarily
        set_transient('formflow_2fa_setup_' . $userId, $secret, 600);

        wp_send_json_success([
            'secret' => $secret,
            'qr_code' => $this->getQRCodeUrl($userId, $secret),
            'manual_entry' => chunk_split($secret, 4, ' '),
        ]);
    }

    /**
     * AJAX: Verify 2FA setup
     */
    public function ajaxVerify2FASetup(): void
    {
        check_ajax_referer('formflow_security_nonce', 'nonce');

        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $userId = get_current_user_id();
        $code = sanitize_text_field($_POST['code'] ?? '');

        $secret = get_transient('formflow_2fa_setup_' . $userId);

        if (!$secret) {
            wp_send_json_error(['message' => __('Setup session expired. Please start again.', 'formflow-pro')]);
        }

        if (!$this->verifyTOTP($secret, $code)) {
            wp_send_json_error(['message' => __('Invalid code. Please try again.', 'formflow-pro')]);
        }

        // Enable 2FA
        $this->enable2FA($userId, $secret);

        // Generate backup codes
        $backupCodes = $this->generateBackupCodes($userId);

        // Cleanup
        delete_transient('formflow_2fa_setup_' . $userId);

        do_action('formflow_2fa_enabled', $userId);

        wp_send_json_success([
            'message' => __('Two-factor authentication enabled successfully!', 'formflow-pro'),
            'backup_codes' => $backupCodes,
        ]);
    }

    /**
     * AJAX: Disable 2FA
     */
    public function ajaxDisable2FA(): void
    {
        check_ajax_referer('formflow_security_nonce', 'nonce');

        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $userId = get_current_user_id();
        $password = $_POST['password'] ?? '';

        // Verify password
        $user = get_userdata($userId);
        if (!wp_check_password($password, $user->user_pass, $userId)) {
            wp_send_json_error(['message' => __('Invalid password.', 'formflow-pro')]);
        }

        $this->disable2FA($userId);

        do_action('formflow_2fa_disabled', $userId);

        wp_send_json_success([
            'message' => __('Two-factor authentication disabled.', 'formflow-pro'),
        ]);
    }

    /**
     * AJAX: Regenerate backup codes
     */
    public function ajaxRegenerateBackupCodes(): void
    {
        check_ajax_referer('formflow_security_nonce', 'nonce');

        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $userId = get_current_user_id();
        $password = $_POST['password'] ?? '';

        // Verify password
        $user = get_userdata($userId);
        if (!wp_check_password($password, $user->user_pass, $userId)) {
            wp_send_json_error(['message' => __('Invalid password.', 'formflow-pro')]);
        }

        $backupCodes = $this->generateBackupCodes($userId);

        wp_send_json_success([
            'message' => __('New backup codes generated.', 'formflow-pro'),
            'backup_codes' => $backupCodes,
        ]);
    }

    /**
     * AJAX: Revoke device
     */
    public function ajaxRevokeDevice(): void
    {
        check_ajax_referer('formflow_security_nonce', 'nonce');

        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $userId = get_current_user_id();
        $deviceId = (int) ($_POST['device_id'] ?? 0);

        if ($this->revokeDevice($userId, $deviceId)) {
            wp_send_json_success(['message' => __('Device removed.', 'formflow-pro')]);
        }

        wp_send_json_error(['message' => __('Failed to remove device.', 'formflow-pro')]);
    }

    /**
     * Get 2FA statistics
     */
    public function getStatistics(): array
    {
        global $wpdb;

        $stats = [
            'users_with_2fa' => 0,
            'total_devices' => 0,
            'total_security_keys' => 0,
            'attempts_today' => 0,
            'failed_attempts_today' => 0,
            'method_distribution' => [],
        ];

        $stats['users_with_2fa'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tableUsers2FA} WHERE is_enabled = 1"
        );

        $stats['total_devices'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tableDevices} WHERE expires_at > NOW()"
        );

        $stats['total_security_keys'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tableSecurityKeys}"
        );

        $today = date('Y-m-d');
        $stats['attempts_today'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tableAttempts} WHERE DATE(attempted_at) = %s",
                $today
            )
        );

        $stats['failed_attempts_today'] = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tableAttempts}
                WHERE DATE(attempted_at) = %s AND is_successful = 0",
                $today
            )
        );

        $methods = $wpdb->get_results(
            "SELECT method, COUNT(*) as count FROM {$this->tableUsers2FA}
            WHERE is_enabled = 1 GROUP BY method"
        );

        foreach ($methods as $method) {
            $stats['method_distribution'][$method->method] = (int) $method->count;
        }

        return $stats;
    }

    /**
     * Cleanup expired data
     */
    public function cleanup(): void
    {
        global $wpdb;

        // Delete expired devices
        $wpdb->query("DELETE FROM {$this->tableDevices} WHERE expires_at < NOW()");

        // Delete old attempts (keep 30 days)
        $wpdb->query(
            "DELETE FROM {$this->tableAttempts} WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }
}
