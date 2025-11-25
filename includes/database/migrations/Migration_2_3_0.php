<?php

declare(strict_types=1);

/**
 * Database Migration v2.3.0
 *
 * Adds integration sync tracking table.
 *
 * @package FormFlowPro\Database\Migrations
 * @since 2.3.0
 */

namespace FormFlowPro\Database\Migrations;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration v2.3.0 Class
 */
class Migration_2_3_0
{
    /**
     * Run the migration
     *
     * @return bool
     */
    public static function up(): bool
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Integration sync tracking table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_integration_sync (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            submission_id bigint(20) unsigned NOT NULL,
            integration_id varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            external_id varchar(255) DEFAULT NULL,
            error_message text DEFAULT NULL,
            synced_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_submission_integration (submission_id, integration_id),
            KEY idx_integration_status (integration_id, status),
            KEY idx_synced_at (synced_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Check if table was created
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $wpdb->prefix . 'formflow_integration_sync'
            )
        );

        if (!$table_exists) {
            return false;
        }

        // Update version
        update_option('formflow_db_version', '2.3.0');

        return true;
    }

    /**
     * Rollback the migration
     *
     * @return bool
     */
    public static function down(): bool
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}formflow_integration_sync");

        update_option('formflow_db_version', '2.2.0');

        return true;
    }

    /**
     * Get migration version
     *
     * @return string
     */
    public static function version(): string
    {
        return '2.3.0';
    }

    /**
     * Get migration description
     *
     * @return string
     */
    public static function description(): string
    {
        return 'Add integration sync tracking table for Enterprise Integrations';
    }
}
