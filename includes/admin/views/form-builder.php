<?php

/**
 * Form Builder Page
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
$form = null;

if ($form_id > 0) {
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}formflow_forms WHERE id = %d",
        $form_id
    ));
}

$is_new = empty($form);
$form_fields = $is_new ? [] : json_decode($form->fields ?? '[]', true);
$form_settings = $is_new ? [] : json_decode($form->settings ?? '{}', true);

// Available field types
$field_types = [
    'text' => __('Text', 'formflow-pro'),
    'email' => __('Email', 'formflow-pro'),
    'textarea' => __('Textarea', 'formflow-pro'),
    'number' => __('Number', 'formflow-pro'),
    'phone' => __('Phone', 'formflow-pro'),
    'date' => __('Date', 'formflow-pro'),
    'select' => __('Select', 'formflow-pro'),
    'checkbox' => __('Checkbox', 'formflow-pro'),
    'radio' => __('Radio', 'formflow-pro'),
    'file' => __('File Upload', 'formflow-pro'),
    'hidden' => __('Hidden', 'formflow-pro'),
    'signature' => __('Signature', 'formflow-pro'),
];

?>

<div class="wrap formflow-admin formflow-form-builder">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-edit"></span>
        <?php echo $is_new ? esc_html__('Create New Form', 'formflow-pro') : esc_html__('Edit Form', 'formflow-pro'); ?>
    </h1>

    <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-forms')); ?>" class="page-title-action">
        <span class="dashicons dashicons-arrow-left-alt" style="vertical-align: middle;"></span>
        <?php esc_html_e('Back to Forms', 'formflow-pro'); ?>
    </a>

    <hr class="wp-header-end">

    <form method="post" id="form-builder-main" data-form-id="<?php echo esc_attr($form_id); ?>">
        <?php wp_nonce_field('formflow_form_builder', 'formflow_builder_nonce'); ?>

        <div class="formflow-builder-layout" style="display: grid; grid-template-columns: 280px 1fr 300px; gap: 20px; margin-top: 20px;">

            <!-- Left Panel: Field Types -->
            <div class="formflow-builder-panel">
                <div class="card" style="padding: 15px;">
                    <h3 style="margin-top: 0;">
                        <span class="dashicons dashicons-plus-alt" style="color: #0073aa;"></span>
                        <?php esc_html_e('Add Fields', 'formflow-pro'); ?>
                    </h3>

                    <div class="formflow-field-types" style="display: flex; flex-direction: column; gap: 8px;">
                        <?php foreach ($field_types as $type => $label) : ?>
                            <button type="button"
                                    class="button formflow-add-field"
                                    data-type="<?php echo esc_attr($type); ?>"
                                    style="text-align: left; justify-content: flex-start;">
                                <span class="dashicons dashicons-plus" style="margin-right: 5px;"></span>
                                <?php echo esc_html($label); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Center Panel: Form Preview -->
            <div class="formflow-builder-canvas">
                <div class="card" style="padding: 20px;">
                    <div class="formflow-form-header" style="margin-bottom: 20px;">
                        <input type="text"
                               id="form-name"
                               name="form_name"
                               value="<?php echo esc_attr($form->name ?? ''); ?>"
                               placeholder="<?php esc_attr_e('Form Name', 'formflow-pro'); ?>"
                               class="large-text"
                               style="font-size: 20px; font-weight: 600; border: 0; border-bottom: 2px solid #ddd; padding: 10px 0;"
                               required>

                        <textarea id="form-description"
                                  name="form_description"
                                  placeholder="<?php esc_attr_e('Form description (optional)', 'formflow-pro'); ?>"
                                  class="large-text"
                                  rows="2"
                                  style="border: 0; border-bottom: 1px solid #eee; padding: 10px 0; resize: none; margin-top: 10px;"><?php echo esc_textarea($form->description ?? ''); ?></textarea>
                    </div>

                    <div id="form-fields-canvas" class="formflow-fields-canvas" style="min-height: 300px; background: #f9f9f9; border: 2px dashed #ddd; border-radius: 4px; padding: 20px;">
                        <?php if (empty($form_fields)) : ?>
                            <div class="formflow-empty-canvas" style="text-align: center; padding: 60px 20px; color: #666;">
                                <span class="dashicons dashicons-forms" style="font-size: 48px; width: 48px; height: 48px; margin-bottom: 10px;"></span>
                                <p><?php esc_html_e('Drag and drop fields here or click a field type to add.', 'formflow-pro'); ?></p>
                            </div>
                        <?php else : ?>
                            <?php foreach ($form_fields as $index => $field) : ?>
                                <div class="formflow-field-item" data-field-index="<?php echo esc_attr($index); ?>">
                                    <div class="field-header">
                                        <span class="field-type-badge"><?php echo esc_html($field_types[$field['type']] ?? $field['type']); ?></span>
                                        <span class="field-label"><?php echo esc_html($field['label'] ?? ''); ?></span>
                                        <span class="field-actions">
                                            <button type="button" class="button-link edit-field"><span class="dashicons dashicons-edit"></span></button>
                                            <button type="button" class="button-link delete-field"><span class="dashicons dashicons-trash"></span></button>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <input type="hidden" name="form_fields" id="form-fields-data" value="<?php echo esc_attr(json_encode($form_fields)); ?>">
                </div>
            </div>

            <!-- Right Panel: Form Settings -->
            <div class="formflow-builder-settings">
                <div class="card" style="padding: 15px;">
                    <h3 style="margin-top: 0;">
                        <span class="dashicons dashicons-admin-settings" style="color: #0073aa;"></span>
                        <?php esc_html_e('Form Settings', 'formflow-pro'); ?>
                    </h3>

                    <div class="formflow-setting-group" style="margin-bottom: 15px;">
                        <label for="form-status" style="font-weight: 600; display: block; margin-bottom: 5px;">
                            <?php esc_html_e('Status', 'formflow-pro'); ?>
                        </label>
                        <select id="form-status" name="form_status" class="regular-text" style="width: 100%;">
                            <option value="draft" <?php selected($form->status ?? 'draft', 'draft'); ?>><?php esc_html_e('Draft', 'formflow-pro'); ?></option>
                            <option value="active" <?php selected($form->status ?? '', 'active'); ?>><?php esc_html_e('Active', 'formflow-pro'); ?></option>
                            <option value="inactive" <?php selected($form->status ?? '', 'inactive'); ?>><?php esc_html_e('Inactive', 'formflow-pro'); ?></option>
                        </select>
                    </div>

                    <div class="formflow-setting-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                            <?php esc_html_e('Confirmation', 'formflow-pro'); ?>
                        </label>
                        <select name="settings[confirmation_type]" class="regular-text" style="width: 100%;">
                            <option value="message" <?php selected($form_settings['confirmation_type'] ?? 'message', 'message'); ?>><?php esc_html_e('Show Message', 'formflow-pro'); ?></option>
                            <option value="redirect" <?php selected($form_settings['confirmation_type'] ?? '', 'redirect'); ?>><?php esc_html_e('Redirect', 'formflow-pro'); ?></option>
                        </select>
                    </div>

                    <div class="formflow-setting-group" style="margin-bottom: 15px;">
                        <label>
                            <input type="checkbox" name="settings[enable_notifications]" value="1" <?php checked($form_settings['enable_notifications'] ?? true); ?>>
                            <?php esc_html_e('Email Notifications', 'formflow-pro'); ?>
                        </label>
                    </div>

                    <div class="formflow-setting-group" style="margin-bottom: 15px;">
                        <label>
                            <input type="checkbox" name="settings[enable_autentique]" value="1" <?php checked($form_settings['enable_autentique'] ?? false); ?>>
                            <?php esc_html_e('Digital Signature (Autentique)', 'formflow-pro'); ?>
                        </label>
                    </div>

                    <div class="formflow-setting-group" style="margin-bottom: 15px;">
                        <label>
                            <input type="checkbox" name="settings[enable_payments]" value="1" <?php checked($form_settings['enable_payments'] ?? false); ?>>
                            <?php esc_html_e('Enable Payments', 'formflow-pro'); ?>
                        </label>
                    </div>
                </div>

                <?php if (!$is_new) : ?>
                <div class="card" style="padding: 15px; margin-top: 15px;">
                    <h3 style="margin-top: 0;">
                        <span class="dashicons dashicons-shortcode" style="color: #0073aa;"></span>
                        <?php esc_html_e('Shortcode', 'formflow-pro'); ?>
                    </h3>
                    <code style="display: block; padding: 10px; background: #f5f5f5; border-radius: 3px; word-break: break-all;">
                        [formflow id="<?php echo esc_attr($form_id); ?>"]
                    </code>
                </div>
                <?php endif; ?>

                <div style="margin-top: 20px;">
                    <button type="submit" class="button button-primary button-large" style="width: 100%;">
                        <span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
                        <?php esc_html_e('Save Form', 'formflow-pro'); ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.formflow-field-item {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 12px;
    margin-bottom: 10px;
    cursor: move;
}
.formflow-field-item:hover {
    border-color: #0073aa;
}
.formflow-field-item .field-header {
    display: flex;
    align-items: center;
    gap: 10px;
}
.formflow-field-item .field-type-badge {
    background: #0073aa;
    color: #fff;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
}
.formflow-field-item .field-label {
    flex: 1;
    font-weight: 500;
}
.formflow-field-item .field-actions {
    display: flex;
    gap: 5px;
}
.formflow-field-item .field-actions button {
    color: #666;
}
.formflow-field-item .field-actions button:hover {
    color: #0073aa;
}
</style>
