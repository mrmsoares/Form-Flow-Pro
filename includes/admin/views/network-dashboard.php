<?php
/**
 * Network Dashboard View
 *
 * @package FormFlowPro\Admin\Views
 * @since 2.3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap formflow-network-dashboard">
    <h1><?php esc_html_e('FormFlow Pro Network Dashboard', 'formflow-pro'); ?></h1>

    <div class="network-stats-grid">
        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-admin-multisite"></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo esc_html($stats['total_sites'] ?? 0); ?></span>
                <span class="stat-label"><?php esc_html_e('Total Sites', 'formflow-pro'); ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-yes-alt"></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo esc_html($stats['active_sites'] ?? 0); ?></span>
                <span class="stat-label"><?php esc_html_e('Active Sites', 'formflow-pro'); ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-forms"></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo esc_html($stats['total_forms'] ?? 0); ?></span>
                <span class="stat-label"><?php esc_html_e('Total Forms', 'formflow-pro'); ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-email"></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo esc_html($stats['total_submissions'] ?? 0); ?></span>
                <span class="stat-label"><?php esc_html_e('Total Submissions', 'formflow-pro'); ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-media-document"></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo esc_html($stats['total_documents'] ?? 0); ?></span>
                <span class="stat-label"><?php esc_html_e('Autentique Documents', 'formflow-pro'); ?></span>
            </div>
        </div>

        <div class="stat-card highlight">
            <div class="stat-icon dashicons dashicons-chart-bar"></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo esc_html($stats['submissions_today'] ?? 0); ?></span>
                <span class="stat-label"><?php esc_html_e('Submissions Today', 'formflow-pro'); ?></span>
            </div>
        </div>
    </div>

    <div class="network-sections">
        <div class="section top-sites">
            <h2><?php esc_html_e('Top Sites by Submissions', 'formflow-pro'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Site', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('URL', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Submissions', 'formflow-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($stats['top_sites'])): ?>
                        <?php foreach ($stats['top_sites'] as $site): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($site['name']); ?></strong>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($site['url']); ?>" target="_blank">
                                        <?php echo esc_html($site['url']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="submission-count"><?php echo esc_html($site['submissions']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3"><?php esc_html_e('No data available.', 'formflow-pro'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section quick-actions">
            <h2><?php esc_html_e('Quick Actions', 'formflow-pro'); ?></h2>
            <div class="action-buttons">
                <a href="<?php echo esc_url(network_admin_url('admin.php?page=formflow-network-sites')); ?>" class="button button-primary">
                    <span class="dashicons dashicons-admin-multisite"></span>
                    <?php esc_html_e('Manage Sites', 'formflow-pro'); ?>
                </a>
                <a href="<?php echo esc_url(network_admin_url('admin.php?page=formflow-network-settings')); ?>" class="button">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('Network Settings', 'formflow-pro'); ?>
                </a>
                <a href="<?php echo esc_url(network_admin_url('admin.php?page=formflow-network-licenses')); ?>" class="button">
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php esc_html_e('License Management', 'formflow-pro'); ?>
                </a>
                <button type="button" class="button" id="sync-network-data">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Sync All Sites', 'formflow-pro'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.formflow-network-dashboard .network-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.formflow-network-dashboard .stat-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.formflow-network-dashboard .stat-card.highlight {
    background: linear-gradient(135deg, #0073aa 0%, #00a0d2 100%);
    color: #fff;
    border: none;
}

.formflow-network-dashboard .stat-icon {
    font-size: 40px;
    width: 40px;
    height: 40px;
    opacity: 0.8;
}

.formflow-network-dashboard .stat-value {
    display: block;
    font-size: 28px;
    font-weight: 600;
    line-height: 1.2;
}

.formflow-network-dashboard .stat-label {
    display: block;
    font-size: 13px;
    opacity: 0.8;
}

.formflow-network-dashboard .network-sections {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.formflow-network-dashboard .section {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
}

.formflow-network-dashboard .section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e0e0;
}

.formflow-network-dashboard .action-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.formflow-network-dashboard .action-buttons .button {
    display: flex;
    align-items: center;
    gap: 8px;
    justify-content: flex-start;
    padding: 10px 15px;
    height: auto;
}

.formflow-network-dashboard .submission-count {
    display: inline-block;
    background: #0073aa;
    color: #fff;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
}

@media (max-width: 782px) {
    .formflow-network-dashboard .network-sections {
        grid-template-columns: 1fr;
    }
}
</style>
