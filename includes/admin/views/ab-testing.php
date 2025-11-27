<?php

/**
 * A/B Testing Page
 *
 * @package FormFlowPro
 * @since 2.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get forms for testing
$forms = $wpdb->get_results("SELECT id, name, status FROM {$wpdb->prefix}formflow_forms ORDER BY name ASC");

// Get active tests
$tests = get_option('formflow_ab_tests', []);

?>

<div class="wrap formflow-admin formflow-ab-testing">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-randomize"></span>
        <?php esc_html_e('A/B Testing', 'formflow-pro'); ?>
        <span class="badge badge-enterprise" style="background: linear-gradient(135deg, #27ae60, #2ecc71); color: #fff; font-size: 11px; padding: 3px 10px; border-radius: 3px; margin-left: 10px; vertical-align: middle;">
            <?php esc_html_e('Enterprise', 'formflow-pro'); ?>
        </span>
    </h1>

    <a href="#" class="page-title-action" id="create-ab-test">
        <?php esc_html_e('Create New Test', 'formflow-pro'); ?>
    </a>

    <hr class="wp-header-end">

    <!-- Info Card -->
    <div class="card" style="padding: 20px; margin-top: 20px; border-left: 4px solid #0073aa;">
        <h3 style="margin-top: 0;">
            <span class="dashicons dashicons-info" style="color: #0073aa;"></span>
            <?php esc_html_e('How A/B Testing Works', 'formflow-pro'); ?>
        </h3>
        <p style="color: #666;">
            <?php esc_html_e('A/B testing allows you to compare different form variations to determine which performs better. Create variants of your forms and automatically distribute traffic between them to find the optimal design.', 'formflow-pro'); ?>
        </p>
        <ul style="list-style: disc; margin-left: 20px; color: #666;">
            <li><?php esc_html_e('Create multiple variants of your form', 'formflow-pro'); ?></li>
            <li><?php esc_html_e('Set traffic distribution percentages', 'formflow-pro'); ?></li>
            <li><?php esc_html_e('Track conversion rates and form completion', 'formflow-pro'); ?></li>
            <li><?php esc_html_e('Automatically select winners based on statistical significance', 'formflow-pro'); ?></li>
        </ul>
    </div>

    <?php if (empty($tests)) : ?>
        <!-- Empty State -->
        <div class="card" style="text-align: center; padding: 60px 20px; margin-top: 20px;">
            <span class="dashicons dashicons-randomize" style="font-size: 64px; width: 64px; height: 64px; color: #ccc;"></span>
            <h2><?php esc_html_e('No A/B Tests Yet', 'formflow-pro'); ?></h2>
            <p style="color: #666;"><?php esc_html_e('Create your first A/B test to start optimizing your forms.', 'formflow-pro'); ?></p>
            <a href="#" class="button button-primary button-hero" id="create-first-test">
                <?php esc_html_e('Create Your First Test', 'formflow-pro'); ?>
            </a>
        </div>
    <?php else : ?>
        <!-- Tests Table -->
        <div class="card" style="margin-top: 20px; padding: 0;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Test Name', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Forms', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Status', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Views', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Conversions', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Winner', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests as $test_id => $test) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($test['name']); ?></strong></td>
                            <td><?php echo esc_html(count($test['variants'] ?? [])); ?> <?php esc_html_e('variants', 'formflow-pro'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($test['status']); ?>">
                                    <?php echo esc_html(ucfirst($test['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($test['total_views'] ?? 0); ?></td>
                            <td><?php echo esc_html($test['total_conversions'] ?? 0); ?></td>
                            <td>
                                <?php if (!empty($test['winner'])) : ?>
                                    <span style="color: #27ae60;">
                                        <span class="dashicons dashicons-awards"></span>
                                        <?php echo esc_html($test['winner']); ?>
                                    </span>
                                <?php else : ?>
                                    <span style="color: #666;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="#" class="button button-small view-test-results" data-test-id="<?php echo esc_attr($test_id); ?>">
                                    <?php esc_html_e('Results', 'formflow-pro'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Create Test Modal -->
<div id="create-test-modal" class="formflow-modal" style="display: none;">
    <div class="formflow-modal-overlay"></div>
    <div class="formflow-modal-content" style="max-width: 600px;">
        <div class="formflow-modal-header">
            <h2><?php esc_html_e('Create A/B Test', 'formflow-pro'); ?></h2>
            <button type="button" class="formflow-modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>

        <div class="formflow-modal-body">
            <form id="create-test-form">
                <div class="formflow-form-group" style="margin-bottom: 20px;">
                    <label for="test-name" style="font-weight: 600; display: block; margin-bottom: 5px;">
                        <?php esc_html_e('Test Name', 'formflow-pro'); ?> <span style="color: red;">*</span>
                    </label>
                    <input type="text" id="test-name" name="test_name" class="regular-text" style="width: 100%;" required>
                </div>

                <div class="formflow-form-group" style="margin-bottom: 20px;">
                    <label for="control-form" style="font-weight: 600; display: block; margin-bottom: 5px;">
                        <?php esc_html_e('Control Form (Original)', 'formflow-pro'); ?> <span style="color: red;">*</span>
                    </label>
                    <select id="control-form" name="control_form" class="regular-text" style="width: 100%;" required>
                        <option value=""><?php esc_html_e('Select form...', 'formflow-pro'); ?></option>
                        <?php foreach ($forms as $form) : ?>
                            <option value="<?php echo esc_attr($form->id); ?>"><?php echo esc_html($form->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="formflow-form-group" style="margin-bottom: 20px;">
                    <label for="variant-form" style="font-weight: 600; display: block; margin-bottom: 5px;">
                        <?php esc_html_e('Variant Form', 'formflow-pro'); ?> <span style="color: red;">*</span>
                    </label>
                    <select id="variant-form" name="variant_form" class="regular-text" style="width: 100%;" required>
                        <option value=""><?php esc_html_e('Select form...', 'formflow-pro'); ?></option>
                        <?php foreach ($forms as $form) : ?>
                            <option value="<?php echo esc_attr($form->id); ?>"><?php echo esc_html($form->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="formflow-form-group" style="margin-bottom: 20px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                        <?php esc_html_e('Traffic Split', 'formflow-pro'); ?>
                    </label>
                    <div style="display: flex; gap: 20px; align-items: center;">
                        <div>
                            <label>Control: <input type="number" name="control_traffic" value="50" min="1" max="99" style="width: 60px;">%</label>
                        </div>
                        <div>
                            <label>Variant: <input type="number" name="variant_traffic" value="50" min="1" max="99" style="width: 60px;">%</label>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="formflow-modal-footer">
            <button type="button" class="button" id="cancel-test">
                <?php esc_html_e('Cancel', 'formflow-pro'); ?>
            </button>
            <button type="button" class="button button-primary" id="save-test">
                <?php esc_html_e('Create Test', 'formflow-pro'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}
.status-active { background: #d4edda; color: #155724; }
.status-paused { background: #fff3cd; color: #856404; }
.status-completed { background: #cce5ff; color: #004085; }
.status-draft { background: #e2e3e5; color: #383d41; }
</style>
