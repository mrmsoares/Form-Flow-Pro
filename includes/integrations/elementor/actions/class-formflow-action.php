<?php

declare(strict_types=1);

/**
 * FormFlow Action for Elementor Pro Forms
 *
 * Custom action to save Elementor Pro form submissions to FormFlow.
 *
 * @package FormFlowPro\Integrations\Elementor\Actions
 * @since 2.0.0
 */

namespace FormFlowPro\Integrations\Elementor\Actions;

use ElementorPro\Modules\Forms\Classes\Action_Base;
use ElementorPro\Modules\Forms\Classes\Form_Record;
use ElementorPro\Modules\Forms\Classes\Ajax_Handler;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * FormFlow Action Class
 */
class FormFlow_Action extends Action_Base
{
    /**
     * Get action name
     *
     * @return string Action name.
     */
    public function get_name(): string
    {
        return 'formflow';
    }

    /**
     * Get action label
     *
     * @return string Action label.
     */
    public function get_label(): string
    {
        return __('FormFlow Pro', 'formflow-pro');
    }

    /**
     * Register action settings
     *
     * @param \Elementor\Widget_Base $widget Widget instance.
     * @return void
     */
    public function register_settings_section($widget): void
    {
        $widget->start_controls_section(
            'section_formflow',
            [
                'label' => __('FormFlow Pro', 'formflow-pro'),
                'condition' => [
                    'submit_actions' => $this->get_name(),
                ],
            ]
        );

        $widget->add_control(
            'formflow_target_form',
            [
                'label' => __('Target Form', 'formflow-pro'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $this->get_formflow_forms(),
                'description' => __('Select which FormFlow form to save this submission to', 'formflow-pro'),
            ]
        );

        $widget->add_control(
            'formflow_enable_signature',
            [
                'label' => __('Enable Digital Signature', 'formflow-pro'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'formflow-pro'),
                'label_off' => __('No', 'formflow-pro'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Send to Autentique for digital signature', 'formflow-pro'),
            ]
        );

        $widget->add_control(
            'formflow_notification',
            [
                'label' => __('Send Notification Email', 'formflow-pro'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'formflow-pro'),
                'label_off' => __('No', 'formflow-pro'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $widget->add_control(
            'formflow_notification_email',
            [
                'label' => __('Notification Email', 'formflow-pro'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => __('admin@example.com', 'formflow-pro'),
                'description' => __('Email address to receive notifications (leave empty to use form default)', 'formflow-pro'),
                'condition' => [
                    'formflow_notification' => 'yes',
                ],
            ]
        );

        $widget->add_control(
            'formflow_metadata',
            [
                'label' => __('Custom Metadata', 'formflow-pro'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'placeholder' => __('key1=value1
key2=value2', 'formflow-pro'),
                'description' => __('Add custom metadata (one per line, format: key=value)', 'formflow-pro'),
            ]
        );

        $widget->end_controls_section();
    }

    /**
     * Run action
     *
     * @param Form_Record  $record Form record.
     * @param Ajax_Handler $ajax_handler AJAX handler.
     * @return void
     */
    public function run($record, $ajax_handler): void
    {
        $settings = $record->get('form_settings');

        // Validate settings
        if (empty($settings['formflow_target_form'])) {
            $ajax_handler->add_error_message(__('FormFlow target form not selected.', 'formflow-pro'));
            return;
        }

        // Get submitted fields
        $raw_fields = $record->get('fields');
        $fields = [];

        foreach ($raw_fields as $id => $field) {
            $fields[$field['id']] = $field['value'];
        }

        // Prepare metadata
        $metadata = $this->parse_metadata($settings['formflow_metadata'] ?? '');
        $metadata['source'] = 'elementor_pro';
        $metadata['form_id'] = $record->get_form_settings('id');
        $metadata['post_id'] = $record->get_form_settings('post_id');

        // Get user agent and IP
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip_address = $this->get_client_ip();

        // Save to database
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_submissions';

        $data = [
            'form_id' => intval($settings['formflow_target_form']),
            'form_data' => wp_json_encode($fields),
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
            $ajax_handler->add_error_message(__('Failed to save submission.', 'formflow-pro'));
            return;
        }

        $submission_id = $wpdb->insert_id;

        // Send notification email if enabled
        if ($settings['formflow_notification'] === 'yes') {
            $this->send_notification($submission_id, $fields, $settings);
        }

        // Handle digital signature if enabled
        if ($settings['formflow_enable_signature'] === 'yes') {
            $this->process_signature($submission_id, $fields);
        }

        // Add success response data
        $ajax_handler->add_response_data('formflow_submission_id', $submission_id);

        // Log successful submission
        do_action('formflow_elementor_submission_saved', $submission_id, $fields, $settings);
    }

    /**
     * On export
     *
     * @param array $element Element data.
     * @return array Element data.
     */
    public function on_export($element): array
    {
        unset(
            $element['formflow_target_form'],
            $element['formflow_notification_email']
        );

        return $element;
    }

    /**
     * Get FormFlow forms
     *
     * @return array Available forms.
     */
    private function get_formflow_forms(): array
    {
        global $wpdb;

        $forms = $wpdb->get_results(
            "SELECT id, name FROM {$wpdb->prefix}formflow_forms WHERE status = 'active' ORDER BY name ASC"
        );

        $options = ['' => __('Select a form', 'formflow-pro')];

        if ($forms) {
            foreach ($forms as $form) {
                $options[$form->id] = $form->name;
            }
        }

        return $options;
    }

    /**
     * Parse metadata string
     *
     * @param string $metadata_string Metadata string.
     * @return array Parsed metadata.
     */
    private function parse_metadata(string $metadata_string): array
    {
        $metadata = [];

        if (empty($metadata_string)) {
            return $metadata;
        }

        $lines = explode("\n", $metadata_string);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $metadata[trim($key)] = trim($value);
        }

        return $metadata;
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address.
     */
    private function get_client_ip(): string
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
     * @param int   $submission_id Submission ID.
     * @param array $fields Form fields.
     * @param array $settings Form settings.
     * @return void
     */
    private function send_notification(int $submission_id, array $fields, array $settings): void
    {
        $to = $settings['formflow_notification_email'] ?? get_option('formflow_company_email', get_option('admin_email'));

        if (empty($to)) {
            return;
        }

        $subject = sprintf(
            __('New FormFlow Submission #%d', 'formflow-pro'),
            $submission_id
        );

        $message = sprintf(
            __('A new form submission has been received via Elementor Pro.', 'formflow-pro') . "\n\n"
        );

        $message .= __('Submission Details:', 'formflow-pro') . "\n";
        $message .= str_repeat('-', 40) . "\n\n";

        foreach ($fields as $key => $value) {
            $message .= sprintf("%s: %s\n", ucfirst(str_replace('_', ' ', $key)), $value);
        }

        $message .= "\n" . str_repeat('-', 40) . "\n\n";
        $message .= sprintf(
            __('View submission: %s', 'formflow-pro'),
            admin_url('admin.php?page=formflow-submissions&submission_id=' . $submission_id)
        );

        wp_mail($to, $subject, $message);
    }

    /**
     * Process digital signature
     *
     * @param int   $submission_id Submission ID.
     * @param array $fields Form fields.
     * @return void
     */
    private function process_signature(int $submission_id, array $fields): void
    {
        // This would integrate with your Autentique service
        do_action('formflow_process_signature', $submission_id, $fields);
    }
}
