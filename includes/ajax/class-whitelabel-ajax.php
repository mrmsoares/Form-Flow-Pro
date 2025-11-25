<?php

declare(strict_types=1);

/**
 * White Label AJAX Handlers
 *
 * Handles AJAX requests for white label settings.
 *
 * @package FormFlowPro\Ajax
 * @since 2.2.0
 */

namespace FormFlowPro\Ajax;

use FormFlowPro\Core\WhiteLabel;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * White Label AJAX Handler Class
 */
class WhiteLabel_Ajax
{
    /**
     * Register AJAX handlers
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('wp_ajax_formflow_get_whitelabel_settings', [__CLASS__, 'get_settings']);
        add_action('wp_ajax_formflow_save_whitelabel_settings', [__CLASS__, 'save_settings']);
        add_action('wp_ajax_formflow_reset_whitelabel_settings', [__CLASS__, 'reset_settings']);
        add_action('wp_ajax_formflow_preview_whitelabel', [__CLASS__, 'preview_settings']);
    }

    /**
     * Get white label settings
     *
     * @return void
     */
    public static function get_settings(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        require_once FORMFLOW_PATH . 'includes/Core/WhiteLabel.php';
        $whitelabel = WhiteLabel::get_instance();

        wp_send_json_success([
            'settings' => $whitelabel->get_all(),
            'defaults' => WhiteLabel::get_defaults(),
        ]);
    }

    /**
     * Save white label settings
     *
     * @return void
     */
    public static function save_settings(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        // Get settings from POST
        $settings = [];

        // Boolean fields
        $boolean_fields = [
            'enabled', 'hide_version', 'hide_support_links', 'remove_powered_by'
        ];

        foreach ($boolean_fields as $field) {
            $settings[$field] = isset($_POST[$field]) && $_POST[$field] === '1';
        }

        // Text fields
        $text_fields = [
            'plugin_name', 'plugin_slug', 'plugin_author', 'plugin_description',
            'admin_menu_icon', 'custom_footer_text'
        ];

        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = sanitize_text_field(wp_unslash($_POST[$field]));
            }
        }

        // URL fields
        $url_fields = [
            'plugin_author_url', 'logo_url', 'custom_support_url', 'custom_docs_url'
        ];

        foreach ($url_fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = esc_url_raw(wp_unslash($_POST[$field]));
            }
        }

        // Integer fields
        $int_fields = ['admin_menu_position', 'logo_width'];

        foreach ($int_fields as $field) {
            if (isset($_POST[$field])) {
                $settings[$field] = absint($_POST[$field]);
            }
        }

        // Color fields
        $color_fields = ['primary_color', 'secondary_color'];

        foreach ($color_fields as $field) {
            if (isset($_POST[$field])) {
                $color = sanitize_text_field($_POST[$field]);
                if (preg_match('/^#([a-fA-F0-9]{3}){1,2}$/', $color)) {
                    $settings[$field] = $color;
                }
            }
        }

        require_once FORMFLOW_PATH . 'includes/Core/WhiteLabel.php';
        $whitelabel = WhiteLabel::get_instance();

        $result = $whitelabel->save_settings($settings);

        if ($result) {
            wp_send_json_success([
                'message' => __('White label settings saved successfully.', 'formflow-pro'),
                'settings' => $whitelabel->get_all(),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to save settings. Please try again.', 'formflow-pro'),
            ]);
        }
    }

    /**
     * Reset white label settings to defaults
     *
     * @return void
     */
    public static function reset_settings(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        require_once FORMFLOW_PATH . 'includes/Core/WhiteLabel.php';
        $whitelabel = WhiteLabel::get_instance();

        $whitelabel->reset_to_defaults();

        wp_send_json_success([
            'message' => __('Settings reset to defaults.', 'formflow-pro'),
            'settings' => WhiteLabel::get_defaults(),
        ]);
    }

    /**
     * Preview white label settings without saving
     *
     * @return void
     */
    public static function preview_settings(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        // Generate preview CSS
        $primary = sanitize_text_field($_POST['primary_color'] ?? '#0073aa');
        $secondary = sanitize_text_field($_POST['secondary_color'] ?? '#00a0d2');

        $preview_css = "
            :root {
                --formflow-primary: {$primary};
                --formflow-secondary: {$secondary};
            }
            .formflow-admin .stat-card.stat-primary .stat-icon {
                background: linear-gradient(135deg, {$primary}, {$secondary});
            }
            .formflow-admin .button-primary {
                background: {$primary};
                border-color: {$primary};
            }
        ";

        wp_send_json_success([
            'preview_css' => $preview_css,
        ]);
    }
}
