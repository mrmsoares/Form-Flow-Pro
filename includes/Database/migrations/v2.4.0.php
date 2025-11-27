<?php

declare(strict_types=1);

/**
 * Database Migration v2.4.0
 *
 * Creates all Enterprise module database tables.
 *
 * @package FormFlowPro\Database\Migrations
 * @since 2.4.0
 */

namespace FormFlowPro\Database\Migrations;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration v2.4.0 Class
 *
 * Enterprise Tables:
 * - Automation: workflows, workflow_executions, workflow_logs
 * - Payments: transactions, subscriptions, invoices
 * - SSO: sso_sessions, sso_identity_links
 * - Security: audit_logs, security_logs, 2fa_tokens, gdpr_requests
 * - Reporting: scheduled_reports, report_history
 * - Autentique: autentique_documents
 */
class Migration_v2_4_0
{
    /**
     * Run the migration
     *
     * @return bool
     */
    public static function up(): bool
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $success = true;

        // =====================================================
        // AUTOMATION MODULE TABLES
        // =====================================================

        // Workflows table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_workflows (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            trigger_type varchar(50) NOT NULL DEFAULT 'form_submission',
            trigger_config longtext DEFAULT NULL COMMENT 'JSON configuration',
            conditions longtext DEFAULT NULL COMMENT 'JSON conditions array',
            actions longtext DEFAULT NULL COMMENT 'JSON actions array',
            status enum('draft','active','paused','archived') NOT NULL DEFAULT 'draft',
            priority int(11) NOT NULL DEFAULT 10,
            execution_count bigint(20) unsigned NOT NULL DEFAULT 0,
            last_executed_at datetime DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_trigger_type (trigger_type),
            KEY idx_created_at (created_at DESC)
        ) $charset_collate;";
        dbDelta($sql);

        // Workflow executions table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_workflow_executions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            workflow_id bigint(20) unsigned NOT NULL,
            submission_id varchar(36) DEFAULT NULL,
            trigger_data longtext DEFAULT NULL COMMENT 'JSON trigger context',
            status enum('running','completed','failed','cancelled') NOT NULL DEFAULT 'running',
            actions_executed int(11) NOT NULL DEFAULT 0,
            actions_failed int(11) NOT NULL DEFAULT 0,
            error_message text DEFAULT NULL,
            duration_ms int(11) unsigned DEFAULT NULL,
            started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_workflow_id (workflow_id),
            KEY idx_submission_id (submission_id),
            KEY idx_status (status),
            KEY idx_started_at (started_at DESC)
        ) $charset_collate;";
        dbDelta($sql);

        // Workflow logs table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_workflow_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            workflow_id bigint(20) unsigned NOT NULL,
            execution_id bigint(20) unsigned DEFAULT NULL,
            submission_id varchar(36) DEFAULT NULL,
            action_type varchar(50) DEFAULT NULL,
            status enum('pending','running','completed','failed','skipped') NOT NULL DEFAULT 'pending',
            message text DEFAULT NULL,
            context longtext DEFAULT NULL COMMENT 'JSON context data',
            duration_ms int(11) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_workflow_id (workflow_id),
            KEY idx_execution_id (execution_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at DESC)
        ) $charset_collate;";
        dbDelta($sql);

        // =====================================================
        // PAYMENTS MODULE TABLES
        // =====================================================

        // Transactions table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_transactions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            submission_id varchar(36) DEFAULT NULL,
            form_id varchar(36) DEFAULT NULL,
            gateway varchar(50) NOT NULL COMMENT 'stripe, paypal, woocommerce',
            gateway_transaction_id varchar(255) DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'BRL',
            status enum('pending','processing','completed','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
            payment_method varchar(50) DEFAULT NULL,
            customer_email varchar(255) DEFAULT NULL,
            customer_name varchar(255) DEFAULT NULL,
            metadata longtext DEFAULT NULL COMMENT 'JSON metadata',
            error_message text DEFAULT NULL,
            refund_amount decimal(10,2) DEFAULT NULL,
            refunded_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_submission_id (submission_id),
            KEY idx_gateway (gateway),
            KEY idx_gateway_tx_id (gateway_transaction_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at DESC)
        ) $charset_collate;";
        dbDelta($sql);

        // Subscriptions table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_subscriptions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            form_id varchar(36) DEFAULT NULL,
            gateway varchar(50) NOT NULL,
            gateway_subscription_id varchar(255) DEFAULT NULL,
            plan_id varchar(100) NOT NULL,
            plan_name varchar(255) DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'BRL',
            interval_type enum('day','week','month','year') NOT NULL DEFAULT 'month',
            interval_count int(11) NOT NULL DEFAULT 1,
            status enum('active','paused','cancelled','expired','past_due') NOT NULL DEFAULT 'active',
            trial_ends_at datetime DEFAULT NULL,
            current_period_start datetime DEFAULT NULL,
            current_period_end datetime DEFAULT NULL,
            cancelled_at datetime DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_gateway_sub_id (gateway_subscription_id),
            KEY idx_status (status),
            KEY idx_plan_id (plan_id)
        ) $charset_collate;";
        dbDelta($sql);

        // Invoices table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_invoices (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            invoice_number varchar(50) NOT NULL,
            transaction_id bigint(20) unsigned DEFAULT NULL,
            subscription_id bigint(20) unsigned DEFAULT NULL,
            customer_email varchar(255) NOT NULL,
            customer_name varchar(255) DEFAULT NULL,
            customer_address text DEFAULT NULL,
            subtotal decimal(10,2) NOT NULL,
            tax_amount decimal(10,2) NOT NULL DEFAULT 0,
            total decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'BRL',
            status enum('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
            items longtext NOT NULL COMMENT 'JSON line items',
            notes text DEFAULT NULL,
            due_date date DEFAULT NULL,
            paid_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            pdf_path varchar(500) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_invoice_number (invoice_number),
            KEY idx_transaction_id (transaction_id),
            KEY idx_subscription_id (subscription_id),
            KEY idx_status (status),
            KEY idx_customer_email (customer_email)
        ) $charset_collate;";
        dbDelta($sql);

        // =====================================================
        // SSO MODULE TABLES
        // =====================================================

        // SSO Sessions table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_sso_sessions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            provider varchar(50) NOT NULL COMMENT 'saml, ldap, oauth',
            provider_user_id varchar(255) DEFAULT NULL,
            session_token varchar(255) NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(500) DEFAULT NULL,
            attributes longtext DEFAULT NULL COMMENT 'JSON provider attributes',
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_activity_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_session_token (session_token),
            KEY idx_provider (provider),
            KEY idx_expires_at (expires_at)
        ) $charset_collate;";
        dbDelta($sql);

        // SSO Identity Links table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_sso_identity_links (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            provider varchar(50) NOT NULL,
            provider_user_id varchar(255) NOT NULL,
            provider_email varchar(255) DEFAULT NULL,
            provider_name varchar(255) DEFAULT NULL,
            access_token text DEFAULT NULL,
            refresh_token text DEFAULT NULL,
            token_expires_at datetime DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            linked_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idx_provider_user (provider, provider_user_id),
            KEY idx_user_id (user_id),
            KEY idx_provider_email (provider, provider_email)
        ) $charset_collate;";
        dbDelta($sql);

        // =====================================================
        // SECURITY MODULE TABLES
        // =====================================================

        // Audit Logs table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_audit_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            action varchar(100) NOT NULL,
            object_type varchar(50) DEFAULT NULL COMMENT 'form, submission, user, etc',
            object_id varchar(36) DEFAULT NULL,
            old_values longtext DEFAULT NULL COMMENT 'JSON before state',
            new_values longtext DEFAULT NULL COMMENT 'JSON after state',
            details text DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(500) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_action (action),
            KEY idx_object (object_type, object_id),
            KEY idx_created_at (created_at DESC)
        ) $charset_collate;";
        dbDelta($sql);

        // Security Logs table (blocked attempts, rate limiting, etc)
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_security_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL COMMENT 'blocked, rate_limited, suspicious',
            action varchar(100) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            details text DEFAULT NULL,
            risk_score int(11) DEFAULT NULL,
            blocked tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_ip_address (ip_address),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at DESC)
        ) $charset_collate;";
        dbDelta($sql);

        // 2FA Tokens table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_2fa_tokens (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            method varchar(20) NOT NULL DEFAULT 'totp' COMMENT 'totp, email, sms',
            secret varchar(255) DEFAULT NULL COMMENT 'Encrypted TOTP secret',
            backup_codes text DEFAULT NULL COMMENT 'JSON array of hashed backup codes',
            is_enabled tinyint(1) NOT NULL DEFAULT 0,
            verified_at datetime DEFAULT NULL,
            last_used_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_user_method (user_id, method),
            KEY idx_user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql);

        // GDPR Requests table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_gdpr_requests (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            request_type enum('export','delete','consent_withdraw','access') NOT NULL,
            email varchar(255) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            status enum('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
            verification_token varchar(255) DEFAULT NULL,
            verified_at datetime DEFAULT NULL,
            data_file_path varchar(500) DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            notes text DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email (email),
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_request_type (request_type),
            KEY idx_verification_token (verification_token)
        ) $charset_collate;";
        dbDelta($sql);

        // =====================================================
        // REPORTING MODULE TABLES
        // =====================================================

        // Scheduled Reports table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_scheduled_reports (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            report_type varchar(50) NOT NULL COMMENT 'submissions, analytics, audit',
            template varchar(50) DEFAULT 'default',
            filters longtext DEFAULT NULL COMMENT 'JSON filter criteria',
            schedule_type enum('daily','weekly','monthly','quarterly') NOT NULL DEFAULT 'weekly',
            schedule_day int(11) DEFAULT NULL COMMENT 'Day of week/month',
            schedule_time time DEFAULT '09:00:00',
            recipients text NOT NULL COMMENT 'JSON array of emails',
            format enum('pdf','csv','excel') NOT NULL DEFAULT 'pdf',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            last_run_at datetime DEFAULT NULL,
            next_run_at datetime DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_is_active (is_active),
            KEY idx_next_run (next_run_at),
            KEY idx_report_type (report_type)
        ) $charset_collate;";
        dbDelta($sql);

        // Report History table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_report_history (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scheduled_report_id bigint(20) unsigned DEFAULT NULL,
            report_type varchar(50) NOT NULL,
            name varchar(255) NOT NULL,
            parameters longtext DEFAULT NULL COMMENT 'JSON generation parameters',
            file_path varchar(500) DEFAULT NULL,
            file_size bigint(20) unsigned DEFAULT NULL,
            format varchar(10) NOT NULL DEFAULT 'pdf',
            status enum('generating','completed','failed','expired') NOT NULL DEFAULT 'generating',
            error_message text DEFAULT NULL,
            recipients_notified int(11) NOT NULL DEFAULT 0,
            generated_by bigint(20) unsigned DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_scheduled_report_id (scheduled_report_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at DESC),
            KEY idx_expires_at (expires_at)
        ) $charset_collate;";
        dbDelta($sql);

        // =====================================================
        // AUTENTIQUE MODULE TABLE
        // =====================================================

        // Autentique Documents table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_autentique_documents (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            submission_id varchar(36) NOT NULL,
            form_id varchar(36) DEFAULT NULL,
            document_id varchar(100) NOT NULL COMMENT 'Autentique document UUID',
            document_name varchar(255) DEFAULT NULL,
            status enum('pending','sent','viewed','signed','refused','expired','cancelled') NOT NULL DEFAULT 'pending',
            signer_email varchar(255) NOT NULL,
            signer_name varchar(255) DEFAULT NULL,
            signer_cpf varchar(14) DEFAULT NULL,
            signature_url varchar(500) DEFAULT NULL,
            signed_file_url varchar(500) DEFAULT NULL,
            signed_file_path varchar(500) DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            viewed_at datetime DEFAULT NULL,
            signed_at datetime DEFAULT NULL,
            refused_at datetime DEFAULT NULL,
            refuse_reason text DEFAULT NULL,
            webhook_data longtext DEFAULT NULL COMMENT 'JSON last webhook payload',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_document_id (document_id),
            KEY idx_submission_id (submission_id),
            KEY idx_form_id (form_id),
            KEY idx_status (status),
            KEY idx_signer_email (signer_email),
            KEY idx_created_at (created_at DESC)
        ) $charset_collate;";
        dbDelta($sql);

        // =====================================================
        // MARKETPLACE MODULE TABLE
        // =====================================================

        // Installed Extensions table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_extensions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            extension_id varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            version varchar(20) NOT NULL,
            author varchar(255) DEFAULT NULL,
            description text DEFAULT NULL,
            license_key varchar(255) DEFAULT NULL,
            license_status enum('valid','invalid','expired','none') NOT NULL DEFAULT 'none',
            license_expires_at datetime DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 0,
            settings longtext DEFAULT NULL COMMENT 'JSON extension settings',
            installed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_extension_id (extension_id),
            KEY idx_is_active (is_active),
            KEY idx_license_status (license_status)
        ) $charset_collate;";
        dbDelta($sql);

        // =====================================================
        // NOTIFICATIONS MODULE TABLE
        // =====================================================

        // Notification Queue table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_notification_queue (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            channel varchar(50) NOT NULL COMMENT 'email, sms, slack, teams, push',
            recipient varchar(255) NOT NULL,
            subject varchar(500) DEFAULT NULL,
            content longtext NOT NULL,
            template_id varchar(100) DEFAULT NULL,
            variables longtext DEFAULT NULL COMMENT 'JSON template variables',
            priority enum('high','normal','low') NOT NULL DEFAULT 'normal',
            status enum('pending','sending','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
            attempts int(11) NOT NULL DEFAULT 0,
            max_attempts int(11) NOT NULL DEFAULT 3,
            error_message text DEFAULT NULL,
            external_id varchar(255) DEFAULT NULL COMMENT 'Provider message ID',
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_channel (channel),
            KEY idx_status (status),
            KEY idx_priority_status (priority, status),
            KEY idx_scheduled_at (scheduled_at),
            KEY idx_created_at (created_at DESC)
        ) $charset_collate;";
        dbDelta($sql);

        // Update version
        update_option('formflow_db_version', '2.4.0');

        return $success;
    }

    /**
     * Rollback the migration
     *
     * @return bool
     */
    public static function down(): bool
    {
        global $wpdb;

        $tables = [
            'formflow_workflows',
            'formflow_workflow_executions',
            'formflow_workflow_logs',
            'formflow_transactions',
            'formflow_subscriptions',
            'formflow_invoices',
            'formflow_sso_sessions',
            'formflow_sso_identity_links',
            'formflow_audit_logs',
            'formflow_security_logs',
            'formflow_2fa_tokens',
            'formflow_gdpr_requests',
            'formflow_scheduled_reports',
            'formflow_report_history',
            'formflow_autentique_documents',
            'formflow_extensions',
            'formflow_notification_queue',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }

        update_option('formflow_db_version', '2.3.0');

        return true;
    }

    /**
     * Get migration version
     *
     * @return string
     */
    public static function version(): string
    {
        return '2.4.0';
    }

    /**
     * Get migration description
     *
     * @return string
     */
    public static function description(): string
    {
        return 'Add Enterprise module tables (Automation, Payments, SSO, Security, Reporting, Autentique, Marketplace, Notifications)';
    }
}
