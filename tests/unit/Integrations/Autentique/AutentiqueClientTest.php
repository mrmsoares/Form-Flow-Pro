<?php
/**
 * Tests for AutentiqueClient class.
 *
 * @package FormFlowPro
 * @subpackage Tests\Integrations\Autentique
 */

namespace FormFlowPro\Tests\Unit\Integrations\Autentique;

use FormFlowPro\Integrations\Autentique\AutentiqueClient;
use FormFlowPro\Tests\TestCase;

/**
 * AutentiqueClient test case.
 */
class AutentiqueClientTest extends TestCase
{
    /**
     * Client instance.
     *
     * @var AutentiqueClient
     */
    private $client;

    /**
     * Setup before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set API key option
        update_option('formflow_autentique_api_key', 'test_api_key_123');

        $this->client = new AutentiqueClient('test_api_key_123');
    }

    /**
     * Test constructor throws exception without API key.
     *
     * @return void
     */
    public function test_constructor_throws_exception_without_api_key()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Autentique API key not configured');

        delete_option('formflow_autentique_api_key');
        new AutentiqueClient(null);
    }

    /**
     * Test constructor accepts API key parameter.
     *
     * @return void
     */
    public function test_constructor_accepts_api_key_parameter()
    {
        $client = new AutentiqueClient('custom_api_key');
        $this->assertInstanceOf(AutentiqueClient::class, $client);
    }

    /**
     * Test createDocument with valid data.
     *
     * @return void
     */
    public function test_create_document_with_valid_data()
    {
        // Mock successful API response
        $this->mockHttpResponse(
            200,
            json_encode([
                'id' => 'doc_123',
                'name' => 'Test Document',
                'status' => 'pending',
            ])
        );

        $result = $this->client->createDocument([
            'name' => 'Test Document',
            'file' => base64_encode('PDF content'),
            'signers' => [
                ['email' => 'signer@example.com', 'name' => 'John Doe'],
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('doc_123', $result['id']);
        $this->assertEquals('Test Document', $result['name']);
    }

    /**
     * Test createDocument throws exception without name.
     *
     * @return void
     */
    public function test_create_document_requires_name()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Document name is required');

        $this->client->createDocument([
            'file' => 'file_content',
            'signers' => [['email' => 'test@example.com', 'name' => 'Test']],
        ]);
    }

    /**
     * Test createDocument throws exception without file.
     *
     * @return void
     */
    public function test_create_document_requires_file()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Document file is required');

        $this->client->createDocument([
            'name' => 'Test Document',
            'signers' => [['email' => 'test@example.com', 'name' => 'Test']],
        ]);
    }

    /**
     * Test createDocument throws exception without signers.
     *
     * @return void
     */
    public function test_create_document_requires_signers()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('At least one signer is required');

        $this->client->createDocument([
            'name' => 'Test Document',
            'file' => 'file_content',
        ]);
    }

    /**
     * Test getDocumentStatus returns document data.
     *
     * @return void
     */
    public function test_get_document_status_returns_data()
    {
        $this->mockHttpResponse(
            200,
            json_encode([
                'id' => 'doc_123',
                'status' => 'signed',
                'signers' => [
                    ['email' => 'test@example.com', 'signed' => true],
                ],
            ])
        );

        $result = $this->client->getDocumentStatus('doc_123');

        $this->assertIsArray($result);
        $this->assertEquals('doc_123', $result['id']);
        $this->assertEquals('signed', $result['status']);
    }

    /**
     * Test downloadDocument returns content.
     *
     * @return void
     */
    public function test_download_document_returns_content()
    {
        // Mock API response with download URL
        $this->mockHttpResponse(
            200,
            json_encode([
                'download_url' => 'https://autentique.com/download/doc_123.pdf',
            ])
        );

        // Mock file download response
        $this->mockHttpDownload('PDF binary content');

        $result = $this->client->downloadDocument('doc_123');

        $this->assertEquals('PDF binary content', $result);
    }

    /**
     * Test cancelDocument sends correct data.
     *
     * @return void
     */
    public function test_cancel_document_sends_reason()
    {
        $this->mockHttpResponse(
            200,
            json_encode([
                'success' => true,
                'message' => 'Document cancelled',
            ])
        );

        $result = $this->client->cancelDocument('doc_123', 'Customer request');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    /**
     * Test resendEmail for specific signer.
     *
     * @return void
     */
    public function test_resend_email_for_signer()
    {
        $this->mockHttpResponse(
            200,
            json_encode([
                'success' => true,
                'message' => 'Email sent',
            ])
        );

        $result = $this->client->resendEmail('doc_123', 'signer@example.com');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    /**
     * Test API error handling.
     *
     * @return void
     */
    public function test_api_error_handling()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Autentique API Error \(400\)/');

        $this->mockHttpResponse(
            400,
            json_encode([
                'error' => 'Invalid request',
            ])
        );

        $this->client->getDocumentStatus('invalid_id');
    }

    /**
     * Test network error handling.
     *
     * @return void
     */
    public function test_network_error_handling()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Autentique API Error/');

        $this->mockHttpError('Connection timeout');

        $this->client->getDocumentStatus('doc_123');
    }

    /**
     * Test testConnection returns true on success.
     *
     * @return void
     */
    public function test_connection_test_returns_true_on_success()
    {
        $this->mockHttpResponse(
            200,
            json_encode(['account' => 'active'])
        );

        $result = $this->client->testConnection();

        $this->assertTrue($result);
    }

    /**
     * Test testConnection returns false on error.
     *
     * @return void
     */
    public function test_connection_test_returns_false_on_error()
    {
        $this->mockHttpError('Connection failed');

        $result = $this->client->testConnection();

        $this->assertFalse($result);
    }

    /**
     * Mock HTTP response.
     *
     * @param int $status_code HTTP status code.
     * @param string $body Response body.
     * @return void
     */
    private function mockHttpResponse($status_code, $body)
    {
        global $wp_http_mock_response;
        $wp_http_mock_response = [
            'response' => ['code' => $status_code],
            'body' => $body,
        ];
    }

    /**
     * Mock HTTP error.
     *
     * @param string $message Error message.
     * @return void
     */
    private function mockHttpError($message)
    {
        global $wp_http_mock_error;
        $wp_http_mock_error = $message;
    }

    /**
     * Mock HTTP file download.
     *
     * @param string $content File content.
     * @return void
     */
    private function mockHttpDownload($content)
    {
        global $wp_http_download_response;
        $wp_http_download_response = [
            'response' => ['code' => 200],
            'body' => $content,
        ];
    }
}
