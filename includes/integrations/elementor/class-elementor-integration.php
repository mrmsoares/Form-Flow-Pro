<?php

declare(strict_types=1);

/**
 * Elementor Integration
 *
 * Manages all Elementor integration features including widgets, actions, and dynamic tags.
 *
 * @package FormFlowPro\Integrations\Elementor
 * @since 2.0.0
 */

namespace FormFlowPro\Integrations\Elementor;

use Elementor\Plugin as ElementorPlugin;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elementor Integration Class
 */
class Elementor_Integration
{
    /**
     * Minimum Elementor Version Required
     *
     * @var string
     */
    const MINIMUM_ELEMENTOR_VERSION = '3.0.0';

    /**
     * Minimum PHP Version Required
     *
     * @var string
     */
    const MINIMUM_PHP_VERSION = '8.0';

    /**
     * Instance of this class
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * Initialize integration
     *
     * @return void
     */
    public function init(): void
    {
        // Check if Elementor is installed and activated
        if (!did_action('elementor/loaded')) {
            add_action('admin_notices', [$this, 'admin_notice_missing_elementor']);
            return;
        }

        // Check for required Elementor version
        if (!version_compare(ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=')) {
            add_action('admin_notices', [$this, 'admin_notice_minimum_elementor_version']);
            return;
        }

        // Check for required PHP version
        if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'admin_notice_minimum_php_version']);
            return;
        }

        // Initialize components
        $this->init_components();
    }

    /**
     * Initialize integration components
     *
     * @return void
     */
    private function init_components(): void
    {
        // Initialize AJAX handlers
        require_once FORMFLOW_PATH . 'includes/integrations/elementor/class-ajax-handler.php';
        Ajax_Handler::init();

        // Register widgets
        add_action('elementor/widgets/register', [$this, 'register_widgets']);

        // Register widget categories
        add_action('elementor/elements/categories_registered', [$this, 'register_widget_categories']);

        // Register Elementor Pro Form Actions
        if (defined('ELEMENTOR_PRO_VERSION')) {
            add_action('elementor_pro/init', [$this, 'register_form_actions']);
        }

        // Register dynamic tags
        add_action('elementor/dynamic_tags/register', [$this, 'register_dynamic_tags']);

        // Enqueue editor scripts
        add_action('elementor/editor/before_enqueue_scripts', [$this, 'enqueue_editor_scripts']);

        // Enqueue frontend scripts
        add_action('elementor/frontend/after_enqueue_styles', [$this, 'enqueue_frontend_styles']);
        add_action('elementor/frontend/after_register_scripts', [$this, 'enqueue_frontend_scripts']);
    }

    /**
     * Register widget categories
     *
     * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
     * @return void
     */
    public function register_widget_categories($elements_manager): void
    {
        $elements_manager->add_category(
            'formflow-pro',
            [
                'title' => __('FormFlow Pro', 'formflow-pro'),
                'icon' => 'fa fa-plug',
            ]
        );
    }

    /**
     * Register widgets
     *
     * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
     * @return void
     */
    public function register_widgets($widgets_manager): void
    {
        // Load widget base class
        require_once FORMFLOW_PATH . 'includes/integrations/elementor/widgets/class-widget-base.php';

        // Load and register Form Display Widget
        require_once FORMFLOW_PATH . 'includes/integrations/elementor/widgets/class-form-widget.php';
        $widgets_manager->register(new Widgets\Form_Widget());
    }

    /**
     * Register Elementor Pro form actions
     *
     * @return void
     */
    public function register_form_actions(): void
    {
        // Load and register FormFlow submission action
        require_once FORMFLOW_PATH . 'includes/integrations/elementor/actions/class-formflow-action.php';

        $form_actions = ElementorPlugin::instance()->modules_manager->get_modules('forms')->get_component('actions');
        $form_actions->register(new Actions\FormFlow_Action());
    }

    /**
     * Register dynamic tags
     *
     * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager Dynamic tags manager.
     * @return void
     */
    public function register_dynamic_tags($dynamic_tags_manager): void
    {
        // Load and register submission count tag
        require_once FORMFLOW_PATH . 'includes/integrations/elementor/tags/class-submission-tag.php';
        $dynamic_tags_manager->register(new Tags\Submission_Tag());
    }

    /**
     * Enqueue editor scripts
     *
     * @return void
     */
    public function enqueue_editor_scripts(): void
    {
        wp_enqueue_script(
            'formflow-elementor-editor',
            FORMFLOW_URL . 'assets/js/elementor-editor.min.js',
            ['jquery', 'elementor-editor'],
            FORMFLOW_VERSION,
            true
        );

        wp_localize_script(
            'formflow-elementor-editor',
            'formflowElementor',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('formflow_elementor'),
            ]
        );
    }

    /**
     * Enqueue frontend styles
     *
     * @return void
     */
    public function enqueue_frontend_styles(): void
    {
        wp_enqueue_style(
            'formflow-elementor',
            FORMFLOW_URL . 'assets/css/elementor-style.min.css',
            [],
            FORMFLOW_VERSION
        );
    }

    /**
     * Enqueue frontend scripts
     *
     * @return void
     */
    public function enqueue_frontend_scripts(): void
    {
        wp_register_script(
            'formflow-elementor',
            FORMFLOW_URL . 'assets/js/elementor.min.js',
            ['jquery', 'elementor-frontend'],
            FORMFLOW_VERSION,
            true
        );

        wp_localize_script(
            'formflow-elementor',
            'formflowElementor',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('formflow_elementor'),
            ]
        );
    }

    /**
     * Admin notice for missing Elementor
     *
     * @return void
     */
    public function admin_notice_missing_elementor(): void
    {
        $message = sprintf(
            /* translators: 1: Plugin name 2: Elementor */
            esc_html__('"%1$s" requires "%2$s" to be installed and activated.', 'formflow-pro'),
            '<strong>' . esc_html__('FormFlow Pro', 'formflow-pro') . '</strong>',
            '<strong>' . esc_html__('Elementor', 'formflow-pro') . '</strong>'
        );

        printf('<div class="notice notice-warning is-dismissible"><p>%s</p></div>', wp_kses_post($message));
    }

    /**
     * Admin notice for minimum Elementor version
     *
     * @return void
     */
    public function admin_notice_minimum_elementor_version(): void
    {
        $message = sprintf(
            /* translators: 1: Plugin name 2: Elementor 3: Required Elementor version */
            esc_html__('"%1$s" requires "%2$s" version %3$s or greater.', 'formflow-pro'),
            '<strong>' . esc_html__('FormFlow Pro', 'formflow-pro') . '</strong>',
            '<strong>' . esc_html__('Elementor', 'formflow-pro') . '</strong>',
            self::MINIMUM_ELEMENTOR_VERSION
        );

        printf('<div class="notice notice-warning is-dismissible"><p>%s</p></div>', wp_kses_post($message));
    }

    /**
     * Admin notice for minimum PHP version
     *
     * @return void
     */
    public function admin_notice_minimum_php_version(): void
    {
        $message = sprintf(
            /* translators: 1: Plugin name 2: PHP 3: Required PHP version */
            esc_html__('"%1$s" requires "%2$s" version %3$s or greater.', 'formflow-pro'),
            '<strong>' . esc_html__('FormFlow Pro', 'formflow-pro') . '</strong>',
            '<strong>' . esc_html__('PHP', 'formflow-pro') . '</strong>',
            self::MINIMUM_PHP_VERSION
        );

        printf('<div class="notice notice-warning is-dismissible"><p>%s</p></div>', wp_kses_post($message));
    }
}
