<?php

declare(strict_types=1);

namespace FormFlowPro\Security;

/**
 * Access Control Manager
 *
 * Comprehensive access control system including:
 * - IP-based restrictions (whitelist/blacklist)
 * - Geo-blocking by country
 * - Session management
 * - Concurrent session limits
 * - Session hijacking prevention
 * - Brute force protection
 * - Login attempt monitoring
 * - Device fingerprinting
 * - Suspicious activity detection
 *
 * @package FormFlowPro\Security
 * @since 2.4.0
 */
class AccessControl
{
    private static ?AccessControl $instance = null;

    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 1800; // 30 minutes
    private const SESSION_LIFETIME = 86400; // 24 hours
    private const MAX_CONCURRENT_SESSIONS = 3;
    private const SUSPICIOUS_THRESHOLD = 10;

    private string $tableIPRules;
    private string $tableSessions;
    private string $tableLoginAttempts;
    private string $tableBlockedIPs;
    private string $tableGeoRules;

    private function __construct()
    {
        global $wpdb;
        $this->tableIPRules = $wpdb->prefix . 'formflow_ip_rules';
        $this->tableSessions = $wpdb->prefix . 'formflow_sessions';
        $this->tableLoginAttempts = $wpdb->prefix . 'formflow_login_attempts';
        $this->tableBlockedIPs = $wpdb->prefix . 'formflow_blocked_ips';
        $this->tableGeoRules = $wpdb->prefix . 'formflow_geo_rules';

        $this->initHooks();
    }

    public static function getInstance(): AccessControl
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initHooks(): void
    {
        // IP checking on every request
        add_action('init', [$this, 'checkIPAccess'], 1);

        // Login hooks
        add_filter('authenticate', [$this, 'checkLoginAccess'], 10, 3);
        add_action('wp_login', [$this, 'handleSuccessfulLogin'], 10, 2);
        add_action('wp_login_failed', [$this, 'handleFailedLogin']);
        add_action('wp_logout', [$this, 'handleLogout']);

        // Session management
        add_action('init', [$this, 'validateSession'], 2);
        add_action('set_logged_in_cookie', [$this, 'createSession'], 10, 6);

        // Cron jobs
        add_action('formflow_cleanup_sessions', [$this, 'cleanupExpiredSessions']);
        add_action('formflow_cleanup_attempts', [$this, 'cleanupOldAttempts']);
        add_action('formflow_unblock_ips', [$this, 'unblockExpiredIPs']);

        if (!wp_next_scheduled('formflow_cleanup_sessions')) {
            wp_schedule_event(time(), 'hourly', 'formflow_cleanup_sessions');
        }
        if (!wp_next_scheduled('formflow_cleanup_attempts')) {
            wp_schedule_event(time(), 'daily', 'formflow_cleanup_attempts');
        }
        if (!wp_next_scheduled('formflow_unblock_ips')) {
            wp_schedule_event(time(), 'hourly', 'formflow_unblock_ips');
        }

        // AJAX handlers
        add_action('wp_ajax_formflow_add_ip_rule', [$this, 'ajaxAddIPRule']);
        add_action('wp_ajax_formflow_remove_ip_rule', [$this, 'ajaxRemoveIPRule']);
        add_action('wp_ajax_formflow_terminate_session', [$this, 'ajaxTerminateSession']);
        add_action('wp_ajax_formflow_terminate_all_sessions', [$this, 'ajaxTerminateAllSessions']);
        add_action('wp_ajax_formflow_block_ip', [$this, 'ajaxBlockIP']);
        add_action('wp_ajax_formflow_unblock_ip', [$this, 'ajaxUnblockIP']);
    }

    /**
     * Create database tables
     */
    public function createTables(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [];

        // IP Rules (whitelist/blacklist)
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableIPRules} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            ip_range_start VARCHAR(45) NULL,
            ip_range_end VARCHAR(45) NULL,
            cidr VARCHAR(50) NULL,
            rule_type ENUM('whitelist', 'blacklist') NOT NULL,
            scope ENUM('all', 'admin', 'api', 'forms') DEFAULT 'all',
            description VARCHAR(255) NULL,
            is_active TINYINT(1) DEFAULT 1,
            expires_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_ip (ip_address),
            INDEX idx_type (rule_type),
            INDEX idx_active (is_active),
            INDEX idx_scope (scope)
        ) {$charset};";

        // User Sessions
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableSessions} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_token VARCHAR(64) NOT NULL UNIQUE,
            user_id BIGINT UNSIGNED NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT NULL,
            device_fingerprint VARCHAR(64) NULL,
            device_type VARCHAR(50) NULL,
            browser VARCHAR(100) NULL,
            os VARCHAR(100) NULL,
            location VARCHAR(255) NULL,
            country_code VARCHAR(2) NULL,
            is_current TINYINT(1) DEFAULT 0,
            last_activity DATETIME NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_token (session_token),
            INDEX idx_ip (ip_address),
            INDEX idx_expires (expires_at),
            INDEX idx_activity (last_activity)
        ) {$charset};";

        // Login Attempts
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableLoginAttempts} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(255) NULL,
            user_id BIGINT UNSIGNED NULL,
            is_successful TINYINT(1) DEFAULT 0,
            failure_reason VARCHAR(100) NULL,
            user_agent TEXT NULL,
            attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip (ip_address),
            INDEX idx_username (username),
            INDEX idx_time (attempted_at),
            INDEX idx_success (is_successful)
        ) {$charset};";

        // Blocked IPs (temporary blocks from brute force)
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableBlockedIPs} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL UNIQUE,
            reason VARCHAR(255) NULL,
            attempts_count INT UNSIGNED DEFAULT 0,
            blocked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            INDEX idx_ip (ip_address),
            INDEX idx_expires (expires_at)
        ) {$charset};";

        // Geo Rules (country blocking)
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableGeoRules} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            country_code VARCHAR(2) NOT NULL,
            country_name VARCHAR(100) NULL,
            rule_type ENUM('allow', 'block') NOT NULL,
            scope ENUM('all', 'admin', 'api', 'forms') DEFAULT 'all',
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_country_scope (country_code, scope),
            INDEX idx_type (rule_type),
            INDEX idx_active (is_active)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    /**
     * Check IP access on every request
     */
    public function checkIPAccess(): void
    {
        // Skip for cron/CLI
        if (wp_doing_cron() || php_sapi_name() === 'cli') {
            return;
        }

        $ip = $this->getClientIP();
        $scope = $this->getCurrentScope();

        // Check if IP is blocked
        if ($this->isIPBlocked($ip)) {
            $this->denyAccess(__('Your IP address has been temporarily blocked.', 'formflow-pro'));
        }

        // Check IP rules
        if (!$this->isIPAllowed($ip, $scope)) {
            $this->denyAccess(__('Access denied from your IP address.', 'formflow-pro'));
        }

        // Check geo rules
        if (!$this->isGeoAllowed($ip, $scope)) {
            $this->denyAccess(__('Access is not available in your region.', 'formflow-pro'));
        }
    }

    /**
     * Check login access
     */
    public function checkLoginAccess($user, string $username, string $password)
    {
        if (empty($username)) {
            return $user;
        }

        $ip = $this->getClientIP();

        // Check if IP is blocked
        if ($this->isIPBlocked($ip)) {
            return new \WP_Error(
                'ip_blocked',
                sprintf(
                    __('Too many failed login attempts. Please try again in %d minutes.', 'formflow-pro'),
                    ceil(self::LOCKOUT_DURATION / 60)
                )
            );
        }

        // Check login attempts
        $attempts = $this->getRecentAttempts($ip);
        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $this->blockIP($ip, 'Too many failed login attempts', self::LOCKOUT_DURATION);

            return new \WP_Error(
                'too_many_attempts',
                sprintf(
                    __('Too many failed login attempts. Please try again in %d minutes.', 'formflow-pro'),
                    ceil(self::LOCKOUT_DURATION / 60)
                )
            );
        }

        return $user;
    }

    /**
     * Handle successful login
     */
    public function handleSuccessfulLogin(string $userLogin, \WP_User $user): void
    {
        $ip = $this->getClientIP();

        // Record successful attempt
        $this->recordLoginAttempt($ip, $userLogin, $user->ID, true);

        // Clear any blocks for this IP
        $this->unblockIP($ip);

        // Check concurrent sessions
        $this->enforceConcurrentSessionLimit($user->ID);

        // Log to audit
        do_action('formflow_audit_login', [
            'user_id' => $user->ID,
            'ip' => $ip,
            'success' => true,
        ]);
    }

    /**
     * Handle failed login
     */
    public function handleFailedLogin(string $username): void
    {
        $ip = $this->getClientIP();

        // Record failed attempt
        $this->recordLoginAttempt($ip, $username, null, false);

        // Check if should block
        $attempts = $this->getRecentAttempts($ip);
        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $this->blockIP($ip, 'Failed login attempts threshold exceeded', self::LOCKOUT_DURATION);
        }

        // Detect suspicious patterns
        $this->detectSuspiciousActivity($ip, $username);

        // Log to audit
        do_action('formflow_audit_login', [
            'username' => $username,
            'ip' => $ip,
            'success' => false,
        ]);
    }

    /**
     * Handle logout
     */
    public function handleLogout(): void
    {
        $userId = get_current_user_id();

        if ($userId && isset($_COOKIE['formflow_session_token'])) {
            $this->terminateSession(
                sanitize_text_field($_COOKIE['formflow_session_token'])
            );
        }
    }

    /**
     * Create session on login
     */
    public function createSession(
        string $loggedInCookie,
        int $expire,
        int $expiration,
        int $userId,
        string $scheme,
        string $token
    ): void {
        global $wpdb;

        $sessionToken = bin2hex(random_bytes(32));
        $ip = $this->getClientIP();
        $deviceInfo = $this->getDeviceInfo();
        $location = $this->getLocationFromIP($ip);

        $wpdb->insert(
            $this->tableSessions,
            [
                'session_token' => $sessionToken,
                'user_id' => $userId,
                'ip_address' => $ip,
                'user_agent' => $deviceInfo['user_agent'],
                'device_fingerprint' => $this->generateDeviceFingerprint(),
                'device_type' => $deviceInfo['device_type'],
                'browser' => $deviceInfo['browser'],
                'os' => $deviceInfo['os'],
                'location' => $location['location'],
                'country_code' => $location['country_code'],
                'is_current' => 1,
                'last_activity' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', time() + self::SESSION_LIFETIME),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        // Set session cookie
        setcookie(
            'formflow_session_token',
            $sessionToken,
            [
                'expires' => time() + self::SESSION_LIFETIME,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * Validate session
     */
    public function validateSession(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $userId = get_current_user_id();

        if (!isset($_COOKIE['formflow_session_token'])) {
            return;
        }

        $sessionToken = sanitize_text_field($_COOKIE['formflow_session_token']);
        $session = $this->getSession($sessionToken);

        if (!$session) {
            // Session not found - could be hijacked
            wp_logout();
            wp_safe_redirect(wp_login_url());
            exit;
        }

        // Check if session belongs to current user
        if ((int) $session['user_id'] !== $userId) {
            wp_logout();
            wp_safe_redirect(wp_login_url());
            exit;
        }

        // Check if session is expired
        if (strtotime($session['expires_at']) < time()) {
            $this->terminateSession($sessionToken);
            wp_logout();
            wp_safe_redirect(wp_login_url());
            exit;
        }

        // Check for IP change (potential hijacking)
        $currentIP = $this->getClientIP();
        if ($session['ip_address'] !== $currentIP) {
            $strictMode = get_option('formflow_session_ip_strict', false);

            if ($strictMode) {
                // Strict mode: terminate session on IP change
                $this->terminateSession($sessionToken);
                wp_logout();
                wp_safe_redirect(wp_login_url());
                exit;
            } else {
                // Log warning for potential hijacking
                do_action('formflow_session_ip_changed', [
                    'user_id' => $userId,
                    'original_ip' => $session['ip_address'],
                    'new_ip' => $currentIP,
                    'session_token' => $sessionToken,
                ]);
            }
        }

        // Update last activity
        $this->updateSessionActivity($sessionToken);
    }

    /**
     * Enforce concurrent session limit
     */
    private function enforceConcurrentSessionLimit(int $userId): void
    {
        global $wpdb;

        $maxSessions = (int) get_option('formflow_max_sessions', self::MAX_CONCURRENT_SESSIONS);

        $sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, session_token FROM {$this->tableSessions}
                WHERE user_id = %d AND expires_at > NOW()
                ORDER BY last_activity DESC",
                $userId
            ),
            ARRAY_A
        );

        // Keep only the most recent sessions
        if (count($sessions) >= $maxSessions) {
            $toTerminate = array_slice($sessions, $maxSessions - 1);
            foreach ($toTerminate as $session) {
                $this->terminateSession($session['session_token']);
            }
        }
    }

    /**
     * Add IP rule
     */
    public function addIPRule(array $data): bool
    {
        global $wpdb;

        $ip = sanitize_text_field($data['ip_address']);

        // Validate IP
        if (!$this->isValidIP($ip) && !$this->isValidCIDR($ip)) {
            return false;
        }

        // Parse CIDR if provided
        $cidr = null;
        $rangeStart = null;
        $rangeEnd = null;

        if (strpos($ip, '/') !== false) {
            $cidr = $ip;
            $range = $this->cidrToRange($ip);
            $ip = $range['start'];
            $rangeStart = $range['start'];
            $rangeEnd = $range['end'];
        }

        return (bool) $wpdb->insert(
            $this->tableIPRules,
            [
                'ip_address' => $ip,
                'ip_range_start' => $rangeStart,
                'ip_range_end' => $rangeEnd,
                'cidr' => $cidr,
                'rule_type' => $data['rule_type'] ?? 'whitelist',
                'scope' => $data['scope'] ?? 'all',
                'description' => sanitize_text_field($data['description'] ?? ''),
                'is_active' => 1,
                'expires_at' => $data['expires_at'] ?? null,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s']
        );
    }

    /**
     * Remove IP rule
     */
    public function removeIPRule(int $ruleId): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete(
            $this->tableIPRules,
            ['id' => $ruleId],
            ['%d']
        );
    }

    /**
     * Check if IP is allowed (with caching)
     */
    public function isIPAllowed(string $ip, string $scope = 'all'): bool
    {
        $cacheKey = 'ffp_ip_allowed_' . md5($ip . '_' . $scope);
        $cached = wp_cache_get($cacheKey, 'formflow_security');

        if ($cached !== false) {
            return (bool) $cached;
        }

        global $wpdb;

        // Check for active whitelist rules
        $hasWhitelist = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tableIPRules}
                WHERE rule_type = 'whitelist'
                AND is_active = 1
                AND (scope = 'all' OR scope = %s)
                AND (expires_at IS NULL OR expires_at > NOW())",
                $scope
            )
        );

        $isAllowed = true;

        if ($hasWhitelist) {
            // If whitelist exists, IP must be in it
            $inWhitelist = $this->isIPInRules($ip, 'whitelist', $scope);
            if (!$inWhitelist) {
                $isAllowed = false;
            }
        }

        // Check blacklist
        if ($isAllowed && $this->isIPInRules($ip, 'blacklist', $scope)) {
            $isAllowed = false;
        }

        // Cache for 5 minutes
        wp_cache_set($cacheKey, $isAllowed ? 1 : 0, 'formflow_security', 300);

        return $isAllowed;
    }

    /**
     * Check if IP is in rules
     */
    private function isIPInRules(string $ip, string $ruleType, string $scope): bool
    {
        global $wpdb;

        // Direct IP match
        $directMatch = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tableIPRules}
                WHERE ip_address = %s
                AND rule_type = %s
                AND is_active = 1
                AND (scope = 'all' OR scope = %s)
                AND (expires_at IS NULL OR expires_at > NOW())",
                $ip,
                $ruleType,
                $scope
            )
        );

        if ($directMatch) {
            return true;
        }

        // Range match
        $ipLong = ip2long($ip);
        if ($ipLong !== false) {
            $rangeMatch = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->tableIPRules}
                    WHERE ip_range_start IS NOT NULL
                    AND ip_range_end IS NOT NULL
                    AND INET_ATON(%s) BETWEEN INET_ATON(ip_range_start) AND INET_ATON(ip_range_end)
                    AND rule_type = %s
                    AND is_active = 1
                    AND (scope = 'all' OR scope = %s)
                    AND (expires_at IS NULL OR expires_at > NOW())",
                    $ip,
                    $ruleType,
                    $scope
                )
            );

            if ($rangeMatch) {
                return true;
            }
        }

        return false;
    }

    /**
     * Block IP temporarily
     */
    public function blockIP(string $ip, string $reason, ?int $duration = null): bool
    {
        global $wpdb;

        $duration = $duration ?? self::LOCKOUT_DURATION;
        $expiresAt = date('Y-m-d H:i:s', time() + $duration);

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, attempts_count FROM {$this->tableBlockedIPs} WHERE ip_address = %s",
                $ip
            )
        );

        if ($existing) {
            return (bool) $wpdb->update(
                $this->tableBlockedIPs,
                [
                    'reason' => $reason,
                    'attempts_count' => $existing->attempts_count + 1,
                    'blocked_at' => current_time('mysql'),
                    'expires_at' => $expiresAt,
                ],
                ['id' => $existing->id],
                ['%s', '%d', '%s', '%s'],
                ['%d']
            );
        }

        return (bool) $wpdb->insert(
            $this->tableBlockedIPs,
            [
                'ip_address' => $ip,
                'reason' => $reason,
                'attempts_count' => 1,
                'blocked_at' => current_time('mysql'),
                'expires_at' => $expiresAt,
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );
    }

    /**
     * Unblock IP
     */
    public function unblockIP(string $ip): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete(
            $this->tableBlockedIPs,
            ['ip_address' => $ip],
            ['%s']
        );
    }

    /**
     * Check if IP is blocked
     */
    public function isIPBlocked(string $ip): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tableBlockedIPs}
                WHERE ip_address = %s AND expires_at > NOW()",
                $ip
            )
        );
    }

    /**
     * Add geo rule
     */
    public function addGeoRule(string $countryCode, string $ruleType, string $scope = 'all'): bool
    {
        global $wpdb;

        $countries = $this->getCountryList();
        $countryName = $countries[strtoupper($countryCode)] ?? $countryCode;

        return (bool) $wpdb->replace(
            $this->tableGeoRules,
            [
                'country_code' => strtoupper($countryCode),
                'country_name' => $countryName,
                'rule_type' => $ruleType,
                'scope' => $scope,
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s']
        );
    }

    /**
     * Remove geo rule
     */
    public function removeGeoRule(string $countryCode, string $scope = 'all'): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete(
            $this->tableGeoRules,
            [
                'country_code' => strtoupper($countryCode),
                'scope' => $scope,
            ],
            ['%s', '%s']
        );
    }

    /**
     * Check if geo is allowed
     */
    public function isGeoAllowed(string $ip, string $scope = 'all'): bool
    {
        global $wpdb;

        // Check if geo blocking is enabled
        if (!get_option('formflow_geo_blocking_enabled', false)) {
            return true;
        }

        $countryCode = $this->getCountryFromIP($ip);
        if (!$countryCode) {
            // Can't determine country - allow by default
            return true;
        }

        // Check for block rule
        $blocked = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tableGeoRules}
                WHERE country_code = %s
                AND rule_type = 'block'
                AND is_active = 1
                AND (scope = 'all' OR scope = %s)",
                $countryCode,
                $scope
            )
        );

        if ($blocked) {
            return false;
        }

        // Check if allow mode is enabled (only allow listed countries)
        $allowMode = get_option('formflow_geo_allow_mode', false);
        if ($allowMode) {
            $allowed = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->tableGeoRules}
                    WHERE country_code = %s
                    AND rule_type = 'allow'
                    AND is_active = 1
                    AND (scope = 'all' OR scope = %s)",
                    $countryCode,
                    $scope
                )
            );

            return (bool) $allowed;
        }

        return true;
    }

    /**
     * Get user sessions
     */
    public function getUserSessions(int $userId): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableSessions}
                WHERE user_id = %d AND expires_at > NOW()
                ORDER BY last_activity DESC",
                $userId
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get session by token
     */
    public function getSession(string $token): ?array
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableSessions} WHERE session_token = %s",
                $token
            ),
            ARRAY_A
        );
    }

    /**
     * Terminate session
     */
    public function terminateSession(string $sessionToken): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete(
            $this->tableSessions,
            ['session_token' => $sessionToken],
            ['%s']
        );
    }

    /**
     * Terminate all user sessions
     */
    public function terminateAllUserSessions(int $userId, ?string $exceptToken = null): int
    {
        global $wpdb;

        if ($exceptToken) {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$this->tableSessions}
                    WHERE user_id = %d AND session_token != %s",
                    $userId,
                    $exceptToken
                )
            );
            return is_int($result) ? $result : 0;
        }

        $result = $wpdb->delete(
            $this->tableSessions,
            ['user_id' => $userId],
            ['%d']
        );
        return is_int($result) ? $result : 0;
    }

    /**
     * Update session activity
     */
    private function updateSessionActivity(string $sessionToken): void
    {
        global $wpdb;

        $wpdb->update(
            $this->tableSessions,
            ['last_activity' => current_time('mysql')],
            ['session_token' => $sessionToken],
            ['%s'],
            ['%s']
        );
    }

    /**
     * Record login attempt
     */
    private function recordLoginAttempt(
        string $ip,
        string $username,
        ?int $userId,
        bool $successful,
        string $failureReason = ''
    ): void {
        global $wpdb;

        $wpdb->insert(
            $this->tableLoginAttempts,
            [
                'ip_address' => $ip,
                'username' => $username,
                'user_id' => $userId,
                'is_successful' => $successful ? 1 : 0,
                'failure_reason' => $failureReason,
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'attempted_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * Get recent failed attempts count
     */
    private function getRecentAttempts(string $ip): int
    {
        global $wpdb;

        $since = date('Y-m-d H:i:s', time() - self::LOCKOUT_DURATION);

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tableLoginAttempts}
                WHERE ip_address = %s
                AND is_successful = 0
                AND attempted_at > %s",
                $ip,
                $since
            )
        );
    }

    /**
     * Detect suspicious activity
     */
    private function detectSuspiciousActivity(string $ip, string $username): void
    {
        global $wpdb;

        // Check for multiple usernames from same IP
        $usernames = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT username) FROM {$this->tableLoginAttempts}
                WHERE ip_address = %s
                AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                $ip
            )
        );

        if ($usernames >= self::SUSPICIOUS_THRESHOLD) {
            // Potential credential stuffing attack
            $this->blockIP($ip, 'Suspected credential stuffing attack', 3600);

            do_action('formflow_suspicious_activity', [
                'type' => 'credential_stuffing',
                'ip' => $ip,
                'usernames_tried' => $usernames,
            ]);
        }

        // Check for distributed attack on same username
        $ips = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT ip_address) FROM {$this->tableLoginAttempts}
                WHERE username = %s
                AND is_successful = 0
                AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                $username
            )
        );

        if ($ips >= self::SUSPICIOUS_THRESHOLD) {
            do_action('formflow_suspicious_activity', [
                'type' => 'distributed_attack',
                'username' => $username,
                'ips_count' => $ips,
            ]);
        }
    }

    /**
     * Cleanup expired sessions
     */
    public function cleanupExpiredSessions(): void
    {
        global $wpdb;

        $wpdb->query("DELETE FROM {$this->tableSessions} WHERE expires_at < NOW()");
    }

    /**
     * Cleanup old login attempts
     */
    public function cleanupOldAttempts(): void
    {
        global $wpdb;

        // Keep 30 days of attempts
        $wpdb->query(
            "DELETE FROM {$this->tableLoginAttempts}
            WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }

    /**
     * Unblock expired IPs
     */
    public function unblockExpiredIPs(): void
    {
        global $wpdb;

        $wpdb->query("DELETE FROM {$this->tableBlockedIPs} WHERE expires_at < NOW()");
    }

    /**
     * Get IP rules
     */
    public function getIPRules(?string $ruleType = null): array
    {
        global $wpdb;

        $where = '1=1';
        $params = [];

        if ($ruleType) {
            $where .= ' AND rule_type = %s';
            $params[] = $ruleType;
        }

        $query = "SELECT * FROM {$this->tableIPRules} WHERE {$where} ORDER BY created_at DESC";

        if ($params) {
            return $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A) ?: [];
        }

        return $wpdb->get_results($query, ARRAY_A) ?: [];
    }

    /**
     * Get blocked IPs
     */
    public function getBlockedIPs(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->tableBlockedIPs}
            WHERE expires_at > NOW()
            ORDER BY blocked_at DESC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get geo rules
     */
    public function getGeoRules(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->tableGeoRules} ORDER BY country_name ASC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get login attempts
     */
    public function getLoginAttempts(array $filters = [], int $limit = 100): array
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['ip_address'])) {
            $where[] = 'ip_address = %s';
            $params[] = $filters['ip_address'];
        }

        if (!empty($filters['username'])) {
            $where[] = 'username = %s';
            $params[] = $filters['username'];
        }

        if (isset($filters['is_successful'])) {
            $where[] = 'is_successful = %d';
            $params[] = $filters['is_successful'] ? 1 : 0;
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableLoginAttempts}
                WHERE {$whereClause}
                ORDER BY attempted_at DESC
                LIMIT %d",
                ...$params
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        global $wpdb;

        return [
            'active_sessions' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableSessions} WHERE expires_at > NOW()"
            ),
            'blocked_ips' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableBlockedIPs} WHERE expires_at > NOW()"
            ),
            'whitelist_rules' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableIPRules} WHERE rule_type = 'whitelist' AND is_active = 1"
            ),
            'blacklist_rules' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableIPRules} WHERE rule_type = 'blacklist' AND is_active = 1"
            ),
            'login_attempts_today' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableLoginAttempts} WHERE DATE(attempted_at) = CURDATE()"
            ),
            'failed_attempts_today' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableLoginAttempts}
                WHERE DATE(attempted_at) = CURDATE() AND is_successful = 0"
            ),
            'geo_rules' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableGeoRules} WHERE is_active = 1"
            ),
        ];
    }

    /**
     * AJAX handlers
     */
    public function ajaxAddIPRule(): void
    {
        check_ajax_referer('formflow_security_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $result = $this->addIPRule([
            'ip_address' => sanitize_text_field($_POST['ip_address'] ?? ''),
            'rule_type' => sanitize_text_field($_POST['rule_type'] ?? 'whitelist'),
            'scope' => sanitize_text_field($_POST['scope'] ?? 'all'),
            'description' => sanitize_text_field($_POST['description'] ?? ''),
        ]);

        if ($result) {
            wp_send_json_success(['message' => __('IP rule added', 'formflow-pro')]);
        }

        wp_send_json_error(['message' => __('Failed to add IP rule', 'formflow-pro')]);
    }

    public function ajaxRemoveIPRule(): void
    {
        check_ajax_referer('formflow_security_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $ruleId = (int) ($_POST['rule_id'] ?? 0);

        if ($this->removeIPRule($ruleId)) {
            wp_send_json_success(['message' => __('IP rule removed', 'formflow-pro')]);
        }

        wp_send_json_error(['message' => __('Failed to remove IP rule', 'formflow-pro')]);
    }

    public function ajaxTerminateSession(): void
    {
        check_ajax_referer('formflow_security_nonce', 'nonce');

        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $sessionToken = sanitize_text_field($_POST['session_token'] ?? '');

        // Verify session belongs to current user or user is admin
        $session = $this->getSession($sessionToken);
        if (!$session) {
            wp_send_json_error(['message' => __('Session not found', 'formflow-pro')]);
        }

        if ((int) $session['user_id'] !== get_current_user_id() && !current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        if ($this->terminateSession($sessionToken)) {
            wp_send_json_success(['message' => __('Session terminated', 'formflow-pro')]);
        }

        wp_send_json_error(['message' => __('Failed to terminate session', 'formflow-pro')]);
    }

    public function ajaxTerminateAllSessions(): void
    {
        check_ajax_referer('formflow_security_nonce', 'nonce');

        if (!current_user_can('read')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $userId = get_current_user_id();
        $currentToken = sanitize_text_field($_COOKIE['formflow_session_token'] ?? '');

        $count = $this->terminateAllUserSessions($userId, $currentToken);

        wp_send_json_success([
            'message' => sprintf(__('%d sessions terminated', 'formflow-pro'), $count),
        ]);
    }

    public function ajaxBlockIP(): void
    {
        check_ajax_referer('formflow_security_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $ip = sanitize_text_field($_POST['ip_address'] ?? '');
        $reason = sanitize_text_field($_POST['reason'] ?? 'Manually blocked');
        $duration = (int) ($_POST['duration'] ?? 3600);

        if ($this->blockIP($ip, $reason, $duration)) {
            wp_send_json_success(['message' => __('IP blocked', 'formflow-pro')]);
        }

        wp_send_json_error(['message' => __('Failed to block IP', 'formflow-pro')]);
    }

    public function ajaxUnblockIP(): void
    {
        check_ajax_referer('formflow_security_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $ip = sanitize_text_field($_POST['ip_address'] ?? '');

        if ($this->unblockIP($ip)) {
            wp_send_json_success(['message' => __('IP unblocked', 'formflow-pro')]);
        }

        wp_send_json_error(['message' => __('Failed to unblock IP', 'formflow-pro')]);
    }

    /**
     * Helper methods
     */
    private function denyAccess(string $message): void
    {
        status_header(403);
        wp_die(
            $message,
            __('Access Denied', 'formflow-pro'),
            ['response' => 403]
        );
    }

    private function getClientIP(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field($_SERVER[$header]);
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

    private function getCurrentScope(): string
    {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return 'admin';
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return 'api';
        }

        return 'all';
    }

    private function isValidIP(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    private function isValidCIDR(string $cidr): bool
    {
        if (strpos($cidr, '/') === false) {
            return false;
        }

        list($ip, $mask) = explode('/', $cidr);

        return $this->isValidIP($ip) && is_numeric($mask) && $mask >= 0 && $mask <= 32;
    }

    private function cidrToRange(string $cidr): array
    {
        list($ip, $mask) = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $maskLong = ~((1 << (32 - (int) $mask)) - 1);

        $start = long2ip($ipLong & $maskLong);
        $end = long2ip($ipLong | ~$maskLong);

        return ['start' => $start, 'end' => $end];
    }

    private function getDeviceInfo(): array
    {
        $userAgent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $browser = 'Unknown';
        $os = 'Unknown';
        $deviceType = 'desktop';

        if (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $m)) {
            $browser = 'Firefox ' . $m[1];
        } elseif (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $m)) {
            $browser = 'Chrome ' . $m[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent, $m)) {
            $browser = 'Safari ' . $m[1];
        } elseif (preg_match('/Edge\/([0-9.]+)/', $userAgent, $m)) {
            $browser = 'Edge ' . $m[1];
        }

        if (preg_match('/Windows NT ([0-9.]+)/', $userAgent, $m)) {
            $versions = ['10.0' => '10', '6.3' => '8.1', '6.2' => '8', '6.1' => '7'];
            $os = 'Windows ' . ($versions[$m[1]] ?? $m[1]);
        } elseif (preg_match('/Mac OS X ([0-9_.]+)/', $userAgent, $m)) {
            $os = 'macOS ' . str_replace('_', '.', $m[1]);
        } elseif (preg_match('/Linux/', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android ([0-9.]+)/', $userAgent, $m)) {
            $os = 'Android ' . $m[1];
            $deviceType = 'mobile';
        } elseif (preg_match('/iPhone OS ([0-9_]+)/', $userAgent, $m)) {
            $os = 'iOS ' . str_replace('_', '.', $m[1]);
            $deviceType = 'mobile';
        }

        return [
            'browser' => $browser,
            'os' => $os,
            'device_type' => $deviceType,
            'user_agent' => substr($userAgent, 0, 500),
        ];
    }

    private function generateDeviceFingerprint(): string
    {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
        ];

        return hash('sha256', implode('|', $components));
    }

    private function getLocationFromIP(string $ip): array
    {
        $default = ['location' => 'Unknown', 'country_code' => null];

        if (in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'])) {
            return ['location' => 'Local', 'country_code' => null];
        }

        $cached = get_transient('formflow_ip_geo_' . md5($ip));
        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get(
            "http://ip-api.com/json/{$ip}?fields=city,country,countryCode",
            ['timeout' => 3]
        );

        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($body && isset($body['city'], $body['country'])) {
                $result = [
                    'location' => $body['city'] . ', ' . $body['country'],
                    'country_code' => $body['countryCode'] ?? null,
                ];
                set_transient('formflow_ip_geo_' . md5($ip), $result, DAY_IN_SECONDS);
                return $result;
            }
        }

        return $default;
    }

    private function getCountryFromIP(string $ip): ?string
    {
        $geo = $this->getLocationFromIP($ip);
        return $geo['country_code'];
    }

    private function getCountryList(): array
    {
        return [
            'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada',
            'AU' => 'Australia', 'DE' => 'Germany', 'FR' => 'France',
            'BR' => 'Brazil', 'IN' => 'India', 'JP' => 'Japan',
            'CN' => 'China', 'RU' => 'Russia', 'IT' => 'Italy',
            'ES' => 'Spain', 'MX' => 'Mexico', 'KR' => 'South Korea',
            'NL' => 'Netherlands', 'SE' => 'Sweden', 'NO' => 'Norway',
            'DK' => 'Denmark', 'FI' => 'Finland', 'PL' => 'Poland',
            'PT' => 'Portugal', 'AR' => 'Argentina', 'CL' => 'Chile',
            'CO' => 'Colombia', 'ZA' => 'South Africa', 'NG' => 'Nigeria',
            'EG' => 'Egypt', 'IL' => 'Israel', 'AE' => 'United Arab Emirates',
            'SG' => 'Singapore', 'MY' => 'Malaysia', 'TH' => 'Thailand',
            'VN' => 'Vietnam', 'PH' => 'Philippines', 'ID' => 'Indonesia',
            'NZ' => 'New Zealand', 'IE' => 'Ireland', 'AT' => 'Austria',
            'CH' => 'Switzerland', 'BE' => 'Belgium', 'CZ' => 'Czech Republic',
            'GR' => 'Greece', 'HU' => 'Hungary', 'RO' => 'Romania',
            'UA' => 'Ukraine', 'TR' => 'Turkey', 'SA' => 'Saudi Arabia',
        ];
    }
}
