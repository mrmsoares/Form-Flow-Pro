<?php
/**
 * Autentique API Client.
 *
 * Client for Autentique digital signature API.
 * Handles authentication, document creation, and status tracking.
 *
 * @package FormFlowPro
 * @subpackage Integrations\Autentique
 * @since 2.1.0
 *
 * @see https://docs.autentique.com.br/api/2
 */

namespace FormFlowPro\Integrations\Autentique;

/**
 * Autentique API Client class.
 *
 * Provides methods to interact with Autentique API:
 * - Create documents
 * - Add signers
 * - Track signature status
 * - Download signed documents
 *
 * @since 2.1.0
 */
class AutentiqueClient
{
    /**
     * API base URL.
     *
     * @since 2.1.0
     * @var string
     */
    private const API_BASE_URL = 'https://api.autentique.com.br';

    /**
     * API version.
     *
     * @since 2.1.0
     * @var string
     */
    private const API_VERSION = 'v2';

    /**
     * API key for authentication.
     *
     * @since 2.1.0
     * @var string
     */
    private $api_key;

    /**
     * HTTP timeout in seconds.
     *
     * @since 2.1.0
     * @var int
     */
    private $timeout;

    /**
     * Constructor.
     *
     * @since 2.1.0
     * @param string|null $api_key API key (defaults to WordPress option).
     * @param int $timeout HTTP timeout in seconds.
     */
    public function __construct($api_key = null, $timeout = 30)
    {
        $this->api_key = $api_key ?? get_option('formflow_autentique_api_key');
        $this->timeout = $timeout;

        if (empty($this->api_key)) {
            throw new \Exception('Autentique API key not configured');
        }
    }

    /**
     * Create a new document.
     *
     * @since 2.1.0
     * @param array $args Document arguments.
     * @return array Response with document ID and details.
     * @throws \Exception If API request fails.
     */
    public function createDocument(array $args)
    {
        $defaults = [
            'name' => '',
            'file' => '', // Base64 encoded PDF or file path
            'signers' => [],
            'sandbox' => false,
            'auto_close' => true,
            'send_automatic_email' => true,
        ];

        $document_data = array_merge($defaults, $args);

        // Validate required fields
        if (empty($document_data['name'])) {
            throw new \Exception('Document name is required');
        }

        if (empty($document_data['file'])) {
            throw new \Exception('Document file is required');
        }

        if (empty($document_data['signers'])) {
            throw new \Exception('At least one signer is required');
        }

        $response = $this->request('POST', '/documents', [
            'document' => $document_data,
        ]);

        return $response;
    }

    /**
     * Get document status.
     *
     * @since 2.1.0
     * @param string $document_id Document ID.
     * @return array Document status and details.
     * @throws \Exception If API request fails.
     */
    public function getDocumentStatus($document_id)
    {
        return $this->request('GET', "/documents/{$document_id}");
    }

    /**
     * Download signed document.
     *
     * @since 2.1.0
     * @param string $document_id Document ID.
     * @return string Binary PDF content.
     * @throws \Exception If API request fails.
     */
    public function downloadDocument($document_id)
    {
        $response = $this->request('GET', "/documents/{$document_id}/download");

        if (isset($response['download_url'])) {
            // Autentique provides download URL
            return $this->downloadFile($response['download_url']);
        }

        return $response;
    }

    /**
     * Cancel a document.
     *
     * @since 2.1.0
     * @param string $document_id Document ID.
     * @param string $reason Cancellation reason.
     * @return array Response.
     * @throws \Exception If API request fails.
     */
    public function cancelDocument($document_id, $reason = '')
    {
        return $this->request('POST', "/documents/{$document_id}/cancel", [
            'reason' => $reason,
        ]);
    }

    /**
     * Resend signature request email.
     *
     * @since 2.1.0
     * @param string $document_id Document ID.
     * @param string $signer_email Signer email.
     * @return array Response.
     * @throws \Exception If API request fails.
     */
    public function resendEmail($document_id, $signer_email)
    {
        return $this->request('POST', "/documents/{$document_id}/resend", [
            'email' => $signer_email,
        ]);
    }

    /**
     * Make HTTP request to Autentique API.
     *
     * @since 2.1.0
     * @param string $method HTTP method.
     * @param string $endpoint API endpoint.
     * @param array $data Request data.
     * @return array Response data.
     * @throws \Exception If request fails.
     */
    private function request($method, $endpoint, array $data = [])
    {
        $url = $this->getApiUrl($endpoint);

        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => $this->getHeaders(),
        ];

        if (!empty($data)) {
            if ($method === 'GET') {
                $url = add_query_arg($data, $url);
            } else {
                $args['body'] = wp_json_encode($data);
            }
        }

        $response = wp_remote_request($url, $args);

        return $this->handleResponse($response);
    }

    /**
     * Handle API response.
     *
     * @since 2.1.0
     * @param array|\WP_Error $response WordPress HTTP response.
     * @return array Parsed response data.
     * @throws \Exception If response indicates error.
     */
    private function handleResponse($response)
    {
        if (is_wp_error($response)) {
            throw new \Exception('Autentique API Error: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Handle different status codes
        if ($status_code >= 200 && $status_code < 300) {
            return $data;
        }

        // Error handling
        $error_message = $data['message'] ?? $data['error'] ?? 'Unknown error';

        throw new \Exception(
            sprintf('Autentique API Error (%d): %s', $status_code, $error_message)
        );
    }

    /**
     * Download file from URL.
     *
     * @since 2.1.0
     * @param string $url File URL.
     * @return string Binary file content.
     * @throws \Exception If download fails.
     */
    private function downloadFile($url)
    {
        $response = wp_remote_get($url, [
            'timeout' => $this->timeout,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Download failed: ' . $response->get_error_message());
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Get full API URL.
     *
     * @since 2.1.0
     * @param string $endpoint API endpoint.
     * @return string Full URL.
     */
    private function getApiUrl($endpoint)
    {
        return self::API_BASE_URL . '/' . self::API_VERSION . $endpoint;
    }

    /**
     * Get HTTP headers for API requests.
     *
     * @since 2.1.0
     * @return array Headers.
     */
    private function getHeaders()
    {
        return [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'FormFlow-Pro/' . FORMFLOW_VERSION,
        ];
    }

    /**
     * Test API connection.
     *
     * @since 2.1.0
     * @return bool True if connection successful.
     */
    public function testConnection()
    {
        try {
            // Test with a simple endpoint (adjust based on actual API)
            $this->request('GET', '/account');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
