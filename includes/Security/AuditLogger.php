<?php

declare(strict_types=1);

namespace FormFlowPro\Security;

/**
 * Audit Logger System
 *
 * Comprehensive audit logging for security and compliance:
 * - All user actions tracking
 * - Authentication events
 * - Data access logging
 * - Configuration changes
 * - Security events
 * - Integration with SIEM systems
 * - Log retention and archival
 * - Real-time alerts
 * - Tamper-proof log storage
 *
 * @package FormFlowPro\Security
 * @since 2.4.0
 */
class AuditLogger
{
    private static ?AuditLogger $instance = null;

    // Event Categories
    public const CATEGORY_AUTH = 'authentication';
    public const CATEGORY_DATA = 'data_access';
    public const CATEGORY_ADMIN = 'administration';
    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_SYSTEM = 'system';
    public const CATEGORY_INTEGRATION = 'integration';
    public const CATEGORY_COMPLIANCE = 'compliance';

    // Event Severities
    public const SEVERITY_DEBUG = 'debug';
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    // Event Types
    public const EVENT_LOGIN_SUCCESS = 'login_success';
    public const EVENT_LOGIN_FAILED = 'login_failed';
    public const EVENT_LOGOUT = 'logout';
    public const EVENT_PASSWORD_CHANGED = 'password_changed';
    public const EVENT_2FA_ENABLED = '2fa_enabled';
    public const EVENT_2FA_DISABLED = '2fa_disabled';
    public const EVENT_2FA_VERIFIED = '2fa_verified';
    public const EVENT_2FA_FAILED = '2fa_failed';
    public const EVENT_API_KEY_CREATED = 'api_key_created';
    public const EVENT_API_KEY_REVOKED = 'api_key_revoked';
    public const EVENT_DATA_EXPORTED = 'data_exported';
    public const EVENT_DATA_DELETED = 'data_deleted';
    public const EVENT_DATA_MODIFIED = 'data_modified';
    public const EVENT_DATA_ACCESSED = 'data_accessed';
    public const EVENT_SETTINGS_CHANGED = 'settings_changed';
    public const EVENT_FORM_CREATED = 'form_created';
    public const EVENT_FORM_MODIFIED = 'form_modified';
    public const EVENT_FORM_DELETED = 'form_deleted';
    public const EVENT_SUBMISSION_CREATED = 'submission_created';
    public const EVENT_SUBMISSION_DELETED = 'submission_deleted';
    public const EVENT_PERMISSION_CHANGED = 'permission_changed';
    public const EVENT_RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    public const EVENT_SUSPICIOUS_ACTIVITY = 'suspicious_activity';
    public const EVENT_GDPR_REQUEST = 'gdpr_request';
    public const EVENT_INTEGRATION_CONNECTED = 'integration_connected';
    public const EVENT_INTEGRATION_ERROR = 'integration_error';
    public const EVENT_WEBHOOK_SENT = 'webhook_sent';
    public const EVENT_WEBHOOK_FAILED = 'webhook_failed';

    private string $tableAuditLogs;
    private string $tableAuditMeta;
    private array $buffer = [];
    private int $bufferSize = 0;
    private const MAX_BUFFER_SIZE = 50;

    private function __construct()
    {
        global $wpdb;
        $this->tableAuditLogs = $wpdb->prefix . 'formflow_audit_logs';
        $this->tableAuditMeta = $wpdb->prefix . 'formflow_audit_meta';

        $this->initHooks();
    }

    public static function getInstance(): AuditLogger
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initHooks(): void
    {
        // Authentication events
        add_action('wp_login', [$this, 'logLoginSuccess'], 10, 2);
        add_action('wp_login_failed', [$this, 'logLoginFailed']);
        add_action('wp_logout', [$this, 'logLogout']);
        add_action('password_reset', [$this, 'logPasswordReset'], 10, 2);
        add_action('profile_update', [$this, 'logProfileUpdate'], 10, 2);

        // FormFlow Pro events
        add_action('formflow_2fa_enabled', [$this, 'log2FAEnabled']);
        add_action('formflow_2fa_disabled', [$this, 'log2FADisabled']);
        add_action('formflow_2fa_verified', [$this, 'log2FAVerified'], 10, 2);
        add_action('formflow_2fa_attempt', [$this, 'log2FAAttempt']);
        add_action('formflow_gdpr_request_created', [$this, 'logGDPRRequest']);
        add_action('formflow_gdpr_erasure_completed', [$this, 'logGDPRErasure']);
        add_action('formflow_settings_updated', [$this, 'logSettingsChanged']);
        add_action('formflow_form_saved', [$this, 'logFormSaved'], 10, 2);
        add_action('formflow_form_deleted', [$this, 'logFormDeleted']);
        add_action('formflow_submission_created', [$this, 'logSubmissionCreated'], 10, 2);
        add_action('formflow_api_request', [$this, 'logAPIRequest'], 10, 3);

        // Flush buffer on shutdown
        add_action('shutdown', [$this, 'flushBuffer']);

        // Cron for cleanup
        add_action('formflow_audit_cleanup', [$this, 'cleanupOldLogs']);
        if (!wp_next_scheduled('formflow_audit_cleanup')) {
            wp_schedule_event(time(), 'daily', 'formflow_audit_cleanup');
        }
    }

    /**
     * Create database tables
     */
    public function createTables(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [];

        // Main audit logs table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableAuditLogs} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id VARCHAR(64) NOT NULL UNIQUE,
            event_type VARCHAR(100) NOT NULL,
            category VARCHAR(50) NOT NULL,
            severity ENUM('debug', 'info', 'warning', 'error', 'critical') DEFAULT 'info',
            user_id BIGINT UNSIGNED NULL,
            user_email VARCHAR(255) NULL,
            user_role VARCHAR(100) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            request_uri VARCHAR(2048) NULL,
            request_method VARCHAR(10) NULL,
            object_type VARCHAR(100) NULL,
            object_id BIGINT UNSIGNED NULL,
            object_name VARCHAR(255) NULL,
            action VARCHAR(100) NULL,
            description TEXT NULL,
            old_value LONGTEXT NULL,
            new_value LONGTEXT NULL,
            context JSON NULL,
            checksum VARCHAR(64) NULL,
            session_id VARCHAR(64) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_category (category),
            INDEX idx_severity (severity),
            INDEX idx_user (user_id),
            INDEX idx_object (object_type, object_id),
            INDEX idx_created (created_at),
            INDEX idx_ip (ip_address),
            INDEX idx_session (session_id),
            FULLTEXT idx_description (description)
        ) {$charset};";

        // Audit metadata for extended data
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableAuditMeta} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            audit_id BIGINT UNSIGNED NOT NULL,
            meta_key VARCHAR(255) NOT NULL,
            meta_value LONGTEXT NULL,
            INDEX idx_audit (audit_id),
            INDEX idx_key (meta_key)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    /**
     * Log an audit event
     */
    public function log(
        string $eventType,
        string $category,
        string $severity = self::SEVERITY_INFO,
        array $data = []
    ): string {
        $eventId = $this->generateEventId();

        $entry = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'category' => $category,
            'severity' => $severity,
            'user_id' => $data['user_id'] ?? get_current_user_id() ?: null,
            'user_email' => $data['user_email'] ?? $this->getCurrentUserEmail(),
            'user_role' => $data['user_role'] ?? $this->getCurrentUserRole(),
            'ip_address' => $data['ip_address'] ?? $this->getClientIP(),
            'user_agent' => $data['user_agent'] ?? $this->getUserAgent(),
            'request_uri' => $data['request_uri'] ?? $this->getRequestUri(),
            'request_method' => $data['request_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
            'object_type' => $data['object_type'] ?? null,
            'object_id' => $data['object_id'] ?? null,
            'object_name' => $data['object_name'] ?? null,
            'action' => $data['action'] ?? null,
            'description' => $data['description'] ?? null,
            'old_value' => isset($data['old_value']) ? $this->serializeValue($data['old_value']) : null,
            'new_value' => isset($data['new_value']) ? $this->serializeValue($data['new_value']) : null,
            'context' => isset($data['context']) ? json_encode($data['context']) : null,
            'session_id' => $this->getSessionId(),
            'created_at' => current_time('mysql'),
        ];

        // Generate checksum for tamper detection
        $entry['checksum'] = $this->generateChecksum($entry);

        // Add to buffer
        $this->buffer[] = $entry;
        $this->bufferSize++;

        // Flush if buffer is full or critical event
        if ($this->bufferSize >= self::MAX_BUFFER_SIZE || $severity === self::SEVERITY_CRITICAL) {
            $this->flushBuffer();
        }

        // Trigger real-time alerts for critical events
        if (in_array($severity, [self::SEVERITY_ERROR, self::SEVERITY_CRITICAL], true)) {
            $this->triggerAlert($entry);
        }

        // Fire action for external integrations (SIEM, etc.)
        do_action('formflow_audit_logged', $eventId, $eventType, $category, $severity, $data);

        return $eventId;
    }

    /**
     * Flush buffer to database
     */
    public function flushBuffer(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        global $wpdb;

        foreach ($this->buffer as $entry) {
            $wpdb->insert(
                $this->tableAuditLogs,
                $entry,
                [
                    '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s',
                    '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
                ]
            );
        }

        $this->buffer = [];
        $this->bufferSize = 0;
    }

    /**
     * Quick logging methods
     */
    public function info(string $eventType, string $description, array $data = []): string
    {
        $data['description'] = $description;
        return $this->log($eventType, self::CATEGORY_SYSTEM, self::SEVERITY_INFO, $data);
    }

    public function warning(string $eventType, string $description, array $data = []): string
    {
        $data['description'] = $description;
        return $this->log($eventType, self::CATEGORY_SECURITY, self::SEVERITY_WARNING, $data);
    }

    public function error(string $eventType, string $description, array $data = []): string
    {
        $data['description'] = $description;
        return $this->log($eventType, self::CATEGORY_SYSTEM, self::SEVERITY_ERROR, $data);
    }

    public function critical(string $eventType, string $description, array $data = []): string
    {
        $data['description'] = $description;
        return $this->log($eventType, self::CATEGORY_SECURITY, self::SEVERITY_CRITICAL, $data);
    }

    /**
     * Log authentication events
     */
    public function logLoginSuccess(string $userLogin, \WP_User $user): void
    {
        $this->log(
            self::EVENT_LOGIN_SUCCESS,
            self::CATEGORY_AUTH,
            self::SEVERITY_INFO,
            [
                'user_id' => $user->ID,
                'user_email' => $user->user_email,
                'description' => sprintf('User %s logged in successfully', $userLogin),
                'context' => [
                    'login' => $userLogin,
                    'method' => 'standard',
                ],
            ]
        );
    }

    public function logLoginFailed(string $username): void
    {
        $this->log(
            self::EVENT_LOGIN_FAILED,
            self::CATEGORY_AUTH,
            self::SEVERITY_WARNING,
            [
                'description' => sprintf('Failed login attempt for username: %s', $username),
                'context' => [
                    'username' => $username,
                    'reason' => 'invalid_credentials',
                ],
            ]
        );
    }

    public function logLogout(): void
    {
        $userId = get_current_user_id();
        $this->log(
            self::EVENT_LOGOUT,
            self::CATEGORY_AUTH,
            self::SEVERITY_INFO,
            [
                'user_id' => $userId,
                'description' => 'User logged out',
            ]
        );
    }

    public function logPasswordReset(\WP_User $user, string $newPass): void
    {
        $this->log(
            self::EVENT_PASSWORD_CHANGED,
            self::CATEGORY_AUTH,
            self::SEVERITY_INFO,
            [
                'user_id' => $user->ID,
                'user_email' => $user->user_email,
                'description' => 'Password was reset',
            ]
        );
    }

    public function logProfileUpdate(int $userId, \WP_User $oldUserData): void
    {
        $newUser = get_userdata($userId);
        $changes = [];

        if ($newUser->user_email !== $oldUserData->user_email) {
            $changes['email'] = [
                'old' => $oldUserData->user_email,
                'new' => $newUser->user_email,
            ];
        }

        if ($newUser->display_name !== $oldUserData->display_name) {
            $changes['display_name'] = [
                'old' => $oldUserData->display_name,
                'new' => $newUser->display_name,
            ];
        }

        if (!empty($changes)) {
            $this->log(
                self::EVENT_DATA_MODIFIED,
                self::CATEGORY_DATA,
                self::SEVERITY_INFO,
                [
                    'user_id' => $userId,
                    'object_type' => 'user',
                    'object_id' => $userId,
                    'description' => 'User profile updated',
                    'old_value' => $changes,
                    'new_value' => null,
                ]
            );
        }
    }

    /**
     * Log 2FA events
     */
    public function log2FAEnabled(int $userId): void
    {
        $this->log(
            self::EVENT_2FA_ENABLED,
            self::CATEGORY_SECURITY,
            self::SEVERITY_INFO,
            [
                'user_id' => $userId,
                'description' => 'Two-factor authentication enabled',
            ]
        );
    }

    public function log2FADisabled(int $userId): void
    {
        $this->log(
            self::EVENT_2FA_DISABLED,
            self::CATEGORY_SECURITY,
            self::SEVERITY_WARNING,
            [
                'user_id' => $userId,
                'description' => 'Two-factor authentication disabled',
            ]
        );
    }

    public function log2FAVerified(int $userId, string $method): void
    {
        $this->log(
            self::EVENT_2FA_VERIFIED,
            self::CATEGORY_AUTH,
            self::SEVERITY_INFO,
            [
                'user_id' => $userId,
                'description' => sprintf('2FA verification successful using %s', $method),
                'context' => ['method' => $method],
            ]
        );
    }

    public function log2FAAttempt(array $data): void
    {
        $severity = $data['success'] ? self::SEVERITY_INFO : self::SEVERITY_WARNING;
        $eventType = $data['success'] ? self::EVENT_2FA_VERIFIED : self::EVENT_2FA_FAILED;

        $this->log(
            $eventType,
            self::CATEGORY_AUTH,
            $severity,
            [
                'user_id' => $data['user_id'],
                'description' => $data['success']
                    ? '2FA verification successful'
                    : '2FA verification failed',
                'context' => $data,
            ]
        );
    }

    /**
     * Log GDPR events
     */
    public function logGDPRRequest(array $data): void
    {
        $this->log(
            self::EVENT_GDPR_REQUEST,
            self::CATEGORY_COMPLIANCE,
            self::SEVERITY_INFO,
            [
                'description' => sprintf('GDPR %s request created for %s', $data['type'], $data['email']),
                'context' => $data,
            ]
        );
    }

    public function logGDPRErasure(array $data): void
    {
        $this->log(
            self::EVENT_DATA_DELETED,
            self::CATEGORY_COMPLIANCE,
            self::SEVERITY_INFO,
            [
                'description' => sprintf('GDPR erasure completed for %s', $data['email']),
                'context' => $data,
            ]
        );
    }

    /**
     * Log settings changes
     */
    public function logSettingsChanged(array $data): void
    {
        $this->log(
            self::EVENT_SETTINGS_CHANGED,
            self::CATEGORY_ADMIN,
            self::SEVERITY_INFO,
            [
                'description' => 'Plugin settings changed',
                'old_value' => $data['old'] ?? null,
                'new_value' => $data['new'] ?? null,
                'context' => ['section' => $data['section'] ?? 'general'],
            ]
        );
    }

    /**
     * Log form events
     */
    public function logFormSaved(int $formId, array $data): void
    {
        $isNew = $data['is_new'] ?? false;
        $this->log(
            $isNew ? self::EVENT_FORM_CREATED : self::EVENT_FORM_MODIFIED,
            self::CATEGORY_DATA,
            self::SEVERITY_INFO,
            [
                'object_type' => 'form',
                'object_id' => $formId,
                'object_name' => $data['title'] ?? 'Untitled Form',
                'description' => $isNew ? 'Form created' : 'Form modified',
            ]
        );
    }

    public function logFormDeleted(int $formId): void
    {
        $this->log(
            self::EVENT_FORM_DELETED,
            self::CATEGORY_DATA,
            self::SEVERITY_WARNING,
            [
                'object_type' => 'form',
                'object_id' => $formId,
                'description' => 'Form deleted',
            ]
        );
    }

    /**
     * Log submission events
     */
    public function logSubmissionCreated(int $submissionId, array $data): void
    {
        $this->log(
            self::EVENT_SUBMISSION_CREATED,
            self::CATEGORY_DATA,
            self::SEVERITY_INFO,
            [
                'object_type' => 'submission',
                'object_id' => $submissionId,
                'description' => 'Form submission received',
                'context' => [
                    'form_id' => $data['form_id'] ?? null,
                    'email' => $data['email'] ?? null,
                ],
            ]
        );
    }

    /**
     * Log API request
     */
    public function logAPIRequest(string $endpoint, string $method, array $response): void
    {
        $severity = ($response['status_code'] ?? 200) >= 400
            ? self::SEVERITY_WARNING
            : self::SEVERITY_DEBUG;

        $this->log(
            'api_request',
            self::CATEGORY_INTEGRATION,
            $severity,
            [
                'description' => sprintf('API request: %s %s', $method, $endpoint),
                'context' => [
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'status_code' => $response['status_code'] ?? null,
                ],
            ]
        );
    }

    /**
     * Query audit logs
     */
    public function query(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = %s';
            $params[] = $filters['event_type'];
        }

        if (!empty($filters['category'])) {
            $where[] = 'category = %s';
            $params[] = $filters['category'];
        }

        if (!empty($filters['severity'])) {
            if (is_array($filters['severity'])) {
                $placeholders = implode(',', array_fill(0, count($filters['severity']), '%s'));
                $where[] = "severity IN ({$placeholders})";
                $params = array_merge($params, $filters['severity']);
            } else {
                $where[] = 'severity = %s';
                $params[] = $filters['severity'];
            }
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['ip_address'])) {
            $where[] = 'ip_address = %s';
            $params[] = $filters['ip_address'];
        }

        if (!empty($filters['object_type'])) {
            $where[] = 'object_type = %s';
            $params[] = $filters['object_type'];
        }

        if (!empty($filters['object_id'])) {
            $where[] = 'object_id = %d';
            $params[] = $filters['object_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = 'MATCH(description) AGAINST(%s IN BOOLEAN MODE)';
            $params[] = $filters['search'];
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $query = "SELECT * FROM {$this->tableAuditLogs}
                  WHERE {$whereClause}
                  ORDER BY created_at DESC
                  LIMIT %d OFFSET %d";

        $results = $wpdb->get_results(
            $wpdb->prepare($query, ...$params),
            ARRAY_A
        );

        // Verify checksums
        foreach ($results as &$row) {
            $row['integrity_valid'] = $this->verifyChecksum($row);
            if ($row['context']) {
                $row['context'] = json_decode($row['context'], true);
            }
        }

        return $results;
    }

    /**
     * Get log count
     */
    public function getCount(array $filters = []): int
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['category'])) {
            $where[] = 'category = %s';
            $params[] = $filters['category'];
        }

        if (!empty($filters['severity'])) {
            $where[] = 'severity = %s';
            $params[] = $filters['severity'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $params[] = $filters['date_from'];
        }

        $whereClause = implode(' AND ', $where);

        if ($params) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->tableAuditLogs} WHERE {$whereClause}",
                    ...$params
                )
            );
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tableAuditLogs} WHERE {$whereClause}"
        );
    }

    /**
     * Get statistics
     */
    public function getStatistics(string $period = 'day'): array
    {
        global $wpdb;

        $intervals = [
            'hour' => 'INTERVAL 1 HOUR',
            'day' => 'INTERVAL 1 DAY',
            'week' => 'INTERVAL 1 WEEK',
            'month' => 'INTERVAL 1 MONTH',
        ];

        $interval = $intervals[$period] ?? $intervals['day'];

        $stats = [
            'total' => 0,
            'by_category' => [],
            'by_severity' => [],
            'by_event_type' => [],
            'top_users' => [],
            'top_ips' => [],
            'timeline' => [],
        ];

        // Total count
        $stats['total'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->tableAuditLogs}
            WHERE created_at >= DATE_SUB(NOW(), {$interval})"
        );

        // By category
        $categories = $wpdb->get_results(
            "SELECT category, COUNT(*) as count FROM {$this->tableAuditLogs}
            WHERE created_at >= DATE_SUB(NOW(), {$interval})
            GROUP BY category ORDER BY count DESC",
            ARRAY_A
        );
        foreach ($categories as $cat) {
            $stats['by_category'][$cat['category']] = (int) $cat['count'];
        }

        // By severity
        $severities = $wpdb->get_results(
            "SELECT severity, COUNT(*) as count FROM {$this->tableAuditLogs}
            WHERE created_at >= DATE_SUB(NOW(), {$interval})
            GROUP BY severity ORDER BY FIELD(severity, 'critical', 'error', 'warning', 'info', 'debug')",
            ARRAY_A
        );
        foreach ($severities as $sev) {
            $stats['by_severity'][$sev['severity']] = (int) $sev['count'];
        }

        // Top event types
        $events = $wpdb->get_results(
            "SELECT event_type, COUNT(*) as count FROM {$this->tableAuditLogs}
            WHERE created_at >= DATE_SUB(NOW(), {$interval})
            GROUP BY event_type ORDER BY count DESC LIMIT 10",
            ARRAY_A
        );
        foreach ($events as $event) {
            $stats['by_event_type'][$event['event_type']] = (int) $event['count'];
        }

        // Top users
        $stats['top_users'] = $wpdb->get_results(
            "SELECT user_id, user_email, COUNT(*) as count FROM {$this->tableAuditLogs}
            WHERE created_at >= DATE_SUB(NOW(), {$interval}) AND user_id IS NOT NULL
            GROUP BY user_id, user_email ORDER BY count DESC LIMIT 10",
            ARRAY_A
        );

        // Top IPs
        $stats['top_ips'] = $wpdb->get_results(
            "SELECT ip_address, COUNT(*) as count FROM {$this->tableAuditLogs}
            WHERE created_at >= DATE_SUB(NOW(), {$interval}) AND ip_address IS NOT NULL
            GROUP BY ip_address ORDER BY count DESC LIMIT 10",
            ARRAY_A
        );

        // Timeline (hourly for day, daily for week/month)
        $groupBy = $period === 'hour' ? '%Y-%m-%d %H:00' : '%Y-%m-%d';
        $stats['timeline'] = $wpdb->get_results(
            "SELECT DATE_FORMAT(created_at, '{$groupBy}') as period, COUNT(*) as count
            FROM {$this->tableAuditLogs}
            WHERE created_at >= DATE_SUB(NOW(), {$interval})
            GROUP BY period ORDER BY period ASC",
            ARRAY_A
        );

        return $stats;
    }

    /**
     * Export logs
     */
    public function export(array $filters = [], string $format = 'json'): string
    {
        $logs = $this->query($filters, 10000, 0);

        switch ($format) {
            case 'csv':
                return $this->exportCSV($logs);
            case 'json':
            default:
                return json_encode($logs, JSON_PRETTY_PRINT);
        }
    }

    /**
     * Export to CSV
     */
    private function exportCSV(array $logs): string
    {
        if (empty($logs)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Header
        fputcsv($output, array_keys($logs[0]));

        // Data
        foreach ($logs as $log) {
            $row = array_map(function ($value) {
                if (is_array($value)) {
                    return json_encode($value);
                }
                return $value;
            }, $log);
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Cleanup old logs
     */
    public function cleanupOldLogs(): void
    {
        global $wpdb;

        $retentionDays = (int) get_option('formflow_audit_retention_days', 90);

        // Archive critical/error logs before deletion
        $this->archiveLogs($retentionDays);

        // Delete old logs
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->tableAuditLogs}
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
                AND severity NOT IN ('critical', 'error')",
                $retentionDays
            )
        );

        // Delete very old critical/error logs (keep for 1 year)
        $wpdb->query(
            "DELETE FROM {$this->tableAuditLogs}
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)"
        );

        // Cleanup orphaned meta
        $wpdb->query(
            "DELETE m FROM {$this->tableAuditMeta} m
            LEFT JOIN {$this->tableAuditLogs} l ON m.audit_id = l.id
            WHERE l.id IS NULL"
        );
    }

    /**
     * Archive logs before deletion
     */
    private function archiveLogs(int $retentionDays): void
    {
        global $wpdb;

        $uploadDir = wp_upload_dir();
        $archiveDir = $uploadDir['basedir'] . '/formflow-audit-archive';

        if (!file_exists($archiveDir)) {
            wp_mkdir_p($archiveDir);
            file_put_contents($archiveDir . '/.htaccess', 'deny from all');
        }

        // Get logs to archive
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableAuditLogs}
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
                AND severity IN ('critical', 'error')
                LIMIT 10000",
                $retentionDays
            ),
            ARRAY_A
        );

        if (!empty($logs)) {
            $filename = 'audit-archive-' . date('Y-m-d-His') . '.json.gz';
            $filepath = $archiveDir . '/' . $filename;

            $compressed = gzencode(json_encode($logs, JSON_PRETTY_PRINT));
            file_put_contents($filepath, $compressed);
        }
    }

    /**
     * Trigger alert for critical events
     */
    private function triggerAlert(array $entry): void
    {
        $alertEmails = get_option('formflow_security_alert_emails', get_option('admin_email'));

        if (empty($alertEmails)) {
            return;
        }

        $subject = sprintf(
            '[%s] Security Alert: %s',
            get_bloginfo('name'),
            $entry['event_type']
        );

        $message = sprintf(
            "A security event has been detected:\n\n" .
            "Event: %s\n" .
            "Category: %s\n" .
            "Severity: %s\n" .
            "Description: %s\n" .
            "IP Address: %s\n" .
            "User: %s\n" .
            "Time: %s\n\n" .
            "Please review the audit logs for more details.",
            $entry['event_type'],
            $entry['category'],
            strtoupper($entry['severity']),
            $entry['description'] ?? 'N/A',
            $entry['ip_address'] ?? 'N/A',
            $entry['user_email'] ?? 'N/A',
            $entry['created_at']
        );

        wp_mail($alertEmails, $subject, $message);

        // Fire action for external alert systems
        do_action('formflow_security_alert', $entry);
    }

    /**
     * Generate checksum for tamper detection
     */
    private function generateChecksum(array $entry): string
    {
        $data = [
            $entry['event_id'],
            $entry['event_type'],
            $entry['category'],
            $entry['user_id'],
            $entry['ip_address'],
            $entry['description'],
            $entry['created_at'],
        ];

        $salt = get_option('formflow_audit_salt');
        if (!$salt) {
            $salt = bin2hex(random_bytes(32));
            update_option('formflow_audit_salt', $salt, false);
        }

        return hash_hmac('sha256', implode('|', $data), $salt);
    }

    /**
     * Verify log checksum
     */
    private function verifyChecksum(array $entry): bool
    {
        $expected = $this->generateChecksum($entry);
        return hash_equals($expected, $entry['checksum'] ?? '');
    }

    /**
     * Generate event ID
     */
    private function generateEventId(): string
    {
        return 'AUD-' . strtoupper(bin2hex(random_bytes(12)));
    }

    /**
     * Serialize value for storage
     */
    private function serializeValue($value): string
    {
        if (is_string($value)) {
            return $value;
        }
        return json_encode($value, JSON_PRETTY_PRINT);
    }

    /**
     * Get current user email
     */
    private function getCurrentUserEmail(): ?string
    {
        $user = wp_get_current_user();
        return $user->ID ? $user->user_email : null;
    }

    /**
     * Get current user role
     */
    private function getCurrentUserRole(): ?string
    {
        $user = wp_get_current_user();
        return $user->ID && !empty($user->roles) ? $user->roles[0] : null;
    }

    /**
     * Get client IP
     */
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

    /**
     * Get user agent
     */
    private function getUserAgent(): string
    {
        return substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    /**
     * Get request URI
     */
    private function getRequestUri(): string
    {
        return substr(sanitize_text_field($_SERVER['REQUEST_URI'] ?? ''), 0, 2048);
    }

    /**
     * Get session ID
     */
    private function getSessionId(): ?string
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return session_id();
        }

        if (isset($_COOKIE['formflow_session'])) {
            return sanitize_text_field($_COOKIE['formflow_session']);
        }

        return null;
    }
}
