<?php

/**
 * Fired during plugin deactivation.
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

namespace FormFlowPro;

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since 2.0.0
 */
class Deactivator
{
    /**
     * Deactivate the plugin.
     *
     * - Unschedule cron jobs
     * - Flush rewrite rules
     * - Clean up temporary data
     *
     * Note: We don't delete database tables or options here.
     * That's done in uninstall.php to prevent accidental data loss.
     *
     * @since 2.0.0
     */
    public static function deactivate()
    {
        // Unschedule cron events
        self::unschedule_events();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Clear any temporary transients
        self::clear_transients();

        // Log deactivation
        if (FORMFLOW_DEBUG) {
            error_log('FormFlow Pro deactivated at ' . current_time('mysql'));
        }
    }

    /**
     * Unschedule all cron events.
     *
     * @since 2.0.0
     */
    private static function unschedule_events()
    {
        $cron_hooks = [
            'formflow_process_queue',
            'formflow_cleanup_logs',
            'formflow_cleanup_cache',
            'formflow_archive_submissions',
        ];

        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }

    /**
     * Clear temporary transients.
     *
     * @since 2.0.0
     */
    private static function clear_transients()
    {
        global $wpdb;

        // Delete all FormFlow transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_formflow_%'
             OR option_name LIKE '_transient_timeout_formflow_%'"
        );
    }
}
