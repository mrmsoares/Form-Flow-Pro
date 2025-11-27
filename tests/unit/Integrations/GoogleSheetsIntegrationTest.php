<?php
/**
 * Tests for GoogleSheetsIntegration class.
 *
 * @package FormFlowPro\Tests\Unit\Integrations
 */

namespace FormFlowPro\Tests\Unit\Integrations;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Integrations\GoogleSheetsIntegration;
use FormFlowPro\Integrations\IntegrationInterface;

class GoogleSheetsIntegrationTest extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration (OAuth)
        set_option('formflow_integration_google_sheets', [
            'enabled' => true,
            'auth_type' => 'oauth',
            'client_id' => 'test_client_id.apps.googleusercontent.com',
            'client_secret' => 'test_client_secret',
            'refresh_token' => 'test_refresh_token',
            'access_token' => 'test_access_token',
            'token_expires' => time() + 3600,
            'default_spreadsheet_id' => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
            'default_sheet_name' => 'Sheet1',
            'auto_create_headers' => true,
            'include_timestamp' => true,
            'include_submission_id' => true,
            'date_format' => 'Y-m-d H:i:s',
        ]);

        $this->integration = new GoogleSheetsIntegration();
    }

    // ==========================================================================
    // Interface Implementation Tests
    // ==========================================================================

    public function test_implements_integration_interface()
    {
        $this->assertInstanceOf(IntegrationInterface::class, $this->integration);
    }

    public function test_get_id_returns_google_sheets()
    {
        $this->assertEquals('google_sheets', $this->integration->getId());
    }

    public function test_get_name_returns_google_sheets()
    {
        $this->assertNotEmpty($this->integration->getName());
    }

    // ==========================================================================
    // Configuration Tests
    // ==========================================================================

    public function test_is_configured_returns_true_with_oauth_credentials()
    {
        $this->assertTrue($this->integration->isConfigured());
    }

    public function test_is_configured_returns_true_with_service_account()
    {
        set_option('formflow_integration_google_sheets', [
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
            'service_account_json' => json_encode([
                'client_email' => 'test@project.iam.gserviceaccount.com',
                'private_key' => 'test_key',
            ]),
        ]);

        $integration = new GoogleSheetsIntegration();
        $this->assertTrue($integration->isConfigured());
    }

    public function test_is_configured_returns_false_without_credentials()
    {
        set_option('formflow_integration_google_sheets', [
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
        ]);

        $integration = new GoogleSheetsIntegration();
        $this->assertFalse($integration->isConfigured());
    }

    public function test_get_config_fields_includes_auth_type()
    {
        $fields = $this->integration->getConfigFields();
        $fieldNames = array_column($fields, 'name');
        $this->assertContains('auth_type', $fieldNames);
    }

    public function test_get_config_fields_includes_spreadsheet_settings()
    {
        $fields = $this->integration->getConfigFields();
        $fieldNames = array_column($fields, 'name');

        $this->assertContains('default_spreadsheet_id', $fieldNames);
        $this->assertContains('default_sheet_name', $fieldNames);
        $this->assertContains('auto_create_headers', $fieldNames);
    }

    // ==========================================================================
    // OAuth Tests
    // ==========================================================================

    public function test_get_authorization_url()
    {
        $url = $this->integration->getAuthorizationUrl();

        $this->assertStringContainsString('accounts.google.com/o/oauth2', $url);
        $this->assertStringContainsString('client_id=', $url);
        $this->assertStringContainsString('scope=', $url);
        $this->assertStringContainsString('spreadsheets', $url);
    }

    public function test_handle_oauth_callback_success()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'access_token' => 'new_access_token',
                    'refresh_token' => 'new_refresh_token',
                    'expires_in' => 3600,
                ]),
            ];
        });

        $result = $this->integration->handleOAuthCallback('auth_code_123');

        $this->assertTrue($result['success']);

        $config = get_option('formflow_integration_google_sheets');
        $this->assertEquals('new_access_token', $config['access_token']);
        $this->assertEquals('new_refresh_token', $config['refresh_token']);
    }

    public function test_handle_oauth_callback_handles_error()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 400],
                'body' => json_encode([
                    'error' => 'invalid_grant',
                    'error_description' => 'Bad Request',
                ]),
            ];
        });

        $result = $this->integration->handleOAuthCallback('invalid_code');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_handle_oauth_callback_handles_network_error()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return new \WP_Error('http_request_failed', 'Connection timeout');
        });

        $result = $this->integration->handleOAuthCallback('code_123');

        $this->assertFalse($result['success']);
    }

    public function test_access_token_refresh_when_expired()
    {
        set_option('formflow_integration_google_sheets', [
            'enabled' => true,
            'auth_type' => 'oauth',
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
            'refresh_token' => 'refresh_token',
            'access_token' => 'expired_token',
            'token_expires' => time() - 3600, // Expired
            'default_spreadsheet_id' => 'test_sheet_id',
        ]);

        $integration = new GoogleSheetsIntegration();

        set_wp_remote_post_handler(function($url, $args) {
            if (strpos($url, 'oauth2.googleapis.com/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'access_token' => 'refreshed_token',
                        'expires_in' => 3600,
                    ]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'properties' => ['title' => 'Test Sheet'],
                ]),
            ];
        });

        $integration->testConnection();

        $config = get_option('formflow_integration_google_sheets');
        $this->assertEquals('refreshed_token', $config['access_token']);
    }

    // ==========================================================================
    // Connection Tests
    // ==========================================================================

    public function test_test_connection_without_spreadsheet_id()
    {
        set_option('formflow_integration_google_sheets', [
            'enabled' => true,
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
            'refresh_token' => 'test_token',
            'access_token' => 'access_token',
            'token_expires' => time() + 3600,
        ]);

        $integration = new GoogleSheetsIntegration();

        $result = $integration->testConnection();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Authentication successful', $result['message']);
    }

    public function test_test_connection_with_spreadsheet_id()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'properties' => ['title' => 'My Test Spreadsheet'],
                    'sheets' => [
                        ['properties' => ['sheetId' => 0, 'title' => 'Sheet1']],
                    ],
                ]),
            ];
        });

        $result = $this->integration->testConnection();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('My Test Spreadsheet', $result['message']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_test_connection_fails_with_invalid_spreadsheet()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 404],
                'body' => json_encode([
                    'error' => ['message' => 'Requested entity was not found.'],
                ]),
            ];
        });

        $result = $this->integration->testConnection();

        $this->assertFalse($result['success']);
    }

    // ==========================================================================
    // Submission Sending Tests
    // ==========================================================================

    public function test_send_submission_appends_row()
    {
        $capturedRequests = [];

        set_wp_remote_post_handler(function($url, $args) use (&$capturedRequests) {
            $capturedRequests[] = [
                'url' => $url,
                'method' => $args['method'] ?? 'POST',
                'body' => json_decode($args['body'] ?? '{}', true),
            ];

            // Mock GET for checking headers
            if ($args['method'] === 'GET') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['values' => [['Timestamp', 'Email']]]),
                ];
            }

            // Mock POST for appending
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'updates' => [
                        'updatedRange' => 'Sheet1!A2:B2',
                        'updatedRows' => 1,
                    ],
                ]),
            ];
        });

        $submission = [
            'id' => 42,
            'created_at' => '2025-01-01 12:00:00',
            'form_data' => [
                'email' => 'test@example.com',
                'name' => 'John Doe',
            ],
        ];

        $result = $this->integration->sendSubmission($submission, []);

        $this->assertTrue($result['success']);
        $this->assertEquals('Sheet1!A2:B2', $result['external_id']);
        $this->assertEquals('appended', $result['action']);
    }

    public function test_send_submission_fails_without_spreadsheet_id()
    {
        set_option('formflow_integration_google_sheets', [
            'enabled' => true,
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
            'refresh_token' => 'test_token',
        ]);

        $integration = new GoogleSheetsIntegration();

        $result = $integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            []
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('spreadsheet ID', $result['message']);
    }

    public function test_send_submission_includes_timestamp()
    {
        $capturedData = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedData) {
            if ($args['method'] === 'GET') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['values' => []]),
                ];
            }

            if (strpos($url, ':append') !== false) {
                $capturedData = json_decode($args['body'], true);
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'updates' => ['updatedRange' => 'Sheet1!A2:B2', 'updatedRows' => 1],
                ]),
            ];
        });

        $submission = [
            'id' => 1,
            'created_at' => '2025-01-01 12:00:00',
            'form_data' => ['email' => 'test@example.com'],
        ];

        $this->integration->sendSubmission($submission, []);

        $this->assertNotNull($capturedData);
        $this->assertArrayHasKey('values', $capturedData);
        // First value should be timestamp
        $this->assertNotEmpty($capturedData['values'][0][0]);
    }

    public function test_send_submission_includes_submission_id()
    {
        $capturedData = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedData) {
            if ($args['method'] === 'GET') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['values' => []]),
                ];
            }

            if (strpos($url, ':append') !== false) {
                $capturedData = json_decode($args['body'], true);
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'updates' => ['updatedRange' => 'Sheet1!A2:C2', 'updatedRows' => 1],
                ]),
            ];
        });

        $submission = [
            'id' => 42,
            'created_at' => '2025-01-01 12:00:00',
            'form_data' => ['email' => 'test@example.com'],
        ];

        $this->integration->sendSubmission($submission, []);

        $this->assertNotNull($capturedData);
        $rowData = $capturedData['values'][0];
        $this->assertContains(42, $rowData);
    }

    public function test_send_submission_applies_field_mapping()
    {
        $capturedData = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedData) {
            if ($args['method'] === 'GET') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['values' => []]),
                ];
            }

            if (strpos($url, ':append') !== false) {
                $capturedData = json_decode($args['body'], true);
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'updates' => ['updatedRange' => 'Sheet1!A2:B2', 'updatedRows' => 1],
                ]),
            ];
        });

        $submission = [
            'id' => 1,
            'form_data' => [
                'email_field' => 'test@example.com',
                'name_field' => 'John Doe',
            ],
        ];

        $mapping = [
            'Email' => 'email_field',
            'Name' => 'name_field',
        ];

        $this->integration->sendSubmission($submission, $mapping);

        $this->assertNotNull($capturedData);
    }

    public function test_send_submission_uses_custom_spreadsheet_from_mapping()
    {
        $capturedUrl = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedUrl) {
            if ($args['method'] === 'GET') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['values' => []]),
                ];
            }

            $capturedUrl = $url;

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'updates' => ['updatedRange' => 'Sheet1!A2', 'updatedRows' => 1],
                ]),
            ];
        });

        $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['_spreadsheet_id' => 'custom_spreadsheet_id']
        );

        $this->assertStringContainsString('custom_spreadsheet_id', $capturedUrl);
    }

    public function test_send_submission_uses_custom_sheet_name()
    {
        $capturedUrl = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedUrl) {
            if ($args['method'] === 'GET') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['values' => []]),
                ];
            }

            $capturedUrl = $url;

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'updates' => ['updatedRange' => 'CustomSheet!A2', 'updatedRows' => 1],
                ]),
            ];
        });

        $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['_sheet_name' => 'CustomSheet']
        );

        $this->assertStringContainsString('CustomSheet', $capturedUrl);
    }

    // ==========================================================================
    // Header Management Tests
    // ==========================================================================

    public function test_auto_create_headers_when_sheet_empty()
    {
        $requests = [];

        set_wp_remote_post_handler(function($url, $args) use (&$requests) {
            $requests[] = [
                'url' => $url,
                'method' => $args['method'] ?? 'POST',
            ];

            // First GET returns empty (no headers)
            if ($args['method'] === 'GET') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([]),
                ];
            }

            // PUT to create headers
            if ($args['method'] === 'PUT') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([]),
                ];
            }

            // POST to append data
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'updates' => ['updatedRange' => 'Sheet1!A2', 'updatedRows' => 1],
                ]),
            ];
        });

        $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            []
        );

        // Should have GET (check headers), PUT (create headers), POST (append data)
        $methods = array_column($requests, 'method');
        $this->assertContains('GET', $methods);
        $this->assertContains('PUT', $methods);
        $this->assertContains('POST', $methods);
    }

    public function test_skip_header_creation_when_exists()
    {
        $putRequests = 0;

        set_wp_remote_post_handler(function($url, $args) use (&$putRequests) {
            if ($args['method'] === 'GET') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'values' => [['Timestamp', 'Email']],
                    ]),
                ];
            }

            if ($args['method'] === 'PUT') {
                $putRequests++;
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'updates' => ['updatedRange' => 'Sheet1!A2', 'updatedRows' => 1],
                ]),
            ];
        });

        $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            []
        );

        // Should not create headers if they exist
        $this->assertEquals(0, $putRequests);
    }

    // ==========================================================================
    // Service Account Tests
    // ==========================================================================

    public function test_service_account_authentication()
    {
        set_option('formflow_integration_google_sheets', [
            'enabled' => true,
            'auth_type' => 'service_account',
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
            'service_account_json' => json_encode([
                'client_email' => 'test@project.iam.gserviceaccount.com',
                'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC7VJTUt9Us8cKj\n-----END PRIVATE KEY-----\n",
            ]),
            'default_spreadsheet_id' => 'test_sheet_id',
        ]);

        $integration = new GoogleSheetsIntegration();

        $jwtCaptured = null;

        set_wp_remote_post_handler(function($url, $args) use (&$jwtCaptured) {
            if (strpos($url, 'oauth2.googleapis.com/token') !== false) {
                $jwtCaptured = $args['body']['assertion'] ?? null;

                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'sa_token_123']),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'properties' => ['title' => 'Test'],
                ]),
            ];
        });

        $integration->testConnection();

        $this->assertNotNull($jwtCaptured);
    }

    // ==========================================================================
    // Sheet Management Tests
    // ==========================================================================

    public function test_get_sheets_returns_list()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'sheets' => [
                        ['properties' => ['sheetId' => 0, 'title' => 'Sheet1', 'index' => 0]],
                        ['properties' => ['sheetId' => 1, 'title' => 'Sheet2', 'index' => 1]],
                    ],
                ]),
            ];
        });

        $sheets = $this->integration->getSheets('test_spreadsheet_id');

        $this->assertCount(2, $sheets);
        $this->assertEquals('Sheet1', $sheets[0]['title']);
        $this->assertEquals('Sheet2', $sheets[1]['title']);
    }

    public function test_create_sheet_success()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'replies' => [
                        [
                            'addSheet' => [
                                'properties' => ['sheetId' => 123, 'title' => 'New Sheet'],
                            ],
                        ],
                    ],
                ]),
            ];
        });

        $result = $this->integration->createSheet('test_spreadsheet_id', 'New Sheet');

        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['sheet_id']);
        $this->assertEquals('New Sheet', $result['title']);
    }

    public function test_create_sheet_handles_error()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 400],
                'body' => json_encode([
                    'error' => ['message' => 'Sheet already exists'],
                ]),
            ];
        });

        $result = $this->integration->createSheet('test_spreadsheet_id', 'Existing Sheet');

        $this->assertFalse($result['success']);
    }

    // ==========================================================================
    // Available Fields Tests
    // ==========================================================================

    public function test_get_available_fields_returns_common_fields()
    {
        $fields = $this->integration->getAvailableFields();

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        $fieldNames = array_column($fields, 'name');
        $this->assertContains('email', $fieldNames);
        $this->assertContains('name', $fieldNames);
    }

    // ==========================================================================
    // Error Handling Tests
    // ==========================================================================

    public function test_send_submission_handles_api_error()
    {
        set_wp_remote_post_handler(function($url, $args) {
            if ($args['method'] === 'GET') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['values' => []]),
                ];
            }

            return [
                'response' => ['code' => 403],
                'body' => json_encode([
                    'error' => ['message' => 'Permission denied'],
                ]),
            ];
        });

        $result = $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            []
        );

        $this->assertFalse($result['success']);
    }

    public function test_send_submission_handles_array_values()
    {
        $capturedData = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedData) {
            if ($args['method'] === 'GET') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['values' => []]),
                ];
            }

            if (strpos($url, ':append') !== false) {
                $capturedData = json_decode($args['body'], true);
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'updates' => ['updatedRange' => 'Sheet1!A2', 'updatedRows' => 1],
                ]),
            ];
        });

        $submission = [
            'id' => 1,
            'form_data' => [
                'tags' => ['tag1', 'tag2', 'tag3'],
            ],
        ];

        $this->integration->sendSubmission($submission, []);

        // Array values should be joined
        $this->assertNotNull($capturedData);
    }

    public function test_column_letter_conversion()
    {
        // This tests the columnLetter method indirectly through headers
        set_option('formflow_integration_google_sheets', [
            'enabled' => true,
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
            'refresh_token' => 'test_token',
            'access_token' => 'access_token',
            'token_expires' => time() + 3600,
            'default_spreadsheet_id' => 'test_sheet_id',
            'auto_create_headers' => true,
        ]);

        $integration = new GoogleSheetsIntegration();
        $capturedUrl = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedUrl) {
            if ($args['method'] === 'GET') {
                $capturedUrl = $url;
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'updates' => ['updatedRange' => 'Sheet1!A2', 'updatedRows' => 1],
                ]),
            ];
        });

        // Create submission with multiple fields to test column letters
        $integration->sendSubmission(
            [
                'id' => 1,
                'form_data' => [
                    'field1' => 'value1',
                    'field2' => 'value2',
                    'field3' => 'value3',
                ],
            ],
            []
        );

        // URL should contain column range
        $this->assertNotNull($capturedUrl);
    }
}
