<?php

declare(strict_types=1);

namespace FormFlowPro\Autentique;

if (!defined('ABSPATH')) exit;

/**
 * Autentique Webhook Handler
 */
class Webhook_Handler
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void
    {
        register_rest_route('formflow/v1', '/webhook/autentique', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true', // Autentique doesn't use auth
        ]);
    }

    /**
     * Handle webhook payload
     */
    public function handle_webhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $request->get_json_params();

        // Log webhook for debugging
        $this->log_webhook($payload);

        if (!isset($payload['event']) || !isset($payload['data'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid payload',
            ], 400);
        }

        $event = $payload['event'];
        $data = $payload['data'];

        try {
            switch ($event) {
                case 'document.signed':
                    $this->handle_document_signed($data);
                    break;

                case 'document.refused':
                    $this->handle_document_refused($data);
                    break;

                case 'document.viewed':
                    $this->handle_document_viewed($data);
                    break;

                case 'document.created':
                    $this->handle_document_created($data);
                    break;

                case 'document.deleted':
                    $this->handle_document_deleted($data);
                    break;

                default:
                    // Unknown event, but don't fail
                    break;
            }

            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Webhook processed',
            ], 200);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle document signed event
     */
    private function handle_document_signed(array $data): void
    {
        $document_id = $data['document']['id'] ?? null;
        $signed_at = $data['signature']['signed']['created_at'] ?? current_time('mysql');

        if (!$document_id) {
            return;
        }

        $service = new Autentique_Service();
        $service->update_signature_status($document_id, 'signed', $signed_at);

        // Send completion email
        $this->send_completion_email($document_id);
    }

    /**
     * Handle document refused event
     */
    private function handle_document_refused(array $data): void
    {
        $document_id = $data['document']['id'] ?? null;

        if (!$document_id) {
            return;
        }

        $service = new Autentique_Service();
        $service->update_signature_status($document_id, 'refused');

        // Notify admin
        $this->notify_admin_refusal($document_id);
    }

    /**
     * Handle document viewed event
     */
    private function handle_document_viewed(array $data): void
    {
        $document_id = $data['document']['id'] ?? null;

        if (!$document_id) {
            return;
        }

        // Update metadata
        global $wpdb;
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_submissions WHERE signature_document_id = %s",
            $document_id
        ));

        if ($submission) {
            $metadata = json_decode($submission->metadata, true) ?? [];
            $metadata['document_viewed_at'] = current_time('mysql');

            $wpdb->update(
                $wpdb->prefix . 'formflow_submissions',
                ['metadata' => wp_json_encode($metadata)],
                ['id' => $submission->id],
                ['%s'],
                ['%d']
            );
        }
    }

    /**
     * Handle document created event
     */
    private function handle_document_created(array $data): void
    {
        // Optional: Additional processing after document creation
    }

    /**
     * Handle document deleted event
     */
    private function handle_document_deleted(array $data): void
    {
        $document_id = $data['document']['id'] ?? null;

        if (!$document_id) {
            return;
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'formflow_submissions',
            [
                'signature_status' => 'canceled',
                'status' => 'failed',
            ],
            ['signature_document_id' => $document_id],
            ['%s', '%s'],
            ['%s']
        );
    }

    /**
     * Send completion email
     */
    private function send_completion_email(string $document_id): void
    {
        global $wpdb;

        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_submissions WHERE signature_document_id = %s",
            $document_id
        ));

        if (!$submission) {
            return;
        }

        $form_data = json_decode($submission->form_data, true);
        $email = $form_data['email'] ?? null;

        if ($email) {
            $email_service = new \FormFlowPro\Email\Email_Template();
            $email_service->send('submission_confirmation', $email, [
                'site_name' => get_bloginfo('name'),
            ]);
        }
    }

    /**
     * Notify admin of refusal
     */
    private function notify_admin_refusal(string $document_id): void
    {
        $admin_email = get_option('formflow_company_email', get_option('admin_email'));

        if (!$admin_email) {
            return;
        }

        $subject = __('Document Signature Refused', 'formflow-pro');
        $message = sprintf(
            __('A document signature was refused: %s', 'formflow-pro'),
            $document_id
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Log webhook for debugging
     */
    private function log_webhook(array $payload): void
    {
        if (get_option('formflow_debug_mode')) {
            $log_service = \FormFlowPro\Logs\Log_Manager::get_instance();
            $log_service->log('webhook', 'Autentique webhook received', $payload);
        }
    }

    /**
     * Get webhook URL
     */
    public static function get_webhook_url(): string
    {
        return rest_url('formflow/v1/webhook/autentique');
    }
}
