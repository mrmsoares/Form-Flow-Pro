<?php

declare(strict_types=1);

/**
 * Elementor AJAX Handler
 *
 * Handles AJAX requests from Elementor widgets.
 *
 * @package FormFlowPro\Integrations\Elementor
 * @since 2.0.0
 */

namespace FormFlowPro\Integrations\Elementor;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler Class
 */
class Ajax_Handler
{
    /**
     * Initialize AJAX handlers
     *
     * @return void
     */
    public static function init(): void
    {
        // Form submission
        add_action('wp_ajax_formflow_submit_form', [__CLASS__, 'handle_form_submission']);
        add_action('wp_ajax_nopriv_formflow_submit_form', [__CLASS__, 'handle_form_submission']);

        // Get form preview (editor only)
        add_action('wp_ajax_formflow_get_form_preview', [__CLASS__, 'get_form_preview']);
    }

    /**
     * Handle form submission
     *
     * @return void
     */
    public static function handle_form_submission(): void
    {
        // Verify nonce
        if (!isset($_POST['formflow_nonce']) || !wp_verify_nonce($_POST['formflow_nonce'], 'formflow_submit_form')) {
            wp_send_json_error([
                'message' => __('Security check failed.', 'formflow-pro'),
            ], 403);
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

        if (!$form_id) {
            wp_send_json_error([
                'message' => __('Form ID is required.', 'formflow-pro'),
            ], 400);
        }

        // Get form configuration
        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_forms WHERE id = %d AND status = 'active'",
            $form_id
        ));

        if (!$form) {
            wp_send_json_error([
                'message' => __('Form not found or inactive.', 'formflow-pro'),
            ], 404);
        }

        // Collect form data
        $form_data = [];
        $form_fields = json_decode($form->fields, true);

        foreach ($form_fields as $field) {
            $field_name = $field['name'] ?? '';
            if (empty($field_name)) {
                continue;
            }

            $field_value = isset($_POST[$field_name]) ? sanitize_text_field($_POST[$field_name]) : '';

            // Validate required fields
            if (isset($field['required']) && $field['required'] && empty($field_value)) {
                wp_send_json_error([
                    'message' => sprintf(
                        __('Field "%s" is required.', 'formflow-pro'),
                        $field['label'] ?? $field_name
                    ),
                ], 400);
            }

            // Validate email fields
            if (($field['type'] ?? '') === 'email' && !empty($field_value) && !is_email($field_value)) {
                wp_send_json_error([
                    'message' => sprintf(
                        __('Please enter a valid email address for "%s".', 'formflow-pro'),
                        $field['label'] ?? $field_name
                    ),
                ], 400);
            }

            $form_data[$field_name] = $field_value;
        }

        // Get user agent and IP
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $ip_address = self::get_client_ip();

        // Prepare metadata
        $metadata = [
            'source' => 'elementor_widget',
            'user_id' => get_current_user_id(),
            'referrer' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '',
        ];

        // Save to database
        $table = $wpdb->prefix . 'formflow_submissions';

        $data = [
            'form_id' => $form_id,
            'form_data' => wp_json_encode($form_data),
            'metadata' => wp_json_encode($metadata),
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $inserted = $wpdb->insert($table, $data, [
            '%d', // form_id
            '%s', // form_data
            '%s', // metadata
            '%s', // ip_address
            '%s', // user_agent
            '%s', // status
            '%s', // created_at
            '%s', // updated_at
        ]);

        if (!$inserted) {
            wp_send_json_error([
                'message' => __('Failed to save submission. Please try again.', 'formflow-pro'),
            ], 500);
        }

        $submission_id = $wpdb->insert_id;

        // Send notification email if configured
        self::send_notification($submission_id, $form, $form_data);

        // Check if digital signature is required
        $require_signature = isset($_POST['enable_autentique']) && $_POST['enable_autentique'] === 'yes';
        $signature_url = null;

        if ($require_signature) {
            // This would integrate with your Autentique service
            $signature_url = self::create_signature_document($submission_id, $form, $form_data);

            if ($signature_url) {
                // Update submission status
                $wpdb->update(
                    $table,
                    ['status' => 'pending_signature'],
                    ['id' => $submission_id],
                    ['%s'],
                    ['%d']
                );
            }
        } else {
            // Mark as completed if no signature required
            $wpdb->update(
                $table,
                ['status' => 'completed'],
                ['id' => $submission_id],
                ['%s'],
                ['%d']
            );
        }

        // Fire action hook
        do_action('formflow_form_submitted', $submission_id, $form_id, $form_data);

        // Send success response
        wp_send_json_success([
            'message' => __('Form submitted successfully!', 'formflow-pro'),
            'submission_id' => $submission_id,
            'signature_url' => $signature_url,
        ]);
    }

    /**
     * Get form preview (editor only)
     *
     * @return void
     */
    public static function get_form_preview(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_elementor')) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
        }

        // Check capability
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions.'], 403);
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;

        if (!$form_id) {
            wp_send_json_error(['message' => 'Form ID is required.'], 400);
        }

        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_forms WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            wp_send_json_error(['message' => 'Form not found.'], 404);
        }

        $form_fields = json_decode($form->fields, true);

        wp_send_json_success([
            'form' => [
                'id' => $form->id,
                'name' => $form->name,
                'description' => $form->description,
                'fields' => $form_fields,
            ],
        ]);
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address.
     */
    private static function get_client_ip(): string
    {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return sanitize_text_field($_SERVER[$key]);
            }
        }

        return '0.0.0.0';
    }

    /**
     * Send notification email
     *
     * @param int    $submission_id Submission ID.
     * @param object $form Form object.
     * @param array  $form_data Form data.
     * @return void
     */
    private static function send_notification(int $submission_id, object $form, array $form_data): void
    {
        $notification_email = get_option('formflow_company_email', get_option('admin_email'));

        if (empty($notification_email)) {
            return;
        }

        $subject = sprintf(
            __('New Form Submission: %s (#%d)', 'formflow-pro'),
            $form->name,
            $submission_id
        );

        $message = sprintf(
            __('A new submission has been received for form "%s".', 'formflow-pro'),
            $form->name
        ) . "\n\n";

        $message .= __('Submission Details:', 'formflow-pro') . "\n";
        $message .= str_repeat('-', 40) . "\n\n";

        foreach ($form_data as $key => $value) {
            $message .= sprintf("%s: %s\n", ucfirst(str_replace('_', ' ', $key)), $value);
        }

        $message .= "\n" . str_repeat('-', 40) . "\n\n";
        $message .= sprintf(
            __('View submission: %s', 'formflow-pro'),
            admin_url('admin.php?page=formflow-submissions&submission_id=' . $submission_id)
        );

        wp_mail($notification_email, $subject, $message);
    }

    /**
     * Create signature document
     *
     * @param int    $submission_id Submission ID.
     * @param object $form Form object.
     * @param array  $form_data Form data.
     * @return string|null Signature URL or null.
     */
    private static function create_signature_document(int $submission_id, object $form, array $form_data): ?string
    {
        // This would integrate with your Autentique service
        // For now, return null
        // TODO: Implement Autentique integration

        return apply_filters('formflow_signature_url', null, $submission_id, $form, $form_data);
    }
}
