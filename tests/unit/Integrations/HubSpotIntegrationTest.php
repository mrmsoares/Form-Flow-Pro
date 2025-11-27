<?php
/**
 * Tests for HubSpotIntegration class.
 *
 * @package FormFlowPro\Tests\Unit\Integrations
 */

namespace FormFlowPro\Tests\Unit\Integrations;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Integrations\HubSpotIntegration;
use FormFlowPro\Integrations\IntegrationInterface;

class HubSpotIntegrationTest extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        set_option('formflow_integration_hubspot', [
            'enabled' => true,
            'auth_type' => 'private_app',
            'access_token' => 'pat-na1-test-access-token',
            'portal_id' => '12345678',
            'default_object' => 'contacts',
            'lifecycle_stage' => 'lead',
            'lead_status' => 'NEW',
            'update_existing' => true,
            'create_timeline' => true,
        ]);

        $this->integration = new HubSpotIntegration();
    }

    // ==========================================================================
    // Interface Implementation Tests
    // ==========================================================================

    public function test_implements_integration_interface()
    {
        $this->assertInstanceOf(IntegrationInterface::class, $this->integration);
    }

    public function test_get_id_returns_hubspot()
    {
        $this->assertEquals('hubspot', $this->integration->getId());
    }

    public function test_get_name_returns_hubspot()
    {
        $this->assertNotEmpty($this->integration->getName());
    }

    // ==========================================================================
    // Configuration Tests
    // ==========================================================================

    public function test_is_configured_returns_true_with_access_token()
    {
        $this->assertTrue($this->integration->isConfigured());
    }

    public function test_is_configured_returns_true_with_api_key()
    {
        set_option('formflow_integration_hubspot', [
            'auth_type' => 'api_key',
            'api_key' => 'test_api_key',
        ]);

        $integration = new HubSpotIntegration();
        $this->assertTrue($integration->isConfigured());
    }

    public function test_is_configured_returns_false_without_credentials()
    {
        set_option('formflow_integration_hubspot', [
            'auth_type' => 'private_app',
        ]);

        $integration = new HubSpotIntegration();
        $this->assertFalse($integration->isConfigured());
    }

    public function test_get_config_fields_includes_auth_options()
    {
        $fields = $this->integration->getConfigFields();
        $fieldNames = array_column($fields, 'name');

        $this->assertContains('auth_type', $fieldNames);
        $this->assertContains('access_token', $fieldNames);
        $this->assertContains('api_key', $fieldNames);
    }

    public function test_get_config_fields_includes_contact_settings()
    {
        $fields = $this->integration->getConfigFields();
        $fieldNames = array_column($fields, 'name');

        $this->assertContains('lifecycle_stage', $fieldNames);
        $this->assertContains('lead_status', $fieldNames);
        $this->assertContains('update_existing', $fieldNames);
    }

    // ==========================================================================
    // Connection Test Tests
    // ==========================================================================

    public function test_test_connection_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'portalId' => 12345678,
                    'uiDomain' => 'app.hubspot.com',
                ]),
            ];
        });

        $result = $this->integration->testConnection();

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(12345678, $result['data']['portal_id']);
    }

    public function test_test_connection_fails_when_not_configured()
    {
        set_option('formflow_integration_hubspot', ['enabled' => true]);
        $integration = new HubSpotIntegration();

        $result = $integration->testConnection();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('configure', $result['message']);
    }

    public function test_test_connection_handles_api_error()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 401],
                'body' => json_encode([
                    'message' => 'Invalid authentication credentials',
                ]),
            ];
        });

        $result = $this->integration->testConnection();

        $this->assertFalse($result['success']);
    }

    public function test_test_connection_saves_portal_id()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'portalId' => 99999999,
                    'uiDomain' => 'app.hubspot.com',
                ]),
            ];
        });

        set_option('formflow_integration_hubspot', [
            'enabled' => true,
            'access_token' => 'test_token',
        ]);

        $integration = new HubSpotIntegration();
        $integration->testConnection();

        $config = get_option('formflow_integration_hubspot');
        $this->assertEquals('99999999', $config['portal_id']);
    }

    // ==========================================================================
    // Authentication Tests
    // ==========================================================================

    public function test_api_request_uses_bearer_token_with_private_app()
    {
        $capturedHeaders = null;

        set_wp_remote_request_handler(function($url, $args) use (&$capturedHeaders) {
            $capturedHeaders = $args['headers'] ?? [];
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['portalId' => 12345678]),
            ];
        });

        $this->integration->testConnection();

        $this->assertArrayHasKey('Authorization', $capturedHeaders);
        $this->assertStringContainsString('Bearer', $capturedHeaders['Authorization']);
    }

    public function test_api_request_uses_api_key_with_legacy_auth()
    {
        set_option('formflow_integration_hubspot', [
            'enabled' => true,
            'auth_type' => 'api_key',
            'api_key' => 'test_api_key_123',
        ]);

        $integration = new HubSpotIntegration();
        $capturedUrl = null;

        set_wp_remote_request_handler(function($url, $args) use (&$capturedUrl) {
            $capturedUrl = $url;
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['portalId' => 12345678]),
            ];
        });

        $integration->testConnection();

        $this->assertStringContainsString('hapikey=test_api_key_123', $capturedUrl);
    }

    // ==========================================================================
    // Contact Creation Tests
    // ==========================================================================

    public function test_send_submission_creates_contact()
    {
        $capturedRequest = null;

        set_wp_remote_request_handler(function($url, $args) use (&$capturedRequest) {
            // Mock search for duplicates - return empty
            if (strpos($url, '/search') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['results' => []]),
                ];
            }

            $capturedRequest = [
                'url' => $url,
                'method' => $args['method'],
                'body' => json_decode($args['body'], true),
            ];

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => '123456']),
            ];
        });

        $submission = [
            'id' => 1,
            'form_data' => [
                'email' => 'john@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
            ],
        ];

        $mapping = [
            'email' => 'email',
            'firstname' => 'first_name',
            'lastname' => 'last_name',
        ];

        $result = $this->integration->sendSubmission($submission, $mapping);

        $this->assertTrue($result['success']);
        $this->assertEquals('123456', $result['external_id']);
        $this->assertEquals('created', $result['action']);
        $this->assertStringContainsString('/crm/v3/objects/contacts', $capturedRequest['url']);
    }

    public function test_send_submission_adds_lifecycle_stage()
    {
        $capturedData = null;

        set_wp_remote_request_handler(function($url, $args) use (&$capturedData) {
            if (strpos($url, '/search') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['results' => []]),
                ];
            }

            $capturedData = json_decode($args['body'], true);

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => '123456']),
            ];
        });

        $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['email' => 'email']
        );

        $this->assertEquals('lead', $capturedData['properties']['lifecyclestage']);
        $this->assertEquals('NEW', $capturedData['properties']['hs_lead_status']);
    }

    public function test_send_submission_fails_when_not_configured()
    {
        set_option('formflow_integration_hubspot', ['enabled' => true]);
        $integration = new HubSpotIntegration();

        $result = $integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            []
        );

        $this->assertFalse($result['success']);
    }

    public function test_send_submission_handles_api_error()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/search') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['results' => []]),
                ];
            }

            return [
                'response' => ['code' => 400],
                'body' => json_encode([
                    'message' => 'Property values were not valid',
                    'category' => 'VALIDATION_ERROR',
                ]),
            ];
        });

        $result = $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'invalid-email']],
            ['email' => 'email']
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not valid', $result['message']);
    }

    // ==========================================================================
    // Contact Update Tests
    // ==========================================================================

    public function test_send_submission_updates_existing_contact()
    {
        $capturedMethod = null;
        $capturedContactId = null;

        set_wp_remote_request_handler(function($url, $args) use (&$capturedMethod, &$capturedContactId) {
            if (strpos($url, '/search') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'results' => [
                            ['id' => 'existing_contact_123'],
                        ],
                    ]),
                ];
            }

            if (strpos($url, '/contacts/') !== false) {
                $capturedMethod = $args['method'];
                preg_match('/\/contacts\/([^\/]+)/', $url, $matches);
                $capturedContactId = $matches[1] ?? null;
            }

            // Mock note creation
            if (strpos($url, '/notes') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['id' => 'note_123']),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'existing_contact_123']),
            ];
        });

        $result = $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'existing@example.com']],
            ['email' => 'email']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('updated', $result['action']);
        $this->assertEquals('existing_contact_123', $result['external_id']);
        $this->assertEquals('PATCH', $capturedMethod);
    }

    public function test_find_contact_by_email()
    {
        $capturedSearchBody = null;

        set_wp_remote_request_handler(function($url, $args) use (&$capturedSearchBody) {
            if (strpos($url, '/search') !== false) {
                $capturedSearchBody = json_decode($args['body'], true);
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'results' => [
                            ['id' => 'contact_found_123'],
                        ],
                    ]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'contact_found_123']),
            ];
        });

        // Trigger find by providing email in mapped data
        $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'search@example.com']],
            ['email' => 'email']
        );

        $this->assertNotNull($capturedSearchBody);
        $this->assertEquals('email', $capturedSearchBody['filterGroups'][0]['filters'][0]['propertyName']);
        $this->assertEquals('EQ', $capturedSearchBody['filterGroups'][0]['filters'][0]['operator']);
    }

    public function test_update_existing_disabled_creates_duplicate()
    {
        set_option('formflow_integration_hubspot', [
            'enabled' => true,
            'access_token' => 'test_token',
            'update_existing' => false,
        ]);

        $integration = new HubSpotIntegration();
        $createCalled = false;

        set_wp_remote_request_handler(function($url, $args) use (&$createCalled) {
            // Even if duplicate exists, should not search
            if (strpos($url, '/search') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['results' => [['id' => 'existing_123']]]),
                ];
            }

            if (strpos($url, '/crm/v3/objects/contacts') !== false && $args['method'] === 'POST') {
                $createCalled = true;
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'new_123']),
            ];
        });

        $result = $integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['email' => 'email']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('created', $result['action']);
        $this->assertTrue($createCalled);
    }

    // ==========================================================================
    // Timeline Event Tests
    // ==========================================================================

    public function test_create_timeline_event_as_note()
    {
        $noteCaptured = null;

        set_wp_remote_request_handler(function($url, $args) use (&$noteCaptured) {
            if (strpos($url, '/search') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['results' => []]),
                ];
            }

            if (strpos($url, '/notes') !== false) {
                $noteCaptured = json_decode($args['body'], true);
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['id' => 'note_123']),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'contact_123']),
            ];
        });

        $this->integration->sendSubmission(
            ['id' => 42, 'form_data' => ['email' => 'test@example.com']],
            ['email' => 'email']
        );

        $this->assertNotNull($noteCaptured);
        $this->assertArrayHasKey('properties', $noteCaptured);
        $this->assertStringContainsString('42', $noteCaptured['properties']['hs_note_body']);
    }

    public function test_timeline_disabled_no_note_created()
    {
        set_option('formflow_integration_hubspot', [
            'enabled' => true,
            'access_token' => 'test_token',
            'create_timeline' => false,
        ]);

        $integration = new HubSpotIntegration();
        $noteRequested = false;

        set_wp_remote_request_handler(function($url, $args) use (&$noteRequested) {
            if (strpos($url, '/search') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['results' => []]),
                ];
            }

            if (strpos($url, '/notes') !== false) {
                $noteRequested = true;
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'contact_123']),
            ];
        });

        $integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['email' => 'email']
        );

        $this->assertFalse($noteRequested);
    }

    // ==========================================================================
    // Object Type Tests
    // ==========================================================================

    public function test_send_submission_creates_deal()
    {
        $capturedUrl = null;

        set_wp_remote_request_handler(function($url, $args) use (&$capturedUrl) {
            $capturedUrl = $url;

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'deal_123']),
            ];
        });

        $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['dealname' => 'Big Deal']],
            ['dealname' => 'dealname', '_object_type' => 'deals']
        );

        $this->assertStringContainsString('/crm/v3/objects/deals', $capturedUrl);
    }

    public function test_send_submission_creates_ticket()
    {
        $capturedUrl = null;

        set_wp_remote_request_handler(function($url, $args) use (&$capturedUrl) {
            $capturedUrl = $url;

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'ticket_123']),
            ];
        });

        $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['subject' => 'Support Request']],
            ['subject' => 'subject', '_object_type' => 'tickets']
        );

        $this->assertStringContainsString('/crm/v3/objects/tickets', $capturedUrl);
    }

    public function test_send_submission_respects_default_object()
    {
        set_option('formflow_integration_hubspot', [
            'enabled' => true,
            'access_token' => 'test_token',
            'default_object' => 'companies',
        ]);

        $integration = new HubSpotIntegration();
        $capturedUrl = null;

        set_wp_remote_request_handler(function($url, $args) use (&$capturedUrl) {
            $capturedUrl = $url;

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'company_123']),
            ];
        });

        $integration->sendSubmission(
            ['id' => 1, 'form_data' => ['name' => 'Acme Corp']],
            ['name' => 'name']
        );

        $this->assertStringContainsString('/crm/v3/objects/companies', $capturedUrl);
    }

    // ==========================================================================
    // Available Fields Tests
    // ==========================================================================

    public function test_get_available_fields_returns_default_when_not_configured()
    {
        set_option('formflow_integration_hubspot', ['enabled' => true]);
        $integration = new HubSpotIntegration();

        $fields = $integration->getAvailableFields();

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        $fieldNames = array_column($fields, 'name');
        $this->assertContains('email', $fieldNames);
        $this->assertContains('firstname', $fieldNames);
        $this->assertContains('lastname', $fieldNames);
    }

    public function test_get_available_fields_fetches_from_api()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/crm/v3/properties/contacts') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'results' => [
                            [
                                'name' => 'email',
                                'label' => 'Email',
                                'type' => 'string',
                                'required' => true,
                                'modificationMetadata' => ['readOnlyValue' => false],
                                'calculated' => false,
                            ],
                            [
                                'name' => 'firstname',
                                'label' => 'First Name',
                                'type' => 'string',
                                'required' => false,
                                'modificationMetadata' => ['readOnlyValue' => false],
                                'calculated' => false,
                            ],
                        ],
                    ]),
                ];
            }

            return [
                'response' => ['code' => 404],
                'body' => '',
            ];
        });

        $fields = $this->integration->getAvailableFields();

        $fieldNames = array_column($fields, 'name');
        $this->assertContains('email', $fieldNames);
        $this->assertContains('firstname', $fieldNames);
    }

    public function test_get_available_fields_excludes_readonly_fields()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/crm/v3/properties/contacts') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'results' => [
                            [
                                'name' => 'hs_object_id',
                                'label' => 'Record ID',
                                'type' => 'string',
                                'required' => false,
                                'modificationMetadata' => ['readOnlyValue' => true],
                                'calculated' => false,
                            ],
                            [
                                'name' => 'email',
                                'label' => 'Email',
                                'type' => 'string',
                                'required' => false,
                                'modificationMetadata' => ['readOnlyValue' => false],
                                'calculated' => false,
                            ],
                        ],
                    ]),
                ];
            }

            return [
                'response' => ['code' => 404],
                'body' => '',
            ];
        });

        $fields = $this->integration->getAvailableFields();

        $fieldNames = array_column($fields, 'name');
        $this->assertNotContains('hs_object_id', $fieldNames);
        $this->assertContains('email', $fieldNames);
    }

    public function test_get_available_fields_excludes_calculated_fields()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/crm/v3/properties/contacts') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'results' => [
                            [
                                'name' => 'calculated_field',
                                'label' => 'Calculated',
                                'type' => 'string',
                                'required' => false,
                                'modificationMetadata' => ['readOnlyValue' => false],
                                'calculated' => true,
                            ],
                            [
                                'name' => 'email',
                                'label' => 'Email',
                                'type' => 'string',
                                'required' => false,
                                'modificationMetadata' => ['readOnlyValue' => false],
                                'calculated' => false,
                            ],
                        ],
                    ]),
                ];
            }

            return [
                'response' => ['code' => 404],
                'body' => '',
            ];
        });

        $fields = $this->integration->getAvailableFields();

        $fieldNames = array_column($fields, 'name');
        $this->assertNotContains('calculated_field', $fieldNames);
        $this->assertContains('email', $fieldNames);
    }

    public function test_get_available_fields_caches_result()
    {
        $apiCallCount = 0;

        set_wp_remote_request_handler(function($url, $args) use (&$apiCallCount) {
            if (strpos($url, '/crm/v3/properties/contacts') !== false) {
                $apiCallCount++;
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['results' => []]),
                ];
            }

            return [
                'response' => ['code' => 404],
                'body' => '',
            ];
        });

        $this->integration->getAvailableFields();
        $firstCallCount = $apiCallCount;

        $this->integration->getAvailableFields();
        $secondCallCount = $apiCallCount;

        // Should use cached result on second call
        $this->assertEquals($firstCallCount, $secondCallCount);
    }

    // ==========================================================================
    // Error Handling Tests
    // ==========================================================================

    public function test_send_submission_handles_network_error()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return new \WP_Error('http_request_failed', 'Network timeout');
        });

        $result = $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['email' => 'email']
        );

        $this->assertFalse($result['success']);
    }

    public function test_timeline_event_failure_does_not_fail_submission()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/search') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['results' => []]),
                ];
            }

            // Contact creation succeeds
            if (strpos($url, '/contacts') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['id' => 'contact_123']),
                ];
            }

            // Note creation fails
            if (strpos($url, '/notes') !== false) {
                return [
                    'response' => ['code' => 500],
                    'body' => json_encode(['message' => 'Internal error']),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([]),
            ];
        });

        $result = $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com']],
            ['email' => 'email']
        );

        // Should still succeed even if note creation failed
        $this->assertTrue($result['success']);
    }

    public function test_properties_format_correct()
    {
        $capturedData = null;

        set_wp_remote_request_handler(function($url, $args) use (&$capturedData) {
            if (strpos($url, '/search') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['results' => []]),
                ];
            }

            if (strpos($url, '/contacts') !== false && $args['method'] === 'POST') {
                $capturedData = json_decode($args['body'], true);
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'contact_123']),
            ];
        });

        $this->integration->sendSubmission(
            ['id' => 1, 'form_data' => ['email' => 'test@example.com', 'name' => 'Test']],
            ['email' => 'email', 'firstname' => 'name']
        );

        $this->assertArrayHasKey('properties', $capturedData);
        $this->assertIsArray($capturedData['properties']);
    }
}
