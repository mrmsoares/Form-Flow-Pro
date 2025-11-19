<?php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

namespace FormFlowPro;

/**
 * The core plugin class.
 *
 * @since 2.0.0
 */
class FormFlowPlugin
{
    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since 2.0.0
     * @var Loader
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since 2.0.0
     * @var string
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since 2.0.0
     * @var string
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->plugin_name = 'formflow-pro';
        $this->version = FORMFLOW_VERSION;

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since 2.0.0
     */
    private function load_dependencies()
    {
        /**
         * The class responsible for orchestrating the actions and filters.
         */
        require_once FORMFLOW_PATH . 'includes/class-loader.php';
        $this->loader = new Loader();

        /**
         * The class responsible for defining internationalization functionality.
         */
        require_once FORMFLOW_PATH . 'includes/class-i18n.php';
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * @since 2.0.0
     */
    private function set_locale()
    {
        $plugin_i18n = new I18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     *
     * @since 2.0.0
     */
    private function define_admin_hooks()
    {
        // Only load admin classes if in admin area
        if (!is_admin()) {
            return;
        }

        require_once FORMFLOW_PATH . 'includes/admin/class-admin.php';
        $plugin_admin = new Admin\Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     *
     * @since 2.0.0
     */
    private function define_public_hooks()
    {
        // Public hooks will be added here as needed
        // For now, this is primarily an admin plugin
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since 2.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks.
     *
     * @since 2.0.0
     * @return Loader
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since 2.0.0
     * @return string
     */
    public function get_version()
    {
        return $this->version;
    }
}
