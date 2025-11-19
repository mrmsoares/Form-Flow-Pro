<?php

/**
 * Form Processor - Core form submission processing engine.
 *
 * Handles the complete form submission workflow:
 * 1. Validation and sanitization
 * 2. Data compression and storage
 * 3. Queue job creation
 * 4. Status tracking
 * 5. Error handling and logging
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

namespace FormFlowPro\Core;

use FormFlowPro\Core\CacheManager;

/**
 * Form Processor class.
 *
 * Main orchestrator for form submission processing pipeline.
 *
 * @since 2.0.0
 */
class FormProcessor
{
    /**
     * WordPress database object.
     *
     * @since 2.0.0
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Cache manager instance.
     *
     * @since 2.0.0
     * @var CacheManager
     */
    private $cache;

    /**
     * Current submission ID.
     *
     * @since 2.0.0
     * @var string|null
     */
    private $submission_id;

    /**
     * Processing start time.
     *
     * @since 2.0.0
     * @var float
     */
    private $start_time;

    /**
     * Constructor.
     *
     * @since 2.0.0
     * @param CacheManager|null $cache Cache manager instance.
     */
    public function __construct($cache = null)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->cache = $cache ?? new CacheManager();
    }

    /**
     * Process form submission.
     *
     * Main entry point for form processing pipeline.
     *
     * @since 2.0.0
     * @param string $form_id Form UUID.
     * @param array $data Form field data.
     * @param array $meta Additional metadata.
     * @return array Result with submission_id and status.
     */
    public function process_submission($form_id, array $data, array $meta = [])
    {
        $this->start_time = microtime(true);
        $this->submission_id = $this->generate_uuid();

        try {
            // Validate form exists and is active
            $form = $this->get_form($form_id);
            if (!$form) {
                throw new \Exception('Form not found or inactive');
            }

            // Validate and sanitize data
            $validated_data = $this->validate_data($data, $form);

            // Store submission
            $submission_stored = $this->store_submission(
                $this->submission_id,
                $form_id,
                $validated_data,
                $meta
            );

            if (!$submission_stored) {
                throw new \Exception('Failed to store submission');
            }

            // Store additional metadata
            if (!empty($meta)) {
                $this->store_submission_meta($this->submission_id, $meta);
            }

            // Queue processing jobs
            $this->queue_processing_jobs($this->submission_id, $form);

            // Log success
            $this->log_processing('info', 'Submission processed successfully', [
                'submission_id' => $this->submission_id,
                'form_id' => $form_id,
                'processing_time_ms' => $this->get_processing_time(),
            ]);

            return [
                'success' => true,
                'submission_id' => $this->submission_id,
                'status' => 'pending',
                'message' => __('Submission received and queued for processing', 'formflow-pro'),
            ];
        } catch (\Exception $e) {
            // Log error
            $this->log_processing('error', $e->getMessage(), [
                'submission_id' => $this->submission_id,
                'form_id' => $form_id,
                'trace' => $e->getTraceAsString(),
            ]);

            // Store failed submission for debugging
            if ($this->submission_id) {
                $this->update_submission_status($this->submission_id, 'failed');
            }

            return [
                'success' => false,
                'submission_id' => $this->submission_id,
                'status' => 'failed',
                'message' => __('Submission processing failed', 'formflow-pro'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get form configuration.
     *
     * Uses caching for performance.
     *
     * @since 2.0.0
     * @param string $form_id Form UUID.
     * @return object|null Form object or null.
     */
    private function get_form($form_id)
    {
        return $this->cache->remember(
            "form_{$form_id}",
            function () use ($form_id) {
                return $this->wpdb->get_row(
                    $this->wpdb->prepare(
                        "SELECT * FROM {$this->wpdb->prefix}formflow_forms
                        WHERE id = %s AND status = 'active'",
                        $form_id
                    )
                );
            },
            1800 // 30 minutes
        );
    }

    /**
     * Validate and sanitize form data.
     *
     * @since 2.0.0
     * @param array $data Raw form data.
     * @param object $form Form configuration.
     * @return array Validated data.
     * @throws \Exception If validation fails.
     */
    private function validate_data(array $data, $form)
    {
        $validated = [];

        // Apply WordPress sanitization
        foreach ($data as $key => $value) {
            $sanitized_key = sanitize_key($key);

            if (is_array($value)) {
                $validated[$sanitized_key] = array_map('sanitize_text_field', $value);
            } elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $validated[$sanitized_key] = sanitize_email($value);
            } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
                $validated[$sanitized_key] = esc_url_raw($value);
            } else {
                $validated[$sanitized_key] = sanitize_text_field($value);
            }
        }

        // Custom validation rules from form settings
        $validated = apply_filters('formflow_validate_submission', $validated, $form);

        return $validated;
    }

    /**
     * Store submission in database.
     *
     * @since 2.0.0
     * @param string $submission_id Submission UUID.
     * @param string $form_id Form UUID.
     * @param array $data Validated form data.
     * @param array $meta Request metadata.
     * @return bool True on success.
     */
    private function store_submission($submission_id, $form_id, array $data, array $meta)
    {
        $json_data = wp_json_encode($data);
        $compressed_data = gzcompress($json_data, 6); // Level 6 compression
        $data_compressed = $compressed_data !== false;

        $insert_data = [
            'id' => $submission_id,
            'form_id' => $form_id,
            'status' => 'pending',
            'data' => $data_compressed ? $compressed_data : $json_data,
            'data_compressed' => $data_compressed ? 1 : 0,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $this->get_user_agent(),
            'referrer_url' => $this->get_referrer(),
            'created_at' => current_time('mysql'),
        ];

        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'formflow_submissions',
            $insert_data,
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Store submission metadata.
     *
     * @since 2.0.0
     * @param string $submission_id Submission UUID.
     * @param array $meta Metadata key-value pairs.
     */
    private function store_submission_meta($submission_id, array $meta)
    {
        foreach ($meta as $key => $value) {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'formflow_submission_meta',
                [
                    'submission_id' => $submission_id,
                    'meta_key' => sanitize_key($key),
                    'meta_value' => is_array($value) || is_object($value)
                        ? serialize($value)
                        : sanitize_text_field($value),
                ],
                ['%s', '%s', '%s']
            );
        }
    }

    /**
     * Queue processing jobs.
     *
     * @since 2.0.0
     * @param string $submission_id Submission UUID.
     * @param object $form Form configuration.
     */
    private function queue_processing_jobs($submission_id, $form)
    {
        $form_settings = json_decode($form->settings, true);

        // Queue PDF generation if template configured
        if (!empty($form->pdf_template_id)) {
            $this->queue_job('generate_pdf', [
                'submission_id' => $submission_id,
                'template_id' => $form->pdf_template_id,
            ], 'high');
        }

        // Queue Autentique if enabled
        if ($form->autentique_enabled && !empty($form_settings['autentique_enabled'])) {
            $this->queue_job('send_autentique', [
                'submission_id' => $submission_id,
            ], 'high');
        }

        // Queue email if template configured
        if (!empty($form->email_template_id)) {
            $this->queue_job('send_email', [
                'submission_id' => $submission_id,
                'template_id' => $form->email_template_id,
            ], 'medium');
        }
    }

    /**
     * Add job to queue.
     *
     * @since 2.0.0
     * @param string $job_type Job type.
     * @param array $job_data Job parameters.
     * @param string $priority Priority level.
     * @return int|false Job ID or false on failure.
     */
    private function queue_job($job_type, array $job_data, $priority = 'medium')
    {
        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'formflow_queue',
            [
                'job_type' => $job_type,
                'job_data' => wp_json_encode($job_data),
                'priority' => $priority,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
                'scheduled_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        return $result !== false ? $this->wpdb->insert_id : false;
    }

    /**
     * Update submission status.
     *
     * @since 2.0.0
     * @param string $submission_id Submission UUID.
     * @param string $status New status.
     * @return bool True on success.
     */
    private function update_submission_status($submission_id, $status)
    {
        $update_data = [
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ];

        if ($status === 'completed') {
            $update_data['processed_at'] = current_time('mysql');
            $update_data['processing_time_ms'] = (int) $this->get_processing_time();
        }

        $result = $this->wpdb->update(
            $this->wpdb->prefix . 'formflow_submissions',
            $update_data,
            ['id' => $submission_id],
            array_fill(0, count($update_data), '%s'),
            ['%s']
        );

        // Invalidate cache
        $this->cache->delete("submission_{$submission_id}");

        return $result !== false;
    }

    /**
     * Log processing event.
     *
     * @since 2.0.0
     * @param string $level Log level.
     * @param string $message Log message.
     * @param array $context Additional context.
     */
    private function log_processing($level, $message, array $context = [])
    {
        $this->wpdb->insert(
            $this->wpdb->prefix . 'formflow_logs',
            [
                'submission_id' => $this->submission_id,
                'level' => $level,
                'message' => $message,
                'context' => wp_json_encode($context),
                'category' => 'processing',
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Generate UUID v4.
     *
     * @since 2.0.0
     * @return string UUID.
     */
    private function generate_uuid()
    {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }

        // Fallback UUID generation
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Get client IP address.
     *
     * @since 2.0.0
     * @return string IP address.
     */
    private function get_client_ip()
    {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
                // Handle comma-separated IPs from proxies
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get user agent.
     *
     * @since 2.0.0
     * @return string User agent.
     */
    private function get_user_agent()
    {
        return !empty($_SERVER['HTTP_USER_AGENT'])
            ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 500)
            : '';
    }

    /**
     * Get referrer URL.
     *
     * @since 2.0.0
     * @return string|null Referrer URL.
     */
    private function get_referrer()
    {
        return !empty($_SERVER['HTTP_REFERER'])
            ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']))
            : null;
    }

    /**
     * Get processing time in milliseconds.
     *
     * @since 2.0.0
     * @return float Processing time.
     */
    private function get_processing_time()
    {
        return round((microtime(true) - $this->start_time) * 1000, 2);
    }
}
