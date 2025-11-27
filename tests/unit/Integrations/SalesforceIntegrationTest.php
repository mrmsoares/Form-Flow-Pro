<?php
/**
 * Tests for SalesforceIntegration class.
 *
 * @package FormFlowPro\Tests\Unit\Integrations
 */

namespace FormFlowPro\Tests\Unit\Integrations;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Integrations\SalesforceIntegration;
use FormFlowPro\Integrations\IntegrationInterface;

class SalesforceIntegrationTest extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        set_option('formflow_integration_salesforce', [
            'enabled' => true,
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'instance_url' => 'https://test.salesforce.com',
            'username' => 'test@example.com',
            'password' => 'test_password_and_token',
            'sandbox' => false,
            'default_object' => 'Lead',
            'lead_source' => 'Web Form',
            'duplicate_check' => true,
            'duplicate_action' => 'update',
        ]);

        $this->integration = new SalesforceIntegration();
    }

    // ==========================================================================
    // Interface Implementation Tests
    // ==========================================================================

    public function test_implements_integration_interface()
    {
        $this->assertInstanceOf(IntegrationInterface::class, $this->integration);
    }

    public function test_get_id_returns_salesforce()
    {
        $this->assertEquals('salesforce', $this->integration->getId());
    }

    public function test_get_name_returns_salesforce()
    {
        $this->assertNotEmpty($this->integration->getName());
    }

    // ==========================================================================
    // Configuration Tests
    // ==========================================================================

    public function test_is_configured_returns_true_when_credentials_set()
    {
        $this->assertTrue($this->integration->isConfigured());
    }

    public function test_is_configured_returns_false_when_missing_client_id()
    {
        set_option('formflow_integration_salesforce', [
            'client_secret' => 'test_secret',
            'instance_url' => 'https://test.salesforce.com',
        ]);

        $integration = new SalesforceIntegration();
        $this->assertFalse($integration->isConfigured());
    }

    public function test_is_configured_returns_false_when_missing_client_secret()
    {
        set_option('formflow_integration_salesforce', [
            'client_id' => 'test_id',
            'instance_url' => 'https://test.salesforce.com',
        ]);

        $integration = new SalesforceIntegration();
        $this->assertFalse($integration->isConfigured());
    }

    public function test_is_configured_returns_false_when_missing_instance_url()
    {
        set_option('formflow_integration_salesforce', [
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
        ]);

        $integration = new SalesforceIntegration();
        $this->assertFalse($integration->isConfigured());
    }

    public function test_get_config_fields_returns_array()
    {
        $fields = $this->integration->getConfigFields();
        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);
    }

    public function test_get_config_fields_includes_required_fields()
    {
        $fields = $this->integration->getConfigFields();
        $fieldNames = array_column($fields, 'name');

        $this->assertContains('client_id', $fieldNames);
        $this->assertContains('client_secret', $fieldNames);
        $this->assertContains('instance_url', $fieldNames);
        $this->assertContains('username', $fieldNames);
        $this->assertContains('password', $fieldNames);
    }

    // ==========================================================================
    // OAuth Token Tests
    // ==========================================================================

    public function test_test_connection_gets_access_token()
    {
        set_wp_remote_post_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'access_token' => 'test_token_123',
                        'instance_url' => 'https://test.salesforce.com',
                    ]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'email' => 'test@example.com',
                    'organization_id' => 'org_123',
                ]),
            ];
        });

        $result = $this->integration->testConnection();

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_test_connection_fails_when_not_configured()
    {
        set_option('formflow_integration_salesforce', ['enabled' => true]);
        $integration = new SalesforceIntegration();

        $result = $integration->testConnection();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('configure', $result['message']);
    }

    public function test_test_connection_fails_when_oauth_fails()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 400],
                'body' => json_encode([
                    'error' => 'invalid_grant',
                    'error_description' => 'authentication failure',
                ]),
            ];
        });

        $result = $this->integration->testConnection();

        $this->assertFalse($result['success']);
    }

    public function test_access_token_is_cached()
    {
        $tokenRequestCount = 0;

        set_wp_remote_post_handler(function($url, $args) use (&$tokenRequestCount) {
            if (strpos($url, '/oauth2/token') !== false) {
                $tokenRequestCount++;
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'access_token' => 'cached_token_123',
                    ]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'email' => 'test@example.com',
                    'organization_id' => 'org_123',
                ]),
            ];
        });

        // First call should request token
        $this->integration->testConnection();
        $firstCount = $tokenRequestCount;

        // Second call should use cached token
        $this->integration->testConnection();
        $secondCount = $tokenRequestCount;

        $this->assertEquals($firstCount, $secondCount);
    }

    public function test_uses_sandbox_url_when_sandbox_enabled()
    {
        set_option('formflow_integration_salesforce', [
            'enabled' => true,
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
            'instance_url' => 'https://test.salesforce.com',
            'username' => 'test@example.com',
            'password' => 'test_pass',
            'sandbox' => true,
        ]);

        $capturedUrl = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedUrl) {
            $capturedUrl = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['access_token' => 'token']),
            ];
        });

        $integration = new SalesforceIntegration();
        $integration->testConnection();

        $this->assertStringContainsString('test.salesforce.com', $capturedUrl);
    }

    // ==========================================================================
    // Record Creation Tests
    // ==========================================================================

    public function test_send_submission_creates_lead()
    {
        $capturedRequest = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedRequest) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'token_123']),
                ];
            }

            // Mock search for duplicates - return empty
            if (strpos($url, '/query') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['records' => []]),
                ];
            }

            $capturedRequest = [
                'url' => $url,
                'method' => $args['method'] ?? 'POST',
                'body' => json_decode($args['body'], true),
            ];

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'lead_123',
                    'success' => true,
                ]),
            ];
        });

        $submission = [
            'id' => 1,
            'form_data' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'company' => 'Acme Corp',
            ],
        ];

        $mapping = [
            'FirstName' => 'first_name',
            'LastName' => 'last_name',
            'Email' => 'email',
            'Company' => 'company',
        ];

        $result = $this->integration->sendSubmission($submission, $mapping);

        $this->assertTrue($result['success']);
        $this->assertEquals('lead_123', $result['external_id']);
        $this->assertEquals('created', $result['action']);
        $this->assertStringContainsString('/sobjects/Lead', $capturedRequest['url']);
    }

    public function test_send_submission_adds_lead_source()
    {
        $capturedData = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedData) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'token_123']),
                ];
            }

            if (strpos($url, '/query') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['records' => []]),
                ];
            }

            $capturedData = json_decode($args['body'], true);

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'lead_123']),
            ];
        });

        $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['Email' => 'email', '_object_type' => 'Lead']
        );

        $this->assertEquals('Web Form', $capturedData['LeadSource']);
    }

    public function test_send_submission_fails_when_not_configured()
    {
        set_option('formflow_integration_salesforce', ['enabled' => true]);
        $integration = new SalesforceIntegration();

        $result = $integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            []
        );

        $this->assertFalse($result['success']);
    }

    public function test_send_submission_handles_api_error()
    {
        set_wp_remote_post_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'token_123']),
                ];
            }

            if (strpos($url, '/query') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['records' => []]),
                ];
            }

            return [
                'response' => ['code' => 400],
                'body' => json_encode([
                    [
                        'errorCode' => 'REQUIRED_FIELD_MISSING',
                        'message' => 'Required field missing: Company',
                    ],
                ]),
            ];
        });

        $result = $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['Email' => 'email']
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Company', $result['message']);
    }

    // ==========================================================================
    // Duplicate Handling Tests
    // ==========================================================================

    public function test_duplicate_check_finds_existing_record()
    {
        set_wp_remote_post_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'token_123']),
                ];
            }

            if (strpos($url, '/query') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'records' => [
                            ['Id' => 'existing_lead_123', 'Email' => 'test@example.com'],
                        ],
                    ]),
                ];
            }

            return [
                'response' => ['code' => 204],
                'body' => '',
            ];
        });

        $result = $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['Email' => 'email']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('updated', $result['action']);
        $this->assertEquals('existing_lead_123', $result['external_id']);
    }

    public function test_duplicate_action_skip()
    {
        set_option('formflow_integration_salesforce', [
            'enabled' => true,
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
            'instance_url' => 'https://test.salesforce.com',
            'username' => 'test@example.com',
            'password' => 'test_pass',
            'duplicate_check' => true,
            'duplicate_action' => 'skip',
        ]);

        $integration = new SalesforceIntegration();

        set_wp_remote_post_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'token_123']),
                ];
            }

            if (strpos($url, '/query') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'records' => [['Id' => 'existing_123']],
                    ]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'new_123']),
            ];
        });

        $result = $integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['Email' => 'email']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('skipped', $result['action']);
    }

    public function test_duplicate_action_create()
    {
        set_option('formflow_integration_salesforce', [
            'enabled' => true,
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
            'instance_url' => 'https://test.salesforce.com',
            'username' => 'test@example.com',
            'password' => 'test_pass',
            'duplicate_check' => true,
            'duplicate_action' => 'create',
        ]);

        $integration = new SalesforceIntegration();

        set_wp_remote_post_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'token_123']),
                ];
            }

            if (strpos($url, '/query') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'records' => [['Id' => 'existing_123']],
                    ]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'new_123']),
            ];
        });

        $result = $integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['Email' => 'email']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('created', $result['action']);
    }

    // ==========================================================================
    // Record Update Tests
    // ==========================================================================

    public function test_update_record_uses_patch_method()
    {
        $capturedMethod = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedMethod) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'token_123']),
                ];
            }

            if (strpos($url, '/query') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'records' => [['Id' => 'lead_123']],
                    ]),
                ];
            }

            $capturedMethod = $args['method'] ?? 'POST';

            return [
                'response' => ['code' => 204],
                'body' => '',
            ];
        });

        $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['Email' => 'email']
        );

        $this->assertEquals('PATCH', $capturedMethod);
    }

    public function test_update_record_handles_204_response()
    {
        set_wp_remote_post_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'token_123']),
                ];
            }

            if (strpos($url, '/query') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'records' => [['Id' => 'lead_123']],
                    ]),
                ];
            }

            // PATCH returns 204 No Content on success
            return [
                'response' => ['code' => 204],
                'body' => '',
            ];
        });

        $result = $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['Email' => 'email']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('updated', $result['action']);
    }

    // ==========================================================================
    // Available Fields Tests
    // ==========================================================================

    public function test_get_available_fields_returns_default_when_not_configured()
    {
        set_option('formflow_integration_salesforce', ['enabled' => true]);
        $integration = new SalesforceIntegration();

        $fields = $integration->getAvailableFields();

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        $fieldNames = array_column($fields, 'name');
        $this->assertContains('Email', $fieldNames);
        $this->assertContains('LastName', $fieldNames);
    }

    public function test_get_available_fields_fetches_from_api()
    {
        set_wp_remote_post_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'token_123']),
                ];
            }

            if (strpos($url, '/describe') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'fields' => [
                            [
                                'name' => 'Email',
                                'label' => 'Email',
                                'type' => 'email',
                                'createable' => true,
                                'updateable' => true,
                                'nillable' => true,
                            ],
                            [
                                'name' => 'LastName',
                                'label' => 'Last Name',
                                'type' => 'string',
                                'createable' => true,
                                'updateable' => true,
                                'nillable' => false,
                            ],
                        ],
                    ]),
                ];
            }

            return ['response' => ['code' => 404], 'body' => ''];
        });

        $fields = $this->integration->getAvailableFields();

        $fieldNames = array_column($fields, 'name');
        $this->assertContains('Email', $fieldNames);
        $this->assertContains('LastName', $fieldNames);
    }

    public function test_get_available_fields_excludes_non_createable_fields()
    {
        set_wp_remote_post_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'token_123']),
                ];
            }

            if (strpos($url, '/describe') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'fields' => [
                            [
                                'name' => 'Id',
                                'label' => 'Record ID',
                                'type' => 'id',
                                'createable' => false,
                                'updateable' => false,
                                'nillable' => false,
                            ],
                            [
                                'name' => 'Email',
                                'label' => 'Email',
                                'type' => 'email',
                                'createable' => true,
                                'updateable' => true,
                                'nillable' => true,
                            ],
                        ],
                    ]),
                ];
            }

            return ['response' => ['code' => 404], 'body' => ''];
        });

        $fields = $this->integration->getAvailableFields();

        $fieldNames = array_column($fields, 'name');
        $this->assertNotContains('Id', $fieldNames);
        $this->assertContains('Email', $fieldNames);
    }

    public function test_get_available_fields_caches_result()
    {
        $apiCallCount = 0;

        set_wp_remote_post_handler(function($url, $args) use (&$apiCallCount) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'token_123']),
                ];
            }

            if (strpos($url, '/describe') !== false) {
                $apiCallCount++;
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['fields' => []]),
                ];
            }

            return ['response' => ['code' => 404], 'body' => ''];
        });

        $this->integration->getAvailableFields();
        $firstCallCount = $apiCallCount;

        $this->integration->getAvailableFields();
        $secondCallCount = $apiCallCount;

        // Should use cached result on second call
        $this->assertEquals($firstCallCount, $secondCallCount);
    }

    // ==========================================================================
    // Object Type Tests
    // ==========================================================================

    public function test_send_submission_creates_contact()
    {
        $capturedUrl = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedUrl) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'token_123']),
                ];
            }

            if (strpos($url, '/query') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['records' => []]),
                ];
            }

            $capturedUrl = $url;

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'contact_123']),
            ];
        });

        $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['Email' => 'email', '_object_type' => 'Contact']
        );

        $this->assertStringContainsString('/sobjects/Contact', $capturedUrl);
    }

    public function test_send_submission_respects_default_object()
    {
        set_option('formflow_integration_salesforce', [
            'enabled' => true,
            'client_id' => 'test_id',
            'client_secret' => 'test_secret',
            'instance_url' => 'https://test.salesforce.com',
            'username' => 'test@example.com',
            'password' => 'test_pass',
            'default_object' => 'Case',
        ]);

        $integration = new SalesforceIntegration();
        $capturedUrl = null;

        set_wp_remote_post_handler(function($url, $args) use (&$capturedUrl) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'token_123']),
                ];
            }

            if (strpos($url, '/query') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['records' => []]),
                ];
            }

            $capturedUrl = $url;

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'case_123']),
            ];
        });

        $integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['Email' => 'email']
        );

        $this->assertStringContainsString('/sobjects/Case', $capturedUrl);
    }

    // ==========================================================================
    // Error Handling Tests
    // ==========================================================================

    public function test_send_submission_handles_auth_failure()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return [
                'response' => ['code' => 401],
                'body' => json_encode(['error' => 'invalid_grant']),
            ];
        });

        $result = $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['Email' => 'email']
        );

        $this->assertFalse($result['success']);
    }

    public function test_send_submission_handles_network_error()
    {
        set_wp_remote_post_handler(function($url, $args) {
            return new \WP_Error('http_request_failed', 'Connection timeout');
        });

        $result = $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['Email' => 'email']
        );

        $this->assertFalse($result['success']);
    }

    public function test_instance_url_updated_from_oauth_response()
    {
        set_wp_remote_post_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'access_token' => 'token_123',
                        'instance_url' => 'https://updated-instance.salesforce.com',
                    ]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'email' => 'test@example.com',
                    'organization_id' => 'org_123',
                ]),
            ];
        });

        $this->integration->testConnection();

        $config = get_option('formflow_integration_salesforce');
        $this->assertEquals('https://updated-instance.salesforce.com', $config['instance_url']);
    }
}
