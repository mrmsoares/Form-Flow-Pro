<?php

declare(strict_types=1);

namespace FormFlowPro\Autentique;

if (!defined('ABSPATH')) exit;

/**
 * Autentique API Service - Real GraphQL Integration
 *
 * Conforme documentação oficial: https://docs.autentique.com.br/api/
 */
class Autentique_Service
{
    private const API_URL = 'https://api.autentique.com.br/v2/graphql';
    private string $api_key;

    public function __construct()
    {
        $this->api_key = get_option('autentique_api_key', '');
    }

    /**
     * Create document for signature
     */
    public function create_document(int $submission_id, array $form_data): ?string
    {
        if (empty($this->api_key)) {
            throw new \Exception('Autentique API key not configured');
        }

        global $wpdb;
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, f.name as form_name
             FROM {$wpdb->prefix}formflow_submissions s
             LEFT JOIN {$wpdb->prefix}formflow_forms f ON s.form_id = f.id
             WHERE s.id = %d",
            $submission_id
        ));

        if (!$submission) {
            throw new \Exception('Submission not found');
        }

        // Generate PDF first
        $pdf_generator = new \FormFlowPro\PDF\PDF_Generator();
        $pdf_path = $pdf_generator->generate_submission_pdf($submission_id);

        if (!file_exists($pdf_path)) {
            throw new \Exception('PDF file not found: ' . $pdf_path);
        }

        // Prepare signers (separado conforme API)
        $signers = $this->prepare_signers($form_data);

        // Prepare document data (separado conforme API)
        $document = [
            'name' => $submission->form_name . ' - Submission #' . $submission_id,
            'refusable' => true,
            'sortable' => false,
            'show_audit_page' => true,
        ];

        // Create document via GraphQL with multipart upload
        $response = $this->create_document_multipart($document, $signers, $pdf_path);

        if (isset($response['errors'])) {
            throw new \Exception('Autentique API error: ' . json_encode($response['errors']));
        }

        $document_id = $response['data']['createDocument']['id'] ?? null;
        $signatures = $response['data']['createDocument']['signatures'] ?? [];

        if (!$document_id) {
            throw new \Exception('Failed to create document');
        }

        // Save document info to submission
        $this->save_document_info($submission_id, $document_id, $signatures);

        // Save to autentique documents table
        $this->save_to_documents_table(
            $document_id,
            $document['name'],
            $submission_id,
            $signatures[0]['email'] ?? '',
            $signatures[0]['name'] ?? '',
            $signatures[0]['link']['short_link'] ?? '',
            $signatures
        );

        // Get signature URL (primeiro signatário)
        $signature_url = $signatures[0]['link']['short_link'] ?? null;

        // Clean up temporary PDF
        if (file_exists($pdf_path)) {
            @unlink($pdf_path);
        }

        return $signature_url;
    }

    /**
     * Create document with multipart/form-data upload
     * Conforme: https://docs.autentique.com.br/api/mutations/criando-um-documento
     */
    private function create_document_multipart(array $document, array $signers, string $file_path): array
    {
        // GraphQL Mutation conforme documentação oficial
        $mutation = 'mutation CreateDocumentMutation($document: DocumentInput!, $signers: [SignerInput!]!, $file: Upload!) {
            createDocument(document: $document, signers: $signers, file: $file) {
                id
                name
                refusable
                sortable
                created_at
                signatures {
                    public_id
                    name
                    email
                    created_at
                    action {
                        name
                    }
                    link {
                        short_link
                    }
                    user {
                        id
                        name
                        email
                    }
                }
            }
        }';

        // Variables (sem o file, que vai no multipart)
        $variables = [
            'document' => $document,
            'signers' => $signers,
            'file' => null, // Será substituído pelo upload
        ];

        // Operations JSON
        $operations = wp_json_encode([
            'query' => $mutation,
            'variables' => $variables,
        ]);

        // Map JSON (mapeia o arquivo para variables.file)
        $map = wp_json_encode([
            'file' => ['variables.file'],
        ]);

        // Boundary para multipart
        $boundary = wp_generate_password(24, false);

        // Build multipart body
        $body = $this->build_multipart_body($operations, $map, $file_path, $boundary);

        // Make request
        $response = wp_remote_post(self::API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body' => $body,
            'timeout' => 60, // Upload pode demorar
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('API request failed: ' . $response->get_error_message());
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        return $data ?? [];
    }

    /**
     * Build multipart/form-data body
     * Formato conforme spec: https://github.com/jaydenseric/graphql-multipart-request-spec
     */
    private function build_multipart_body(string $operations, string $map, string $file_path, string $boundary): string
    {
        $body = '';
        $eol = "\r\n";

        // Part 1: operations
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="operations"' . $eol . $eol;
        $body .= $operations . $eol;

        // Part 2: map
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="map"' . $eol . $eol;
        $body .= $map . $eol;

        // Part 3: file
        $filename = basename($file_path);
        $file_contents = file_get_contents($file_path);

        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . $eol;
        $body .= 'Content-Type: application/pdf' . $eol . $eol;
        $body .= $file_contents . $eol;

        // End boundary
        $body .= '--' . $boundary . '--' . $eol;

        return $body;
    }

    /**
     * Get document status
     */
    public function get_document_status(string $document_id): array
    {
        $query = '
        query GetDocument($id: ID!) {
            document(id: $id) {
                id
                name
                created_at
                signatures {
                    public_id
                    name
                    email
                    created_at
                    action {
                        name
                    }
                    link {
                        short_link
                    }
                    user {
                        name
                        email
                    }
                    viewed {
                        created_at
                    }
                    signed {
                        created_at
                    }
                    rejected {
                        created_at
                    }
                }
                files {
                    original
                    signed
                }
            }
        }';

        $variables = ['id' => $document_id];
        $response = $this->graphql_request($query, $variables);

        if (isset($response['errors'])) {
            throw new \Exception('Failed to get document status');
        }

        return $response['data']['document'] ?? [];
    }

    /**
     * List all documents
     */
    public function list_documents(int $limit = 10): array
    {
        $query = '
        query ListDocuments($limit: Int!) {
            documents(limit: $limit) {
                data {
                    id
                    name
                    created_at
                    signatures {
                        email
                        signed {
                            created_at
                        }
                    }
                }
            }
        }';

        $variables = ['limit' => $limit];
        $response = $this->graphql_request($query, $variables);

        return $response['data']['documents']['data'] ?? [];
    }

    /**
     * Delete document
     */
    public function delete_document(string $document_id): bool
    {
        $mutation = '
        mutation DeleteDocument($id: ID!) {
            deleteDocument(id: $id)
        }';

        $variables = ['id' => $document_id];
        $response = $this->graphql_request($mutation, $variables);

        return isset($response['data']['deleteDocument']) && $response['data']['deleteDocument'] === true;
    }

    /**
     * Make GraphQL request (sem upload de arquivo)
     */
    private function graphql_request(string $query, array $variables = []): array
    {
        $response = wp_remote_post(self::API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'query' => $query,
                'variables' => $variables,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('API request failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data ?? [];
    }

    /**
     * Prepare signers from form data
     * Conforme: https://docs.autentique.com.br/api/mutations/criando-um-documento
     */
    private function prepare_signers(array $form_data): array
    {
        $signers = [];

        // Try to get email and name from form data
        $email = $form_data['email'] ?? $form_data['user_email'] ?? '';
        $name = $form_data['name'] ?? $form_data['user_name'] ?? $form_data['full_name'] ?? '';

        if (!empty($email)) {
            $signer = [
                'email' => $email,
                'action' => 'SIGN',
            ];

            // Add name if available (opcional)
            if (!empty($name)) {
                $signer['name'] = $name;
            }

            // Add positions (CORRIGIDO: z deve ser int, não string)
            $signer['positions'] = [
                [
                    'x' => '50.0',
                    'y' => '85.0',
                    'z' => 1, // INT conforme documentação
                    'element' => 'SIGNATURE',
                ]
            ];

            $signers[] = $signer;
        }

        // Add company signer if configured
        $company_email = get_option('formflow_company_email');
        if ($company_email) {
            $signers[] = [
                'email' => $company_email,
                'action' => 'SIGN',
                'positions' => [
                    [
                        'x' => '50.0',
                        'y' => '90.0',
                        'z' => 1, // INT
                        'element' => 'SIGNATURE',
                    ]
                ]
            ];
        }

        if (empty($signers)) {
            throw new \Exception('No signers configured. Email is required.');
        }

        return $signers;
    }

    /**
     * Save document info to submission
     */
    private function save_document_info(int $submission_id, string $document_id, array $signatures): void
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'formflow_submissions',
            [
                'signature_document_id' => $document_id,
                'signature_status' => 'pending',
                'status' => 'pending_signature',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $submission_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        // Save signature links to metadata
        $metadata = $wpdb->get_var($wpdb->prepare(
            "SELECT metadata FROM {$wpdb->prefix}formflow_submissions WHERE id = %d",
            $submission_id
        ));

        $metadata = $metadata ? json_decode($metadata, true) : [];
        $metadata['autentique_signatures'] = $signatures;
        $metadata['signature_created_at'] = current_time('mysql');

        $wpdb->update(
            $wpdb->prefix . 'formflow_submissions',
            ['metadata' => wp_json_encode($metadata)],
            ['id' => $submission_id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * Update signature status from webhook
     */
    public function update_signature_status(string $document_id, string $status, ?string $signed_at = null): void
    {
        global $wpdb;

        $data = [
            'signature_status' => $status,
            'updated_at' => current_time('mysql'),
        ];

        if ($status === 'signed' && $signed_at) {
            $data['signature_completed_at'] = $signed_at;
            $data['status'] = 'completed';
        } elseif ($status === 'refused') {
            $data['status'] = 'failed';
        }

        $wpdb->update(
            $wpdb->prefix . 'formflow_submissions',
            $data,
            ['signature_document_id' => $document_id],
            array_fill(0, count($data), '%s'),
            ['%s']
        );

        // Log the update
        do_action('formflow_signature_status_updated', $document_id, $status);

        // Also update in autentique documents table
        $update_data = [
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ];

        if ($status === 'signed' && $signed_at) {
            $update_data['signed_at'] = $signed_at;
        }

        $wpdb->update(
            $wpdb->prefix . 'formflow_autentique_documents',
            $update_data,
            ['document_id' => $document_id],
            array_fill(0, count($update_data), '%s'),
            ['%s']
        );
    }

    /**
     * Save document to autentique documents table
     */
    private function save_to_documents_table(
        string $document_id,
        string $document_name,
        int $submission_id,
        string $signer_email,
        string $signer_name,
        string $signature_url,
        array $signatures
    ): void {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_autentique_documents',
            [
                'document_id' => $document_id,
                'document_name' => $document_name,
                'submission_id' => $submission_id,
                'signer_email' => $signer_email,
                'signer_name' => $signer_name,
                'status' => 'pending',
                'signature_url' => $signature_url,
                'metadata' => wp_json_encode(['signatures' => $signatures]),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Resend signature link
     * Note: Autentique doesn't have a specific "resend" endpoint,
     * so we'll retrieve the document and return the existing link
     */
    public function resend_signature_link(string $document_id): bool
    {
        try {
            $document = $this->get_document_status($document_id);

            if (empty($document)) {
                return false;
            }

            // Get the signer's email and link
            $signatures = $document['signatures'] ?? [];

            foreach ($signatures as $signature) {
                // Only resend to pending signers
                if (empty($signature['signed'])) {
                    $email = $signature['email'] ?? '';
                    $link = $signature['link']['short_link'] ?? '';

                    if ($email && $link) {
                        // Send email notification with the link
                        $this->send_signature_reminder($email, $link, $document['name'] ?? '');
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log('FormFlow Autentique resend error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send signature reminder email
     */
    private function send_signature_reminder(string $email, string $link, string $document_name): bool
    {
        $subject = sprintf(
            __('Reminder: Please sign the document "%s"', 'formflow-pro'),
            $document_name
        );

        $message = sprintf(
            __("Hello,\n\nThis is a friendly reminder to sign the document:\n\n%s\n\nPlease click the link below to sign:\n%s\n\nThank you!", 'formflow-pro'),
            $document_name,
            $link
        );

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        return wp_mail($email, $subject, $message, $headers);
    }
}
