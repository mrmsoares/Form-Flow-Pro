<?php
/**
 * Tests for FormBuilderManager class.
 */

namespace FormFlowPro\Tests\Unit\FormBuilder;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\FormBuilder\FormBuilderManager;
use FormFlowPro\FormBuilder\FieldTypesRegistry;
use FormFlowPro\FormBuilder\DragDropBuilder;
use FormFlowPro\FormBuilder\FormVersioning;
use FormFlowPro\FormBuilder\ABTesting;

class FormBuilderManagerTest extends TestCase
{
    private $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test';
        $_SERVER['HTTP_REFERER'] = 'https://example.com';

        $this->manager = FormBuilderManager::getInstance();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_USER_AGENT']);
        unset($_SERVER['HTTP_REFERER']);

        parent::tearDown();
    }

    public function test_getInstance_returns_singleton()
    {
        $instance1 = FormBuilderManager::getInstance();
        $instance2 = FormBuilderManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_getFieldRegistry_returns_field_types_registry()
    {
        $registry = $this->manager->getFieldRegistry();

        $this->assertInstanceOf(FieldTypesRegistry::class, $registry);
    }

    public function test_getBuilder_returns_drag_drop_builder()
    {
        $builder = $this->manager->getBuilder();

        $this->assertInstanceOf(DragDropBuilder::class, $builder);
    }

    public function test_getVersioning_returns_form_versioning()
    {
        $versioning = $this->manager->getVersioning();

        $this->assertInstanceOf(FormVersioning::class, $versioning);
    }

    public function test_getABTesting_returns_ab_testing()
    {
        $abTesting = $this->manager->getABTesting();

        $this->assertInstanceOf(ABTesting::class, $abTesting);
    }

    public function test_handleFormSubmission_with_missing_form_id_returns_error()
    {
        $_POST = [];

        $output = $this->callAjaxEndpoint(function() {
            $this->manager->handleFormSubmission();
        });

        $this->assertFalse($output['success']);
        $this->assertArrayHasKey('data', $output);
        $this->assertStringContainsString('Invalid form', $output['data']['message']);
    }

    public function test_handleFormSubmission_with_invalid_nonce_returns_error()
    {
        $_POST = [
            'ffp_form_id' => 1,
            'ffp_nonce' => 'invalid_nonce',
        ];

        $output = $this->callAjaxEndpoint(function() {
            $this->manager->handleFormSubmission();
        });

        $this->assertFalse($output['success']);
        $this->assertArrayHasKey('data', $output);
        $this->assertStringContainsString('Security check failed', $output['data']['message']);
    }

    public function test_handleFormSubmission_with_valid_data_returns_success()
    {
        global $wpdb;

        $form_id = 123;
        $nonce = wp_create_nonce('ffp_submit_form_' . $form_id);

        $mockForm = (object)[
            'id' => $form_id,
            'title' => 'Test Form',
            'fields' => [
                [
                    'id' => 'field_1',
                    'name' => 'name',
                    'type' => 'text',
                    'label' => 'Name',
                    'required' => true,
                ],
                [
                    'id' => 'field_2',
                    'name' => 'email',
                    'type' => 'email',
                    'label' => 'Email',
                    'required' => true,
                ],
            ],
            'logic' => [],
            'notifications' => [],
            'settings' => [
                'success_message' => 'Thank you for your submission!',
            ],
        ];

        // Mock getForm to return our test form
        $builder = $this->createMock(DragDropBuilder::class);
        $builder->method('getForm')->willReturn($mockForm);

        $_POST = [
            'ffp_form_id' => $form_id,
            'ffp_nonce' => $nonce,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        set_nonce_verified(true);

        $output = $this->callAjaxEndpoint(function() {
            $this->manager->handleFormSubmission();
        });

        $this->assertTrue($output['success']);
        $this->assertArrayHasKey('data', $output);
    }

    public function test_handleFormSubmission_with_honeypot_filled_returns_fake_success()
    {
        global $wpdb;

        $form_id = 123;
        $nonce = wp_create_nonce('ffp_submit_form_' . $form_id);

        $mockForm = (object)[
            'id' => $form_id,
            'fields' => [],
            'logic' => [],
            'notifications' => [],
            'settings' => [
                'honeypot' => true,
                'success_message' => 'Thank you!',
            ],
        ];

        $builder = $this->createMock(DragDropBuilder::class);
        $builder->method('getForm')->willReturn($mockForm);

        $_POST = [
            'ffp_form_id' => $form_id,
            'ffp_nonce' => $nonce,
            'ffp_hp_' . $form_id => 'spam_content',
        ];

        set_nonce_verified(true);

        $output = $this->callAjaxEndpoint(function() {
            $this->manager->handleFormSubmission();
        });

        // Should return fake success to fool bots
        $this->assertTrue($output['success']);
    }

    public function test_handleFormSubmission_with_missing_required_field_returns_errors()
    {
        global $wpdb;

        $form_id = 123;
        $nonce = wp_create_nonce('ffp_submit_form_' . $form_id);

        $mockForm = (object)[
            'id' => $form_id,
            'fields' => [
                [
                    'id' => 'field_1',
                    'name' => 'email',
                    'type' => 'email',
                    'label' => 'Email',
                    'required' => true,
                ],
            ],
            'logic' => [],
            'notifications' => [],
            'settings' => [],
        ];

        $builder = $this->createMock(DragDropBuilder::class);
        $builder->method('getForm')->willReturn($mockForm);

        $_POST = [
            'ffp_form_id' => $form_id,
            'ffp_nonce' => $nonce,
            // Missing email field
        ];

        set_nonce_verified(true);

        $output = $this->callAjaxEndpoint(function() {
            $this->manager->handleFormSubmission();
        });

        $this->assertFalse($output['success']);
        $this->assertArrayHasKey('errors', $output['data']);
    }

    public function test_getTemplates_returns_predefined_templates()
    {
        $templates = $this->manager->getTemplates();

        $this->assertIsArray($templates);
        $this->assertArrayHasKey('contact', $templates);
        $this->assertArrayHasKey('newsletter', $templates);
        $this->assertArrayHasKey('feedback', $templates);
        $this->assertArrayHasKey('registration', $templates);
        $this->assertArrayHasKey('job_application', $templates);

        // Check contact template structure
        $contactTemplate = $templates['contact'];
        $this->assertArrayHasKey('name', $contactTemplate);
        $this->assertArrayHasKey('description', $contactTemplate);
        $this->assertArrayHasKey('category', $contactTemplate);
        $this->assertArrayHasKey('fields', $contactTemplate);
        $this->assertIsArray($contactTemplate['fields']);
    }

    public function test_exportForm_with_invalid_id_returns_null()
    {
        $builder = $this->createMock(DragDropBuilder::class);
        $builder->method('getForm')->willReturn(null);

        $export = $this->manager->exportForm(999);

        $this->assertNull($export);
    }

    public function test_exportForm_with_valid_id_returns_form_data()
    {
        $form_id = 123;

        $mockForm = (object)[
            'id' => $form_id,
            'title' => 'Test Form',
            'toArray' => function() {
                return [
                    'id' => 123,
                    'title' => 'Test Form',
                    'fields' => [],
                ];
            }
        ];

        $builder = $this->createMock(DragDropBuilder::class);
        $builder->method('getForm')->willReturn($mockForm);

        $export = $this->manager->exportForm($form_id);

        $this->assertIsArray($export);
        $this->assertArrayHasKey('version', $export);
        $this->assertArrayHasKey('exported_at', $export);
        $this->assertArrayHasKey('form', $export);
    }

    public function test_importForm_with_empty_data_returns_null()
    {
        $result = $this->manager->importForm([]);

        $this->assertNull($result);
    }

    public function test_importForm_with_valid_data_creates_form()
    {
        global $wpdb;

        $importData = [
            'form' => [
                'title' => 'Imported Form',
                'fields' => [
                    [
                        'id' => 'field_old',
                        'type' => 'text',
                        'label' => 'Test Field',
                    ],
                ],
            ],
        ];

        $wpdb->set_mock_result('insert_id', 456);

        $builder = $this->createMock(DragDropBuilder::class);
        $builder->method('createForm')->willReturn((object)['id' => 456]);

        $form_id = $this->manager->importForm($importData);

        $this->assertEquals(456, $form_id);
    }

    public function test_getFormAnalytics_returns_statistics()
    {
        global $wpdb;

        $form_id = 123;

        // Mock submissions count
        $wpdb->set_mock_result('get_var', 50);

        // Mock daily breakdown
        $wpdb->set_mock_result('get_results', [
            ['date' => '2024-01-01', 'count' => 10],
            ['date' => '2024-01-02', 'count' => 15],
            ['date' => '2024-01-03', 'count' => 25],
        ]);

        // Mock form views
        $mockForm = (object)[
            'toArray' => function() {
                return ['views' => 1000];
            }
        ];

        $builder = $this->createMock(DragDropBuilder::class);
        $builder->method('getForm')->willReturn($mockForm);

        $analytics = $this->manager->getFormAnalytics($form_id, '30days');

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('total_submissions', $analytics);
        $this->assertArrayHasKey('period_submissions', $analytics);
        $this->assertArrayHasKey('conversion_rate', $analytics);
        $this->assertArrayHasKey('daily_breakdown', $analytics);
    }

    public function test_ajaxGetFieldSchema_returns_field_type_schema()
    {
        $_POST = [
            'nonce' => wp_create_nonce('ffp_nonce'),
            'type' => 'text',
        ];

        set_nonce_verified(true);

        $output = $this->callAjaxEndpoint(function() {
            $this->manager->ajaxGetFieldSchema();
        });

        $this->assertTrue($output['success']);
        $this->assertArrayHasKey('data', $output);
    }

    public function test_ajaxValidateField_with_valid_field_returns_success()
    {
        $_POST = [
            'nonce' => wp_create_nonce('ffp_nonce'),
            'type' => 'email',
            'value' => 'test@example.com',
            'field' => json_encode(['required' => true]),
        ];

        set_nonce_verified(true);

        $output = $this->callAjaxEndpoint(function() {
            $this->manager->ajaxValidateField();
        });

        $this->assertTrue($output['success']);
        $this->assertArrayHasKey('data', $output);
        $this->assertTrue($output['data']['valid']);
    }

    public function test_ajaxValidateField_with_invalid_field_returns_errors()
    {
        $_POST = [
            'nonce' => wp_create_nonce('ffp_nonce'),
            'type' => 'email',
            'value' => 'invalid-email',
            'field' => json_encode(['required' => true]),
        ];

        set_nonce_verified(true);

        $output = $this->callAjaxEndpoint(function() {
            $this->manager->ajaxValidateField();
        });

        $this->assertFalse($output['success']);
        $this->assertArrayHasKey('data', $output);
        $this->assertArrayHasKey('errors', $output['data']);
    }

    public function test_parseSmartTags_replaces_field_tags()
    {
        $content = 'Hello {field:name}, your email is {field:email}';
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $result = $this->callPrivateMethod($this->manager, 'parseSmartTags', [$content, $data]);

        $this->assertEquals('Hello John Doe, your email is john@example.com', $result);
    }

    public function test_parseSmartTags_replaces_system_tags()
    {
        $content = 'Site: {site_name}, URL: {site_url}';
        $data = [];

        set_bloginfo('name', 'Test Site');

        $result = $this->callPrivateMethod($this->manager, 'parseSmartTags', [$content, $data]);

        $this->assertStringContainsString('Test Site', $result);
    }

    public function test_handleFileUpload_with_invalid_type_returns_error()
    {
        $_FILES['test_file'] = [
            'name' => 'test.exe',
            'type' => 'application/x-msdownload',
            'tmp_name' => '/tmp/phptest',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
        ];

        $field = [
            'allowed_types' => ['jpg', 'png', 'pdf'],
        ];

        $result = $this->callPrivateMethod($this->manager, 'handleFileUpload', ['test_file', $field]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_type', $result->get_error_code());
    }

    public function test_handleFileUpload_with_large_file_returns_error()
    {
        $_FILES['test_file'] = [
            'name' => 'large.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/phptest',
            'error' => UPLOAD_ERR_OK,
            'size' => 10 * 1024 * 1024, // 10MB
        ];

        $field = [
            'allowed_types' => ['jpg'],
            'max_size' => 5 * 1024 * 1024, // 5MB max
        ];

        $result = $this->callPrivateMethod($this->manager, 'handleFileUpload', ['test_file', $field]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('file_too_large', $result->get_error_code());
    }

    public function test_getClientIP_detects_forwarded_ip()
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.1, 10.0.0.1';

        $ip = $this->callPrivateMethod($this->manager, 'getClientIP', []);

        $this->assertEquals('192.168.1.1', $ip);
    }

    public function test_getClientIP_falls_back_to_remote_addr()
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        $ip = $this->callPrivateMethod($this->manager, 'getClientIP', []);

        $this->assertEquals('203.0.113.1', $ip);
    }

    public function test_restGetFieldTypes_returns_field_schemas()
    {
        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/field-types');

        $response = $this->manager->restGetFieldTypes($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();

        $this->assertArrayHasKey('types', $data);
        $this->assertArrayHasKey('categories', $data);
    }

    public function test_restGetTemplates_returns_all_templates()
    {
        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/templates');

        $response = $this->manager->restGetTemplates($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('contact', $data);
    }

    public function test_restExportForm_returns_form_export_data()
    {
        global $wpdb;

        $form_id = 123;

        $mockForm = (object)[
            'id' => $form_id,
            'toArray' => function() {
                return ['id' => 123, 'title' => 'Test'];
            }
        ];

        $builder = $this->createMock(DragDropBuilder::class);
        $builder->method('getForm')->willReturn($mockForm);

        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/forms/' . $form_id . '/export');
        $request->set_url_params(['id' => $form_id]);

        $response = $this->manager->restExportForm($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }
}
