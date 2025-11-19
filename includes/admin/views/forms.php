<?php

/**
 * Forms Management Page
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle form actions
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;

// Handle delete action
if ($action === 'delete' && $form_id && check_admin_referer('delete_form_' . $form_id)) {
    $wpdb->delete(
        $wpdb->prefix . 'formflow_forms',
        ['id' => $form_id],
        ['%d']
    );
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Form deleted successfully.', 'formflow-pro') . '</p></div>';
}

// Handle duplicate action
if ($action === 'duplicate' && $form_id && check_admin_referer('duplicate_form_' . $form_id)) {
    $original_form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}formflow_forms WHERE id = %d",
        $form_id
    ));

    if ($original_form) {
        $wpdb->insert(
            $wpdb->prefix . 'formflow_forms',
            [
                'name' => $original_form->name . ' (Copy)',
                'description' => $original_form->description,
                'fields' => $original_form->fields,
                'settings' => $original_form->settings,
                'status' => 'draft',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Form duplicated successfully.', 'formflow-pro') . '</p></div>';
    }
}

// Handle toggle status
if ($action === 'toggle_status' && $form_id && check_admin_referer('toggle_status_' . $form_id)) {
    $current_status = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM {$wpdb->prefix}formflow_forms WHERE id = %d",
        $form_id
    ));

    $new_status = ($current_status === 'active') ? 'inactive' : 'active';

    $wpdb->update(
        $wpdb->prefix . 'formflow_forms',
        ['status' => $new_status, 'updated_at' => current_time('mysql')],
        ['id' => $form_id],
        ['%s', '%s'],
        ['%d']
    );

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Form status updated.', 'formflow-pro') . '</p></div>';
}

// Get all forms
$forms = $wpdb->get_results("
    SELECT
        f.*,
        COUNT(DISTINCT s.id) as submission_count
    FROM {$wpdb->prefix}formflow_forms f
    LEFT JOIN {$wpdb->prefix}formflow_submissions s ON f.id = s.form_id
    GROUP BY f.id
    ORDER BY f.created_at DESC
");

?>

<div class="wrap formflow-admin formflow-forms">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Forms', 'formflow-pro'); ?>
    </h1>

    <a href="#" class="page-title-action" id="add-new-form">
        <?php esc_html_e('Add New', 'formflow-pro'); ?>
    </a>

    <hr class="wp-header-end">

    <?php if (empty($forms)) : ?>
        <!-- Empty State -->
        <div class="formflow-empty-state">
            <div class="card">
                <div class="formflow-empty-icon">
                    <span class="dashicons dashicons-forms"></span>
                </div>
                <h2><?php esc_html_e('No Forms Yet', 'formflow-pro'); ?></h2>
                <p><?php esc_html_e('Get started by creating your first form.', 'formflow-pro'); ?></p>
                <a href="#" class="button button-primary button-hero" id="create-first-form">
                    <?php esc_html_e('Create Your First Form', 'formflow-pro'); ?>
                </a>
            </div>
        </div>
    <?php else : ?>
        <!-- Forms Table -->
        <div class="card" style="margin-top: 20px;">
            <table class="wp-list-table widefat fixed striped formflow-forms-table">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all">
                        </th>
                        <th scope="col" class="manage-column column-name column-primary">
                            <?php esc_html_e('Name', 'formflow-pro'); ?>
                        </th>
                        <th scope="col" class="manage-column column-status">
                            <?php esc_html_e('Status', 'formflow-pro'); ?>
                        </th>
                        <th scope="col" class="manage-column column-submissions">
                            <?php esc_html_e('Submissions', 'formflow-pro'); ?>
                        </th>
                        <th scope="col" class="manage-column column-date">
                            <?php esc_html_e('Created', 'formflow-pro'); ?>
                        </th>
                        <th scope="col" class="manage-column column-actions">
                            <?php esc_html_e('Actions', 'formflow-pro'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forms as $form) : ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="form[]" value="<?php echo esc_attr($form->id); ?>">
                            </th>
                            <td class="column-name column-primary" data-colname="Name">
                                <strong>
                                    <a href="#" class="row-title edit-form" data-form-id="<?php echo esc_attr($form->id); ?>">
                                        <?php echo esc_html($form->name); ?>
                                    </a>
                                </strong>
                                <?php if (!empty($form->description)) : ?>
                                    <p class="description"><?php echo esc_html($form->description); ?></p>
                                <?php endif; ?>

                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="#" class="edit-form" data-form-id="<?php echo esc_attr($form->id); ?>">
                                            <?php esc_html_e('Edit', 'formflow-pro'); ?>
                                        </a> |
                                    </span>
                                    <span class="duplicate">
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=formflow-forms&action=duplicate&form_id=' . $form->id), 'duplicate_form_' . $form->id); ?>">
                                            <?php esc_html_e('Duplicate', 'formflow-pro'); ?>
                                        </a> |
                                    </span>
                                    <span class="trash">
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=formflow-forms&action=delete&form_id=' . $form->id), 'delete_form_' . $form->id); ?>"
                                           class="submitdelete"
                                           onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this form?', 'formflow-pro')); ?>');">
                                            <?php esc_html_e('Delete', 'formflow-pro'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td class="column-status" data-colname="Status">
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=formflow-forms&action=toggle_status&form_id=' . $form->id), 'toggle_status_' . $form->id); ?>">
                                    <span class="status-badge status-<?php echo esc_attr($form->status); ?>">
                                        <?php echo esc_html(ucfirst($form->status)); ?>
                                    </span>
                                </a>
                            </td>
                            <td class="column-submissions" data-colname="Submissions">
                                <a href="<?php echo admin_url('admin.php?page=formflow-submissions&form_id=' . $form->id); ?>">
                                    <?php echo esc_html($form->submission_count); ?>
                                </a>
                            </td>
                            <td class="column-date" data-colname="Created">
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($form->created_at))); ?>
                            </td>
                            <td class="column-actions" data-colname="Actions">
                                <a href="#" class="button button-small edit-form" data-form-id="<?php echo esc_attr($form->id); ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                    <?php esc_html_e('Edit', 'formflow-pro'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Form Builder Modal -->
<div id="form-builder-modal" class="formflow-modal" style="display: none;">
    <div class="formflow-modal-overlay"></div>
    <div class="formflow-modal-content">
        <div class="formflow-modal-header">
            <h2 id="form-builder-title"><?php esc_html_e('Form Builder', 'formflow-pro'); ?></h2>
            <button type="button" class="formflow-modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>

        <div class="formflow-modal-body">
            <form id="form-builder-form">
                <input type="hidden" name="form_id" id="form-id" value="">
                <input type="hidden" name="action" value="formflow_save_form">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('formflow_save_form'); ?>">

                <!-- Basic Info -->
                <div class="formflow-form-section">
                    <h3><?php esc_html_e('Basic Information', 'formflow-pro'); ?></h3>

                    <div class="formflow-form-group">
                        <label for="form-name">
                            <?php esc_html_e('Form Name', 'formflow-pro'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="text"
                               id="form-name"
                               name="form_name"
                               class="regular-text"
                               required
                               placeholder="<?php esc_attr_e('Enter form name', 'formflow-pro'); ?>">
                    </div>

                    <div class="formflow-form-group">
                        <label for="form-description">
                            <?php esc_html_e('Description', 'formflow-pro'); ?>
                        </label>
                        <textarea id="form-description"
                                  name="form_description"
                                  class="large-text"
                                  rows="3"
                                  placeholder="<?php esc_attr_e('Optional description', 'formflow-pro'); ?>"></textarea>
                    </div>

                    <div class="formflow-form-group">
                        <label for="form-status">
                            <?php esc_html_e('Status', 'formflow-pro'); ?>
                        </label>
                        <select id="form-status" name="form_status" class="regular-text">
                            <option value="draft"><?php esc_html_e('Draft', 'formflow-pro'); ?></option>
                            <option value="active"><?php esc_html_e('Active', 'formflow-pro'); ?></option>
                            <option value="inactive"><?php esc_html_e('Inactive', 'formflow-pro'); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Form Fields -->
                <div class="formflow-form-section">
                    <h3><?php esc_html_e('Form Fields', 'formflow-pro'); ?></h3>

                    <div id="form-fields-container">
                        <!-- Fields will be dynamically added here -->
                    </div>

                    <button type="button" class="button" id="add-form-field">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('Add Field', 'formflow-pro'); ?>
                    </button>
                </div>

                <!-- Autentique Settings -->
                <div class="formflow-form-section">
                    <h3><?php esc_html_e('Autentique Digital Signature', 'formflow-pro'); ?></h3>

                    <div class="formflow-form-group">
                        <label>
                            <input type="checkbox"
                                   id="enable-autentique"
                                   name="enable_autentique"
                                   value="1">
                            <?php esc_html_e('Enable digital signature for this form', 'formflow-pro'); ?>
                        </label>
                    </div>

                    <div id="autentique-settings" style="display: none;">
                        <div class="formflow-form-group">
                            <label for="document-template">
                                <?php esc_html_e('Document Template', 'formflow-pro'); ?>
                            </label>
                            <select id="document-template" name="document_template" class="regular-text">
                                <option value=""><?php esc_html_e('Select template...', 'formflow-pro'); ?></option>
                                <!-- Templates will be loaded via AJAX -->
                            </select>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="formflow-modal-footer">
            <button type="button" class="button" id="cancel-form-builder">
                <?php esc_html_e('Cancel', 'formflow-pro'); ?>
            </button>
            <button type="button" class="button button-primary" id="save-form-builder">
                <?php esc_html_e('Save Form', 'formflow-pro'); ?>
            </button>
        </div>
    </div>
</div>
