<?php
/**
 * Network Sites Overview View
 *
 * @package FormFlowPro\Admin\Views
 * @since 2.3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap formflow-network-sites">
    <h1><?php esc_html_e('FormFlow Pro - Sites Overview', 'formflow-pro'); ?></h1>

    <div class="tablenav top">
        <div class="alignleft actions">
            <select id="filter-status">
                <option value=""><?php esc_html_e('All Sites', 'formflow-pro'); ?></option>
                <option value="active"><?php esc_html_e('Plugin Active', 'formflow-pro'); ?></option>
                <option value="inactive"><?php esc_html_e('Plugin Inactive', 'formflow-pro'); ?></option>
            </select>
            <button type="button" class="button" id="filter-apply">
                <?php esc_html_e('Filter', 'formflow-pro'); ?>
            </button>
        </div>
        <div class="alignright">
            <button type="button" class="button button-primary" id="activate-all">
                <?php esc_html_e('Activate All', 'formflow-pro'); ?>
            </button>
            <button type="button" class="button" id="sync-settings-all">
                <?php esc_html_e('Sync Settings to All', 'formflow-pro'); ?>
            </button>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped sites">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-cb check-column">
                    <input type="checkbox" id="cb-select-all">
                </th>
                <th scope="col" class="manage-column column-name">
                    <?php esc_html_e('Site', 'formflow-pro'); ?>
                </th>
                <th scope="col" class="manage-column column-status">
                    <?php esc_html_e('Status', 'formflow-pro'); ?>
                </th>
                <th scope="col" class="manage-column column-forms">
                    <?php esc_html_e('Forms', 'formflow-pro'); ?>
                </th>
                <th scope="col" class="manage-column column-submissions">
                    <?php esc_html_e('Submissions', 'formflow-pro'); ?>
                </th>
                <th scope="col" class="manage-column column-today">
                    <?php esc_html_e('Today', 'formflow-pro'); ?>
                </th>
                <th scope="col" class="manage-column column-version">
                    <?php esc_html_e('DB Version', 'formflow-pro'); ?>
                </th>
                <th scope="col" class="manage-column column-actions">
                    <?php esc_html_e('Actions', 'formflow-pro'); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sites as $site): ?>
                <tr class="site-row <?php echo $site['plugin_active'] ? 'active' : 'inactive'; ?>"
                    data-blog-id="<?php echo esc_attr($site['blog_id']); ?>">
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="sites[]" value="<?php echo esc_attr($site['blog_id']); ?>">
                    </th>
                    <td class="column-name">
                        <strong>
                            <a href="<?php echo esc_url($site['url'] . '/wp-admin/'); ?>" target="_blank">
                                <?php echo esc_html($site['name']); ?>
                            </a>
                        </strong>
                        <div class="row-actions">
                            <span class="view">
                                <a href="<?php echo esc_url($site['url']); ?>" target="_blank">
                                    <?php esc_html_e('Visit', 'formflow-pro'); ?>
                                </a> |
                            </span>
                            <span class="dashboard">
                                <a href="<?php echo esc_url($site['url'] . '/wp-admin/admin.php?page=formflow-pro'); ?>" target="_blank">
                                    <?php esc_html_e('Dashboard', 'formflow-pro'); ?>
                                </a>
                            </span>
                        </div>
                    </td>
                    <td class="column-status">
                        <?php if ($site['plugin_active']): ?>
                            <span class="status-badge active">
                                <?php esc_html_e('Active', 'formflow-pro'); ?>
                            </span>
                        <?php else: ?>
                            <span class="status-badge inactive">
                                <?php esc_html_e('Inactive', 'formflow-pro'); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="column-forms">
                        <?php echo esc_html($site['forms_count'] ?? '-'); ?>
                    </td>
                    <td class="column-submissions">
                        <?php echo esc_html($site['submissions_count'] ?? '-'); ?>
                    </td>
                    <td class="column-today">
                        <?php if (isset($site['submissions_today']) && $site['submissions_today'] > 0): ?>
                            <span class="today-badge"><?php echo esc_html($site['submissions_today']); ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="column-version">
                        <?php echo esc_html($site['db_version'] ?? '-'); ?>
                    </td>
                    <td class="column-actions">
                        <?php if ($site['plugin_active']): ?>
                            <button type="button" class="button button-small sync-site"
                                    data-blog-id="<?php echo esc_attr($site['blog_id']); ?>">
                                <?php esc_html_e('Sync', 'formflow-pro'); ?>
                            </button>
                        <?php else: ?>
                            <button type="button" class="button button-small button-primary activate-site"
                                    data-blog-id="<?php echo esc_attr($site['blog_id']); ?>">
                                <?php esc_html_e('Activate', 'formflow-pro'); ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-cb check-column">
                    <input type="checkbox" id="cb-select-all-2">
                </th>
                <th scope="col" class="manage-column column-name">
                    <?php esc_html_e('Site', 'formflow-pro'); ?>
                </th>
                <th scope="col" class="manage-column column-status">
                    <?php esc_html_e('Status', 'formflow-pro'); ?>
                </th>
                <th scope="col" class="manage-column column-forms">
                    <?php esc_html_e('Forms', 'formflow-pro'); ?>
                </th>
                <th scope="col" class="manage-column column-submissions">
                    <?php esc_html_e('Submissions', 'formflow-pro'); ?>
                </th>
                <th scope="col" class="manage-column column-today">
                    <?php esc_html_e('Today', 'formflow-pro'); ?>
                </th>
                <th scope="col" class="manage-column column-version">
                    <?php esc_html_e('DB Version', 'formflow-pro'); ?>
                </th>
                <th scope="col" class="manage-column column-actions">
                    <?php esc_html_e('Actions', 'formflow-pro'); ?>
                </th>
            </tr>
        </tfoot>
    </table>

    <div class="tablenav bottom">
        <div class="alignleft actions bulkactions">
            <select id="bulk-action-selector">
                <option value=""><?php esc_html_e('Bulk Actions', 'formflow-pro'); ?></option>
                <option value="activate"><?php esc_html_e('Activate', 'formflow-pro'); ?></option>
                <option value="deactivate"><?php esc_html_e('Deactivate', 'formflow-pro'); ?></option>
                <option value="sync"><?php esc_html_e('Sync Settings', 'formflow-pro'); ?></option>
            </select>
            <button type="button" class="button" id="do-bulk-action">
                <?php esc_html_e('Apply', 'formflow-pro'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.formflow-network-sites .status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.formflow-network-sites .status-badge.active {
    background: #d4edda;
    color: #155724;
}

.formflow-network-sites .status-badge.inactive {
    background: #f8d7da;
    color: #721c24;
}

.formflow-network-sites .today-badge {
    display: inline-block;
    background: #0073aa;
    color: #fff;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
}

.formflow-network-sites .site-row.inactive {
    background: #f9f9f9;
}

.formflow-network-sites .tablenav .alignright {
    display: flex;
    gap: 10px;
}
</style>
