<?php

/**
 * Automation & Workflows Admin Page
 *
 * @package FormFlowPro
 * @since 2.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle form submission for creating/editing workflows
if (isset($_POST['formflow_workflow_submit'])) {
    check_admin_referer('formflow_workflow', 'formflow_workflow_nonce');

    $workflow_data = [
        'name' => sanitize_text_field($_POST['workflow_name'] ?? ''),
        'description' => sanitize_textarea_field($_POST['workflow_description'] ?? ''),
        'trigger_type' => sanitize_text_field($_POST['trigger_type'] ?? 'form_submission'),
        'trigger_config' => wp_json_encode($_POST['trigger_config'] ?? []),
        'conditions' => wp_json_encode($_POST['conditions'] ?? []),
        'actions' => wp_json_encode($_POST['actions'] ?? []),
        'status' => sanitize_text_field($_POST['workflow_status'] ?? 'draft'),
        'updated_at' => current_time('mysql'),
    ];

    if (!empty($_POST['workflow_id'])) {
        $wpdb->update(
            $wpdb->prefix . 'formflow_workflows',
            $workflow_data,
            ['id' => intval($_POST['workflow_id'])],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Workflow updated successfully.', 'formflow-pro') . '</p></div>';
    } else {
        $workflow_data['created_at'] = current_time('mysql');
        $wpdb->insert(
            $wpdb->prefix . 'formflow_workflows',
            $workflow_data,
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Workflow created successfully.', 'formflow-pro') . '</p></div>';
    }
}

// Handle workflow deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['workflow_id'])) {
    $workflow_id = intval($_GET['workflow_id']);
    if (check_admin_referer('delete_workflow_' . $workflow_id)) {
        $wpdb->delete(
            $wpdb->prefix . 'formflow_workflows',
            ['id' => $workflow_id],
            ['%d']
        );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Workflow deleted successfully.', 'formflow-pro') . '</p></div>';
    }
}

// Get current view
$current_view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'list';
$workflow_id = isset($_GET['workflow_id']) ? intval($_GET['workflow_id']) : 0;

// Get statistics
$stats = [
    'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_workflows"),
    'active' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_workflows WHERE status = 'active'"),
    'draft' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_workflows WHERE status = 'draft'"),
    'executions_today' => (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_workflow_logs WHERE DATE(created_at) = %s",
        current_time('Y-m-d')
    )),
];

// Get forms for dropdown
$forms = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}formflow_forms ORDER BY name ASC");

?>

<div class="wrap formflow-admin formflow-automation">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-randomize"></span>
        <?php esc_html_e('Automation & Workflows', 'formflow-pro'); ?>
    </h1>

    <?php if ($current_view === 'list') : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-automation&view=edit')); ?>" class="page-title-action">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php esc_html_e('Add New Workflow', 'formflow-pro'); ?>
        </a>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- Stats Cards -->
    <div class="formflow-stats-cards" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
        <div class="formflow-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div class="stat-icon" style="font-size: 32px; color: #0073aa;">
                <span class="dashicons dashicons-randomize"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value" style="font-size: 28px; font-weight: bold; color: #23282d;"><?php echo esc_html($stats['total']); ?></div>
                <div class="stat-label" style="color: #666; font-size: 13px;"><?php esc_html_e('Total Workflows', 'formflow-pro'); ?></div>
            </div>
        </div>

        <div class="formflow-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div class="stat-icon" style="font-size: 32px; color: #46b450;">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value" style="font-size: 28px; font-weight: bold; color: #23282d;"><?php echo esc_html($stats['active']); ?></div>
                <div class="stat-label" style="color: #666; font-size: 13px;"><?php esc_html_e('Active', 'formflow-pro'); ?></div>
            </div>
        </div>

        <div class="formflow-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div class="stat-icon" style="font-size: 32px; color: #f0ad4e;">
                <span class="dashicons dashicons-edit"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value" style="font-size: 28px; font-weight: bold; color: #23282d;"><?php echo esc_html($stats['draft']); ?></div>
                <div class="stat-label" style="color: #666; font-size: 13px;"><?php esc_html_e('Draft', 'formflow-pro'); ?></div>
            </div>
        </div>

        <div class="formflow-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div class="stat-icon" style="font-size: 32px; color: #9b59b6;">
                <span class="dashicons dashicons-performance"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value" style="font-size: 28px; font-weight: bold; color: #23282d;"><?php echo esc_html($stats['executions_today']); ?></div>
                <div class="stat-label" style="color: #666; font-size: 13px;"><?php esc_html_e('Executions Today', 'formflow-pro'); ?></div>
            </div>
        </div>
    </div>

    <?php if ($current_view === 'list') : ?>
        <!-- Workflows List View -->
        <div class="card" style="margin-top: 20px; padding: 0;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 5%;"><?php esc_html_e('ID', 'formflow-pro'); ?></th>
                        <th style="width: 25%;"><?php esc_html_e('Name', 'formflow-pro'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Trigger', 'formflow-pro'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                        <th style="width: 10%;"><?php esc_html_e('Status', 'formflow-pro'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Last Run', 'formflow-pro'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Operations', 'formflow-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $workflows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}formflow_workflows ORDER BY created_at DESC");

                    if (empty($workflows)) :
                    ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <div style="color: #666;">
                                    <span class="dashicons dashicons-randomize" style="font-size: 48px; width: 48px; height: 48px; margin-bottom: 10px; color: #ccc;"></span>
                                    <p style="font-size: 16px; margin: 10px 0;"><?php esc_html_e('No workflows created yet.', 'formflow-pro'); ?></p>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-automation&view=edit')); ?>" class="button button-primary">
                                        <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
                                        <?php esc_html_e('Create Your First Workflow', 'formflow-pro'); ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($workflows as $workflow) :
                            $actions = json_decode($workflow->actions ?? '[]', true);
                            $actions_count = is_array($actions) ? count($actions) : 0;
                            $trigger_labels = [
                                'form_submission' => __('Form Submission', 'formflow-pro'),
                                'status_change' => __('Status Change', 'formflow-pro'),
                                'signature_completed' => __('Signature Completed', 'formflow-pro'),
                                'scheduled' => __('Scheduled', 'formflow-pro'),
                                'webhook' => __('Webhook', 'formflow-pro'),
                            ];
                        ?>
                            <tr>
                                <td><strong>#<?php echo esc_html($workflow->id); ?></strong></td>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-automation&view=edit&workflow_id=' . $workflow->id)); ?>">
                                            <?php echo esc_html($workflow->name); ?>
                                        </a>
                                    </strong>
                                    <?php if (!empty($workflow->description)) : ?>
                                        <br><span style="color: #666; font-size: 12px;"><?php echo esc_html(wp_trim_words($workflow->description, 10)); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="dashicons dashicons-flag" style="color: #0073aa;"></span>
                                    <?php echo esc_html($trigger_labels[$workflow->trigger_type] ?? $workflow->trigger_type); ?>
                                </td>
                                <td>
                                    <span class="dashicons dashicons-list-view" style="color: #666;"></span>
                                    <?php printf(esc_html(_n('%d action', '%d actions', $actions_count, 'formflow-pro')), $actions_count); ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = $workflow->status === 'active' ? 'status-active' : 'status-draft';
                                    $status_color = $workflow->status === 'active' ? '#46b450' : '#f0ad4e';
                                    ?>
                                    <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; background: <?php echo esc_attr($status_color); ?>22; border-radius: 3px; color: <?php echo esc_attr($status_color); ?>; font-weight: 500;">
                                        <span class="dashicons dashicons-<?php echo $workflow->status === 'active' ? 'yes' : 'edit'; ?>" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                        <?php echo esc_html(ucfirst($workflow->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $last_run = $wpdb->get_var($wpdb->prepare(
                                        "SELECT created_at FROM {$wpdb->prefix}formflow_workflow_logs WHERE workflow_id = %d ORDER BY created_at DESC LIMIT 1",
                                        $workflow->id
                                    ));
                                    echo $last_run ? esc_html(human_time_diff(strtotime($last_run)) . ' ' . __('ago', 'formflow-pro')) : '<span style="color: #999;">' . esc_html__('Never', 'formflow-pro') . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-automation&view=edit&workflow_id=' . $workflow->id)); ?>" class="button button-small" title="<?php esc_attr_e('Edit', 'formflow-pro'); ?>">
                                        <span class="dashicons dashicons-edit" style="margin-top: 4px;"></span>
                                    </a>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-automation&view=logs&workflow_id=' . $workflow->id)); ?>" class="button button-small" title="<?php esc_attr_e('View Logs', 'formflow-pro'); ?>">
                                        <span class="dashicons dashicons-list-view" style="margin-top: 4px;"></span>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=formflow-automation&action=delete&workflow_id=' . $workflow->id), 'delete_workflow_' . $workflow->id)); ?>"
                                       class="button button-small"
                                       style="color: #dc3545;"
                                       onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this workflow?', 'formflow-pro'); ?>');"
                                       title="<?php esc_attr_e('Delete', 'formflow-pro'); ?>">
                                        <span class="dashicons dashicons-trash" style="margin-top: 4px;"></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($current_view === 'edit') : ?>
        <!-- Workflow Editor View -->
        <?php
        $workflow = null;
        if ($workflow_id) {
            $workflow = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}formflow_workflows WHERE id = %d",
                $workflow_id
            ));
        }
        $trigger_config = $workflow ? json_decode($workflow->trigger_config ?? '{}', true) : [];
        $conditions = $workflow ? json_decode($workflow->conditions ?? '[]', true) : [];
        $actions = $workflow ? json_decode($workflow->actions ?? '[]', true) : [];
        ?>

        <div class="card" style="margin-top: 20px;">
            <form method="post" action="" id="workflow-editor-form">
                <?php wp_nonce_field('formflow_workflow', 'formflow_workflow_nonce'); ?>
                <input type="hidden" name="workflow_id" value="<?php echo esc_attr($workflow_id); ?>">

                <h2 style="margin-top: 0;">
                    <?php echo $workflow ? esc_html__('Edit Workflow', 'formflow-pro') : esc_html__('Create New Workflow', 'formflow-pro'); ?>
                </h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="workflow_name"><?php esc_html_e('Workflow Name', 'formflow-pro'); ?> <span style="color: #dc3545;">*</span></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="workflow_name"
                                   name="workflow_name"
                                   value="<?php echo esc_attr($workflow->name ?? ''); ?>"
                                   class="regular-text"
                                   required>
                            <p class="description"><?php esc_html_e('Give your workflow a descriptive name.', 'formflow-pro'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="workflow_description"><?php esc_html_e('Description', 'formflow-pro'); ?></label>
                        </th>
                        <td>
                            <textarea id="workflow_description"
                                      name="workflow_description"
                                      rows="3"
                                      class="large-text"><?php echo esc_textarea($workflow->description ?? ''); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="trigger_type"><?php esc_html_e('Trigger', 'formflow-pro'); ?></label>
                        </th>
                        <td>
                            <select id="trigger_type" name="trigger_type" class="regular-text">
                                <option value="form_submission" <?php selected($workflow->trigger_type ?? '', 'form_submission'); ?>>
                                    <?php esc_html_e('Form Submission', 'formflow-pro'); ?>
                                </option>
                                <option value="status_change" <?php selected($workflow->trigger_type ?? '', 'status_change'); ?>>
                                    <?php esc_html_e('Status Change', 'formflow-pro'); ?>
                                </option>
                                <option value="signature_completed" <?php selected($workflow->trigger_type ?? '', 'signature_completed'); ?>>
                                    <?php esc_html_e('Signature Completed', 'formflow-pro'); ?>
                                </option>
                                <option value="scheduled" <?php selected($workflow->trigger_type ?? '', 'scheduled'); ?>>
                                    <?php esc_html_e('Scheduled (Cron)', 'formflow-pro'); ?>
                                </option>
                                <option value="webhook" <?php selected($workflow->trigger_type ?? '', 'webhook'); ?>>
                                    <?php esc_html_e('Webhook Received', 'formflow-pro'); ?>
                                </option>
                            </select>
                            <p class="description"><?php esc_html_e('Select when this workflow should be triggered.', 'formflow-pro'); ?></p>
                        </td>
                    </tr>

                    <tr id="trigger-form-row">
                        <th scope="row">
                            <label for="trigger_form_id"><?php esc_html_e('Form', 'formflow-pro'); ?></label>
                        </th>
                        <td>
                            <select id="trigger_form_id" name="trigger_config[form_id]" class="regular-text">
                                <option value=""><?php esc_html_e('All Forms', 'formflow-pro'); ?></option>
                                <?php foreach ($forms as $form) : ?>
                                    <option value="<?php echo esc_attr($form->id); ?>" <?php selected($trigger_config['form_id'] ?? '', $form->id); ?>>
                                        <?php echo esc_html($form->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="workflow_status"><?php esc_html_e('Status', 'formflow-pro'); ?></label>
                        </th>
                        <td>
                            <select id="workflow_status" name="workflow_status" class="regular-text">
                                <option value="draft" <?php selected($workflow->status ?? 'draft', 'draft'); ?>>
                                    <?php esc_html_e('Draft', 'formflow-pro'); ?>
                                </option>
                                <option value="active" <?php selected($workflow->status ?? '', 'active'); ?>>
                                    <?php esc_html_e('Active', 'formflow-pro'); ?>
                                </option>
                                <option value="paused" <?php selected($workflow->status ?? '', 'paused'); ?>>
                                    <?php esc_html_e('Paused', 'formflow-pro'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- Conditions Section -->
                <div class="workflow-section" style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                        <span class="dashicons dashicons-filter" style="color: #0073aa;"></span>
                        <?php esc_html_e('Conditions', 'formflow-pro'); ?>
                        <span style="font-weight: normal; font-size: 13px; color: #666;">(<?php esc_html_e('Optional', 'formflow-pro'); ?>)</span>
                    </h3>
                    <p class="description"><?php esc_html_e('Add conditions to control when this workflow should run.', 'formflow-pro'); ?></p>

                    <div id="conditions-container" style="margin-top: 15px;">
                        <?php if (!empty($conditions)) : ?>
                            <?php foreach ($conditions as $index => $condition) : ?>
                                <div class="condition-row" style="display: flex; gap: 10px; margin-bottom: 10px; padding: 10px; background: #fff; border-radius: 4px;">
                                    <select name="conditions[<?php echo $index; ?>][field]" class="condition-field">
                                        <option value="form_data.email" <?php selected($condition['field'] ?? '', 'form_data.email'); ?>><?php esc_html_e('Email', 'formflow-pro'); ?></option>
                                        <option value="form_data.name" <?php selected($condition['field'] ?? '', 'form_data.name'); ?>><?php esc_html_e('Name', 'formflow-pro'); ?></option>
                                        <option value="submission.status" <?php selected($condition['field'] ?? '', 'submission.status'); ?>><?php esc_html_e('Status', 'formflow-pro'); ?></option>
                                    </select>
                                    <select name="conditions[<?php echo $index; ?>][operator]" class="condition-operator">
                                        <option value="equals" <?php selected($condition['operator'] ?? '', 'equals'); ?>><?php esc_html_e('equals', 'formflow-pro'); ?></option>
                                        <option value="not_equals" <?php selected($condition['operator'] ?? '', 'not_equals'); ?>><?php esc_html_e('not equals', 'formflow-pro'); ?></option>
                                        <option value="contains" <?php selected($condition['operator'] ?? '', 'contains'); ?>><?php esc_html_e('contains', 'formflow-pro'); ?></option>
                                        <option value="not_contains" <?php selected($condition['operator'] ?? '', 'not_contains'); ?>><?php esc_html_e('not contains', 'formflow-pro'); ?></option>
                                    </select>
                                    <input type="text" name="conditions[<?php echo $index; ?>][value]" value="<?php echo esc_attr($condition['value'] ?? ''); ?>" placeholder="<?php esc_attr_e('Value', 'formflow-pro'); ?>" class="regular-text">
                                    <button type="button" class="button remove-condition" style="color: #dc3545;">
                                        <span class="dashicons dashicons-no" style="margin-top: 4px;"></span>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <button type="button" id="add-condition" class="button" style="margin-top: 10px;">
                        <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
                        <?php esc_html_e('Add Condition', 'formflow-pro'); ?>
                    </button>
                </div>

                <!-- Actions Section -->
                <div class="workflow-section" style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                    <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                        <span class="dashicons dashicons-admin-generic" style="color: #46b450;"></span>
                        <?php esc_html_e('Actions', 'formflow-pro'); ?>
                    </h3>
                    <p class="description"><?php esc_html_e('Define what should happen when this workflow is triggered.', 'formflow-pro'); ?></p>

                    <div id="actions-container" style="margin-top: 15px;">
                        <?php if (!empty($actions)) : ?>
                            <?php foreach ($actions as $index => $action) : ?>
                                <div class="action-row" style="display: flex; gap: 10px; margin-bottom: 10px; padding: 15px; background: #fff; border-radius: 4px; border-left: 3px solid #46b450;">
                                    <select name="actions[<?php echo $index; ?>][type]" class="action-type" style="min-width: 200px;">
                                        <option value="send_email" <?php selected($action['type'] ?? '', 'send_email'); ?>><?php esc_html_e('Send Email', 'formflow-pro'); ?></option>
                                        <option value="update_status" <?php selected($action['type'] ?? '', 'update_status'); ?>><?php esc_html_e('Update Status', 'formflow-pro'); ?></option>
                                        <option value="create_signature" <?php selected($action['type'] ?? '', 'create_signature'); ?>><?php esc_html_e('Create Signature Request', 'formflow-pro'); ?></option>
                                        <option value="webhook" <?php selected($action['type'] ?? '', 'webhook'); ?>><?php esc_html_e('Send Webhook', 'formflow-pro'); ?></option>
                                        <option value="create_task" <?php selected($action['type'] ?? '', 'create_task'); ?>><?php esc_html_e('Create Task', 'formflow-pro'); ?></option>
                                        <option value="delay" <?php selected($action['type'] ?? '', 'delay'); ?>><?php esc_html_e('Delay', 'formflow-pro'); ?></option>
                                    </select>
                                    <input type="text" name="actions[<?php echo $index; ?>][config]" value="<?php echo esc_attr(is_array($action['config'] ?? '') ? wp_json_encode($action['config']) : ($action['config'] ?? '')); ?>" placeholder="<?php esc_attr_e('Configuration (JSON)', 'formflow-pro'); ?>" class="regular-text">
                                    <button type="button" class="button remove-action" style="color: #dc3545;">
                                        <span class="dashicons dashicons-no" style="margin-top: 4px;"></span>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <button type="button" id="add-action" class="button" style="margin-top: 10px;">
                        <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
                        <?php esc_html_e('Add Action', 'formflow-pro'); ?>
                    </button>
                </div>

                <p class="submit" style="margin-top: 30px;">
                    <button type="submit" name="formflow_workflow_submit" class="button button-primary button-large">
                        <span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
                        <?php echo $workflow ? esc_html__('Update Workflow', 'formflow-pro') : esc_html__('Create Workflow', 'formflow-pro'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-automation')); ?>" class="button button-large">
                        <?php esc_html_e('Cancel', 'formflow-pro'); ?>
                    </a>
                </p>
            </form>
        </div>

    <?php elseif ($current_view === 'logs') : ?>
        <!-- Workflow Logs View -->
        <?php
        $workflow = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_workflows WHERE id = %d",
            $workflow_id
        ));
        ?>

        <div class="card" style="margin-top: 20px;">
            <h2 style="margin-top: 0;">
                <?php esc_html_e('Execution Logs', 'formflow-pro'); ?>
                <?php if ($workflow) : ?>
                    - <?php echo esc_html($workflow->name); ?>
                <?php endif; ?>
            </h2>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Submission', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Status', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Actions Run', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Duration', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Date', 'formflow-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $logs = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}formflow_workflow_logs WHERE workflow_id = %d ORDER BY created_at DESC LIMIT 100",
                        $workflow_id
                    ));

                    if (empty($logs)) :
                    ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px; color: #666;">
                                <?php esc_html_e('No execution logs found.', 'formflow-pro'); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($logs as $log) : ?>
                            <tr>
                                <td>#<?php echo esc_html($log->id); ?></td>
                                <td>
                                    <?php if ($log->submission_id) : ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-submissions&action=view&id=' . $log->submission_id)); ?>">
                                            #<?php echo esc_html($log->submission_id); ?>
                                        </a>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'completed' => '#46b450',
                                        'failed' => '#dc3545',
                                        'running' => '#0073aa',
                                    ];
                                    $color = $status_colors[$log->status] ?? '#666';
                                    ?>
                                    <span style="color: <?php echo esc_attr($color); ?>; font-weight: 500;">
                                        <?php echo esc_html(ucfirst($log->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log->actions_executed ?? 0); ?></td>
                                <td><?php echo esc_html($log->duration_ms ? $log->duration_ms . 'ms' : '-'); ?></td>
                                <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <p style="margin-top: 20px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-automation')); ?>" class="button">
                    <span class="dashicons dashicons-arrow-left-alt" style="margin-top: 4px;"></span>
                    <?php esc_html_e('Back to Workflows', 'formflow-pro'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    var conditionIndex = <?php echo !empty($conditions) ? count($conditions) : 0; ?>;
    var actionIndex = <?php echo !empty($actions) ? count($actions) : 0; ?>;

    // Add condition
    $('#add-condition').on('click', function() {
        var html = '<div class="condition-row" style="display: flex; gap: 10px; margin-bottom: 10px; padding: 10px; background: #fff; border-radius: 4px;">' +
            '<select name="conditions[' + conditionIndex + '][field]" class="condition-field">' +
                '<option value="form_data.email"><?php esc_html_e('Email', 'formflow-pro'); ?></option>' +
                '<option value="form_data.name"><?php esc_html_e('Name', 'formflow-pro'); ?></option>' +
                '<option value="submission.status"><?php esc_html_e('Status', 'formflow-pro'); ?></option>' +
            '</select>' +
            '<select name="conditions[' + conditionIndex + '][operator]" class="condition-operator">' +
                '<option value="equals"><?php esc_html_e('equals', 'formflow-pro'); ?></option>' +
                '<option value="not_equals"><?php esc_html_e('not equals', 'formflow-pro'); ?></option>' +
                '<option value="contains"><?php esc_html_e('contains', 'formflow-pro'); ?></option>' +
                '<option value="not_contains"><?php esc_html_e('not contains', 'formflow-pro'); ?></option>' +
            '</select>' +
            '<input type="text" name="conditions[' + conditionIndex + '][value]" placeholder="<?php esc_attr_e('Value', 'formflow-pro'); ?>" class="regular-text">' +
            '<button type="button" class="button remove-condition" style="color: #dc3545;">' +
                '<span class="dashicons dashicons-no" style="margin-top: 4px;"></span>' +
            '</button>' +
        '</div>';

        $('#conditions-container').append(html);
        conditionIndex++;
    });

    // Remove condition
    $(document).on('click', '.remove-condition', function() {
        $(this).closest('.condition-row').remove();
    });

    // Add action
    $('#add-action').on('click', function() {
        var html = '<div class="action-row" style="display: flex; gap: 10px; margin-bottom: 10px; padding: 15px; background: #fff; border-radius: 4px; border-left: 3px solid #46b450;">' +
            '<select name="actions[' + actionIndex + '][type]" class="action-type" style="min-width: 200px;">' +
                '<option value="send_email"><?php esc_html_e('Send Email', 'formflow-pro'); ?></option>' +
                '<option value="update_status"><?php esc_html_e('Update Status', 'formflow-pro'); ?></option>' +
                '<option value="create_signature"><?php esc_html_e('Create Signature Request', 'formflow-pro'); ?></option>' +
                '<option value="webhook"><?php esc_html_e('Send Webhook', 'formflow-pro'); ?></option>' +
                '<option value="create_task"><?php esc_html_e('Create Task', 'formflow-pro'); ?></option>' +
                '<option value="delay"><?php esc_html_e('Delay', 'formflow-pro'); ?></option>' +
            '</select>' +
            '<input type="text" name="actions[' + actionIndex + '][config]" placeholder="<?php esc_attr_e('Configuration (JSON)', 'formflow-pro'); ?>" class="regular-text">' +
            '<button type="button" class="button remove-action" style="color: #dc3545;">' +
                '<span class="dashicons dashicons-no" style="margin-top: 4px;"></span>' +
            '</button>' +
        '</div>';

        $('#actions-container').append(html);
        actionIndex++;
    });

    // Remove action
    $(document).on('click', '.remove-action', function() {
        $(this).closest('.action-row').remove();
    });

    // Toggle trigger form row based on trigger type
    $('#trigger_type').on('change', function() {
        var type = $(this).val();
        if (type === 'form_submission' || type === 'status_change') {
            $('#trigger-form-row').show();
        } else {
            $('#trigger-form-row').hide();
        }
    }).trigger('change');
});
</script>
