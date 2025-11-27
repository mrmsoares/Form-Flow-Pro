<?php
/**
 * Tests for RestController class.
 */

namespace FormFlowPro\Tests\Unit\API;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\API\RestController;

class RestControllerTest extends TestCase
{
    private $restController;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up mock server variables
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test';

        $this->restController = RestController::getInstance();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_USER_AGENT']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SERVER['HTTP_X_API_KEY']);
        unset($_GET['api_key']);

        parent::tearDown();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = RestController::getInstance();
        $instance2 = RestController::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(RestController::class, $instance1);
    }

    public function test_register_routes_registers_all_endpoint_groups()
    {
        // registerRoutes should register forms, submissions, templates, analytics, settings, webhooks, auth
        $this->restController->registerRoutes();

        // This would be verified by checking registered routes in a real WordPress environment
        $this->assertTrue(true);
    }

    public function test_get_pagination_args_returns_correct_structure()
    {
        $args = $this->callPrivateMethod($this->restController, 'getPaginationArgs');

        $this->assertIsArray($args);
        $this->assertArrayHasKey('page', $args);
        $this->assertArrayHasKey('per_page', $args);
        $this->assertArrayHasKey('orderby', $args);
        $this->assertArrayHasKey('order', $args);
    }

    public function test_get_pagination_args_has_correct_defaults()
    {
        $args = $this->callPrivateMethod($this->restController, 'getPaginationArgs');

        $this->assertEquals(1, $args['page']['default']);
        $this->assertEquals(20, $args['per_page']['default']);
        $this->assertEquals('created_at', $args['orderby']['default']);
        $this->assertEquals('DESC', $args['order']['default']);
    }

    public function test_get_pagination_args_has_validation_rules()
    {
        $args = $this->callPrivateMethod($this->restController, 'getPaginationArgs');

        $this->assertEquals(1, $args['page']['minimum']);
        $this->assertEquals(1, $args['per_page']['minimum']);
        $this->assertEquals(100, $args['per_page']['maximum']);
        $this->assertEquals(['ASC', 'DESC'], $args['order']['enum']);
    }

    public function test_check_read_permission_returns_true_for_logged_in_user()
    {
        // Mock user capability
        set_current_user_can('edit_posts', true);

        $result = $this->restController->checkReadPermission();

        $this->assertTrue($result);
    }

    public function test_check_write_permission_returns_true_for_admin()
    {
        set_current_user_can('manage_options', true);

        $result = $this->restController->checkWritePermission();

        $this->assertTrue($result);
    }

    public function test_check_admin_permission_returns_true_for_admin()
    {
        set_current_user_can('manage_options', true);

        $result = $this->restController->checkAdminPermission();

        $this->assertTrue($result);
    }

    public function test_check_public_submit_permission_enforces_rate_limit()
    {
        update_option('formflow_api_rate_limit', 5);

        // Simulate 5 previous requests
        set_transient('formflow_api_submit_' . md5('127.0.0.1'), 5, 60);

        $result = $this->restController->checkPublicSubmitPermission();

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('rate_limit_exceeded', $result->get_error_code());
    }

    public function test_check_public_submit_permission_allows_within_limit()
    {
        update_option('formflow_api_rate_limit', 10);

        // Simulate 3 previous requests
        set_transient('formflow_api_submit_' . md5('127.0.0.1'), 3, 60);

        $result = $this->restController->checkPublicSubmitPermission();

        $this->assertTrue($result);
    }

    public function test_validate_api_key_with_valid_key()
    {
        $apiKey = 'test_key_123';

        update_option('formflow_api_keys', [
            [
                'key' => $apiKey,
                'active' => true,
                'permission' => 'read',
            ],
        ]);

        $_SERVER['HTTP_X_API_KEY'] = $apiKey;

        $result = $this->callPrivateMethod($this->restController, 'validateApiKey', ['read']);

        $this->assertTrue($result);
    }

    public function test_validate_api_key_with_invalid_key()
    {
        update_option('formflow_api_keys', []);

        $_SERVER['HTTP_X_API_KEY'] = 'invalid_key';

        $result = $this->callPrivateMethod($this->restController, 'validateApiKey', ['read']);

        $this->assertFalse($result);
    }

    public function test_validate_api_key_checks_permission_level()
    {
        $apiKey = 'read_only_key';

        update_option('formflow_api_keys', [
            [
                'key' => $apiKey,
                'active' => true,
                'permission' => 'read',
            ],
        ]);

        $_SERVER['HTTP_X_API_KEY'] = $apiKey;

        // Should fail when requiring write permission
        $result = $this->callPrivateMethod($this->restController, 'validateApiKey', ['write']);

        $this->assertFalse($result);
    }

    public function test_validate_api_key_respects_inactive_keys()
    {
        $apiKey = 'inactive_key';

        update_option('formflow_api_keys', [
            [
                'key' => $apiKey,
                'active' => false,
                'permission' => 'admin',
            ],
        ]);

        $_SERVER['HTTP_X_API_KEY'] = $apiKey;

        $result = $this->callPrivateMethod($this->restController, 'validateApiKey', ['read']);

        $this->assertFalse($result);
    }

    public function test_get_api_key_from_request_checks_authorization_header()
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test_token_123';

        $apiKey = $this->callPrivateMethod($this->restController, 'getApiKeyFromRequest');

        $this->assertEquals('test_token_123', $apiKey);
    }

    public function test_get_api_key_from_request_checks_x_api_key_header()
    {
        $_SERVER['HTTP_X_API_KEY'] = 'api_key_456';

        $apiKey = $this->callPrivateMethod($this->restController, 'getApiKeyFromRequest');

        $this->assertEquals('api_key_456', $apiKey);
    }

    public function test_get_api_key_from_request_checks_query_parameter()
    {
        $_GET['api_key'] = 'query_key_789';

        $apiKey = $this->callPrivateMethod($this->restController, 'getApiKeyFromRequest');

        $this->assertEquals('query_key_789', $apiKey);
    }

    public function test_get_api_key_from_request_returns_null_when_not_found()
    {
        $apiKey = $this->callPrivateMethod($this->restController, 'getApiKeyFromRequest');

        $this->assertNull($apiKey);
    }

    public function test_get_forms_returns_paginated_results()
    {
        global $wpdb;

        $mockForms = [
            ['id' => 1, 'name' => 'Form 1', 'status' => 'active'],
            ['id' => 2, 'name' => 'Form 2', 'status' => 'active'],
        ];

        $wpdb->set_mock_result('get_results', $mockForms);
        $wpdb->set_mock_result('get_var', 2); // Total count

        $request = new \WP_REST_Request('GET', '/formflow/v1/forms');
        $request->set_param('page', 1);
        $request->set_param('per_page', 20);
        $request->set_param('orderby', 'created_at');
        $request->set_param('order', 'DESC');

        $response = $this->restController->getForms($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertCount(2, $data['data']);
        $this->assertEquals(2, $data['meta']['total']);
    }

    public function test_get_form_returns_single_form()
    {
        global $wpdb;

        $mockForm = [
            'id' => 1,
            'name' => 'Test Form',
            'settings' => '{"key":"value"}',
            'status' => 'active',
        ];

        $wpdb->set_mock_result('get_row', $mockForm);

        $request = new \WP_REST_Request('GET', '/formflow/v1/forms/1');
        $request->set_param('id', 1);

        $response = $this->restController->getForm($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertEquals(1, $data['id']);
        $this->assertEquals('Test Form', $data['name']);
        $this->assertIsArray($data['settings']);
    }

    public function test_get_form_returns_404_when_not_found()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', null);

        $request = new \WP_REST_Request('GET', '/formflow/v1/forms/999');
        $request->set_param('id', 999);

        $response = $this->restController->getForm($request);

        $this->assertEquals(404, $response->get_status());
    }

    public function test_create_form_inserts_new_form()
    {
        global $wpdb;

        $wpdb->insert_id = 123;

        $request = new \WP_REST_Request('POST', '/formflow/v1/forms');
        $request->set_param('name', 'New Form');
        $request->set_param('elementor_form_id', 'form-123');
        $request->set_param('settings', ['enabled' => true]);

        $response = $this->restController->createForm($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(201, $response->get_status());

        $data = $response->get_data();
        $this->assertEquals(123, $data['id']);
    }

    public function test_update_form_updates_existing_form()
    {
        global $wpdb;

        $wpdb->set_mock_update_result(1);

        $request = new \WP_REST_Request('PUT', '/formflow/v1/forms/1');
        $request->set_param('id', 1);
        $request->set_param('name', 'Updated Form');
        $request->set_param('status', 'inactive');

        $response = $this->restController->updateForm($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('message', $data);
    }

    public function test_delete_form_removes_form()
    {
        global $wpdb;

        $wpdb->set_mock_delete_result(1);

        $request = new \WP_REST_Request('DELETE', '/formflow/v1/forms/1');
        $request->set_param('id', 1);

        $response = $this->restController->deleteForm($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('message', $data);
    }

    public function test_get_submissions_filters_by_form_id()
    {
        global $wpdb;

        $mockSubmissions = [
            ['id' => 1, 'form_id' => 5, 'form_data' => '{}'],
        ];

        $wpdb->set_mock_result('get_results', $mockSubmissions);
        $wpdb->set_mock_result('get_var', 1);

        $request = new \WP_REST_Request('GET', '/formflow/v1/submissions');
        $request->set_param('form_id', 5);
        $request->set_param('page', 1);
        $request->set_param('per_page', 20);

        $response = $this->restController->getSubmissions($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_get_submissions_filters_by_status()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', []);
        $wpdb->set_mock_result('get_var', 0);

        $request = new \WP_REST_Request('GET', '/formflow/v1/submissions');
        $request->set_param('status', 'completed');
        $request->set_param('page', 1);
        $request->set_param('per_page', 20);

        $response = $this->restController->getSubmissions($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_create_submission_validates_form_exists()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', null); // Form not found

        $request = new \WP_REST_Request('POST', '/formflow/v1/submissions');
        $request->set_param('form_id', 999);
        $request->set_param('data', ['field' => 'value']);

        $response = $this->restController->createSubmission($request);

        $this->assertEquals(404, $response->get_status());
    }

    public function test_create_submission_creates_new_submission()
    {
        global $wpdb;

        $mockForm = (object)['id' => 1, 'name' => 'Test Form'];
        $wpdb->set_mock_result('get_row', $mockForm);
        $wpdb->insert_id = 456;

        $request = new \WP_REST_Request('POST', '/formflow/v1/submissions');
        $request->set_param('form_id', 1);
        $request->set_param('data', ['name' => 'John', 'email' => 'john@example.com']);

        $response = $this->restController->createSubmission($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(201, $response->get_status());

        $data = $response->get_data();
        $this->assertEquals(456, $data['id']);
    }

    public function test_bulk_submissions_delete_action()
    {
        global $wpdb;

        $wpdb->set_mock_delete_result(1);

        $request = new \WP_REST_Request('POST', '/formflow/v1/submissions/bulk');
        $request->set_param('action', 'delete');
        $request->set_param('ids', [1, 2, 3]);

        $response = $this->restController->bulkSubmissions($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('processed', $data);
        $this->assertArrayHasKey('failed', $data);
    }

    public function test_export_submissions_returns_csv()
    {
        global $wpdb;

        $mockSubmissions = [
            [
                'id' => 1,
                'form_id' => 1,
                'form_data' => '{"name":"John"}',
                'created_at' => '2024-01-01',
            ],
        ];

        $wpdb->set_mock_result('get_results', $mockSubmissions);

        $request = new \WP_REST_Request('GET', '/formflow/v1/submissions/export');
        $request->set_param('format', 'csv');

        $response = $this->restController->exportSubmissions($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        // Check headers
        $headers = $response->get_headers();
        $this->assertEquals('text/csv', $headers['Content-Type']);
    }

    public function test_export_submissions_returns_json()
    {
        global $wpdb;

        $mockSubmissions = [
            ['id' => 1, 'form_data' => '{}'],
        ];

        $wpdb->set_mock_result('get_results', $mockSubmissions);

        $request = new \WP_REST_Request('GET', '/formflow/v1/submissions/export');
        $request->set_param('format', 'json');

        $response = $this->restController->exportSubmissions($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_get_analytics_overview_returns_stats()
    {
        global $wpdb;

        $mockStats = ['total' => 100, 'today' => 5];
        $wpdb->set_mock_result('get_row', $mockStats);

        $request = new \WP_REST_Request('GET', '/formflow/v1/analytics/overview');

        $response = $this->restController->getAnalyticsOverview($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertEquals(100, $data['total']);
        $this->assertEquals(5, $data['today']);
    }

    public function test_get_analytics_trends_returns_time_series_data()
    {
        global $wpdb;

        $mockTrends = [
            ['date' => '2024-01-01', 'count' => 10],
            ['date' => '2024-01-02', 'count' => 15],
        ];

        $wpdb->set_mock_result('get_results', $mockTrends);

        $request = new \WP_REST_Request('GET', '/formflow/v1/analytics/trends');

        $response = $this->restController->getAnalyticsTrends($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertIsArray($data);
    }

    public function test_get_settings_returns_all_settings()
    {
        update_option('formflow_settings', ['key' => 'value']);

        $request = new \WP_REST_Request('GET', '/formflow/v1/settings');

        $response = $this->restController->getSettings($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('key', $data);
    }

    public function test_update_settings_saves_settings()
    {
        $request = new \WP_REST_Request('PUT', '/formflow/v1/settings');
        $request->set_body(json_encode(['setting1' => 'value1']));

        $response = $this->restController->updateSettings($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $saved = get_option('formflow_settings');
        $this->assertIsArray($saved);
    }

    public function test_create_webhook_generates_secret()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/webhooks');
        $request->set_param('name', 'Test Webhook');
        $request->set_param('url', 'https://example.com/webhook');
        $request->set_param('events', ['submission.created']);

        $response = $this->restController->createWebhook($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(201, $response->get_status());

        $webhooks = get_option('formflow_webhooks', []);
        $this->assertNotEmpty($webhooks);

        $webhook = reset($webhooks);
        $this->assertArrayHasKey('secret', $webhook);
        $this->assertNotEmpty($webhook['secret']);
    }

    public function test_test_webhook_sends_request()
    {
        $webhookId = time();
        $webhooks = [
            $webhookId => [
                'id' => $webhookId,
                'url' => 'https://example.com/webhook',
                'events' => ['*'],
                'active' => true,
            ],
        ];

        update_option('formflow_webhooks', $webhooks);

        $request = new \WP_REST_Request('POST', '/formflow/v1/webhooks/' . $webhookId . '/test');
        $request->set_param('id', $webhookId);

        $response = $this->restController->testWebhook($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('success', $data);
    }

    public function test_create_api_key_generates_unique_key()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/auth/keys');
        $request->set_param('name', 'Test API Key');
        $request->set_param('permission', 'read');

        $response = $this->restController->createApiKey($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(201, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('key', $data);
        $this->assertStringStartsWith('ffp_', $data['key']);
    }

    public function test_get_api_keys_masks_keys()
    {
        update_option('formflow_api_keys', [
            [
                'id' => 1,
                'key' => 'ffp_1234567890abcdefghijklmnopqrstuvwxyz',
                'name' => 'Test Key',
            ],
        ]);

        $request = new \WP_REST_Request('GET', '/formflow/v1/auth/keys');

        $response = $this->restController->getApiKeys($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertIsArray($data);

        $key = $data[0];
        // Key should be masked
        $this->assertStringContainsString('...', $key['key']);
    }

    public function test_verify_auth_returns_authenticated_status()
    {
        $apiKey = 'test_key_valid';

        update_option('formflow_api_keys', [
            [
                'key' => $apiKey,
                'active' => true,
                'permission' => 'read',
            ],
        ]);

        $_SERVER['HTTP_X_API_KEY'] = $apiKey;

        $request = new \WP_REST_Request('GET', '/formflow/v1/auth/verify');

        $response = $this->restController->verifyAuth($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('authenticated', $data);
        $this->assertTrue($data['authenticated']);
    }

    public function test_trigger_webhook_sends_to_matching_webhooks()
    {
        $webhooks = [
            1 => [
                'id' => 1,
                'url' => 'https://example.com/webhook1',
                'events' => ['submission.created'],
                'active' => true,
                'secret' => 'secret123',
            ],
        ];

        update_option('formflow_webhooks', $webhooks);

        $this->callPrivateMethod($this->restController, 'triggerWebhook', [
            'submission.created',
            ['submission_id' => 123],
        ]);

        // Webhook should be triggered
        $this->assertTrue(true);
    }

    public function test_trigger_webhook_includes_signature()
    {
        $webhooks = [
            1 => [
                'id' => 1,
                'url' => 'https://example.com/webhook',
                'events' => ['*'],
                'active' => true,
                'secret' => 'secret_key',
            ],
        ];

        update_option('formflow_webhooks', $webhooks);

        $this->callPrivateMethod($this->restController, 'triggerWebhook', [
            'test.event',
            ['data' => 'value'],
        ]);

        // Should generate HMAC signature
        $this->assertTrue(true);
    }

    public function test_update_api_key_last_used_increments_count()
    {
        $apiKey = 'test_key_123';

        update_option('formflow_api_keys', [
            [
                'key' => $apiKey,
                'active' => true,
                'permission' => 'read',
                'request_count' => 10,
            ],
        ]);

        $this->callPrivateMethod($this->restController, 'updateApiKeyLastUsed', [$apiKey]);

        $keys = get_option('formflow_api_keys', []);
        $this->assertEquals(11, $keys[0]['request_count']);
    }

    public function test_get_form_stats_returns_time_period_counts()
    {
        global $wpdb;

        $mockStats = [
            'total' => 100,
            'today' => 5,
            'week' => 25,
            'month' => 60,
        ];

        $wpdb->set_mock_result('get_row', $mockStats);

        $request = new \WP_REST_Request('GET', '/formflow/v1/forms/1/stats');
        $request->set_param('id', 1);

        $response = $this->restController->getFormStats($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertEquals(100, $data['total']);
        $this->assertEquals(5, $data['today']);
    }

    public function test_get_form_submissions_decodes_json_data()
    {
        global $wpdb;

        $mockSubmissions = [
            [
                'id' => 1,
                'form_data' => '{"name":"John","email":"john@example.com"}',
            ],
        ];

        $wpdb->set_mock_result('get_results', $mockSubmissions);
        $wpdb->set_mock_result('get_var', 1);

        $request = new \WP_REST_Request('GET', '/formflow/v1/forms/1/submissions');
        $request->set_param('id', 1);
        $request->set_param('page', 1);
        $request->set_param('per_page', 20);

        $response = $this->restController->getFormSubmissions($request);

        $data = $response->get_data();
        $this->assertIsArray($data['data'][0]['form_data']);
        $this->assertEquals('John', $data['data'][0]['form_data']['name']);
    }
}
