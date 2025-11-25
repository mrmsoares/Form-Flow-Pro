<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

namespace FormFlowPro\Admin;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for enqueueing
 * the admin-specific stylesheet and JavaScript.
 *
 * @since 2.0.0
 */
class Admin
{
    /**
     * The ID of this plugin.
     *
     * @since 2.0.0
     * @var string
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since 2.0.0
     * @var string
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since 2.0.0
     * @param string $plugin_name The name of this plugin.
     * @param string $version The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since 2.0.0
     */
    public function enqueue_styles()
    {
        // Only enqueue on our plugin pages
        if (!$this->is_formflow_page()) {
            return;
        }

        // Critical CSS (inlined for performance)
        $critical_css = FORMFLOW_PATH . 'assets/css/critical-style.min.css';
        if (file_exists($critical_css)) {
            wp_enqueue_style(
                $this->plugin_name . '-critical',
                FORMFLOW_URL . 'assets/css/critical-style.min.css',
                [],
                $this->version,
                'all'
            );
        }

        // Main admin CSS (async loaded)
        $admin_css = FORMFLOW_PATH . 'assets/css/admin-style.min.css';
        if (file_exists($admin_css)) {
            wp_enqueue_style(
                $this->plugin_name,
                FORMFLOW_URL . 'assets/css/admin-style.min.css',
                [$this->plugin_name . '-critical'],
                $this->version,
                'all'
            );
        }

        // Autentique admin CSS
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'formflow-autentique') !== false) {
            wp_enqueue_style(
                $this->plugin_name . '-autentique',
                FORMFLOW_URL . 'assets/css/autentique-admin.css',
                [$this->plugin_name],
                $this->version,
                'all'
            );
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since 2.0.0
     */
    public function enqueue_scripts()
    {
        // Only enqueue on our plugin pages
        if (!$this->is_formflow_page()) {
            return;
        }

        $screen = get_current_screen();

        // Main admin script
        wp_enqueue_script(
            $this->plugin_name,
            FORMFLOW_URL . 'assets/js/admin.min.js',
            ['jquery'],
            $this->version,
            true
        );

        // Page-specific scripts (lazy loaded)
        if ($screen && strpos($screen->id, 'formflow-forms') !== false) {
            wp_enqueue_script(
                $this->plugin_name . '-forms',
                FORMFLOW_URL . 'assets/js/forms.min.js',
                ['jquery', $this->plugin_name],
                $this->version,
                true
            );
        }

        if ($screen && strpos($screen->id, 'formflow-submissions') !== false) {
            wp_enqueue_script(
                $this->plugin_name . '-submissions',
                FORMFLOW_URL . 'assets/js/submissions.min.js',
                ['jquery', $this->plugin_name],
                $this->version,
                true
            );
        }

        if ($screen && strpos($screen->id, 'formflow-analytics') !== false) {
            wp_enqueue_script(
                $this->plugin_name . '-analytics',
                FORMFLOW_URL . 'assets/js/analytics.min.js',
                ['jquery', $this->plugin_name],
                $this->version,
                true
            );
        }

        if ($screen && strpos($screen->id, 'formflow-autentique') !== false) {
            wp_enqueue_script(
                $this->plugin_name . '-autentique',
                FORMFLOW_URL . 'assets/js/autentique.min.js',
                ['jquery', $this->plugin_name],
                $this->version,
                true
            );
        }

        if ($screen && strpos($screen->id, 'formflow-settings') !== false) {
            wp_enqueue_script(
                $this->plugin_name . '-settings',
                FORMFLOW_URL . 'assets/js/settings.min.js',
                ['jquery', $this->plugin_name],
                $this->version,
                true
            );
        }

        // Localize script with data
        wp_localize_script(
            $this->plugin_name,
            'formflowData',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('formflow_nonce'),
                'plugin_url' => FORMFLOW_URL,
                'strings' => [
                    'confirm_delete' => __('Are you sure you want to delete this submission?', 'formflow-pro'),
                    'processing' => __('Processing...', 'formflow-pro'),
                    'error' => __('An error occurred. Please try again.', 'formflow-pro'),
                ],
            ]
        );
    }

    /**
     * Add plugin admin menu.
     *
     * @since 2.0.0
     */
    public function add_plugin_admin_menu()
    {
        // Main menu
        add_menu_page(
            __('FormFlow Pro', 'formflow-pro'),              // Page title
            __('FormFlow Pro', 'formflow-pro'),              // Menu title
            'manage_options',                                 // Capability
            'formflow-pro',                                   // Menu slug
            [$this, 'display_dashboard_page'],               // Callback
            'dashicons-forms',                                // Icon
            30                                                // Position
        );

        // Dashboard submenu (rename main menu item)
        add_submenu_page(
            'formflow-pro',
            __('Dashboard', 'formflow-pro'),
            __('Dashboard', 'formflow-pro'),
            'manage_options',
            'formflow-pro',
            [$this, 'display_dashboard_page']
        );

        // Forms
        add_submenu_page(
            'formflow-pro',
            __('Forms', 'formflow-pro'),
            __('Forms', 'formflow-pro'),
            'manage_options',
            'formflow-forms',
            [$this, 'display_forms_page']
        );

        // Submissions
        add_submenu_page(
            'formflow-pro',
            __('Submissions', 'formflow-pro'),
            __('Submissions', 'formflow-pro'),
            'manage_options',
            'formflow-submissions',
            [$this, 'display_submissions_page']
        );

        // Analytics
        add_submenu_page(
            'formflow-pro',
            __('Analytics', 'formflow-pro'),
            __('Analytics', 'formflow-pro'),
            'manage_options',
            'formflow-analytics',
            [$this, 'display_analytics_page']
        );

        // Autentique
        add_submenu_page(
            'formflow-pro',
            __('Autentique Documents', 'formflow-pro'),
            __('Autentique', 'formflow-pro'),
            'manage_options',
            'formflow-autentique',
            [$this, 'display_autentique_page']
        );

        // Settings
        add_submenu_page(
            'formflow-pro',
            __('Settings', 'formflow-pro'),
            __('Settings', 'formflow-pro'),
            'manage_options',
            'formflow-settings',
            [$this, 'display_settings_page']
        );
    }

    /**
     * Render the dashboard page.
     *
     * @since 2.0.0
     */
    public function display_dashboard_page()
    {
        include_once FORMFLOW_PATH . 'includes/admin/views/dashboard.php';
    }

    /**
     * Render the forms page.
     *
     * @since 2.0.0
     */
    public function display_forms_page()
    {
        include_once FORMFLOW_PATH . 'includes/admin/views/forms.php';
    }

    /**
     * Render the submissions page.
     *
     * @since 2.0.0
     */
    public function display_submissions_page()
    {
        include_once FORMFLOW_PATH . 'includes/admin/views/submissions.php';
    }

    /**
     * Render the analytics page.
     *
     * @since 2.0.0
     */
    public function display_analytics_page()
    {
        include_once FORMFLOW_PATH . 'includes/admin/views/analytics.php';
    }

    /**
     * Render the autentique page.
     *
     * @since 2.0.0
     */
    public function display_autentique_page()
    {
        include_once FORMFLOW_PATH . 'includes/admin/views/autentique.php';
    }

    /**
     * Render the settings page.
     *
     * @since 2.0.0
     */
    public function display_settings_page()
    {
        include_once FORMFLOW_PATH . 'includes/admin/views/settings.php';
    }

    /**
     * Check if current page is a FormFlow page.
     *
     * @since 2.0.0
     * @return bool
     */
    private function is_formflow_page()
    {
        $screen = get_current_screen();

        if (!$screen) {
            return false;
        }

        return strpos($screen->id, 'formflow') !== false;
    }
}
