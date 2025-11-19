<?php

/**
 * Provide a admin area view for settings
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['formflow_settings_submit'])) {
    check_admin_referer('formflow_settings', 'formflow_settings_nonce');

    // Save settings
    update_option('formflow_autentique_api_key', sanitize_text_field($_POST['autentique_api_key'] ?? ''));
    update_option('formflow_cache_enabled', isset($_POST['cache_enabled']) ? 1 : 0);
    update_option('formflow_debug_mode', isset($_POST['debug_mode']) ? 1 : 0);

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'formflow-pro') . '</p></div>';
}

// Get current settings
$autentique_api_key = get_option('formflow_autentique_api_key', '');
$cache_enabled = get_option('formflow_cache_enabled', true);
$debug_mode = get_option('formflow_debug_mode', false);
?>

<div class="wrap formflow-settings">
    <h1 class="ff-heading-1">
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>

    <div class="ff-container">
        <form method="post" action="">
            <?php wp_nonce_field('formflow_settings', 'formflow_settings_nonce'); ?>

            <!-- Autentique Integration -->
            <div class="ff-card" style="margin-bottom: 2rem;">
                <div class="ff-card-header">
                    <h3 class="ff-card-title"><?php esc_html_e('Autentique Integration', 'formflow-pro'); ?></h3>
                    <p class="ff-card-description"><?php esc_html_e('Configure your Autentique API credentials', 'formflow-pro'); ?></p>
                </div>
                <div class="ff-card-body">
                    <div class="ff-input-group">
                        <label for="autentique_api_key" class="ff-input-label">
                            <?php esc_html_e('API Key', 'formflow-pro'); ?> *
                        </label>
                        <input
                            type="text"
                            id="autentique_api_key"
                            name="autentique_api_key"
                            class="ff-input"
                            value="<?php echo esc_attr($autentique_api_key); ?>"
                            placeholder="<?php esc_attr_e('Enter your Autentique API key', 'formflow-pro'); ?>"
                        >
                        <span class="ff-input-help">
                            <?php esc_html_e('Get your API key from', 'formflow-pro'); ?>
                            <a href="https://www.autentique.com.br/developers" target="_blank">Autentique Developers</a>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Performance Settings -->
            <div class="ff-card" style="margin-bottom: 2rem;">
                <div class="ff-card-header">
                    <h3 class="ff-card-title"><?php esc_html_e('Performance', 'formflow-pro'); ?></h3>
                    <p class="ff-card-description"><?php esc_html_e('Optimize plugin performance', 'formflow-pro'); ?></p>
                </div>
                <div class="ff-card-body">
                    <div class="ff-input-group">
                        <label>
                            <input
                                type="checkbox"
                                name="cache_enabled"
                                value="1"
                                <?php checked($cache_enabled, 1); ?>
                            >
                            <?php esc_html_e('Enable caching', 'formflow-pro'); ?>
                        </label>
                        <span class="ff-input-help">
                            <?php esc_html_e('Recommended for production environments. Improves performance significantly.', 'formflow-pro'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Developer Settings -->
            <div class="ff-card" style="margin-bottom: 2rem;">
                <div class="ff-card-header">
                    <h3 class="ff-card-title"><?php esc_html_e('Developer Options', 'formflow-pro'); ?></h3>
                    <p class="ff-card-description"><?php esc_html_e('Advanced settings for developers', 'formflow-pro'); ?></p>
                </div>
                <div class="ff-card-body">
                    <div class="ff-input-group">
                        <label>
                            <input
                                type="checkbox"
                                name="debug_mode"
                                value="1"
                                <?php checked($debug_mode, 1); ?>
                            >
                            <?php esc_html_e('Enable debug mode', 'formflow-pro'); ?>
                        </label>
                        <span class="ff-input-help">
                            <?php esc_html_e('Logs detailed information for troubleshooting. Disable in production.', 'formflow-pro'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- System Info -->
            <div class="ff-card">
                <div class="ff-card-header">
                    <h3 class="ff-card-title"><?php esc_html_e('System Information', 'formflow-pro'); ?></h3>
                </div>
                <div class="ff-card-body">
                    <table class="widefat">
                        <tbody>
                            <tr>
                                <td><strong><?php esc_html_e('Plugin Version:', 'formflow-pro'); ?></strong></td>
                                <td><?php echo esc_html(FORMFLOW_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('WordPress Version:', 'formflow-pro'); ?></strong></td>
                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('PHP Version:', 'formflow-pro'); ?></strong></td>
                                <td><?php echo esc_html(PHP_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Database Version:', 'formflow-pro'); ?></strong></td>
                                <td><?php echo esc_html(get_option('formflow_db_version', 'Not installed')); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Submit Button -->
            <p class="submit" style="margin-top: 2rem;">
                <button type="submit" name="formflow_settings_submit" class="button button-primary button-large">
                    <?php esc_html_e('Save Settings', 'formflow-pro'); ?>
                </button>
            </p>
        </form>
    </div>
</div>
