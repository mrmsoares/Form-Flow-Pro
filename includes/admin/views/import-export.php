<?php

/**
 * Import/Export Page
 *
 * @package FormFlowPro
 * @since 2.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle export request
if (isset($_POST['formflow_export']) && check_admin_referer('formflow_export', 'formflow_export_nonce')) {
    $export_type = sanitize_text_field($_POST['export_type'] ?? 'forms');
    $form_ids = isset($_POST['export_forms']) ? array_map('intval', $_POST['export_forms']) : [];

    // Export logic would be handled via AJAX for file download
}

// Handle import
if (isset($_POST['formflow_import']) && check_admin_referer('formflow_import', 'formflow_import_nonce')) {
    if (!empty($_FILES['import_file']['tmp_name'])) {
        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($file_content, true);

        if ($import_data && isset($import_data['forms'])) {
            $imported = 0;
            foreach ($import_data['forms'] as $form) {
                $wpdb->insert(
                    $wpdb->prefix . 'formflow_forms',
                    [
                        'name' => sanitize_text_field($form['name'] ?? 'Imported Form'),
                        'description' => sanitize_textarea_field($form['description'] ?? ''),
                        'fields' => wp_json_encode($form['fields'] ?? []),
                        'settings' => wp_json_encode($form['settings'] ?? []),
                        'status' => 'draft',
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
                );
                $imported++;
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(esc_html__('%d forms imported successfully.', 'formflow-pro'), $imported) . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid import file format.', 'formflow-pro') . '</p></div>';
        }
    }
}

// Get forms for export
$forms = $wpdb->get_results("SELECT id, name, status, created_at FROM {$wpdb->prefix}formflow_forms ORDER BY name ASC");

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'export';

?>

<div class="wrap formflow-admin formflow-import-export">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-database"></span>
        <?php esc_html_e('Import / Export', 'formflow-pro'); ?>
    </h1>

    <hr class="wp-header-end">

    <!-- Tabs -->
    <nav class="nav-tab-wrapper wp-clearfix" style="margin-top: 20px;">
        <a href="?page=formflow-import-export&tab=export" class="nav-tab <?php echo $active_tab === 'export' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-upload"></span>
            <?php esc_html_e('Export', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-import-export&tab=import" class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-download"></span>
            <?php esc_html_e('Import', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-import-export&tab=backup" class="nav-tab <?php echo $active_tab === 'backup' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-backup"></span>
            <?php esc_html_e('Backup', 'formflow-pro'); ?>
        </a>
    </nav>

    <!-- Export Tab -->
    <?php if ($active_tab === 'export') : ?>
        <div class="tab-content" style="margin-top: 20px;">
            <div class="card" style="padding: 20px;">
                <h2 style="margin-top: 0;">
                    <span class="dashicons dashicons-upload" style="color: #0073aa;"></span>
                    <?php esc_html_e('Export Forms & Data', 'formflow-pro'); ?>
                </h2>

                <form method="post" id="export-form">
                    <?php wp_nonce_field('formflow_export', 'formflow_export_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Export Type', 'formflow-pro'); ?></th>
                            <td>
                                <fieldset>
                                    <label style="display: block; margin-bottom: 10px;">
                                        <input type="radio" name="export_type" value="forms" checked>
                                        <?php esc_html_e('Forms Only', 'formflow-pro'); ?>
                                        <span style="color: #666; display: block; margin-left: 25px; font-size: 12px;">
                                            <?php esc_html_e('Export form structure and settings', 'formflow-pro'); ?>
                                        </span>
                                    </label>
                                    <label style="display: block; margin-bottom: 10px;">
                                        <input type="radio" name="export_type" value="forms_submissions">
                                        <?php esc_html_e('Forms with Submissions', 'formflow-pro'); ?>
                                        <span style="color: #666; display: block; margin-left: 25px; font-size: 12px;">
                                            <?php esc_html_e('Export forms including all submission data', 'formflow-pro'); ?>
                                        </span>
                                    </label>
                                    <label style="display: block; margin-bottom: 10px;">
                                        <input type="radio" name="export_type" value="submissions">
                                        <?php esc_html_e('Submissions Only (CSV)', 'formflow-pro'); ?>
                                        <span style="color: #666; display: block; margin-left: 25px; font-size: 12px;">
                                            <?php esc_html_e('Export submissions as CSV spreadsheet', 'formflow-pro'); ?>
                                        </span>
                                    </label>
                                    <label style="display: block;">
                                        <input type="radio" name="export_type" value="all">
                                        <?php esc_html_e('Complete Backup', 'formflow-pro'); ?>
                                        <span style="color: #666; display: block; margin-left: 25px; font-size: 12px;">
                                            <?php esc_html_e('Export all forms, submissions, and settings', 'formflow-pro'); ?>
                                        </span>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Select Forms', 'formflow-pro'); ?></th>
                            <td>
                                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                    <label style="display: block; margin-bottom: 10px; font-weight: 600;">
                                        <input type="checkbox" id="select-all-forms">
                                        <?php esc_html_e('Select All', 'formflow-pro'); ?>
                                    </label>
                                    <hr style="margin: 10px 0;">
                                    <?php if (empty($forms)) : ?>
                                        <p style="color: #666; margin: 0;"><?php esc_html_e('No forms available for export.', 'formflow-pro'); ?></p>
                                    <?php else : ?>
                                        <?php foreach ($forms as $form) : ?>
                                            <label style="display: block; margin-bottom: 8px;">
                                                <input type="checkbox" name="export_forms[]" value="<?php echo esc_attr($form->id); ?>" class="form-checkbox">
                                                <?php echo esc_html($form->name); ?>
                                                <span style="color: #999; font-size: 11px;">(<?php echo esc_html(ucfirst($form->status)); ?>)</span>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="formflow_export" class="button button-primary button-large">
                            <span class="dashicons dashicons-upload" style="margin-top: 4px;"></span>
                            <?php esc_html_e('Export', 'formflow-pro'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Import Tab -->
    <?php if ($active_tab === 'import') : ?>
        <div class="tab-content" style="margin-top: 20px;">
            <div class="card" style="padding: 20px;">
                <h2 style="margin-top: 0;">
                    <span class="dashicons dashicons-download" style="color: #0073aa;"></span>
                    <?php esc_html_e('Import Forms', 'formflow-pro'); ?>
                </h2>

                <div class="notice notice-info inline" style="margin: 0 0 20px 0;">
                    <p>
                        <strong><?php esc_html_e('Supported Formats:', 'formflow-pro'); ?></strong>
                        <?php esc_html_e('FormFlow Pro JSON (.json), WPForms export (.json), Gravity Forms export (.json)', 'formflow-pro'); ?>
                    </p>
                </div>

                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('formflow_import', 'formflow_import_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="import-file"><?php esc_html_e('Import File', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="file" id="import-file" name="import_file" accept=".json" required>
                                <p class="description"><?php esc_html_e('Select a JSON export file to import.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Import Options', 'formflow-pro'); ?></th>
                            <td>
                                <label style="display: block; margin-bottom: 10px;">
                                    <input type="checkbox" name="import_as_draft" value="1" checked>
                                    <?php esc_html_e('Import forms as draft', 'formflow-pro'); ?>
                                </label>
                                <label style="display: block;">
                                    <input type="checkbox" name="skip_duplicates" value="1">
                                    <?php esc_html_e('Skip forms that already exist', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" name="formflow_import" class="button button-primary button-large">
                            <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                            <?php esc_html_e('Import', 'formflow-pro'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Backup Tab -->
    <?php if ($active_tab === 'backup') : ?>
        <div class="tab-content" style="margin-top: 20px;">
            <div class="card" style="padding: 20px;">
                <h2 style="margin-top: 0;">
                    <span class="dashicons dashicons-backup" style="color: #0073aa;"></span>
                    <?php esc_html_e('Automatic Backups', 'formflow-pro'); ?>
                    <span class="badge badge-enterprise" style="background: linear-gradient(135deg, #27ae60, #2ecc71); color: #fff; font-size: 11px; padding: 3px 10px; border-radius: 3px; margin-left: 10px; vertical-align: middle;">
                        <?php esc_html_e('Enterprise', 'formflow-pro'); ?>
                    </span>
                </h2>

                <p style="color: #666;">
                    <?php esc_html_e('Configure automatic backups of your forms and submissions.', 'formflow-pro'); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto Backup', 'formflow-pro'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_backup" value="1" <?php checked(get_option('formflow_auto_backup', 0)); ?>>
                                <?php esc_html_e('Enable automatic backups', 'formflow-pro'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Backup Frequency', 'formflow-pro'); ?></th>
                        <td>
                            <select name="backup_frequency" class="regular-text">
                                <option value="daily"><?php esc_html_e('Daily', 'formflow-pro'); ?></option>
                                <option value="weekly"><?php esc_html_e('Weekly', 'formflow-pro'); ?></option>
                                <option value="monthly"><?php esc_html_e('Monthly', 'formflow-pro'); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Retention', 'formflow-pro'); ?></th>
                        <td>
                            <select name="backup_retention" class="regular-text">
                                <option value="7"><?php esc_html_e('7 days', 'formflow-pro'); ?></option>
                                <option value="30"><?php esc_html_e('30 days', 'formflow-pro'); ?></option>
                                <option value="90"><?php esc_html_e('90 days', 'formflow-pro'); ?></option>
                                <option value="365"><?php esc_html_e('1 year', 'formflow-pro'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Save Backup Settings', 'formflow-pro'); ?>
                    </button>
                    <button type="button" class="button" id="create-backup-now">
                        <?php esc_html_e('Create Backup Now', 'formflow-pro'); ?>
                    </button>
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('#select-all-forms').on('change', function() {
        $('.form-checkbox').prop('checked', $(this).prop('checked'));
    });
});
</script>
