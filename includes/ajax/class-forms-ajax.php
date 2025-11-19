<?php

declare(strict_types=1);

/**
 * Forms AJAX Handlers
 *
 * Handles all AJAX requests for Forms management.
 *
 * @package FormFlowPro\Ajax
 * @since 2.0.0
 */

namespace FormFlowPro\Ajax;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Forms AJAX Handler Class
 */
class Forms_Ajax
{
    /**
     * Register AJAX handlers
     *
     * @return void
     */
    public static function init(): void
    {
        // Form CRUD operations
        add_action('wp_ajax_formflow_save_form', [__CLASS__, 'save_form']);
        add_action('wp_ajax_formflow_get_form', [__CLASS__, 'get_form']);
        add_action('wp_ajax_formflow_delete_form', [__CLASS__, 'delete_form']);
        add_action('wp_ajax_formflow_duplicate_form', [__CLASS__, 'duplicate_form']);
        add_action('wp_ajax_formflow_update_form_status', [__CLASS__, 'update_form_status']);
    }

    /**
     * Save form (create or update)
     *
     * @return void
     */
    public static function save_form(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'formflow_forms';

        // Get form data
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $description = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
        $fields = isset($_POST['fields']) ? wp_json_encode($_POST['fields']) : '[]';
        $settings = isset($_POST['settings']) ? wp_json_encode($_POST['settings']) : '{}';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';

        // Validate required fields
        if (empty($name)) {
            wp_send_json_error([
                'message' => __('Form name is required.', 'formflow-pro'),
            ], 400);
        }

        // Prepare data
        $data = [
            'name' => $name,
            'description' => $description,
            'fields' => $fields,
            'settings' => $settings,
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ];

        $format = ['%s', '%s', '%s', '%s', '%s', '%s'];

        if ($form_id > 0) {
            // Update existing form
            $result = $wpdb->update(
                $table,
                $data,
                ['id' => $form_id],
                $format,
                ['%d']
            );

            if ($result === false) {
                wp_send_json_error([
                    'message' => __('Failed to update form.', 'formflow-pro'),
                ], 500);
            }

            wp_send_json_success([
                'message' => __('Form updated successfully.', 'formflow-pro'),
                'form_id' => $form_id,
            ]);
        } else {
            // Create new form
            $data['created_at'] = current_time('mysql');
            $format[] = '%s';

            $result = $wpdb->insert($table, $data, $format);

            if (!$result) {
                wp_send_json_error([
                    'message' => __('Failed to create form.', 'formflow-pro'),
                ], 500);
            }

            $form_id = $wpdb->insert_id;

            wp_send_json_success([
                'message' => __('Form created successfully.', 'formflow-pro'),
                'form_id' => $form_id,
            ]);
        }
    }

    /**
     * Get form data
     *
     * @return void
     */
    public static function get_form(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

        if (!$form_id) {
            wp_send_json_error(['message' => __('Form ID is required.', 'formflow-pro')], 400);
        }

        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_forms WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            wp_send_json_error(['message' => __('Form not found.', 'formflow-pro')], 404);
        }

        // Parse JSON fields
        $form->fields = json_decode($form->fields, true);
        $form->settings = json_decode($form->settings, true);

        wp_send_json_success([
            'form' => $form,
        ]);
    }

    /**
     * Delete form
     *
     * @return void
     */
    public static function delete_form(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

        if (!$form_id) {
            wp_send_json_error(['message' => __('Form ID is required.', 'formflow-pro')], 400);
        }

        global $wpdb;

        // Check if form exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_forms WHERE id = %d",
            $form_id
        ));

        if (!$exists) {
            wp_send_json_error(['message' => __('Form not found.', 'formflow-pro')], 404);
        }

        // Delete form
        $result = $wpdb->delete(
            $wpdb->prefix . 'formflow_forms',
            ['id' => $form_id],
            ['%d']
        );

        if (!$result) {
            wp_send_json_error([
                'message' => __('Failed to delete form.', 'formflow-pro'),
            ], 500);
        }

        // Also delete associated submissions (optional - could be configurable)
        $wpdb->delete(
            $wpdb->prefix . 'formflow_submissions',
            ['form_id' => $form_id],
            ['%d']
        );

        wp_send_json_success([
            'message' => __('Form deleted successfully.', 'formflow-pro'),
        ]);
    }

    /**
     * Duplicate form
     *
     * @return void
     */
    public static function duplicate_form(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

        if (!$form_id) {
            wp_send_json_error(['message' => __('Form ID is required.', 'formflow-pro')], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'formflow_forms';

        // Get original form
        $original = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $form_id
        ), ARRAY_A);

        if (!$original) {
            wp_send_json_error(['message' => __('Form not found.', 'formflow-pro')], 404);
        }

        // Prepare duplicated data
        unset($original['id']);
        $original['name'] = $original['name'] . ' (Copy)';
        $original['status'] = 'draft';
        $original['created_at'] = current_time('mysql');
        $original['updated_at'] = current_time('mysql');

        // Insert duplicate
        $result = $wpdb->insert($table, $original, [
            '%s', // name
            '%s', // description
            '%s', // fields
            '%s', // settings
            '%s', // status
            '%s', // created_at
            '%s', // updated_at
        ]);

        if (!$result) {
            wp_send_json_error([
                'message' => __('Failed to duplicate form.', 'formflow-pro'),
            ], 500);
        }

        $new_form_id = $wpdb->insert_id;

        wp_send_json_success([
            'message' => __('Form duplicated successfully.', 'formflow-pro'),
            'form_id' => $new_form_id,
        ]);
    }

    /**
     * Update form status
     *
     * @return void
     */
    public static function update_form_status(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$form_id || !$status) {
            wp_send_json_error([
                'message' => __('Form ID and status are required.', 'formflow-pro'),
            ], 400);
        }

        // Validate status
        $valid_statuses = ['active', 'draft', 'archived'];
        if (!in_array($status, $valid_statuses, true)) {
            wp_send_json_error([
                'message' => __('Invalid status.', 'formflow-pro'),
            ], 400);
        }

        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'formflow_forms',
            [
                'status' => $status,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $form_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            wp_send_json_error([
                'message' => __('Failed to update form status.', 'formflow-pro'),
            ], 500);
        }

        wp_send_json_success([
            'message' => __('Form status updated successfully.', 'formflow-pro'),
        ]);
    }
}
