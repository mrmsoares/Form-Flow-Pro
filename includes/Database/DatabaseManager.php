<?php

/**
 * Database Manager - Handles all database operations and migrations.
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

namespace FormFlowPro\Database;

/**
 * Database Manager class.
 *
 * Responsible for creating, updating, and managing database tables.
 *
 * @since 2.0.0
 */
class DatabaseManager
{
    /**
     * WordPress database object.
     *
     * @since 2.0.0
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Database version.
     *
     * @since 2.0.0
     * @var string
     */
    private $db_version;

    /**
     * Constructor.
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->db_version = FORMFLOW_DB_VERSION;
    }

    /**
     * All available migration versions in order.
     *
     * @since 2.4.0
     * @var array
     */
    private $migrations = [
        '2.0.0',
        '2.3.0',
        '2.4.0',
    ];

    /**
     * Create all database tables.
     *
     * Runs all pending migrations sequentially.
     *
     * @since 2.0.0
     * @since 2.4.0 Updated to run all pending migrations
     */
    public function create_tables()
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Get current database version
        $current_version = get_option('formflow_db_version', '0.0.0');

        // Run all pending migrations
        foreach ($this->migrations as $version) {
            if (version_compare($current_version, $version, '<')) {
                $this->run_migration($version);
            }
        }

        // Update database version to latest
        update_option('formflow_db_version', $this->db_version);
    }

    /**
     * Run migration for specific version.
     *
     * @since 2.0.0
     * @since 2.4.0 Updated path to use Database (capital D)
     * @param string $version Version to migrate to.
     */
    private function run_migration($version)
    {
        $migration_file = FORMFLOW_PATH . "includes/Database/migrations/v{$version}.php";

        if (!file_exists($migration_file)) {
            return;
        }

        require_once $migration_file;

        $class_name = "FormFlowPro\\Database\\Migrations\\Migration_v" . str_replace('.', '_', $version);

        if (class_exists($class_name)) {
            // Support both static and instance methods
            if (method_exists($class_name, 'up')) {
                if ((new \ReflectionMethod($class_name, 'up'))->isStatic()) {
                    $class_name::up();
                } else {
                    $migration = new $class_name();
                    $migration->up();
                }
            }
        }
    }

    /**
     * Drop all database tables.
     *
     * CAUTION: This will delete all data!
     * Only used during uninstall.
     *
     * @since 2.0.0
     * @since 2.4.0 Added Enterprise module tables
     */
    public function drop_tables()
    {
        $tables = [
            // Core tables (v2.0.0)
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
            // Integration sync (v2.3.0)
            'formflow_integration_sync',
            // Automation (v2.4.0)
            'formflow_workflows',
            'formflow_workflow_executions',
            'formflow_workflow_logs',
            // Payments (v2.4.0)
            'formflow_transactions',
            'formflow_subscriptions',
            'formflow_invoices',
            // SSO (v2.4.0)
            'formflow_sso_sessions',
            'formflow_sso_identity_links',
            // Security (v2.4.0)
            'formflow_audit_logs',
            'formflow_security_logs',
            'formflow_2fa_tokens',
            'formflow_gdpr_requests',
            // Reporting (v2.4.0)
            'formflow_scheduled_reports',
            'formflow_report_history',
            // Autentique (v2.4.0)
            'formflow_autentique_documents',
            // Marketplace (v2.4.0)
            'formflow_extensions',
            // Notifications (v2.4.0)
            'formflow_notification_queue',
        ];

        foreach ($tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS {$this->wpdb->prefix}{$table}");
        }

        delete_option('formflow_db_version');
    }

    /**
     * Get table name with prefix.
     *
     * @since 2.0.0
     * @param string $table Table name without prefix.
     * @return string Table name with prefix.
     */
    public function get_table_name($table)
    {
        return $this->wpdb->prefix . 'formflow_' . $table;
    }

    /**
     * Check if table exists.
     *
     * @since 2.0.0
     * @param string $table Table name without prefix.
     * @return bool True if table exists.
     */
    public function table_exists($table)
    {
        $table_name = $this->get_table_name($table);
        return $this->wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    }

    /**
     * Get database charset collate.
     *
     * @since 2.0.0
     * @return string Charset collate string.
     */
    public function get_charset_collate()
    {
        return $this->wpdb->get_charset_collate();
    }
}
