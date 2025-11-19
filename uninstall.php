<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Uninstall FormFlow Pro Enterprise.
 *
 * Deletes all plugin data including:
 * - Database tables
 * - Options and transients
 * - Uploaded files
 * - Scheduled cron events
 *
 * @since 2.0.0
 */
function formflow_pro_uninstall()
{
    global $wpdb;

    // Check if user has permission to delete plugins
    if (!current_user_can('delete_plugins')) {
        return;
    }

    // Delete database tables
    formflow_drop_tables($wpdb);

    // Delete all plugin options
    formflow_delete_options();

    // Delete all transients
    formflow_delete_transients();

    // Delete uploaded files and directories
    formflow_delete_uploads();

    // Clear scheduled events
    formflow_clear_scheduled_events();

    // For multisite, repeat for each site
    if (is_multisite()) {
        formflow_uninstall_multisite();
    }
}

/**
 * Drop all plugin database tables.
 *
 * @since 2.0.0
 * @param wpdb $wpdb WordPress database object.
 */
function formflow_drop_tables($wpdb)
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
        $table_name = $wpdb->prefix . $table;
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}

/**
 * Delete all plugin options.
 *
 * @since 2.0.0
 */
function formflow_delete_options()
{
    $options = [
        'formflow_version',
        'formflow_db_version',
        'formflow_autentique_api_key',
        'formflow_cache_enabled',
        'formflow_debug_mode',
        'formflow_performance_mode',
        'formflow_cache_ttl',
        'formflow_default_pdf_template',
        'formflow_default_email_template',
        'formflow_queue_batch_size',
        'formflow_queue_timeout',
        'formflow_log_retention_days',
        'formflow_max_upload_size',
        'formflow_pdf_quality',
        'formflow_email_from_name',
        'formflow_email_from_email',
    ];

    foreach ($options as $option) {
        delete_option($option);
    }

    // Delete any options that start with 'formflow_'
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'formflow_%'");
}

/**
 * Delete all plugin transients.
 *
 * @since 2.0.0
 */
function formflow_delete_transients()
{
    global $wpdb;

    // Delete regular transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_formflow_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_formflow_%'");

    // Delete site transients (for multisite)
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_formflow_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_timeout_formflow_%'");
}

/**
 * Delete uploaded files and directories.
 *
 * @since 2.0.0
 */
function formflow_delete_uploads()
{
    $upload_dir = wp_upload_dir();
    $formflow_dir = $upload_dir['basedir'] . '/formflow-pro';

    if (is_dir($formflow_dir)) {
        formflow_delete_directory($formflow_dir);
    }
}

/**
 * Recursively delete a directory and its contents.
 *
 * @since 2.0.0
 * @param string $dir Directory path.
 * @return bool True on success, false on failure.
 */
function formflow_delete_directory($dir)
{
    if (!is_dir($dir)) {
        return false;
    }

    $items = array_diff(scandir($dir), ['.', '..']);

    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            formflow_delete_directory($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}

/**
 * Clear all scheduled cron events.
 *
 * @since 2.0.0
 */
function formflow_clear_scheduled_events()
{
    $cron_events = [
        'formflow_process_queue',
        'formflow_cleanup_logs',
        'formflow_cleanup_cache',
        'formflow_archive_submissions',
        'formflow_generate_analytics',
        'formflow_check_autentique_status',
        'formflow_retry_failed_jobs',
    ];

    foreach ($cron_events as $event) {
        $timestamp = wp_next_scheduled($event);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $event);
        }
    }

    // Clear all cron schedules for this plugin
    wp_clear_scheduled_hook('formflow_process_queue');
    wp_clear_scheduled_hook('formflow_cleanup_logs');
    wp_clear_scheduled_hook('formflow_cleanup_cache');
    wp_clear_scheduled_hook('formflow_archive_submissions');
    wp_clear_scheduled_hook('formflow_generate_analytics');
    wp_clear_scheduled_hook('formflow_check_autentique_status');
    wp_clear_scheduled_hook('formflow_retry_failed_jobs');
}

/**
 * Uninstall on multisite.
 *
 * @since 2.0.0
 */
function formflow_uninstall_multisite()
{
    global $wpdb;

    // Get all blog IDs
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");

    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);

        // Run uninstall for this site
        formflow_drop_tables($wpdb);
        formflow_delete_options();
        formflow_delete_transients();
        formflow_delete_uploads();
        formflow_clear_scheduled_events();

        restore_current_blog();
    }
}

// Run the uninstall
formflow_pro_uninstall();
