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
     * Create all database tables.
     *
     * @since 2.0.0
     */
    public function create_tables()
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Get current database version
        $current_version = get_option('formflow_db_version', '0.0.0');

        // Only run if version changed
        if (version_compare($current_version, $this->db_version, '>=')) {
            return;
        }

        // Load and run migration
        $this->run_migration($this->db_version);

        // Update database version
        update_option('formflow_db_version', $this->db_version);
    }

    /**
     * Run migration for specific version.
     *
     * @since 2.0.0
     * @param string $version Version to migrate to.
     */
    private function run_migration($version)
    {
        $migration_file = FORMFLOW_PATH . "includes/database/migrations/v{$version}.php";

        if (!file_exists($migration_file)) {
            return;
        }

        require_once $migration_file;

        $class_name = "FormFlowPro\\Database\\Migrations\\Migration_v" . str_replace('.', '_', $version);

        if (class_exists($class_name)) {
            $migration = new $class_name();
            $migration->up();
        }
    }

    /**
     * Drop all database tables.
     *
     * CAUTION: This will delete all data!
     * Only used during uninstall.
     *
     * @since 2.0.0
     */
    public function drop_tables()
    {
        $tables = [
            $this->wpdb->prefix . 'formflow_forms',
            $this->wpdb->prefix . 'formflow_submissions',
            $this->wpdb->prefix . 'formflow_submission_meta',
            $this->wpdb->prefix . 'formflow_logs',
            $this->wpdb->prefix . 'formflow_queue',
            $this->wpdb->prefix . 'formflow_templates',
            $this->wpdb->prefix . 'formflow_analytics',
            $this->wpdb->prefix . 'formflow_webhooks',
            $this->wpdb->prefix . 'formflow_cache',
            $this->wpdb->prefix . 'formflow_settings',
        ];

        foreach ($tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS {$table}");
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
