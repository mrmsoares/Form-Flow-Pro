<?php

declare(strict_types=1);

/**
 * AJAX Handler Manager
 *
 * Central manager for all AJAX handlers.
 *
 * @package FormFlowPro\Ajax
 * @since 2.0.0
 */

namespace FormFlowPro\Ajax;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler Manager Class
 */
class Ajax_Handler
{
    /**
     * Initialize all AJAX handlers
     *
     * @return void
     */
    public static function init(): void
    {
        // Load handler classes
        self::load_handlers();

        // Initialize each handler
        Forms_Ajax::init();
        Submissions_Ajax::init();
        Analytics_Ajax::init();
        Settings_Ajax::init();
        Dashboard_Ajax::init();
        Config_Ajax::init(); // V2.2.0
        WhiteLabel_Ajax::init(); // V2.2.0
        Integrations_Ajax::init(); // V2.3.0
    }

    /**
     * Load AJAX handler classes
     *
     * @return void
     */
    private static function load_handlers(): void
    {
        require_once FORMFLOW_PATH . 'includes/ajax/class-forms-ajax.php';
        require_once FORMFLOW_PATH . 'includes/ajax/class-submissions-ajax.php';
        require_once FORMFLOW_PATH . 'includes/ajax/class-analytics-ajax.php';
        require_once FORMFLOW_PATH . 'includes/ajax/class-settings-ajax.php';
        require_once FORMFLOW_PATH . 'includes/ajax/class-dashboard-ajax.php';
        require_once FORMFLOW_PATH . 'includes/ajax/class-config-ajax.php'; // V2.2.0
        require_once FORMFLOW_PATH . 'includes/ajax/class-whitelabel-ajax.php'; // V2.2.0
        require_once FORMFLOW_PATH . 'includes/ajax/class-integrations-ajax.php'; // V2.3.0
    }
}
