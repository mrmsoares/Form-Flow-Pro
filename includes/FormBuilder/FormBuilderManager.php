<?php
/**
 * FormFlow Pro - Form Builder Manager
 *
 * Central manager for the Form Builder module coordinating all components:
 * Field Types, Versioning, A/B Testing, and the Drag & Drop Builder.
 *
 * @package FormFlowPro
 * @subpackage FormBuilder
 * @since 2.4.0
 */

namespace FormFlowPro\FormBuilder;

use FormFlowPro\Traits\SingletonTrait;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Builder Manager
 */
class FormBuilderManager
{
    use SingletonTrait;

    private FieldTypesRegistry $field_registry;
    private DragDropBuilder $builder;
    private FormVersioning $versioning;
    private ABTesting $ab_testing;

    protected function init(): void
    {
        // Initialize all components
        $this->field_registry = FieldTypesRegistry::getInstance();
        $this->builder = DragDropBuilder::getInstance();
        $this->versioning = FormVersioning::getInstance();
        $this->ab_testing = ABTesting::getInstance();

        $this->registerHooks();
        $this->registerRestRoutes();
    }

    private function registerHooks(): void
    {
        // Admin menu
        add_action('admin_menu', [$this, 'addAdminMenuItems'], 20);

        // Admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        // AJAX handlers
        add_action('wp_ajax_ffp_builder_get_field_schema', [$this, 'ajaxGetFieldSchema']);
        add_action('wp_ajax_ffp_builder_validate_field', [$this, 'ajaxValidateField']);
        add_action('wp_ajax_ffp_builder_import_form', [$this, 'ajaxImportForm']);
        add_action('wp_ajax_ffp_builder_export_form', [$this, 'ajaxExportForm']);

        // Form submission
        add_action('wp_ajax_ffp_submit_form', [$this, 'handleFormSubmission']);
        add_action('wp_ajax_nopriv_ffp_submit_form', [$this, 'handleFormSubmission']);

        // Cron for A/B testing
        add_action('ffp_daily_cron', [$this->ab_testing, 'checkTestsForWinners']);

        // Register cron if not scheduled
        if (!wp_next_scheduled('ffp_daily_cron')) {
            wp_schedule_event(time(), 'daily', 'ffp_daily_cron');
        }
    }

    private function registerRestRoutes(): void
    {
        add_action('rest_api_init', function() {
            // Field types endpoints
            register_rest_route('form-flow-pro/v1', '/field-types', [
                'methods' => 'GET',
                'callback' => [$this, 'restGetFieldTypes'],
                'permission_callback' => '__return_true',
            ]);

            // Form templates
            register_rest_route('form-flow-pro/v1', '/templates', [
                'methods' => 'GET',
                'callback' => [$this, 'restGetTemplates'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ]);

            // Import/Export
            register_rest_route('form-flow-pro/v1', '/forms/(?P<id>\d+)/export', [
                'methods' => 'GET',
                'callback' => [$this, 'restExportForm'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ]);

            register_rest_route('form-flow-pro/v1', '/forms/import', [
                'methods' => 'POST',
                'callback' => [$this, 'restImportForm'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ]);

            // Analytics
            register_rest_route('form-flow-pro/v1', '/forms/(?P<id>\d+)/analytics', [
                'methods' => 'GET',
                'callback' => [$this, 'restGetFormAnalytics'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ]);
        });
    }

    /**
     * Add admin menu items
     */
    public function addAdminMenuItems(): void
    {
        add_submenu_page(
            'formflow-pro',
            __('A/B Testing', 'form-flow-pro'),
            __('A/B Testing', 'form-flow-pro'),
            'edit_posts',
            'formflow-pro-ab-testing',
            [$this, 'renderABTestingPage']
        );

        add_submenu_page(
            'formflow-pro',
            __('Templates', 'form-flow-pro'),
            __('Templates', 'form-flow-pro'),
            'edit_posts',
            'formflow-pro-templates',
            [$this, 'renderTemplatesPage']
        );

        add_submenu_page(
            'formflow-pro',
            __('Analytics', 'form-flow-pro'),
            __('Analytics', 'form-flow-pro'),
            'edit_posts',
            'formflow-pro-analytics',
            [$this, 'renderAnalyticsPage']
        );

        add_submenu_page(
            'formflow-pro',
            __('Import/Export', 'form-flow-pro'),
            __('Import/Export', 'form-flow-pro'),
            'manage_options',
            'formflow-pro-import-export',
            [$this, 'renderImportExportPage']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets($hook): void
    {
        if (strpos($hook, 'formflow-pro') === false) {
            return;
        }

        // Main builder styles
        wp_enqueue_style(
            'ffp-builder-main',
            plugins_url('assets/css/builder-main.css', dirname(__DIR__)),
            [],
            JEFORM_VERSION
        );

        // Analytics charts
        if (strpos($hook, 'analytics') !== false || strpos($hook, 'ab-testing') !== false) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                [],
                '4.4.0',
                true
            );
        }
    }

    /**
     * Get field registry
     */
    public function getFieldRegistry(): FieldTypesRegistry
    {
        return $this->field_registry;
    }

    /**
     * Get builder
     */
    public function getBuilder(): DragDropBuilder
    {
        return $this->builder;
    }

    /**
     * Get versioning
     */
    public function getVersioning(): FormVersioning
    {
        return $this->versioning;
    }

    /**
     * Get A/B testing
     */
    public function getABTesting(): ABTesting
    {
        return $this->ab_testing;
    }

    /**
     * Handle form submission
     */
    public function handleFormSubmission(): void
    {
        $form_id = (int) ($_POST['ffp_form_id'] ?? 0);

        if (!$form_id) {
            wp_send_json_error(['message' => __('Invalid form', 'form-flow-pro')]);
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['ffp_nonce'] ?? '', 'ffp_submit_form_' . $form_id)) {
            wp_send_json_error(['message' => __('Security check failed', 'form-flow-pro')]);
        }

        // Get form
        $form = $this->builder->getForm($form_id);

        if (!$form) {
            wp_send_json_error(['message' => __('Form not found', 'form-flow-pro')]);
        }

        // Check honeypot
        if (!empty($form->settings['honeypot'])) {
            $honeypot_field = 'ffp_hp_' . $form_id;
            if (!empty($_POST[$honeypot_field])) {
                // Log potential spam
                do_action('ffp_spam_detected', $form_id, $_POST);
                wp_send_json_success([
                    'message' => $form->settings['success_message'] ?? __('Thank you!', 'form-flow-pro'),
                ]); // Fake success for bots
            }
        }

        // Collect and validate data
        $data = [];
        $errors = [];

        foreach ($form->fields as $field) {
            $field_name = $field['name'] ?? $field['id'];
            $field_type = $field['type'] ?? 'text';
            $value = $_POST[$field_name] ?? null;

            // Handle file uploads
            if ($field_type === 'file' || $field_type === 'image') {
                $value = $this->handleFileUpload($field_name, $field);
                if (is_wp_error($value)) {
                    $errors[$field_name] = $value->get_error_message();
                    continue;
                }
            }

            // Sanitize
            $value = $this->field_registry->sanitize($field_type, $value, $field);

            // Validate
            $field_errors = $this->field_registry->validate($field_type, $value, $field);

            if (!empty($field_errors)) {
                $errors[$field_name] = $field_errors;
            }

            $data[$field_name] = $value;
        }

        // Apply conditional logic for validation
        foreach ($form->logic as $rule_data) {
            $rule = new ConditionalRule($rule_data);

            if ($rule->action === 'require' && $rule->evaluate($data)) {
                $target = $rule->target_field;
                if (empty($data[$target])) {
                    $errors[$target] = [__('This field is required.', 'form-flow-pro')];
                }
            }
        }

        // Check for errors
        if (!empty($errors)) {
            wp_send_json_error([
                'message' => $form->settings['error_message'] ?? __('Please fix the errors below.', 'form-flow-pro'),
                'errors' => $errors,
            ]);
        }

        // Save submission
        $submission_id = $this->saveSubmission($form_id, $data);

        if (!$submission_id) {
            wp_send_json_error(['message' => __('Failed to save submission', 'form-flow-pro')]);
        }

        // Update form submission count
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ffp_forms SET submissions = submissions + 1 WHERE id = %d",
            $form_id
        ));

        // Trigger actions
        do_action('ffp_form_submission', $form_id, [
            'submission_id' => $submission_id,
            'data' => $data,
            'form' => $form,
        ]);

        // Send notifications
        $this->processNotifications($form, $data, $submission_id);

        // Prepare response
        $response = [
            'message' => $form->settings['success_message'] ?? __('Thank you for your submission!', 'form-flow-pro'),
            'submission_id' => $submission_id,
        ];

        // Redirect if enabled
        if (!empty($form->settings['redirect_enabled']) && !empty($form->settings['redirect_url'])) {
            $response['redirect'] = $form->settings['redirect_url'];
        }

        wp_send_json_success($response);
    }

    /**
     * Handle file upload
     */
    private function handleFileUpload(string $field_name, array $field)
    {
        if (empty($_FILES[$field_name]) || $_FILES[$field_name]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $file = $_FILES[$field_name];

        // Check file type
        $allowed_types = $field['allowed_types'] ?? ['jpg', 'jpeg', 'png', 'pdf'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_types)) {
            return new \WP_Error('invalid_type', __('File type not allowed.', 'form-flow-pro'));
        }

        // Check file size
        $max_size = $field['max_size'] ?? 5 * 1024 * 1024;

        if ($file['size'] > $max_size) {
            return new \WP_Error('file_too_large', __('File is too large.', 'form-flow-pro'));
        }

        // Upload file
        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            return new \WP_Error('upload_error', $upload['error']);
        }

        return $upload['url'];
    }

    /**
     * Save submission to database
     */
    private function saveSubmission(int $form_id, array $data): ?int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_submissions';

        // Ensure table exists
        $this->createSubmissionsTable();

        $inserted = $wpdb->insert($table, [
            'form_id' => $form_id,
            'data' => json_encode($data),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->getClientIP(),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'referrer' => esc_url_raw($_SERVER['HTTP_REFERER'] ?? ''),
            'created_at' => current_time('mysql'),
        ]);

        return $inserted ? $wpdb->insert_id : null;
    }

    /**
     * Create submissions table
     */
    private function createSubmissionsTable(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_submissions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT UNSIGNED NOT NULL,
            data LONGTEXT NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT 0,
            ip_address VARCHAR(45) DEFAULT '',
            user_agent TEXT,
            referrer VARCHAR(500) DEFAULT '',
            status ENUM('new', 'read', 'starred', 'trash') DEFAULT 'new',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get client IP
     */
    private function getClientIP(): string
    {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field(trim($ip));
    }

    /**
     * Process form notifications
     */
    private function processNotifications($form, array $data, int $submission_id): void
    {
        foreach ($form->notifications as $notification) {
            if (!$this->shouldSendNotification($notification, $data)) {
                continue;
            }

            $type = $notification['type'] ?? 'email';

            switch ($type) {
                case 'email':
                    $this->sendEmailNotification($notification, $form, $data);
                    break;

                case 'sms':
                    $this->sendSMSNotification($notification, $form, $data);
                    break;

                case 'webhook':
                    $this->sendWebhookNotification($notification, $form, $data, $submission_id);
                    break;

                case 'slack':
                case 'teams':
                case 'discord':
                    $this->sendChatNotification($type, $notification, $form, $data);
                    break;
            }
        }
    }

    /**
     * Check if notification should be sent
     */
    private function shouldSendNotification(array $notification, array $data): bool
    {
        if (empty($notification['conditions'])) {
            return true;
        }

        $rule = new ConditionalRule([
            'conditions' => $notification['conditions'],
            'logic' => $notification['condition_logic'] ?? 'all',
        ]);

        return $rule->evaluate($data);
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(array $notification, $form, array $data): void
    {
        $to = $this->parseSmartTags($notification['to'] ?? '', $data);
        $subject = $this->parseSmartTags($notification['subject'] ?? '', $data);
        $message = $this->parseSmartTags($notification['message'] ?? '', $data);

        // Build HTML email
        $html = $this->buildEmailHTML($subject, $message, $form, $data);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        if (!empty($notification['from_email'])) {
            $from_name = $notification['from_name'] ?? get_bloginfo('name');
            $from_email = $notification['from_email'];
            $headers[] = "From: {$from_name} <{$from_email}>";
        }

        if (!empty($notification['reply_to'])) {
            $reply_to = $this->parseSmartTags($notification['reply_to'], $data);
            $headers[] = "Reply-To: {$reply_to}";
        }

        if (!empty($notification['cc'])) {
            $headers[] = "Cc: {$notification['cc']}";
        }

        if (!empty($notification['bcc'])) {
            $headers[] = "Bcc: {$notification['bcc']}";
        }

        wp_mail($to, $subject, $html, $headers);
    }

    /**
     * Build email HTML
     */
    private function buildEmailHTML(string $subject, string $message, $form, array $data): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>';
        $html .= '<div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;">';

        $html .= '<h2>' . esc_html($subject) . '</h2>';
        $html .= '<div>' . nl2br(esc_html($message)) . '</div>';

        // Add form data table
        $html .= '<h3>' . __('Submission Details', 'form-flow-pro') . '</h3>';
        $html .= '<table style="width:100%;border-collapse:collapse;">';

        foreach ($form->fields as $field) {
            $field_name = $field['name'] ?? $field['id'];
            $field_label = $field['label'] ?? $field_name;
            $value = $data[$field_name] ?? '';

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $html .= '<tr>';
            $html .= '<td style="padding:8px;border:1px solid #ddd;font-weight:bold;">' . esc_html($field_label) . '</td>';
            $html .= '<td style="padding:8px;border:1px solid #ddd;">' . esc_html($value) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Send SMS notification
     */
    private function sendSMSNotification(array $notification, $form, array $data): void
    {
        if (!class_exists('FormFlowPro\\Notifications\\SMSManager')) {
            return;
        }

        $sms_manager = \FormFlowPro\Notifications\SMSManager::getInstance();
        $to = $this->parseSmartTags($notification['to'] ?? '', $data);
        $message = $this->parseSmartTags($notification['message'] ?? '', $data);

        $sms_manager->send($to, $message);
    }

    /**
     * Send webhook notification
     */
    private function sendWebhookNotification(array $notification, $form, array $data, int $submission_id): void
    {
        $url = $notification['url'] ?? '';

        if (empty($url)) {
            return;
        }

        $payload = [
            'form_id' => $form->id,
            'form_title' => $form->title,
            'submission_id' => $submission_id,
            'data' => $data,
            'timestamp' => current_time('c'),
        ];

        $args = [
            'body' => json_encode($payload),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ];

        // Add custom headers
        if (!empty($notification['headers'])) {
            foreach ($notification['headers'] as $key => $value) {
                $args['headers'][$key] = $value;
            }
        }

        // Add authentication
        if (!empty($notification['auth_type'])) {
            switch ($notification['auth_type']) {
                case 'basic':
                    $args['headers']['Authorization'] = 'Basic ' . base64_encode(
                        $notification['auth_user'] . ':' . $notification['auth_pass']
                    );
                    break;

                case 'bearer':
                    $args['headers']['Authorization'] = 'Bearer ' . $notification['auth_token'];
                    break;
            }
        }

        wp_remote_post($url, $args);
    }

    /**
     * Send chat notification
     */
    private function sendChatNotification(string $type, array $notification, $form, array $data): void
    {
        if (!class_exists('FormFlowPro\\Notifications\\ChatManager')) {
            return;
        }

        $chat_manager = \FormFlowPro\Notifications\ChatManager::getInstance();
        $message = $this->parseSmartTags($notification['message'] ?? '', $data);

        // Build submission summary for chat
        $summary = sprintf(
            __("New submission from %s\n", 'form-flow-pro'),
            $form->title
        );

        foreach ($form->fields as $field) {
            $field_name = $field['name'] ?? $field['id'];
            $field_label = $field['label'] ?? $field_name;
            $value = $data[$field_name] ?? '';

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            if ($value) {
                $summary .= "{$field_label}: {$value}\n";
            }
        }

        $chat_manager->send($type, $notification['webhook_url'] ?? '', [
            'text' => $summary,
        ]);
    }

    /**
     * Parse smart tags in string
     */
    private function parseSmartTags(string $content, array $data): string
    {
        // Field tags: {field:field_name}
        $content = preg_replace_callback('/\{field:([^}]+)\}/', function($matches) use ($data) {
            $field_name = $matches[1];
            $value = $data[$field_name] ?? '';
            return is_array($value) ? implode(', ', $value) : $value;
        }, $content);

        // System tags
        $tags = [
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => home_url(),
            '{admin_email}' => get_option('admin_email'),
            '{date}' => current_time('Y-m-d'),
            '{time}' => current_time('H:i:s'),
            '{datetime}' => current_time('Y-m-d H:i:s'),
            '{user_ip}' => $this->getClientIP(),
            '{user_agent}' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            '{referrer}' => esc_url($_SERVER['HTTP_REFERER'] ?? ''),
        ];

        // User tags
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $tags['{user_id}'] = $user->ID;
            $tags['{user_email}'] = $user->user_email;
            $tags['{user_name}'] = $user->display_name;
            $tags['{user_first_name}'] = $user->first_name;
            $tags['{user_last_name}'] = $user->last_name;
        }

        return str_replace(array_keys($tags), array_values($tags), $content);
    }

    /**
     * Get form templates
     */
    public function getTemplates(): array
    {
        return apply_filters('ffp_form_templates', [
            'contact' => [
                'name' => __('Contact Form', 'form-flow-pro'),
                'description' => __('Simple contact form with name, email, and message', 'form-flow-pro'),
                'category' => 'general',
                'fields' => [
                    ['type' => 'name', 'label' => __('Name', 'form-flow-pro'), 'required' => true],
                    ['type' => 'email', 'label' => __('Email', 'form-flow-pro'), 'required' => true],
                    ['type' => 'phone', 'label' => __('Phone', 'form-flow-pro')],
                    ['type' => 'textarea', 'label' => __('Message', 'form-flow-pro'), 'required' => true],
                ],
            ],
            'newsletter' => [
                'name' => __('Newsletter Signup', 'form-flow-pro'),
                'description' => __('Email subscription form', 'form-flow-pro'),
                'category' => 'marketing',
                'fields' => [
                    ['type' => 'email', 'label' => __('Email Address', 'form-flow-pro'), 'required' => true],
                    ['type' => 'checkbox', 'label' => __('Interests', 'form-flow-pro'), 'options' => []],
                ],
            ],
            'feedback' => [
                'name' => __('Feedback Form', 'form-flow-pro'),
                'description' => __('Customer feedback and rating form', 'form-flow-pro'),
                'category' => 'feedback',
                'fields' => [
                    ['type' => 'rating', 'label' => __('Overall Rating', 'form-flow-pro'), 'required' => true],
                    ['type' => 'textarea', 'label' => __('Comments', 'form-flow-pro')],
                    ['type' => 'email', 'label' => __('Email (optional)', 'form-flow-pro')],
                ],
            ],
            'registration' => [
                'name' => __('Event Registration', 'form-flow-pro'),
                'description' => __('Event or webinar registration form', 'form-flow-pro'),
                'category' => 'events',
                'fields' => [
                    ['type' => 'name', 'label' => __('Full Name', 'form-flow-pro'), 'required' => true, 'format' => 'first_last'],
                    ['type' => 'email', 'label' => __('Email', 'form-flow-pro'), 'required' => true],
                    ['type' => 'phone', 'label' => __('Phone', 'form-flow-pro')],
                    ['type' => 'select', 'label' => __('Session', 'form-flow-pro'), 'options' => []],
                ],
            ],
            'job_application' => [
                'name' => __('Job Application', 'form-flow-pro'),
                'description' => __('Job application with resume upload', 'form-flow-pro'),
                'category' => 'hr',
                'fields' => [
                    ['type' => 'name', 'label' => __('Full Name', 'form-flow-pro'), 'required' => true, 'format' => 'full'],
                    ['type' => 'email', 'label' => __('Email', 'form-flow-pro'), 'required' => true],
                    ['type' => 'phone', 'label' => __('Phone', 'form-flow-pro'), 'required' => true],
                    ['type' => 'select', 'label' => __('Position', 'form-flow-pro'), 'required' => true, 'options' => []],
                    ['type' => 'file', 'label' => __('Resume', 'form-flow-pro'), 'required' => true, 'allowed_types' => ['pdf', 'doc', 'docx']],
                    ['type' => 'textarea', 'label' => __('Cover Letter', 'form-flow-pro')],
                ],
            ],
            'order' => [
                'name' => __('Order Form', 'form-flow-pro'),
                'description' => __('Product order form with payment', 'form-flow-pro'),
                'category' => 'ecommerce',
                'fields' => [
                    ['type' => 'name', 'label' => __('Name', 'form-flow-pro'), 'required' => true],
                    ['type' => 'email', 'label' => __('Email', 'form-flow-pro'), 'required' => true],
                    ['type' => 'address', 'label' => __('Shipping Address', 'form-flow-pro'), 'required' => true],
                    ['type' => 'select', 'label' => __('Product', 'form-flow-pro'), 'required' => true, 'options' => []],
                    ['type' => 'number', 'label' => __('Quantity', 'form-flow-pro'), 'required' => true, 'min' => 1],
                ],
            ],
            'survey' => [
                'name' => __('Customer Survey', 'form-flow-pro'),
                'description' => __('Multi-step customer satisfaction survey', 'form-flow-pro'),
                'category' => 'feedback',
                'multi_step' => true,
                'steps' => [
                    ['title' => __('About You', 'form-flow-pro')],
                    ['title' => __('Experience', 'form-flow-pro')],
                    ['title' => __('Feedback', 'form-flow-pro')],
                ],
                'fields' => [
                    ['type' => 'text', 'label' => __('Name', 'form-flow-pro'), 'step' => 0],
                    ['type' => 'email', 'label' => __('Email', 'form-flow-pro'), 'step' => 0],
                    ['type' => 'rating', 'label' => __('Product Quality', 'form-flow-pro'), 'step' => 1],
                    ['type' => 'rating', 'label' => __('Customer Service', 'form-flow-pro'), 'step' => 1],
                    ['type' => 'textarea', 'label' => __('Suggestions', 'form-flow-pro'), 'step' => 2],
                ],
            ],
        ]);
    }

    /**
     * Export form to JSON
     */
    public function exportForm(int $form_id, bool $include_versions = false): ?array
    {
        $form = $this->builder->getForm($form_id);

        if (!$form) {
            return null;
        }

        $export = [
            'version' => '2.4.0',
            'exported_at' => current_time('c'),
            'form' => $form->toArray(),
        ];

        if ($include_versions) {
            $export['versions'] = array_map(
                function($v) { return $v->toArray(); },
                $this->versioning->getVersionHistory($form_id, ['limit' => 100])
            );
        }

        return $export;
    }

    /**
     * Import form from JSON
     */
    public function importForm(array $import_data): ?int
    {
        if (empty($import_data['form'])) {
            return null;
        }

        $form_data = $import_data['form'];

        // Remove ID to create new form
        unset($form_data['id']);

        // Mark as imported
        $form_data['title'] = sprintf(__('%s (Imported)', 'form-flow-pro'), $form_data['title'] ?? 'Form');
        $form_data['status'] = 'draft';

        // Generate new field IDs
        foreach ($form_data['fields'] as &$field) {
            $field['id'] = uniqid('field_');
        }

        $form = $this->builder->createForm($form_data);

        return $form ? $form->id : null;
    }

    /**
     * Get form analytics
     */
    public function getFormAnalytics(int $form_id, string $period = '30days'): array
    {
        global $wpdb;

        $submissions_table = $wpdb->prefix . 'ffp_submissions';

        // Date range
        $end_date = current_time('Y-m-d');
        $start_date = match ($period) {
            '7days' => date('Y-m-d', strtotime('-7 days')),
            '30days' => date('Y-m-d', strtotime('-30 days')),
            '90days' => date('Y-m-d', strtotime('-90 days')),
            'year' => date('Y-m-d', strtotime('-1 year')),
            default => date('Y-m-d', strtotime('-30 days')),
        };

        // Total submissions
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$submissions_table} WHERE form_id = %d",
            $form_id
        ));

        // Submissions in period
        $period_submissions = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$submissions_table}
             WHERE form_id = %d AND DATE(created_at) BETWEEN %s AND %s",
            $form_id,
            $start_date,
            $end_date
        ));

        // Daily breakdown
        $daily = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM {$submissions_table}
             WHERE form_id = %d AND DATE(created_at) BETWEEN %s AND %s
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $form_id,
            $start_date,
            $end_date
        ), ARRAY_A);

        // Form views (if tracking)
        $form = $this->builder->getForm($form_id);
        $views = $form ? ($form->toArray()['views'] ?? 0) : 0;

        // Conversion rate
        $conversion_rate = $views > 0 ? round(($total / $views) * 100, 2) : 0;

        return [
            'total_submissions' => $total,
            'period_submissions' => $period_submissions,
            'total_views' => $views,
            'conversion_rate' => $conversion_rate,
            'daily_breakdown' => $daily,
            'period' => $period,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ];
    }

    /**
     * REST: Get field types
     */
    public function restGetFieldTypes(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'types' => $this->field_registry->getSchemas(),
            'categories' => $this->field_registry->getCategories(),
        ]);
    }

    /**
     * REST: Get templates
     */
    public function restGetTemplates(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->getTemplates());
    }

    /**
     * REST: Export form
     */
    public function restExportForm(\WP_REST_Request $request): \WP_REST_Response
    {
        $form_id = (int) $request->get_param('id');
        $include_versions = $request->get_param('include_versions') === 'true';

        $export = $this->exportForm($form_id, $include_versions);

        if (!$export) {
            return new \WP_REST_Response(['error' => 'Form not found'], 404);
        }

        return new \WP_REST_Response($export);
    }

    /**
     * REST: Import form
     */
    public function restImportForm(\WP_REST_Request $request): \WP_REST_Response
    {
        $import_data = $request->get_json_params();

        $form_id = $this->importForm($import_data);

        if (!$form_id) {
            return new \WP_REST_Response(['error' => 'Failed to import form'], 400);
        }

        return new \WP_REST_Response([
            'form_id' => $form_id,
            'message' => __('Form imported successfully', 'form-flow-pro'),
        ], 201);
    }

    /**
     * REST: Get form analytics
     */
    public function restGetFormAnalytics(\WP_REST_Request $request): \WP_REST_Response
    {
        $form_id = (int) $request->get_param('id');
        $period = $request->get_param('period') ?? '30days';

        return new \WP_REST_Response($this->getFormAnalytics($form_id, $period));
    }

    /**
     * AJAX: Get field schema
     */
    public function ajaxGetFieldSchema(): void
    {
        check_ajax_referer('ffp_nonce', 'nonce');

        $type = sanitize_text_field($_POST['type'] ?? '');
        $field_type = $this->field_registry->get($type);

        if (!$field_type) {
            wp_send_json_error(['message' => 'Unknown field type']);
        }

        wp_send_json_success($field_type->getSchema());
    }

    /**
     * AJAX: Validate field
     */
    public function ajaxValidateField(): void
    {
        check_ajax_referer('ffp_nonce', 'nonce');

        $type = sanitize_text_field($_POST['type'] ?? '');
        $value = $_POST['value'] ?? null;
        $field = json_decode(stripslashes($_POST['field'] ?? '{}'), true);

        $errors = $this->field_registry->validate($type, $value, $field);

        if (empty($errors)) {
            wp_send_json_success(['valid' => true]);
        } else {
            wp_send_json_error(['errors' => $errors]);
        }
    }

    /**
     * AJAX: Import form
     */
    public function ajaxImportForm(): void
    {
        check_ajax_referer('ffp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $import_data = json_decode(stripslashes($_POST['import_data'] ?? '{}'), true);
        $form_id = $this->importForm($import_data);

        if ($form_id) {
            wp_send_json_success([
                'form_id' => $form_id,
                'message' => __('Form imported successfully', 'form-flow-pro'),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to import form', 'form-flow-pro')]);
        }
    }

    /**
     * AJAX: Export form
     */
    public function ajaxExportForm(): void
    {
        check_ajax_referer('ffp_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $form_id = (int) ($_POST['form_id'] ?? 0);
        $include_versions = !empty($_POST['include_versions']);

        $export = $this->exportForm($form_id, $include_versions);

        if ($export) {
            wp_send_json_success($export);
        } else {
            wp_send_json_error(['message' => __('Form not found', 'form-flow-pro')]);
        }
    }

    /**
     * Render A/B Testing page
     */
    public function renderABTestingPage(): void
    {
        include FORMFLOW_PATH . 'includes/admin/views/ab-testing.php';
    }

    /**
     * Render Templates page
     */
    public function renderTemplatesPage(): void
    {
        $templates = $this->getTemplates();
        include FORMFLOW_PATH . 'includes/admin/views/templates.php';
    }

    /**
     * Render Analytics page
     */
    public function renderAnalyticsPage(): void
    {
        include FORMFLOW_PATH . 'includes/admin/views/analytics.php';
    }

    /**
     * Render Import/Export page
     */
    public function renderImportExportPage(): void
    {
        include FORMFLOW_PATH . 'includes/admin/views/import-export.php';
    }
}
