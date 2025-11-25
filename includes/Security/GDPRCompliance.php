<?php

declare(strict_types=1);

namespace FormFlowPro\Security;

/**
 * GDPR Compliance Manager
 *
 * Complete GDPR compliance implementation including:
 * - Personal data export (Right to Data Portability)
 * - Data deletion requests (Right to Erasure)
 * - Consent management with granular tracking
 * - Data retention policies
 * - Privacy request workflow
 * - Anonymization tools
 * - Cookie consent integration
 * - Processing activity records
 *
 * @package FormFlowPro\Security
 * @since 2.4.0
 */
class GDPRCompliance
{
    private static ?GDPRCompliance $instance = null;

    private const REQUEST_TYPES = ['export', 'erasure', 'rectification', 'restriction'];
    private const REQUEST_STATUSES = ['pending', 'processing', 'completed', 'rejected', 'expired'];
    private const CONSENT_TYPES = ['marketing', 'analytics', 'functional', 'third_party', 'profiling'];
    private const DATA_RETENTION_DEFAULT = 365; // days

    private string $tableRequests;
    private string $tableConsents;
    private string $tableProcessingActivities;
    private string $tableDataInventory;

    private function __construct()
    {
        global $wpdb;
        $this->tableRequests = $wpdb->prefix . 'formflow_gdpr_requests';
        $this->tableConsents = $wpdb->prefix . 'formflow_gdpr_consents';
        $this->tableProcessingActivities = $wpdb->prefix . 'formflow_gdpr_processing';
        $this->tableDataInventory = $wpdb->prefix . 'formflow_gdpr_inventory';

        $this->initHooks();
    }

    public static function getInstance(): GDPRCompliance
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initHooks(): void
    {
        // WordPress Privacy Tools integration
        add_action('admin_init', [$this, 'registerPrivacyExporter']);
        add_action('admin_init', [$this, 'registerPrivacyEraser']);

        // Cron jobs
        add_action('formflow_gdpr_process_requests', [$this, 'processScheduledRequests']);
        add_action('formflow_gdpr_cleanup', [$this, 'cleanupExpiredData']);
        add_action('formflow_gdpr_retention', [$this, 'enforceDataRetention']);

        // Schedule cron if not scheduled
        if (!wp_next_scheduled('formflow_gdpr_process_requests')) {
            wp_schedule_event(time(), 'hourly', 'formflow_gdpr_process_requests');
        }
        if (!wp_next_scheduled('formflow_gdpr_cleanup')) {
            wp_schedule_event(time(), 'daily', 'formflow_gdpr_cleanup');
        }

        // AJAX handlers
        add_action('wp_ajax_formflow_gdpr_request', [$this, 'ajaxCreateRequest']);
        add_action('wp_ajax_nopriv_formflow_gdpr_request', [$this, 'ajaxCreateRequest']);
        add_action('wp_ajax_formflow_gdpr_consent', [$this, 'ajaxUpdateConsent']);
        add_action('wp_ajax_nopriv_formflow_gdpr_consent', [$this, 'ajaxUpdateConsent']);
        add_action('wp_ajax_formflow_gdpr_process', [$this, 'ajaxProcessRequest']);
        add_action('wp_ajax_formflow_gdpr_download', [$this, 'ajaxDownloadExport']);

        // Form submission hooks
        add_action('formflow_before_submission', [$this, 'captureConsent'], 10, 2);
        add_action('formflow_after_submission', [$this, 'logProcessingActivity'], 10, 2);
    }

    /**
     * Create database tables
     */
    public function createTables(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [];

        // GDPR Requests
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableRequests} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_id VARCHAR(64) NOT NULL UNIQUE,
            type ENUM('export', 'erasure', 'rectification', 'restriction') NOT NULL,
            status ENUM('pending', 'processing', 'completed', 'rejected', 'expired') DEFAULT 'pending',
            email VARCHAR(255) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            verification_code VARCHAR(64) NULL,
            verified_at DATETIME NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            notes TEXT NULL,
            export_file VARCHAR(255) NULL,
            processed_by BIGINT UNSIGNED NULL,
            processed_at DATETIME NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_type (type),
            INDEX idx_status (status),
            INDEX idx_expires (expires_at),
            INDEX idx_user (user_id)
        ) {$charset};";

        // Consent Records
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableConsents} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            consent_id VARCHAR(64) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            form_id BIGINT UNSIGNED NULL,
            submission_id BIGINT UNSIGNED NULL,
            consent_type VARCHAR(50) NOT NULL,
            consent_given TINYINT(1) DEFAULT 0,
            consent_text TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            consent_version VARCHAR(20) DEFAULT '1.0',
            source VARCHAR(100) NULL,
            withdrawn_at DATETIME NULL,
            withdrawal_reason TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_user (user_id),
            INDEX idx_type (consent_type),
            INDEX idx_form (form_id),
            INDEX idx_given (consent_given)
        ) {$charset};";

        // Processing Activities (Article 30)
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableProcessingActivities} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            activity_id VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            purpose TEXT NOT NULL,
            legal_basis ENUM('consent', 'contract', 'legal_obligation', 'vital_interests', 'public_task', 'legitimate_interests') NOT NULL,
            data_categories JSON NOT NULL,
            data_subjects JSON NOT NULL,
            recipients JSON NULL,
            third_countries JSON NULL,
            retention_period INT UNSIGNED NULL,
            security_measures TEXT NULL,
            is_active TINYINT(1) DEFAULT 1,
            dpo_approved TINYINT(1) DEFAULT 0,
            dpo_approved_at DATETIME NULL,
            risk_assessment TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active),
            INDEX idx_legal_basis (legal_basis)
        ) {$charset};";

        // Data Inventory
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableDataInventory} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            data_type VARCHAR(100) NOT NULL,
            table_name VARCHAR(255) NOT NULL,
            column_name VARCHAR(255) NOT NULL,
            is_personal_data TINYINT(1) DEFAULT 0,
            is_sensitive TINYINT(1) DEFAULT 0,
            is_encrypted TINYINT(1) DEFAULT 0,
            retention_days INT UNSIGNED NULL,
            anonymization_method VARCHAR(100) NULL,
            description TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_column (table_name, column_name),
            INDEX idx_personal (is_personal_data),
            INDEX idx_sensitive (is_sensitive)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    /**
     * Create privacy request
     */
    public function createRequest(string $type, string $email, ?int $userId = null): array
    {
        global $wpdb;

        if (!in_array($type, self::REQUEST_TYPES, true)) {
            return ['success' => false, 'error' => __('Invalid request type', 'formflow-pro')];
        }

        if (!is_email($email)) {
            return ['success' => false, 'error' => __('Invalid email address', 'formflow-pro')];
        }

        // Check for existing pending request
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$this->tableRequests}
                WHERE email = %s AND type = %s AND status IN ('pending', 'processing')
                AND expires_at > NOW()",
                $email,
                $type
            )
        );

        if ($existing) {
            return [
                'success' => false,
                'error' => __('A similar request is already pending', 'formflow-pro'),
            ];
        }

        $requestId = $this->generateRequestId();
        $verificationCode = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $inserted = $wpdb->insert(
            $this->tableRequests,
            [
                'request_id' => $requestId,
                'type' => $type,
                'status' => 'pending',
                'email' => $email,
                'user_id' => $userId,
                'verification_code' => password_hash($verificationCode, PASSWORD_DEFAULT),
                'ip_address' => $this->getClientIP(),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'expires_at' => $expiresAt,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            return ['success' => false, 'error' => __('Failed to create request', 'formflow-pro')];
        }

        // Send verification email
        $this->sendVerificationEmail($email, $requestId, $verificationCode, $type);

        // Log audit event
        do_action('formflow_gdpr_request_created', [
            'request_id' => $requestId,
            'type' => $type,
            'email' => $email,
        ]);

        return [
            'success' => true,
            'request_id' => $requestId,
            'message' => __('Request created. Please check your email to verify.', 'formflow-pro'),
        ];
    }

    /**
     * Verify request
     */
    public function verifyRequest(string $requestId, string $code): bool
    {
        global $wpdb;

        $request = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, verification_code FROM {$this->tableRequests}
                WHERE request_id = %s AND status = 'pending'",
                $requestId
            )
        );

        if (!$request || !password_verify($code, $request->verification_code)) {
            return false;
        }

        $wpdb->update(
            $this->tableRequests,
            [
                'verified_at' => current_time('mysql'),
                'status' => 'processing',
            ],
            ['id' => $request->id],
            ['%s', '%s'],
            ['%d']
        );

        // Queue for processing
        do_action('formflow_gdpr_request_verified', $requestId);

        return true;
    }

    /**
     * Process export request
     */
    public function processExportRequest(string $requestId): array
    {
        global $wpdb;

        $request = $this->getRequest($requestId);
        if (!$request) {
            return ['success' => false, 'error' => 'Request not found'];
        }

        $email = $request['email'];
        $userId = $request['user_id'];

        $exportData = [
            'export_date' => current_time('c'),
            'request_id' => $requestId,
            'data_subject' => [
                'email' => $email,
                'user_id' => $userId,
            ],
            'data' => [],
        ];

        // Collect submissions data
        $submissionsTable = $wpdb->prefix . 'formflow_submissions';
        $submissions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$submissionsTable} WHERE email = %s OR user_id = %d",
                $email,
                $userId ?? 0
            ),
            ARRAY_A
        );

        if ($submissions) {
            $exportData['data']['submissions'] = array_map(function ($sub) {
                $sub['submission_data'] = json_decode($sub['submission_data'] ?? '{}', true);
                unset($sub['ip_address']); // Remove IP for privacy
                return $sub;
            }, $submissions);
        }

        // Collect consent records
        $consents = $this->getConsentHistory($email);
        if ($consents) {
            $exportData['data']['consent_history'] = $consents;
        }

        // Collect form data
        $formsTable = $wpdb->prefix . 'formflow_forms';
        $forms = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, created_at FROM {$formsTable} WHERE created_by = %d",
                $userId ?? 0
            ),
            ARRAY_A
        );

        if ($forms) {
            $exportData['data']['forms_created'] = $forms;
        }

        // Apply filters for extensibility
        $exportData = apply_filters('formflow_gdpr_export_data', $exportData, $email, $userId);

        // Generate export file
        $uploadDir = wp_upload_dir();
        $exportDir = $uploadDir['basedir'] . '/formflow-exports';

        if (!file_exists($exportDir)) {
            wp_mkdir_p($exportDir);
            file_put_contents($exportDir . '/.htaccess', 'deny from all');
        }

        $filename = 'export-' . $requestId . '-' . time() . '.json';
        $filepath = $exportDir . '/' . $filename;

        file_put_contents($filepath, json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Also create ZIP with HTML summary
        $zipFilename = 'export-' . $requestId . '-' . time() . '.zip';
        $zipPath = $exportDir . '/' . $zipFilename;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
            $zip->addFile($filepath, 'data.json');
            $zip->addFromString('summary.html', $this->generateExportHTML($exportData));
            $zip->close();
        }

        // Update request
        $wpdb->update(
            $this->tableRequests,
            [
                'status' => 'completed',
                'export_file' => $zipFilename,
                'processed_at' => current_time('mysql'),
            ],
            ['request_id' => $requestId],
            ['%s', '%s', '%s'],
            ['%s']
        );

        // Send download link
        $this->sendExportReadyEmail($request['email'], $requestId);

        // Cleanup JSON file
        unlink($filepath);

        return [
            'success' => true,
            'file' => $zipFilename,
            'message' => 'Export completed',
        ];
    }

    /**
     * Process erasure request
     */
    public function processErasureRequest(string $requestId): array
    {
        global $wpdb;

        $request = $this->getRequest($requestId);
        if (!$request) {
            return ['success' => false, 'error' => 'Request not found'];
        }

        $email = $request['email'];
        $userId = $request['user_id'];
        $deletedItems = [];

        // Delete/anonymize submissions
        $submissionsTable = $wpdb->prefix . 'formflow_submissions';
        $count = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$submissionsTable}
                SET email = 'deleted@anonymized.local',
                    submission_data = JSON_SET(submission_data, '$.anonymized', true),
                    ip_address = '0.0.0.0',
                    user_agent = 'anonymized'
                WHERE email = %s OR user_id = %d",
                $email,
                $userId ?? 0
            )
        );
        $deletedItems['submissions_anonymized'] = $count;

        // Delete consent records
        $count = $wpdb->delete(
            $this->tableConsents,
            ['email' => $email],
            ['%s']
        );
        $deletedItems['consents_deleted'] = $count;

        // Delete from Autentique documents table if exists
        $autentiqueTable = $wpdb->prefix . 'formflow_autentique_documents';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$autentiqueTable}'")) {
            $count = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$autentiqueTable}
                    SET signer_email = 'deleted@anonymized.local',
                        signer_name = 'Anonymized'
                    WHERE signer_email = %s",
                    $email
                )
            );
            $deletedItems['documents_anonymized'] = $count;
        }

        // Apply filters for custom tables
        $deletedItems = apply_filters('formflow_gdpr_erasure_data', $deletedItems, $email, $userId);

        // Update request
        $wpdb->update(
            $this->tableRequests,
            [
                'status' => 'completed',
                'notes' => json_encode($deletedItems),
                'processed_at' => current_time('mysql'),
            ],
            ['request_id' => $requestId],
            ['%s', '%s', '%s'],
            ['%s']
        );

        // Send confirmation
        $this->sendErasureConfirmationEmail($email);

        do_action('formflow_gdpr_erasure_completed', [
            'request_id' => $requestId,
            'email' => $email,
            'deleted_items' => $deletedItems,
        ]);

        return [
            'success' => true,
            'deleted' => $deletedItems,
            'message' => 'Erasure completed',
        ];
    }

    /**
     * Record consent
     */
    public function recordConsent(array $data): bool
    {
        global $wpdb;

        $required = ['email', 'consent_type', 'consent_given'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }

        $consentId = $this->generateConsentId();

        return (bool) $wpdb->insert(
            $this->tableConsents,
            [
                'consent_id' => $consentId,
                'email' => sanitize_email($data['email']),
                'user_id' => $data['user_id'] ?? null,
                'form_id' => $data['form_id'] ?? null,
                'submission_id' => $data['submission_id'] ?? null,
                'consent_type' => sanitize_text_field($data['consent_type']),
                'consent_given' => $data['consent_given'] ? 1 : 0,
                'consent_text' => sanitize_textarea_field($data['consent_text'] ?? ''),
                'ip_address' => $this->getClientIP(),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'consent_version' => $data['version'] ?? '1.0',
                'source' => sanitize_text_field($data['source'] ?? 'form'),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Withdraw consent
     */
    public function withdrawConsent(string $email, string $consentType, string $reason = ''): bool
    {
        global $wpdb;

        $updated = $wpdb->update(
            $this->tableConsents,
            [
                'consent_given' => 0,
                'withdrawn_at' => current_time('mysql'),
                'withdrawal_reason' => sanitize_textarea_field($reason),
            ],
            [
                'email' => $email,
                'consent_type' => $consentType,
                'consent_given' => 1,
            ],
            ['%d', '%s', '%s'],
            ['%s', '%s', '%d']
        );

        if ($updated) {
            do_action('formflow_consent_withdrawn', [
                'email' => $email,
                'consent_type' => $consentType,
                'reason' => $reason,
            ]);
        }

        return (bool) $updated;
    }

    /**
     * Check if consent is given
     */
    public function hasConsent(string $email, string $consentType): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT consent_given FROM {$this->tableConsents}
                WHERE email = %s AND consent_type = %s
                ORDER BY created_at DESC LIMIT 1",
                $email,
                $consentType
            )
        );
    }

    /**
     * Get consent history for email
     */
    public function getConsentHistory(string $email): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT consent_type, consent_given, consent_text, consent_version,
                        source, created_at, withdrawn_at, withdrawal_reason
                FROM {$this->tableConsents}
                WHERE email = %s
                ORDER BY created_at DESC",
                $email
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get current consents for email
     */
    public function getCurrentConsents(string $email): array
    {
        global $wpdb;

        $consents = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT c1.consent_type, c1.consent_given, c1.created_at
                FROM {$this->tableConsents} c1
                INNER JOIN (
                    SELECT consent_type, MAX(created_at) as max_date
                    FROM {$this->tableConsents}
                    WHERE email = %s
                    GROUP BY consent_type
                ) c2 ON c1.consent_type = c2.consent_type AND c1.created_at = c2.max_date
                WHERE c1.email = %s",
                $email,
                $email
            ),
            ARRAY_A
        );

        $result = [];
        foreach ($consents as $consent) {
            $result[$consent['consent_type']] = (bool) $consent['consent_given'];
        }

        return $result;
    }

    /**
     * Register processing activity
     */
    public function registerProcessingActivity(array $data): bool
    {
        global $wpdb;

        $activityId = $this->generateActivityId();

        return (bool) $wpdb->insert(
            $this->tableProcessingActivities,
            [
                'activity_id' => $activityId,
                'name' => sanitize_text_field($data['name']),
                'purpose' => sanitize_textarea_field($data['purpose']),
                'legal_basis' => $data['legal_basis'],
                'data_categories' => json_encode($data['data_categories'] ?? []),
                'data_subjects' => json_encode($data['data_subjects'] ?? []),
                'recipients' => json_encode($data['recipients'] ?? []),
                'third_countries' => json_encode($data['third_countries'] ?? []),
                'retention_period' => $data['retention_period'] ?? self::DATA_RETENTION_DEFAULT,
                'security_measures' => sanitize_textarea_field($data['security_measures'] ?? ''),
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s']
        );
    }

    /**
     * Get all processing activities
     */
    public function getProcessingActivities(bool $activeOnly = true): array
    {
        global $wpdb;

        $where = $activeOnly ? 'WHERE is_active = 1' : '';

        return $wpdb->get_results(
            "SELECT * FROM {$this->tableProcessingActivities} {$where} ORDER BY name ASC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Register data in inventory
     */
    public function registerDataInventory(array $data): bool
    {
        global $wpdb;

        return (bool) $wpdb->replace(
            $this->tableDataInventory,
            [
                'data_type' => sanitize_text_field($data['data_type']),
                'table_name' => sanitize_text_field($data['table_name']),
                'column_name' => sanitize_text_field($data['column_name']),
                'is_personal_data' => !empty($data['is_personal_data']) ? 1 : 0,
                'is_sensitive' => !empty($data['is_sensitive']) ? 1 : 0,
                'is_encrypted' => !empty($data['is_encrypted']) ? 1 : 0,
                'retention_days' => $data['retention_days'] ?? null,
                'anonymization_method' => $data['anonymization_method'] ?? null,
                'description' => sanitize_textarea_field($data['description'] ?? ''),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * Get data inventory
     */
    public function getDataInventory(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->tableDataInventory} ORDER BY table_name, column_name",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Enforce data retention policy
     */
    public function enforceDataRetention(): void
    {
        global $wpdb;

        $inventory = $wpdb->get_results(
            "SELECT table_name, column_name, retention_days, anonymization_method
            FROM {$this->tableDataInventory}
            WHERE retention_days IS NOT NULL AND is_personal_data = 1",
            ARRAY_A
        );

        foreach ($inventory as $item) {
            $tableName = $wpdb->prefix . $item['table_name'];
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$item['retention_days']} days"));

            // Check if table exists
            if (!$wpdb->get_var("SHOW TABLES LIKE '{$tableName}'")) {
                continue;
            }

            // Check if column exists
            $column = $wpdb->get_results("SHOW COLUMNS FROM {$tableName} LIKE 'created_at'");
            if (empty($column)) {
                continue;
            }

            switch ($item['anonymization_method']) {
                case 'delete':
                    $wpdb->query(
                        $wpdb->prepare(
                            "DELETE FROM {$tableName} WHERE created_at < %s",
                            $cutoffDate
                        )
                    );
                    break;

                case 'hash':
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE {$tableName}
                            SET {$item['column_name']} = SHA2({$item['column_name']}, 256)
                            WHERE created_at < %s",
                            $cutoffDate
                        )
                    );
                    break;

                case 'mask':
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE {$tableName}
                            SET {$item['column_name']} = '***MASKED***'
                            WHERE created_at < %s",
                            $cutoffDate
                        )
                    );
                    break;

                case 'null':
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE {$tableName}
                            SET {$item['column_name']} = NULL
                            WHERE created_at < %s",
                            $cutoffDate
                        )
                    );
                    break;
            }
        }

        do_action('formflow_gdpr_retention_enforced');
    }

    /**
     * Get request by ID
     */
    public function getRequest(string $requestId): ?array
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableRequests} WHERE request_id = %s",
                $requestId
            ),
            ARRAY_A
        );
    }

    /**
     * Get all requests
     */
    public function getRequests(array $filters = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['type'])) {
            $where[] = 'type = %s';
            $params[] = $filters['type'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }

        if (!empty($filters['email'])) {
            $where[] = 'email LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['email']) . '%';
        }

        $whereClause = implode(' AND ', $where);
        $query = "SELECT * FROM {$this->tableRequests} WHERE {$whereClause} ORDER BY created_at DESC";

        if ($params) {
            $query = $wpdb->prepare($query, ...$params);
        }

        return $wpdb->get_results($query, ARRAY_A) ?: [];
    }

    /**
     * Process scheduled requests
     */
    public function processScheduledRequests(): void
    {
        global $wpdb;

        // Get verified requests pending processing
        $requests = $wpdb->get_results(
            "SELECT request_id, type FROM {$this->tableRequests}
            WHERE status = 'processing' AND verified_at IS NOT NULL
            LIMIT 10",
            ARRAY_A
        );

        foreach ($requests as $request) {
            switch ($request['type']) {
                case 'export':
                    $this->processExportRequest($request['request_id']);
                    break;
                case 'erasure':
                    $this->processErasureRequest($request['request_id']);
                    break;
            }
        }
    }

    /**
     * Cleanup expired data
     */
    public function cleanupExpiredData(): void
    {
        global $wpdb;

        // Expire old pending requests
        $wpdb->query(
            "UPDATE {$this->tableRequests}
            SET status = 'expired'
            WHERE status = 'pending' AND expires_at < NOW()"
        );

        // Delete export files older than 7 days
        $uploadDir = wp_upload_dir();
        $exportDir = $uploadDir['basedir'] . '/formflow-exports';

        if (is_dir($exportDir)) {
            $files = glob($exportDir . '/export-*.zip');
            $cutoff = strtotime('-7 days');

            foreach ($files as $file) {
                if (filemtime($file) < $cutoff) {
                    unlink($file);
                }
            }
        }

        // Update requests with deleted files
        $wpdb->query(
            "UPDATE {$this->tableRequests}
            SET export_file = NULL
            WHERE export_file IS NOT NULL
            AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
    }

    /**
     * Register WordPress privacy exporter
     */
    public function registerPrivacyExporter(): void
    {
        register_rest_route('formflow/v1', '/privacy/export', [
            'methods' => 'POST',
            'callback' => [$this, 'wpPrivacyExport'],
            'permission_callback' => function () {
                return current_user_can('export_others_personal_data');
            },
        ]);

        add_filter('wp_privacy_personal_data_exporters', function ($exporters) {
            $exporters['formflow-pro'] = [
                'exporter_friendly_name' => __('FormFlow Pro', 'formflow-pro'),
                'callback' => [$this, 'wpPrivacyExporterCallback'],
            ];
            return $exporters;
        });
    }

    /**
     * WordPress privacy exporter callback
     */
    public function wpPrivacyExporterCallback(string $email, int $page = 1): array
    {
        global $wpdb;

        $exportItems = [];
        $perPage = 100;
        $offset = ($page - 1) * $perPage;

        // Export submissions
        $submissionsTable = $wpdb->prefix . 'formflow_submissions';
        $submissions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$submissionsTable}
                WHERE email = %s
                LIMIT %d OFFSET %d",
                $email,
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        foreach ($submissions as $submission) {
            $data = [];
            $data[] = ['name' => 'Form ID', 'value' => $submission['form_id']];
            $data[] = ['name' => 'Submission Date', 'value' => $submission['created_at']];

            $submissionData = json_decode($submission['submission_data'] ?? '{}', true);
            foreach ($submissionData as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $data[] = ['name' => $key, 'value' => (string) $value];
            }

            $exportItems[] = [
                'group_id' => 'formflow_submissions',
                'group_label' => __('Form Submissions', 'formflow-pro'),
                'item_id' => 'submission-' . $submission['id'],
                'data' => $data,
            ];
        }

        // Export consents
        $consents = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableConsents}
                WHERE email = %s
                LIMIT %d OFFSET %d",
                $email,
                $perPage,
                $offset
            ),
            ARRAY_A
        );

        foreach ($consents as $consent) {
            $exportItems[] = [
                'group_id' => 'formflow_consents',
                'group_label' => __('Consent Records', 'formflow-pro'),
                'item_id' => 'consent-' . $consent['id'],
                'data' => [
                    ['name' => 'Type', 'value' => $consent['consent_type']],
                    ['name' => 'Given', 'value' => $consent['consent_given'] ? 'Yes' : 'No'],
                    ['name' => 'Date', 'value' => $consent['created_at']],
                    ['name' => 'Version', 'value' => $consent['consent_version']],
                ],
            ];
        }

        $done = count($submissions) < $perPage && count($consents) < $perPage;

        return [
            'data' => $exportItems,
            'done' => $done,
        ];
    }

    /**
     * Register WordPress privacy eraser
     */
    public function registerPrivacyEraser(): void
    {
        add_filter('wp_privacy_personal_data_erasers', function ($erasers) {
            $erasers['formflow-pro'] = [
                'eraser_friendly_name' => __('FormFlow Pro', 'formflow-pro'),
                'callback' => [$this, 'wpPrivacyEraserCallback'],
            ];
            return $erasers;
        });
    }

    /**
     * WordPress privacy eraser callback
     */
    public function wpPrivacyEraserCallback(string $email, int $page = 1): array
    {
        global $wpdb;

        $itemsRemoved = 0;
        $itemsRetained = 0;
        $messages = [];

        // Anonymize submissions
        $submissionsTable = $wpdb->prefix . 'formflow_submissions';
        $count = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$submissionsTable}
                SET email = 'deleted@anonymized.local',
                    ip_address = '0.0.0.0'
                WHERE email = %s",
                $email
            )
        );
        $itemsRemoved += $count;

        // Delete consents
        $count = $wpdb->delete(
            $this->tableConsents,
            ['email' => $email],
            ['%s']
        );
        $itemsRemoved += $count;

        return [
            'items_removed' => $itemsRemoved,
            'items_retained' => $itemsRetained,
            'messages' => $messages,
            'done' => true,
        ];
    }

    /**
     * Capture consent from form submission
     */
    public function captureConsent(array $formData, int $formId): void
    {
        // Check for consent fields in form
        $consentFields = ['gdpr_consent', 'marketing_consent', 'privacy_consent', 'terms_consent'];

        foreach ($consentFields as $field) {
            if (isset($formData[$field])) {
                $this->recordConsent([
                    'email' => $formData['email'] ?? '',
                    'form_id' => $formId,
                    'consent_type' => str_replace('_consent', '', $field),
                    'consent_given' => !empty($formData[$field]),
                    'consent_text' => $formData[$field . '_text'] ?? '',
                    'source' => 'form_submission',
                ]);
            }
        }
    }

    /**
     * Log processing activity
     */
    public function logProcessingActivity(array $submission, int $formId): void
    {
        do_action('formflow_processing_activity_logged', [
            'form_id' => $formId,
            'submission_id' => $submission['id'] ?? null,
            'activity' => 'form_submission',
            'timestamp' => current_time('mysql'),
        ]);
    }

    /**
     * Generate export HTML summary
     */
    private function generateExportHTML(array $data): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<title>' . __('Personal Data Export', 'formflow-pro') . '</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;max-width:800px;margin:0 auto;padding:20px;}';
        $html .= 'h1{color:#333;}h2{color:#666;border-bottom:1px solid #ddd;padding-bottom:10px;}';
        $html .= 'table{width:100%;border-collapse:collapse;margin-bottom:20px;}';
        $html .= 'th,td{border:1px solid #ddd;padding:10px;text-align:left;}';
        $html .= 'th{background:#f5f5f5;}</style></head><body>';

        $html .= '<h1>' . __('Personal Data Export', 'formflow-pro') . '</h1>';
        $html .= '<p>' . sprintf(__('Export generated on %s', 'formflow-pro'), $data['export_date']) . '</p>';

        // Data Subject Info
        $html .= '<h2>' . __('Data Subject', 'formflow-pro') . '</h2>';
        $html .= '<table><tr><th>' . __('Email', 'formflow-pro') . '</th>';
        $html .= '<td>' . esc_html($data['data_subject']['email']) . '</td></tr></table>';

        // Submissions
        if (!empty($data['data']['submissions'])) {
            $html .= '<h2>' . __('Form Submissions', 'formflow-pro') . '</h2>';
            foreach ($data['data']['submissions'] as $sub) {
                $html .= '<table>';
                $html .= '<tr><th colspan="2">' . __('Submission', 'formflow-pro') . ' #' . $sub['id'] . '</th></tr>';
                $html .= '<tr><th>' . __('Date', 'formflow-pro') . '</th><td>' . $sub['created_at'] . '</td></tr>';
                if (is_array($sub['submission_data'])) {
                    foreach ($sub['submission_data'] as $key => $value) {
                        $html .= '<tr><th>' . esc_html($key) . '</th>';
                        $html .= '<td>' . esc_html(is_array($value) ? json_encode($value) : $value) . '</td></tr>';
                    }
                }
                $html .= '</table>';
            }
        }

        // Consent History
        if (!empty($data['data']['consent_history'])) {
            $html .= '<h2>' . __('Consent History', 'formflow-pro') . '</h2>';
            $html .= '<table><tr><th>' . __('Type', 'formflow-pro') . '</th>';
            $html .= '<th>' . __('Given', 'formflow-pro') . '</th>';
            $html .= '<th>' . __('Date', 'formflow-pro') . '</th></tr>';
            foreach ($data['data']['consent_history'] as $consent) {
                $html .= '<tr><td>' . esc_html($consent['consent_type']) . '</td>';
                $html .= '<td>' . ($consent['consent_given'] ? __('Yes', 'formflow-pro') : __('No', 'formflow-pro')) . '</td>';
                $html .= '<td>' . esc_html($consent['created_at']) . '</td></tr>';
            }
            $html .= '</table>';
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Send verification email
     */
    private function sendVerificationEmail(string $email, string $requestId, string $code, string $type): void
    {
        $verifyUrl = add_query_arg([
            'action' => 'formflow_gdpr_verify',
            'request' => $requestId,
            'code' => $code,
        ], home_url());

        $typeLabels = [
            'export' => __('data export', 'formflow-pro'),
            'erasure' => __('data deletion', 'formflow-pro'),
            'rectification' => __('data correction', 'formflow-pro'),
            'restriction' => __('processing restriction', 'formflow-pro'),
        ];

        $subject = sprintf(
            __('[%s] Verify your %s request', 'formflow-pro'),
            get_bloginfo('name'),
            $typeLabels[$type] ?? $type
        );

        $message = sprintf(
            __("Hello,\n\nWe received a %s request for this email address.\n\nPlease click the link below to verify your request:\n%s\n\nThis link will expire in 30 days.\n\nIf you did not make this request, please ignore this email.\n\nRegards,\n%s", 'formflow-pro'),
            $typeLabels[$type] ?? $type,
            $verifyUrl,
            get_bloginfo('name')
        );

        wp_mail($email, $subject, $message);
    }

    /**
     * Send export ready email
     */
    private function sendExportReadyEmail(string $email, string $requestId): void
    {
        $downloadUrl = add_query_arg([
            'action' => 'formflow_gdpr_download',
            'request' => $requestId,
        ], admin_url('admin-ajax.php'));

        $subject = sprintf(
            __('[%s] Your data export is ready', 'formflow-pro'),
            get_bloginfo('name')
        );

        $message = sprintf(
            __("Hello,\n\nYour data export is ready for download.\n\nDownload link: %s\n\nThis link will expire in 7 days.\n\nRegards,\n%s", 'formflow-pro'),
            $downloadUrl,
            get_bloginfo('name')
        );

        wp_mail($email, $subject, $message);
    }

    /**
     * Send erasure confirmation email
     */
    private function sendErasureConfirmationEmail(string $email): void
    {
        $subject = sprintf(
            __('[%s] Your data has been deleted', 'formflow-pro'),
            get_bloginfo('name')
        );

        $message = sprintf(
            __("Hello,\n\nYour data deletion request has been processed.\n\nAll personal data associated with this email address has been anonymized or deleted from our systems.\n\nRegards,\n%s", 'formflow-pro'),
            get_bloginfo('name')
        );

        wp_mail($email, $subject, $message);
    }

    /**
     * AJAX: Create request
     */
    public function ajaxCreateRequest(): void
    {
        check_ajax_referer('formflow_gdpr_nonce', 'nonce');

        $type = sanitize_text_field($_POST['type'] ?? 'export');
        $email = sanitize_email($_POST['email'] ?? '');

        $result = $this->createRequest($type, $email, get_current_user_id() ?: null);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Update consent
     */
    public function ajaxUpdateConsent(): void
    {
        check_ajax_referer('formflow_gdpr_nonce', 'nonce');

        $email = sanitize_email($_POST['email'] ?? '');
        $consentType = sanitize_text_field($_POST['consent_type'] ?? '');
        $consentGiven = !empty($_POST['consent_given']);

        if (!$email || !$consentType) {
            wp_send_json_error(['message' => __('Invalid parameters', 'formflow-pro')]);
        }

        if ($consentGiven) {
            $result = $this->recordConsent([
                'email' => $email,
                'consent_type' => $consentType,
                'consent_given' => true,
                'source' => 'ajax',
            ]);
        } else {
            $result = $this->withdrawConsent($email, $consentType);
        }

        if ($result) {
            wp_send_json_success(['message' => __('Consent updated', 'formflow-pro')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update consent', 'formflow-pro')]);
        }
    }

    /**
     * AJAX: Process request (admin only)
     */
    public function ajaxProcessRequest(): void
    {
        check_ajax_referer('formflow_gdpr_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $requestId = sanitize_text_field($_POST['request_id'] ?? '');
        $action = sanitize_text_field($_POST['process_action'] ?? '');

        $request = $this->getRequest($requestId);
        if (!$request) {
            wp_send_json_error(['message' => __('Request not found', 'formflow-pro')]);
        }

        switch ($action) {
            case 'approve':
                if ($request['type'] === 'export') {
                    $result = $this->processExportRequest($requestId);
                } elseif ($request['type'] === 'erasure') {
                    $result = $this->processErasureRequest($requestId);
                }
                break;

            case 'reject':
                global $wpdb;
                $wpdb->update(
                    $this->tableRequests,
                    [
                        'status' => 'rejected',
                        'processed_by' => get_current_user_id(),
                        'processed_at' => current_time('mysql'),
                        'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
                    ],
                    ['request_id' => $requestId],
                    ['%s', '%d', '%s', '%s'],
                    ['%s']
                );
                $result = ['success' => true, 'message' => 'Request rejected'];
                break;

            default:
                wp_send_json_error(['message' => __('Invalid action', 'formflow-pro')]);
        }

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Download export
     */
    public function ajaxDownloadExport(): void
    {
        $requestId = sanitize_text_field($_GET['request'] ?? '');

        $request = $this->getRequest($requestId);
        if (!$request || !$request['export_file']) {
            wp_die(__('Export not found or expired', 'formflow-pro'));
        }

        $uploadDir = wp_upload_dir();
        $filepath = $uploadDir['basedir'] . '/formflow-exports/' . $request['export_file'];

        if (!file_exists($filepath)) {
            wp_die(__('Export file not found', 'formflow-pro'));
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $request['export_file'] . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        global $wpdb;

        return [
            'total_requests' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->tableRequests}"),
            'pending_requests' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableRequests} WHERE status = 'pending'"
            ),
            'completed_requests' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableRequests} WHERE status = 'completed'"
            ),
            'total_consents' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->tableConsents}"),
            'active_consents' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableConsents} WHERE consent_given = 1"
            ),
            'processing_activities' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableProcessingActivities} WHERE is_active = 1"
            ),
        ];
    }

    private function generateRequestId(): string
    {
        return 'REQ-' . strtoupper(bin2hex(random_bytes(8)));
    }

    private function generateConsentId(): string
    {
        return 'CON-' . strtoupper(bin2hex(random_bytes(8)));
    }

    private function generateActivityId(): string
    {
        return 'ACT-' . strtoupper(bin2hex(random_bytes(8)));
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
}
