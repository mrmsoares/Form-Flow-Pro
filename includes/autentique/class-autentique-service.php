<?php

declare(strict_types=1);

namespace FormFlowPro\Autentique;

if (!defined('ABSPATH')) exit;

/**
 * Autentique API Service - Real GraphQL Integration
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
        $pdf_url = $pdf_generator->generate_submission_pdf($submission_id);

        // Prepare signers
        $signers = $this->prepare_signers($form_data);

        // Create document via GraphQL
        $mutation = '
        mutation CreateDocument($document: DocumentInput!) {
            createDocument(document: $document) {
                id
                name
                signatures {
                    public_id
                    email
                    created_at
                    action {
                        name
                    }
                    link {
                        short_link
                    }
                }
            }
        }';

        $variables = [
            'document' => [
                'name' => $submission->form_name . ' - Submission #' . $submission_id,
                'file' => [
                    'url' => $pdf_url
                ],
                'signers' => $signers,
                'show_audit_page' => true,
            ]
        ];

        $response = $this->graphql_request($mutation, $variables);

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

        // Get signature URL
        $signature_url = $signatures[0]['link']['short_link'] ?? null;

        return $signature_url;
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
            deleteDocument(id: $id) {
                id
            }
        }';

        $variables = ['id' => $document_id];
        $response = $this->graphql_request($mutation, $variables);

        return isset($response['data']['deleteDocument']['id']);
    }

    /**
     * Make GraphQL request
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
     */
    private function prepare_signers(array $form_data): array
    {
        $signers = [];

        // Try to get email and name from form data
        $email = $form_data['email'] ?? $form_data['user_email'] ?? '';
        $name = $form_data['name'] ?? $form_data['user_name'] ?? $form_data['full_name'] ?? 'User';

        if (!empty($email)) {
            $signers[] = [
                'email' => $email,
                'action' => 'SIGN',
                'positions' => [
                    [
                        'x' => '50',
                        'y' => '85',
                        'z' => '1',
                    ]
                ]
            ];
        }

        // Add company signer if configured
        $company_email = get_option('formflow_company_email');
        if ($company_email) {
            $signers[] = [
                'email' => $company_email,
                'action' => 'SIGN',
                'positions' => [
                    [
                        'x' => '50',
                        'y' => '90',
                        'z' => '1',
                    ]
                ]
            ];
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
    }
}
