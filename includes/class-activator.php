<?php
/**
 * Fired during plugin activation.
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

namespace FormFlowPro;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since 2.0.0
 */
class Activator
{
    /**
     * Activate the plugin.
     *
     * - Check system requirements
     * - Create database tables
     * - Set default options
     * - Schedule cron jobs
     *
     * @since 2.0.0
     */
    public static function activate()
    {
        // Check minimum requirements
        self::check_requirements();

        // Create database tables
        self::create_tables();

        // Set default options
        self::set_default_options();

        // Schedule cron jobs
        self::schedule_events();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set activation flag
        update_option('formflow_activated', time());
        update_option('formflow_version', FORMFLOW_VERSION);
    }

    /**
     * Check system requirements.
     *
     * @since 2.0.0
     * @throws \Exception If requirements are not met.
     */
    private static function check_requirements()
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            deactivate_plugins(FORMFLOW_BASENAME);
            wp_die(
                esc_html__('FormFlow Pro requires PHP 8.0 or higher.', 'formflow-pro'),
                esc_html__('Plugin Activation Error', 'formflow-pro'),
                ['back_link' => true]
            );
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '6.0', '<')) {
            deactivate_plugins(FORMFLOW_BASENAME);
            wp_die(
                esc_html__('FormFlow Pro requires WordPress 6.0 or higher.', 'formflow-pro'),
                esc_html__('Plugin Activation Error', 'formflow-pro'),
                ['back_link' => true]
            );
        }

        // Check for required PHP extensions
        $required_extensions = ['json', 'mbstring', 'pdo'];
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                deactivate_plugins(FORMFLOW_BASENAME);
                wp_die(
                    sprintf(
                        /* translators: %s: extension name */
                        esc_html__('FormFlow Pro requires the %s PHP extension.', 'formflow-pro'),
                        $extension
                    ),
                    esc_html__('Plugin Activation Error', 'formflow-pro'),
                    ['back_link' => true]
                );
            }
        }
    }

    /**
     * Create database tables.
     *
     * @since 2.0.0
     */
    private static function create_tables()
    {
        require_once FORMFLOW_PATH . 'includes/database/class-database-manager.php';

        $db_manager = new Database\DatabaseManager();
        $db_manager->create_tables();

        update_option('formflow_db_version', FORMFLOW_DB_VERSION);
    }

    /**
     * Set default plugin options.
     *
     * @since 2.0.0
     */
    private static function set_default_options()
    {
        $defaults = [
            'formflow_cache_enabled' => true,
            'formflow_cache_ttl' => 3600,
            'formflow_debug_mode' => false,
            'formflow_performance_mode' => 'balanced', // balanced, speed, memory
            'formflow_queue_enabled' => true,
            'formflow_queue_batch_size' => 10,
        ];

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }

    /**
     * Schedule cron events.
     *
     * @since 2.0.0
     */
    private static function schedule_events()
    {
        // Process queue every 5 minutes
        if (!wp_next_scheduled('formflow_process_queue')) {
            wp_schedule_event(time(), 'five_minutes', 'formflow_process_queue');
        }

        // Clean up old logs daily
        if (!wp_next_scheduled('formflow_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'formflow_cleanup_logs');
        }

        // Clean up cache hourly
        if (!wp_next_scheduled('formflow_cleanup_cache')) {
            wp_schedule_event(time(), 'hourly', 'formflow_cleanup_cache');
        }

        // Archive old submissions weekly
        if (!wp_next_scheduled('formflow_archive_submissions')) {
            wp_schedule_event(time(), 'weekly', 'formflow_archive_submissions');
        }
    }
}
