<?php
/**
 * Database Migration v2.0.0
 *
 * Creates all initial database tables for FormFlow Pro Enterprise.
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

namespace FormFlowPro\Database\Migrations;

/**
 * Migration v2.0.0 class.
 *
 * @since 2.0.0
 */
class Migration_v2_0_0
{
    /**
     * WordPress database object.
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Charset collate.
     *
     * @var string
     */
    private $charset_collate;

    /**
     * Constructor.
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
    }

    /**
     * Run migration up (create tables).
     *
     * @since 2.0.0
     */
    public function up()
    {
        $this->create_forms_table();
        $this->create_submissions_table();
        $this->create_submission_meta_table();
        $this->create_logs_table();
        $this->create_queue_table();
        $this->create_templates_table();
        $this->create_analytics_table();
        $this->create_webhooks_table();
        $this->create_cache_table();
        $this->create_settings_table();

        // Insert default data
        $this->seed_default_data();
    }

    /**
     * Create forms table.
     *
     * @since 2.0.0
     */
    private function create_forms_table()
    {
        $table_name = $this->wpdb->prefix . 'formflow_forms';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id VARCHAR(36) NOT NULL PRIMARY KEY COMMENT 'UUID',
            name VARCHAR(255) NOT NULL COMMENT 'Form display name',
            elementor_form_id VARCHAR(100) NOT NULL COMMENT 'Elementor form ID',
            status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
            settings LONGTEXT NOT NULL COMMENT 'JSON: form configuration',
            pdf_template_id VARCHAR(36) DEFAULT NULL COMMENT 'FK to templates',
            email_template_id VARCHAR(36) DEFAULT NULL COMMENT 'FK to templates',
            autentique_enabled TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'FK to wp_users',

            INDEX idx_elementor_id (elementor_form_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at DESC)
        ) {$this->charset_collate};";

        dbDelta($sql);
    }

    /**
     * Create submissions table.
     *
     * @since 2.0.0
     */
    private function create_submissions_table()
    {
        $table_name = $this->wpdb->prefix . 'formflow_submissions';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id VARCHAR(36) NOT NULL PRIMARY KEY COMMENT 'UUID',
            form_id VARCHAR(36) NOT NULL COMMENT 'FK to forms',

            status ENUM(
                'pending',
                'processing',
                'pdf_generated',
                'autentique_sent',
                'completed',
                'failed'
            ) NOT NULL DEFAULT 'pending',

            data LONGTEXT NOT NULL COMMENT 'Compressed JSON of form fields',
            data_compressed TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Is data compressed?',

            pdf_path VARCHAR(500) DEFAULT NULL COMMENT 'Path to generated PDF',
            pdf_size INT UNSIGNED DEFAULT NULL COMMENT 'PDF file size in bytes',

            autentique_document_id VARCHAR(100) DEFAULT NULL COMMENT 'Autentique document UUID',
            autentique_status VARCHAR(50) DEFAULT NULL COMMENT 'Autentique document status',
            autentique_signed_at DATETIME DEFAULT NULL COMMENT 'When document was signed',

            email_sent TINYINT(1) NOT NULL DEFAULT 0,
            email_sent_at DATETIME DEFAULT NULL,
            email_opened TINYINT(1) NOT NULL DEFAULT 0,
            email_opened_at DATETIME DEFAULT NULL,

            ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IPv4 or IPv6',
            user_agent VARCHAR(500) DEFAULT NULL,
            referrer_url VARCHAR(500) DEFAULT NULL,

            processed_at DATETIME DEFAULT NULL,
            processing_time_ms INT UNSIGNED DEFAULT NULL COMMENT 'Total processing time',
            retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_form_status (form_id, status),
            INDEX idx_status_created (status, created_at DESC),
            INDEX idx_created_at (created_at DESC),
            INDEX idx_autentique_id (autentique_document_id),
            INDEX idx_ip_created (ip_address, created_at DESC),
            INDEX idx_list_view (id, form_id, status, created_at, email_sent)
        ) {$this->charset_collate} ROW_FORMAT=COMPRESSED;";

        dbDelta($sql);
    }

    /**
     * Create submission meta table.
     *
     * @since 2.0.0
     */
    private function create_submission_meta_table()
    {
        $table_name = $this->wpdb->prefix . 'formflow_submission_meta';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            meta_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            submission_id VARCHAR(36) NOT NULL COMMENT 'FK to submissions',
            meta_key VARCHAR(255) NOT NULL,
            meta_value LONGTEXT DEFAULT NULL,

            INDEX idx_submission_key (submission_id, meta_key),
            INDEX idx_key_value (meta_key, meta_value(191))
        ) {$this->charset_collate};";

        dbDelta($sql);
    }

    /**
     * Create logs table.
     *
     * @since 2.0.0
     */
    private function create_logs_table()
    {
        $table_name = $this->wpdb->prefix . 'formflow_logs';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            submission_id VARCHAR(36) DEFAULT NULL COMMENT 'FK to submissions (nullable)',

            level ENUM('debug', 'info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            context LONGTEXT DEFAULT NULL COMMENT 'JSON: additional context',

            category VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'pdf, autentique, email, queue',

            request_id VARCHAR(36) DEFAULT NULL COMMENT 'Trace ID for request',

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_submission (submission_id),
            INDEX idx_level_created (level, created_at DESC),
            INDEX idx_category_created (category, created_at DESC),
            INDEX idx_request_id (request_id)
        ) {$this->charset_collate};";

        dbDelta($sql);
    }

    /**
     * Create queue table.
     *
     * @since 2.0.0
     */
    private function create_queue_table()
    {
        $table_name = $this->wpdb->prefix . 'formflow_queue';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

            job_type VARCHAR(50) NOT NULL COMMENT 'generate_pdf, send_autentique, send_email',
            job_data LONGTEXT NOT NULL COMMENT 'JSON: job parameters',

            priority ENUM('high', 'medium', 'low') NOT NULL DEFAULT 'medium',
            status ENUM('pending', 'processing', 'completed', 'failed', 'dead_letter') NOT NULL DEFAULT 'pending',

            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,

            last_error TEXT DEFAULT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            scheduled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When to process',
            started_at DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,

            INDEX idx_worker_query (status, scheduled_at, priority),
            INDEX idx_job_type (job_type),
            INDEX idx_created_at (created_at DESC),
            INDEX idx_status (status)
        ) {$this->charset_collate};";

        dbDelta($sql);
    }

    /**
     * Create templates table.
     *
     * @since 2.0.0
     */
    private function create_templates_table()
    {
        $table_name = $this->wpdb->prefix . 'formflow_templates';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id VARCHAR(36) NOT NULL PRIMARY KEY COMMENT 'UUID',

            name VARCHAR(255) NOT NULL,
            type ENUM('pdf', 'email') NOT NULL,

            content LONGTEXT NOT NULL COMMENT 'Template HTML/structure',
            settings LONGTEXT DEFAULT NULL COMMENT 'JSON: template settings',

            pdf_orientation ENUM('portrait', 'landscape') DEFAULT 'portrait',
            pdf_page_size VARCHAR(20) DEFAULT 'A4',

            email_subject VARCHAR(500) DEFAULT NULL,
            email_from_name VARCHAR(255) DEFAULT NULL,
            email_from_email VARCHAR(255) DEFAULT NULL,

            is_default TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('active', 'inactive', 'draft') NOT NULL DEFAULT 'active',

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by BIGINT(20) UNSIGNED DEFAULT NULL,

            INDEX idx_type_status (type, status),
            INDEX idx_is_default (is_default)
        ) {$this->charset_collate};";

        dbDelta($sql);
    }

    /**
     * Create analytics table.
     *
     * @since 2.0.0
     */
    private function create_analytics_table()
    {
        $table_name = $this->wpdb->prefix . 'formflow_analytics';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

            form_id VARCHAR(36) DEFAULT NULL COMMENT 'FK to forms (nullable for global)',
            submission_id VARCHAR(36) DEFAULT NULL COMMENT 'FK to submissions (nullable)',

            metric_type VARCHAR(50) NOT NULL COMMENT 'conversion_rate, avg_time, etc',
            metric_value DECIMAL(10, 2) NOT NULL,
            metric_unit VARCHAR(20) DEFAULT NULL COMMENT 'seconds, percentage, count',

            dimensions LONGTEXT DEFAULT NULL COMMENT 'JSON: filtering dimensions',

            recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            period_start DATETIME NOT NULL COMMENT 'Start of aggregation period',
            period_end DATETIME NOT NULL COMMENT 'End of aggregation period',

            INDEX idx_form_metric_period (form_id, metric_type, period_start),
            INDEX idx_metric_type (metric_type),
            INDEX idx_period (period_start, period_end)
        ) {$this->charset_collate};";

        dbDelta($sql);
    }

    /**
     * Create webhooks table.
     *
     * @since 2.0.0
     */
    private function create_webhooks_table()
    {
        $table_name = $this->wpdb->prefix . 'formflow_webhooks';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

            name VARCHAR(255) NOT NULL,
            event VARCHAR(100) NOT NULL COMMENT 'submission.created, pdf.generated, etc',

            url VARCHAR(500) NOT NULL,
            method ENUM('POST', 'PUT', 'PATCH') NOT NULL DEFAULT 'POST',
            headers LONGTEXT DEFAULT NULL COMMENT 'JSON: custom headers',

            enabled TINYINT(1) NOT NULL DEFAULT 1,

            total_calls BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            failed_calls BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            last_called_at DATETIME DEFAULT NULL,
            last_status_code SMALLINT DEFAULT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_event_enabled (event, enabled)
        ) {$this->charset_collate};";

        dbDelta($sql);
    }

    /**
     * Create cache table.
     *
     * @since 2.0.0
     */
    private function create_cache_table()
    {
        $table_name = $this->wpdb->prefix . 'formflow_cache';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            cache_key VARCHAR(255) NOT NULL PRIMARY KEY,
            cache_value LONGTEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_expires (expires_at)
        ) {$this->charset_collate};";

        dbDelta($sql);
    }

    /**
     * Create settings table.
     *
     * @since 2.0.0
     */
    private function create_settings_table()
    {
        $table_name = $this->wpdb->prefix . 'formflow_settings';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,

            setting_key VARCHAR(255) NOT NULL UNIQUE,
            setting_value LONGTEXT DEFAULT NULL,

            autoload TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Load on every request?',

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_autoload (autoload)
        ) {$this->charset_collate};";

        dbDelta($sql);
    }

    /**
     * Seed default data.
     *
     * @since 2.0.0
     */
    private function seed_default_data()
    {
        // Insert default PDF template
        $this->wpdb->insert(
            $this->wpdb->prefix . 'formflow_templates',
            [
                'id' => 'default-pdf-template',
                'name' => 'Default PDF Template',
                'type' => 'pdf',
                'content' => '<html><body><h1>{{form_title}}</h1>{{fields}}</body></html>',
                'is_default' => 1,
                'status' => 'active',
            ]
        );

        // Insert default email template
        $this->wpdb->insert(
            $this->wpdb->prefix . 'formflow_templates',
            [
                'id' => 'default-email-template',
                'name' => 'Default Confirmation Email',
                'type' => 'email',
                'content' => '<h1>Thank you, {{name}}!</h1><p>Your submission has been received.</p>',
                'email_subject' => 'Form Submission Confirmation',
                'email_from_name' => get_bloginfo('name'),
                'email_from_email' => get_bloginfo('admin_email'),
                'is_default' => 1,
                'status' => 'active',
            ]
        );

        // Insert default settings
        $default_settings = [
            ['setting_key' => 'default_pdf_template', 'setting_value' => 'default-pdf-template'],
            ['setting_key' => 'default_email_template', 'setting_value' => 'default-email-template'],
            ['setting_key' => 'performance_mode', 'setting_value' => 'balanced'],
            ['setting_key' => 'cache_ttl', 'setting_value' => '3600'],
        ];

        foreach ($default_settings as $setting) {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'formflow_settings',
                $setting
            );
        }
    }

    /**
     * Run migration down (drop tables).
     *
     * @since 2.0.0
     */
    public function down()
    {
        $tables = [
            'formflow_forms',
            'formflow_submissions',
            'formflow_submission_meta',
            'formflow_logs',
            'formflow_queue',
            'formflow_templates',
            'formflow_analytics',
            'formflow_webhooks',
            'formflow_cache',
            'formflow_settings',
        ];

        foreach ($tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS {$this->wpdb->prefix}{$table}");
        }
    }
}
