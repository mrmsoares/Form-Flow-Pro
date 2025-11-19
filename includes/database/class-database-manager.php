<?php

declare(strict_types=1);

namespace FormFlowPro\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Manager
 */
class DatabaseManager
{
    public function create_tables(): void
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Queue table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}formflow_queue (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_type varchar(100) NOT NULL,
            job_data longtext NOT NULL,
            priority int(11) NOT NULL DEFAULT 10,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts int(11) NOT NULL DEFAULT 0,
            error_message text,
            created_at datetime NOT NULL,
            updated_at datetime,
            completed_at datetime,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY priority (priority),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql);
    }
}
