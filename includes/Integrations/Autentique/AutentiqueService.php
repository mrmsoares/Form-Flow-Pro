<?php

/**
 * Autentique Service.
 *
 * Service layer for Autentique integration.
 * Orchestrates document creation, PDF generation, and signature tracking.
 *
 * @package FormFlowPro
 * @subpackage Integrations\Autentique
 * @since 2.1.0
 */

namespace FormFlowPro\Integrations\Autentique;

use FormFlowPro\Core\CacheManager;

/**
 * Autentique Service class.
 *
 * Integrates Autentique API with FormFlow Pro submission pipeline.
 * Handles document lifecycle from creation to signed document storage.
 *
 * @since 2.1.0
 */
class AutentiqueService
{
    /**
     * Autentique API client.
     *
     * @since 2.1.0
     * @var AutentiqueClient
     */
    private $client;

    /**
     * Cache manager.
     *
     * @since 2.1.0
     * @var CacheManager
     */
    private $cache;

    /**
     * WordPress database instance.
     *
     * @since 2.1.0
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Constructor.
     *
     * @since 2.1.0
     * @param AutentiqueClient|null $client Autentique client instance.
     * @param CacheManager|null $cache Cache manager instance.
     */
    public function __construct($client = null, $cache = null)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->client = $client ?? new AutentiqueClient();
        $this->cache = $cache ?? new CacheManager();
    }

    /**
     * Create Autentique document from form submission.
     *
     * @since 2.1.0
     * @param string $submission_id Submission ID.
     * @return array Result with document ID or error.
     */
    public function createDocumentFromSubmission($submission_id)
    {
        try {
            // Get submission data
            $submission = $this->getSubmission($submission_id);
            if (!$submission) {
                throw new \Exception('Submission not found');
            }

            // Get form configuration
            $form = $this->getForm($submission->form_id);
            if (!$form) {
                throw new \Exception('Form not found');
            }

            // Check if Autentique is enabled for this form
            $settings = json_decode($form->settings, true);
            if (!isset($settings['autentique_enabled']) || !$settings['autentique_enabled']) {
                throw new \Exception('Autentique not enabled for this form');
            }

            // Parse submission data
            $data = json_decode($submission->form_data, true);

            // Generate PDF from submission
            $pdf_path = $this->generatePDF($submission, $data, $form);
            $pdf_base64 = $this->encodeFileToBase64($pdf_path);

            // Extract signers from submission
            $signers = $this->extractSigners($data, $settings);

            // Prepare document data
            $document_args = [
                'name' => $this->getDocumentName($submission, $form),
                'file' => $pdf_base64,
                'signers' => $signers,
                'sandbox' => $settings['autentique_sandbox'] ?? false,
                'auto_close' => $settings['autentique_auto_close'] ?? true,
                'send_automatic_email' => $settings['autentique_send_email'] ?? true,
            ];

            // Create document in Autentique
            $response = $this->client->createDocument($document_args);

            // Store document reference in database
            $this->storeDocumentReference($submission_id, $response);

            // Update submission status
            $this->updateSubmissionStatus($submission_id, 'pending_signature');

            // Queue status check job
            $this->queueStatusCheck($submission_id, $response['id']);

            // Log success
            $this->logActivity($submission_id, 'info', 'Document created in Autentique', [
                'document_id' => $response['id'],
            ]);

            // Clean up temporary PDF
            if (file_exists($pdf_path)) {
                unlink($pdf_path);
            }

            return [
                'success' => true,
                'document_id' => $response['id'],
                'message' => 'Document created successfully',
            ];
        } catch (\Exception $e) {
            $this->logActivity($submission_id, 'error', 'Failed to create Autentique document', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process Autentique webhook notification.
     *
     * @since 2.1.0
     * @param array $webhook_data Webhook payload.
     * @return array Processing result.
     */
    public function processSignatureWebhook(array $webhook_data)
    {
        try {
            // Validate webhook signature (if configured)
            if (!$this->validateWebhookSignature($webhook_data)) {
                throw new \Exception('Invalid webhook signature');
            }

            // Extract document ID and event
            $document_id = $webhook_data['document_id'] ?? null;
            $event = $webhook_data['event'] ?? null;

            if (!$document_id || !$event) {
                throw new \Exception('Missing required webhook data');
            }

            // Find submission by document ID
            $submission_id = $this->findSubmissionByDocumentId($document_id);
            if (!$submission_id) {
                throw new \Exception('Submission not found for document');
            }

            // Process based on event type
            switch ($event) {
                case 'document.signed':
                    $this->handleDocumentSigned($submission_id, $document_id, $webhook_data);
                    break;

                case 'document.refused':
                    $this->handleDocumentRefused($submission_id, $document_id, $webhook_data);
                    break;

                case 'document.viewed':
                    $this->handleDocumentViewed($submission_id, $document_id, $webhook_data);
                    break;

                case 'document.completed':
                    $this->handleDocumentCompleted($submission_id, $document_id, $webhook_data);
                    break;

                default:
                    $this->logActivity($submission_id, 'info', 'Unhandled webhook event', [
                        'event' => $event,
                        'document_id' => $document_id,
                    ]);
            }

            return [
                'success' => true,
                'message' => 'Webhook processed',
            ];
        } catch (\Exception $e) {
            // Log error but return success to prevent webhook retry
            error_log('Autentique webhook error: ' . $e->getMessage());

            return [
                'success' => true, // Return success to acknowledge webhook
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check document status and update submission.
     *
     * @since 2.1.0
     * @param string $document_id Autentique document ID.
     * @return array Document status.
     */
    public function checkDocumentStatus($document_id)
    {
        try {
            // Check cache first
            $cache_key = 'autentique_status_' . $document_id;
            $cached = $this->cache->get($cache_key);

            if ($cached !== null) {
                return $cached;
            }

            // Fetch from API
            $status = $this->client->getDocumentStatus($document_id);

            // Cache for 5 minutes
            $this->cache->set($cache_key, $status, 300);

            // Find and update submission
            $submission_id = $this->findSubmissionByDocumentId($document_id);
            if ($submission_id) {
                $this->updateDocumentStatus($submission_id, $status);
            }

            return $status;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Download signed document and store in WordPress.
     *
     * @since 2.1.0
     * @param string $document_id Autentique document ID.
     * @return array Result with file URL or error.
     */
    public function downloadSignedDocument($document_id)
    {
        try {
            // Download PDF from Autentique
            $pdf_content = $this->client->downloadDocument($document_id);

            // Find submission
            $submission_id = $this->findSubmissionByDocumentId($document_id);
            if (!$submission_id) {
                throw new \Exception('Submission not found');
            }

            // Save to WordPress uploads
            $upload = $this->saveSignedDocument($submission_id, $document_id, $pdf_content);

            // Store file reference in submission meta
            $this->storeSignedDocumentMeta($submission_id, $upload);

            // Update submission status
            $this->updateSubmissionStatus($submission_id, 'completed');

            // Log success
            $this->logActivity($submission_id, 'info', 'Signed document downloaded', [
                'file_url' => $upload['url'],
            ]);

            return [
                'success' => true,
                'file_url' => $upload['url'],
                'file_path' => $upload['file'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate PDF from submission data.
     *
     * @since 2.1.0
     * @param object $submission Submission object.
     * @param array $data Form data.
     * @param object $form Form object.
     * @return string Path to generated PDF.
     * @throws \Exception If PDF generation fails.
     */
    private function generatePDF($submission, array $data, $form)
    {
        // Get PDF template
        $template_id = $this->getPDFTemplateId($form);

        if ($template_id) {
            // Use template-based generation
            return $this->generatePDFFromTemplate($template_id, $data);
        }

        // Fallback: Simple PDF generation
        return $this->generateSimplePDF($submission, $data, $form);
    }

    /**
     * Generate simple PDF without template.
     *
     * @since 2.1.0
     * @param object $submission Submission object.
     * @param array $data Form data.
     * @param object $form Form object.
     * @return string Path to generated PDF.
     */
    private function generateSimplePDF($submission, array $data, $form)
    {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/formflow-temp/';

        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        $filename = 'submission-' . $submission->id . '-' . time() . '.pdf';
        $filepath = $temp_dir . $filename;

        // Simple HTML to PDF conversion
        // In production, integrate with TCPDF, mPDF, or Dompdf
        $html = $this->buildSubmissionHTML($data, $form);

        // Placeholder: Write HTML for now
        // TODO: Integrate proper PDF library
        file_put_contents($filepath, $html);

        return $filepath;
    }

    /**
     * Build HTML from submission data.
     *
     * @since 2.1.0
     * @param array $data Form data.
     * @param object $form Form object.
     * @return string HTML content.
     */
    private function buildSubmissionHTML(array $data, $form)
    {
        $html = '<html><body>';
        $html .= '<h1>' . esc_html($form->name) . '</h1>';
        $html .= '<table>';

        foreach ($data as $key => $value) {
            $html .= '<tr>';
            $html .= '<td><strong>' . esc_html($key) . ':</strong></td>';
            $html .= '<td>' . esc_html($value) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Encode file to Base64.
     *
     * @since 2.1.0
     * @param string $file_path File path.
     * @return string Base64 encoded content.
     * @throws \Exception If file not found.
     */
    private function encodeFileToBase64($file_path)
    {
        if (!file_exists($file_path)) {
            throw new \Exception('File not found: ' . $file_path);
        }

        $content = file_get_contents($file_path);
        return base64_encode($content);
    }

    /**
     * Extract signers from form data.
     *
     * @since 2.1.0
     * @param array $data Form data.
     * @param array $settings Form settings.
     * @return array Signers array.
     */
    private function extractSigners(array $data, array $settings)
    {
        $signers = [];

        // Get signer field mappings from settings
        $signer_config = $settings['autentique_signers'] ?? [];

        foreach ($signer_config as $config) {
            $signer = [
                'email' => $data[$config['email_field']] ?? '',
                'name' => $data[$config['name_field']] ?? '',
                'action' => $config['action'] ?? 'sign', // sign, approve, acknowledge
            ];

            // Optional fields
            if (isset($config['phone_field']) && isset($data[$config['phone_field']])) {
                $signer['phone'] = $data[$config['phone_field']];
            }

            if (isset($config['cpf_field']) && isset($data[$config['cpf_field']])) {
                $signer['cpf'] = $data[$config['cpf_field']];
            }

            if (!empty($signer['email'])) {
                $signers[] = $signer;
            }
        }

        // Ensure at least one signer
        if (empty($signers)) {
            throw new \Exception('No signers configured');
        }

        return $signers;
    }

    /**
     * Get document name from submission.
     *
     * @since 2.1.0
     * @param object $submission Submission object.
     * @param object $form Form object.
     * @return string Document name.
     */
    private function getDocumentName($submission, $form)
    {
        $timestamp = date('Y-m-d H:i:s', strtotime($submission->created_at));
        return sprintf(
            '%s - %s - %s',
            $form->name,
            $submission->id,
            $timestamp
        );
    }

    /**
     * Store document reference in database.
     *
     * @since 2.1.0
     * @param string $submission_id Submission ID.
     * @param array $response Autentique API response.
     * @return void
     */
    private function storeDocumentReference($submission_id, array $response)
    {
        $this->wpdb->insert(
            $this->wpdb->prefix . 'formflow_submission_meta',
            [
                'submission_id' => $submission_id,
                'meta_key' => 'autentique_document_id',
                'meta_value' => $response['id'],
                'created_at' => current_time('mysql'),
            ]
        );

        // Store full response as JSON
        $this->wpdb->insert(
            $this->wpdb->prefix . 'formflow_submission_meta',
            [
                'submission_id' => $submission_id,
                'meta_key' => 'autentique_document_data',
                'meta_value' => wp_json_encode($response),
                'created_at' => current_time('mysql'),
            ]
        );
    }

    /**
     * Update submission status.
     *
     * @since 2.1.0
     * @param string $submission_id Submission ID.
     * @param string $status New status.
     * @return void
     */
    private function updateSubmissionStatus($submission_id, $status)
    {
        $this->wpdb->update(
            $this->wpdb->prefix . 'formflow_submissions',
            ['status' => $status],
            ['id' => $submission_id],
            ['%s'],
            ['%s']
        );

        // Invalidate cache
        $this->cache->delete('submission_' . $submission_id);
    }

    /**
     * Queue document status check job.
     *
     * @since 2.1.0
     * @param string $submission_id Submission ID.
     * @param string $document_id Autentique document ID.
     * @return void
     */
    private function queueStatusCheck($submission_id, $document_id)
    {
        $job_id = $this->generateUUID();

        $this->wpdb->insert(
            $this->wpdb->prefix . 'formflow_queue',
            [
                'id' => $job_id,
                'submission_id' => $submission_id,
                'job_type' => 'autentique_status_check',
                'payload' => wp_json_encode(['document_id' => $document_id]),
                'status' => 'pending',
                'priority' => 50,
                'scheduled_at' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
                'created_at' => current_time('mysql'),
            ]
        );
    }

    /**
     * Handle document signed event.
     *
     * @since 2.1.0
     * @param string $submission_id Submission ID.
     * @param string $document_id Document ID.
     * @param array $data Webhook data.
     * @return void
     */
    private function handleDocumentSigned($submission_id, $document_id, array $data)
    {
        $signer = $data['signer'] ?? [];

        $this->logActivity($submission_id, 'info', 'Document signed by user', [
            'document_id' => $document_id,
            'signer_email' => $signer['email'] ?? 'unknown',
            'signed_at' => $data['signed_at'] ?? current_time('mysql'),
        ]);

        // Update status if all signed
        if ($data['all_signed'] ?? false) {
            $this->updateSubmissionStatus($submission_id, 'fully_signed');
            $this->queueDocumentDownload($submission_id, $document_id);
        }
    }

    /**
     * Handle document completed event.
     *
     * @since 2.1.0
     * @param string $submission_id Submission ID.
     * @param string $document_id Document ID.
     * @param array $data Webhook data.
     * @return void
     */
    private function handleDocumentCompleted($submission_id, $document_id, array $data)
    {
        $this->logActivity($submission_id, 'info', 'Document signing completed', [
            'document_id' => $document_id,
        ]);

        // Download signed document
        $this->downloadSignedDocument($document_id);

        // Trigger completion hooks
        do_action('formflow_autentique_document_completed', $submission_id, $document_id, $data);
    }

    /**
     * Handle document refused event.
     *
     * @since 2.1.0
     * @param string $submission_id Submission ID.
     * @param string $document_id Document ID.
     * @param array $data Webhook data.
     * @return void
     */
    private function handleDocumentRefused($submission_id, $document_id, array $data)
    {
        $this->updateSubmissionStatus($submission_id, 'signature_refused');

        $this->logActivity($submission_id, 'warning', 'Document signing refused', [
            'document_id' => $document_id,
            'reason' => $data['reason'] ?? 'No reason provided',
        ]);
    }

    /**
     * Handle document viewed event.
     *
     * @since 2.1.0
     * @param string $submission_id Submission ID.
     * @param string $document_id Document ID.
     * @param array $data Webhook data.
     * @return void
     */
    private function handleDocumentViewed($submission_id, $document_id, array $data)
    {
        $this->logActivity($submission_id, 'info', 'Document viewed', [
            'document_id' => $document_id,
            'viewer_email' => $data['viewer_email'] ?? 'unknown',
        ]);
    }

    /**
     * Queue document download job.
     *
     * @since 2.1.0
     * @param string $submission_id Submission ID.
     * @param string $document_id Document ID.
     * @return void
     */
    private function queueDocumentDownload($submission_id, $document_id)
    {
        $job_id = $this->generateUUID();

        $this->wpdb->insert(
            $this->wpdb->prefix . 'formflow_queue',
            [
                'id' => $job_id,
                'submission_id' => $submission_id,
                'job_type' => 'autentique_download',
                'payload' => wp_json_encode(['document_id' => $document_id]),
                'status' => 'pending',
                'priority' => 80,
                'created_at' => current_time('mysql'),
            ]
        );
    }

    /**
     * Save signed document to WordPress uploads.
     *
     * @since 2.1.0
     * @param string $submission_id Submission ID.
     * @param string $document_id Document ID.
     * @param string $content PDF content.
     * @return array Upload information.
     */
    private function saveSignedDocument($submission_id, $document_id, $content)
    {
        $upload_dir = wp_upload_dir();
        $formflow_dir = $upload_dir['basedir'] . '/formflow-signed/';

        if (!file_exists($formflow_dir)) {
            wp_mkdir_p($formflow_dir);
        }

        $filename = sprintf('signed-%s-%s.pdf', $submission_id, $document_id);
        $filepath = $formflow_dir . $filename;

        file_put_contents($filepath, $content);

        return [
            'file' => $filepath,
            'url' => $upload_dir['baseurl'] . '/formflow-signed/' . $filename,
            'type' => 'application/pdf',
        ];
    }

    /**
     * Store signed document metadata.
     *
     * @since 2.1.0
     * @param string $submission_id Submission ID.
     * @param array $upload Upload information.
     * @return void
     */
    private function storeSignedDocumentMeta($submission_id, array $upload)
    {
        $this->wpdb->insert(
            $this->wpdb->prefix . 'formflow_submission_meta',
            [
                'submission_id' => $submission_id,
                'meta_key' => 'signed_document_url',
                'meta_value' => $upload['url'],
                'created_at' => current_time('mysql'),
            ]
        );

        $this->wpdb->insert(
            $this->wpdb->prefix . 'formflow_submission_meta',
            [
                'submission_id' => $submission_id,
                'meta_key' => 'signed_document_path',
                'meta_value' => $upload['file'],
                'created_at' => current_time('mysql'),
            ]
        );
    }

    /**
     * Validate webhook signature.
     *
     * @since 2.1.0
     * @param array $data Webhook data.
     * @return bool True if valid.
     */
    private function validateWebhookSignature(array $data)
    {
        // TODO: Implement webhook signature validation
        // Based on Autentique documentation
        return true;
    }

    /**
     * Find submission by Autentique document ID.
     *
     * @since 2.1.0
     * @param string $document_id Autentique document ID.
     * @return string|null Submission ID or null.
     */
    private function findSubmissionByDocumentId($document_id)
    {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT submission_id FROM {$this->wpdb->prefix}formflow_submission_meta
                WHERE meta_key = 'autentique_document_id' AND meta_value = %s LIMIT 1",
                $document_id
            )
        );

        return $result ?: null;
    }

    /**
     * Update document status in database.
     *
     * @since 2.1.0
     * @param string $submission_id Submission ID.
     * @param array $status Status data.
     * @return void
     */
    private function updateDocumentStatus($submission_id, array $status)
    {
        // Update or insert status meta
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->wpdb->prefix}formflow_submission_meta
                WHERE submission_id = %s AND meta_key = 'autentique_status' LIMIT 1",
                $submission_id
            )
        );

        if ($existing) {
            $this->wpdb->update(
                $this->wpdb->prefix . 'formflow_submission_meta',
                [
                    'meta_value' => wp_json_encode($status),
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $existing]
            );
        } else {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'formflow_submission_meta',
                [
                    'submission_id' => $submission_id,
                    'meta_key' => 'autentique_status',
                    'meta_value' => wp_json_encode($status),
                    'created_at' => current_time('mysql'),
                ]
            );
        }
    }

    /**
     * Get submission from database.
     *
     * @since 2.1.0
     * @param string $submission_id Submission ID.
     * @return object|null Submission object or null.
     */
    private function getSubmission($submission_id)
    {
        // Check cache first
        $cache_key = 'submission_' . $submission_id;
        $cached = $this->cache->get($cache_key);

        if ($cached !== null) {
            return $cached;
        }

        $submission = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}formflow_submissions WHERE id = %s",
                $submission_id
            )
        );

        if ($submission) {
            $this->cache->set($cache_key, $submission, 300);
        }

        return $submission;
    }

    /**
     * Get form from database.
     *
     * @since 2.1.0
     * @param string $form_id Form ID.
     * @return object|null Form object or null.
     */
    private function getForm($form_id)
    {
        // Check cache first
        $cache_key = 'form_' . $form_id;
        $cached = $this->cache->get($cache_key);

        if ($cached !== null) {
            return $cached;
        }

        $form = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}formflow_forms WHERE id = %s",
                $form_id
            )
        );

        if ($form) {
            $this->cache->set($cache_key, $form, 600);
        }

        return $form;
    }

    /**
     * Get PDF template ID from form settings.
     *
     * @since 2.1.0
     * @param object $form Form object.
     * @return string|null Template ID or null.
     */
    private function getPDFTemplateId($form)
    {
        $settings = json_decode($form->settings, true);
        return $settings['autentique_pdf_template'] ?? null;
    }

    /**
     * Generate PDF from template.
     *
     * @since 2.1.0
     * @param string $template_id Template ID.
     * @param array $data Form data.
     * @return string Path to generated PDF.
     */
    private function generatePDFFromTemplate($template_id, array $data)
    {
        // TODO: Implement template-based PDF generation
        // Integration with PDF library and template engine
        throw new \Exception('Template-based PDF generation not yet implemented');
    }

    /**
     * Log activity.
     *
     * @since 2.1.0
     * @param string $submission_id Submission ID.
     * @param string $level Log level (info, warning, error).
     * @param string $message Log message.
     * @param array $context Additional context.
     * @return void
     */
    private function logActivity($submission_id, $level, $message, array $context = [])
    {
        $this->wpdb->insert(
            $this->wpdb->prefix . 'formflow_logs',
            [
                'id' => $this->generateUUID(),
                'submission_id' => $submission_id,
                'level' => $level,
                'message' => $message,
                'context' => wp_json_encode($context),
                'created_at' => current_time('mysql'),
            ]
        );
    }

    /**
     * Generate UUID v4.
     *
     * @since 2.1.0
     * @return string UUID.
     */
    private function generateUUID()
    {
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
}
