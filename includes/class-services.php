<?php

declare(strict_types=1);

/**
 * Services Loader
 *
 * Loads and initializes all FormFlow Pro services.
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

namespace FormFlowPro;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Services Class
 */
class Services
{
    /**
     * Initialize all services
     *
     * @return void
     */
    public static function init(): void
    {
        self::load_services();
        self::initialize_services();
    }

    /**
     * Load service classes
     *
     * @return void
     */
    private static function load_services(): void
    {
        // Queue System
        require_once FORMFLOW_PATH . 'includes/queue/class-queue-manager.php';

        // PDF Generation
        require_once FORMFLOW_PATH . 'includes/pdf/class-pdf-generator.php';

        // Email Templates
        require_once FORMFLOW_PATH . 'includes/email/class-email-template.php';

        // Cache Layer
        require_once FORMFLOW_PATH . 'includes/cache/class-cache-manager.php';
    }

    /**
     * Initialize services
     *
     * @return void
     */
    private static function initialize_services(): void
    {
        // Initialize Queue Manager
        Queue\Queue_Manager::get_instance();

        // Initialize Cache Manager
        Cache\Cache_Manager::get_instance();

        // Hook into submission processing
        add_action('formflow_form_submitted', [__CLASS__, 'handle_submission'], 10, 3);
    }

    /**
     * Handle form submission
     *
     * @param int   $submission_id Submission ID.
     * @param int   $form_id Form ID.
     * @param array $form_data Form data.
     * @return void
     */
    public static function handle_submission(int $submission_id, int $form_id, array $form_data): void
    {
        $queue = Queue\Queue_Manager::get_instance();

        // Queue PDF generation
        $queue->add_job('generate_pdf', [
            'submission_id' => $submission_id,
        ], 5);

        // Queue email notification
        $queue->add_job('send_notification', [
            'submission_id' => $submission_id,
            'form_id' => $form_id,
            'form_data' => $form_data,
        ], 10);
    }

    /**
     * Get cache instance
     *
     * @return Cache\Cache_Manager
     */
    public static function cache(): Cache\Cache_Manager
    {
        return Cache\Cache_Manager::get_instance();
    }

    /**
     * Get queue instance
     *
     * @return Queue\Queue_Manager
     */
    public static function queue(): Queue\Queue_Manager
    {
        return Queue\Queue_Manager::get_instance();
    }

    /**
     * Get PDF generator instance
     *
     * @return PDF\PDF_Generator
     */
    public static function pdf(): PDF\PDF_Generator
    {
        return new PDF\PDF_Generator();
    }

    /**
     * Get email template instance
     *
     * @return Email\Email_Template
     */
    public static function email(): Email\Email_Template
    {
        return new Email\Email_Template();
    }
}

// Register queue processors
add_action('formflow_process_generate_pdf', function ($data, $job_id) {
    try {
        $pdf = Services::pdf();
        $pdf_url = $pdf->generate_submission_pdf($data['submission_id']);

        // Save PDF URL to submission metadata
        global $wpdb;
        $metadata = $wpdb->get_var($wpdb->prepare(
            "SELECT metadata FROM {$wpdb->prefix}formflow_submissions WHERE id = %d",
            $data['submission_id']
        ));

        $metadata = $metadata ? json_decode($metadata, true) : [];
        $metadata['pdf_url'] = $pdf_url;

        $wpdb->update(
            $wpdb->prefix . 'formflow_submissions',
            ['metadata' => wp_json_encode($metadata)],
            ['id' => $data['submission_id']],
            ['%s'],
            ['%d']
        );
    } catch (\Exception $e) {
        error_log('FormFlow PDF Generation Error: ' . $e->getMessage());
    }
}, 10, 2);

add_action('formflow_process_send_notification', function ($data, $job_id) {
    try {
        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_forms WHERE id = %d",
            $data['form_id']
        ));

        if (!$form) {
            return;
        }

        $email = Services::email();
        $admin_email = get_option('formflow_company_email', get_option('admin_email'));

        $form_data_html = '<table>';
        foreach ($data['form_data'] as $key => $value) {
            $form_data_html .= '<tr><td><strong>' . ucwords(str_replace('_', ' ', $key)) . ':</strong></td>';
            $form_data_html .= '<td>' . esc_html($value) . '</td></tr>';
        }
        $form_data_html .= '</table>';

        $email->send('submission_notification', $admin_email, [
            'form_name' => $form->name,
            'form_data' => $form_data_html,
            'admin_url' => admin_url('admin.php?page=formflow-submissions&submission_id=' . $data['submission_id']),
            'site_name' => get_bloginfo('name'),
        ]);

        // Send confirmation to user if email field exists
        if (isset($data['form_data']['email'])) {
            $email->send('submission_confirmation', $data['form_data']['email'], [
                'site_name' => get_bloginfo('name'),
            ]);
        }
    } catch (\Exception $e) {
        error_log('FormFlow Email Notification Error: ' . $e->getMessage());
    }
}, 10, 2);

/**
 * Process check_signature_status queue job
 *
 * Checks the current status of an Autentique document and updates
 * the submission accordingly.
 *
 * @since 2.0.0
 */
add_action('formflow_process_check_signature_status', function ($data, $job_id) {
    try {
        $submission_id = $data['submission_id'] ?? null;
        if (!$submission_id) {
            error_log('FormFlow: check_signature_status - Missing submission_id');
            return;
        }

        global $wpdb;

        // Get submission to find document_id
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT id, signature_document_id, signature_status FROM {$wpdb->prefix}formflow_submissions WHERE id = %d",
            $submission_id
        ));

        if (!$submission) {
            error_log("FormFlow: Submission #{$submission_id} not found");
            return;
        }

        // If no document ID yet, try to get from autentique_documents table
        $document_id = $submission->signature_document_id;
        if (!$document_id) {
            $doc = $wpdb->get_row($wpdb->prepare(
                "SELECT document_id FROM {$wpdb->prefix}formflow_autentique_documents WHERE submission_id = %d ORDER BY created_at DESC LIMIT 1",
                $submission_id
            ));
            $document_id = $doc->document_id ?? null;
        }

        if (!$document_id) {
            error_log("FormFlow: No document_id found for submission #{$submission_id}");
            return;
        }

        // Initialize Autentique service
        require_once FORMFLOW_PATH . 'includes/autentique/class-autentique-service.php';
        $autentique = new \FormFlowPro\Autentique\Autentique_Service();

        // Get current document status from Autentique API
        $document_status = $autentique->get_document_status($document_id);

        if (empty($document_status)) {
            error_log("FormFlow: Could not get status for document {$document_id}");
            return;
        }

        // Check signature status
        $all_signed = true;
        $any_refused = false;
        $signed_at = null;

        if (!empty($document_status['signatures'])) {
            foreach ($document_status['signatures'] as $signature) {
                // Check if refused
                if (!empty($signature['refused'])) {
                    $any_refused = true;
                    break;
                }

                // Check if not signed yet
                if (empty($signature['signed'])) {
                    $all_signed = false;
                } else {
                    // Use the latest signature timestamp
                    $signature_time = $signature['signed']['created_at'] ?? null;
                    if ($signature_time && (!$signed_at || $signature_time > $signed_at)) {
                        $signed_at = $signature_time;
                    }
                }
            }
        }

        // Determine new status
        $new_status = 'pending';
        if ($any_refused) {
            $new_status = 'refused';
        } elseif ($all_signed) {
            $new_status = 'signed';
        }

        // Update submission if status changed
        if ($new_status !== $submission->signature_status) {
            $update_data = [
                'signature_status' => $new_status,
                'updated_at' => current_time('mysql'),
            ];

            if ($signed_at) {
                $update_data['signed_at'] = $signed_at;
            }

            $wpdb->update(
                $wpdb->prefix . 'formflow_submissions',
                $update_data,
                ['id' => $submission_id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            // Also update the autentique_documents table
            $wpdb->update(
                $wpdb->prefix . 'formflow_autentique_documents',
                [
                    'status' => $new_status,
                    'signed_at' => $signed_at,
                    'updated_at' => current_time('mysql'),
                ],
                ['document_id' => $document_id],
                ['%s', '%s', '%s'],
                ['%s']
            );

            // Fire action for status change
            do_action('formflow_signature_status_changed', $submission_id, $new_status, $document_id);

            // Log the status change
            require_once FORMFLOW_PATH . 'includes/logs/class-log-manager.php';
            $log = \FormFlowPro\Logs\Log_Manager::get_instance();
            $log->info('Signature status updated', [
                'submission_id' => $submission_id,
                'document_id' => $document_id,
                'old_status' => $submission->signature_status,
                'new_status' => $new_status,
            ]);

            error_log("FormFlow: Signature status updated for submission #{$submission_id}: {$new_status}");
        }

        // If still pending, re-queue for later check (max 24 hours)
        if ($new_status === 'pending') {
            $check_count = ($data['check_count'] ?? 0) + 1;

            // Check up to 288 times (24 hours at 5-minute intervals)
            if ($check_count < 288) {
                $queue = \FormFlowPro\Queue\Queue_Manager::get_instance();
                $queue->add_job('check_signature_status', [
                    'submission_id' => $submission_id,
                    'check_count' => $check_count,
                ], 5);
            } else {
                error_log("FormFlow: Max signature checks reached for submission #{$submission_id}");
            }
        }
    } catch (\Exception $e) {
        error_log('FormFlow Signature Status Check Error: ' . $e->getMessage());

        require_once FORMFLOW_PATH . 'includes/logs/class-log-manager.php';
        $log = \FormFlowPro\Logs\Log_Manager::get_instance();
        $log->error('Signature status check failed', [
            'submission_id' => $data['submission_id'] ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}, 10, 2);
