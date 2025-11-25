<?php

/**
 * White Label Manager
 *
 * Allows customization of plugin branding for agencies and developers.
 *
 * @package FormFlowPro
 * @since 2.2.0
 */

namespace FormFlowPro\Core;

/**
 * White Label class.
 *
 * @since 2.2.0
 */
class WhiteLabel
{
    /**
     * Singleton instance.
     *
     * @var WhiteLabel|null
     */
    private static $instance = null;

    /**
     * White label settings.
     *
     * @var array
     */
    private $settings = [];

    /**
     * Default settings.
     *
     * @var array
     */
    private const DEFAULTS = [
        'enabled' => false,
        'plugin_name' => 'FormFlow Pro',
        'plugin_slug' => 'formflow-pro',
        'plugin_author' => 'FormFlow Team',
        'plugin_author_url' => 'https://formflow.pro',
        'plugin_description' => 'Enterprise form processing with PDF generation and digital signatures.',
        'admin_menu_icon' => 'dashicons-forms',
        'admin_menu_position' => 30,
        'primary_color' => '#0073aa',
        'secondary_color' => '#00a0d2',
        'logo_url' => '',
        'logo_width' => 150,
        'custom_footer_text' => '',
        'hide_version' => false,
        'hide_support_links' => false,
        'custom_support_url' => '',
        'custom_docs_url' => '',
        'remove_powered_by' => false,
    ];

    /**
     * Get singleton instance.
     *
     * @return WhiteLabel
     */
    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        $this->load_settings();

        if ($this->is_enabled()) {
            $this->register_hooks();
        }
    }

    /**
     * Load white label settings.
     */
    private function load_settings(): void
    {
        $saved_settings = get_option('formflow_whitelabel_settings', []);
        $this->settings = array_merge(self::DEFAULTS, $saved_settings);
    }

    /**
     * Save white label settings.
     *
     * @param array $settings Settings to save.
     * @return bool Success.
     */
    public function save_settings(array $settings): bool
    {
        $sanitized = $this->sanitize_settings($settings);
        $result = update_option('formflow_whitelabel_settings', $sanitized);

        if ($result) {
            $this->settings = array_merge(self::DEFAULTS, $sanitized);
        }

        return $result;
    }

    /**
     * Sanitize settings.
     *
     * @param array $settings Raw settings.
     * @return array Sanitized settings.
     */
    private function sanitize_settings(array $settings): array
    {
        $sanitized = [];

        $sanitized['enabled'] = !empty($settings['enabled']);
        $sanitized['plugin_name'] = sanitize_text_field($settings['plugin_name'] ?? self::DEFAULTS['plugin_name']);
        $sanitized['plugin_slug'] = sanitize_key($settings['plugin_slug'] ?? self::DEFAULTS['plugin_slug']);
        $sanitized['plugin_author'] = sanitize_text_field($settings['plugin_author'] ?? self::DEFAULTS['plugin_author']);
        $sanitized['plugin_author_url'] = esc_url_raw($settings['plugin_author_url'] ?? self::DEFAULTS['plugin_author_url']);
        $sanitized['plugin_description'] = sanitize_textarea_field($settings['plugin_description'] ?? self::DEFAULTS['plugin_description']);
        $sanitized['admin_menu_icon'] = sanitize_text_field($settings['admin_menu_icon'] ?? self::DEFAULTS['admin_menu_icon']);
        $sanitized['admin_menu_position'] = absint($settings['admin_menu_position'] ?? self::DEFAULTS['admin_menu_position']);
        $sanitized['primary_color'] = $this->sanitize_color($settings['primary_color'] ?? self::DEFAULTS['primary_color']);
        $sanitized['secondary_color'] = $this->sanitize_color($settings['secondary_color'] ?? self::DEFAULTS['secondary_color']);
        $sanitized['logo_url'] = esc_url_raw($settings['logo_url'] ?? '');
        $sanitized['logo_width'] = absint($settings['logo_width'] ?? self::DEFAULTS['logo_width']);
        $sanitized['custom_footer_text'] = wp_kses_post($settings['custom_footer_text'] ?? '');
        $sanitized['hide_version'] = !empty($settings['hide_version']);
        $sanitized['hide_support_links'] = !empty($settings['hide_support_links']);
        $sanitized['custom_support_url'] = esc_url_raw($settings['custom_support_url'] ?? '');
        $sanitized['custom_docs_url'] = esc_url_raw($settings['custom_docs_url'] ?? '');
        $sanitized['remove_powered_by'] = !empty($settings['remove_powered_by']);

        return $sanitized;
    }

    /**
     * Sanitize color value.
     *
     * @param string $color Color value.
     * @return string Sanitized color.
     */
    private function sanitize_color(string $color): string
    {
        if (preg_match('/^#([a-fA-F0-9]{3}){1,2}$/', $color)) {
            return $color;
        }
        return self::DEFAULTS['primary_color'];
    }

    /**
     * Register WordPress hooks.
     */
    private function register_hooks(): void
    {
        // Admin styles
        add_action('admin_head', [$this, 'output_custom_styles']);

        // Plugin row meta
        add_filter('plugin_row_meta', [$this, 'filter_plugin_row_meta'], 10, 2);

        // Plugin action links
        add_filter('plugin_action_links_' . FORMFLOW_BASENAME, [$this, 'filter_action_links'], 10, 1);

        // Admin footer
        add_filter('admin_footer_text', [$this, 'filter_admin_footer'], 100);
    }

    /**
     * Check if white label is enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool
    {
        return !empty($this->settings['enabled']);
    }

    /**
     * Get setting value.
     *
     * @param string $key Setting key.
     * @param mixed $default Default value.
     * @return mixed Setting value.
     */
    public function get(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /**
     * Get all settings.
     *
     * @return array All settings.
     */
    public function get_all(): array
    {
        return $this->settings;
    }

    /**
     * Get plugin name.
     *
     * @return string Plugin name.
     */
    public function get_plugin_name(): string
    {
        return $this->settings['plugin_name'] ?? self::DEFAULTS['plugin_name'];
    }

    /**
     * Get primary color.
     *
     * @return string Primary color hex.
     */
    public function get_primary_color(): string
    {
        return $this->settings['primary_color'] ?? self::DEFAULTS['primary_color'];
    }

    /**
     * Get secondary color.
     *
     * @return string Secondary color hex.
     */
    public function get_secondary_color(): string
    {
        return $this->settings['secondary_color'] ?? self::DEFAULTS['secondary_color'];
    }

    /**
     * Get logo URL.
     *
     * @return string Logo URL or empty.
     */
    public function get_logo_url(): string
    {
        return $this->settings['logo_url'] ?? '';
    }

    /**
     * Output custom CSS styles.
     */
    public function output_custom_styles(): void
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'formflow') === false) {
            return;
        }

        $primary = $this->get_primary_color();
        $secondary = $this->get_secondary_color();

        ?>
        <style>
        :root {
            --formflow-primary: <?php echo esc_attr($primary); ?>;
            --formflow-secondary: <?php echo esc_attr($secondary); ?>;
        }

        .formflow-admin .stat-card.stat-primary .stat-icon {
            background: linear-gradient(135deg, <?php echo esc_attr($primary); ?>, <?php echo esc_attr($secondary); ?>);
        }

        .formflow-admin .button-primary,
        .formflow-admin .nav-tab-active {
            background: <?php echo esc_attr($primary); ?>;
            border-color: <?php echo esc_attr($primary); ?>;
        }

        .formflow-admin .button-primary:hover {
            background: <?php echo esc_attr($secondary); ?>;
            border-color: <?php echo esc_attr($secondary); ?>;
        }

        .formflow-admin a {
            color: <?php echo esc_attr($primary); ?>;
        }

        .formflow-admin .analytics-version-badge {
            background: linear-gradient(135deg, <?php echo esc_attr($primary); ?>, <?php echo esc_attr($secondary); ?>);
        }

        <?php if ($this->get_logo_url()) : ?>
        .formflow-admin .custom-logo {
            max-width: <?php echo esc_attr($this->settings['logo_width']); ?>px;
            height: auto;
        }
        <?php endif; ?>
        </style>
        <?php
    }

    /**
     * Filter plugin row meta.
     *
     * @param array $links Plugin meta links.
     * @param string $file Plugin file.
     * @return array Filtered links.
     */
    public function filter_plugin_row_meta(array $links, string $file): array
    {
        if ($file !== FORMFLOW_BASENAME) {
            return $links;
        }

        if ($this->settings['hide_support_links']) {
            // Remove default links
            return array_slice($links, 0, 2);
        }

        // Replace links with custom URLs
        $filtered = [];
        foreach ($links as $key => $link) {
            if ($key === 'docs' && !empty($this->settings['custom_docs_url'])) {
                $filtered[$key] = '<a href="' . esc_url($this->settings['custom_docs_url']) . '">' .
                    esc_html__('Documentation', 'formflow-pro') . '</a>';
            } elseif ($key === 'support' && !empty($this->settings['custom_support_url'])) {
                $filtered[$key] = '<a href="' . esc_url($this->settings['custom_support_url']) . '">' .
                    esc_html__('Support', 'formflow-pro') . '</a>';
            } else {
                $filtered[$key] = $link;
            }
        }

        return $filtered;
    }

    /**
     * Filter plugin action links.
     *
     * @param array $links Action links.
     * @return array Filtered links.
     */
    public function filter_action_links(array $links): array
    {
        // Action links remain unchanged
        return $links;
    }

    /**
     * Filter admin footer text.
     *
     * @param string $text Footer text.
     * @return string Filtered text.
     */
    public function filter_admin_footer(string $text): string
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'formflow') === false) {
            return $text;
        }

        if (!empty($this->settings['custom_footer_text'])) {
            return $this->settings['custom_footer_text'];
        }

        if ($this->settings['remove_powered_by']) {
            return '';
        }

        return $text;
    }

    /**
     * Get defaults for form display.
     *
     * @return array Default settings.
     */
    public static function get_defaults(): array
    {
        return self::DEFAULTS;
    }

    /**
     * Reset to defaults.
     *
     * @return bool Success.
     */
    public function reset_to_defaults(): bool
    {
        delete_option('formflow_whitelabel_settings');
        $this->settings = self::DEFAULTS;
        return true;
    }
}
