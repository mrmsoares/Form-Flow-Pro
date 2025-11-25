<?php
/**
 * Network Settings View
 *
 * @package FormFlowPro\Admin\Views
 * @since 2.3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap formflow-network-settings">
    <h1><?php esc_html_e('FormFlow Pro - Network Settings', 'formflow-pro'); ?></h1>

    <form method="post" action="" id="network-settings-form">
        <?php wp_nonce_field('formflow_network_settings', 'formflow_network_nonce'); ?>

        <div class="settings-sections">
            <div class="settings-section">
                <h2><?php esc_html_e('Network Activation', 'formflow-pro'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Network Activated', 'formflow-pro'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="network_activated" value="1"
                                    <?php checked(!empty($settings['network_activated'])); ?>>
                                <?php esc_html_e('Auto-activate plugin on all new sites', 'formflow-pro'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Sync Settings', 'formflow-pro'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="sync_settings" value="1"
                                    <?php checked(!empty($settings['sync_settings'])); ?>>
                                <?php esc_html_e('Sync settings from main site to all subsites', 'formflow-pro'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="settings-section">
                <h2><?php esc_html_e('Shared Resources', 'formflow-pro'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Shared Templates', 'formflow-pro'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="shared_templates" value="1"
                                    <?php checked(!empty($settings['shared_templates'])); ?>>
                                <?php esc_html_e('Share form templates across all sites', 'formflow-pro'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Global Integrations', 'formflow-pro'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="global_integrations" value="1"
                                    <?php checked(!empty($settings['global_integrations'])); ?>>
                                <?php esc_html_e('Use same integration credentials for all sites', 'formflow-pro'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="settings-section">
                <h2><?php esc_html_e('Analytics & Reporting', 'formflow-pro'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Centralized Analytics', 'formflow-pro'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="centralized_analytics" value="1"
                                    <?php checked(!empty($settings['centralized_analytics'])); ?>>
                                <?php esc_html_e('Aggregate analytics from all sites', 'formflow-pro'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Cross-Site Reporting', 'formflow-pro'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_cross_site_reporting" value="1"
                                    <?php checked(!empty($settings['enable_cross_site_reporting'])); ?>>
                                <?php esc_html_e('Enable cross-site comparison reports', 'formflow-pro'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="settings-section">
                <h2><?php esc_html_e('Data Management', 'formflow-pro'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="data_retention_days">
                                <?php esc_html_e('Data Retention', 'formflow-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" name="data_retention_days" id="data_retention_days"
                                   value="<?php echo esc_attr($settings['data_retention_days'] ?? 90); ?>"
                                   min="30" max="3650" class="small-text">
                            <?php esc_html_e('days', 'formflow-pro'); ?>
                            <p class="description">
                                <?php esc_html_e('Submissions older than this will be archived.', 'formflow-pro'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Save Network Settings', 'formflow-pro'); ?>
            </button>
        </p>
    </form>
</div>

<style>
.formflow-network-settings .settings-sections {
    max-width: 800px;
}

.formflow-network-settings .settings-section {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.formflow-network-settings .settings-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e0e0;
}
</style>
